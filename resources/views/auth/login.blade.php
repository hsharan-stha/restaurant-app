@extends('layouts.guest')

@section('title', 'Login')

@section('content')
    <h1 class="mb-6 text-center text-2xl font-semibold text-white">Sign in</h1>
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label class="mb-1 block text-sm text-slate-400">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-400">Password</label>
            <input type="password" name="password" required
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-400">
            <input type="checkbox" name="remember" class="rounded border-slate-600 bg-slate-950">
            Remember me
        </label>
        <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2 font-medium text-white hover:bg-emerald-500">Sign in</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500">
        No account?
        <a href="{{ route('register') }}" class="text-emerald-400 hover:underline">Register</a>
    </p>
@endsection
