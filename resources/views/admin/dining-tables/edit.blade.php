@extends('layouts.app')

@section('title', 'Edit table')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold text-white">Edit table #{{ $diningTable->table_number }}</h1>
    <form method="POST" action="{{ route('dining-tables.update', $diningTable) }}" class="max-w-md space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="mb-1 block text-sm text-slate-400">Table number</label>
            <input type="number" name="table_number" value="{{ old('table_number', $diningTable->table_number) }}" min="1" required
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-400">Status</label>
            <select name="status" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                <option value="available" @selected(old('status', $diningTable->status->value) === 'available')>available</option>
                <option value="occupied" @selected(old('status', $diningTable->status->value) === 'occupied')>occupied</option>
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white">Update</button>
    </form>
@endsection
