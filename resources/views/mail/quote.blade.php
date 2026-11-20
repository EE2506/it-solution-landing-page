<!doctype html>
<html lang="en-PH">
<body>
    <h1>Project estimate {{ $quote->reference }}</h1>
    <p>{{ \Illuminate\Support\Carbon::parse($quote->snapshot['submitted_at'])->timezone('Asia/Manila')->format('F j, Y') }}</p>
    @if ($quote->snapshot['revises'] ?? null)<p>Revision of {{ $quote->snapshot['revises'] }}</p>@endif
    @if ($isOwner)
        <p>Requested by {{ $quote->contact['name'] }} ({{ $quote->contact['email'] }})</p>
        @if ($quote->contact['business'] ?? null)<p>{{ $quote->contact['business'] }}</p>@endif
        @if ($quote->contact['notes'] ?? null)<p>{{ $quote->contact['notes'] }}</p>@endif
    @else
        <p>Thanks for sending your project brief, {{ $quote->contact['name'] }}. I’ll confirm the final scope, price, and delivery date after review.</p>
    @endif
    @include('quote.breakdown', ['estimate' => $quote->snapshot['estimate']])
</body>
</html>
