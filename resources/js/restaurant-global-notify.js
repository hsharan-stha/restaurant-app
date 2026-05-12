/**
 * Global staff realtime: Laravel Echo (Reverb) + Web Speech API.
 * Loaded on authenticated admin layouts. One Echo connection per tab.
 * Speech unlocks after first user gesture (browser policy); sessionStorage persists for the tab.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const SPEECH_TAB_KEY = 'restaurant-speech-unlocked';
const PENDING_REPEAT_MS = 4000;
const ORDER_SEEN_KEY = 'restaurant-dashboard-order-seen-v1';

function meta(name) {
    return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? '';
}

function tableDisplayLabel(tableName, tableNumber) {
    const n = (tableName ?? '').trim();
    if (n) {
        return n;
    }
    return `Table ${tableNumber ?? ''}`;
}

function labelFromPayload(p) {
    if (!p) {
        return 'Table';
    }
    return tableDisplayLabel(p.table_name, p.table_number);
}

async function fetchFloorTables(url) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const res = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
        },
    });
    if (!res.ok) {
        return null;
    }
    return res.json();
}

document.addEventListener('DOMContentLoaded', () => {
    if (meta('restaurant-notify') !== '1') {
        return;
    }
    if (window.__restaurantNotifyInit) {
        return;
    }
    window.__restaurantNotifyInit = true;

    const broadcastDriver = meta('restaurant-broadcast-driver') || 'null';
    const reverbKey = meta('restaurant-reverb-key');
    const reverbHost = meta('restaurant-reverb-host') || window.location.hostname;
    const reverbPort = Number(meta('restaurant-reverb-port') || '8080');
    const reverbScheme = (meta('restaurant-reverb-scheme') || 'http').toLowerCase();
    const pusherKey = meta('restaurant-pusher-key');
    const pusherCluster = meta('restaurant-pusher-cluster') || 'mt1';
    const floorStateUrl = meta('restaurant-floor-state-url');

    let speechUnlocked = window.sessionStorage.getItem(SPEECH_TAB_KEY) === '1';
    const preUnlockSpeechBuffer = [];

    function unlockSpeechFromGesture() {
        if (speechUnlocked) {
            return;
        }
        speechUnlocked = true;
        try {
            window.sessionStorage.setItem(SPEECH_TAB_KEY, '1');
        } catch {
            /* ignore */
        }
        try {
            window.speechSynthesis?.resume();
        } catch {
            /* ignore */
        }
        while (preUnlockSpeechBuffer.length > 0) {
            const { text, immediate } = preUnlockSpeechBuffer.shift();
            if (immediate) {
                speakImmediate(text);
            } else {
                enqueueSpeech(text);
            }
        }
        reconcilePendingLoopTimer();
    }

    ['click', 'touchstart', 'keydown', 'pointerdown'].forEach((ev) => {
        document.addEventListener(ev, () => unlockSpeechFromGesture(), { passive: true, capture: true });
    });

    const speechQueue = [];
    let speechSpeaking = false;

    /** @type {Map<number, { label: string, pendingKitchen: number, pendingNonKitchen: number, lastKitchenPending: number, lastNonKitchenPending: number }>} */
    const pendingAnnounceTables = new Map();
    let pendingVoiceRoundRobin = 0;
    let pendingLoopTimerId = null;

    const playedCheckoutIds = new Set();

    /** After first floor snapshot, newly appearing pending tables get a voice alert (avoids blasting every pending row on first page load). */
    let pendingFloorMergePrimed = false;

    function applyUtteranceParams(utterance) {
        utterance.lang = 'en-US';
        utterance.rate = 1;
        utterance.volume = 1;
        try {
            const voices = window.speechSynthesis?.getVoices?.() ?? [];
            const en =
                voices.find((v) => v.lang?.toLowerCase().startsWith('en')) ??
                voices.find((v) => v.lang?.toLowerCase().includes('en'));
            if (en) {
                utterance.voice = en;
            }
        } catch {
            /* default */
        }
    }

    function drainSpeechQueue() {
        if (!window.speechSynthesis || speechSpeaking || speechQueue.length === 0) {
            return;
        }
        if (!speechUnlocked) {
            return;
        }
        try {
            window.speechSynthesis.resume();
        } catch {
            /* ignore */
        }
        speechSpeaking = true;
        const text = speechQueue.shift();
        const u = new SpeechSynthesisUtterance(text);
        applyUtteranceParams(u);
        u.onend = () => {
            speechSpeaking = false;
            drainSpeechQueue();
        };
        u.onerror = () => {
            speechSpeaking = false;
            drainSpeechQueue();
        };
        window.speechSynthesis.speak(u);
    }

    function enqueueSpeech(text) {
        if (!window.speechSynthesis) {
            return;
        }
        if (!speechUnlocked) {
            preUnlockSpeechBuffer.push({ text, immediate: false });
            return;
        }
        speechQueue.push(text);
        drainSpeechQueue();
    }

    /** Urgent: cancel queue and speak now (new order). */
    function speakImmediate(text) {
        if (!window.speechSynthesis) {
            return;
        }
        if (!speechUnlocked) {
            preUnlockSpeechBuffer.push({ text, immediate: true });
            return;
        }
        try {
            if (window.speechSynthesis.speaking || window.speechSynthesis.pending) {
                window.speechSynthesis.cancel();
            }
        } catch {
            /* ignore */
        }
        speechQueue.length = 0;
        speechSpeaking = false;
        try {
            window.speechSynthesis.resume();
        } catch {
            /* ignore */
        }
        const u = new SpeechSynthesisUtterance(text);
        applyUtteranceParams(u);
        speechSpeaking = true;
        u.onend = () => {
            speechSpeaking = false;
            drainSpeechQueue();
        };
        u.onerror = () => {
            speechSpeaking = false;
            drainSpeechQueue();
        };
        window.speechSynthesis.speak(u);
    }

    function stopSpeechAndClearQueue() {
        try {
            window.speechSynthesis?.cancel();
        } catch {
            /* ignore */
        }
        speechQueue.length = 0;
        speechSpeaking = false;
    }

    function dispatchFloorRefresh() {
        window.dispatchEvent(new CustomEvent('restaurant:refresh-floor', { bubbles: true }));
    }

    function setWsStatusBadge(mode) {
        const el = document.getElementById('df-ws-status');
        if (!el) {
            return;
        }
        const base =
            'rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide';
        if (mode === 'live') {
            el.textContent = 'Live';
            el.className = `${base} border-emerald-800/60 bg-emerald-950/40 text-emerald-300`;
        } else if (mode === 'connecting') {
            el.textContent = '…';
            el.className = `${base} border-orange-900/60 bg-black/30 text-orange-400`;
        } else {
            el.textContent = 'Poll';
            el.className = `${base} border-orange-900/60 bg-black/30 text-orange-500`;
        }
    }

    function reconcilePendingLoopTimer() {
        if (pendingLoopTimerId != null) {
            window.clearInterval(pendingLoopTimerId);
            pendingLoopTimerId = null;
        }
        if (pendingAnnounceTables.size === 0 || !speechUnlocked) {
            return;
        }
        pendingLoopTimerId = window.setInterval(() => {
            if (!speechUnlocked) {
                return;
            }
            const ids = Array.from(pendingAnnounceTables.keys()).sort((a, b) => a - b);
            if (ids.length === 0) {
                return;
            }
            const idx = pendingVoiceRoundRobin % ids.length;
            pendingVoiceRoundRobin++;
            const tid = ids[idx];
            const entry = pendingAnnounceTables.get(tid);
            if (!entry?.label) {
                return;
            }
            const tableSeen = seenState[String(tid)] ?? {};
            const needsKitchen = entry.pendingKitchen > 0 && tableSeen.kitchen !== true;
            const needsNonKitchen = entry.pendingNonKitchen > 0 && tableSeen.non_kitchen !== true;
            if (!needsKitchen && !needsNonKitchen) {
                return;
            }
            enqueueSpeech(`${entry.label} has placed an order.`);
        }, PENDING_REPEAT_MS);
    }

    function syncPendingTablesFromPayload(tables) {
        if (!Array.isArray(tables)) {
            return;
        }
        const prevKeys = new Set(pendingAnnounceTables.keys());
        const serverPendingIds = new Set();
        for (const t of tables) {
            const isPending = t.visual === 'pending' || (t.counts?.pending ?? 0) > 0;
            if (!isPending) {
                continue;
            }
            const id = Number(t.id);
            serverPendingIds.add(id);
            const tblabel = tableDisplayLabel(t.table_name, t.table_number);
            const pendingKitchen = Number(t.counts?.pending_kitchen ?? 0);
            const pendingNonKitchen = Number(t.counts?.pending_non_kitchen ?? 0);
            const prev = pendingAnnounceTables.get(id);
            const tableSeen = seenState[String(id)] ?? {};
            if (pendingKitchen > (prev?.lastKitchenPending ?? 0)) {
                tableSeen.kitchen = false;
            }
            if (pendingNonKitchen > (prev?.lastNonKitchenPending ?? 0)) {
                tableSeen.non_kitchen = false;
            }
            seenState[String(id)] = tableSeen;
            pendingAnnounceTables.set(id, {
                label: tblabel,
                pendingKitchen,
                pendingNonKitchen,
                lastKitchenPending: pendingKitchen,
                lastNonKitchenPending: pendingNonKitchen,
            });
            if (!prevKeys.has(id) && pendingFloorMergePrimed) {
                speakImmediate(`${tblabel} has placed an order.`);
            }
        }
        pendingFloorMergePrimed = true;
        pendingAnnounceTables.forEach((_, id) => {
            if (!serverPendingIds.has(id)) {
                pendingAnnounceTables.delete(id);
            }
        });
        persistSeenState();
        reconcilePendingLoopTimer();
    }

    async function syncPendingFromApi() {
        if (!floorStateUrl) {
            return;
        }
        const data = await fetchFloorTables(floorStateUrl);
        if (data?.tables) {
            syncPendingTablesFromPayload(data.tables);
        }
    }

    function bindEchoConnectionStatus() {
        try {
            const conn = window.Echo?.connector?.pusher?.connection;
            if (!conn?.bind) {
                return;
            }
            conn.bind('connected', () => setWsStatusBadge('live'));
            conn.bind('disconnected', () => setWsStatusBadge('poll'));
            conn.bind('unavailable', () => setWsStatusBadge('poll'));
            conn.bind('failed', () => setWsStatusBadge('poll'));
        } catch {
            /* ignore */
        }
    }

    function onOrderPlaced(payload) {
        const tid = Number(payload?.table_id);
        const tblabel = labelFromPayload(payload);
        if (Number.isFinite(tid)) {
            pendingAnnounceTables.set(tid, {
                label: tblabel,
                pendingKitchen: 1,
                pendingNonKitchen: 1,
                lastKitchenPending: 1,
                lastNonKitchenPending: 1,
            });
            reconcilePendingLoopTimer();
        }
        speakImmediate(`${tblabel} has placed an order.`);
        dispatchFloorRefresh();
    }

    function onOrderPreparing(payload) {
        const tid = Number(payload?.table_id);
        if (Number.isFinite(tid)) {
            pendingAnnounceTables.delete(tid);
        }
        reconcilePendingLoopTimer();
        stopSpeechAndClearQueue();
        const tblabel = labelFromPayload(payload);
        speakImmediate(`Preparing order for ${tblabel}.`);
        dispatchFloorRefresh();
    }

    function onOrderCompleted(payload) {
        const tblabel = labelFromPayload(payload);
        enqueueSpeech(`Order completed for ${tblabel}.`);
        dispatchFloorRefresh();
    }

    function onCheckoutCompleted(payload) {
        const oid = payload?.order_id;
        const tblabel = labelFromPayload(payload);
        if (oid != null && !playedCheckoutIds.has(oid)) {
            playedCheckoutIds.add(oid);
            enqueueSpeech(`Checkout completed for ${tblabel}.`);
        }
        dispatchFloorRefresh();
    }

    /** Guest/staff flow: customer taps “Request checkout” — must announce table number (was refresh-only). */
    function onCheckoutRequested(payload) {
        const text =
            typeof payload?.announcement_text === 'string' && payload.announcement_text.trim() !== ''
                ? payload.announcement_text.trim()
                : (() => {
                      const n = payload?.order?.table?.table_number;
                      return n != null && n !== ''
                          ? `Table number ${n} has requested checkout`
                          : 'A guest has requested checkout.';
                  })();
        speakImmediate(text);
        dispatchFloorRefresh();
    }

    function onOrderUpdated(payload) {
        if (payload?.voice_line) {
            speakImmediate(payload.voice_line);
        }
        dispatchFloorRefresh();
        window.dispatchEvent(
            new CustomEvent('restaurant:order-updated', { bubbles: true, detail: payload ?? {} }),
        );
    }

    window.addEventListener('storage', () => {
        try {
            seenState = JSON.parse(window.sessionStorage.getItem(ORDER_SEEN_KEY) ?? '{}') ?? {};
        } catch {
            /* ignore */
        }
    });

    window.addEventListener('restaurant:order-side-seen', (e) => {
        const tableId = Number(e.detail?.tableId);
        const side = e.detail?.side;
        if (!Number.isFinite(tableId) || (side !== 'kitchen' && side !== 'non_kitchen')) {
            return;
        }
        seenState[String(tableId)] = seenState[String(tableId)] ?? {};
        seenState[String(tableId)][side] = true;
        persistSeenState();
        reconcilePendingLoopTimer();
    });

    if (broadcastDriver === 'reverb' && reverbKey) {
        setWsStatusBadge('connecting');
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
        bindEchoConnectionStatus();
        const dash = window.Echo.channel('dashboard');
        dash.listen('.OrderPlaced', onOrderPlaced);
        dash.listen('.OrderPreparing', onOrderPreparing);
        dash.listen('.OrderCompleted', onOrderCompleted);
        dash.listen('.CheckoutCompleted', onCheckoutCompleted);
        dash.listen('.OrderUpdated', onOrderUpdated);
        dash.listen('.CheckoutRequested', onCheckoutRequested);
        dash.listen('.GuestSessionStarted', dispatchFloorRefresh);
    } else if (broadcastDriver === 'pusher' && pusherKey) {
        setWsStatusBadge('connecting');
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: pusherKey,
            cluster: pusherCluster,
            forceTLS: true,
        });
        bindEchoConnectionStatus();
        const dash = window.Echo.channel('dashboard');
        dash.listen('.OrderPlaced', onOrderPlaced);
        dash.listen('.OrderPreparing', onOrderPreparing);
        dash.listen('.OrderCompleted', onOrderCompleted);
        dash.listen('.CheckoutCompleted', onCheckoutCompleted);
        dash.listen('.OrderUpdated', onOrderUpdated);
        dash.listen('.CheckoutRequested', onCheckoutRequested);
        dash.listen('.GuestSessionStarted', dispatchFloorRefresh);
    } else {
        setWsStatusBadge('poll');
    }

    if (window.speechSynthesis) {
        window.speechSynthesis.addEventListener('voiceschanged', () => {});
    }

    void syncPendingFromApi();
    window.setInterval(() => {
        void syncPendingFromApi();
    }, 12000);
});
    let seenState = {};
    try {
        seenState = JSON.parse(window.sessionStorage.getItem(ORDER_SEEN_KEY) ?? '{}') ?? {};
    } catch {
        seenState = {};
    }

    function persistSeenState() {
        try {
            window.sessionStorage.setItem(ORDER_SEEN_KEY, JSON.stringify(seenState));
        } catch {
            /* ignore */
        }
    }
