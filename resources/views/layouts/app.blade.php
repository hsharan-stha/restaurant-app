<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name', 'Restaurant OS') }}</title>
    @auth
        @include('components.restaurant-notify-meta')
    @endauth
    @vite(['resources/css/app.css', 'resources/js/restaurant-global-notify.js', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="app-theme min-h-screen bg-slate-950 text-slate-100 antialiased">
    @auth
        <a
            href="{{ route('dashboard') }}"
            class="fixed bottom-4 right-4 z-50 inline-flex items-center gap-2 rounded-xl border border-orange-300/40 bg-orange-600/90 px-4 py-2 text-sm font-semibold text-white shadow-xl hover:bg-orange-500"
        >
            <span aria-hidden="true">←</span>
            <span>Dashboard</span>
        </a>
    @endauth

    <main class="app-admin-main mx-auto max-w-[1600px] px-3 py-5 text-[13px] leading-snug sm:px-4 lg:py-6">
        @if(session('status'))
            <div class="mb-3 rounded border border-emerald-700/50 bg-emerald-950/50 px-2.5 py-1.5 text-xs text-emerald-200">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-3 rounded border border-red-800/50 bg-red-950/45 px-2.5 py-2 text-xs text-red-100">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
