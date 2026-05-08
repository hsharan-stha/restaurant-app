@extends('layouts.app')

@section('title', 'Tables')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-white">Dining tables</h1>
        <a href="{{ route('dining-tables.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-500">Add table</a>
    </div>

    @if(session('status'))
        <div class="mb-4 rounded-lg bg-emerald-500/20 px-4 py-3 text-emerald-300">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->has('customer_session'))
        <div class="mb-4 rounded-lg bg-red-500/20 px-4 py-3 text-red-300">
            {{ $errors->first('customer_session') }}
        </div>
    @endif

    <div class="space-y-6">
        @foreach($tables as $table)
            <div class="overflow-hidden rounded-xl border border-slate-800">
                <div class="bg-slate-900/80 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-white">Table {{ $table->table_number }}</h2>
                            <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs {{ $table->status->value === 'available' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300' }}">
                                {{ $table->status->value }}
                            </span>
                        </div>
                        <div class="text-right">
                            <a href="{{ route('dining-tables.edit', $table) }}" class="text-emerald-400 hover:underline">Edit</a>
                            <form action="{{ route('dining-tables.destroy', $table) }}" method="POST" class="inline" onsubmit="return confirm('Delete?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ml-3 text-red-400 hover:underline">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-800 px-6 py-4">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <h3 class="mb-3 text-sm font-semibold text-slate-400">QR Code</h3>
                            <div class="inline-flex rounded-2xl bg-white p-3">
                                <div class="h-32 w-32 [&>svg]:h-full [&>svg]:w-full">
                                    {!! $table->customer_qr_svg !!}
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-3 text-sm font-semibold text-slate-400">Guest Menu Access</h3>
                            <div class="space-y-2">
                                <a href="{{ $table->customer_entry_url }}" target="_blank" class="text-emerald-400 hover:underline">
                                    Open guest menu
                                </a>
                                <code class="block overflow-x-auto rounded-lg bg-slate-950 px-3 py-2 text-xs text-slate-200">
                                    {{ $table->customer_entry_url }}
                                </code>
                            </div>
                        </div>
                    </div>
                </div>

                @if($table->customerSessions()->count() > 0)
                    <div class="border-t border-slate-800 px-6 py-4">
                        <h3 class="mb-4 text-sm font-semibold text-slate-400">Active Customer Sessions</h3>
                        <div class="space-y-3">
                            @foreach($table->customerSessions as $session)
                                @php
                                    // Check if table is occupied
                                    $tableIsOccupied = $table->status->value === 'occupied';
                                    
                                    // Check for any preparing orders
                                    $hasPreparingOrder = $session->orders
                                        ->where('status', 'preparing')
                                        ->isNotEmpty();
                                    
                                    $cannotDelete = $tableIsOccupied || $hasPreparingOrder;
                                @endphp
                                <div class="flex items-center justify-between rounded-lg {{ $cannotDelete ? 'bg-red-900/30 border border-red-700/50' : 'bg-slate-900/50' }} px-4 py-3">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-slate-200">
                                            {{ $session->guest_name ?? 'Unknown Guest' }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-400">
                                            Party size: {{ $session->party_size ?? 'N/A' }}
                                            | Started: {{ $session->started_at ? $session->started_at->format('M d, g:i A') : 'N/A' }}
                                        </p>
                                        @if($session->last_seen_at)
                                            <p class="text-xs text-slate-500">
                                                Last seen: {{ $session->last_seen_at->format('M d, g:i A') }}
                                            </p>
                                        @endif
                                        @if($tableIsOccupied)
                                            <p class="mt-2 text-xs text-red-400">
                                                ⚠️ Table is occupied - cannot delete
                                            </p>
                                        @elseif($hasPreparingOrder)
                                            <p class="mt-2 text-xs text-red-400">
                                                ⚠️ Order being prepared - cannot delete
                                            </p>
                                        @endif
                                    </div>
                                    @if(!$cannotDelete)
                                        <form action="{{ route('customer-sessions.destroy', $session) }}" method="POST" class="inline" onsubmit="return confirm('Delete this customer session?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ml-4 text-sm text-red-400 hover:text-red-300 hover:underline">
                                                Delete Session
                                            </button>
                                        </form>
                                    @else
                                        <button disabled class="ml-4 text-sm text-slate-500 cursor-not-allowed">
                                            Delete Session
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endsection
