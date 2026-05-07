import './bootstrap';

const VOICE_ALERT_KEY = 'restaurant-os-voice-alerts';

function playNotificationSound() {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (! AudioContext) {
        return;
    }

    const ctx = new AudioContext();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.frequency.value = 880;
    gain.gain.value = 0.08;
    osc.start();

    setTimeout(() => {
        osc.stop();
        ctx.close();
    }, 180);
}

function getVoiceAlertEnabled() {
    return window.localStorage.getItem(VOICE_ALERT_KEY) === 'enabled';
}

function setVoiceAlertEnabled(enabled) {
    window.localStorage.setItem(VOICE_ALERT_KEY, enabled ? 'enabled' : 'disabled');
}

function updateVoiceButton(button, enabled) {
    if (! button) {
        return;
    }

    button.textContent = enabled ? 'Voice alerts on' : 'Enable voice alerts';
    button.classList.toggle('border-emerald-500', enabled);
    button.classList.toggle('text-emerald-300', enabled);
}

function speakNotification(message) {
    if (! ('speechSynthesis' in window) || ! getVoiceAlertEnabled()) {
        return;
    }

    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(message);
    utterance.lang = 'en-US';
    utterance.rate = 1;
    utterance.pitch = 1;
    window.speechSynthesis.speak(utterance);
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('live-orders-dashboard');
    const voiceButton = document.getElementById('voice-alert-toggle');

    if (voiceButton) {
        updateVoiceButton(voiceButton, getVoiceAlertEnabled());

        voiceButton.addEventListener('click', () => {
            const next = ! getVoiceAlertEnabled();
            setVoiceAlertEnabled(next);
            updateVoiceButton(voiceButton, next);

            if (next && 'speechSynthesis' in window) {
                window.speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance('Voice alerts enabled');
                utterance.lang = 'en-US';
                utterance.volume = 0.6;
                window.speechSynthesis.speak(utterance);
            }
        });
    }

    if (! root || ! window.Echo) {
        return;
    }

    window.Echo.channel('orders').listen('.NewOrderCreated', (payload) => {
        playNotificationSound();
        speakNotification(payload?.announcement_text ?? 'New order placed');

        const id = payload?.order?.id;

        if (! id) {
            window.location.reload();

            return;
        }

        const row = document.querySelector(`[data-order-id="${id}"]`);

        if (row) {
            row.classList.add('order-row-blink');
            setTimeout(() => row.classList.remove('order-row-blink'), 4500);
        }

        setTimeout(() => window.location.reload(), row ? 1200 : 100);
    });
});
