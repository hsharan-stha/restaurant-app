/**
 * Guest menu: listen on public table.{id} channel for OrderUpdated (staff edited pending order).
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

function meta(name) {
    return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? '';
}

document.addEventListener('DOMContentLoaded', () => {
    const tableId = meta('guest-table-id');
    const summaryUrl = meta('guest-order-summary-url');
    if (!tableId || !summaryUrl) {
        return;
    }

    const broadcastDriver = meta('guest-broadcast-driver') || 'null';
    const reverbKey = meta('guest-reverb-key');
    const reverbHost = meta('guest-reverb-host') || window.location.hostname;
    const reverbPort = Number(meta('guest-reverb-port') || '8080');
    const reverbScheme = (meta('guest-reverb-scheme') || 'http').toLowerCase();
    const pusherKey = meta('guest-pusher-key');
    const pusherCluster = meta('guest-pusher-cluster') || 'mt1';

    function refreshOrderPanel() {
        const url = summaryUrl.includes('?') ? `${summaryUrl}&partial=1` : `${summaryUrl}?partial=1`;
        window
            .fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then((r) => {
                if (!r.ok) {
                    return null;
                }
                return r.text();
            })
            .then((html) => {
                if (!html) {
                    return;
                }
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const next = doc.getElementById('guest-order-panel');
                const cur = document.getElementById('guest-order-panel');
                if (next && cur) {
                    cur.replaceWith(next);
                }
            })
            .catch(() => {});
    }

    const channelName = `table.${tableId}`;

    if (broadcastDriver === 'reverb' && reverbKey) {
        window.Pusher = Pusher;
        const forceTLS = reverbScheme === 'https';
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: reverbHost,
            wsPort: reverbPort,
            wssPort: reverbPort,
            forceTLS,
            enabledTransports: ['ws', 'wss'],
            disableStats: true,
        });
        const ch = window.Echo.channel(channelName);
        ch.listen('.OrderUpdated', refreshOrderPanel);
        ch.listen('.OrderPlaced', refreshOrderPanel);
        ch.listen('.CheckoutRequested', refreshOrderPanel);
    } else if (broadcastDriver === 'pusher' && pusherKey) {
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: pusherKey,
            cluster: pusherCluster,
            forceTLS: true,
        });
        const ch = window.Echo.channel(channelName);
        ch.listen('.OrderUpdated', refreshOrderPanel);
        ch.listen('.OrderPlaced', refreshOrderPanel);
        ch.listen('.CheckoutRequested', refreshOrderPanel);
    }
});
