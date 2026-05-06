@extends('layouts.app')

@section('title', 'New category')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold text-white">New category</h1>
    <form method="POST" action="{{ route('categories.store') }}" class="max-w-md space-y-4">
        @csrf
        <div>
            <label class="mb-1 block text-sm text-slate-400">Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
        </div>
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white">Save</button>
    </form>
@endsection
