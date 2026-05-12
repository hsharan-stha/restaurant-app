import './bootstrap';
import axios from 'axios';

/** @param {string} s */
function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

/** @param {string} tpl @param {number|string} id */
function urlId(tpl, id) {
    return tpl.replace(/__ID__/g, String(id));
}

/** @param {HTMLElement} host @param {string} message @param {'ok'|'err'} variant */
function toast(host, message, variant = 'ok') {
    if (!host) {
        return;
    }
    const el = document.createElement('div');
    el.className = `pointer-events-auto catalog-toast-enter rounded border px-3 py-2 text-xs shadow-lg ${
        variant === 'err'
            ? 'border-red-700/60 bg-red-950/90 text-red-100'
            : 'border-emerald-700/60 bg-emerald-950/90 text-emerald-100'
    }`;
    el.textContent = message;
    host.appendChild(el);
    window.requestAnimationFrame(() => {
        el.classList.add('catalog-toast-active');
    });
    window.setTimeout(() => {
        el.classList.remove('catalog-toast-active');
        el.style.opacity = '0';
        window.setTimeout(() => el.remove(), 200);
    }, 3200);
}

function debounce(fn, ms) {
    let t = 0;
    return (...args) => {
        window.clearTimeout(t);
        t = window.setTimeout(() => fn(...args), ms);
    };
}

function catalogCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

axios.defaults.headers.common['X-CSRF-TOKEN'] = catalogCsrf();

// ——— Categories ———

function initCategories(root) {
    const listUrl = root.dataset.urlList;
    const storeUrl = root.dataset.urlStore;
    const patchTpl = root.dataset.urlPatch;
    const toggleTpl = root.dataset.urlToggle;
    const deleteTpl = root.dataset.urlDelete;
    const tbody = root.querySelector('[data-cc-tbody]');
    const emptyEl = root.querySelector('[data-cc-empty]');
    const qInput = root.querySelector('[data-cc-q]');
    const form = root.querySelector('[data-cc-form]');
    const toastHost = root.querySelector('[data-catalog-toast-host]');
    const panelTitle = root.querySelector('[data-cc-panel-title]');
    const panelSub = root.querySelector('[data-cc-panel-sub]');
    const btnNew = root.querySelector('[data-cc-new]');
    const btnClear = root.querySelector('[data-cc-clear]');

    let categories = [];
    let selectedId = null;

    function clearForm(isNew = true) {
        if (!form) {
            return;
        }
        /** @type HTMLFormElement */
        const f = form;
        f.reset();
        const hid = f.querySelector('[data-cc-id]');
        if (hid) {
            hid.value = '';
        }
        const active = f.querySelector('input[name="is_active"]');
        if (active) {
            /** @type HTMLInputElement */
            (active).checked = true;
        }
        const kitchen = f.querySelector('input[name="is_kitchen"]');
        if (kitchen) {
            /** @type HTMLInputElement */
            (kitchen).checked = false;
        }
        selectedId = null;
        if (panelTitle) {
            panelTitle.textContent = isNew ? 'New category' : 'Edit category';
        }
        if (panelSub) {
            panelSub.textContent = 'Save to apply';
        }
    }

    async function load() {
        const q = qInput?.value?.trim() ?? '';
        const params = q ? { params: { q } } : {};
        const { data } = await axios.get(listUrl, params);
        categories = data.categories ?? [];
        render();
        const url = new URL(window.location.href);
        const editParam = url.searchParams.get('edit');
        const wantsNew = url.searchParams.get('new');
        if (wantsNew === '1') {
            clearForm(true);
        } else if (editParam && /^\d+$/.test(editParam)) {
            pickCategory(Number(editParam));
        }
    }

    function render() {
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        if (categories.length === 0) {
            emptyEl?.classList.remove('hidden');
            return;
        }
        emptyEl?.classList.add('hidden');
        categories.forEach((c) => {
            const tr = document.createElement('tr');
            tr.dataset.id = String(c.id);
            tr.className = 'cursor-pointer transition hover:bg-slate-800/50';
            if (selectedId === c.id) {
                tr.classList.add('catalog-row-active');
            }
            const status = c.is_active
                ? `<span class="text-emerald-400">On</span>`
                : `<span class="text-slate-500">Off</span>`;
            const kitchen = c.is_kitchen
                ? `<span class="text-orange-400">Yes</span>`
                : `<span class="text-slate-500">No</span>`;
            tr.innerHTML = `
                <td class="px-2 py-1">
                    <span class="font-medium text-slate-100">${escapeHtml(c.icon ? `${c.icon} ` : '')}${escapeHtml(c.name)}</span>
                </td>
                <td class="px-1 py-1 text-right tabular-nums text-slate-400">${c.menu_items_count}</td>
                <td class="px-1 py-1 text-xs">${status}</td>
                <td class="px-1 py-1 text-xs">${kitchen}</td>
                <td class="px-1 py-1 text-right text-slate-400">${c.sort_order}</td>
                <td class="space-x-1 px-1 py-1 text-right text-[10px]">
                    <button type="button" data-act="edit" class="text-amber-400 hover:underline">Edit</button>
                    <button type="button" data-act="toggle" class="text-slate-400 hover:underline">Toggle</button>
                    <button type="button" data-act="del" class="text-red-400 hover:underline">Del</button>
                </td>`;
            tbody.appendChild(tr);
        });
    }

    function pickCategory(id) {
        const c = categories.find((x) => x.id === id);
        if (!c || !form) {
            clearForm(true);
            return;
        }
        selectedId = id;
        const hid = form.querySelector('[data-cc-id]');
        if (hid) {
            hid.value = String(id);
        }
        /** @type HTMLFormElement */
        const f = form;
        f.name.value = c.name;
        f.sort_order.value = String(c.sort_order);
        f.is_active.checked = !!c.is_active;
        f.is_kitchen.checked = !!c.is_kitchen;
        f.icon.value = c.icon ?? '';
        if (panelTitle) {
            panelTitle.textContent = 'Edit category';
        }
        if (panelSub) {
            panelSub.textContent = `#${id} · ${c.menu_items_count} items`;
        }
        render();
    }

    tbody?.addEventListener('click', (e) => {
        const tr = e.target?.closest?.('tr');
        const id = Number(tr?.dataset?.id);
        if (!id) {
            return;
        }
        const btn = e.target?.closest?.('[data-act]');
        if (btn) {
            const act = btn.dataset.act;
            e.stopPropagation();
            if (act === 'edit') {
                pickCategory(id);
                return;
            }
            if (act === 'toggle') {
                void (async () => {
                    try {
                        const { data } = await axios.post(urlId(toggleTpl, id));
                        toast(toastHost, data.message ?? 'Updated');
                        await load();
                        if (selectedId === id) {
                            pickCategory(id);
                        }
                    } catch (err) {
                        toast(toastHost, err.response?.data?.message ?? 'Request failed', 'err');
                    }
                })();
                return;
            }
            if (act === 'del') {
                // eslint-disable-next-line no-alert -- quick admin confirm
                if (!window.confirm('Delete this category?')) {
                    return;
                }
                void (async () => {
                    try {
                        await axios.delete(urlId(deleteTpl, id));
                        toast(toastHost, 'Category deleted');
                        if (selectedId === id) {
                            clearForm(true);
                        }
                        await load();
                    } catch (err) {
                        toast(toastHost, err.response?.data?.message ?? 'Cannot delete', 'err');
                    }
                })();
            }
            return;
        }
        pickCategory(id);
    });

    const debouncedLoad = debounce(() => load(), 280);
    qInput?.addEventListener('input', () => debouncedLoad());

    form?.addEventListener('submit', (e) => {
        e.preventDefault();
        void (async () => {
            if (!form) {
                return;
            }
            const fd = new FormData(form);
            const cb = /** @type HTMLInputElement|null */ (form.querySelector('input[name="is_active"]'));
            const kitchenCb = /** @type HTMLInputElement|null */ (form.querySelector('input[name="is_kitchen"]'));
            fd.set('is_active', cb?.checked ? '1' : '0');
            fd.set('is_kitchen', kitchenCb?.checked ? '1' : '0');
            const id = fd.get('id')?.toString() ?? '';
            try {
                if (id) {
                    const patchUrl = urlId(patchTpl, id);
                    fd.set('_method', 'PATCH');
                    await axios.post(patchUrl, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
                    toast(toastHost, 'Category updated');
                } else {
                    fd.delete('id');
                    await axios.post(storeUrl, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
                    toast(toastHost, 'Category saved');
                    clearForm(true);
                }
                await load();
            } catch (err) {
                const msg =
                    err.response?.data?.message ??
                    (err.response?.data?.errors
                        ? Object.values(err.response.data.errors)[0]?.[0]
                        : null) ??
                    'Save failed';
                toast(toastHost, msg, 'err');
            }
        })();
    });

    btnClear?.addEventListener('click', () => clearForm(true));
    btnNew?.addEventListener('click', () => clearForm(true));

    void load().catch(() => toast(toastHost, 'Could not load categories', 'err'));
}

// ——— Menu items ———

/** @typedef {{id:number,name:string,description?:string,price:string,discount_price?:string|null,prep_minutes?:number|null,is_bestseller:boolean,is_popular:boolean,is_available:boolean,dietary_type?:string|null,image_url?:string|null,category_id:number,category?:{id:number,name:string}}} MenuItemRow */

function initMenuItems(root) {
    const itemsUrl = root.dataset.urlItems;
    const catsUrl = root.dataset.urlCategories;
    const storeUrl = root.dataset.urlStore;
    const patchTpl = root.dataset.urlPatch;
    const inlineTpl = root.dataset.urlInline;
    const dupTpl = root.dataset.urlDup;
    const deleteTpl = root.dataset.urlDelete;
    const bulkUrl = root.dataset.urlBulk;

    const tbody = root.querySelector('[data-mi-tbody]');
    const emptyEl = root.querySelector('[data-mi-empty]');
    const qInput = root.querySelector('[data-mi-q]');
    const fCat = root.querySelector('[data-mi-filter-cat]');
    const fAvail = root.querySelector('[data-mi-filter-avail]');
    const fDiet = root.querySelector('[data-mi-filter-diet]');
    const btnRefresh = root.querySelector('[data-mi-refresh]');
    const btnNew = root.querySelector('[data-mi-new]');
    const checkAll = root.querySelector('[data-mi-check-all]');
    const bulkWrap = root.querySelector('[data-mi-bulk-wrap]');
    const bulkCount = root.querySelector('[data-mi-bulk-count]');
    const bulkMoveCat = root.querySelector('[data-mi-bulk-move-cat]');
    const btnBulkMove = root.querySelector('[data-mi-bulk-move]');
    const btnBulkOn = root.querySelector('[data-mi-bulk-on]');
    const btnBulkOff = root.querySelector('[data-mi-bulk-off]');
    const btnBulkDel = root.querySelector('[data-mi-bulk-del]');

    const form = root.querySelector('[data-mi-form]');
    const toastHost = root.querySelector('[data-catalog-toast-host]');
    const drop = root.querySelector('[data-mi-drop]');
    const fileIn = root.querySelector('[data-mi-file]');
    const previewWrap = root.querySelector('[data-mi-preview]');
    const previewImg = previewWrap?.querySelector('img');
    const btnClearImg = root.querySelector('[data-mi-clear-img]');
    const removeWrap = root.querySelector('[data-mi-remove-wrap]');
    const removeCb = root.querySelector('[data-mi-remove]');
    const formCat = root.querySelector('[data-mi-form-cat]');
    const filterCatSel = root.querySelector('[data-mi-filter-cat]');
    const miId = root.querySelector('[data-mi-id]');
    const formError = root.querySelector('[data-mi-form-error]');
    const btnSave = root.querySelector('[data-mi-save]');
    const btnSaveAnother = root.querySelector('[data-mi-save-another]');
    const btnSaveContinue = root.querySelector('[data-mi-save-continue]');
    const btnDupPanel = root.querySelector('[data-mi-dup-panel]');
    const btnDelPanel = root.querySelector('[data-mi-del-panel]');
    const panelTitle = root.querySelector('[data-mi-panel-title]');
    const panelSub = root.querySelector('[data-mi-panel-sub]');

    /** @type MenuItemRow[] */
    let items = [];
    let categories = [];
    const selectedIds = new Set();

    let fileObjectUrl = null;

    function revokeFileUrl() {
        if (fileObjectUrl) {
            URL.revokeObjectURL(fileObjectUrl);
            fileObjectUrl = null;
        }
    }

    function syncCategorySelects() {
        const opts = `<option value="">All categories</option>${categories
            .map((c) => `<option value="${c.id}">${escapeHtml(c.name)}</option>`)
            .join('')}`;
        if (filterCatSel) {
            const v = filterCatSel.value;
            filterCatSel.innerHTML = opts;
            filterCatSel.value = v && categories.some((c) => String(c.id) === v) ? v : '';
        }
        if (formCat) {
            const fv = formCat.value;
            formCat.innerHTML = categories.map((c) => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
            if (fv && categories.some((c) => String(c.id) === fv)) {
                formCat.value = fv;
            }
        }
        if (bulkMoveCat) {
            const v = bulkMoveCat.value;
            bulkMoveCat.innerHTML =
                `<option value="">Pick category…</option>` +
                categories.map((c) => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
            if (v && categories.some((c) => String(c.id) === v)) {
                bulkMoveCat.value = v;
            }
        }
    }

    function showFormError(text) {
        if (!formError) {
            return;
        }
        if (text) {
            formError.textContent = text;
            formError.classList.remove('hidden');
        } else {
            formError.textContent = '';
            formError.classList.add('hidden');
        }
    }

    function clearFormForNew() {
        if (!form) {
            return;
        }
        form.reset();
        if (miId) {
            miId.value = '';
        }
        const av = form.querySelector('input[name="is_available"]');
        if (av) {
            /** @type HTMLInputElement */
            (av).checked = true;
        }
        ['is_bestseller', 'is_popular'].forEach((n) => {
            const el = form.querySelector(`input[name="${n}"]`);
            if (el) {
                /** @type HTMLInputElement */
                (el).checked = false;
            }
        });
        removeCb && ((removeCb).checked = false);
        removeWrap?.classList.add('hidden');
        revokeFileUrl();
        if (previewWrap && previewImg) {
            previewWrap.classList.add('hidden');
            previewImg.removeAttribute('src');
        }
        if (btnDupPanel) {
            btnDupPanel.disabled = true;
        }
        if (btnDelPanel) {
            btnDelPanel.disabled = true;
        }
        if (btnSaveContinue) {
            btnSaveContinue.classList.add('hidden');
        }
        if (panelTitle) {
            panelTitle.textContent = 'New item';
        }
        if (panelSub) {
            panelSub.textContent = 'Fill fields · faster with Tab';
        }
        showFormError('');
        renderTable();
    }

    function hydrateForm(it) {
        if (!form) {
            return;
        }
        showFormError('');
        if (miId) {
            miId.value = String(it.id);
        }
        form.name.value = it.name;
        form.category_id.value = String(it.category_id);
        form.price.value = String(it.price);
        form.discount_price.value = it.discount_price != null ? String(it.discount_price) : '';
        form.prep_minutes.value =
            it.prep_minutes != null && it.prep_minutes !== undefined ? String(it.prep_minutes) : '';
        form.description.value = it.description ?? '';
        /** @type HTMLInputElement|null */
        const av = form.querySelector('input[name="is_available"]');
        if (av) {
            av.checked = !!it.is_available;
        }
        /** @type HTMLInputElement|null */
        const bs = form.querySelector('input[name="is_bestseller"]');
        if (bs) {
            bs.checked = !!it.is_bestseller;
        }
        /** @type HTMLInputElement|null */
        const pop = form.querySelector('input[name="is_popular"]');
        if (pop) {
            pop.checked = !!it.is_popular;
        }
        form.dietary_type.value = it.dietary_type ?? '';

        revokeFileUrl();
        if (previewWrap && previewImg) {
            if (it.image_url) {
                previewImg.src = it.image_url;
                previewWrap.classList.remove('hidden');
            } else {
                previewWrap.classList.add('hidden');
                previewImg.removeAttribute('src');
            }
        }
        removeCb && ((removeCb).checked = false);
        if (removeWrap) {
            if (it.image_url) {
                removeWrap.classList.remove('hidden');
            } else {
                removeWrap.classList.add('hidden');
            }
        }
        if (fileIn) {
            fileIn.value = '';
        }
        if (btnDupPanel) {
            btnDupPanel.disabled = false;
        }
        if (btnDelPanel) {
            btnDelPanel.disabled = false;
        }
        if (btnSaveContinue) {
            btnSaveContinue.classList.remove('hidden');
        }
        if (panelTitle) {
            panelTitle.textContent = `Edit · ${it.name}`;
        }
        if (panelSub) {
            panelSub.textContent = `#${it.id}`;
        }
        renderTable();
    }

    function buildAppendFieldData(fd) {
        if (!form) {
            return;
        }
        const names = ['is_available', 'is_bestseller', 'is_popular'];
        names.forEach((n) => {
            const cb = form.querySelector(`input[name="${n}"]`);
            fd.append(n, cb && /** @type HTMLInputElement */ (cb).checked ? '1' : '0');
        });
        const dit = /** @type HTMLSelectElement */ (form.dietary_type)?.value ?? '';
        if (dit === '') {
            fd.append('dietary_type', '');
        }
        const rm = /** @type HTMLInputElement|null */ (removeCb);
        if (rm?.checked) {
            fd.append('remove_image', '1');
        }
    }

    async function saveForm({ another = false } = {}) {
        if (!form) {
            return;
        }
        showFormError('');
        const id = miId?.value?.trim() ?? '';
        const fd = new FormData(form);
        buildAppendFieldData(fd);

        try {
            if (id) {
                const url = urlId(patchTpl, id);
                fd.set('_method', 'PATCH');
                await axios.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
                toast(toastHost, 'Item updated');
                await loadItems();
                const next = items.find((x) => x.id === Number(id));
                if (another) {
                    clearFormForNew();
                } else if (next) {
                    hydrateForm(next);
                }
            } else {
                fd.delete('id');
                const { data } = await axios.post(storeUrl, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
                toast(toastHost, data.message ?? 'Saved');
                await loadItems();
                if (another) {
                    clearFormForNew();
                } else if (data.item) {
                    hydrateForm(data.item);
                }
            }
        } catch (err) {
            const msg =
                err.response?.data?.message ??
                (err.response?.data?.errors
                    ? Object.values(err.response.data.errors)[0]?.[0]
                    : null) ??
                'Save failed';
            showFormError(String(msg));
            toast(toastHost, String(msg), 'err');
        }
    }

    async function loadCategories() {
        const { data } = await axios.get(catsUrl);
        categories = data.categories ?? [];
        syncCategorySelects();
    }

    async function loadItems() {
        const params = {};
        const q = qInput?.value?.trim();
        if (q) {
            params.q = q;
        }
        const fc = fCat?.value;
        if (fc) {
            params.category_id = fc;
        }
        const av = fAvail?.value;
        if (av === '0' || av === '1') {
            params.available = av;
        }
        const d = fDiet?.value;
        if (d) {
            params.diet = d;
        }
        const { data } = await axios.get(itemsUrl, { params });
        items = data.items ?? [];
        renderTable();
    }

    function renderTable() {
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        if (items.length === 0) {
            emptyEl?.classList.remove('hidden');
        } else {
            emptyEl?.classList.add('hidden');
        }
        const activeId = miId?.value ? Number(miId.value) : null;
        items.forEach((it) => {
            const tr = document.createElement('tr');
            tr.dataset.id = String(it.id);
            tr.className = 'align-top transition hover:bg-slate-800/40';
            if (activeId && it.id === activeId) {
                tr.classList.add('catalog-row-active');
            }
            const thumb = it.image_url
                ? `<img src="${escapeHtml(it.image_url)}" alt="" class="thumb-sq rounded border border-slate-700 object-cover">`
                : `<div class="thumb-sq rounded border border-slate-800 bg-slate-900"></div>`;
            const catOpts = categories
                .map(
                    (c) =>
                        `<option value="${c.id}" ${c.id === it.category_id ? 'selected' : ''}>${escapeHtml(c.name)}</option>`,
                )
                .join('');
            tr.innerHTML = `
                <td class="px-1 py-1 align-middle">
                    <input type="checkbox" data-mi-row-check class="h-4 w-4 rounded border-slate-600 bg-slate-950" data-id="${it.id}" ${
                        selectedIds.has(it.id) ? 'checked' : ''
                    }>
                </td>
                <td class="px-0 py-1 align-middle">${thumb}</td>
                <td class="max-w-[10rem] px-1 py-1 align-middle">
                    <button type="button" data-mi-open class="line-clamp-2 w-full text-left font-medium text-slate-100 hover:text-amber-200">${escapeHtml(
                        it.name,
                    )}</button>
                </td>
                <td class="px-1 py-1 align-middle">
                    <select data-inline="category_id" class="catalog-input max-w-[9rem] rounded border border-slate-700 bg-slate-950 py-0.5 pl-1 text-[10px] text-white">${catOpts}</select>
                </td>
                <td class="px-1 py-1 align-middle text-right">
                    <input data-inline="price" type="number" min="0" step="0.01" value="${escapeHtml(
                        it.price,
                    )}" class="catalog-input w-24 rounded border border-slate-700 bg-slate-950 py-0.5 pr-1 text-right text-[11px] text-white">
                </td>
                <td class="px-1 py-1 text-center align-middle">
                    <input data-inline="is_available" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950 text-emerald-600" ${
                        it.is_available ? 'checked' : ''
                    }>
                </td>
                <td class="px-1 py-1 align-middle">
                    <select data-inline="dietary_type" class="catalog-input w-full rounded border border-slate-700 bg-slate-950 py-0.5 pl-0.5 text-[10px] text-white">
                        <option value="" ${!it.dietary_type ? 'selected' : ''}>—</option>
                        <option value="veg" ${it.dietary_type === 'veg' ? 'selected' : ''}>Veg</option>
                        <option value="non_veg" ${it.dietary_type === 'non_veg' ? 'selected' : ''}>Non</option>
                    </select>
                </td>
                <td class="space-x-1 px-1 py-1 text-right align-middle text-[10px]">
                    <button type="button" data-mi-dup class="text-slate-400 hover:underline">Dup</button>
                    <button type="button" data-mi-del class="text-red-400 hover:underline">Del</button>
                </td>`;
            tbody.appendChild(tr);
        });
        updateBulkUi();
        if (checkAll) {
            const allOn = items.length > 0 && items.every((it) => selectedIds.has(it.id));
            checkAll.checked = allOn;
            checkAll.indeterminate = !allOn && items.some((it) => selectedIds.has(it.id));
        }
    }

    function updateBulkUi() {
        if (!bulkWrap || !bulkCount) {
            return;
        }
        const n = selectedIds.size;
        bulkCount.textContent = `${n} selected`;
        bulkWrap.style.display = n > 0 ? 'block' : 'none';
    }

    async function patchInline(id, payload) {
        try {
            const { data } = await axios.patch(urlId(inlineTpl, id), payload);
            toast(toastHost, data.message ?? 'Updated');
            await loadItems();
            if (miId?.value && Number(miId.value) === id && data.item) {
                hydrateForm(data.item);
            }
        } catch (err) {
            toast(toastHost, err.response?.data?.message ?? 'Update failed', 'err');
            await loadItems();
        }
    }

    tbody?.addEventListener('change', (e) => {
        const t = /** @type HTMLElement */ (e.target);
        const tr = t.closest('tr');
        const id = Number(tr?.dataset?.id);
        if (!id) {
            return;
        }
        if (t.matches('[data-mi-row-check]')) {
            /** @type HTMLInputElement */
            const cb = /** @type HTMLInputElement */ (t);
            if (cb.checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
            updateBulkUi();
            renderTable();
            return;
        }
        const field = t.getAttribute('data-inline');
        if (!field) {
            return;
        }
        if (field === 'price') {
            /** @type HTMLInputElement */
            const inp = /** @type HTMLInputElement */ (t);
            void patchInline(id, { price: inp.value });
            return;
        }
        if (field === 'category_id') {
            /** @type HTMLSelectElement */
            const sel = /** @type HTMLSelectElement */ (t);
            void patchInline(id, { category_id: Number(sel.value) });
            return;
        }
        if (field === 'is_available') {
            /** @type HTMLInputElement */
            const cb = /** @type HTMLInputElement */ (t);
            void patchInline(id, { is_available: cb.checked });
            return;
        }
        if (field === 'dietary_type') {
            /** @type HTMLSelectElement */
            const sel = /** @type HTMLSelectElement */ (t);
            const v = sel.value;
            void patchInline(id, { dietary_type: v === '' ? null : v });
        }
    });

    tbody?.addEventListener('click', (e) => {
        const open = e.target?.closest?.('[data-mi-open]');
        const tr = e.target?.closest?.('tr');
        const id = Number(tr?.dataset?.id);
        if (open && id) {
            const it = items.find((x) => x.id === id);
            if (it) {
                hydrateForm(it);
            }
            return;
        }
        const dup = e.target?.closest?.('[data-mi-dup]');
        if (dup && id) {
            void (async () => {
                try {
                    const { data } = await axios.post(urlId(dupTpl, id));
                    toast(toastHost, data.message ?? 'Duplicated');
                    await loadItems();
                    if (data.item) {
                        hydrateForm(data.item);
                    }
                } catch (err) {
                    toast(toastHost, 'Duplicate failed', 'err');
                }
            })();
            return;
        }
        const del = e.target?.closest?.('[data-mi-del]');
        if (del && id) {
            // eslint-disable-next-line no-alert
            if (!window.confirm('Delete this item?')) {
                return;
            }
            void (async () => {
                try {
                    await axios.delete(urlId(deleteTpl, id));
                    toast(toastHost, 'Deleted');
                    selectedIds.delete(id);
                    if (miId?.value && Number(miId.value) === id) {
                        clearFormForNew();
                    }
                    await loadItems();
                } catch (err) {
                    toast(toastHost, 'Delete failed', 'err');
                }
            })();
        }
    });

    checkAll?.addEventListener('change', () => {
        /** @type HTMLInputElement */
        const cb = /** @type HTMLInputElement */ (checkAll);
        if (cb.checked) {
            items.forEach((it) => selectedIds.add(it.id));
        } else {
            items.forEach((it) => selectedIds.delete(it.id));
        }
        renderTable();
    });

    function selectedIdList() {
        return [...selectedIds];
    }

    async function runBulk(action, extra = {}) {
        const ids = selectedIdList();
        if (ids.length === 0) {
            return;
        }
        if (action === 'delete') {
            // eslint-disable-next-line no-alert
            if (!window.confirm(`Delete ${ids.length} items?`)) {
                return;
            }
        }
        try {
            await axios.post(bulkUrl, { action, ids, ...extra });
            toast(toastHost, 'Bulk action done');
            selectedIds.clear();
            await loadItems();
            clearFormForNew();
        } catch (err) {
            toast(toastHost, err.response?.data?.message ?? 'Bulk failed', 'err');
        }
    }

    btnBulkMove?.addEventListener('click', () => {
        const cid = Number(bulkMoveCat?.value);
        if (!cid) {
            toast(toastHost, 'Pick a category', 'err');
            return;
        }
        void runBulk('set_category', { category_id: cid });
    });
    btnBulkOn?.addEventListener('click', () => void runBulk('activate'));
    btnBulkOff?.addEventListener('click', () => void runBulk('deactivate'));
    btnBulkDel?.addEventListener('click', () => void runBulk('delete'));

    const debouncedItems = debounce(() => loadItems(), 260);
    qInput?.addEventListener('input', () => debouncedItems());
    fCat?.addEventListener('change', () => loadItems());
    fAvail?.addEventListener('change', () => loadItems());
    fDiet?.addEventListener('change', () => loadItems());
    btnRefresh?.addEventListener('click', () =>
        Promise.all([loadCategories(), loadItems()]).catch(() => toast(toastHost, 'Refresh failed', 'err')),
    );
    btnNew?.addEventListener('click', () => clearFormForNew());

    btnSave?.addEventListener('click', () => void saveForm({ another: false }));
    btnSaveAnother?.addEventListener('click', () => void saveForm({ another: true }));
    btnSaveContinue?.addEventListener('click', () => void saveForm({ another: false }));

    btnDupPanel?.addEventListener('click', () => {
        const id = Number(miId?.value);
        if (!id) {
            return;
        }
        void (async () => {
            try {
                const { data } = await axios.post(urlId(dupTpl, id));
                toast(toastHost, data.message ?? 'Duplicated');
                await loadItems();
                if (data.item) {
                    hydrateForm(data.item);
                }
            } catch {
                toast(toastHost, 'Duplicate failed', 'err');
            }
        })();
    });

    btnDelPanel?.addEventListener('click', () => {
        const id = Number(miId?.value);
        if (!id) {
            return;
        }
        // eslint-disable-next-line no-alert
        if (!window.confirm('Delete this item?')) {
            return;
        }
        void (async () => {
            try {
                await axios.delete(urlId(deleteTpl, id));
                toast(toastHost, 'Deleted');
                clearFormForNew();
                await loadItems();
            } catch {
                toast(toastHost, 'Delete failed', 'err');
            }
        })();
    });

    drop?.addEventListener('dragover', (e) => {
        e.preventDefault();
        drop.classList.add('border-amber-500/70');
    });
    drop?.addEventListener('dragleave', () => drop.classList.remove('border-amber-500/70'));
    drop?.addEventListener('drop', (e) => {
        e.preventDefault();
        drop?.classList.remove('border-amber-500/70');
        const f = e.dataTransfer?.files?.[0];
        if (f?.type?.startsWith('image/') && fileIn) {
            const dt = new DataTransfer();
            dt.items.add(f);
            fileIn.files = dt.files;
            revokeFileUrl();
            fileObjectUrl = URL.createObjectURL(f);
            if (previewImg && previewWrap) {
                previewImg.src = fileObjectUrl;
                previewWrap.classList.remove('hidden');
            }
        }
    });

    fileIn?.addEventListener('change', () => {
        const f = fileIn.files?.[0];
        revokeFileUrl();
        if (!f?.type?.startsWith('image/')) {
            return;
        }
        fileObjectUrl = URL.createObjectURL(f);
        if (previewImg && previewWrap) {
            previewImg.src = fileObjectUrl;
            previewWrap.classList.remove('hidden');
        }
    });

    btnClearImg?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (fileIn) {
            fileIn.value = '';
        }
        revokeFileUrl();
        if (previewWrap) {
            previewWrap.classList.add('hidden');
        }
        removeCb && ((removeCb).checked = true);
    });

    form?.addEventListener('keydown', (e) => {
        if (!(e.metaKey || e.ctrlKey) || e.key !== 'Enter') {
            return;
        }
        e.preventDefault();
        void saveForm({ another: false });
    });

    void Promise.all([loadCategories(), loadItems()])
        .then(() => {
            const url = new URL(window.location.href);
            if (url.searchParams.get('new') === '1') {
                clearFormForNew();
            } else {
                const eid = url.searchParams.get('edit');
                if (eid && /^\d+$/.test(eid)) {
                    const it = items.find((x) => x.id === Number(eid));
                    if (it) {
                        hydrateForm(it);
                    }
                }
            }
        })
        .catch(() => toast(toastHost, 'Could not load catalog', 'err'));
}

document.addEventListener('DOMContentLoaded', () => {
    const cat = document.getElementById('catalog-categories');
    if (cat) {
        initCategories(cat);
    }
    const mi = document.getElementById('catalog-menu-items');
    if (mi) {
        initMenuItems(mi);
    }
});
