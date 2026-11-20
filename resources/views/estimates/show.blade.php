@extends('estimates.layout')
@section('title', 'Your saved estimate')
@section('content')
    <h1>Your saved estimate</h1>
    <p class="reference">{{ $quote->reference }}</p>
    <p>Submitted {{ \Illuminate\Support\Carbon::parse($quote->snapshot['submitted_at'])->timezone('Asia/Manila')->format('F j, Y') }}</p>
    @if ($quote->snapshot['revises'] ?? null)
        <p>Revision of {{ $quote->snapshot['revises'] }}</p>
    @endif
    <div class="actions">
        <a href="{{ route('estimates.pdf', $quote) }}">Download PDF</a>
        <a href="{{ route('estimates.revise', $quote) }}">Revise this estimate</a>
    </div>
    @include('quote.breakdown', ['estimate' => $estimate])
    @if ($discoveryUrl && ($estimate['question'] || $estimate['review']))
        <p><a rel="noreferrer" href="{{ $discoveryUrl }}">Book a discovery call</a></p>
    @endif
    <p>I’ll confirm the final scope, price, and delivery date after review.</p>
@endsection
