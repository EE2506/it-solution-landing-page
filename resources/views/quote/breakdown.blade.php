@php
    $money = fn ($amount) => '₱'.number_format($amount / 100, $amount % 100 ? 2 : 0);
@endphp
<h2>{{ $estimate['name'] }}</h2>
<p>{{ $estimate['why'] }}</p>
<h3>Included essentials</h3>
<ul>
    @foreach ($estimate['included'] as $item)
        <li>{{ $item }}</li>
    @endforeach
</ul>
@if ($estimate['features'] ?? [])
    <h3>Requested scope</h3>
    <ul>
        @foreach ($estimate['features'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
@endif
@if ($estimate['one_time'] || $estimate['rush'])
    <h3>One-time work</h3>
    <ul>
        @foreach ($estimate['one_time'] as $item)
            <li>{{ $item['name'] }} ({{ $item['quantity'] }} × {{ $money($item['rate']) }}) — {{ $money($item['amount']) }}</li>
        @endforeach
        @if ($estimate['rush'])
            <li>Priority delivery — {{ $money($estimate['rush']) }}</li>
        @endif
    </ul>
    <p><strong>{{ $estimate['review'] ? 'Estimated subtotal for priced items' : 'One-time estimate' }} — {{ $money($estimate['total']) }}</strong></p>
@endif
@foreach (['monthly' => 'Monthly charges', 'review' => 'Needs review', 'later' => 'Saved for later'] as $key => $heading)
    @if ($estimate[$key])
        <h3>{{ $heading }}</h3>
        <ul>
            @foreach ($estimate[$key] as $item)
                <li>{{ $item['name'] }}@isset($item['amount']) — {{ $money($item['amount']) }}@endisset @isset($item['scope']) — {{ $item['scope'] }}@endisset</li>
            @endforeach
        </ul>
    @endif
@endforeach
@if ($estimate['provider'])
    <h3>Separate provider costs</h3>
    <ul>
        @foreach ($estimate['provider'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
@endif
@if ($estimate['question'])
    <p>To discuss: {{ $estimate['question'] }}</p>
@endif
