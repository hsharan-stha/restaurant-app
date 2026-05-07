@extends('layouts.app')

@section('title', 'Live orders')

@section('content')
    @php
        use App\Enums\OrderStatus;
        use App\Enums\PaymentStatus;
    @endphp

    <div id="live-orders-dashboard">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-white">Live orders</h1>
                <p class="mt-1 text-sm text-slate-400">Broadcasting channel <code class="text-emerald-400">orders</code> - new orders play a tone, speak the table number, and refresh the board.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    id="voice-alert-toggle"
                    class="rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-medium text-slate-200 hover:border-emerald-500 hover:text-white"
                >
                    Enable voice alerts
                </button>
                <a href="{{ route('orders.create') }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">New order</a>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            @include('partials.order-column', ['title' => 'Pending', 'titleClass' => 'text-amber-400', 'orders' => $pendingOrders])
            @include('partials.order-column', ['title' => 'Preparing', 'titleClass' => 'text-sky-400', 'orders' => $preparingOrders])
            @include('partials.order-column', ['title' => 'Completed', 'titleClass' => 'text-emerald-400', 'orders' => $completedOrders])
        </div>
    </div>

    <script>
        (() => {
            const VOICE_ALERT_KEY = 'restaurant-os-voice-alerts';
            const root = document.getElementById('live-orders-dashboard');
            const voiceButton = document.getElementById('voice-alert-toggle');

            const getVoiceAlertEnabled = () => window.localStorage.getItem(VOICE_ALERT_KEY) === 'enabled';

            const setVoiceAlertEnabled = (enabled) => {
                window.localStorage.setItem(VOICE_ALERT_KEY, enabled ? 'enabled' : 'disabled');
            };

            const updateVoiceButton = (enabled) => {
                if (!voiceButton) {
                    return;
                }

                voiceButton.textContent = enabled ? 'Voice alerts on' : 'Enable voice alerts';
                voiceButton.classList.toggle('border-emerald-500', enabled);
                voiceButton.classList.toggle('text-emerald-300', enabled);
            };

            const speakNotification = (message) => {
                if (!('speechSynthesis' in window) || !getVoiceAlertEnabled()) {
                    return;
                }

                window.speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance(message);
                utterance.lang = 'en-US';
                utterance.rate = 1;
                utterance.pitch = 1;
                window.speechSynthesis.speak(utterance);
            };

            if (voiceButton) {
                updateVoiceButton(getVoiceAlertEnabled());

                voiceButton.addEventListener('click', () => {
                    const next = !getVoiceAlertEnabled();
                    setVoiceAlertEnabled(next);
                    updateVoiceButton(next);

                    if (next && 'speechSynthesis' in window) {
                        window.speechSynthesis.cancel();
                        const utterance = new SpeechSynthesisUtterance('Voice alerts enabled');
                        utterance.lang = 'en-US';
                        utterance.volume = 0.6;
                        window.speechSynthesis.speak(utterance);
                    }
                });
            }

            if (!root || !window.Echo) {
                return;
            }

            window.Echo.channel('orders').listen('.NewOrderCreated', (payload) => {
                speakNotification(payload?.announcement_text ?? 'New order placed');
            });
        })();
    </script>
@endsection
