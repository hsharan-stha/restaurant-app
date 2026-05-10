<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ config('app.name', 'Restaurant OS') }}</title>
    @auth
        @include('components.restaurant-notify-meta')
    @endauth
    @vite(['resources/css/app.css', 'resources/js/restaurant-global-notify.js', 'resources/js/dashboard-floor.js'])
</head>
<body class="restaurant-dashboard-theme min-h-screen overflow-hidden antialiased">
    @if(session('status'))
        <div class="fixed right-4 top-4 z-50 max-w-sm rounded-2xl border border-emerald-600/40 bg-emerald-950/90 px-4 py-3 text-sm text-emerald-200 shadow-xl">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="fixed bottom-4 left-4 z-50 max-w-sm rounded-2xl border border-red-600/40 bg-red-950/90 px-4 py-3 text-sm text-red-200 shadow-xl">
            <ul class="space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</body>
</html>
