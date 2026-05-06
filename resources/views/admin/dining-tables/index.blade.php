@extends('layouts.app')

@section('title', 'Tables')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-white">Dining tables</h1>
        <a href="{{ route('dining-tables.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-500">Add table</a>
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-800">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-800 bg-slate-900/80 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Number</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @foreach($tables as $table)
                    <tr>
                        <td class="px-4 py-3 text-slate-200">{{ $table->table_number }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $table->status->value === 'available' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300' }}">
                                {{ $table->status->value }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('dining-tables.edit', $table) }}" class="text-emerald-400 hover:underline">Edit</a>
                            <form action="{{ route('dining-tables.destroy', $table) }}" method="POST" class="inline" onsubmit="return confirm('Delete?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ml-3 text-red-400 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
