<!doctype html>
<html lang="en-PH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Quote requests</title>
    @vite('resources/css/app.css')
</head>
<body>
    <main class="owner-shell">
        @auth
            <header class="owner-bar">
                <nav aria-label="Owner">
                    <a href="{{ route('owner.index') }}">Quote requests</a>
                    <a href="{{ route('owner.catalog') }}">Catalog</a>
                </nav>
                <form method="post" action="{{ route('owner.logout') }}">@csrf <button class="secondary">Sign out</button></form>
            </header>
        @endauth
        @if (session('message'))<p role="status">{{ session('message') }}</p>@endif
        @if ($errors->any())
            <ul class="error" role="alert">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif
        @yield('content')
    </main>
</body>
</html>
