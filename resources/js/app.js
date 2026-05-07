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

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('live-orders-dashboard');
    const voiceButton = document.getElementById('voice-alert-toggle');
    let speechQueue = [];
    let speakingNow = false;
    let refreshScheduled = false;

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
        utterance.rate = 1;
        utterance.pitch = 1;
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

    const handleRealtimeAlert = (message, rowId = null) => {
        speakNotification(message);

        if (rowId) {
            const row = document.querySelector(`[data-order-id="${rowId}"]`);

            if (row) {
                row.classList.add('order-row-blink');
                window.setTimeout(() => row.classList.remove('order-row-blink'), 4500);
            }
        }

        scheduleDashboardRefresh();
    };

    if (voiceButton) {
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
                utterance.lang = 'en-US';
                utterance.volume = 0.6;
                window.speechSynthesis.speak(utterance);
            } else {
                speechQueue = [];
                speakingNow = false;
                window.speechSynthesis.cancel();
            }
        });
    }

    if (!root || !window.Echo) {
        return;
    }

    window.Echo.channel('orders')
        .listen('.NewOrderCreated', (payload) => {
            handleRealtimeAlert(
                payload?.announcement_text ?? `Table number ${payload?.order?.table?.table_number ?? ''} has placed an order`,
                payload?.order?.id ?? null,
            );
        })
        .listen('.CheckoutRequested', (payload) => {
            handleRealtimeAlert(
                payload?.announcement_text ?? `Table number ${payload?.order?.table?.table_number ?? ''} has requested checkout`,
                payload?.order?.id ?? null,
            );
        });
});
