import './bootstrap';

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

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('live-orders-dashboard');
    if (! root || ! window.Echo) {
        return;
    }

    window.Echo.channel('orders').listen('.NewOrderCreated', (payload) => {
        playNotificationSound();
        const id = payload?.order?.id;
        if (! id) {
            window.location.reload();

            return;
        }
        const row = document.querySelector(`[data-order-id="${id}"]`);
        if (row) {
            row.classList.add('order-row-blink');
            setTimeout(() => row.classList.remove('order-row-blink'), 4500);
        } else {
            window.location.reload();
        }
    });
});
