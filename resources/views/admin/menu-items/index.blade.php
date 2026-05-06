@extends('layouts.app')

@section('title', 'Menu')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-white">Menu items</h1>
        <a href="{{ route('menu-items.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-500">Add item</a>
    </div>
    <div class="overflow-hidden rounded-xl border border-slate-800">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-800 bg-slate-900/80 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Price</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @foreach($menuItems as $item)
                    <tr>
                        <td class="px-4 py-3 text-slate-200">{{ $item->name }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ $item->category->name }}</td>
                        <td class="px-4 py-3 text-emerald-300">${{ number_format($item->price, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('menu-items.edit', $item) }}" class="text-emerald-400 hover:underline">Edit</a>
                            <form action="{{ route('menu-items.destroy', $item) }}" method="POST" class="inline" onsubmit="return confirm('Delete?');">
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
