import Konva from 'konva';

const GRID = 16;
const WORLD_W = 3200;
const WORLD_H = 2400;

const VISUAL_BASE = {
    pending: null,
    preparing: '#eab308',
    completed_blue: '#3b82f6',
    occupied_red: '#dc2626',
    reserved: '#ca8a04',
    available: '#22c55e',
};

const KITCHEN_MODE_KEY = 'restaurant-dashboard-kitchen-mode';
/** Debounce for batched canvas refreshes (ms). Critical order events bypass this. */
const REFRESH_DEBOUNCE_MS = 40;
const SESSION_HISTORY_PAGE_SIZE = 5;
const ORDER_SEEN_KEY = 'restaurant-dashboard-order-seen-v1';

function tableDisplayLabel(tableName, tableNumber) {
    const n = (tableName ?? '').trim();
    if (n) {
        return n;
    }
    return `Table ${tableNumber ?? ''}`;
}

function tableCountsLine(model) {
    const c = model?.counts ?? {};
    const p = Number(c.pending ?? 0);
    const pr = Number(c.preparing ?? 0);
    const r = Number(c.ready ?? 0);
    const d = Number(c.delivered ?? 0);
    if (p + pr + r + d === 0) {
        return '';
    }
    return `\nP:${p} PR:${pr} R:${r} D:${d}`;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function fetchJson(url, options = {}) {
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
        ...(options.headers ?? {}),
    };
    const response = await fetch(url, { credentials: 'same-origin', ...options, headers });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const message = data.message ?? data.error ?? response.statusText ?? 'Request failed';
        throw new Error(typeof message === 'string' ? message : JSON.stringify(message));
    }
    return data;
}

function createGridPattern() {
    const canvas = document.createElement('canvas');
    const size = GRID * 4;
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        return null;
    }
    ctx.fillStyle = '#0c0a09';
    ctx.fillRect(0, 0, size, size);
    ctx.strokeStyle = '#292524';
    ctx.lineWidth = 1;
    for (let i = 0; i <= size; i += GRID) {
        ctx.beginPath();
        ctx.moveTo(i, 0);
        ctx.lineTo(i, size);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(0, i);
        ctx.lineTo(size, i);
        ctx.stroke();
    }
    return canvas;
}

function buildTableGroup(model) {
    const shapeKind = model.shape === 'round' ? 'round' : 'square';
    let baseW = Number(model.width);
    let baseH = Number(model.height);
    if (shapeKind === 'round') {
        const s = Math.max(Math.min(baseW, baseH, 560), 48);
        baseW = s;
        baseH = s;
    }

    const group = new Konva.Group({
        x: Number(model.x_position),
        y: Number(model.y_position),
        rotation: Number(model.rotation ?? 0),
        draggable: false,
        listening: true,
        name: 'table',
    });

    group.setAttr('tableId', model.id);
    group.setAttr('shapeKind', shapeKind);
    group.setAttr('visual', model.visual ?? 'available');
    group.setAttr('searchText', `${model.table_name ?? ''} ${model.table_number ?? ''}`.toLowerCase());

    const strokeColor = '#1c1917';

    let shapeNode;
    if (shapeKind === 'round') {
        const r = Math.min(baseW, baseH) / 2;
        shapeNode = new Konva.Circle({
            name: 'shape-body',
            radius: r,
            fill: '#22c55e',
            stroke: strokeColor,
            strokeWidth: 2,
        });
    } else {
        shapeNode = new Konva.Rect({
            name: 'shape-body',
            x: 0,
            y: 0,
            width: baseW,
            height: baseH,
            offsetX: baseW / 2,
            offsetY: baseH / 2,
            cornerRadius: 10,
            fill: '#22c55e',
            stroke: strokeColor,
            strokeWidth: 2,
        });
    }

    const baseLabel = model.table_name ?? `Table ${model.table_number ?? ''}`;
    const labelText =
        model.guest_party_size != null && model.guest_party_size !== ''
            ? `${baseLabel}\n👥 ${model.guest_party_size}${tableCountsLine(model)}`
            : `${baseLabel}${tableCountsLine(model)}`;
    const label = new Konva.Text({
        name: 'table-label',
        text: labelText,
        fontSize: shapeKind === 'round' ? 13 : 12,
        lineHeight: 1.12,
        fontFamily: 'ui-sans-serif, system-ui, sans-serif',
        fill: '#fffbeb',
        align: 'center',
        verticalAlign: 'middle',
        width: baseW,
        height: baseH,
        offsetX: baseW / 2,
        offsetY: baseH / 2,
        listening: false,
    });

    group.add(shapeNode);
    group.add(label);

    return group;
}

function applyVisualFill(group, visual, shapeBody) {
    if (visual === 'pending') {
        return;
    }
    const fill = VISUAL_BASE[visual] ?? VISUAL_BASE.available;
    shapeBody.fill(fill);
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('dashboard-floor-root');
    const container = document.getElementById('df-konva-container');
    if (!root || !container) {
        return;
    }

    const urlFloorState = root.dataset.urlFloorState;
    const urlPanelTemplate = root.dataset.urlPanelTemplate ?? '';
    const urlOrderStatusTemplate = root.dataset.urlOrderStatusTemplate ?? '';
    const urlOrdersBase = (root.dataset.urlOrdersBase ?? '').replace(/\/$/, '');
    const urlItemPreparingTemplate = root.dataset.urlItemPreparingTemplate ?? '';
    const urlItemReadyTemplate = root.dataset.urlItemReadyTemplate ?? '';
    const urlItemDeliverTemplate = root.dataset.urlItemDeliverTemplate ?? '';
    const urlDeliverAllReadyTemplate = root.dataset.urlDeliverAllReadyTemplate ?? '';

    const drawer = document.getElementById('df-drawer');
    const drawerBackdrop = document.getElementById('df-drawer-backdrop');
    const drawerClose = document.getElementById('df-drawer-close');
    const drawerEmpty = document.getElementById('df-drawer-empty');
    const drawerBody = document.getElementById('df-drawer-body');
    const drawerTitle = document.getElementById('df-drawer-title');
    const drawerMeta = document.getElementById('df-drawer-meta');
    const activeOrdersEl = document.getElementById('df-active-orders');
    const statusActionsEl = document.getElementById('df-status-actions');
    const sessionHistoryEl = document.getElementById('df-session-history');
    const sessionActionsEl = document.getElementById('df-session-actions');
    const mobileSessionBar = document.getElementById('df-mobile-session-bar');
    const liveCountEl = document.getElementById('df-live-count');
    const searchInput = document.getElementById('df-search');
    const checkoutModal = document.getElementById('df-checkout-modal');
    const checkoutSummary = document.getElementById('df-checkout-summary');
    const checkoutCancel = document.getElementById('df-checkout-cancel');
    const checkoutConfirm = document.getElementById('df-checkout-confirm');
    let orderSeenState = {};
    try {
        orderSeenState = JSON.parse(window.sessionStorage.getItem(ORDER_SEEN_KEY) ?? '{}') ?? {};
    } catch {
        orderSeenState = {};
    }

    function persistOrderSeenState() {
        try {
            window.sessionStorage.setItem(ORDER_SEEN_KEY, JSON.stringify(orderSeenState));
        } catch {
            /* ignore */
        }
    }

    function markTableSideSeen(tableId, side) {
        const key = String(tableId);
        orderSeenState[key] = orderSeenState[key] ?? {};
        orderSeenState[key][side] = true;
        persistOrderSeenState();
        window.dispatchEvent(new CustomEvent('restaurant:order-side-seen', { detail: { tableId: Number(tableId), side } }));
    }

    /** Menu catalog from last table panel load (for add-item dropdown). */
    let panelMenuCatalog = null;

    let stage;
    let layer;
    let world;
    let tablesLayer;
    const tableNodes = new Map();
    const blinkAnims = new Map();
    let selectedId = null;
    let worldScale = 1;
    let isPanning = false;
    let lastPointer = null;

    function setToolbarHeight() {
        const header = root.querySelector('header');
        if (header) {
            document.documentElement.style.setProperty('--df-toolbar-h', `${header.offsetHeight}px`);
        }
    }

    function clearTableGlow(shape) {
        if (!shape) {
            return;
        }
        shape.shadowEnabled(false);
        shape.shadowBlur(0);
        shape.shadowOpacity(0);
    }

    function stopBlink(tableId) {
        const anim = blinkAnims.get(tableId);
        if (anim) {
            anim.stop();
            blinkAnims.delete(tableId);
        }
        const group = tableNodes.get(tableId);
        const shape = group?.findOne('.shape-body');
        clearTableGlow(shape);
    }

    function startBlink(shape, tableId, konvaLayer) {
        stopBlink(tableId);
        shape.shadowEnabled(true);
        shape.shadowColor('rgba(249, 115, 22, 0.95)');
        shape.shadowOffsetX(0);
        shape.shadowOffsetY(0);
        let t = 0;
        const anim = new Konva.Animation((frame) => {
            t += (frame.timeDiff ?? 16) / 480;
            const mix = (Math.sin(t) + 1) / 2;
            const c1 = [249, 115, 22];
            const c2 = [239, 68, 68];
            const rgb = c1.map((v, i) => Math.round(v + (c2[i] - v) * mix));
            shape.fill(`rgb(${rgb.join(',')})`);
            const pulse = 0.88 + 0.12 * Math.sin(t * 1.15);
            shape.opacity(pulse);
            const glow = 12 + 28 * mix;
            shape.shadowBlur(glow);
            shape.shadowOpacity(0.48 + 0.48 * mix);
        }, konvaLayer);
        anim.start();
        blinkAnims.set(tableId, anim);
    }

    function updateTableVisual(group, visual) {
        const shape = group.findOne('.shape-body');
        if (!shape) {
            return;
        }
        const tid = group.getAttr('tableId');
        stopBlink(tid);
        group.setAttr('visual', visual);
        shape.opacity(1);
        if (visual === 'pending') {
            startBlink(shape, tid, layer);
        } else {
            clearTableGlow(shape);
            applyVisualFill(group, visual, shape);
        }

        if (selectedId != null && Number(selectedId) === Number(tid)) {
            shape.stroke('#fbbf24');
            shape.strokeWidth(5);
        } else if (visual === 'pending') {
            shape.stroke('#fb923c');
            shape.strokeWidth(5);
        } else {
            shape.stroke('#1c1917');
            shape.strokeWidth(2);
        }
        layer.batchDraw();
    }

    function rebuildTables(tables) {
        blinkAnims.forEach((a) => a.stop());
        blinkAnims.clear();
        tablesLayer.destroyChildren();
        tableNodes.clear();

        tables.forEach((t) => {
            const g = buildTableGroup(t);
            tablesLayer.add(g);
            tableNodes.set(t.id, g);
            updateTableVisual(g, t.visual ?? 'available');
        });
        layer.batchDraw();
    }

    function mergeTableVisuals(tables) {
        tables.forEach((t) => {
            const g = tableNodes.get(t.id);
            if (g) {
                updateTableVisual(g, t.visual ?? 'available');
                g.setAttr('searchText', `${t.table_name ?? ''} ${t.table_number ?? ''}`.toLowerCase());
                const lbl = g.findOne('.table-label');
                if (lbl) {
                    const base = t.table_name ?? `Table ${t.table_number ?? ''}`;
                    const guest = t.guest_party_size;
                    lbl.text(
                        guest != null && guest !== '' ? `${base}\n👥 ${guest}${tableCountsLine(t)}` : `${base}${tableCountsLine(t)}`,
                    );
                }
            }
        });
    }

    let refreshTimer = null;
    let floorStateRequest = null;
    let floorStateQueued = false;
    let queuedFullRebuild = false;
    /** @param {boolean} [immediate] If true, refresh canvas now (no debounce). Use for order alerts. */
    const scheduleRefresh = (immediate = false) => {
        if (refreshTimer) {
            window.clearTimeout(refreshTimer);
            refreshTimer = null;
        }
        const run = () => {
            loadFloorState(false).catch(() => {});
        };
        if (immediate) {
            run();
            return;
        }
        refreshTimer = window.setTimeout(() => {
            refreshTimer = null;
            run();
        }, REFRESH_DEBOUNCE_MS);
    };

    async function loadFloorState(fullRebuild) {
        if (floorStateRequest) {
            floorStateQueued = true;
            queuedFullRebuild = queuedFullRebuild || fullRebuild;
            return floorStateRequest;
        }

        floorStateRequest = (async () => {
            const data = await fetchJson(urlFloorState);
        if (liveCountEl) {
            liveCountEl.textContent = `${data.live_order_count ?? 0} live`;
        }
        const tables = data.tables ?? [];
        if (fullRebuild || tableNodes.size === 0) {
            rebuildTables(tables);
        } else {
            const ids = new Set(tables.map((t) => t.id));
            tableNodes.forEach((node, id) => {
                if (!ids.has(id)) {
                    stopBlink(id);
                    node.destroy();
                    tableNodes.delete(id);
                }
            });
            tables.forEach((t) => {
                if (!tableNodes.has(t.id)) {
                    const g = buildTableGroup(t);
                    tablesLayer.add(g);
                    tableNodes.set(t.id, g);
                }
                updateTableVisual(tableNodes.get(t.id), t.visual ?? 'available');
            });
            mergeTableVisuals(tables);
        }

        applySearchFilter();
        layer.batchDraw();
        })();

        try {
            await floorStateRequest;
        } finally {
            floorStateRequest = null;
            if (floorStateQueued) {
                const nextFullRebuild = queuedFullRebuild;
                floorStateQueued = false;
                queuedFullRebuild = false;
                loadFloorState(nextFullRebuild).catch(() => {});
            }
        }
    }

    function panelUrl(id, historyPage = sessionHistoryPage) {
        const url = new URL(urlPanelTemplate.replace('__ID__', String(id)), window.location.origin);
        url.searchParams.set('history_page', String(Math.max(1, Number(historyPage) || 1)));
        return url.toString();
    }

    function orderStatusUrl(orderId) {
        return urlOrderStatusTemplate.replace('__ORDER__', String(orderId));
    }

    function itemActionUrl(template, orderId, itemId) {
        return template
            .replace('__ORDER__', String(orderId))
            .replace('__ITEM__', String(itemId));
    }

    function deliverAllReadyUrl(orderId) {
        return urlDeliverAllReadyTemplate.replace('__ORDER__', String(orderId));
    }

    function openDrawer() {
        drawer?.classList.remove('translate-x-full');
        drawer?.setAttribute('aria-hidden', 'false');
        drawerBackdrop?.classList.remove('opacity-0', 'pointer-events-none');
        drawerBackdrop?.classList.add('opacity-100', 'pointer-events-auto');
    }

    function closeDrawer() {
        selectedId = null;
        drawer?.classList.add('translate-x-full');
        drawer?.setAttribute('aria-hidden', 'true');
        drawerBackdrop?.classList.add('opacity-0', 'pointer-events-none');
        drawerBackdrop?.classList.remove('opacity-100', 'pointer-events-auto');
        tableNodes.forEach((node, tid) => {
            const body = node.findOne('.shape-body');
            if (!body) {
                return;
            }
            const visual = node.getAttr('visual') ?? 'available';
            if (visual === 'pending') {
                body.stroke('#fb923c');
                body.strokeWidth(5);
            } else {
                body.stroke('#1c1917');
                body.strokeWidth(2);
            }
        });
        layer.batchDraw();
        if (drawerEmpty && drawerBody) {
            drawerEmpty.classList.remove('hidden');
            drawerBody.classList.add('hidden');
        }
    }

    function renderLineItem(order, it, editable, options = {}) {
        const historyMode = options.history === true;
        const prep = String(it.preparation_status ?? 'pending');
        const statusMap = {
            pending: { label: 'Pending', cls: 'bg-red-950/40 text-red-200 border-red-800/50' },
            preparing: { label: 'Preparing', cls: 'bg-orange-950/40 text-orange-200 border-orange-800/50' },
            ready: { label: 'Ready', cls: 'bg-blue-950/40 text-blue-200 border-blue-800/50' },
            delivered: { label: 'Delivered', cls: 'bg-emerald-950/40 text-emerald-200 border-emerald-800/50' },
        };
        const statusMeta = statusMap[prep] ?? statusMap.pending;
        const remaining = Number(it.remaining_quantity ?? Math.max(0, Number(it.quantity ?? 0) - Number(it.delivered_quantity ?? 0)));
        const deliveredQty = Number(it.delivered_quantity ?? 0);
        const canDeliver = remaining > 0;
        const prepActions = historyMode ? '' : `
            <div class="mt-2 flex flex-wrap gap-1.5">
                <button type="button" data-df-item-action="mark-preparing" data-order-id="${order.id}" data-item-id="${it.id}" class="rounded-md border border-orange-900/60 px-2 py-1 text-[10px] font-semibold text-orange-200 hover:bg-orange-950/50">Mark Preparing</button>
                <button type="button" data-df-item-action="mark-ready" data-order-id="${order.id}" data-item-id="${it.id}" class="rounded-md border border-blue-900/60 px-2 py-1 text-[10px] font-semibold text-blue-200 hover:bg-blue-950/50">Mark Ready</button>
                <button type="button" data-df-item-action="deliver" data-order-id="${order.id}" data-item-id="${it.id}" data-remaining="${remaining}" ${canDeliver ? '' : 'disabled'} class="rounded-md border border-emerald-900/60 bg-emerald-950/20 px-2 py-1 text-[10px] font-semibold text-emerald-200 hover:bg-emerald-950/50 disabled:cursor-not-allowed disabled:opacity-40">Deliver Item</button>
            </div>`;
        const ctrls = editable
            ? `<div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" data-df-pos-action="dec" data-order-id="${order.id}" data-item-id="${it.id}" class="min-h-[44px] min-w-[44px] shrink-0 rounded-xl border border-orange-700 bg-orange-950/60 text-xl font-bold leading-none text-orange-50 hover:bg-orange-900">−</button>
                    <span class="min-w-[2.25rem] text-center text-base font-semibold tabular-nums text-orange-50">${it.quantity}</span>
                    <button type="button" data-df-pos-action="inc" data-order-id="${order.id}" data-item-id="${it.id}" class="min-h-[44px] min-w-[44px] shrink-0 rounded-xl border border-orange-700 bg-orange-950/60 text-xl font-bold leading-none text-orange-50 hover:bg-orange-900">+</button>
                    <button type="button" data-df-pos-action="remove" data-order-id="${order.id}" data-item-id="${it.id}" class="ml-auto rounded-xl border border-red-900/60 bg-red-950/40 px-3 py-2 text-xs font-semibold text-red-200">Remove</button>
                </div>`
            : `<p class="mt-2 text-sm text-orange-600">Qty <span class="font-semibold text-orange-200">${it.quantity}</span></p>`;

        return `
            <div class="rounded-lg border border-orange-950/40 bg-black/25 p-3" data-df-line="${it.id}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-base font-semibold text-orange-50">${escapeHtml(it.name)}</p>
                        <p class="mt-0.5 text-xs text-orange-700">¥${escapeHtml(it.price)} each</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-[10px]">
                            <span class="rounded-full border px-2 py-0.5 ${statusMeta.cls}">${statusMeta.label}</span>
                            <span class="text-orange-500">Delivered ${deliveredQty}/${it.quantity}</span>
                            <span class="text-orange-700">Remain ${remaining}</span>
                        </div>
                    </div>
                    <p class="shrink-0 text-base font-bold tabular-nums text-orange-100">¥${escapeHtml(it.line_total)}</p>
                </div>
                ${ctrls}
                ${prepActions}
            </div>`;
    }

    function renderAddItemBlock(order) {
        const cats = panelMenuCatalog?.categories ?? [];
        let optHtml = '<option value="">Choose an item…</option>';
        for (const c of cats) {
            optHtml += `<optgroup label="${escapeHtml(c.name)}">`;
            for (const m of c.items ?? []) {
                optHtml += `<option value="${m.id}">${escapeHtml(m.name)} · ¥${escapeHtml(m.price)}</option>`;
            }
            optHtml += '</optgroup>';
        }
        if (!cats.length) {
            optHtml = '<option value="">No menu loaded</option>';
        }

        return `
            <details class="mt-4 rounded-xl border border-orange-900/50 bg-orange-950/15 open:border-orange-700/80">
                <summary class="cursor-pointer select-none px-3 py-3 text-sm font-semibold text-orange-200">Add items</summary>
                <div class="space-y-3 border-t border-orange-950/40 p-3">
                    <select data-df-add-menu-item class="w-full rounded-xl border border-orange-900/50 bg-black/40 px-3 py-3 text-sm text-orange-50">${optHtml}</select>
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="text-xs font-semibold uppercase text-orange-700">Qty</label>
                        <input type="number" min="1" max="999" value="1" data-df-add-qty class="w-24 rounded-xl border border-orange-900/50 bg-black/40 px-3 py-2 text-center text-sm text-orange-50" />
                    </div>
                    <button type="button" data-df-pos-action="add-item" data-order-id="${order.id}" class="w-full rounded-xl bg-orange-600 py-3 text-sm font-semibold text-white shadow hover:bg-orange-500">Add to order</button>
                </div>
            </details>`;
    }

    let sessionHistoryPage = 1;

    function renderOrderCard(order, options = {}) {
        const historyMode = options.history === true;
        const editable = historyMode ? false : order.editable === true;
        let lockBanner = '';
        if (historyMode) {
            lockBanner = `<div class="mb-3 rounded-xl border border-slate-700/50 bg-slate-950/30 px-3 py-2 text-xs text-slate-200">
                        History view only
                    </div>`;
        } else if (!editable && order.status === 'preparing') {
            lockBanner = `<div class="mb-3 flex items-center gap-2 rounded-xl border border-amber-700/50 bg-amber-950/40 px-3 py-2 text-xs text-amber-100">
                        <span aria-hidden="true">🔒</span>
                        <span>Order already sent to kitchen</span>
                    </div>`;
        } else if (!editable && order.status === 'completed') {
            lockBanner = `<div class="mb-3 rounded-xl border border-blue-800/50 bg-blue-950/30 px-3 py-2 text-xs text-blue-100">
                        Food served — use <strong>Checkout</strong> below when the bill is settled.
                    </div>`;
        }

        const kitchenItems = (order.items ?? []).filter((it) => it.is_kitchen === true);
        const nonKitchenItems = (order.items ?? []).filter((it) => it.is_kitchen !== true);
        const renderItemGroup = (title, items) => items.length
            ? `<div class="min-w-0 space-y-3 rounded-xl border border-orange-900/35 bg-black/15 p-3">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-orange-700">${title}</p>
                    ${(items ?? []).map((it) => renderLineItem(order, it, editable, { history: historyMode })).join('')}
               </div>`
            : '';
        const lines = [
            renderItemGroup('Kitchen Items', kitchenItems),
            renderItemGroup('Non Kitchen Items', nonKitchenItems),
        ].filter(Boolean).join('');
        const linesLayout = lines
            ? `<div class="grid grid-cols-1 gap-3 xl:grid-cols-2">${lines}</div>`
            : '';
        const readyCount = (order.items ?? []).filter((it) => String(it.preparation_status) === 'ready' && Number(it.remaining_quantity ?? 0) > 0).length;

        const totals = `
            <div class="mt-3 space-y-1 border-t border-orange-950/40 pt-3 text-xs text-orange-200">
                <div class="flex justify-between gap-2 text-sm font-semibold text-orange-50"><span>Total</span><span class="tabular-nums">¥${escapeHtml(order.grand_total ?? order.total_amount)}</span></div>
            </div>`;

        const addBlock = editable ? renderAddItemBlock(order) : '';
        const deliverAllBlock = !historyMode && readyCount > 0
            ? `<button type="button" data-df-order-action="deliver-all-ready" data-order-id="${order.id}" class="mt-3 w-full rounded-lg border border-emerald-800/60 bg-emerald-950/35 px-3 py-2 text-xs font-semibold text-emerald-100 hover:bg-emerald-900/40">Deliver All Ready Items (${readyCount})</button>`
            : '';

        return `
            <div class="rounded-xl border border-orange-900/40 bg-black/20 p-3" data-df-order-card="${order.id}">
                ${lockBanner}
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-mono text-xs text-orange-600">${order.order_number ? `Order ${escapeHtml(String(order.order_number))}` : `#${order.id}`}</span>
                    <span class="rounded-full bg-orange-950 px-2 py-0.5 text-[10px] font-semibold uppercase text-orange-200">${escapeHtml(order.status)}</span>
                </div>
                <div class="mt-3">${linesLayout}</div>
                ${totals}
                ${deliverAllBlock}
                ${addBlock}
                <p class="mt-2 text-[11px] text-orange-800">${formatTime(order.created_at)}</p>
            </div>`;
    }

    function mergeOrderFromResponse(order) {
        const el = activeOrdersEl?.querySelector(`[data-df-order-card="${order.id}"]`);
        if (el) {
            el.outerHTML = renderOrderCard(order);
        }
    }

    function renderSessionHistory(data) {
        if (!sessionHistoryEl) {
            return;
        }

        const history = data.session_history ?? {};
        const historySessions = history.data ?? [];

        if (!historySessions.length) {
            sessionHistoryEl.innerHTML = '<p class="text-sm text-orange-800">No completed session history.</p>';
            return;
        }

        const totalPages = Math.max(1, Number(history.last_page ?? 1));
        sessionHistoryPage = Math.min(Math.max(1, Number(history.current_page ?? 1)), totalPages);

        sessionHistoryEl.innerHTML = historySessions
            .map((sess, idx) => {
                const start = (sessionHistoryPage - 1) * Number(history.per_page ?? SESSION_HISTORY_PAGE_SIZE);
                const sid = sess.customer_session_id ? `Session #${sess.customer_session_id}` : `Visit ${start + idx + 1}`;
                const blocks = (sess.orders ?? []).map((order) => renderOrderCard(order, { history: true })).join('');
                return `<details class="group rounded-xl border border-orange-950/50 bg-black/10 open:bg-black/20">
                    <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-orange-200">${escapeHtml(sid)}</summary>
                    <div class="space-y-2 border-t border-orange-950/40 px-3 py-3">${blocks}</div>
                </details>`;
            })
            .join('');

        if (totalPages > 1) {
            sessionHistoryEl.insertAdjacentHTML(
                'beforeend',
                `<div class="mt-3 flex items-center justify-between gap-3 rounded-xl border border-orange-900/40 bg-black/15 px-3 py-2 text-xs text-orange-200">
                    <button type="button" data-df-history-page="prev" class="rounded-lg border border-orange-800 px-2.5 py-1 disabled:cursor-not-allowed disabled:opacity-40" ${sessionHistoryPage <= 1 ? 'disabled' : ''}>Prev</button>
                    <span>Page ${sessionHistoryPage} / ${totalPages}</span>
                    <button type="button" data-df-history-page="next" class="rounded-lg border border-orange-800 px-2.5 py-1 disabled:cursor-not-allowed disabled:opacity-40" ${sessionHistoryPage >= totalPages ? 'disabled' : ''}>Next</button>
                </div>`
            );
        }
    }

    async function handlePosClick(e) {
        const itemActionBtn = e.target.closest('[data-df-item-action]');
        if (itemActionBtn) {
            const action = itemActionBtn.dataset.dfItemAction;
            const orderId = itemActionBtn.dataset.orderId;
            const itemId = itemActionBtn.dataset.itemId;
            if (!orderId || !itemId) {
                return;
            }
            try {
                let data;
                if (action === 'mark-preparing') {
                    data = await fetchJson(itemActionUrl(urlItemPreparingTemplate, orderId, itemId), { method: 'POST' });
                } else if (action === 'mark-ready') {
                    data = await fetchJson(itemActionUrl(urlItemReadyTemplate, orderId, itemId), { method: 'POST' });
                } else if (action === 'deliver') {
                    const remaining = Math.max(1, Number(itemActionBtn.dataset.remaining ?? 1));
                    data = await fetchJson(itemActionUrl(urlItemDeliverTemplate, orderId, itemId), {
                        method: 'POST',
                        body: JSON.stringify({ quantity: remaining }),
                    });
                } else {
                    return;
                }
                if (data.panel) {
                    applyPanelData(data.panel);
                } else {
                    mergeOrderFromResponse(data.order);
                }
                scheduleRefresh(true);
            } catch (err) {
                window.alert(err instanceof Error ? err.message : 'Update failed');
            }
            return;
        }

        const orderActionBtn = e.target.closest('[data-df-order-action]');
        if (orderActionBtn && orderActionBtn.dataset.dfOrderAction === 'deliver-all-ready') {
            const orderId = orderActionBtn.dataset.orderId;
            if (!orderId) {
                return;
            }
            try {
                const data = await fetchJson(deliverAllReadyUrl(orderId), { method: 'POST' });
                if (data.panel) {
                    applyPanelData(data.panel);
                } else {
                    mergeOrderFromResponse(data.order);
                }
                scheduleRefresh(true);
            } catch (err) {
                window.alert(err instanceof Error ? err.message : 'Update failed');
            }
            return;
        }

        const btn = e.target.closest('[data-df-pos-action]');
        if (!btn || !urlOrdersBase) {
            return;
        }
        const act = btn.dataset.dfPosAction;
        const orderId = btn.dataset.orderId;
        const itemId = btn.dataset.itemId;
        if (!orderId) {
            return;
        }
        try {
            if (act === 'add-item') {
                const card = btn.closest('[data-df-order-card]');
                const sel = card?.querySelector('[data-df-add-menu-item]');
                const qtyIn = card?.querySelector('[data-df-add-qty]');
                const mid = sel?.value;
                const qty = Math.max(1, Math.min(999, Number(qtyIn?.value ?? 1)));
                if (!mid) {
                    return;
                }
                const data = await fetchJson(`${urlOrdersBase}/${orderId}/items`, {
                    method: 'POST',
                    body: JSON.stringify({
                        menu_item_id: Number(mid),
                        quantity: qty,
                        notes: null,
                        options: {},
                    }),
                });
                if (data.panel) {
                    applyPanelData(data.panel);
                } else {
                    mergeOrderFromResponse(data.order);
                }
                scheduleRefresh(true);
                return;
            }
            if (!itemId) {
                return;
            }
            if (act === 'inc') {
                const data = await fetchJson(`${urlOrdersBase}/${orderId}/items/${itemId}/increment`, {
                    method: 'POST',
                    body: JSON.stringify({}),
                });
                if (data.panel) {
                    applyPanelData(data.panel);
                } else {
                    mergeOrderFromResponse(data.order);
                }
            } else if (act === 'dec') {
                const data = await fetchJson(`${urlOrdersBase}/${orderId}/items/${itemId}/decrement`, {
                    method: 'POST',
                    body: JSON.stringify({}),
                });
                if (data.panel) {
                    applyPanelData(data.panel);
                } else {
                    mergeOrderFromResponse(data.order);
                }
            } else if (act === 'remove') {
                const data = await fetchJson(`${urlOrdersBase}/${orderId}/items/${itemId}`, {
                    method: 'DELETE',
                });
                if (data.panel) {
                    applyPanelData(data.panel);
                } else {
                    mergeOrderFromResponse(data.order);
                }
            }
            scheduleRefresh(true);
        } catch (err) {
            window.alert(err instanceof Error ? err.message : 'Update failed');
        }
    }


    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function formatTime(iso) {
        if (!iso) {
            return '';
        }
        try {
            return new Date(iso).toLocaleString();
        } catch {
            return iso;
        }
    }

    async function patchOrderStatus(orderId, status) {
        await fetchJson(orderStatusUrl(orderId), {
            method: 'PATCH',
            body: JSON.stringify({ status }),
        });
        await loadFloorState(false);
        if (selectedId) {
            await loadPanel(selectedId);
        }
    }

    function renderStatusButtons(activeOrders, targetEl = statusActionsEl) {
        if (!targetEl) {
            return;
        }
        targetEl.innerHTML = '';
        const pending = activeOrders.find((o) => o.status === 'pending');
        const preparing = activeOrders.find((o) => o.status === 'preparing');

        const mkBtn = (label, cls, onClick) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = `rounded-xl px-3 py-2 text-xs font-semibold ${cls}`;
            b.textContent = label;
            b.addEventListener('click', onClick);
            targetEl.appendChild(b);
        };

        if (pending) {
            mkBtn('Accept → Preparing', 'bg-emerald-700 text-white hover:bg-emerald-600', () =>
                patchOrderStatus(pending.id, 'preparing'),
            );
        }
        if (preparing) {
            mkBtn('Food delivered (completed)', 'bg-sky-700 text-white hover:bg-sky-600', () =>
                patchOrderStatus(preparing.id, 'completed'),
            );
        }
        if (!pending && !preparing && activeOrders.length) {
            mkBtn('Refresh', 'border border-orange-800 text-orange-200 hover:bg-orange-950', () =>
                selectedId ? loadPanel(selectedId) : undefined,
            );
        }
    }

    function openCheckoutModal(url, summary) {
        if (!checkoutModal || !checkoutConfirm || !checkoutSummary) {
            window.location.assign(url);
            return;
        }
        checkoutConfirm.setAttribute('href', url);
        checkoutSummary.innerHTML = `
            <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                <span class="text-orange-700">Session</span><span>${escapeHtml(summary.sessionCode || '—')}</span>
                <span class="text-orange-700">Total orders</span><span>${escapeHtml(String(summary.totalOrders || 0))}</span>
                <span class="text-orange-700">Total amount</span><span class="font-semibold text-amber-200">¥${escapeHtml(summary.totalAmount || '0.00')}</span>
                <span class="text-orange-700">Payment</span><span>${escapeHtml(summary.paymentMethod || 'Cash')}</span>
            </div>`;
        checkoutModal.classList.remove('hidden');
        checkoutModal.classList.add('flex');
    }

    function closeCheckoutModal() {
        if (!checkoutModal) return;
        checkoutModal.classList.add('hidden');
        checkoutModal.classList.remove('flex');
    }

    function buildSessionActions(panelData) {
        const sessions = panelData.sessions ?? [];
        if (!sessions.length) {
            return '<p class="rounded-xl border border-orange-900/50 bg-black/20 px-3 py-3 text-sm text-orange-700">No dining session found.</p>';
        }
        const openSession =
            sessions.find((s) => (s.orders ?? []).some((o) => o.status !== 'checkout_done')) ??
            sessions[0];
        const orders = openSession.orders ?? [];
        const sessionStatus = String(openSession.session_status ?? 'open');
        const firstOrder = orders[0];
        if (!firstOrder) {
            return '<p class="rounded-xl border border-orange-900/50 bg-black/20 px-3 py-3 text-sm text-orange-700">No orders in session.</p>';
        }
        const orderIds = orders.map((o) => o.id).join(',');
        const isOpen = orders.some((o) => o.status !== 'checkout_done');
        const hasCompletedForCheckout = orders.some((o) => o.status === 'completed');
        const checkoutTarget = orders.find((o) => o.status === 'completed');
        const runningTotal = openSession.grand_total ?? orders.reduce((sum, o) => sum + Number(o.grand_total ?? o.total_amount ?? 0), 0).toFixed(2);
        const pendingKitchen = Number(panelData.table?.counts?.pending_kitchen ?? 0);
        const pendingNonKitchen = Number(panelData.table?.counts?.pending_non_kitchen ?? 0);
        const sessionStateClass = sessionStatus === 'food_delivered'
            ? 'text-emerald-300 border-emerald-500/40 bg-emerald-950/25'
            : (isOpen
                ? (hasCompletedForCheckout ? 'text-orange-300 border-orange-500/40 bg-orange-950/25' : 'text-emerald-300 border-emerald-500/40 bg-emerald-950/25')
                : 'text-slate-300 border-slate-600/40 bg-slate-900/30');
        const checkoutUrl = checkoutTarget ? `/orders/${checkoutTarget.id}/pay` : '';
        const billPreviewUrl = `/orders/${firstOrder.id}/bill/thermal?ids=${encodeURIComponent(orderIds)}&paper=80`;
        const checkoutBtn = isOpen && hasCompletedForCheckout
            ? `<button type="button" data-df-session-checkout="${checkoutUrl}" data-df-session-code="${escapeHtml(openSession.session_code ?? '')}" data-df-session-orders="${orders.length}" data-df-session-total="${escapeHtml(String(runningTotal))}" class="w-full rounded-xl bg-rose-700 px-3 py-2.5 text-sm font-semibold text-white hover:bg-rose-600">Checkout Session</button>`
            : `<button type="button" disabled class="w-full cursor-not-allowed rounded-xl border border-slate-700 bg-slate-900/50 px-3 py-2.5 text-sm font-semibold text-slate-500">Checkout Session</button>`;

        return `
            <div class="sticky top-0 rounded-xl border ${sessionStateClass} p-3">
                <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                    <span class="text-orange-700">Table</span><span>${escapeHtml(String(panelData.table?.table_name ?? `Table ${panelData.table?.table_number ?? ''}`))}</span>
                    <span class="text-orange-700">Session</span><span>${escapeHtml(openSession.session_code ?? '—')}</span>
                    <span class="text-orange-700">Orders</span><span>${orders.length}</span>
                    <span class="text-orange-700">Status</span><span class="uppercase">${escapeHtml(sessionStatus.replace('_', ' '))}</span>
                    <span class="text-orange-700">Started</span><span>${escapeHtml(formatTime(openSession.started_at || ''))}</span>
                </div>
                <div class="mt-2 rounded-lg border border-amber-500/40 bg-amber-950/30 px-2.5 py-2 text-right">
                    <span class="text-[10px] uppercase tracking-wide text-amber-300/80">Running Total</span>
                    <p class="text-lg font-bold text-amber-200">¥${escapeHtml(String(runningTotal))}</p>
                </div>
                <div class="mt-3 grid grid-cols-1 gap-2 text-xs">
                    ${pendingKitchen > 0 ? `<button type="button" data-df-mark-seen="kitchen" data-table-id="${panelData.table?.id}" class="rounded-lg border border-orange-700 px-2 py-2 text-center text-orange-100 hover:bg-orange-950">Mark Kitchen Seen</button>` : ''}
                    ${pendingNonKitchen > 0 ? `<button type="button" data-df-mark-seen="non_kitchen" data-table-id="${panelData.table?.id}" class="rounded-lg border border-orange-700 px-2 py-2 text-center text-orange-100 hover:bg-orange-950">Mark Non-Kitchen Seen</button>` : ''}
                    <a href="${billPreviewUrl}" target="_blank" rel="noopener" class="rounded-lg border border-orange-700 px-2 py-2 text-center text-orange-100 hover:bg-orange-950">Print Bill Preview</a>
                </div>
                <div class="mt-3">${checkoutBtn}</div>
            </div>`;
    }

    function applyPanelData(data) {
        panelMenuCatalog = data.menu_catalog ?? null;
        if (drawerTitle) {
            drawerTitle.textContent = data.table?.table_name ?? `Table ${data.table?.table_number}`;
        }
        if (drawerMeta) {
            const allItems = (data.active_orders ?? []).flatMap((o) => o.items ?? []);
            const countBy = (s) => allItems.filter((i) => String(i.preparation_status) === s).length;
            drawerMeta.innerHTML = `
                <span>Table #${escapeHtml(String(data.table?.table_number ?? ''))} · Seats ${escapeHtml(String(data.table?.seat_capacity ?? '—'))}</span>
                <span class="ml-2 inline-flex items-center gap-1 text-[10px]">
                    <span class="rounded-full bg-red-900/35 px-1.5 py-0.5 text-red-300">P ${countBy('pending')}</span>
                    <span class="rounded-full bg-orange-900/35 px-1.5 py-0.5 text-orange-300">PR ${countBy('preparing')}</span>
                    <span class="rounded-full bg-blue-900/35 px-1.5 py-0.5 text-blue-300">R ${countBy('ready')}</span>
                    <span class="rounded-full bg-emerald-900/35 px-1.5 py-0.5 text-emerald-300">D ${countBy('delivered')}</span>
                </span>`;
        }
        if (sessionActionsEl) {
            sessionActionsEl.innerHTML = buildSessionActions(data);
            const sessions = data.sessions ?? [];
            const openSession =
                sessions.find((s) => (s.orders ?? []).some((o) => o.status !== 'checkout_done')) ??
                sessions[0];
            if (openSession) {
                const runningTotalCard = Array.from(sessionActionsEl.querySelectorAll('div')).find((el) => {
                    const text = (el.textContent ?? '').toLowerCase();
                    return text.includes('running total');
                });
                if (runningTotalCard) {
                    const subtotal = escapeHtml(String(openSession.subtotal ?? '0.00'));
                    const tax = escapeHtml(String(openSession.tax ?? '0.00'));
                    const alreadyPatched = runningTotalCard.querySelector('[data-df-session-subtotal]');
                    const totalLabel = Array.from(runningTotalCard.querySelectorAll('span')).find((el) =>
                        (el.textContent ?? '').toLowerCase().includes('running total')
                    );
                    if (totalLabel && !alreadyPatched) {
                        totalLabel.insertAdjacentHTML(
                            'beforebegin',
                            `<div data-df-session-subtotal class="flex items-center justify-between gap-3 text-[11px] text-amber-200/80"><span>Subtotal</span><span class="font-semibold">¥${subtotal}</span></div>
                             <div data-df-session-tax class="mt-1 flex items-center justify-between gap-3 text-[11px] text-amber-200/80"><span>Tax</span><span class="font-semibold">¥${tax}</span></div>`
                        );
                    } else if (alreadyPatched) {
                        const subtotalRow = runningTotalCard.querySelector('[data-df-session-subtotal] .font-semibold');
                        const taxRow = runningTotalCard.querySelector('[data-df-session-tax] .font-semibold');
                        if (subtotalRow) {
                            subtotalRow.textContent = `¥${subtotal}`;
                        }
                        if (taxRow) {
                            taxRow.textContent = `¥${tax}`;
                        }
                    }
                }
            }
        }
        if (mobileSessionBar) {
            const sessions = data.sessions ?? [];
            const openSession = sessions.find((s) => (s.orders ?? []).some((o) => o.status !== 'checkout_done'));
            const orders = openSession?.orders ?? [];
            const checkoutTarget = orders.find((o) => o.status === 'completed');
            if (openSession && checkoutTarget) {
                const total = escapeHtml(String(openSession.grand_total ?? '0.00'));
                mobileSessionBar.classList.remove('hidden', 'pointer-events-none');
                mobileSessionBar.classList.add('pointer-events-auto');
                mobileSessionBar.innerHTML = `<button type="button" data-df-session-checkout="/orders/${checkoutTarget.id}/pay" data-df-session-code="${escapeHtml(openSession.session_code ?? '')}" data-df-session-orders="${orders.length}" data-df-session-total="${total}" class="w-full rounded-lg bg-rose-700 py-2 text-sm font-semibold text-white">Checkout Session · ¥${total}</button>`;
            } else {
                mobileSessionBar.classList.add('hidden', 'pointer-events-none');
                mobileSessionBar.innerHTML = '';
            }
        }
        if (activeOrdersEl) {
            const act = data.active_orders ?? [];
            const actionable = act.filter((o) => o.status === 'pending' || o.status === 'preparing');
            const completed = act.filter((o) => o.status === 'completed');
            if (!act.length) {
                activeOrdersEl.innerHTML = '<p class="text-orange-800">No active kitchen ticket.</p>';
                renderStatusButtons([]);
            } else {
                const inlineKitchen = actionable.length
                    ? `<div data-df-inline-kitchen-actions class="rounded-xl border border-orange-900/40 bg-black/15 p-2.5">
                            <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-orange-700">Kitchen actions (latest first)</p>
                            <div class="flex flex-wrap gap-2"></div>
                        </div>`
                    : '';
                activeOrdersEl.innerHTML = [
                    ...actionable.map(renderOrderCard),
                    inlineKitchen,
                    ...completed.map(renderOrderCard),
                ].join('');

                const inlineWrap = activeOrdersEl.querySelector('[data-df-inline-kitchen-actions] > div');
                renderStatusButtons(act, inlineWrap || statusActionsEl);
            }
        }
        renderSessionHistory(data);
        if (drawerEmpty && drawerBody) {
            drawerEmpty.classList.add('hidden');
            drawerBody.classList.remove('hidden');
            drawerBody.classList.add('flex');
        }
    }

    async function loadPanel(tableId) {
        const data = await fetchJson(panelUrl(tableId));
        applyPanelData(data);
    }

    async function selectTable(group) {
        selectedId = group.getAttr('tableId');
        tableNodes.forEach((node, tid) => {
            const body = node.findOne('.shape-body');
            if (!body) {
                return;
            }
            const visual = node.getAttr('visual') ?? 'available';
            const on = Number(tid) === Number(selectedId);
            if (on) {
                body.stroke('#fbbf24');
                body.strokeWidth(5);
            } else if (visual === 'pending') {
                body.stroke('#fb923c');
                body.strokeWidth(5);
            } else {
                body.stroke('#1c1917');
                body.strokeWidth(2);
            }
        });
        layer.batchDraw();
        openDrawer();
        await loadPanel(selectedId);
    }

    function applySearchFilter() {
        const q = (searchInput?.value ?? '').trim().toLowerCase();
        tableNodes.forEach((node) => {
            const text = node.getAttr('searchText') ?? '';
            const match = !q || text.includes(q);
            node.opacity(match ? 1 : 0.12);
        });
        layer.batchDraw();
    }

    function resizeStage() {
        if (!stage || !container) {
            return;
        }
        stage.width(container.clientWidth);
        stage.height(container.clientHeight);
        layer.batchDraw();
    }

    const gridCanvas = createGridPattern();
    const patternImage = gridCanvas ? new Image() : null;
    if (patternImage && gridCanvas) {
        patternImage.src = gridCanvas.toDataURL();
    }

    stage = new Konva.Stage({
        container: 'df-konva-container',
        width: container.clientWidth,
        height: container.clientHeight,
    });

    layer = new Konva.Layer();
    world = new Konva.Group({ x: 40, y: 40, name: 'world' });

    const floorBg = new Konva.Rect({
        name: 'floor-bg',
        x: 0,
        y: 0,
        width: WORLD_W,
        height: WORLD_H,
        fillPatternImage: patternImage ?? undefined,
        fillPatternRepeat: 'repeat',
        stroke: '#431407',
        strokeWidth: 1,
    });
    if (!patternImage) {
        floorBg.fill('#0c0a09');
    } else {
        const applyPattern = () => {
            floorBg.fillPatternImage(patternImage);
            floorBg.fillPatternRepeat('repeat');
            layer?.batchDraw();
        };
        if (patternImage.complete) {
            applyPattern();
        } else {
            patternImage.onload = applyPattern;
        }
    }

    tablesLayer = new Konva.Group({ name: 'tables-layer' });
    world.add(floorBg);
    world.add(tablesLayer);
    layer.add(world);
    stage.add(layer);

    stage.on('mousedown touchstart', (e) => {
        if (e.target.getAttr?.('name') === 'floor-bg') {
            isPanning = true;
            lastPointer = stage.getPointerPosition();
            closeDrawer();
        }
    });

    stage.on('mousemove touchmove', () => {
        if (!isPanning || !lastPointer) {
            return;
        }
        const p = stage.getPointerPosition();
        if (!p) {
            return;
        }
        const dx = p.x - lastPointer.x;
        const dy = p.y - lastPointer.y;
        world.x(world.x() + dx);
        world.y(world.y() + dy);
        lastPointer = p;
        layer.batchDraw();
    });

    stage.on('mouseup touchend mouseleave', () => {
        isPanning = false;
        lastPointer = null;
    });

    stage.on('wheel', (e) => {
        e.evt.preventDefault();
        const oldScale = worldScale;
        const pointer = stage.getPointerPosition();
        if (!pointer) {
            return;
        }
        const scaleBy = 1.06;
        const direction = e.evt.deltaY > 0 ? -1 : 1;
        let newScale = direction > 0 ? oldScale * scaleBy : oldScale / scaleBy;
        newScale = Math.max(0.35, Math.min(2.25, newScale));
        const mousePointTo = {
            x: (pointer.x - world.x()) / oldScale,
            y: (pointer.y - world.y()) / oldScale,
        };
        worldScale = newScale;
        world.scale({ x: newScale, y: newScale });
        world.position({
            x: pointer.x - mousePointTo.x * newScale,
            y: pointer.y - mousePointTo.y * newScale,
        });
        layer.batchDraw();
    });

    stage.on('click tap', (e) => {
        let node = e.target;
        while (node && node.getAttr && node.getAttr('tableId') == null) {
            node = node.getParent();
        }
        if (node && node.getAttr('tableId') != null) {
            selectTable(node);
        }
    });

    window.addEventListener('resize', () => {
        resizeStage();
        setToolbarHeight();
    });

    document.getElementById('df-zoom-in')?.addEventListener('click', () => {
        const oldScale = worldScale;
        let newScale = Math.min(2.25, oldScale * 1.12);
        const center = { x: stage.width() / 2, y: stage.height() / 2 };
        const mousePointTo = {
            x: (center.x - world.x()) / oldScale,
            y: (center.y - world.y()) / oldScale,
        };
        worldScale = newScale;
        world.scale({ x: newScale, y: newScale });
        world.position({
            x: center.x - mousePointTo.x * newScale,
            y: center.y - mousePointTo.y * newScale,
        });
        layer.batchDraw();
    });

    document.getElementById('df-zoom-out')?.addEventListener('click', () => {
        const oldScale = worldScale;
        let newScale = Math.max(0.35, oldScale / 1.12);
        const center = { x: stage.width() / 2, y: stage.height() / 2 };
        const mousePointTo = {
            x: (center.x - world.x()) / oldScale,
            y: (center.y - world.y()) / oldScale,
        };
        worldScale = newScale;
        world.scale({ x: newScale, y: newScale });
        world.position({
            x: center.x - mousePointTo.x * newScale,
            y: center.y - mousePointTo.y * newScale,
        });
        layer.batchDraw();
    });

    document.getElementById('df-zoom-reset')?.addEventListener('click', () => {
        worldScale = 1;
        world.scale({ x: 1, y: 1 });
        world.position({ x: 40, y: 40 });
        layer.batchDraw();
    });

    drawerClose?.addEventListener('click', () => closeDrawer());
    drawerBackdrop?.addEventListener('click', () => closeDrawer());
    searchInput?.addEventListener('input', applySearchFilter);

    activeOrdersEl?.addEventListener('click', handlePosClick);
    sessionHistoryEl?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-df-history-page]');
        if (!btn) {
            return;
        }
        sessionHistoryPage += btn.dataset.dfHistoryPage === 'next' ? 1 : -1;
        if (selectedId) {
            loadPanel(selectedId).catch(() => {});
        }
    });
    sessionActionsEl?.addEventListener('click', (e) => {
        const seenBtn = e.target.closest('[data-df-mark-seen]');
        if (seenBtn) {
            const side = seenBtn.getAttribute('data-df-mark-seen');
            const tableId = seenBtn.getAttribute('data-table-id');
            if (side && tableId) {
                markTableSideSeen(tableId, side);
                seenBtn.setAttribute('disabled', 'disabled');
                seenBtn.classList.add('opacity-40', 'pointer-events-none');
            }
            return;
        }
        const btn = e.target.closest('[data-df-session-checkout]');
        if (!btn) return;
        const url = btn.getAttribute('data-df-session-checkout');
        if (!url) return;
        openCheckoutModal(url, {
            sessionCode: btn.getAttribute('data-df-session-code') ?? '',
            totalOrders: btn.getAttribute('data-df-session-orders') ?? '0',
            totalAmount: btn.getAttribute('data-df-session-total') ?? '0.00',
            paymentMethod: 'Cash',
        });
    });
    mobileSessionBar?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-df-session-checkout]');
        if (!btn) return;
        const url = btn.getAttribute('data-df-session-checkout');
        if (!url) return;
        openCheckoutModal(url, {
            sessionCode: btn.getAttribute('data-df-session-code') ?? '',
            totalOrders: btn.getAttribute('data-df-session-orders') ?? '0',
            totalAmount: btn.getAttribute('data-df-session-total') ?? '0.00',
            paymentMethod: 'Cash',
        });
    });

    window.addEventListener('restaurant:refresh-floor', () => scheduleRefresh(true));
    window.addEventListener('restaurant:order-updated', (e) => {
        const tid = e.detail?.order?.table_id;
        if (selectedId != null && tid != null && Number(selectedId) === Number(tid)) {
            loadPanel(selectedId).catch(() => {});
        }
    });

    document.getElementById('df-kitchen')?.addEventListener('click', () => {
        const on = root.classList.toggle('kitchen-mode');
        window.localStorage.setItem(KITCHEN_MODE_KEY, on ? '1' : '0');
        if (on) {
            root.classList.add('brightness-110', 'contrast-125');
        } else {
            root.classList.remove('brightness-110', 'contrast-125');
        }
    });
    if (window.localStorage.getItem(KITCHEN_MODE_KEY) === '1') {
        root.classList.add('kitchen-mode', 'brightness-110', 'contrast-125');
    }

    document.getElementById('df-fullscreen')?.addEventListener('click', async () => {
        try {
            if (!document.fullscreenElement) {
                await root.requestFullscreen();
            } else {
                await document.exitFullscreen();
            }
        } catch {
            /* ignore */
        }
    });

    function closeActionsMenu() {
        const panel = document.getElementById('df-actions-menu-panel');
        const btn = document.getElementById('df-actions-menu-btn');
        panel?.classList.add('hidden');
        btn?.setAttribute('aria-expanded', 'false');
    }

    (function initDashboardActionsMenu() {
        const wrap = document.getElementById('df-actions-menu-wrap');
        const btn = document.getElementById('df-actions-menu-btn');
        const panel = document.getElementById('df-actions-menu-panel');
        if (!wrap || !btn || !panel) {
            return;
        }
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = panel.classList.contains('hidden');
            panel.classList.toggle('hidden', !open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', () => {
            closeActionsMenu();
        });
        wrap.addEventListener('click', (e) => e.stopPropagation());
        panel.querySelectorAll('a[role="menuitem"]').forEach((link) => {
            link.addEventListener('click', () => closeActionsMenu());
        });
    })();

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeDrawer();
            closeActionsMenu();
            closeCheckoutModal();
        }
    });
    checkoutCancel?.addEventListener('click', closeCheckoutModal);
    checkoutModal?.addEventListener('click', (e) => {
        if (e.target === checkoutModal) {
            closeCheckoutModal();
        }
    });

    window.setInterval(() => {
        loadFloorState(false).catch(() => {});
    }, 12000);

    setToolbarHeight();
    resizeStage();
    loadFloorState(true)
        .then(() => {})
        .catch(() => {});
});
