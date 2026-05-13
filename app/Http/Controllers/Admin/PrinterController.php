<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PrinterConnectionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePrinterRequest;
use App\Http\Requests\Admin\UpdatePrinterRequest;
use App\Models\Printer;
use App\Services\Printing\PrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrinterController extends Controller
{
    public function index(PrintService $printService): View
    {
        $printers = Printer::query()->orderBy('name')->get();
        $reachability = [];
        foreach ($printers as $printer) {
            $reachability[$printer->id] = $printService->checkPrinterReachable($printer);
        }

        return view('admin.printing.printers.index', compact('printers', 'reachability'));
    }

    public function create(): View
    {
        return view('admin.printing.printers.create');
    }

    public function store(StorePrinterRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_enabled'] = $request->boolean('is_enabled', true);
        $data['auto_print_enabled'] = $request->boolean('auto_print_enabled', true);
        if (($data['connection_type'] ?? '') === PrinterConnectionType::FileRaw->value) {
            $data['port'] = 9100;
        }

        Printer::query()->create($data);

        return redirect()->route('admin.printing.printers.index')->with('status', 'Printer saved.');
    }

    public function edit(Printer $printer): View
    {
        return view('admin.printing.printers.edit', compact('printer'));
    }

    public function update(UpdatePrinterRequest $request, Printer $printer): RedirectResponse
    {
        $data = $request->validated();
        $data['is_enabled'] = $request->boolean('is_enabled');
        $data['auto_print_enabled'] = $request->boolean('auto_print_enabled');
        if (($data['connection_type'] ?? '') === PrinterConnectionType::FileRaw->value) {
            $data['port'] = 9100;
        }

        $printer->update($data);

        return redirect()->route('admin.printing.printers.index')->with('status', 'Printer updated.');
    }

    public function destroy(Printer $printer): RedirectResponse
    {
        $printer->delete();

        return redirect()->route('admin.printing.printers.index')->with('status', 'Printer removed.');
    }

    public function testPrint(Request $request, Printer $printer, PrintService $printService): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        try {
            $printService->testPrint($printer);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.printing.printers.index')
                ->withErrors(['print' => 'Test print failed: '.$e->getMessage()]);
        }

        return redirect()->route('admin.printing.printers.index')->with('status', 'Test print sent.');
    }

    public function status(Printer $printer, PrintService $printService): JsonResponse
    {
        abort_unless(request()->user()?->isAdmin(), 403);
        $r = $printService->checkPrinterReachable($printer);

        return response()->json([
            'reachable' => $r['ok'],
            'message' => $r['message'],
        ]);
    }
}
