<?php

namespace App\Services\Printing;

use App\Enums\PrinterConnectionType;
use App\Enums\PrinterRole;
use App\Enums\PrintLogStatus;
use App\Enums\PrintLogType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\PrintLog;
use App\Models\PrintSetting;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer as EscposPrinter;
use Throwable;

class PrintService
{
    public function __construct(
        protected EscposThermalReceiptBuilder $receiptBuilder,
    ) {}

    /**
     * @param  array<int>|null  $forceItemIds
     */
    public function processOrderPrintJob(int $orderId, string $ticketKind, ?array $forceItemIds = null, bool $markAsPrinted = true, bool $bypassAutoToggles = false): void
    {
        $role = $ticketKind === 'cashier' ? PrinterRole::Cashier : PrinterRole::Kitchen;
        $settings = PrintSetting::current()->load(['kitchenPrinter', 'cashierPrinter']);

        $autoOn = $role === PrinterRole::Kitchen
            ? $settings->auto_print_kitchen
            : $settings->auto_print_cashier;

        $printerModel = $role === PrinterRole::Kitchen
            ? $settings->kitchenPrinter
            : $settings->cashierPrinter;

        if (! $printerModel || ! $printerModel->is_enabled) {
            return;
        }

        if (! $bypassAutoToggles && ! $printerModel->auto_print_enabled && $forceItemIds === null) {
            return;
        }

        if (! $bypassAutoToggles && ! $autoOn && $forceItemIds === null) {
            return;
        }

        if ($printerModel->role !== $role) {
            return;
        }

        $order = Order::query()
            ->with(['table', 'items.menuItem', 'customerSession', 'diningSession'])
            ->find($orderId);

        if (! $order) {
            return;
        }

        $items = $this->resolveItemsToPrint($order, $role, $forceItemIds);
        if ($items->isEmpty()) {
            return;
        }

        $logType = $role === PrinterRole::Kitchen ? PrintLogType::Kitchen : PrintLogType::Cashier;

        $this->printToPhysical($order, $printerModel, $role, $items, $logType, $markAsPrinted);
    }

    /**
     * @param  array<int>|null  $forceItemIds
     * @return Collection<int, OrderItem>
     */
    public function resolveItemsToPrint(Order $order, PrinterRole $role, ?array $forceItemIds): Collection
    {
        $column = $role === PrinterRole::Kitchen ? 'kitchen_printed_at' : 'cashier_printed_at';

        $query = OrderItem::query()
            ->where('order_id', $order->id)
            ->with('menuItem')
            ->orderBy('id');

        if (is_array($forceItemIds) && $forceItemIds !== []) {
            $query->whereIn('id', $forceItemIds);
        } else {
            $query->whereNull($column);
        }

        return $query->get();
    }

    public function testPrint(Printer $printer): PrintLog
    {
        $connector = $this->makeConnector($printer);
        $p = new EscposPrinter($connector);

        try {
            $p->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $p->setEmphasis(true);
            $p->text("TEST PRINT\n");
            $p->setEmphasis(false);
            $p->text($printer->name."\n");
            $p->text(config('app.name', 'Restaurant')."\n");
            $p->text(now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s')."\n");
            $p->feed(3);
            $p->cut();
        } finally {
            $p->close();
        }

        return PrintLog::query()->create([
            'order_id' => null,
            'printer_id' => $printer->id,
            'print_type' => PrintLogType::Test,
            'status' => PrintLogStatus::Success,
            'message' => null,
            'order_item_ids' => null,
            'bytes_sent' => null,
            'completed_at' => now(),
        ]);
    }

    /**
     * @return array{ok: bool, message: ?string}
     */
    public function checkPrinterReachable(Printer $printer): array
    {
        try {
            if ($printer->connection_type === PrinterConnectionType::NetworkEscpos) {
                $errno = 0;
                $errstr = '';
                $fp = @fsockopen($printer->host, (int) $printer->port, $errno, $errstr, 2.0);
                if (! is_resource($fp)) {
                    return ['ok' => false, 'message' => $errstr !== '' ? $errstr : "Connection failed ({$errno})"];
                }
                fclose($fp);

                return ['ok' => true, 'message' => null];
            }

            if ($printer->connection_type === PrinterConnectionType::FileRaw) {
                if (! file_exists($printer->host)) {
                    return ['ok' => false, 'message' => 'Path does not exist'];
                }
                if (! is_writable($printer->host)) {
                    return ['ok' => false, 'message' => 'Path is not writable'];
                }

                return ['ok' => true, 'message' => null];
            }
        } catch (Exception $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return ['ok' => false, 'message' => 'Unsupported connection type'];
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    protected function printToPhysical(
        Order $order,
        Printer $printerModel,
        PrinterRole $role,
        Collection $items,
        PrintLogType $logType,
        bool $markAsPrinted,
    ): void {
        $log = PrintLog::query()->create([
            'order_id' => $order->id,
            'printer_id' => $printerModel->id,
            'print_type' => $logType,
            'status' => PrintLogStatus::Pending,
            'message' => null,
            'order_item_ids' => $items->pluck('id')->all(),
            'bytes_sent' => null,
            'completed_at' => null,
        ]);

        $connector = $this->makeConnector($printerModel);
        $p = new EscposPrinter($connector);

        try {
            $this->receiptBuilder->renderTicket($p, $order, $items, $role);
        } catch (Throwable $e) {
            $log->update([
                'status' => PrintLogStatus::Failed,
                'message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        } finally {
            $p->close();
        }

        if ($markAsPrinted) {
            DB::transaction(function () use ($order, $items, $role, $log): void {
                $column = $role === PrinterRole::Kitchen ? 'kitchen_printed_at' : 'cashier_printed_at';
                $now = now();
                OrderItem::query()
                    ->where('order_id', $order->id)
                    ->whereIn('id', $items->pluck('id')->all())
                    ->update([$column => $now]);

                $log->update([
                    'status' => PrintLogStatus::Success,
                    'bytes_sent' => null,
                    'completed_at' => now(),
                ]);
            });
        } else {
            $log->update([
                'status' => PrintLogStatus::Success,
                'message' => 'reprint (flags unchanged)',
                'bytes_sent' => null,
                'completed_at' => now(),
            ]);
        }
    }

    protected function makeConnector(Printer $printer): NetworkPrintConnector|FilePrintConnector
    {
        return match ($printer->connection_type) {
            PrinterConnectionType::NetworkEscpos => new NetworkPrintConnector($printer->host, (int) $printer->port, 3),
            PrinterConnectionType::FileRaw => new FilePrintConnector($printer->host),
        };
    }

    /**
     * Duplicate copy of a past ticket (does not change order_items print flags).
     */
    public function reprintFromLog(PrintLog $log): void
    {
        if ($log->print_type === PrintLogType::Test || ! $log->order_id || empty($log->order_item_ids)) {
            throw new \InvalidArgumentException('Nothing to reprint for this log entry.');
        }

        $printer = $log->printer;
        if (! $printer) {
            throw new \InvalidArgumentException('Printer no longer exists.');
        }

        $role = $log->print_type === PrintLogType::Cashier ? PrinterRole::Cashier : PrinterRole::Kitchen;

        $order = Order::query()
            ->with(['table', 'items.menuItem', 'customerSession', 'diningSession'])
            ->findOrFail($log->order_id);

        $items = OrderItem::query()
            ->where('order_id', $order->id)
            ->whereIn('id', $log->order_item_ids)
            ->with('menuItem')
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            throw new \InvalidArgumentException('Order lines are no longer available.');
        }

        $this->printToPhysical($order, $printer, $role, $items, $log->print_type, false);
    }
}
