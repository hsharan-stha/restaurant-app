@php
    $tableLabel = $table->table_name ? $table->table_name : 'Table '.$table->table_number;
@endphp

@extends('layouts.customer')

@section('title', $tableLabel.' · Start')

@section('content')
    <div class="flex min-h-[calc(100dvh-2rem)] flex-col px-4 pb-10 pt-8 sm:px-6">
        <div class="mx-auto w-full max-w-md flex-1">
            <p class="text-center text-[10px] font-bold uppercase tracking-[0.2em] text-orange-600">{{ $restaurantDisplayName }}</p>
            <h1 class="mt-2 text-center text-2xl font-bold text-slate-900 sm:text-3xl">Welcome 👋</h1>
            <p class="mt-2 text-center text-lg font-semibold text-slate-800">{{ $tableLabel }}</p>
            <p class="mt-8 text-center text-sm font-medium text-slate-600">How many people are dining?</p>

            @if ($errors->any())
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-center text-sm text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('guest.session.start') }}" id="guest-start-form" class="mt-6 space-y-6">
                @csrf
                <input type="hidden" name="guest_count" id="guest_count" value="{{ old('guest_count', '2') }}" required>

                <div class="grid grid-cols-3 gap-3 sm:gap-4">
                    @foreach ([1, 2, 3, 4, 5] as $n)
                        <button
                            type="button"
                            class="guest-count-btn min-h-[52px] rounded-2xl border-2 border-orange-200 bg-white text-lg font-bold text-slate-900 shadow-sm transition hover:border-orange-400 hover:bg-orange-50"
                            data-count="{{ $n }}"
                        >
                            {{ $n }}
                        </button>
                    @endforeach
                    <button
                        type="button"
                        class="guest-count-btn min-h-[52px] rounded-2xl border-2 border-orange-200 bg-white text-lg font-bold text-slate-900 shadow-sm transition hover:border-orange-400 hover:bg-orange-50"
                        data-count="6"
                    >
                        6+
                    </button>
                </div>

                <div class="flex items-center justify-center gap-4 rounded-2xl border border-orange-100 bg-orange-50/50 px-4 py-3">
                    <button type="button" id="guest-count-minus" class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-2xl font-bold text-orange-800 shadow ring-1 ring-orange-200" aria-label="Decrease">−</button>
                    <span class="min-w-[3rem] text-center text-3xl font-bold tabular-nums text-slate-900" id="guest-count-display">2</span>
                    <button type="button" id="guest-count-plus" class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-2xl font-bold text-orange-800 shadow ring-1 ring-orange-200" aria-label="Increase">+</button>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-2xl bg-orange-600 py-4 text-base font-bold text-white shadow-lg shadow-orange-900/20 transition hover:bg-orange-500"
                >
                    Start ordering
                </button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const input = document.getElementById('guest_count');
            const display = document.getElementById('guest-count-display');
            const form = document.getElementById('guest-start-form');
            if (!input || !display || !form) return;

            function setCount(n) {
                const v = Math.min(99, Math.max(1, Number(n) || 1));
                input.value = String(v);
                display.textContent = String(v);
                document.querySelectorAll('.guest-count-btn').forEach((btn) => {
                    const on = Number(btn.getAttribute('data-count')) === v;
                    btn.classList.toggle('border-orange-600', on);
                    btn.classList.toggle('bg-orange-100', on);
                    btn.classList.toggle('ring-2', on);
                    btn.classList.toggle('ring-orange-400', on);
                });
            }

            document.querySelectorAll('.guest-count-btn').forEach((btn) => {
                btn.addEventListener('click', () => setCount(btn.getAttribute('data-count')));
            });
            document.getElementById('guest-count-minus')?.addEventListener('click', () => setCount(Number(input.value) - 1));
            document.getElementById('guest-count-plus')?.addEventListener('click', () => setCount(Number(input.value) + 1));

            setCount(input.value || '2');
        })();
    </script>
@endsection
