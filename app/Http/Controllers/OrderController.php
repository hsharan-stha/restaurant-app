<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Category;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function create(): View
    {
        $tables = DiningTable::query()->orderBy('table_number')->get();
        $categories = Category::query()
            ->where('is_active', true)
            ->with(['menuItems' => fn ($q) => $q->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $activeSessions = CustomerSession::query()
            ->whereIn('table_id', $tables->pluck('id'))
            ->where('status', SessionStatus::Active)
            ->get()
            ->keyBy('table_id');

        $tablesPayload = $tables->map(function (DiningTable $t) use ($activeSessions) {
            $session = $activeSessions->get($t->id);

            return [
                'id' => $t->id,
                'number' => $t->table_number,
                'label' => $t->table_name ? $t->table_name : 'Table '.$t->table_number,
                'status' => $t->status->value,
                'has_active_session' => $session !== null,
                'session_id' => $session?->id,
            ];
        })->values()->all();

        $catalogPayload = [
            'categories' => $categories->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'items' => $c->menuItems->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'price' => (string) $m->price,
                    'image_url' => $m->image_url,
                    'veg' => self::guessMenuItemVeg($m->name),
                ])->values()->all(),
            ])->values()->all(),
        ];

        return view('orders.create', compact('tablesPayload', 'catalogPayload'));
    }

    protected static function guessMenuItemVeg(string $name): ?bool
    {
        $n = strtolower($name);
        if (preg_match('/\b(chicken|beef|pork|fish|lamb|mutton|seafood|shrimp|prawn|meat|bone)\b/', $n)) {
            return false;
        }
        if (preg_match('/\b(veg|vegetable|paneer|tofu|salad)\b/', $n)) {
            return true;
        }

        return null;
    }

    public function store(StoreOrderRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $order = $this->orderService->createOrder(
                (int) $request->validated('table_id'),
                $request->validated('items')
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['table_id' => $e->getMessage()])->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Order placed.',
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status->value,
                    'table_id' => $order->table_id,
                    'total_amount' => (string) $order->total_amount,
                ],
                'redirect_url' => route('orders.show', $order),
            ]);
        }

        return redirect()->route('orders.show', $order)->with('status', 'Order placed.');
    }

    public function show(Order $order): View
    {
        $order->load(['table', 'items.menuItem', 'invoice', 'payments']);

        return view('orders.show', compact('order'));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse|JsonResponse
    {
        $status = OrderStatus::from($request->validated('status'));
        $updated = $this->orderService->updateStatus($order, $status);

        if ($request->wantsJson()) {
            return response()->json([
                'order' => [
                    'id' => $updated->id,
                    'status' => $updated->status->value,
                    'table_id' => $updated->table_id,
                    'total_amount' => (string) $updated->total_amount,
                ],
            ]);
        }

        return redirect()->route('dashboard');
    }
}
