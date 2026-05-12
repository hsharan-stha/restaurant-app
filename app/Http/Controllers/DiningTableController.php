<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Enums\DiningSessionStatus;
use App\Enums\TableStatus;
use App\Http\Requests\StoreDiningTableRequest;
use App\Http\Requests\UpdateDiningTableRequest;
use App\Models\CustomerSession;
use App\Models\DiningSession;
use App\Models\DiningTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DiningTableController extends Controller
{
    public function floorPlan(): View
    {
        return view('admin.dining-tables.floor-plan');
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

    public function destroy(Request $request, DiningTable $diningTable): RedirectResponse|JsonResponse
    {
        $diningTable->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Table deleted.']);
        }

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

    public function clearSession(Request $request, DiningTable $diningTable): JsonResponse
    {
        $customerSession = CustomerSession::query()
            ->where('table_id', $diningTable->id)
            ->where('status', SessionStatus::Active->value)
            ->latest('id')
            ->first();

        $diningSession = DiningSession::query()
            ->where('table_id', $diningTable->id)
            ->whereIn('status', [
                DiningSessionStatus::Open->value,
                DiningSessionStatus::InProgress->value,
                DiningSessionStatus::FoodDelivered->value,
            ])
            ->latest('id')
            ->first();

        if (! $customerSession && ! $diningSession) {
            throw ValidationException::withMessages([
                'session' => 'There is no active session to clear for this table.',
            ]);
        }

        $hasOrders = $diningTable->orders()
            ->where(function ($query) use ($customerSession, $diningSession) {
                if ($customerSession) {
                    $query->where('customer_session_id', $customerSession->id);
                }

                if ($diningSession) {
                    $method = $customerSession ? 'orWhere' : 'where';
                    $query->{$method}('dining_session_id', $diningSession->id);
                }
            })
            ->exists();

        if ($hasOrders) {
            throw ValidationException::withMessages([
                'session' => 'This session already has orders. Use checkout for billed visits; clear session is only for empty QR sessions.',
            ]);
        }

        DB::transaction(function () use ($customerSession, $diningSession, $diningTable) {
            $now = now();

            if ($customerSession) {
                $customerSession->update([
                    'status' => SessionStatus::Completed,
                    'closed_at' => $customerSession->closed_at ?? $now,
                    'last_seen_at' => $now,
                ]);
            }

            if ($diningSession) {
                $diningSession->update([
                    'status' => DiningSessionStatus::Cancelled,
                    'closed_at' => $diningSession->closed_at ?? $now,
                ]);
            }

            $diningTable->update([
                'status' => TableStatus::Available,
            ]);
        });

        return response()->json([
            'message' => 'Session cleared and table marked available.',
        ]);
    }
}
