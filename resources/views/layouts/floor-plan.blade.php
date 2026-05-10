<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Floor plan') — {{ config('app.name', 'Restaurant OS') }}</title>
    @auth
        @include('components.restaurant-notify-meta')
    @endauth
    @vite(['resources/css/app.css', 'resources/js/restaurant-global-notify.js', 'resources/js/floor-plan.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    @if(session('status'))
        <div class="fixed right-4 top-4 z-[100] max-w-sm rounded-2xl border border-emerald-600/40 bg-emerald-950/95 px-4 py-3 text-sm text-emerald-200 shadow-xl">
            {{ session('status') }}
        </div>
    @endif

    @yield('content')
</body>
</html>
