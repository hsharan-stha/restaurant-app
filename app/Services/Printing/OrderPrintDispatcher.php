<?php

namespace App\Services\Printing;

use App\Jobs\OrderPrintJob;
use App\Models\PrintSetting;

class OrderPrintDispatcher
{
    public function queueForOrder(int $orderId): void
    {
        $settings = PrintSetting::current();

        if ($settings->auto_print_kitchen && $settings->kitchen_printer_id) {
            OrderPrintJob::dispatch($orderId, 'kitchen');
        }

        if ($settings->auto_print_cashier && $settings->cashier_printer_id) {
            OrderPrintJob::dispatch($orderId, 'cashier');
        }
    }
}
