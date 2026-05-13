<?php

namespace App\Jobs;

use App\Services\Printing\PrintService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class OrderPrintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $orderId,
        public string $ticketKind,
        public bool $bypassAutoToggles = false,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(PrintService $printService): void
    {
        $dir = storage_path('app/escpos-order-locks');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir.'/'.$this->orderId.'-'.$this->ticketKind.'.lock';
        $fh = fopen($path, 'c+');
        if ($fh === false) {
            $this->release(3);

            return;
        }

        if (! flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            $this->release(3);

            return;
        }

        try {
            $printService->processOrderPrintJob($this->orderId, $this->ticketKind, null, true, $this->bypassAutoToggles);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    public function failed(?Throwable $exception): void {}
}
