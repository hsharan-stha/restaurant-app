/**
 * Staff POS ordering (/orders/create): cart, table selection, AJAX place order.
 */
const LS_DRAFT = 'staff-pos-draft-v1';
const LS_POPULAR = 'staff-pos-popular-v1';
const LS_RECENT = 'staff-pos-recent-v1';
const LS_LAST_ORDER = 'staff-pos-last-order-id';

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

function parseMoney(str) {
    const n = Number.parseFloat(String(str ?? '0'));
    return Number.isFinite(n) ? n : 0;
}

function formatMoney(n) {
    const v = Number.isFinite(n) ? n : 0;
    return `¥${v.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

function categoryEmoji(name) {
    const n = (name ?? '').toLowerCase();
    if (/pizza/.test(n)) return '🍕';
    if (/burger|sandwich/.test(n)) return '🍔';
    if (/drink|beverage|coffee|tea|juice|wine|beer/.test(n)) return '🥤';
    if (/dessert|sweet|cake|ice/.test(n)) return '🍰';
    if (/momo|dumpling/.test(n)) return '🥟';
    if (/coffee/.test(n)) return '☕';
    if (/salad/.test(n)) return '🥗';
    if (/chicken|meat|grill|bbq/.test(n)) return '🍗';
    return '🍽️';
}

function vegBadge(veg) {
    if (veg === true) {
        return '<span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded border border-green-600/60 bg-green-500/15 text-[9px] font-bold text-green-400" title="Vegetarian">V</span>';
    }
    if (veg === false) {
        return '<span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded border border-red-600/60 bg-red-500/15 text-[9px] font-bold text-red-400" title="Non-veg">N</span>';
    }
    return '<span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded border border-slate-700 text-[9px] text-slate-500" title="—">—</span>';
}

function tableStatusClasses(status) {
    if (status === 'available') return 'border-emerald-500/50 bg-emerald-950/40 text-emerald-200 ring-emerald-500/30';
    if (status === 'occupied') return 'border-red-500/50 bg-red-950/40 text-red-200 ring-red-500/30';
    if (status === 'reserved') return 'border-amber-400/50 bg-amber-950/40 text-amber-200 ring-amber-400/30';
    return 'border-slate-600 bg-slate-900 text-slate-200';
}

function loadJson(key, fallback) {
    try {
        const raw = localStorage.getItem(key);
        if (!raw) return fallback;
        return JSON.parse(raw);
    } catch {
        return fallback;
    }
}

function saveJson(key, val) {
    try {
        localStorage.setItem(key, JSON.stringify(val));
    } catch {
        /* ignore */
    }
}

function bumpPopular(menuItemId) {
    const o = loadJson(LS_POPULAR, {});
    const id = String(menuItemId);
    o[id] = (o[id] ?? 0) + 1;
    saveJson(LS_POPULAR, o);
}

function pushRecent(menuItemId) {
    const id = Number(menuItemId);
    let arr = loadJson(LS_RECENT, []);
    if (!Array.isArray(arr)) arr = [];
    arr = arr.filter((x) => x !== id);
    arr.unshift(id);
    saveJson(LS_RECENT, arr.slice(0, 30));
}

function popularScore(id) {
    const o = loadJson(LS_POPULAR, {});
    return o[String(id)] ?? 0;
}

function deepEqualObj(a, b) {
    return JSON.stringify(a ?? null) === JSON.stringify(b ?? null);
}

/**
 * `crypto.randomUUID()` is not available (or throws) on non-secure origins
 * (e.g. http://192.168.x.x — only https and http://localhost are "secure").
 */
function newLineId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        try {
            return crypto.randomUUID();
        } catch {
            /* fall through */
        }
    }
    return `l-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}

function init() {
    const root = document.getElementById('staff-pos-root');
    if (!root) return;

    const catalogEl = document.getElementById('staff-pos-catalog');
    const tablesEl = document.getElementById('staff-pos-tables');
    if (!catalogEl || !tablesEl) return;

    /** @type {{ categories: Array<{ id:number, name:string, items: Array<any> }> }} */
    let catalog;
    let tables;
    try {
        catalog = JSON.parse(catalogEl.textContent || '{}');
        tables = JSON.parse(tablesEl.textContent || '[]');
    } catch (e) {
        console.error('[staff-pos]', e);
        const toast = document.getElementById('staff-pos-toast');
        if (toast) {
            toast.textContent = 'Could not load menu data. Refresh the page.';
            toast.classList.remove('hidden');
            toast.className =
                'mb-1.5 rounded border border-red-500/40 bg-red-950/60 px-2 py-1 text-[11px] text-red-200';
        }
        return;
    }

    const storeUrl = root.dataset.storeUrl || '';
    const ordersBase = (root.dataset.ordersBase || '').replace(/\/$/, '');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const flatItems = [];
    for (const c of catalog.categories ?? []) {
        for (const it of c.items ?? []) {
            flatItems.push({
                ...it,
                category_id: c.id,
                category_name: c.name,
                priceNum: parseMoney(it.price),
            });
        }
    }

    let selectedTableId = '';
    let activeCategoryId = catalog.categories?.[0]?.id ?? null;
    let filterMode = 'all';
    let searchQuery = '';
    /** @type {Array<{ lineId:string, menu_item_id:number, name:string, price:number, quantity:number, notes:string, options:any }>} */
    let cart = [];
    let lastPlacedOrderId = Number(localStorage.getItem(LS_LAST_ORDER) || '') || null;

    /** Drawer state */
    let drawerItem = null;
    let drawerNotes = '';
    let drawerSpice = '';
    let drawerQty = 1;

    const elTableSelect = document.getElementById('staff-pos-table-select');
    const elTableChips = document.getElementById('staff-pos-table-chips');
    const elSessionHint = document.getElementById('staff-pos-session-hint');
    const elSearch = document.getElementById('staff-pos-search');
    const elCategories = document.getElementById('staff-pos-categories');
    const elItems = document.getElementById('staff-pos-items');
    const elMenuHeading = document.getElementById('staff-pos-menu-heading');
    const elItemCount = document.getElementById('staff-pos-item-count');
    const elCartLines = document.getElementById('staff-pos-cart-lines');
    const elCartTableLabel = document.getElementById('staff-pos-cart-table-label');
    const elSubtotal = document.getElementById('staff-pos-subtotal');
    const elToast = document.getElementById('staff-pos-toast');
    const elDrawer = document.getElementById('staff-pos-drawer');
    const elDrawerOverlay = document.getElementById('staff-pos-drawer-overlay');
    const elDrawerTitle = document.getElementById('staff-pos-drawer-title');
    const elDrawerBody = document.getElementById('staff-pos-drawer-body');
    const elDrawerAdd = document.getElementById('staff-pos-drawer-add');

    function showToast(message, kind = 'info') {
        if (!elToast) return;
        elToast.textContent = message;
        elToast.classList.remove('hidden');
        elToast.className =
            'mb-1.5 rounded border px-2 py-1 text-[11px] ' +
            (kind === 'error'
                ? 'border-red-500/40 bg-red-950/60 text-red-200'
                : kind === 'success'
                  ? 'border-emerald-500/40 bg-emerald-950/60 text-emerald-200'
                  : 'border-slate-700 bg-slate-900 text-slate-300');
        window.clearTimeout(showToast._t);
        showToast._t = window.setTimeout(() => elToast.classList.add('hidden'), 4200);
    }

    function openDrawer(item, opts = {}) {
        drawerItem = item;
        drawerNotes = opts.notes ?? '';
        const opt = opts.options && typeof opts.options === 'object' ? opts.options : {};
        drawerSpice = opt.spice_level ?? '';
        drawerQty = Math.max(1, opts.quantity ?? 1);
        if (elDrawerTitle) elDrawerTitle.textContent = item.name;
        renderDrawerBody();
        if (elDrawer) {
            elDrawer.classList.remove('translate-x-full');
            elDrawer.setAttribute('aria-hidden', 'false');
        }
        if (elDrawerOverlay) {
            elDrawerOverlay.classList.remove('hidden');
        }
    }

    function closeDrawer() {
        drawerItem = null;
        if (elDrawer) {
            elDrawer.classList.add('translate-x-full');
            elDrawer.setAttribute('aria-hidden', 'true');
        }
        if (elDrawerOverlay) elDrawerOverlay.classList.add('hidden');
    }

    function renderDrawerBody() {
        if (!elDrawerBody || !drawerItem) return;
        const img = drawerItem.image_url
            ? `<img src="${escapeHtml(drawerItem.image_url)}" alt="" class="mb-2 aspect-video max-h-[8rem] w-full rounded-md object-cover" loading="lazy" />`
            : `<div class="mb-2 flex aspect-video max-h-[8rem] w-full items-center justify-center rounded-md bg-slate-900 text-2xl text-slate-600">🍽️</div>`;
        elDrawerBody.innerHTML = `
            ${img}
            <p class="mb-2 font-mono text-base font-semibold text-emerald-400">${escapeHtml(formatMoney(drawerItem.priceNum))}</p>
            <label class="mb-1 block text-[11px] font-medium text-slate-400">Qty</label>
            <div class="mb-3 flex items-center gap-2">
                <button type="button" data-drawer-qty="-1" class="h-8 w-8 rounded-md border border-slate-700 bg-slate-900 text-lg font-semibold leading-none text-white hover:bg-slate-800">−</button>
                <span id="staff-pos-drawer-qty-display" class="min-w-[1.75rem] text-center font-mono text-sm font-semibold">${drawerQty}</span>
                <button type="button" data-drawer-qty="1" class="h-8 w-8 rounded-md border border-slate-700 bg-slate-900 text-lg font-semibold leading-none text-white hover:bg-slate-800">+</button>
            </div>
            <label class="mb-1 block text-[11px] font-medium text-slate-400">Spice</label>
            <select id="staff-pos-drawer-spice" class="mb-2 h-8 w-full rounded-md border border-slate-700 bg-slate-900 px-2 text-xs text-white">
                <option value="">Default</option>
                <option value="mild">Mild</option>
                <option value="medium">Medium</option>
                <option value="hot">Hot</option>
                <option value="extra_hot">Extra hot</option>
            </select>
            <label class="mb-1 block text-[11px] font-medium text-slate-400">Notes</label>
            <textarea id="staff-pos-drawer-notes" rows="2" class="w-full rounded-md border border-slate-700 bg-slate-900 px-2 py-1.5 text-xs text-white placeholder:text-slate-600" placeholder="Mods…">${escapeHtml(drawerNotes)}</textarea>
        `;
        const spiceSel = document.getElementById('staff-pos-drawer-spice');
        if (spiceSel) spiceSel.value = drawerSpice;
        spiceSel?.addEventListener('change', () => {
            drawerSpice = spiceSel.value;
        });
        const notesTa = document.getElementById('staff-pos-drawer-notes');
        notesTa?.addEventListener('input', () => {
            drawerNotes = notesTa.value;
        });
        elDrawerBody.querySelectorAll('[data-drawer-qty]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const d = Number(btn.getAttribute('data-drawer-qty'));
                drawerQty = Math.max(1, drawerQty + d);
                const disp = document.getElementById('staff-pos-drawer-qty-display');
                if (disp) disp.textContent = String(drawerQty);
            });
        });
    }

    function buildOptions() {
        const o = {};
        if (drawerSpice) o.spice_level = drawerSpice;
        return Object.keys(o).length ? o : null;
    }

    function addOrMergeLine(item, qty, notes, options) {
        const same = cart.find(
            (l) =>
                Number(l.menu_item_id) === Number(item.id) &&
                (l.notes || '') === (notes || '') &&
                deepEqualObj(l.options, options)
        );
        if (same) {
            same.quantity += qty;
        } else {
            cart.push({
                lineId: newLineId(),
                menu_item_id: Number(item.id),
                name: item.name,
                price: item.priceNum,
                quantity: qty,
                notes: notes || '',
                options,
            });
        }
        renderCart();
    }

    function populateTables() {
        if (!elTableSelect || !elTableChips) return;
        elTableSelect.innerHTML = '<option value="">Select table…</option>';
        for (const t of tables) {
            const opt = document.createElement('option');
            opt.value = String(t.id);
            opt.textContent = `${t.label} — ${t.status}${t.has_active_session ? ' · session' : ''}`;
            elTableSelect.appendChild(opt);
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.dataset.tableId = String(t.id);
            chip.className = `min-h-[2.25rem] rounded-md border px-2 py-1 text-left text-[11px] font-medium leading-tight ring-1 transition hover:brightness-110 ${tableStatusClasses(t.status)}`;
            chip.innerHTML = `<span class="block truncate font-semibold">${escapeHtml(t.label)}</span><span class="text-[10px] opacity-85">${escapeHtml(t.status)}${t.has_active_session ? ' · ses' : ''}</span>`;
            chip.addEventListener('click', () => {
                elTableSelect.value = String(t.id);
                elTableSelect.dispatchEvent(new Event('change'));
            });
            elTableChips.appendChild(chip);
        }
    }

    function updateSessionHint() {
        if (!elSessionHint) return;
        const t = tables.find((x) => String(x.id) === String(selectedTableId));
        if (!t || !selectedTableId) {
            elSessionHint.classList.add('hidden');
            return;
        }
        elSessionHint.classList.remove('hidden');
        if (t.has_active_session) {
            elSessionHint.textContent = `This table has an active guest session (session #${t.session_id ?? '—'}). New items append to the pending ticket when applicable.`;
        } else {
            elSessionHint.textContent = 'No active guest session. Staff orders still create or append pending orders for this table.';
        }
    }

    function updateCartTableLabel() {
        if (!elCartTableLabel) return;
        const t = tables.find((x) => String(x.id) === String(selectedTableId));
        elCartTableLabel.textContent = t ? `${t.label} · ${t.status}` : 'No table selected';
    }

    function renderCategories() {
        if (!elCategories) return;
        elCategories.innerHTML = '';
        for (const c of catalog.categories ?? []) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.dataset.catId = String(c.id);
            const active = String(c.id) === String(activeCategoryId);
            btn.className = active
                ? 'flex min-h-[2rem] shrink-0 items-center gap-1.5 rounded-md border border-emerald-500/60 bg-emerald-700/70 px-2 py-1.5 text-left text-[11px] font-semibold text-white lg:w-full'
                : 'flex min-h-[2rem] shrink-0 items-center gap-1.5 rounded-md border border-slate-800 bg-slate-900 px-2 py-1.5 text-left text-[11px] font-medium text-slate-300 hover:bg-slate-800 lg:w-full';
            btn.innerHTML = `<span class="text-sm leading-none opacity-95">${categoryEmoji(c.name)}</span><span class="truncate">${escapeHtml(c.name)}</span>`;
            btn.addEventListener('click', () => {
                activeCategoryId = c.id;
                renderCategories();
                renderItems();
            });
            elCategories.appendChild(btn);
        }
    }

    function getVisibleItems() {
        const q = searchQuery.trim().toLowerCase();
        let pool = flatItems;
        if (q) {
            pool = flatItems.filter((it) => it.name.toLowerCase().includes(q));
        } else if (activeCategoryId != null) {
            pool = flatItems.filter((it) => String(it.category_id) === String(activeCategoryId));
        }

        if (!q && filterMode === 'popular') {
            pool = [...pool].sort((a, b) => popularScore(b.id) - popularScore(a.id));
        } else if (!q && filterMode === 'recent') {
            const recent = loadJson(LS_RECENT, []);
            const set = new Set(recent.map(Number));
            pool = pool.filter((it) => set.has(it.id));
            pool = [...pool].sort((a, b) => recent.indexOf(a.id) - recent.indexOf(b.id));
        }

        return pool;
    }

    function renderItems() {
        if (!elItems) return;
        const list = getVisibleItems();
        const cat = catalog.categories?.find((c) => String(c.id) === String(activeCategoryId));
        if (elMenuHeading) {
            const q = searchQuery.trim();
            elMenuHeading.textContent = q ? `Search: “${q}”` : cat ? cat.name : 'Menu';
        }
        if (elItemCount) elItemCount.textContent = `${list.length} items`;

        elItems.innerHTML = '';
        if (!flatItems.length) {
            elItems.innerHTML =
                '<div class="col-span-full rounded-md border border-amber-600/35 bg-amber-950/25 p-4 text-center text-[11px] text-amber-100/95"><p class="font-semibold">No menu items</p><p class="mt-1 text-amber-200/75">Configure menu in admin.</p></div>';
            return;
        }
        if (!list.length) {
            elItems.innerHTML =
                '<div class="col-span-full rounded-md border border-slate-800 bg-slate-900/70 p-4 text-center text-[11px] text-slate-400"><p>No matches</p><p class="mt-1 text-[10px] text-slate-500">Change category or clear search.</p></div>';
            return;
        }
        for (const it of list) {
            const card = document.createElement('article');
            card.className =
                'group flex flex-col overflow-hidden rounded-md border border-slate-800/90 bg-slate-900/60 transition hover:border-slate-600 hover:bg-slate-900/85 active:scale-[0.99]';
            const imgUrl = it.image_url;
            const imgBlock = imgUrl
                ? `<img src="${escapeHtml(imgUrl)}" alt="" class="h-[4.25rem] w-full object-cover" loading="lazy" />`
                : `<div class="flex h-[4.25rem] w-full items-center justify-center bg-slate-900 text-xl text-slate-600">🍽️</div>`;
            card.innerHTML = `
                <button type="button" class="staff-pos-quick-add block w-full text-left" data-item-id="${it.id}" aria-label="Add to cart">
                    ${imgBlock}
                    <div class="flex flex-1 flex-col gap-0.5 p-2">
                        <div class="flex items-start justify-between gap-1">
                            <h3 class="line-clamp-2 min-h-[2rem] text-[11px] font-semibold leading-snug text-slate-100">${escapeHtml(it.name)}</h3>
                            ${vegBadge(it.veg)}
                        </div>
                        <div class="flex items-center justify-between gap-1 pt-0.5">
                            <span class="font-mono text-xs font-semibold text-emerald-400/95">${escapeHtml(formatMoney(it.priceNum))}</span>
                            <span class="rounded border border-emerald-800/50 bg-emerald-950/40 px-1 py-px text-[9px] font-medium uppercase tracking-wide text-emerald-400/90">Sell</span>
                        </div>
                    </div>
                </button>
                <div class="border-t border-slate-800/80 px-2 pb-2 pt-px">
                    <button type="button" class="staff-pos-custom h-7 w-full rounded border border-slate-700 bg-slate-950 text-[10px] font-medium text-slate-300 hover:bg-slate-900" data-item-id="${it.id}">More</button>
                </div>
            `;
            elItems.appendChild(card);
        }

        elItems.querySelectorAll('.staff-pos-quick-add').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = Number(btn.getAttribute('data-item-id'));
                const item = flatItems.find((x) => Number(x.id) === id);
                if (!item) return;
                bumpPopular(item.id);
                pushRecent(item.id);
                addOrMergeLine(item, 1, '', null);
                cardPulse(btn.closest('article'));
            });
        });
        elItems.querySelectorAll('.staff-pos-custom').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = Number(btn.getAttribute('data-item-id'));
                const item = flatItems.find((x) => Number(x.id) === id);
                if (!item) return;
                openDrawer(item);
            });
        });
    }

    function cardPulse(article) {
        if (!article) return;
        article.classList.add('ring-2', 'ring-emerald-400');
        window.setTimeout(() => article.classList.remove('ring-2', 'ring-emerald-400'), 280);
    }

    function renderCart() {
        if (!elCartLines || !elSubtotal) return;
        const sub = cart.reduce((s, l) => s + l.price * l.quantity, 0);
        elSubtotal.textContent = formatMoney(sub);
        if (!cart.length) {
            elCartLines.innerHTML =
                '<p class="py-6 text-center text-[11px] text-slate-500">Empty · tap item</p>';
            return;
        }
        elCartLines.innerHTML = '';
        for (const line of cart) {
            const wrap = document.createElement('div');
            wrap.className =
                'rounded-md border border-slate-800/90 bg-slate-950/80 p-1.5';
            const opts = line.options && Object.keys(line.options).length ? JSON.stringify(line.options) : '';
            const notesHtml =
                line.notes || opts
                    ? `<p class="mt-0.5 text-[10px] leading-snug text-slate-500">${escapeHtml(line.notes || '')}${opts ? `<span class="block text-slate-600">${escapeHtml(opts)}</span>` : ''}</p>`
                    : '';
            wrap.innerHTML = `
                <div class="flex items-start justify-between gap-1.5">
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[11px] font-medium text-slate-100">${escapeHtml(line.name)}</div>
                        ${notesHtml}
                    </div>
                    <div class="shrink-0 text-right font-mono text-[11px] font-medium text-emerald-400/95">${escapeHtml(formatMoney(line.price * line.quantity))}</div>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                    <div class="inline-flex items-center gap-px rounded-md border border-slate-700 bg-slate-900 p-px">
                        <button type="button" data-line-dec="${line.lineId}" class="h-7 min-w-[1.75rem] rounded px-2 text-base font-semibold leading-none text-white hover:bg-slate-800">−</button>
                        <span class="min-w-[1.5rem] text-center font-mono text-[11px] font-semibold tabular-nums">${line.quantity}</span>
                        <button type="button" data-line-inc="${line.lineId}" class="h-7 min-w-[1.75rem] rounded px-2 text-base font-semibold leading-none text-white hover:bg-slate-800">+</button>
                    </div>
                    <button type="button" data-line-remove="${line.lineId}" class="rounded border border-red-900/50 bg-red-950/40 px-2 py-1 text-[10px] font-medium text-red-300 hover:bg-red-950/70">×</button>
                </div>
            `;
            elCartLines.appendChild(wrap);
        }

        elCartLines.querySelectorAll('[data-line-inc]').forEach((b) => {
            b.addEventListener('click', () => {
                const id = b.getAttribute('data-line-inc');
                const line = cart.find((l) => l.lineId === id);
                if (line) line.quantity += 1;
                renderCart();
            });
        });
        elCartLines.querySelectorAll('[data-line-dec]').forEach((b) => {
            b.addEventListener('click', () => {
                const id = b.getAttribute('data-line-dec');
                const line = cart.find((l) => l.lineId === id);
                if (!line) return;
                line.quantity -= 1;
                if (line.quantity < 1) cart = cart.filter((l) => l.lineId !== id);
                renderCart();
            });
        });
        elCartLines.querySelectorAll('[data-line-remove]').forEach((b) => {
            b.addEventListener('click', () => {
                const id = b.getAttribute('data-line-remove');
                cart = cart.filter((l) => l.lineId !== id);
                renderCart();
            });
        });
    }

    function collectPayload() {
        return {
            table_id: Number(selectedTableId),
            items: cart.map((l) => {
                const row = {
                    menu_item_id: l.menu_item_id,
                    quantity: l.quantity,
                };
                if (l.notes?.trim()) row.notes = l.notes.trim();
                if (l.options && Object.keys(l.options).length) row.options = l.options;
                return row;
            }),
        };
    }

    async function placeOrder() {
        if (!selectedTableId) {
            showToast('Select a table first.', 'error');
            return;
        }
        if (!cart.length) {
            showToast('Add at least one item.', 'error');
            return;
        }
        const body = collectPayload();
        try {
            const res = await fetch(storeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                showToast(data.message || `Order failed (${res.status})`, 'error');
                return;
            }
            lastPlacedOrderId = data.order?.id ?? null;
            if (lastPlacedOrderId) {
                try {
                    localStorage.setItem(LS_LAST_ORDER, String(lastPlacedOrderId));
                } catch {
                    /* ignore */
                }
            }
            showToast(data.message || 'Order placed.', 'success');
            cart = [];
            renderCart();
        } catch {
            showToast('Network error.', 'error');
        }
    }

    async function sendToKitchen() {
        const oid = lastPlacedOrderId;
        if (!oid) {
            showToast('Place an order first, then send to kitchen.', 'error');
            return;
        }
        const url = `${ordersBase}/${oid}/status`;
        try {
            const res = await fetch(url, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ status: 'preparing' }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                showToast(data.message || `Could not update (${res.status})`, 'error');
                return;
            }
            showToast('Sent to kitchen (preparing).', 'success');
        } catch {
            showToast('Network error.', 'error');
        }
    }

    function openCheckout() {
        const oid = lastPlacedOrderId;
        if (!oid) {
            showToast('Place an order first to open its page.', 'error');
            return;
        }
        window.location.href = `${ordersBase}/${oid}`;
    }

    function saveDraft() {
        const payload = {
            table_id: selectedTableId,
            cart,
            at: Date.now(),
        };
        saveJson(LS_DRAFT, payload);
        showToast('Draft saved on this device.', 'success');
    }

    function loadDraft() {
        const d = loadJson(LS_DRAFT, null);
        if (!d || !d.cart) {
            showToast('No draft found.', 'error');
            return;
        }
        if (d.table_id) {
            selectedTableId = String(d.table_id);
            if (elTableSelect) elTableSelect.value = selectedTableId;
        }
        cart = Array.isArray(d.cart) ? d.cart : [];
        updateCartTableLabel();
        updateSessionHint();
        renderCart();
        showToast('Draft loaded.', 'success');
    }

    /* Filter buttons */
    document.querySelectorAll('.pos-filter-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            filterMode = btn.getAttribute('data-pos-filter') || 'all';
            document.querySelectorAll('.pos-filter-btn').forEach((b) => {
                const on = b.getAttribute('data-pos-filter') === filterMode;
                b.className = on
                    ? 'pos-filter-btn rounded border border-emerald-600 bg-emerald-900/60 px-2 py-0.5 text-[11px] font-medium text-emerald-100'
                    : 'pos-filter-btn rounded border border-slate-700 bg-slate-900 px-2 py-0.5 text-[11px] font-medium text-slate-300 hover:bg-slate-800';
            });
            renderItems();
        });
    });

    elTableSelect?.addEventListener('change', () => {
        selectedTableId = elTableSelect.value;
        updateCartTableLabel();
        updateSessionHint();
    });

    elSearch?.addEventListener('input', () => {
        searchQuery = elSearch.value || '';
        renderItems();
    });

    document.getElementById('staff-pos-place')?.addEventListener('click', placeOrder);
    document.getElementById('staff-pos-kitchen')?.addEventListener('click', sendToKitchen);
    document.getElementById('staff-pos-checkout')?.addEventListener('click', openCheckout);
    document.getElementById('staff-pos-save-draft')?.addEventListener('click', saveDraft);
    document.getElementById('staff-pos-load-draft')?.addEventListener('click', loadDraft);

    document.getElementById('staff-pos-drawer-close')?.addEventListener('click', closeDrawer);
    elDrawerOverlay?.addEventListener('click', closeDrawer);

    elDrawerAdd?.addEventListener('click', () => {
        if (!drawerItem) return;
        bumpPopular(drawerItem.id);
        pushRecent(drawerItem.id);
        const opts = buildOptions();
        addOrMergeLine(drawerItem, drawerQty, drawerNotes, opts);
        closeDrawer();
        showToast('Added to cart.', 'success');
    });

    populateTables();
    renderCategories();
    renderItems();
    renderCart();
    updateCartTableLabel();
    updateSessionHint();
}

document.addEventListener('DOMContentLoaded', init);
