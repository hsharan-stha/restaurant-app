<?php

namespace App\Services\Printing;

use App\Enums\PrinterRole;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Mike42\Escpos\Printer;

class EscposThermalReceiptBuilder
{
    /**
     * @param  Collection<int, OrderItem>  $items
     */
    public function renderTicket(Printer $printer, Order $order, Collection $items, PrinterRole $role): void
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->setTextSize(2, 1);
        $printer->text(config('app.name', 'Restaurant')."\n");
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        $printer->text($role === PrinterRole::Kitchen ? "KITCHEN TICKET\n" : "ORDER RECEIPT\n");
        $printer->text(str_repeat('-', 32)."\n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $table = $order->table;
        $tableLabel = $table
            ? (trim((string) ($table->table_name ?? '')) !== ''
                ? (string) $table->table_name
                : 'Table '.(string) $table->table_number)
            : '—';

        $printer->setEmphasis(true);
        $printer->text('Table: '.$tableLabel."\n");
        $printer->setEmphasis(false);
        $printer->text('Order #'.(string) $order->order_number."\n");
        if ($order->ordered_at) {
            $printer->text($order->ordered_at->timezone(config('app.timezone'))->format('Y-m-d H:i')."\n");
        }
        $customer = $this->resolveCustomerLabel($order);
        if ($customer) {
            $printer->text('Guest: '.$customer."\n");
        }
        $printer->text(str_repeat('-', 32)."\n");

        if ($role === PrinterRole::Kitchen) {
            $this->printKitchenLines($printer, $items);
        } else {
            $this->printCashierLines($printer, $items, $order);
        }

        $printer->text(str_repeat('-', 32)."\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Thank you\n");
        $printer->feed(3);
        $printer->cut();
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    protected function printKitchenLines(Printer $printer, Collection $items): void
    {
        foreach ($items as $item) {
            $name = $item->menuItem?->name ?? 'Item';
            $printer->setEmphasis(true);
            $printer->setTextSize(2, 2);
            $printer->text((string) $item->quantity."x\n");
            $printer->setTextSize(1, 1);
            $printer->text($this->wrapLine($name, 30)."\n");
            $printer->setEmphasis(false);
            if ($item->notes) {
                $printer->setEmphasis(true);
                $printer->text('NOTE: '.$this->wrapLine((string) $item->notes, 28)."\n");
                $printer->setEmphasis(false);
            }
            if (! empty($item->options) && is_array($item->options)) {
                $printer->text('Opts: '.$this->wrapLine(json_encode($item->options, JSON_UNESCAPED_UNICODE), 28)."\n");
            }
            $printer->text("\n");
        }
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    protected function printCashierLines(Printer $printer, Collection $items, Order $order): void
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $name = $item->menuItem?->name ?? 'Item';
            $line = (float) $item->price * (int) $item->quantity;
            $subtotal += $line;
            $left = (string) $item->quantity.'x '.$this->truncate($name, 18);
            $right = number_format($line, 2);
            $printer->text($this->twoColumn($left, $right, 32)."\n");
            if ($item->notes) {
                $printer->text('  '.$this->wrapLine((string) $item->notes, 30)."\n");
            }
        }
        $printer->text(str_repeat('-', 32)."\n");
        $printer->setEmphasis(true);
        $printer->text($this->twoColumn('Subtotal (lines)', number_format($subtotal, 2), 32)."\n");
        $printer->setEmphasis(false);
        $printer->text($this->twoColumn('Order total', number_format((float) $order->total_amount, 2), 32)."\n");
    }

    protected function resolveCustomerLabel(Order $order): ?string
    {
        $guest = $order->customerSession?->guest_name;
        if (is_string($guest) && trim($guest) !== '') {
            return trim($guest);
        }
        $name = $order->diningSession?->customer_name;
        if (! is_string($name) || trim($name) === '') {
            return null;
        }
        if (str_starts_with($name, 'guest-session-')) {
            return null;
        }

        return trim($name);
    }

    protected function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 1).'…';
    }

    protected function wrapLine(string $text, int $width): string
    {
        $text = str_replace(["\r", "\n"], ' ', $text);

        return mb_strlen($text) <= $width ? $text : mb_substr($text, 0, $width - 1).'…';
    }

    protected function twoColumn(string $left, string $right, int $width): string
    {
        $space = $width - mb_strlen($left) - mb_strlen($right);
        if ($space < 1) {
            return mb_substr($left, 0, max(1, $width - mb_strlen($right) - 1)).' '.$right;
        }

        return $left.str_repeat(' ', $space).$right;
    }
}
