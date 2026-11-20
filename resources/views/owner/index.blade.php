@extends('owner.layout')
@section('content')
    <h1>Quote requests</h1>
    <search>
        <form method="post" action="{{ route('owner.search') }}" class="actions">
            @csrf
            <label>Reference or email <input name="term" type="search" maxlength="254"></label>
            <button>Search</button>
            @if ($filtered)<button class="secondary" name="term" value="">Clear search</button>@endif
        </form>
    </search>
    <table>
        <thead><tr><th scope="col">Reference</th><th scope="col">Status</th><th scope="col">Received (PHT)</th></tr></thead>
        <tbody>
            @forelse ($quotes as $quote)
                <tr>
                    <td><a href="{{ route('owner.show', $quote) }}">{{ $quote->reference }}</a></td>
                    <td>{{ $statuses[$quote->status] }}</td>
                    <td>{{ $quote->created_at->timezone('Asia/Manila')->format('m/d/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No requests found</td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($quotes->hasPages())
        <nav class="actions" aria-label="Quote pages">
            @if ($quotes->previousPageUrl())<a href="{{ $quotes->previousPageUrl() }}">Previous page</a>@endif
            @if ($quotes->nextPageUrl())<a href="{{ $quotes->nextPageUrl() }}">Next page</a>@endif
        </nav>
    @endif
@endsection
