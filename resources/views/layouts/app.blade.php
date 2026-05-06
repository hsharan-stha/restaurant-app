<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name', 'Restaurant OS') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <nav class="border-b border-slate-800 bg-slate-900/80 backdrop-blur">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-3">
            <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-emerald-400">Restaurant OS</a>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-slate-300 hover:text-white">Dashboard</a>
                    <a href="{{ route('orders.create') }}" class="text-slate-300 hover:text-white">New order</a>
                    @if(auth()->user()->isAdmin())
                        <span class="hidden text-slate-600 sm:inline">|</span>
                        <a href="{{ route('categories.index') }}" class="text-slate-300 hover:text-white">Categories</a>
                        <a href="{{ route('menu-items.index') }}" class="text-slate-300 hover:text-white">Menu</a>
                        <a href="{{ route('dining-tables.index') }}" class="text-slate-300 hover:text-white">Tables</a>
                    @endif
                    <span class="hidden text-slate-600 sm:inline">|</span>
                    <span class="text-slate-400">{{ auth()->user()->name }}</span>
                    <span class="rounded-full bg-slate-800 px-2 py-0.5 text-xs text-slate-400">{{ auth()->user()->isAdmin() ? 'Admin' : 'Staff' }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="rounded-md bg-slate-800 px-3 py-1 text-slate-200 hover:bg-slate-700">Log out</button>
                    </form>
                @endauth
            </div>
        </div>
    </nav>
    <main class="mx-auto max-w-7xl px-4 py-8">
        @if(session('status'))
            <div class="mb-6 rounded-lg border border-emerald-600/40 bg-emerald-950/40 px-4 py-3 text-emerald-200">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-600/40 bg-red-950/40 px-4 py-3 text-red-200">
                <ul class="list-disc pl-5">
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
