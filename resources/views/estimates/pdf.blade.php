<!doctype html>
<html lang="en-PH">
<head>
    <meta charset="utf-8">
    <title>Project estimate {{ $quote->reference }}</title>
    <style>
        @page { margin: 36pt; }
        body { color: #242d27; font: 10pt/1.45 "DejaVu Sans", sans-serif; }
        h1 { color: #28583e; font-size: 22pt; margin: 0 0 8pt; }
        h2 { font-size: 15pt; margin: 16pt 0 4pt; }
        h3 { font-size: 11pt; margin: 12pt 0 4pt; }
        h2, h3 { page-break-after: avoid; }
        ul { margin: 0 0 8pt; padding-left: 15pt; }
        p { margin: 4pt 0 8pt; }
        .meta { padding-bottom: 8pt; border-bottom: 1pt solid #d3d8cd; }
    </style>
</head>
<body>
    <h1>Project estimate</h1>
    <p class="meta">{{ $quote->reference }} · {{ \Illuminate\Support\Carbon::parse($quote->snapshot['submitted_at'])->timezone('Asia/Manila')->format('F j, Y') }}</p>
    @if ($quote->snapshot['revises'] ?? null)<p>Revision of {{ $quote->snapshot['revises'] }}</p>@endif
    @include('quote.breakdown', ['estimate' => $estimate])
    <p>I’ll confirm the final scope, price, and delivery date after review.</p>
</body>
</html>
