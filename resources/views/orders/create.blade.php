@extends('layouts.app')

@section('title', 'New order')

@section('content')
    <div class="mb-6">
        <a href="{{ route('dashboard') }}" class="text-sm text-slate-400 hover:text-white">← Dashboard</a>
    </div>
    <h1 class="mb-6 text-2xl font-semibold text-white">New order</h1>

    <form method="POST" action="{{ route('orders.store') }}" id="order-form" class="max-w-2xl space-y-6">
        @csrf
        <div>
            <label class="mb-1 block text-sm text-slate-400">Dining table</label>
            <select name="table_id" required
                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                <option value="">Select table</option>
                @foreach($tables as $t)
                    <option value="{{ $t->id }}" @selected(old('table_id') == $t->id)>
                        #{{ $t->table_number }} — {{ $t->status->value }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between">
                <span class="text-sm font-medium text-slate-300">Items</span>
                <button type="button" id="add-line" class="text-sm text-emerald-400 hover:underline">+ Add line</button>
            </div>
            <div id="lines" class="space-y-3">
                <div class="line flex flex-wrap gap-2">
                    <select name="items[0][menu_item_id]" required
                            class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                        <option value="">Menu item</option>
                        @foreach($menuItems as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} — ¥{{ number_format($item->price, 2) }} ({{ $item->category->name }})</option>
                        @endforeach
                    </select>
                    <input type="number" name="items[0][quantity]" value="1" min="1" required
                           class="w-24 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                </div>
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-2 text-sm font-medium text-white hover:bg-emerald-500">Place order</button>
    </form>

    <template id="line-template">
        <div class="line flex flex-wrap gap-2">
            <select name="items[__I__][menu_item_id]" required
                    class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                <option value="">Menu item</option>
                @foreach($menuItems as $item)
                    <option value="{{ $item->id }}">{{ $item->name }} — ¥{{ number_format($item->price, 2) }}</option>
                @endforeach
            </select>
            <input type="number" name="items[__I__][quantity]" value="1" min="1" required
                   class="w-24 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
        </div>
    </template>

    <script>
        (function () {
            const lines = document.getElementById('lines');
            const addBtn = document.getElementById('add-line');
            const tpl = document.getElementById('line-template');
            let i = 1;
            addBtn.addEventListener('click', function () {
                const html = tpl.innerHTML.replaceAll('__I__', i++);
                const wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                lines.appendChild(wrap.firstElementChild);
            });
        })();
    </script>
@endsection
