<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Guest Ordering') | {{ config('app.name', 'Restaurant OS') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="customer-theme min-h-screen bg-[#fff7f2] text-slate-900 antialiased">
    @if(session('status'))
        <div class="border-b border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800">
            <div class="mx-auto max-w-6xl">{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="border-b border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <div class="mx-auto max-w-6xl">
                <ul class="space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @yield('content')
</body>
</html>
