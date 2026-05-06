@extends('layouts.guest')

@section('title', 'Register')

@section('content')
    <h1 class="mb-6 text-center text-2xl font-semibold text-white">Create account</h1>
    <p class="mb-6 text-center text-sm text-slate-500">New accounts receive the Staff role by default.</p>
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <div>
            <label class="mb-1 block text-sm text-slate-400">Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-400">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-400">Password</label>
            <input type="password" name="password" required
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-400">Confirm password</label>
            <input type="password" name="password_confirmation" required
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
        </div>
        <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2 font-medium text-white hover:bg-emerald-500">Register</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500">
        Already have an account?
        <a href="{{ route('login') }}" class="text-emerald-400 hover:underline">Sign in</a>
    </p>
@endsection
