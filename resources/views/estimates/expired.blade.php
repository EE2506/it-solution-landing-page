@extends('estimates.layout')
@section('title', 'Private link expired')
@section('content')
    <h1>Request a fresh private link</h1>
    <p>This link is no longer valid. Enter your estimate reference and email to get a fresh link.</p>
    <a href="{{ route('estimates.find') }}">Find my estimate</a>
@endsection
