/**
 * Reusable 2s dashboard order polling: fetch latest orders, dedupe by ID, toast + optional sound, UI refresh hook.
 * No WebSockets — session cookie + JSON only.
 */

const DEFAULT_POLL_MS = 2000;
const STORAGE_AFTER_KEY = 'restaurant-dash-after-order-id';
const PROCESSED_TOAST_KEY = 'restaurant-dash-toast-order-ids';
const MAX_STORED_IDS = 80;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function tableLabel(tableName, tableNumber) {
    const n = (tableName ?? '').trim();
    if (n) {
        return n;
    }
    return `Table ${tableNumber ?? ''}`;
}

function readJsonSession(key, fallback) {
    try {
        return JSON.parse(window.sessionStorage.getItem(key) ?? '') ?? fallback;
    } catch {
        return fallback;
    }
}

function writeJsonSession(key, value) {
    try {
        window.sessionStorage.setItem(key, JSON.stringify(value));
    } catch {
        /* ignore */
    }
}

function playFallbackChime() {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) {
        return;
    }
    try {
        const ctx = new Ctx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.0001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.12, ctx.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.22);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.25);
        window.setTimeout(() => {
            try {
                ctx.close();
            } catch {
                /* ignore */
            }
        }, 400);
    } catch {
        /* ignore */
    }
}

/**
 * @param {{ pollUrl: string, pollMs?: number, soundUrl?: string, toastHost?: HTMLElement | null, onRefresh?: () => void }} options
 */
export function createDashboardOrderPoller(options) {
    const pollMs = options.pollMs ?? DEFAULT_POLL_MS;
    const soundUrl = options.soundUrl ?? '/sounds/order.mp3';
    let intervalId = null;
    let inFlight = false;
    let bootstrapped = false;
    let audioUnlocked = false;
    const toastHost =
        options.toastHost ??
        (() => {
            let el = document.getElementById('df-toast-host');
            if (!el) {
                el = document.createElement('div');
                el.id = 'df-toast-host';
                el.className =
                    'pointer-events-none fixed right-3 top-3 z-[100] flex max-w-[min(22rem,calc(100vw-1.5rem))] flex-col items-end gap-2 sm:right-4 sm:top-4';
                document.body.appendChild(el);
            }
            return el;
        })();

    function persistAfterId(id) {
        try {
            window.sessionStorage.setItem(STORAGE_AFTER_KEY, String(Math.max(0, id)));
        } catch {
            /* ignore */
        }
    }

    function getAfterId() {
        const v = Number(window.sessionStorage.getItem(STORAGE_AFTER_KEY) ?? '0');
        return Number.isFinite(v) && v > 0 ? v : 0;
    }

    function getToastSeenSet() {
        const arr = readJsonSession(PROCESSED_TOAST_KEY, []);
        return new Set(Array.isArray(arr) ? arr.map(Number).filter((n) => n > 0) : []);
    }

    function rememberToastIds(ids) {
        const set = getToastSeenSet();
        ids.forEach((id) => set.add(id));
        const list = [...set].sort((a, b) => a - b).slice(-MAX_STORED_IDS);
        writeJsonSession(PROCESSED_TOAST_KEY, list);
    }

    function unlockAudioFromGesture() {
        if (audioUnlocked) {
            return;
        }
        audioUnlocked = true;
    }

    ['click', 'touchstart', 'keydown'].forEach((ev) => {
        document.addEventListener(ev, () => unlockAudioFromGesture(), { passive: true, capture: true });
    });

    function playOrderSoundOnce() {
        if (!audioUnlocked) {
            return;
        }
        const audio = new Audio(soundUrl);
        audio.preload = 'auto';
        audio.volume = 0.85;
        const p = audio.play();
        if (p !== undefined) {
            p.catch(() => playFallbackChime());
        }
        audio.addEventListener(
            'error',
            () => {
                playFallbackChime();
            },
            { once: true },
        );
    }

    function showToast(message, variant = 'order') {
        const wrap = document.createElement('div');
        wrap.className =
            'pointer-events-auto origin-top-right animate-[dfToastIn_0.35s_ease-out_both] rounded-xl border shadow-2xl backdrop-blur-md';
        wrap.setAttribute('role', 'status');
        wrap.setAttribute('aria-live', 'polite');
        const border =
            variant === 'order'
                ? 'border-amber-500/50 bg-gradient-to-br from-amber-950/95 to-stone-950/95'
                : 'border-slate-600/50 bg-slate-950/95';
        wrap.className += ` ${border} max-w-full px-4 py-3 text-sm text-amber-50`;

        const title = document.createElement('p');
        title.className = 'text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-400/90';
        title.textContent = 'New order';

        const body = document.createElement('p');
        body.className = 'mt-1 text-[15px] font-semibold leading-snug text-orange-50';
        body.textContent = message;

        wrap.appendChild(title);
        wrap.appendChild(body);
        toastHost.appendChild(wrap);

        window.setTimeout(() => {
            wrap.classList.add('animate-[dfToastOut_0.3s_ease-in_forwards]');
            window.setTimeout(() => wrap.remove(), 320);
        }, 5200);
    }

    function pulseLiveBadge() {
        const el = document.getElementById('df-live-count');
        if (!el) {
            return;
        }
        el.classList.add('ring-2', 'ring-amber-400/80', 'animate-pulse');
        window.setTimeout(() => {
            el.classList.remove('ring-2', 'ring-amber-400/80', 'animate-pulse');
        }, 2200);
    }

    async function tick() {
        if (inFlight) {
            return;
        }
        inFlight = true;
        try {
            const afterId = bootstrapped ? getAfterId() : 0;
            const url = new URL(options.pollUrl, window.location.origin);
            if (afterId > 0) {
                url.searchParams.set('after_order_id', String(afterId));
            }

            const res = await fetch(url.toString(), {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });
            if (!res.ok) {
                return;
            }
            const data = await res.json().catch(() => null);
            if (!data || !Number.isFinite(Number(data.latest_order_id))) {
                return;
            }

            const latestId = Math.max(0, Math.floor(Number(data.latest_order_id)));
            const newOrders = Array.isArray(data.new_orders) ? data.new_orders : [];

            if (!bootstrapped) {
                bootstrapped = true;
                persistAfterId(latestId);
                return;
            }

            const toastSeen = getToastSeenSet();
            const fresh = newOrders.filter((o) => o && !toastSeen.has(Number(o.id)));
            if (fresh.length === 0) {
                persistAfterId(Math.max(getAfterId(), latestId));
                return;
            }

            let playedSound = false;
            fresh.forEach((o) => {
                const id = Number(o.id);
                const label = tableLabel(o.table_name, o.table_number);
                const msg = `${label} has placed an order`;
                showToast(msg, 'order');
                if (!playedSound) {
                    playOrderSoundOnce();
                    playedSound = true;
                }
            });

            rememberToastIds(fresh.map((o) => Number(o.id)));
            persistAfterId(latestId);

            pulseLiveBadge();
            options.onRefresh?.();
        } finally {
            inFlight = false;
        }
    }

    function start() {
        if (intervalId != null) {
            return;
        }
        void tick();
        intervalId = window.setInterval(() => void tick(), pollMs);
    }

    function stop() {
        if (intervalId != null) {
            window.clearInterval(intervalId);
            intervalId = null;
        }
    }

    return { start, stop, tickOnce: tick };
}
