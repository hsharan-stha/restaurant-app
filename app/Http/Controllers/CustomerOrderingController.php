<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
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

    public function enter(Request $request, DiningTable $table): RedirectResponse
    {
        $customerSession = $this->resolveOrCreateSession($request, $table);

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

        $activeOrder = Order::query()
            ->with(['items.menuItem', 'table'])
            ->where('table_id', $customerSession->table_id)
            ->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Preparing->value])
            ->latest('id')
            ->first();

        return view('guest.menu', [
            'customerSession' => $customerSession,
            'table' => $customerSession->table,
            'categories' => $categories,
            'activeOrder' => $activeOrder,
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

        return CustomerSession::query()->create([
            'table_id' => $table->id,
            'session_token' => (string) Str::uuid(),
            'started_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
        ]);
    }
}
