<?php

namespace App\Http\Controllers;

use App\Models\QuoteDelivery;
use App\Models\QuoteRequest;
use App\Support\PrivateEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OwnerController extends Controller
{
    public const STATUSES = ['new' => 'New', 'review' => 'In Review', 'responded' => 'Responded'];

    public function signIn(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email', 'max:254'], 'password' => ['required', 'string', 'max:256']]);

        if (! Auth::attempt(['email_lookup' => PrivateEmail::lookup($credentials['email']), 'password' => $credentials['password'], 'is_owner' => true])) {
            return back()->withErrors(['email' => 'Check your sign-in details.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route('owner.index');
    }

    public function signOut(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function search(Request $request): RedirectResponse
    {
        $term = $request->validate(['term' => ['nullable', 'string', 'max:254']])['term'] ?? '';
        $search = $term === '' ? null : (str_contains($term, '@')
            ? ['email_lookup', PrivateEmail::lookup($term)]
            : ['reference', strtoupper($term)]);
        $request->session()->put('owner_search', $search);
        Log::info('Owner searched quote requests', ['owner' => $request->user()->id]);

        return redirect()->route('owner.index');
    }

    public function index(Request $request): View
    {
        $search = $request->session()->get('owner_search');
        $quotes = QuoteRequest::query()
            ->when($search, fn ($query) => $query->where($search[0], $search[1]))
            ->latest()->orderByDesc('id')->paginate(20);

        return view('owner.index', ['quotes' => $quotes, 'statuses' => self::STATUSES, 'filtered' => (bool) $search]);
    }

    public function show(Request $request, QuoteRequest $quote): View
    {
        Log::info('Owner opened quote request', ['owner' => $request->user()->id, 'reference' => $quote->reference]);
        $root = $quote->series_id ?? $quote->id;
        $history = QuoteRequest::where('id', $root)->orWhere('series_id', $root)
            ->oldest('id')->paginate(20, ['id', 'reference', 'revises_id', 'created_at'], 'versions');

        return view('owner.show', ['quote' => $quote->load('deliveries'), 'statuses' => self::STATUSES, 'history' => $history]);
    }

    public function update(Request $request, QuoteRequest $quote): RedirectResponse
    {
        $values = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'internal_notes' => ['present', 'nullable', 'string', 'max:6000'],
        ]);
        $quote->update($values);
        Log::info('Owner updated quote request', ['owner' => $request->user()->id, 'reference' => $quote->reference, 'status' => $quote->status]);

        return back()->with('message', 'Request updated.');
    }

    public function retry(Request $request, QuoteRequest $quote, QuoteDelivery $delivery): RedirectResponse
    {
        abort_unless($delivery->quote_request_id === $quote->id, 404);
        $reset = $quote->deliveries()->whereKey($delivery->id)->whereNull('sent_at')->whereNotNull('failed_at')
            ->update(['attempts' => 0, 'failed_at' => null, 'available_at' => now()]);
        if ($reset) {
            Log::info('Owner retried quote delivery', ['owner' => $request->user()->id, 'reference' => $quote->reference, 'recipient' => $delivery->recipient]);
        }

        return back()->with('message', $reset ? 'Delivery queued for retry.' : 'Delivery already processed or pending.');
    }
}
