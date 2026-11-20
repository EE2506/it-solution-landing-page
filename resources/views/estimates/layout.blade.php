<!doctype html>
<html lang="en-PH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title') · Build your project &amp; estimate</title>
    @vite('resources/css/app.css')
</head>
<body>
    <main class="owner-shell">
        <header class="owner-bar"><a href="/">Build your project &amp; estimate</a></header>
        @if ($errors->any())
            <ul class="error" role="alert">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif
        @yield('content')
    </main>
</body>
</html>
