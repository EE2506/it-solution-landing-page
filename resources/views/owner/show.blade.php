@extends('owner.layout')
@section('content')
    <h1>{{ $quote->reference }}</h1>
    <p>{{ $quote->contact['name'] }} · {{ $quote->contact['email'] }}</p>
    @if ($quote->contact['business'] ?? null)<p>{{ $quote->contact['business'] }}</p>@endif
    @if ($quote->contact['notes'] ?? null)<p class="notes">{{ $quote->contact['notes'] }}</p>@endif

    @include('quote.breakdown', ['estimate' => $quote->snapshot['estimate']])

    <details>
        <summary>Original answers</summary>
        <pre>{{ json_encode($quote->snapshot['answers'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </details>

    <h2>Delivery</h2>
    @foreach ($quote->deliveries as $delivery)
        <form method="post" action="{{ route('owner.retry', [$quote, $delivery]) }}" class="actions">
            @csrf
            <p>{{ ucfirst($delivery->recipient) }} · {{ $delivery->state() }}</p>
            @if ($delivery->failed_at)<button class="secondary">Retry {{ $delivery->recipient }} email</button>@endif
        </form>
    @endforeach

    <form method="post" action="{{ route('owner.update', $quote) }}" class="field-stack">
        @csrf
        @method('PATCH')
        <label>Status
            <select name="status">
                @foreach ($statuses as $status => $label)
                    <option value="{{ $status }}" @selected(old('status', $quote->status) === $status)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>Private notes <textarea name="internal_notes" rows="4" maxlength="6000">{{ old('internal_notes', $quote->internal_notes) }}</textarea></label>
        <button>Save changes</button>
    </form>
@endsection
