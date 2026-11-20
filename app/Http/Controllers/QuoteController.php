<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuildEstimate;
use App\Models\QuoteRequest;
use App\Services\Estimate;
use App\Support\PrivateEmail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Builder', [
            'catalog' => collect(config('quote'))->only(['services', 'installations', 'goals', 'feature_choices', 'appointment_answers', 'addons', 'months', 'rush_percent']),
            'requestKey' => (string) Str::uuid(),
        ]);
    }

    public function preview(BuildEstimate $request, Estimate $estimates): JsonResponse
    {
        return response()->json($this->previewData($estimates->build($request->validated())));
    }

    public function store(BuildEstimate $request, Estimate $estimates): JsonResponse
    {
        $details = $request->validate([
            'request_key' => ['required', 'uuid'],
            'preview' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'contact' => ['required', 'array:name,email,business,notes'],
            'contact.name' => ['required', 'string', 'max:120'],
            'contact.email' => ['required', 'email:rfc', 'max:254'],
            'contact.business' => ['nullable', 'string', 'max:160'],
            'contact.notes' => ['nullable', 'string', 'max:3000'],
        ]);

        // Keep confirmation replays private to the browser session that saved the request.
        if ($reference = $request->session()->get('quotes.'.$details['request_key'])) {
            return $this->confirmation(QuoteRequest::where('reference', $reference)->firstOrFail());
        }

        $answers = $request->validated();
        $latest = $this->previewData($estimates->build($answers));
        if (! hash_equals($latest['preview'], $details['preview'])) {
            return response()->json($latest + ['message' => 'The estimate changed. Review the updated breakdown before sending.'], 409);
        }

        try {
            $quote = DB::transaction(function () use ($details, $answers, $latest): QuoteRequest {
                $submittedAt = now();
                $quote = QuoteRequest::create([
                    'reference' => 'EST-'.$submittedAt->copy()->timezone('Asia/Manila')->format('ym').'-'.Str::upper(Str::random(10)),
                    'request_key' => $details['request_key'],
                    'email_lookup' => PrivateEmail::lookup($details['contact']['email']),
                    'contact' => $details['contact'],
                    'snapshot' => ['answers' => $answers, 'estimate' => $latest['estimate'], 'submitted_at' => $submittedAt->toIso8601String()],
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
        ], $status);
    }
}
