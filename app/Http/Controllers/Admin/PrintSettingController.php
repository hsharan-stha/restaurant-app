<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePrintSettingRequest;
use App\Models\Printer;
use App\Models\PrintSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PrintSettingController extends Controller
{
    public function edit(): View
    {
        $settings = PrintSetting::current()->load(['kitchenPrinter', 'cashierPrinter']);
        $printers = Printer::query()->orderBy('role')->orderBy('name')->get();

        return view('admin.printing.settings.edit', compact('settings', 'printers'));
    }

    public function update(UpdatePrintSettingRequest $request): RedirectResponse
    {
        $settings = PrintSetting::current();
        $settings->update([
            'auto_print_kitchen' => $request->boolean('auto_print_kitchen'),
            'auto_print_cashier' => $request->boolean('auto_print_cashier'),
            'kitchen_printer_id' => $request->filled('kitchen_printer_id') ? (int) $request->input('kitchen_printer_id') : null,
            'cashier_printer_id' => $request->filled('cashier_printer_id') ? (int) $request->input('cashier_printer_id') : null,
        ]);

        return redirect()->route('admin.printing.settings.edit')->with('status', 'Print settings saved.');
    }
}
