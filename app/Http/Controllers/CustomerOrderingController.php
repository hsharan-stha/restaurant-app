<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Events\CheckoutRequested;
use App\Http\Requests\StoreCustomerOrderRequest;
use App\Models\Category;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerOrderingController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function enter(Request $request, DiningTable $table): View|RedirectResponse
    {
        try {
            $customerSession = $this->resolveOrCreateSession($request, $table);
        } catch (\InvalidArgumentException $e) {
            return view('guest.table-unavailable', [
                'table' => $table,
                'message' => $e->getMessage(),
            ]);
        }

        $request->session()->put('customer_session_token', $customerSession->session_token);

        return redirect()->route('guest.menu');
    }

    public function menu(Request $request): View|RedirectResponse
    {
        $customerSession = $this->getActiveSession($request);

        if (! $customerSession) {
            return redirect()->route('login')->withErrors([
                'email' => 'Scan the table QR code to begin a guest ordering session.',
            ]);
        }

        $customerSession->update(['last_seen_at' => now()]);

        $categories = Category::query()
            ->with(['menuItems' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        $sessionOrders = Order::query()
            ->with(['items.menuItem', 'table', 'invoice', 'payments'])
            ->where('customer_session_id', $customerSession->id)
            ->latest('id')
            ->get();
        $activeOrder = $sessionOrders
            ->first(fn ($order) => in_array($order->status, [OrderStatus::Pending, OrderStatus::Preparing], true));
        $sessionTotal = $sessionOrders->sum(fn ($order) => (float) $order->total_amount);
        $hasOpenKitchenOrders = $sessionOrders->contains(
            fn ($order) => in_array($order->status, [OrderStatus::Pending, OrderStatus::Preparing], true)
        );

        return view('guest.menu', [
            'customerSession' => $customerSession,
            'table' => $customerSession->table,
            'categories' => $categories,
            'activeOrder' => $activeOrder,
            'sessionOrders' => $sessionOrders,
            'sessionTotal' => $sessionTotal,
            'hasOpenKitchenOrders' => $hasOpenKitchenOrders,
        ]);
    }

    public function store(StoreCustomerOrderRequest $request): RedirectResponse
    {
        $customerSession = $this->getActiveSession($request);
        abort_unless($customerSession, 403, 'Guest session not found.');

        try {
            $order = $this->orderService->createOrAppendOrder(
                $customerSession->table_id,
                $request->validated('items'),
                $customerSession
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('guest.menu')->withErrors(['order' => $e->getMessage()]);
        }

        $customerSession->update(['last_seen_at' => now()]);

        return redirect()
            ->route('guest.menu', ['ordered' => $order->id])
            ->with('status', 'Your order has been sent to the kitchen.');
    }

    public function proceedToCheckout(Request $request): RedirectResponse
    {
        $customerSession = $this->getActiveSession($request);
        abort_unless($customerSession, 403, 'Guest session not found.');

        $activeOrder = Order::query()
            ->where('customer_session_id', $customerSession->id)
            ->latest('id')
            ->first();

        if (! $activeOrder) {
            return redirect()->route('guest.menu')->withErrors([
                'order' => 'There is no order available for checkout.',
            ]);
        }

        $activeOrder->update(['checkout_requested_at' => now()]);
        $activeOrder->load('table');
        $customerSession->update(['last_seen_at' => now()]);
        rescue(fn () => event(new CheckoutRequested($activeOrder)), report: false);

        return redirect()->route('guest.menu')->with('status', 'Checkout request sent to staff.');
    }

    protected function getActiveSession(Request $request): ?CustomerSession
    {
        $token = $request->session()->get('customer_session_token');

        if (! $token) {
            return null;
        }

        return CustomerSession::query()
            ->with('table')
            ->where('session_token', $token)
            ->whereNull('closed_at')
            ->first();
    }

    protected function resolveOrCreateSession(Request $request, DiningTable $table): CustomerSession
    {
        $activeSession = $this->getActiveSession($request);

        if ($activeSession && $activeSession->table_id === $table->id) {
            $activeSession->update(['last_seen_at' => now()]);

            return $activeSession;
        }

        $otherOpenSessionExists = CustomerSession::query()
            ->where('table_id', $table->id)
            ->whereNull('closed_at')
            ->when(
                $activeSession,
                fn ($query) => $query->whereKeyNot($activeSession->id)
            )
            ->exists();

        if ($otherOpenSessionExists) {
            throw new \InvalidArgumentException(sprintf(
                'Table %s is already occupied in different session.',
                $table->table_number
            ));
        }

        return CustomerSession::query()->create([
            'table_id' => $table->id,
            'session_token' => (string) Str::uuid(),
            'started_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
        ]);
    }
}
