@php
    use App\Enums\OrderStatus;
    $map = [
        OrderStatus::Pending->value => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
        OrderStatus::Preparing->value => 'bg-sky-500/20 text-sky-300 border-sky-500/30',
        OrderStatus::Completed->value => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
        OrderStatus::CheckoutDone->value => 'bg-slate-600/40 text-slate-200 border-slate-500/30',
    ];
    $cls = $map[$status->value] ?? 'bg-slate-700 text-slate-200';
@endphp
<span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {{ $cls }}">
    {{ ucfirst($status->value) }}
</span>
