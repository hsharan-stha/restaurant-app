<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrintLogStatus;
use App\Enums\PrintLogType;
use App\Http\Controllers\Controller;
use App\Jobs\OrderPrintJob;
use App\Models\PrintLog;
use App\Services\Printing\PrintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PrintLogController extends Controller
{
    public function index(): View
    {
        $logs = PrintLog::query()
            ->with(['order.table', 'printer'])
            ->latest()
            ->paginate(40);

        return view('admin.printing.logs.index', compact('logs'));
    }

    public function retry(PrintLog $printLog): RedirectResponse
    {
        abort_unless(request()->user()?->isAdmin(), 403);
        abort_unless($printLog->status === PrintLogStatus::Failed, 422, 'Only failed jobs can be retried this way.');
        abort_unless($printLog->order_id, 422);
        abort_unless($printLog->print_type !== PrintLogType::Test, 422);

        $kind = $printLog->print_type->value === 'cashier' ? 'cashier' : 'kitchen';
        OrderPrintJob::dispatch((int) $printLog->order_id, $kind, true);

        return redirect()->route('admin.printing.logs.index')->with('status', 'Print job queued again.');
    }

    public function reprint(PrintLog $printLog, PrintService $printService): RedirectResponse
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        try {
            $printService->reprintFromLog($printLog);
        } catch (\Throwable $e) {
            return redirect()->route('admin.printing.logs.index')->withErrors(['reprint' => $e->getMessage()]);
        }

        return redirect()->route('admin.printing.logs.index')->with('status', 'Reprint sent to the printer.');
    }
}
