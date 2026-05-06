@extends('layouts.app')

@section('title', 'Edit menu item')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold text-white">Edit menu item</h1>
    <form method="POST" action="{{ route('menu-items.update', $menuItem) }}" enctype="multipart/form-data" class="max-w-md space-y-4">
        @csrf
        @method('PUT')
        <div>
            <label class="mb-1 block text-sm text-slate-400">Name</label>
            <input type="text" name="name" value="{{ old('name', $menuItem->name) }}" required
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-400">Price</label>
            <input type="number" step="0.01" name="price" value="{{ old('price', $menuItem->price) }}" required
                   class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
        </div>
        <div>
            <label class="mb-1 block text-sm text-slate-400">Category</label>
            <select name="category_id" required class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected(old('category_id', $menuItem->category_id) == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @if($menuItem->image)
            <p class="text-xs text-slate-500">Current image stored. Upload to replace.</p>
        @endif
        <div>
            <label class="mb-1 block text-sm text-slate-400">New image (optional)</label>
            <input type="file" name="image" accept="image/*" class="w-full text-sm text-slate-400">
        </div>
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white">Update</button>
    </form>
@endsection
