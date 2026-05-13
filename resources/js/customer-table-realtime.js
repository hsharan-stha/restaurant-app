/**
 * Guest menu: periodic refresh of the order summary panel (staff edits, checkout requests).
 */
const GUEST_ORDER_POLL_MS = 5000;

function meta(name) {
    return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? '';
}

document.addEventListener('DOMContentLoaded', () => {
    const tableId = meta('guest-table-id');
    const summaryUrl = meta('guest-order-summary-url');
    if (!tableId || !summaryUrl) {
        return;
    }

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

    void refreshOrderPanel();
    window.setInterval(refreshOrderPanel, GUEST_ORDER_POLL_MS);
});
