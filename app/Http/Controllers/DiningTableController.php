<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Http\Requests\StoreDiningTableRequest;
use App\Http\Requests\UpdateDiningTableRequest;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DiningTableController extends Controller
{
    public function index(): View
    {
        $tables = DiningTable::query()
            ->orderBy('table_number')
            ->with(['customerSessions' => function ($query) {
                $query->with(['orders' => function ($query) {
                    $query->with('payments');
                }]);
            }])
            ->get();

        return view('admin.dining-tables.index', compact('tables'));
    }

    public function create(): View
    {
        return view('admin.dining-tables.create');
    }

    public function store(StoreDiningTableRequest $request): RedirectResponse
    {
        DiningTable::query()->create($request->validated());

        return redirect()->route('dining-tables.index')->with('status', 'Table created.');
    }

    public function edit(DiningTable $diningTable): View
    {
        return view('admin.dining-tables.edit', compact('diningTable'));
    }

    public function update(UpdateDiningTableRequest $request, DiningTable $diningTable): RedirectResponse
    {
        $diningTable->update($request->validated());

        return redirect()->route('dining-tables.index')->with('status', 'Table updated.');
    }

    public function destroy(DiningTable $diningTable): RedirectResponse
    {
        $diningTable->delete();

        return redirect()->route('dining-tables.index')->with('status', 'Table deleted.');
    }

    public function destroyCustomerSession(CustomerSession $customerSession): RedirectResponse
    {
        // Check if table is occupied
        $table = $customerSession->table;
        if ($table->status === TableStatus::Occupied) {
            throw ValidationException::withMessages([
                'customer_session' => 'Cannot delete customer session. The table is currently occupied. Please mark the table as available first.',
            ]);
        }

        // Check if customer has any preparing orders
        $hasPreparingOrder = $customerSession->orders()
            ->where('status', OrderStatus::Preparing->value)
            ->exists();

        if ($hasPreparingOrder) {
            throw ValidationException::withMessages([
                'customer_session' => 'Cannot delete customer session. The customer has an order that is still being prepared. Please wait for the order to complete.',
            ]);
        }

        $customerSession->delete();

        return redirect()->route('dining-tables.index')->with('status', 'Customer session deleted.');
    }
}
