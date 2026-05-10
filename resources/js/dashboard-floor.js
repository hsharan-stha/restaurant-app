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

function tableDisplayLabel(tableName, tableNumber) {
    const n = (tableName ?? '').trim();
    if (n) {
        return n;
    }
    return `Table ${tableNumber ?? ''}`;
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
            ? `${baseLabel}\n👥 ${model.guest_party_size}`
            : baseLabel;
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
    const liveCountEl = document.getElementById('df-live-count');
    const searchInput = document.getElementById('df-search');

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
                        guest != null && guest !== '' ? `${base}\n👥 ${guest}` : base,
                    );
                }
            }
        });
    }

    let refreshTimer = null;
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
    }

    function panelUrl(id) {
        return urlPanelTemplate.replace('__ID__', String(id));
    }

    function orderStatusUrl(orderId) {
        return urlOrderStatusTemplate.replace('__ORDER__', String(orderId));
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

    function renderLineItem(order, it, editable) {
        const opts = it.options && typeof it.options === 'object' ? it.options : {};
        const spiceVal = opts.spice_level ?? '';
        const toppingsStr = Array.isArray(opts.toppings) ? opts.toppings.join(', ') : '';
        const ctrls = editable
            ? `<div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" data-df-pos-action="dec" data-order-id="${order.id}" data-item-id="${it.id}" class="min-h-[44px] min-w-[44px] shrink-0 rounded-xl border border-orange-700 bg-orange-950/60 text-xl font-bold leading-none text-orange-50 hover:bg-orange-900">−</button>
                    <span class="min-w-[2.25rem] text-center text-base font-semibold tabular-nums text-orange-50">${it.quantity}</span>
                    <button type="button" data-df-pos-action="inc" data-order-id="${order.id}" data-item-id="${it.id}" class="min-h-[44px] min-w-[44px] shrink-0 rounded-xl border border-orange-700 bg-orange-950/60 text-xl font-bold leading-none text-orange-50 hover:bg-orange-900">+</button>
                    <button type="button" data-df-pos-action="remove" data-order-id="${order.id}" data-item-id="${it.id}" class="ml-auto rounded-xl border border-red-900/60 bg-red-950/40 px-3 py-2 text-xs font-semibold text-red-200">Remove</button>
                </div>
                <label class="mt-3 block text-[10px] font-semibold uppercase tracking-wide text-orange-700">Special notes</label>
                <textarea data-df-pos-notes data-order-id="${order.id}" data-item-id="${it.id}" rows="2" class="mt-1 w-full rounded-xl border border-orange-900/50 bg-black/40 px-3 py-2 text-sm text-orange-50 placeholder:text-orange-900 focus:border-orange-500 focus:outline-none">${escapeHtml(it.notes ?? '')}</textarea>
                <label class="mt-2 block text-[10px] font-semibold uppercase tracking-wide text-orange-700">Spice</label>
                <select data-df-pos-spice data-order-id="${order.id}" data-item-id="${it.id}" class="mt-1 w-full rounded-xl border border-orange-900/50 bg-black/40 px-3 py-2 text-sm text-orange-50">
                    <option value="">—</option>
                    <option value="mild" ${spiceVal === 'mild' ? 'selected' : ''}>Mild</option>
                    <option value="medium" ${spiceVal === 'medium' ? 'selected' : ''}>Medium</option>
                    <option value="hot" ${spiceVal === 'hot' ? 'selected' : ''}>Hot</option>
                    <option value="extra_hot" ${spiceVal === 'extra_hot' ? 'selected' : ''}>Extra hot</option>
                </select>
                <label class="mt-2 block text-[10px] font-semibold uppercase tracking-wide text-orange-700">Toppings (comma-separated)</label>
                <input type="text" data-df-pos-toppings data-order-id="${order.id}" data-item-id="${it.id}" value="${escapeHtml(toppingsStr)}" class="mt-1 w-full rounded-xl border border-orange-900/50 bg-black/40 px-3 py-2 text-sm text-orange-50 placeholder:text-orange-900 focus:border-orange-500 focus:outline-none" placeholder="e.g. extra cheese, bacon" />`
            : `<p class="mt-2 text-sm text-orange-600">Qty <span class="font-semibold text-orange-200">${it.quantity}</span></p>`;

        return `
            <div class="rounded-lg border border-orange-950/40 bg-black/25 p-3" data-df-line="${it.id}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-base font-semibold text-orange-50">${escapeHtml(it.name)}</p>
                        <p class="mt-0.5 text-xs text-orange-700">¥${escapeHtml(it.price)} each</p>
                    </div>
                    <p class="shrink-0 text-base font-bold tabular-nums text-orange-100">¥${escapeHtml(it.line_total)}</p>
                </div>
                ${ctrls}
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

    function renderOrderCard(order) {
        const editable = order.editable === true;
        let lockBanner = '';
        if (!editable && order.status === 'preparing') {
            lockBanner = `<div class="mb-3 flex items-center gap-2 rounded-xl border border-amber-700/50 bg-amber-950/40 px-3 py-2 text-xs text-amber-100">
                        <span aria-hidden="true">🔒</span>
                        <span>Order already sent to kitchen</span>
                    </div>`;
        } else if (!editable && order.status === 'completed') {
            lockBanner = `<div class="mb-3 rounded-xl border border-blue-800/50 bg-blue-950/30 px-3 py-2 text-xs text-blue-100">
                        Food served — use <strong>Checkout</strong> below when the bill is settled.
                    </div>`;
        }

        const taxPct =
            order.tax_rate != null ? String(Math.round(Number(order.tax_rate) * 1000) / 10).replace(/\.0$/, '') : '0';

        const lines = (order.items ?? []).map((it) => renderLineItem(order, it, editable)).join('');

        const totals = `
            <div class="mt-3 space-y-1 border-t border-orange-950/40 pt-3 text-xs text-orange-200">
                <div class="flex justify-between gap-2"><span>Subtotal</span><span class="tabular-nums">¥${escapeHtml(order.subtotal ?? order.total_amount)}</span></div>
                <div class="flex justify-between gap-2"><span>Tax (${taxPct}%)</span><span class="tabular-nums">¥${escapeHtml(order.tax_amount ?? '0.00')}</span></div>
                <div class="flex justify-between gap-2 text-sm font-semibold text-orange-50"><span>Total</span><span class="tabular-nums">¥${escapeHtml(order.grand_total ?? order.total_amount)}</span></div>
            </div>`;

        const addBlock = editable ? renderAddItemBlock(order) : '';

        return `
            <div class="rounded-xl border border-orange-900/40 bg-black/20 p-3" data-df-order-card="${order.id}">
                ${lockBanner}
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-mono text-xs text-orange-600">${order.order_number ? `Order ${escapeHtml(String(order.order_number))}` : `#${order.id}`}</span>
                    <span class="rounded-full bg-orange-950 px-2 py-0.5 text-[10px] font-semibold uppercase text-orange-200">${escapeHtml(order.status)}</span>
                </div>
                <div class="mt-3 space-y-3">${lines}</div>
                ${totals}
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

    async function handlePosClick(e) {
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
                mergeOrderFromResponse(data.order);
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
                mergeOrderFromResponse(data.order);
            } else if (act === 'dec') {
                const data = await fetchJson(`${urlOrdersBase}/${orderId}/items/${itemId}/decrement`, {
                    method: 'POST',
                    body: JSON.stringify({}),
                });
                mergeOrderFromResponse(data.order);
            } else if (act === 'remove') {
                const data = await fetchJson(`${urlOrdersBase}/${orderId}/items/${itemId}`, {
                    method: 'DELETE',
                });
                mergeOrderFromResponse(data.order);
            }
            scheduleRefresh(true);
        } catch (err) {
            window.alert(err instanceof Error ? err.message : 'Update failed');
        }
    }

    async function saveNotesFromTextarea(ta) {
        if (!urlOrdersBase) {
            return;
        }
        const orderId = ta.dataset.orderId;
        const itemId = ta.dataset.itemId;
        if (!orderId || !itemId) {
            return;
        }
        try {
            const data = await fetchJson(`${urlOrdersBase}/${orderId}/items/${itemId}`, {
                method: 'PATCH',
                body: JSON.stringify({ notes: ta.value }),
            });
            mergeOrderFromResponse(data.order);
            scheduleRefresh(true);
        } catch (err) {
            window.alert(err instanceof Error ? err.message : 'Update failed');
        }
    }

    async function saveSpiceFromSelect(sel) {
        if (!urlOrdersBase) {
            return;
        }
        const orderId = sel.dataset.orderId;
        const itemId = sel.dataset.itemId;
        if (!orderId || !itemId) {
            return;
        }
        try {
            const data = await fetchJson(`${urlOrdersBase}/${orderId}/items/${itemId}`, {
                method: 'PATCH',
                body: JSON.stringify({
                    options: { spice_level: sel.value === '' ? null : sel.value },
                }),
            });
            mergeOrderFromResponse(data.order);
            scheduleRefresh(true);
        } catch (err) {
            window.alert(err instanceof Error ? err.message : 'Update failed');
        }
    }

    async function saveToppingsFromInput(inp) {
        if (!urlOrdersBase) {
            return;
        }
        const orderId = inp.dataset.orderId;
        const itemId = inp.dataset.itemId;
        if (!orderId || !itemId) {
            return;
        }
        const raw = inp.value.trim();
        const toppings = raw === '' ? [] : raw.split(',').map((s) => s.trim()).filter(Boolean);
        try {
            const data = await fetchJson(`${urlOrdersBase}/${orderId}/items/${itemId}`, {
                method: 'PATCH',
                body: JSON.stringify({ options: { toppings } }),
            });
            mergeOrderFromResponse(data.order);
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

    function renderStatusButtons(activeOrders) {
        if (!statusActionsEl) {
            return;
        }
        statusActionsEl.innerHTML = '';
        const pending = activeOrders.find((o) => o.status === 'pending');
        const preparing = activeOrders.find((o) => o.status === 'preparing');
        const servingComplete = activeOrders.filter((o) => o.status === 'completed');

        const mkBtn = (label, cls, onClick) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = `rounded-xl px-3 py-2 text-xs font-semibold ${cls}`;
            b.textContent = label;
            b.addEventListener('click', onClick);
            statusActionsEl.appendChild(b);
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
        servingComplete.forEach((o) => {
            const label = o.order_number ? `Checkout #${o.order_number}` : `Checkout order #${o.id}`;
            mkBtn(label, 'bg-violet-700 text-white hover:bg-violet-600', () =>
                patchOrderStatus(o.id, 'checkout_done'),
            );
        });
        if (!pending && !preparing && servingComplete.length === 0 && activeOrders.length) {
            mkBtn('Refresh', 'border border-orange-800 text-orange-200 hover:bg-orange-950', () =>
                selectedId ? loadPanel(selectedId) : undefined,
            );
        }
    }

    async function loadPanel(tableId) {
        const data = await fetchJson(panelUrl(tableId));
        panelMenuCatalog = data.menu_catalog ?? null;
        if (drawerTitle) {
            drawerTitle.textContent = data.table?.table_name ?? `Table ${data.table?.table_number}`;
        }
        if (drawerMeta) {
            drawerMeta.textContent = `Table #${data.table?.table_number} · Seats ${data.table?.seat_capacity ?? '—'} · ${data.visual ?? ''}`;
        }
        if (activeOrdersEl) {
            const act = data.active_orders ?? [];
            activeOrdersEl.innerHTML = act.length ? act.map(renderOrderCard).join('') : '<p class="text-orange-800">No active kitchen ticket.</p>';
            renderStatusButtons(act);
        }
        if (sessionHistoryEl) {
            const sessions = data.sessions ?? [];
            sessionHistoryEl.innerHTML = sessions
                .map((sess, idx) => {
                    const sid = sess.customer_session_id ? `Session #${sess.customer_session_id}` : `Visit ${idx + 1}`;
                    const blocks = (sess.orders ?? []).map(renderOrderCard).join('');
                    return `<details class="group rounded-xl border border-orange-950/50 bg-black/10 open:bg-black/20">
                        <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-orange-200">${escapeHtml(sid)}</summary>
                        <div class="space-y-2 border-t border-orange-950/40 px-3 py-3">${blocks}</div>
                    </details>`;
                })
                .join('');
        }
        if (drawerEmpty && drawerBody) {
            drawerEmpty.classList.add('hidden');
            drawerBody.classList.remove('hidden');
            drawerBody.classList.add('flex');
        }
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
    activeOrdersEl?.addEventListener('change', (e) => {
        const sel = e.target.closest('[data-df-pos-spice]');
        if (sel) {
            void saveSpiceFromSelect(sel);
        }
    });
    activeOrdersEl?.addEventListener(
        'blur',
        (e) => {
            const ta = e.target.closest('[data-df-pos-notes]');
            if (ta) {
                void saveNotesFromTextarea(ta);
            }
            const tops = e.target.closest('[data-df-pos-toppings]');
            if (tops) {
                void saveToppingsFromInput(tops);
            }
        },
        true,
    );

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
