<?php

namespace App\Http\Controllers\Api;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\DiningSession;
use App\Services\DiningSessionService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiningSessionApiController extends Controller
{
    public function __construct(
        protected DiningSessionService $diningSessionService,
        protected OrderService $orderService,
        protected PaymentService $paymentService,
    ) {}

    public function createDiningSession(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'table_id' => ['required', 'integer', 'exists:dining_tables,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
        ]);

        $session = $this->diningSessionService->getOrCreateOpenForTable(
            (int) $payload['table_id'],
            $request->user()?->id
        );
        if (! empty($payload['customer_name']) && ! $session->customer_name) {
            $session->update(['customer_name' => (string) $payload['customer_name']]);
        }

        return response()->json($session->fresh(['table']), 201);
    }

    public function getActiveDiningSessionByTable(int $tableId): JsonResponse
    {
        $session = DiningSession::query()
            ->with(['orders.items.menuItem', 'table'])
            ->where('table_id', $tableId)
            ->whereIn('status', [
                DiningSessionStatus::Open->value,
                DiningSessionStatus::InProgress->value,
                DiningSessionStatus::FoodDelivered->value,
            ])
            ->latest('id')
            ->first();

        return response()->json(['session' => $session]);
    }

    public function addOrderToSession(Request $request, DiningSession $diningSession): JsonResponse
    {
        abort_if(
            in_array($diningSession->status, [DiningSessionStatus::Completed, DiningSessionStatus::CheckedOut, DiningSessionStatus::Cancelled], true),
            422,
            'Cannot add orders to a closed session.'
        );
        $payload = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
            'items.*.options' => ['nullable', 'array'],
        ]);

        $order = $this->orderService->createOrAppendOrder(
            $diningSession->table_id,
            $payload['items'],
            null
        );

        if (! $order->dining_session_id) {
            $order->update(['dining_session_id' => $diningSession->id]);
        }

        return response()->json($order->fresh(['items.menuItem', 'diningSession']), 201);
    }

    public function checkoutDiningSession(Request $request, DiningSession $diningSession): JsonResponse
    {
        abort_if(
            in_array($diningSession->status, [DiningSessionStatus::Completed, DiningSessionStatus::CheckedOut, DiningSessionStatus::Cancelled], true),
            422,
            'Session already closed.'
        );

        $request->validate([
            'method' => ['nullable', 'in:cash,card,online'],
        ]);
        $method = PaymentMethod::tryFrom((string) $request->input('method', 'cash')) ?? PaymentMethod::Cash;

        $checkoutTarget = $diningSession->orders()
            ->where('status', OrderStatus::Completed->value)
            ->orderBy('id')
            ->first();

        abort_if(! $checkoutTarget, 422, 'Cannot checkout an empty session.');

        $this->paymentService->processLocalPayment($checkoutTarget, $method);

        return response()->json([
            'message' => 'Session checked out.',
            'session' => $diningSession->fresh(['orders.invoice', 'table']),
        ]);
    }

    public function getDiningSessionDetails(DiningSession $diningSession): JsonResponse
    {
        $session = $diningSession->load(['table', 'orders.items.menuItem', 'orders.invoice', 'orders.payments']);

        return response()->json($session);
    }

    public function getCompletedDiningSessions(): JsonResponse
    {
        $sessions = DiningSession::query()
            ->with(['table', 'orders.payments'])
            ->whereIn('status', [DiningSessionStatus::Completed->value, DiningSessionStatus::CheckedOut->value])
            ->orderByDesc('closed_at')
            ->get();

        return response()->json([
            'sessions' => $sessions,
        ]);
    }
}
