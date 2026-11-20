<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class QuoteBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['quote.owner_email' => 'owner@example.com']);
        $this->withoutVite();
    }

    public function test_salon_totals_scope_and_deferred_sms(): void
    {
        $scope = ['mode' => 'direct', 'service' => 'website', 'sms_source' => 'booking', 'booking_tool' => 'supported', 'addons' => [
            'booking' => ['state' => 'selected', 'quantity' => 1],
            'sms' => ['state' => 'selected', 'quantity' => 1],
        ]];
        $this->postJson('/estimate', $scope + ['goal' => null])->assertOk()->assertJsonPath('estimate.total', 2050000);
        $this->postJson('/estimate', $scope + ['priority' => true])->assertJsonPath('estimate.total', 2460000);
        $scope['addons']['sms']['state'] = 'later';
        $this->postJson('/estimate', $scope)->assertJsonPath('estimate.total', 1800000);
        $this->postJson('/estimate', $scope + ['priority' => true])->assertJsonPath('estimate.total', 2160000)
            ->assertJsonPath('estimate.later.0.id', 'sms');

        $scope['addons']['sms']['state'] = 'selected';
        $scope['sms_source'] = 'already_sends';
        $this->postJson('/estimate', $scope)->assertJsonPath('estimate.total', 1800000);
        $scope['sms_source'] = 'unknown';
        $this->postJson('/estimate', $scope)->assertJsonPath('estimate.review.0.id', 'sms');
    }

    public function test_guided_recommendation_and_standalone_installation(): void
    {
        $this->postJson('/estimate', ['mode' => 'guided', 'goal' => 'records', 'features' => ['sales_stock', 'counter_payments'], 'staff_count' => 3])
            ->assertOk()->assertJsonPath('estimate.name', 'Custom System')->assertJsonCount(1, 'estimate.review')
            ->assertJsonCount(0, 'estimate.one_time')->assertJsonPath('estimate.features.1', 'Record payments at the counter')
            ->assertJsonPath('estimate.features.2', 'Staff using the system: 3');
        $this->postJson('/estimate', ['mode' => 'guided', 'goal' => 'not_sure'])->assertOk();
        $this->postJson('/estimate', ['mode' => 'guided', 'goal' => 'appointments', 'appointment' => 'staff'])
            ->assertJsonPath('estimate.service', 'system');
        $this->postJson('/estimate', ['mode' => 'direct', 'service' => 'installation', 'installation' => 'scheduler',
            'addons' => ['booking' => ['state' => 'selected', 'quantity' => 1]],
        ])->assertOk()->assertJsonPath('estimate.total', 300000)->assertJsonCount(1, 'estimate.one_time');
        $this->postJson('/estimate', ['mode' => 'direct', 'service' => 'website', 'email_purpose' => 'inquiry',
            'addons' => ['email' => ['state' => 'selected', 'quantity' => 1]],
        ])->assertOk()->assertJsonCount(0, 'estimate.review')->assertJsonPath('estimate.total', 1500000);
    }

    public function test_repricing_requires_confirmation_and_saved_quote_is_private_and_immutable(): void
    {
        $this->get('/')->assertOk();
        $scope = ['mode' => 'direct', 'service' => 'website'];
        $request = $this->submission($scope);
        config(['quote.services.website.rate' => 1600000]);
        $updated = $this->postJson('/quotes', $request)->assertConflict()->assertJsonPath('estimate.total', 1600000);
        $this->assertDatabaseCount('quote_requests', 0);

        $request['preview'] = $updated->json('preview');
        $reference = $this->postJson('/quotes', $request)->assertCreated()->assertJsonPath('delivery', 'Pending')->json('reference');
        $this->postJson('/quotes', $request)->assertOk()->assertJsonPath('reference', $reference);
        $this->get('/quotes/'.$reference)->assertNotFound();
        $this->get('/owner')->assertRedirect('/owner/sign-in');
        $this->actingAs(User::factory()->create())->get('/owner')->assertForbidden();
        config(['quote.services.website.rate' => 1700000]);

        $quote = QuoteRequest::sole();
        $this->assertSame(1600000, $quote->snapshot['estimate']['total']);
        $this->assertStringNotContainsString('client@example.com', $quote->getRawOriginal('contact'));
        $this->assertDatabaseCount('quote_deliveries', 2);

        $owner = User::factory()->create(['is_owner' => true]);
        $this->assertNotSame($owner->email, $owner->getRawOriginal('email'));
        $this->post('/owner/sign-out');
        $this->post('/owner/sign-in', ['email' => $owner->email, 'password' => 'password'])->assertRedirect('/owner');
        $this->post('/owner/search', ['term' => 'CLIENT@example.com'])->assertRedirect('/owner');
        $this->get('/owner')->assertOk()->assertSee($reference);
        $this->get('/owner/quotes/'.$reference)->assertOk()->assertSee('₱16,000');
        $this->patch('/owner/quotes/'.$reference, ['status' => 'review', 'internal_notes' => 'Private follow-up'])->assertRedirect();
        $this->assertSame('review', $quote->fresh()->status);
        $this->assertSame('Private follow-up', $quote->fresh()->internal_notes);
        $this->assertSame(1600000, $quote->fresh()->snapshot['estimate']['total']);
    }

    public function test_failed_delivery_can_retry_without_resending_the_other_copy(): void
    {
        $this->postJson('/quotes', $this->submission(['mode' => 'direct', 'service' => 'website']))->assertCreated();
        $quote = QuoteRequest::sole();
        $client = $quote->deliveries()->where('recipient', 'client')->sole();
        $client->forceFill(['attempts' => 4])->save();
        $failClient = true;
        $sent = [];
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$failClient, &$sent, $quote): void {
            $email = $event->message->getTo()[0]->getAddress();
            $this->assertStringContainsString($quote->reference, $event->message->getSubject());
            if ($email === 'client@example.com' && $failClient) {
                throw new TransportException('Test transport rejected the delivery.');
            }
            $sent[] = $email;
        });

        $this->artisan('quotes:deliver')->assertSuccessful();
        $this->assertSame(['owner@example.com'], $sent);
        $this->assertSame('Failed', $client->fresh()->state());
        $this->actingAs(User::factory()->create(['is_owner' => true]))
            ->post(route('owner.retry', [$quote, $client]))->assertRedirect();
        $failClient = false;
        $this->artisan('quotes:deliver')->assertSuccessful();
        $this->assertSame(['owner@example.com', 'client@example.com'], $sent);
        $this->assertSame('Sent', $client->fresh()->state());
    }

    private function submission(array $scope): array
    {
        return $scope + [
            'request_key' => (string) Str::uuid(),
            'preview' => $this->postJson('/estimate', $scope)->assertOk()->json('preview'),
            'contact' => ['name' => 'Test Client', 'email' => 'client@example.com'],
        ];
    }
}
