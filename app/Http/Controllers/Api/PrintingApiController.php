<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Printer;
use App\Services\Printing\PrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintingApiController extends Controller
{
    public function printers(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $printers = Printer::query()->orderBy('name')->get();
        $printService = app(PrintService::class);
        $out = $printers->map(function (Printer $printer) use ($printService) {
            $r = $printService->checkPrinterReachable($printer);

            return [
                'id' => $printer->id,
                'name' => $printer->name,
                'role' => $printer->role->value,
                'connection_type' => $printer->connection_type->value,
                'host' => $printer->host,
                'port' => $printer->port,
                'is_enabled' => $printer->is_enabled,
                'reachable' => $r['ok'],
                'reachable_message' => $r['message'],
            ];
        });

        return response()->json(['printers' => $out]);
    }

    public function printerStatus(Request $request, Printer $printer, PrintService $printService): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $r = $printService->checkPrinterReachable($printer);

        return response()->json([
            'printer_id' => $printer->id,
            'reachable' => $r['ok'],
            'message' => $r['message'],
        ]);
    }
}
