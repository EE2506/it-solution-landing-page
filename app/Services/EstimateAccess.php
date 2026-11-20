<?php

namespace App\Services;

use App\Models\QuoteRequest;
use Illuminate\Http\Request;

class EstimateAccess
{
    public function resolve(Request $request, string $reference): QuoteRequest
    {
        $quote = QuoteRequest::where('reference', strtoupper($reference))->first();
        abort_unless($quote, 403, 'Request a fresh private link to open this estimate.');
        $this->expiry($request, $quote);

        return $quote;
    }

    public function expiry(Request $request, QuoteRequest $quote): int
    {
        $expiresAt = (int) $request->session()->get('estimate_access.'.$quote->id);
        abort_unless($expiresAt > now()->timestamp, 403, 'Request a fresh private link to open this estimate.');

        return $expiresAt;
    }

    public function grant(Request $request, QuoteRequest $quote, int $expiry): void
    {
        $grants = array_filter($request->session()->get('estimate_access', []), static fn (int $time): bool => $time > now()->timestamp);
        $grants[$quote->id] = $expiry;
        $request->session()->put('estimate_access', $grants);
    }
}
