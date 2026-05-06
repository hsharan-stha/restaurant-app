<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function show(Order $order): View
    {
        $order->load(['table', 'items.menuItem', 'invoice', 'payments']);

        abort_unless($order->invoice, 404);

        return view('invoices.show', compact('order'));
    }

    public function pdf(Order $order): Response
    {
        $order->load(['table', 'items.menuItem', 'invoice', 'payments']);

        abort_unless($order->invoice, 404);

        $pdf = Pdf::loadView('invoices.pdf', ['order' => $order]);

        return $pdf->download('invoice-'.$order->id.'.pdf');
    }
}
