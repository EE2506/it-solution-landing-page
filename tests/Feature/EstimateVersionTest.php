<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class EstimateVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['quote.owner_email' => 'owner@example.com']);
        $this->withoutVite();
    }

    public function test_private_lookup_is_neutral_and_an_expired_v1_link_can_be_replaced(): void
    {
        $quote = $this->submitWebsite();
        $quote->update(['snapshot' => Arr::except($quote->snapshot, ['revises'])]);
        $other = $this->submitWebsite();
        config(['quote.services.website.rate' => 1900000]);

        $this->get(route('estimates.show', $quote))->assertForbidden();
        $this->post('/find-estimate', ['reference' => $quote->reference, 'email' => 'different@example.com'])
            ->assertRedirect('/find-estimate')->assertSessionHas('lookup_requested', true);
        $this->assertDatabaseMissing('quote_deliveries', ['quote_request_id' => $quote->id, 'recipient' => 'access']);
        $this->post('/find-estimate', ['reference' => strtolower($quote->reference), 'email' => 'CLIENT@example.com'])
            ->assertRedirect('/find-estimate')->assertSessionHas('lookup_requested', true);
        $this->assertDatabaseHas('quote_deliveries', ['quote_request_id' => $quote->id, 'recipient' => 'access']);

        $links = [];
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$links): void {
            if (str_starts_with($event->message->getSubject(), 'Your private estimate link')) {
                $this->assertSame('client@example.com', $event->message->getTo()[0]->getAddress());
                preg_match('/href="([^"]+)"/', $event->message->getHtmlBody(), $match);
                $links[] = html_entity_decode($match[1]);
            }
        });

        $this->artisan('quotes:deliver')->assertSuccessful();
        $this->assertCount(1, $links);
        $this->get(str_replace($quote->reference, $other->reference, $links[0]))->assertForbidden();
        $this->get($links[0])->assertRedirect(route('estimates.show', $quote));
        $savedView = $this->get(route('estimates.show', $quote))->assertOk()->assertSee('₱15,000')->assertDontSee('₱19,000');
        $this->assertStringContainsString('no-store', $savedView->headers->get('Cache-Control'));
        $this->get(route('estimates.show', $other))->assertForbidden();
        $this->get(route('estimates.revise', $other))->assertForbidden();
        $this->get(route('estimates.pdf', $other))->assertForbidden();

        $pdf = $this->get(route('estimates.pdf', $quote))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));

        $this->travel(31)->minutes();
        $this->get($links[0])->assertForbidden()->assertSee('Request a fresh private link');
        $this->get(route('estimates.show', $quote))->assertForbidden();
        $this->get(route('estimates.pdf', $quote))->assertForbidden();
        $this->post('/find-estimate', ['reference' => $quote->reference, 'email' => 'client@example.com'])->assertRedirect('/find-estimate');
        $this->artisan('quotes:deliver')->assertSuccessful();
        $this->assertCount(2, $links);
        $this->assertNotSame($links[0], $links[1]);
        $this->get($links[1])->assertRedirect(route('estimates.show', $quote));
        $this->travelBack();
    }

    public function test_catalog_repricing_and_retirement_keep_original_and_revision_linked(): void
    {
        $scope = ['mode' => 'direct', 'service' => 'website', 'booking_tool' => 'supported', 'addons' => [
            'booking' => ['state' => 'selected', 'quantity' => 1],
        ]];
        $original = $this->submitWebsite($scope);
        $this->get('/owner/catalog')->assertRedirect('/owner/sign-in');
        $this->actingAs(User::factory()->create())->get('/owner/catalog')->assertForbidden();
        $owner = User::factory()->create(['is_owner' => true]);
        $this->actingAs($owner)->get('/owner/catalog')->assertOk()->assertSee('Business Website');
        $this->patch('/owner/catalog/services/website', [
            'rate' => '17000.50', 'enabled' => '1', 'rush' => '1', 'includes' => "Home and Contact pages\nHandover guide",
        ])->assertRedirect();
        $this->patch('/owner/catalog/addons/booking', ['rate' => '3000', 'enabled' => '0', 'rush' => '1'])->assertRedirect();
        $this->post('/owner/sign-out')->assertRedirect('/owner/sign-in');

        $preview = $this->postJson('/estimate', $scope)->assertOk()->assertJsonPath('estimate.total', 1700050)
            ->assertJsonPath('estimate.included.1', 'Handover guide')->assertJsonCount(1, 'estimate.one_time')->json('preview');
        $values = $scope + [
            'request_key' => (string) Str::uuid(), 'preview' => $preview, 'revision_of' => $original->reference,
            'contact' => ['name' => 'Revised client', 'email' => 'untrusted@example.com'],
        ];
        $this->postJson('/quotes', $values)->assertForbidden();

        $signed = URL::temporarySignedRoute('estimates.open', now()->addMinutes(30), ['quote' => $original->reference]);
        $this->get($signed)->assertRedirect();
        $this->get(route('estimates.revise', $original))->assertOk()->assertSee($original->reference);
        $saved = $this->postJson('/quotes', $values)->assertCreated();
        $this->postJson('/quotes', $values)->assertOk()->assertJsonPath('reference', $saved->json('reference'));
        $revision = QuoteRequest::where('reference', $saved->json('reference'))->sole();
        $this->assertSame($original->id, $revision->revises_id);
        $this->assertSame($original->id, $revision->series_id);
        $this->assertSame('client@example.com', $revision->contact['email']);
        $this->assertSame(1800000, $original->fresh()->snapshot['estimate']['total']);

        $this->get(route('estimates.show', $revision))->assertOk()
            ->assertSee($original->reference)->assertSee('₱17,000.50')->assertDontSee('Booking integration');
        $this->actingAs($owner)->get(route('owner.show', $original))->assertOk()->assertSee('Estimate history')->assertSee($revision->reference);
    }

    public function test_budget_deferrals_respect_essential_choices_and_dependencies(): void
    {
        $scope = ['mode' => 'direct', 'service' => 'website', 'sms_source' => 'booking', 'booking_tool' => 'supported', 'addons' => [
            'booking' => ['state' => 'selected', 'quantity' => 1],
            'sms' => ['state' => 'selected', 'quantity' => 1],
            'copy' => ['state' => 'selected', 'quantity' => 1],
        ]];
        $this->postJson('/estimate/budget', $scope + ['budget_centavos' => 2000000, 'essential' => ['booking']])
            ->assertOk()->assertJsonPath('total', 2050000)->assertJsonPath('options.0.total', 1800000)
            ->assertJsonPath('options.0.savings', 250000)->assertJsonPath('options.0.defer.sms', 'SMS integration')
            ->assertJsonPath('options.0.review.0', 'Copywriting');

        config(['quote.addons.sms.requires' => ['booking']]);
        $this->postJson('/estimate/budget', $scope + ['budget_centavos' => 2000000, 'essential' => ['sms']])
            ->assertOk()->assertJsonCount(0, 'options');
        $this->postJson('/estimate/budget', $scope + ['budget_centavos' => 1600000])
            ->assertOk()->assertJsonPath('options.0.total', 1500000)
            ->assertJsonPath('options.0.defer.booking', 'Booking integration')->assertJsonPath('options.0.defer.sms', 'SMS integration');
    }

    private function submitWebsite(array $scope = ['mode' => 'direct', 'service' => 'website']): QuoteRequest
    {
        $preview = $this->postJson('/estimate', $scope)->assertOk()->json('preview');
        $saved = $this->postJson('/quotes', $scope + [
            'request_key' => (string) Str::uuid(), 'preview' => $preview,
            'contact' => ['name' => 'Client', 'email' => 'client@example.com'],
        ])->assertCreated();

        return QuoteRequest::where('reference', $saved->json('reference'))->sole();
    }
}
