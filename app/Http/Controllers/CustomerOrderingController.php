<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\SessionStatus;
use App\Enums\TableStatus;
use App\Events\CheckoutRequested;
use App\Events\GuestSessionStarted;
use App\Http\Requests\StartGuestSessionRequest;
use App\Http\Requests\StoreCustomerOrderRequest;
use App\Models\Category;
use App\Models\CustomerSession;
use App\Models\DiningTable;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerOrderingController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * QR scan / deep link: creates session (party size set on welcome screen).
     */
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

        return redirect()->route('guest.welcome');
    }

    /**
     * Welcome screen: guest count required before menu.
     */
    public function welcome(Request $request): View|RedirectResponse
    {
        $customerSession = $this->getActiveSession($request);

        if ($customerSession && $customerSession->party_size !== null && $customerSession->party_size >= 1) {
            return redirect()->route('guest.menu');
        }

        $table = null;

        if ($customerSession) {
            $table = $customerSession->table;
        } else {
            $table = $this->resolveTableFromQuery($request);
            if (! $table && $request->session()->has('guest_intended_table_id')) {
                $table = DiningTable::query()->find($request->session()->get('guest_intended_table_id'));
            }
            if (! $table) {
                return redirect()->route('guest.need-qr');
            }
            $request->session()->put('guest_intended_table_id', $table->id);
        }

        return view('guest.welcome', [
            'table' => $table,
            'customerSession' => $customerSession,
            'restaurantDisplayName' => (string) config('restaurant.display_name', config('app.name')),
        ]);
    }

    public function needQr(): View
    {
        return view('guest.need-qr', [
            'restaurantDisplayName' => (string) config('restaurant.display_name', config('app.name')),
        ]);
    }

    /**
     * Start dining session after guest count is chosen.
     */
    public function startSession(StartGuestSessionRequest $request): RedirectResponse
    {
        $count = (int) $request->validated('guest_count');
        $session = $this->getActiveSession($request);

        if ($session) {
            if ($session->party_size !== null && $session->party_size >= 1) {
                return redirect()->route('guest.menu');
            }
            $session->update([
                'party_size' => $count,
                'last_seen_at' => now(),
            ]);
            $table = $session->table;
        } else {
            $tableId = $request->session()->pull('guest_intended_table_id');
            if (! $tableId) {
                return redirect()->route('guest.need-qr');
            }
            $table = DiningTable::query()->findOrFail($tableId);
            try {
                $session = $this->createSessionForTable($request, $table, $count);
            } catch (\InvalidArgumentException $e) {
                return redirect()->route('guest.welcome', $request->query())
                    ->withErrors(['guest_count' => $e->getMessage()]);
            }
            $request->session()->put('customer_session_token', $session->session_token);
        }

        $table->update(['status' => TableStatus::Occupied]);
        $session->refresh();

        rescue(fn () => event(new GuestSessionStarted($table->fresh(), $count)), report: false);

        return redirect()->route('guest.menu');
    }

    public function menu(Request $request): View|RedirectResponse
    {
        $redirect = $this->ensureGuestCanOrder($request);
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }
        $customerSession = $redirect;

        $customerSession->update(['last_seen_at' => now()]);

        $categories = $this->guestMenuCategories();

        foreach ($categories as $category) {
            foreach ($category->menuItems as $menuItem) {
                $menuItem->setAttribute('veg_hint', self::guessMenuItemVeg($menuItem->name));
            }
        }

        $sessionOrders = $this->guestSessionOrders($customerSession);
        $activeOrder = $sessionOrders
            ->first(fn ($order) => in_array($order->status, [
                OrderStatus::Pending,
                OrderStatus::Preparing,
                OrderStatus::Completed,
            ], true));
        $sessionTotal = $sessionOrders->sum(fn ($order) => (float) $order->total_amount);
        $hasOpenKitchenOrders = $sessionOrders->contains(
            fn ($order) => in_array($order->status, [
                OrderStatus::Pending,
                OrderStatus::Preparing,
                OrderStatus::Completed,
            ], true)
        );

        return view('guest.menu', [
            'customerSession' => $customerSession,
            'table' => $customerSession->table,
            'categories' => $categories,
            'activeOrder' => $activeOrder,
            'sessionOrders' => $sessionOrders,
            'sessionTotal' => $sessionTotal,
            'hasOpenKitchenOrders' => $hasOpenKitchenOrders,
            'restaurantDisplayName' => (string) config('restaurant.display_name', config('app.name')),
            'taxRate' => (float) config('restaurant.tax_rate', 0),
        ]);
    }

    public function store(StoreCustomerOrderRequest $request): RedirectResponse|JsonResponse
    {
        $redirect = $this->ensureGuestCanOrder($request);
        if ($redirect instanceof RedirectResponse) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Session not ready. Open the menu from your table QR.'], 403);
            }

            return $redirect;
        }
        $customerSession = $redirect;

        try {
            $order = $this->orderService->createOrAppendOrder(
                $customerSession->table_id,
                $request->validated('items'),
                $customerSession
            );
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->route('guest.menu')->withErrors(['order' => $e->getMessage()]);
        }

        $customerSession->update(['last_seen_at' => now()]);

        if ($request->wantsJson()) {
            $sessionOrders = $this->guestSessionOrders($customerSession->fresh('table'));
            $activeOrder = $sessionOrders
                ->first(fn ($sessionOrder) => in_array($sessionOrder->status, [
                    OrderStatus::Pending,
                    OrderStatus::Preparing,
                    OrderStatus::Completed,
                ], true));
            $sessionTotal = $sessionOrders->sum(fn ($sessionOrder) => (float) $sessionOrder->total_amount);
            $hasOpenKitchenOrders = $sessionOrders->contains(
                fn ($sessionOrder) => in_array($sessionOrder->status, [
                    OrderStatus::Pending,
                    OrderStatus::Preparing,
                    OrderStatus::Completed,
                ], true)
            );

            return response()->json([
                'message' => 'Your order has been sent to the kitchen.',
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status->value,
                    'total_amount' => (string) $order->total_amount,
                    'table_id' => $order->table_id,
                ],
                'order_panel_html' => view('guest.partials.order-session-panel', [
                    'sessionOrders' => $sessionOrders,
                    'sessionTotal' => $sessionTotal,
                    'activeOrder' => $activeOrder,
                    'table' => $customerSession->table,
                    'hasOpenKitchenOrders' => $hasOpenKitchenOrders,
                ])->render(),
            ]);
        }

        return redirect()
            ->route('guest.menu', ['ordered' => $order->id])
            ->with('status', 'Your order has been sent to the kitchen.');
    }

    public function proceedToCheckout(Request $request): RedirectResponse|JsonResponse
    {
        $redirect = $this->ensureGuestCanOrder($request);
        if ($redirect instanceof RedirectResponse) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Session not ready.'], 403);
            }

            return $redirect;
        }
        $customerSession = $redirect;

        $activeOrder = Order::query()
            ->where('customer_session_id', $customerSession->id)
            ->latest('id')
            ->first();

        if (! $activeOrder) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'There is no order available for checkout.'], 422);
            }

            return redirect()->route('guest.menu')->withErrors([
                'order' => 'There is no order available for checkout.',
            ]);
        }

        $activeOrder->update(['checkout_requested_at' => now()]);
        $activeOrder->load('table');
        $customerSession->update(['last_seen_at' => now()]);
        rescue(fn () => event(new CheckoutRequested($activeOrder)), report: false);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Checkout request sent to staff.',
                'order_id' => $activeOrder->id,
            ]);
        }

        return redirect()->route('guest.menu')->with('status', 'Checkout request sent to staff.');
    }

    /**
     * Full order summary page, or HTML fragment when partial=1 (for realtime refresh).
     */
    public function orderSummary(Request $request): View|RedirectResponse
    {
        $redirect = $this->ensureGuestCanOrder($request);
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }
        $customerSession = $redirect;

        $sessionOrders = $this->guestSessionOrders($customerSession);

        $activeOrder = $sessionOrders->first(
            fn ($order) => in_array($order->status, [
                OrderStatus::Pending,
                OrderStatus::Preparing,
                OrderStatus::Completed,
            ], true)
        );
        $sessionTotal = $sessionOrders->sum(fn ($order) => (float) $order->total_amount);
        $table = $customerSession->table;

        $hasOpenKitchenOrders = $sessionOrders->contains(
            fn ($order) => in_array($order->status, [
                OrderStatus::Pending,
                OrderStatus::Preparing,
                OrderStatus::Completed,
            ], true)
        );

        $taxRate = (float) config('restaurant.tax_rate', 0);
        $taxEstimate = round((float) $sessionTotal * $taxRate, 2);
        $grandEstimate = round((float) $sessionTotal + $taxEstimate, 2);

        $payload = compact(
            'sessionOrders',
            'sessionTotal',
            'activeOrder',
            'table',
            'hasOpenKitchenOrders'
        );

        if ($request->boolean('partial')) {
            return view('guest.partials.order-session-panel', $payload);
        }

        return view('guest.order-summary', array_merge($payload, [
            'customerSession' => $customerSession,
            'restaurantDisplayName' => (string) config('restaurant.display_name', config('app.name')),
            'taxRate' => $taxRate,
            'taxEstimate' => $taxEstimate,
            'grandEstimate' => $grandEstimate,
        ]));
    }

    protected function ensureGuestCanOrder(Request $request): CustomerSession|RedirectResponse
    {
        $session = $this->getActiveSession($request);
        if (! $session) {
            $table = $this->resolveTableFromQuery($request);
            if ($table) {
                $request->session()->put('guest_intended_table_id', $table->id);

                return redirect()->route('guest.welcome', ['table' => $request->query('table')]);
            }

            return redirect()->route('guest.need-qr');
        }

        if ($session->party_size === null || $session->party_size < 1) {
            return redirect()->route('guest.welcome');
        }

        return $session;
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

    protected function resolveTableFromQuery(Request $request): ?DiningTable
    {
        $code = $request->query('table');
        if (! is_string($code)) {
            return null;
        }
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $byName = DiningTable::query()->where('table_name', $code)->first();
        if ($byName) {
            return $byName;
        }

        if (ctype_digit($code)) {
            return DiningTable::query()->where('table_number', (int) $code)->first();
        }

        return null;
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
            ->where('status', SessionStatus::Active->value)
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
            ->where('status', SessionStatus::Active->value)
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

        return $this->createSessionForTable($request, $table, null);
    }

    protected function createSessionForTable(Request $request, DiningTable $table, ?int $partySize): CustomerSession
    {
        $activeSession = $this->getActiveSession($request);
        $otherOpenSessionExists = CustomerSession::query()
            ->where('table_id', $table->id)
            ->where('status', SessionStatus::Active->value)
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
            'party_size' => $partySize,
            'started_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
            'status' => SessionStatus::Active,
        ]);
    }

    protected function guestMenuCategories()
    {
        return Cache::remember('guest:menu-categories', now()->addMinutes(5), function () {
            return Category::query()
                ->where('is_active', true)
                ->with(['menuItems' => fn ($query) => $query->where('is_available', true)->orderBy('name')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        });
    }

    protected function guestSessionOrders(CustomerSession $customerSession)
    {
        return Order::query()
            ->with(['items.menuItem'])
            ->where('customer_session_id', $customerSession->id)
            ->latest('id')
            ->get();
    }
}
