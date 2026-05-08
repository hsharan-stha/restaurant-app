import './bootstrap';

const VOICE_ALERT_KEY = 'restaurant-os-voice-alerts';

function getVoiceAlertEnabled() {
    const stored = window.localStorage.getItem(VOICE_ALERT_KEY);

    if (stored === null) {
        window.localStorage.setItem(VOICE_ALERT_KEY, 'enabled');

        return true;
    }

    return stored === 'enabled';
}

function setVoiceAlertEnabled(enabled) {
    window.localStorage.setItem(VOICE_ALERT_KEY, enabled ? 'enabled' : 'disabled');
}

function updateVoiceButton(button, enabled) {
    if (!button) {
        return;
    }

    button.textContent = enabled ? 'Voice alerts on' : 'Enable voice alerts';
    button.classList.toggle('border-emerald-500', enabled);
    button.classList.toggle('text-emerald-300', enabled);
}

function playFallbackBeep() {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) {
        return;
    }

    const ctx = new AudioCtx();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();

    osc.type = 'sine';
    osc.frequency.value = 880;
    gain.gain.value = 0.05;

    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();

    window.setTimeout(() => {
        osc.stop();
        ctx.close();
    }, 180);
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('live-orders-dashboard');
    const voiceButton = document.getElementById('voice-alert-toggle');
    const actionToggle = document.getElementById('dashboard-action-toggle');
    const actionClose = document.getElementById('dashboard-action-close');
    const actionPanel = document.getElementById('dashboard-action-panel');
    const actionBackdrop = document.getElementById('dashboard-action-backdrop');
    let speechQueue = [];
    let speakingNow = false;
    let refreshScheduled = false;
    let pollInFlight = false;
    let lastSeenId = 0;
    let lastSeenOrderItemId = Number(root?.dataset?.latestOrderItemId ?? 0);
    let lastCheckoutSeenAt = root?.dataset?.latestCheckoutRequestAt ?? '';
    let preferredVoice = null;

    const pickPreferredVoice = () => {
        if (!('speechSynthesis' in window)) {
            return null;
        }

        const voices = window.speechSynthesis.getVoices();
        if (!voices || voices.length === 0) {
            return null;
        }

        const scoreVoice = (voice) => {
            const name = `${voice.name} ${voice.lang}`.toLowerCase();
            let score = 0;
            if (name.includes('google')) score += 100;
            if (name.includes('wavenet')) score += 90;
            if (name.includes('neural')) score += 80;
            if (name.includes('natural')) score += 70;
            if (name.includes('enhanced')) score += 60;
            if (name.includes('en-us')) score += 30;
            if (name.includes('en-gb')) score += 25;
            if (voice.default) score += 10;
            return score;
        };

        return [...voices].sort((a, b) => scoreVoice(b) - scoreVoice(a))[0] ?? null;
    };

    const performDashboardRefresh = () => {
        if (refreshScheduled) {
            return;
        }

        refreshScheduled = true;
        window.setTimeout(() => window.location.reload(), 250);
    };

    const processSpeechQueue = () => {
        if (
            speakingNow ||
            speechQueue.length === 0 ||
            !('speechSynthesis' in window) ||
            !getVoiceAlertEnabled()
        ) {
            return;
        }

        const message = speechQueue.shift();
        if (!message) {
            return;
        }

        speakingNow = true;

        const utterance = new SpeechSynthesisUtterance(message);
        utterance.lang = 'en-US';
        utterance.rate = 0.96;
        utterance.pitch = 1.02;
        utterance.volume = 1;
        preferredVoice = preferredVoice ?? pickPreferredVoice();
        if (preferredVoice) {
            utterance.voice = preferredVoice;
            utterance.lang = preferredVoice.lang || utterance.lang;
        }
        utterance.onend = () => {
            speakingNow = false;
            if (speechQueue.length === 0) {
                performDashboardRefresh();
            }
            processSpeechQueue();
        };
        utterance.onerror = () => {
            speakingNow = false;
            if (speechQueue.length === 0) {
                performDashboardRefresh();
            }
            processSpeechQueue();
        };

        window.speechSynthesis.speak(utterance);
    };

    const speakNotification = (message) => {
        playFallbackBeep();

        if (!('speechSynthesis' in window) || !getVoiceAlertEnabled() || !message) {
            return;
        }

        speechQueue.push(message);
        processSpeechQueue();
    };

    const scheduleDashboardRefresh = () => {
        if (!getVoiceAlertEnabled() || !('speechSynthesis' in window)) {
            performDashboardRefresh();

            return;
        }

        if (!speakingNow && speechQueue.length === 0) {
            performDashboardRefresh();
        }
    };

    const handleRealtimeAlert = (message, rowId = null, type = 'generic') => {
        speakNotification(message);

        if (rowId) {
            const row = document.querySelector(`[data-order-id="${rowId}"]`);

            if (row) {
                if (type === 'new_order') {
                    row.classList.add('order-row-recent');
                    window.setTimeout(() => row.classList.remove('order-row-recent'), 120000);
                } else {
                    row.classList.add('order-row-blink');
                    window.setTimeout(() => row.classList.remove('order-row-blink'), 4500);
                }
            }
        }

        scheduleDashboardRefresh();
    };

    const setActionPanelOpen = (isOpen) => {
        if (!actionPanel || !actionBackdrop || !actionToggle) {
            return;
        }

        actionPanel.classList.toggle('translate-x-full', !isOpen);
        actionPanel.classList.toggle('opacity-0', !isOpen);
        actionPanel.classList.toggle('pointer-events-none', !isOpen);
        actionBackdrop.classList.toggle('opacity-0', !isOpen);
        actionBackdrop.classList.toggle('bg-slate-950/0', !isOpen);
        actionBackdrop.classList.toggle('bg-slate-950/50', isOpen);
        actionBackdrop.classList.toggle('pointer-events-none', !isOpen);
        actionToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        actionPanel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    };

    const updateLastSeenFromDom = () => {
        const rowIds = [...document.querySelectorAll('[data-order-id]')]
            .map((el) => Number(el.getAttribute('data-order-id')))
            .filter((id) => Number.isFinite(id) && id > 0);
        if (rowIds.length > 0) {
            lastSeenId = Math.max(...rowIds);
        }
    };

    const pollForAlerts = async () => {
        if (!root || pollInFlight) {
            return;
        }

        pollInFlight = true;

        try {
            const url = new URL('/dashboard/poll', window.location.origin);
            url.searchParams.set('last_seen_id', String(lastSeenId));
            url.searchParams.set('last_seen_order_item_id', String(lastSeenOrderItemId));
            if (lastCheckoutSeenAt) {
                url.searchParams.set('last_checkout_seen_at', lastCheckoutSeenAt);
            }

            const response = await window.fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const orders = Array.isArray(payload?.orders) ? payload.orders : [];
            const checkoutRequests = Array.isArray(payload?.checkout_requests) ? payload.checkout_requests : [];
            const maxOrderItemId = Number(payload?.max_order_item_id ?? 0);
            if (Number.isFinite(maxOrderItemId) && maxOrderItemId > lastSeenOrderItemId) {
                lastSeenOrderItemId = maxOrderItemId;
            }

            orders.forEach((order) => {
                const id = Number(order?.id ?? 0);
                if (id > lastSeenId) {
                    lastSeenId = id;
                }
                const alertType = order?.type === 'preparing_order_update'
                    ? 'preparing_order_update'
                    : 'new_order';
                handleRealtimeAlert(
                    order?.announcement_text
                        ?? (alertType === 'preparing_order_update'
                            ? `Table number ${order?.table_number ?? ''} has added an order`
                            : `Table number ${order?.table_number ?? ''} has placed an order`),
                    id || null,
                    alertType,
                );
            });

            checkoutRequests.forEach((order) => {
                if (order?.checkout_requested_at) {
                    lastCheckoutSeenAt = order.checkout_requested_at;
                }
                handleRealtimeAlert(
                    order?.announcement_text ?? `Table number ${order?.table_number ?? ''} has requested checkout`,
                    Number(order?.id ?? 0) || null,
                    'checkout',
                );
            });
        } catch (_error) {
            // Ignore transient polling errors; next interval will retry.
        } finally {
            pollInFlight = false;
        }
    };

    if (voiceButton) {
        // Keep voice alerts enabled by default on dashboard load for reliability.
        if (!getVoiceAlertEnabled()) {
            setVoiceAlertEnabled(true);
        }
        updateVoiceButton(voiceButton, getVoiceAlertEnabled());

        voiceButton.addEventListener('click', () => {
            const next = !getVoiceAlertEnabled();
            setVoiceAlertEnabled(next);
            updateVoiceButton(voiceButton, next);

            if (!('speechSynthesis' in window)) {
                return;
            }

            if (next) {
                speechQueue = [];
                speakingNow = false;
                const utterance = new SpeechSynthesisUtterance('Voice alerts enabled');
                preferredVoice = preferredVoice ?? pickPreferredVoice();
                utterance.lang = preferredVoice?.lang || 'en-US';
                utterance.volume = 0.6;
                utterance.rate = 0.96;
                utterance.pitch = 1.02;
                if (preferredVoice) {
                    utterance.voice = preferredVoice;
                }
                window.speechSynthesis.speak(utterance);
            } else {
                speechQueue = [];
                speakingNow = false;
                window.speechSynthesis.cancel();
            }
        });
    }

    if (actionToggle && actionPanel && actionBackdrop) {
        actionToggle.addEventListener('click', () => {
            const isOpen = actionToggle.getAttribute('aria-expanded') === 'true';
            setActionPanelOpen(!isOpen);
        });

        actionClose?.addEventListener('click', () => setActionPanelOpen(false));
        actionBackdrop.addEventListener('click', () => setActionPanelOpen(false));

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setActionPanelOpen(false);
            }
        });
    }

    if (!root) {
        return;
    }

    if ('speechSynthesis' in window) {
        preferredVoice = pickPreferredVoice();
        window.speechSynthesis.onvoiceschanged = () => {
            preferredVoice = pickPreferredVoice();
        };
    }

    updateLastSeenFromDom();
    window.setInterval(pollForAlerts, 4000);
});
