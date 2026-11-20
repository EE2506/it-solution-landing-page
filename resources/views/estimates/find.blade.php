@extends('estimates.layout')
@section('title', 'Find my estimate')
@section('content')
    <h1>Find my estimate</h1>
    <p>Enter the email and reference you used to request your estimate. I’ll send a private link to the email on file.</p>
    @if (session('lookup_requested'))
        <p role="status" class="recommendation">If the details match a saved estimate, an email with a private link will arrive shortly.</p>
    @endif
    <form method="post" action="{{ route('estimates.send') }}" class="field-stack">
        @csrf
        <label>Quote reference <input name="reference" maxlength="32" placeholder="EST-YYMM-…" value="{{ old('reference') }}" required></label>
        <label>Email address <input name="email" type="email" autocomplete="email" maxlength="254" value="{{ old('email') }}" required></label>
        <button>Send private link</button>
    </form>
@endsection
