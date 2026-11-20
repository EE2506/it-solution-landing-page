<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuildEstimate;
use App\Models\QuoteRequest;
use App\Services\BudgetComparison;
use App\Services\Estimate;
use App\Services\EstimateAccess;
use App\Services\QuoteCatalog;
use App\Support\PrivateEmail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(QuoteCatalog $catalog): Response
    {
        return $this->builder($catalog->active(), $catalog->discoveryUrl());
    }

    public function revise(Request $request, string $quote, EstimateAccess $access, QuoteCatalog $catalog): Response
    {
        $quote = $access->resolve($request, $quote);
        $active = $catalog->active();
        $snapshot = $quote->snapshot;
        $answers = $snapshot['answers'];
        $estimate = $snapshot['estimate'];
        $answers['addons'] = array_intersect_key($answers['addons'] ?? [], $active['addons']);
        $answers['monthly'] = array_values(array_intersect($answers['monthly'] ?? [], array_keys($active['months'][$estimate['service']] ?? [])));
        $available = isset($active['services'][$estimate['service']])
            && (! $estimate['installation'] || isset($active['installations'][$estimate['installation']]))
            && ($answers['mode'] === 'direct' || isset($active['goals'][$answers['goal']]))
            && (($answers['goal'] ?? null) !== 'appointments' || isset($active['appointment_answers'][$answers['appointment'] ?? 'unknown']));
        $retired = array_keys(array_diff_key(config('quote.addons'), $active['addons']));
        if (! isset($active['services'][$estimate['service']])) {
            $retired[] = $estimate['service'];
        }
        if ($estimate['installation'] && ! isset($active['installations'][$estimate['installation']])) {
            $retired[] = $estimate['installation'];
        }

        return $this->builder($active, $catalog->discoveryUrl(), [
            'reference' => $quote->reference,
            'available' => $available,
            'answers' => $answers,
            'original' => $estimate,
            'contact' => $quote->contact,
            'retired' => $retired,
        ]);
    }

    private function builder(array $catalog, ?string $discoveryUrl, ?array $revision = null): Response
    {
        return Inertia::render('Builder', [
            'catalog' => collect($catalog)->only(['services', 'installations', 'goals', 'feature_choices', 'appointment_answers', 'addons', 'months', 'rush_percent']),
            'requestKey' => (string) Str::uuid(),
            'revision' => $revision,
            'discoveryUrl' => $discoveryUrl,
        ]);
    }

    public function preview(BuildEstimate $request, Estimate $estimates): JsonResponse
    {
        return response()->json($this->previewData($estimates->build($request->validated())));
    }

    public function budget(BuildEstimate $request, QuoteCatalog $catalog, Estimate $estimates, BudgetComparison $comparison): JsonResponse
    {
        $active = $catalog->active();
        $target = $request->validate([
            'budget_centavos' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'essential' => ['sometimes', 'array', 'max:6'],
            'essential.*' => ['distinct', Rule::in([...array_keys(config('quote.addons')), 'priority'])],
        ]);

        return response()->json($comparison->compare($estimates, $request->validated(), $active, (int) $target['budget_centavos'], $target['essential'] ?? []));
    }

    public function store(BuildEstimate $request, Estimate $estimates, EstimateAccess $access): JsonResponse
    {
        $details = $request->validate([
            'request_key' => ['required', 'uuid'],
            'preview' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'contact' => ['required', 'array:name,email,business,notes'],
            'contact.name' => ['required', 'string', 'max:120'],
            'contact.email' => ['required', 'email:rfc', 'max:254'],
            'contact.business' => ['nullable', 'string', 'max:160'],
            'contact.notes' => ['nullable', 'string', 'max:3000'],
            'revision_of' => ['nullable', 'string', 'max:32'],
        ]);

        // Keep confirmation replays private to the browser session that saved the request.
        if ($reference = $request->session()->get('quotes.'.$details['request_key'])) {
            return $this->confirmation(QuoteRequest::where('reference', $reference)->firstOrFail());
        }

        $answers = $request->validated();
        $parent = isset($details['revision_of']) ? $access->resolve($request, $details['revision_of']) : null;
        $parentExpiry = $parent ? $access->expiry($request, $parent) : null;
        if ($parent) {
            $details['contact']['email'] = $parent->contact['email'];
        }
        $latest = $this->previewData($estimates->build($answers));
        if (! hash_equals($latest['preview'], $details['preview'])) {
            return response()->json($latest + ['message' => 'The estimate changed. Review the updated breakdown before sending.'], 409);
        }

        try {
            $quote = DB::transaction(function () use ($details, $answers, $latest, $parent): QuoteRequest {
                $submittedAt = now();
                $quote = QuoteRequest::create([
                    'reference' => 'EST-'.$submittedAt->copy()->timezone('Asia/Manila')->format('ym').'-'.Str::upper(Str::random(10)),
                    'request_key' => $details['request_key'],
                    'email_lookup' => PrivateEmail::lookup($details['contact']['email']),
                    'contact' => $details['contact'],
                    'revises_id' => $parent?->id,
                    'series_id' => $parent ? ($parent->series_id ?? $parent->id) : null,
                    'snapshot' => [
                        'answers' => $answers, 'estimate' => $latest['estimate'],
                        'submitted_at' => $submittedAt->toIso8601String(), 'revises' => $parent?->reference,
                    ],
                ]);
                $quote->deliveries()->createMany([
                    ['recipient' => 'client', 'available_at' => $submittedAt],
                    ['recipient' => 'owner', 'available_at' => $submittedAt],
                ]);

                return $quote;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $quote = QuoteRequest::where('request_key', $details['request_key'])->first();
            if ($quote === null) {
                throw $exception;
            }

            return response()->json(['message' => 'This request has already been recorded. Check your inbox for the reference.'], 409);
        }

        $request->session()->put('quotes.'.$details['request_key'], $quote->reference);
        if ($parentExpiry) {
            $access->grant($request, $quote, $parentExpiry);
        }
        Log::info('Quote request created', ['reference' => $quote->reference]);

        return $this->confirmation($quote, 201);
    }

    private function previewData(array $estimate): array
    {
        return ['estimate' => $estimate, 'preview' => hash_hmac('sha256', json_encode($estimate, JSON_THROW_ON_ERROR), config('app.key'))];
    }

    private function confirmation(QuoteRequest $quote, int $status = 200): JsonResponse
    {
        return response()->json([
            'reference' => $quote->reference,
            'delivery' => $quote->deliveries()->where('recipient', 'client')->firstOrFail()->state(),
            'url' => $quote->revises_id ? route('estimates.show', $quote) : null,
        ], $status);
    }
}
