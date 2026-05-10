/**
 * Guest QR menu: cart, sticky bar, AJAX place order, order-panel refresh.
 */
const LS_CART = 'guest-menu-cart-lines-v1';

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

function newLineId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        try {
            return crypto.randomUUID();
        } catch {
            /* fall through */
        }
    }
    return `g-${Date.now()}-${Math.random().toString(36).slice(2, 11)}`;
}

function parseMoney(str) {
    const n = Number.parseFloat(String(str ?? '0'));
    return Number.isFinite(n) ? n : 0;
}

function currency(amount) {
    const v = Number.isFinite(amount) ? amount : 0;
    return `¥${Math.round(v).toLocaleString('ja-JP')}`;
}

function deepEq(a, b) {
    return JSON.stringify(a ?? '') === JSON.stringify(b ?? '');
}

/** @param {HTMLElement | null} el @param {string} className */
function restartAnimation(el, className) {
    if (!el || !className) {
        return;
    }
    el.classList.remove(className);
    // Reflow so repeated taps replay the animation
    void el.offsetWidth;
    el.classList.add(className);
    window.setTimeout(() => el.classList.remove(className), 650);
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('guest-menu-root');
    if (!root) {
        return;
    }

    const storeUrl = root.dataset.ordersStoreUrl ?? '';
    const summaryUrl = root.dataset.orderSummaryUrl ?? '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    /** @type {Array<{ lineId: string, menu_item_id: number, name: string, price: number, quantity: number, notes: string }>} */
    let lines = [];

    try {
        const raw = sessionStorage.getItem(LS_CART);
        if (raw) {
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                lines = parsed;
            }
        }
    } catch {
        /* ignore */
    }

    function persistCart() {
        try {
            sessionStorage.setItem(LS_CART, JSON.stringify(lines));
        } catch {
            /* ignore */
        }
    }

    function lineTotal(l) {
        return l.quantity * l.price;
    }

    function cartCount() {
        return lines.reduce((s, l) => s + l.quantity, 0);
    }

    function cartSubtotal() {
        return lines.reduce((s, l) => s + lineTotal(l), 0);
    }

    function findMerge(menuItemId, notes) {
        return lines.find((l) => Number(l.menu_item_id) === Number(menuItemId) && deepEq(l.notes, notes));
    }

    /**
     * @param {number | string} menuItemId
     * @param {{ interactive?: boolean, pulseMenuItemId?: number, pulseLineId?: string, fromDrawer?: boolean }} [anim]
     */
    function addQuantity(menuItemId, name, price, qty = 1, notes = '') {
        const p = parseMoney(price);
        const existing = findMerge(menuItemId, notes);
        if (existing) {
            existing.quantity += qty;
        } else {
            lines.push({
                lineId: newLineId(),
                menu_item_id: Number(menuItemId),
                name: String(name),
                price: p,
                quantity: qty,
                notes: notes || '',
            });
        }
        persistCart();
        render({
            interactive: true,
            pulseMenuItemId: Number(menuItemId),
        });
    }

    /**
     * @param {string} lineId
     * @param {{ pulseMenuItemId?: number, fromDrawer?: boolean }} [extra]
     */
    function setQuantity(lineId, q, extra = {}) {
        const line = lines.find((l) => l.lineId === lineId);
        if (!line) {
            return;
        }
        const menuId = extra.pulseMenuItemId ?? line.menu_item_id;
        if (q < 1) {
            lines = lines.filter((l) => l.lineId !== lineId);
        } else {
            line.quantity = q;
        }
        persistCart();
        render({
            interactive: true,
            pulseMenuItemId: menuId,
            pulseLineId: extra.fromDrawer ? lineId : undefined,
        });
    }

    function syncCardSteppers() {
        document.querySelectorAll('[data-menu-item]').forEach((card) => {
            const mid = card.getAttribute('data-id');
            const qtyInCart = lines
                .filter((l) => Number(l.menu_item_id) === Number(mid) && !l.notes)
                .reduce((s, l) => s + l.quantity, 0);
            const val = card.querySelector('[data-quantity-value]');
            const down = card.querySelector('[data-quantity-down]');
            if (val) {
                val.textContent = String(qtyInCart);
            }
            if (down) {
                down.disabled = qtyInCart === 0;
            }
        });
    }

    function buildHiddenInputs(container) {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        lines.forEach((line, index) => {
            const idInp = document.createElement('input');
            idInp.type = 'hidden';
            idInp.name = `items[${index}][menu_item_id]`;
            idInp.value = String(line.menu_item_id);
            container.appendChild(idInp);
            const qInp = document.createElement('input');
            qInp.type = 'hidden';
            qInp.name = `items[${index}][quantity]`;
            qInp.value = String(line.quantity);
            container.appendChild(qInp);
            if (line.notes?.trim()) {
                const nInp = document.createElement('input');
                nInp.type = 'hidden';
                nInp.name = `items[${index}][notes]`;
                nInp.value = line.notes.trim();
                container.appendChild(nInp);
            }
        });
    }

    /**
     * @param {{ interactive?: boolean, pulseMenuItemId?: number, pulseLineId?: string } | null} anim
     */
    function applyChromeAnimations(anim) {
        if (!anim?.interactive) {
            return;
        }

        const badge = document.getElementById('guest-cart-bar-count');
        const stickyBtn = document.getElementById('guest-open-cart');
        restartAnimation(badge, 'guest-badge-pop');
        restartAnimation(stickyBtn, 'guest-cart-bar-pulse');

        const drawerTotal = document.getElementById('guest-drawer-total');
        if (drawerTotal && document.getElementById('guest-cart-drawer')?.classList.contains('is-open')) {
            restartAnimation(drawerTotal, 'guest-qty-pop');
        }

        if (anim.pulseMenuItemId != null) {
            const card = document.querySelector(`[data-menu-item][data-id="${anim.pulseMenuItemId}"]`);
            const qtySpan = card?.querySelector('[data-quantity-value]');
            restartAnimation(qtySpan, 'guest-qty-pop');
            restartAnimation(card, 'guest-menu-card-pulse');
        }

        if (anim.pulseLineId) {
            const esc =
                typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
                    ? CSS.escape(anim.pulseLineId)
                    : anim.pulseLineId;
            const row = document.querySelector(`[data-cart-line-id="${esc}"]`);
            restartAnimation(row, 'guest-cart-row-flash');
            row?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function renderCartDrawer() {
        const linesRoot = document.getElementById('guest-drawer-lines');
        const hiddenRoot = document.getElementById('guest-cart-hidden-inputs');
        const totalEl = document.getElementById('guest-drawer-total');

        if (!linesRoot) {
            return;
        }

        linesRoot.innerHTML = '';
        buildHiddenInputs(hiddenRoot);

        if (lines.length === 0) {
            linesRoot.innerHTML =
                '<p class="rounded-2xl border border-dashed border-orange-200 bg-orange-50/60 px-3 py-8 text-center text-xs leading-relaxed text-slate-500">Tap <span class="font-bold text-orange-700">+</span> on a dish to add it here.</p>';
        } else {
            lines.forEach((line) => {
                const row = document.createElement('div');
                row.dataset.cartLineId = line.lineId;
                row.className =
                    'guest-cart-line-row rounded-2xl border border-orange-100/90 bg-white px-3 py-2.5 shadow-sm ring-1 ring-orange-50/80';
                const notesHtml = line.notes
                    ? `<p class="mt-0.5 text-[11px] leading-snug text-slate-500">${escapeHtml(line.notes)}</p>`
                    : '';
                row.innerHTML = `
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold leading-tight text-slate-900">${escapeHtml(line.name)}</p>
                            ${notesHtml}
                            <p class="mt-0.5 text-[11px] text-slate-500">${line.quantity} × ${currency(line.price)}</p>
                            <button type="button" class="mt-1.5 text-[11px] font-semibold text-orange-700 active:text-orange-900" data-note-for="${line.lineId}">${line.notes ? 'Edit note' : '+ Note'}</button>
                            <div class="mt-1.5 hidden" data-note-editor="${line.lineId}">
                                <textarea rows="2" class="w-full rounded-xl border border-orange-200 bg-orange-50/40 px-2.5 py-2 text-xs text-slate-800 placeholder:text-slate-400" placeholder="Allergies, spice…">${escapeHtml(line.notes)}</textarea>
                                <button type="button" class="mt-1.5 text-[11px] font-semibold text-orange-800" data-note-save="${line.lineId}">Save</button>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1.5">
                            <p class="text-sm font-bold tabular-nums text-slate-950">${currency(lineTotal(line))}</p>
                            <div class="guest-cart-row-stepper flex items-center gap-1.5 rounded-2xl bg-orange-50/90 px-1 py-1 ring-1 ring-orange-100/90">
                                <button type="button" class="guest-qty-btn-compact" data-dec="${line.lineId}" aria-label="Decrease">−</button>
                                <span class="guest-drawer-qty guest-qty-display min-w-[1.5rem] text-center text-sm font-bold tabular-nums text-slate-900" data-drawer-qty="${line.lineId}">${line.quantity}</span>
                                <button type="button" class="guest-qty-btn-compact" data-inc="${line.lineId}" aria-label="Increase">+</button>
                            </div>
                        </div>
                    </div>
                `;
                linesRoot.appendChild(row);
            });
        }

        if (totalEl) {
            totalEl.textContent = currency(cartSubtotal());
        }

        const barCount = document.getElementById('guest-cart-bar-count');
        const barTotal = document.getElementById('guest-cart-bar-total');
        if (barCount) {
            barCount.textContent = String(cartCount());
        }
        if (barTotal) {
            barTotal.textContent = currency(cartSubtotal());
        }

        const submitBtn = document.getElementById('guest-place-order-btn');
        const has = lines.length > 0;
        if (submitBtn) {
            submitBtn.disabled = !has;
            submitBtn.classList.toggle('opacity-40', !has);
            submitBtn.classList.toggle('pointer-events-none', !has);
        }

        syncCardSteppers();
    }

    /**
     * @param {{ interactive?: boolean, pulseMenuItemId?: number, pulseLineId?: string } | null} anim
     */
    function render(anim = null) {
        renderCartDrawer();
        applyChromeAnimations(anim);
    }

    async function refreshOrderPanel() {
        if (!summaryUrl) {
            return;
        }
        const url = summaryUrl.includes('?') ? `${summaryUrl}&partial=1` : `${summaryUrl}?partial=1`;
        try {
            const r = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!r.ok) {
                return;
            }
            const html = await r.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const next = doc.getElementById('guest-order-panel');
            const cur = document.getElementById('guest-order-panel');
            if (next && cur) {
                cur.replaceWith(next);
            }
        } catch {
            /* ignore */
        }
    }

    function showOrderSuccess() {
        const el = document.getElementById('guest-order-success');
        if (!el) {
            return;
        }
        el.classList.remove('pointer-events-none', 'opacity-0');
        el.classList.add('opacity-100');
        el.setAttribute('aria-hidden', 'false');
        window.setTimeout(() => {
            el.classList.add('pointer-events-none', 'opacity-0');
            el.classList.remove('opacity-100');
            el.setAttribute('aria-hidden', 'true');
        }, 2200);
    }

    async function submitOrder() {
        if (!storeUrl || lines.length === 0) {
            return;
        }
        const payload = {
            items: lines.map((l) => {
                const row = {
                    menu_item_id: l.menu_item_id,
                    quantity: l.quantity,
                };
                if (l.notes?.trim()) {
                    row.notes = l.notes.trim();
                }
                return row;
            }),
        };
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
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                window.alert(data.message || 'Could not place order.');
                return;
            }
            lines = [];
            persistCart();
            render({ interactive: true });
            showOrderSuccess();
            await refreshOrderPanel();
            closeDrawer();
        } catch {
            window.alert('Network error. Try again.');
        }
    }

    function openDrawer() {
        document.getElementById('guest-cart-drawer')?.classList.add('is-open');
        document.getElementById('guest-mobile-overlay')?.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeDrawer() {
        document.getElementById('guest-cart-drawer')?.classList.remove('is-open');
        document.getElementById('guest-mobile-overlay')?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    document.getElementById('guest-cart-lines-scroll')?.addEventListener('click', (e) => {
        const dec = e.target.closest('[data-dec]');
        const inc = e.target.closest('[data-inc]');
        const noteFor = e.target.closest('[data-note-for]');
        const noteSave = e.target.closest('[data-note-save]');

        if (dec) {
            const id = dec.getAttribute('data-dec');
            const line = lines.find((l) => l.lineId === id);
            if (line) {
                setQuantity(id, line.quantity - 1, { fromDrawer: true, pulseMenuItemId: line.menu_item_id });
            }
            return;
        }
        if (inc) {
            const id = inc.getAttribute('data-inc');
            const line = lines.find((l) => l.lineId === id);
            if (line) {
                setQuantity(id, line.quantity + 1, { fromDrawer: true, pulseMenuItemId: line.menu_item_id });
            }
            return;
        }
        if (noteFor) {
            const id = noteFor.getAttribute('data-note-for');
            const ne =
                typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
                    ? CSS.escape(id ?? '')
                    : id ?? '';
            document.querySelector(`[data-note-editor="${ne}"]`)?.classList.toggle('hidden');
            return;
        }
        if (noteSave) {
            const id = noteSave.getAttribute('data-note-save');
            const line = lines.find((l) => l.lineId === id);
            const sid =
                typeof CSS !== 'undefined' && typeof CSS.escape === 'function'
                    ? CSS.escape(id ?? '')
                    : id ?? '';
            const ta = document.querySelector(`[data-note-editor="${sid}"] textarea`);
            if (line && ta) {
                line.notes = ta.value.trim();
                persistCart();
                render();
            }
        }
    });

    document.querySelectorAll('[data-menu-item]').forEach((card) => {
        card.querySelector('[data-quantity-up]')?.addEventListener('click', () => {
            addQuantity(card.dataset.id, card.dataset.name, card.dataset.price, 1, '');
        });
        card.querySelector('[data-quantity-down]')?.addEventListener('click', () => {
            const mid = card.dataset.id;
            const withNotes = lines.filter((l) => Number(l.menu_item_id) === Number(mid) && l.notes);
            if (withNotes.length) {
                openDrawer();
                return;
            }
            const simple = lines.find((l) => Number(l.menu_item_id) === Number(mid) && !l.notes);
            if (simple) {
                setQuantity(simple.lineId, simple.quantity - 1, { pulseMenuItemId: Number(mid) });
            }
        });
    });

    document.getElementById('guest-open-cart')?.addEventListener('click', openDrawer);
    document.getElementById('guest-close-cart')?.addEventListener('click', closeDrawer);
    document.getElementById('guest-mobile-overlay')?.addEventListener('click', closeDrawer);
    document.getElementById('guest-place-order-btn')?.addEventListener('click', submitOrder);

    document.addEventListener('click', (e) => {
        const wrap = e.target.closest('[data-reorder-id]');
        if (!wrap) {
            return;
        }
        const id = wrap.dataset.reorderId;
        const name = wrap.dataset.reorderName;
        const price = wrap.dataset.reorderPrice;
        if (e.target.closest('[data-reorder-decrease]')) {
            const simple = lines.find((l) => Number(l.menu_item_id) === Number(id) && !l.notes);
            if (simple) {
                setQuantity(simple.lineId, simple.quantity - 1, { pulseMenuItemId: Number(id) });
            }
        }
        if (e.target.closest('[data-reorder-amount]')) {
            const amt = Number(e.target.closest('[data-reorder-amount]')?.getAttribute('data-reorder-amount') || 1);
            addQuantity(id, name, price, amt, '');
        }
    });

    const tabs = document.getElementById('guest-category-tabs');
    if (tabs) {
        tabs.querySelectorAll('[data-scroll-category]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-scroll-category');
                const target = document.getElementById(`category-${id}`);
                target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                tabs.querySelectorAll('[data-scroll-category]').forEach((b) => {
                    b.classList.toggle('guest-tab-active', b === btn);
                });
            });
        });
    }

    render();

    window.__guestRefreshOrderPanel = refreshOrderPanel;
});
