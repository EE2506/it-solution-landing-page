<?php

namespace App\Http\Controllers;

use App\Models\QuoteRequest;
use App\Services\EstimateAccess;
use App\Services\QuoteCatalog;
use App\Support\PrivateEmail;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class EstimateAccessController extends Controller
{
    public function requestLink(Request $request): RedirectResponse
    {
        $input = $request->validate(['reference' => ['required', 'string', 'max:32'], 'email' => ['required', 'email:rfc', 'max:254']]);
        $quote = QuoteRequest::query()
            ->where('reference', strtoupper(trim($input['reference'])))
            ->where('email_lookup', PrivateEmail::lookup($input['email']))
            ->first();

        if ($quote) {
            RateLimiter::attempt('estimate-link:'.$quote->id, 1, function () use ($quote): void {
                DB::table('quote_deliveries')->upsert([
                    'quote_request_id' => $quote->id, 'recipient' => 'access', 'attempts' => 0,
                    'available_at' => now(), 'sent_at' => null, 'failed_at' => null,
                ], ['quote_request_id', 'recipient'], ['attempts', 'available_at', 'sent_at', 'failed_at']);
            }, 120);
        }

        return redirect()->route('estimates.find')->with('lookup_requested', true);
    }

    public function open(Request $request, string $quote, EstimateAccess $access): RedirectResponse
    {
        $quote = QuoteRequest::where('reference', $quote)->firstOrFail();
        $access->grant($request, $quote, (int) $request->query('expires'));
        $request->session()->regenerate();

        return redirect()->route('estimates.show', $quote)->header('Referrer-Policy', 'no-referrer');
    }

    public function show(Request $request, string $quote, EstimateAccess $access, QuoteCatalog $catalog): View
    {
        $quote = $access->resolve($request, $quote);

        return view('estimates.show', [
            'quote' => $quote,
            'estimate' => $quote->snapshot['estimate'],
            'discoveryUrl' => $catalog->discoveryUrl(),
        ]);
    }

    public function pdf(Request $request, string $quote, EstimateAccess $access): Response
    {
        $quote = $access->resolve($request, $quote);

        $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]));
        $pdf->setPaper('A4');
        $pdf->loadHtml(view('estimates.pdf', ['quote' => $quote, 'estimate' => $quote->snapshot['estimate']])->render(), 'UTF-8');
        $pdf->render();
        Log::info('Estimate PDF downloaded', ['reference' => $quote->reference]);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$quote->reference.'.pdf"',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
