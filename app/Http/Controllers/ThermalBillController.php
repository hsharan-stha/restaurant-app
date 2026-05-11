<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ThermalBillController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        $orders = $this->resolveOrders($request, $order);
        abort_if($orders->isEmpty(), 404);

        $paper = $this->paper($request);
        $primary = $orders->first();
        $returnTo = $request->query('return_to');

        return view('bills.thermal', [
            'bill' => $this->buildBill($orders, $request),
            'order' => $primary,
            'paper' => $paper,
            'autoprint' => $request->boolean('autoprint'),
            'returnTo' => is_string($returnTo) && $returnTo !== '' ? $returnTo : route('dashboard'),
            'orderIdsCsv' => $orders->pluck('id')->implode(','),
        ]);
    }

    public function pdf(Request $request, Order $order): Response
    {
        $orders = $this->resolveOrders($request, $order);
        abort_if($orders->isEmpty(), 404);

        $paper = $this->paper($request);
        $pdf = Pdf::loadView('bills.thermal-pdf', [
            'bill' => $this->buildBill($orders, $request),
            'order' => $orders->first(),
            'paper' => $paper,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf('bill-%s.pdf', $orders->pluck('id')->implode('-'));

        return $pdf->download($filename);
    }

    protected function paper(Request $request): string
    {
        return $request->query('paper') === '58' ? '58' : '80';
    }

    protected function resolveOrders(Request $request, Order $order): Collection
    {
        $csv = $request->query('ids');
        if (! is_string($csv) || trim($csv) === '') {
            return collect([$order->load([
                'table',
                'customerSession',
                'items.menuItem',
                'invoice',
                'payments',
            ])]);
        }

        $ids = collect(explode(',', $csv))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Order::query()
            ->with(['table', 'customerSession', 'items.menuItem', 'invoice', 'payments'])
            ->whereIn('id', $ids->all())
            ->orderBy('id')
            ->get();
    }

    protected function buildBill(Collection $orders, Request $request): array
    {
        $lineItems = $orders
            ->flatMap(fn (Order $order) => $order->items)
            ->groupBy(fn ($line) => ($line->menuItem->name ?? 'Item').'|'.number_format((float) $line->price, 2, '.', ''))
            ->map(function (Collection $group) {
                $first = $group->first();
                $qty = (int) $group->sum('quantity');
                $unit = (float) $first->price;

                return [
                    'name' => $first->menuItem->name ?? 'Item',
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => $qty * $unit,
                ];
            })
            ->values();

        $subtotal = (float) $orders->sum(fn (Order $o) => (float) ($o->invoice->subtotal ?? 0));
        $tax = (float) $orders->sum(fn (Order $o) => (float) ($o->invoice->tax ?? 0));
        $total = (float) $orders->sum(fn (Order $o) => (float) ($o->invoice->total ?? $o->total_amount));

        $grossFromCatalog = (float) $orders
            ->flatMap(fn (Order $o) => $o->items)
            ->sum(function ($line) {
                $basePrice = (float) ($line->menuItem->price ?? $line->price);

                return $basePrice * (int) $line->quantity;
            });
        $chargedLines = (float) $lineItems->sum('line_total');
        $discount = max(0, round($grossFromCatalog - $chargedLines, 2));

        $payments = $orders
            ->flatMap(fn (Order $o) => $o->payments)
            ->filter(fn ($p) => $p->status === PaymentStatus::Completed)
            ->sortByDesc('created_at')
            ->values();
        $latestPayment = $payments->first();

        $anchor = $orders
            ->map(fn (Order $o) => $o->checkout_at ?? $o->completed_at ?? $o->ordered_at ?? $o->updated_at ?? $o->created_at)
            ->filter()
            ->sortByDesc(fn (Carbon $d) => $d->getTimestamp())
            ->first();

        $table = $orders->first()?->table;
        $qrSvg = null;
        $qrValue = null;
        if ($table && $table->qr_token) {
            $qrSvg = $table->customer_qr_svg;
            $qrValue = $table->customer_entry_url;
        }

        return [
            'restaurant_name' => config('app.name', 'Restaurant OS'),
            'table_number' => $table?->table_number,
            'order_ids' => $orders->pluck('id')->all(),
            'order_reference' => $orders->pluck('order_number')->filter()->first() ?: '#'.$orders->pluck('id')->implode('/#'),
            'items' => $lineItems,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'grand_total' => $total,
            'payment_method' => $latestPayment?->method?->value,
            'cashier_name' => $request->user()?->name,
            'order_datetime' => $anchor,
            'qr_svg' => $qrSvg,
            'qr_value' => $qrValue,
            'barcode_value' => $orders->pluck('order_number')->filter()->first(),
        ];
    }
}
