import Konva from 'konva';

const GRID = 16;
const MIN_SIZE = 48;
const MAX_SIZE = 560;
const WORLD_W = 3200;
const WORLD_H = 2400;

const STATUS_FILL = {
    available: '#22c55e',
    reserved: '#eab308',
    occupied: '#ef4444',
};

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

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message = data.message ?? data.error ?? response.statusText ?? 'Request failed';
        throw new Error(typeof message === 'string' ? message : JSON.stringify(message));
    }

    return data;
}

function debounce(fn, ms) {
    let t = null;
    return (...args) => {
        window.clearTimeout(t);
        t = window.setTimeout(() => fn(...args), ms);
    };
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
    ctx.fillStyle = '#0f172a';
    ctx.fillRect(0, 0, size, size);
    ctx.strokeStyle = '#1e293b';
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

function snap(value) {
    return Math.round(value / GRID) * GRID;
}

function rectsOverlap(a, b) {
    return !(a.x + a.width <= b.x || b.x + b.width <= a.x || a.y + a.height <= b.y || b.y + b.height <= a.y);
}

function serializeTable(group) {
    const id = group.getAttr('tableId');
    const shapeBody = group.findOne('.shape-body');
    const label = group.findOne('.table-label');
    const shape = group.getAttr('shapeKind');

    let width;
    let height;

    if (shape === 'round') {
        const r = shapeBody.radius();
        width = r * 2;
        height = r * 2;
    } else {
        width = shapeBody.width();
        height = shapeBody.height();
    }

    const seats = group.getAttr('seatCapacity');

    return {
        id,
        table_name: label?.text() ?? `Table`,
        shape,
        x_position: group.x(),
        y_position: group.y(),
        width,
        height,
        scale_x: 1,
        scale_y: 1,
        rotation: group.rotation(),
        fill_color: group.getAttr('customFill') ?? null,
        seat_capacity: seats != null ? Number(seats) : null,
        status: group.getAttr('tableStatus') ?? 'available',
        floor_id: group.getAttr('floorId') ?? null,
    };
}

function applyFillToTable(group) {
    const shapeBody = group.findOne('.shape-body');
    const custom = group.getAttr('customFill');
    const status = group.getAttr('tableStatus') ?? 'available';
    const fill = custom || STATUS_FILL[status] || STATUS_FILL.available;
    shapeBody.fill(fill);
}

function buildTableGroup(model, hooks) {
    const shapeKind = model.shape === 'round' ? 'round' : 'square';
    let baseW = Number(model.width);
    let baseH = Number(model.height);
    if (shapeKind === 'round') {
        const s = Math.max(Math.min(baseW, baseH, MAX_SIZE), MIN_SIZE);
        baseW = s;
        baseH = s;
    }

    const group = new Konva.Group({
        x: Number(model.x_position),
        y: Number(model.y_position),
        rotation: Number(model.rotation ?? 0),
        draggable: true,
        name: 'table',
    });

    group.setAttr('tableId', model.id);
    group.setAttr('shapeKind', shapeKind);
    group.setAttr('baseWidth', baseW);
    group.setAttr('baseHeight', baseH);
    group.setAttr('tableStatus', model.status?.value ?? model.status ?? 'available');
    group.setAttr('seatCapacity', model.seat_capacity != null ? Number(model.seat_capacity) : 4);
    group.setAttr('customFill', model.fill_color || null);
    group.setAttr('floorId', model.floor_id ?? null);

    const strokeColor = '#0f172a';

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

    const labelText = model.table_name ?? `Table ${model.table_number ?? ''}`;
    const label = new Konva.Text({
        name: 'table-label',
        text: labelText,
        fontSize: shapeKind === 'round' ? 15 : 14,
        fontFamily: 'ui-sans-serif, system-ui, sans-serif',
        fill: '#f8fafc',
        align: 'center',
        verticalAlign: 'middle',
        width: baseW,
        height: baseH,
        offsetX: baseW / 2,
        offsetY: baseH / 2,
        listening: true,
        shadowColor: 'rgba(0,0,0,0.35)',
        shadowBlur: 4,
        shadowOffsetY: 1,
    });

    group.add(shapeNode);
    group.add(label);

    group.dragBoundFunc((pos) => ({
        x: snap(pos.x),
        y: snap(pos.y),
    }));

    applyFillToTable(group);

    group.on('dragstart', () => {
        group.setAttr('dragStartPos', group.position());
    });

    group.on('dragend', () => {
        const start = group.getAttr('dragStartPos');
        if (hooks.preventOverlap(group) && start) {
            group.position(start);
        }
        hooks.onChange();
    });

    group.on('transformend', () => {
        const shapeKindInner = group.getAttr('shapeKind');
        const body = group.findOne('.shape-body');

        if (shapeKindInner === 'square') {
            const sx = group.scaleX();
            const sy = group.scaleY();
            const newW = Math.min(MAX_SIZE, Math.max(MIN_SIZE, body.width() * sx));
            const newH = Math.min(MAX_SIZE, Math.max(MIN_SIZE, body.height() * sy));
            body.width(newW);
            body.height(newH);
            body.offsetX(newW / 2);
            body.offsetY(newH / 2);
            body.x(0);
            body.y(0);
            group.setAttr('baseWidth', newW);
            group.setAttr('baseHeight', newH);
        } else {
            const sx = group.scaleX();
            const sy = group.scaleY();
            const uniform = (sx + sy) / 2;
            const rawR = body.radius() * uniform;
            const r = Math.min(MAX_SIZE / 2, Math.max(MIN_SIZE / 2, rawR));
            body.radius(r);
            group.setAttr('baseWidth', r * 2);
            group.setAttr('baseHeight', r * 2);
        }

        group.scaleX(1);
        group.scaleY(1);
        body.scaleX(1);
        body.scaleY(1);

        const tw = shapeKindInner === 'round' ? body.radius() * 2 : body.width();
        const th = shapeKindInner === 'round' ? body.radius() * 2 : body.height();
        const labelNode = group.findOne('.table-label');
        labelNode.width(tw);
        labelNode.height(th);
        labelNode.offsetX(tw / 2);
        labelNode.offsetY(th / 2);

        hooks.onChange();
    });

    label.on('dblclick dbltap', () => {
        hooks.editLabel(group);
    });

    return group;
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('floor-plan-root');
    const container = document.getElementById('konva-container');
    const statusEl = document.getElementById('fp-status');
    const inlineEditor = document.getElementById('fp-inline-editor');

    if (!root || !container) {
        return;
    }

    const urlData = root.dataset.urlData;
    const urlSync = root.dataset.urlSync;
    const urlStore = root.dataset.urlStore;
    const urlTableTemplate = root.dataset.urlTableTemplate ?? '';

    const gridCanvas = createGridPattern();
    const patternImage = gridCanvas ? new Image() : null;
    if (patternImage && gridCanvas) {
        patternImage.src = gridCanvas.toDataURL();
    }

    let stage;
    let layer;
    let world;
    let tablesLayer;
    let transformer;
    const tableNodes = new Map();
    let selectedId = null;

    let worldScale = 1;
    let isPanning = false;
    let lastPointer = null;

    const setStatus = (message, isError = false) => {
        if (!statusEl) {
            return;
        }
        statusEl.textContent = message;
        statusEl.classList.toggle('text-rose-400', isError);
        statusEl.classList.toggle('text-slate-500', !isError);
    };

    const drawer = document.getElementById('fp-drawer');
    const drawerBackdrop = document.getElementById('fp-drawer-backdrop');
    const drawerClose = document.getElementById('fp-drawer-close');
    const drawerEmpty = document.getElementById('fp-drawer-empty');
    const drawerBody = document.getElementById('fp-drawer-body');
    const drawerTitle = document.getElementById('fp-drawer-title');
    const drawerQr = document.getElementById('fp-drawer-qr');
    const drawerName = document.getElementById('fp-drawer-name');
    const drawerSeats = document.getElementById('fp-drawer-seats');
    const drawerApply = document.getElementById('fp-drawer-apply');
    const drawerCopyUrl = document.getElementById('fp-drawer-copy-url');
    const drawerDownloadQr = document.getElementById('fp-drawer-download-qr');
    const drawerPrintQr = document.getElementById('fp-drawer-print-qr');
    const drawerShape = document.getElementById('fp-drawer-shape');
    const drawerPosition = document.getElementById('fp-drawer-position');
    const drawerColorSwatch = document.getElementById('fp-drawer-color-swatch');
    const drawerColorLabel = document.getElementById('fp-drawer-color-label');
    const drawerUpdated = document.getElementById('fp-drawer-updated');
    const autosaveEl = document.getElementById('fp-autosave');

    let drawerRefreshTimer = null;
    let currentOrderingUrl = '';

    function setToolbarOffset() {
        const header = document.querySelector('#floor-plan-root > header');
        if (header) {
            document.documentElement.style.setProperty('--fp-toolbar-h', `${header.offsetHeight}px`);
        }
    }

    function tableDetailUrl(id) {
        return urlTableTemplate.replace('__ID__', String(id));
    }

    function stopDrawerRefresh() {
        if (drawerRefreshTimer != null) {
            window.clearInterval(drawerRefreshTimer);
            drawerRefreshTimer = null;
        }
    }

    function startDrawerRefresh() {
        stopDrawerRefresh();
        if (selectedId == null) {
            return;
        }
        drawerRefreshTimer = window.setInterval(() => {
            if (selectedId == null) {
                stopDrawerRefresh();
                return;
            }
            fetchJson(tableDetailUrl(selectedId))
                .then((data) => {
                    const group = tableNodes.get(Number(selectedId));
                    if (group) {
                        populateDrawer(data, group, true);
                    }
                })
                .catch(() => {});
        }, 25000);
    }

    function openDrawer() {
        drawer?.classList.remove('translate-x-full');
        drawer?.setAttribute('aria-hidden', 'false');
        drawerBackdrop?.classList.remove('opacity-0', 'pointer-events-none');
        drawerBackdrop?.classList.add('opacity-100', 'pointer-events-auto');
    }

    function closeDrawer() {
        drawer?.classList.add('translate-x-full');
        drawer?.setAttribute('aria-hidden', 'true');
        drawerBackdrop?.classList.add('opacity-0', 'pointer-events-none');
        drawerBackdrop?.classList.remove('opacity-100', 'pointer-events-auto');
        stopDrawerRefresh();
    }

    function formatPosition(table) {
        const x = Math.round(Number(table.x_position));
        const y = Math.round(Number(table.y_position));
        return `Center x ${x}, y ${y} (px)`;
    }

    function resolveDisplayColor(table, group) {
        if (table.fill_color) {
            return { hex: table.fill_color, label: table.fill_color };
        }
        const st = table.status ?? group?.getAttr('tableStatus') ?? 'available';
        const hex = STATUS_FILL[st] ?? STATUS_FILL.available;
        return { hex, label: `${st} (default)` };
    }

    function populateDrawer(payload, group, syncCanvasFromServer = true) {
        const table = payload.table;
        currentOrderingUrl = payload.ordering_url ?? '';

        if (drawerEmpty && drawerBody) {
            drawerEmpty.classList.add('hidden');
            drawerBody.classList.remove('hidden');
            drawerBody.classList.add('flex');
        }

        if (drawerTitle) {
            drawerTitle.textContent = table.table_name ?? `Table ${table.table_number}`;
        }
        if (drawerQr) {
            drawerQr.innerHTML = payload.qr_svg ?? '';
        }
        if (drawerName) {
            drawerName.value = table.table_name ?? '';
        }
        if (drawerSeats) {
            drawerSeats.value = String(table.seat_capacity ?? 4);
        }
        if (drawerShape) {
            drawerShape.textContent = table.shape === 'round' ? 'Round' : 'Square';
        }
        if (drawerPosition) {
            drawerPosition.textContent = formatPosition(table);
        }
        const colorInfo = resolveDisplayColor(table, group);
        if (drawerColorSwatch) {
            drawerColorSwatch.style.backgroundColor = colorInfo.hex;
        }
        if (drawerColorLabel) {
            drawerColorLabel.textContent = colorInfo.label;
        }
        if (drawerUpdated && table.updated_at) {
            drawerUpdated.textContent = new Date(table.updated_at).toLocaleString();
        }

        document.querySelectorAll('.fp-status-btn').forEach((btn) => {
            const active = btn.getAttribute('data-status') === table.status;
            btn.classList.toggle('ring-2', active);
            btn.classList.toggle('ring-orange-300', active);
            btn.classList.toggle('border-orange-400', active);
        });

        if (syncCanvasFromServer && group) {
            group.setAttr('tableStatus', table.status);
            group.setAttr('seatCapacity', table.seat_capacity ?? 4);
            group.setAttr('customFill', table.fill_color || null);
            const label = group.findOne('.table-label');
            if (label && table.table_name) {
                label.text(table.table_name);
            }
            applyFillToTable(group);
            layer.batchDraw();
        }
    }

    function updateSelectionOutline() {
        tableNodes.forEach((node, tid) => {
            const body = node.findOne('.shape-body');
            if (!body) {
                return;
            }
            const isSel = selectedId != null && Number(tid) === Number(selectedId);
            body.stroke(isSel ? '#fbbf24' : '#0f172a');
            body.strokeWidth(isSel ? 4 : 2);
        });
        layer.batchDraw();
    }

    async function selectTable(group) {
        selectedId = group.getAttr('tableId');
        transformer.nodes([group]);
        transformer.moveToTop();
        group.moveToTop();
        updateSelectionOutline();

        openDrawer();
        setStatus('Loading table…');

        try {
            const data = await fetchJson(tableDetailUrl(selectedId));
            populateDrawer(data, group, true);
            setStatus('Table loaded.');
            startDrawerRefresh();
        } catch (err) {
            setStatus(err.message, true);
        }
    }

    function clearSelection() {
        selectedId = null;
        transformer.nodes([]);
        updateSelectionOutline();
        closeDrawer();
        if (drawerEmpty && drawerBody) {
            drawerEmpty.classList.remove('hidden');
            drawerBody.classList.add('hidden');
            drawerBody.classList.remove('flex');
        }
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

    function getWorldRects(exceptGroup) {
        const rects = [];
        tableNodes.forEach((node) => {
            if (node === exceptGroup) {
                return;
            }
            rects.push(node.getClientRect({ skipTransform: false }));
        });
        return rects;
    }

    function overlapsOthers(self) {
        const box = self.getClientRect({ skipTransform: false });
        const rects = getWorldRects(self);
        return rects.some((r) => rectsOverlap(box, r));
    }

    const scheduleSave = debounce(() => {
        if (!autosaveEl?.checked) {
            return;
        }
        saveAll(false).catch((e) => setStatus(e.message, true));
    }, 2500);

    function onTableChange() {
        scheduleSave();
        setStatus('Unsaved changes…');
    }

    function editLabel(group) {
        if (!inlineEditor) {
            return;
        }
        const label = group.findOne('.table-label');
        const pos = label.getAbsolutePosition();
        const box = label.getClientRect();
        const containerRect = container.getBoundingClientRect();
        inlineEditor.value = label.text();
        inlineEditor.classList.remove('hidden');
        inlineEditor.classList.remove('pointer-events-none');
        inlineEditor.style.left = `${containerRect.left + pos.x}px`;
        inlineEditor.style.top = `${containerRect.top + pos.y}px`;
        inlineEditor.style.width = `${Math.max(box.width, 120)}px`;
        inlineEditor.focus();
        inlineEditor.select();

        const finish = () => {
            const v = inlineEditor.value.trim() || label.text();
            label.text(v);
            inlineEditor.classList.add('hidden');
            inlineEditor.classList.add('pointer-events-none');
            inlineEditor.removeEventListener('blur', onBlur);
            inlineEditor.removeEventListener('keydown', onKey);
            if (drawerName && Number(selectedId) === Number(group.getAttr('tableId'))) {
                drawerName.value = v;
            }
            layer.batchDraw();
            onTableChange();
        };

        const onBlur = () => finish();
        const onKey = (e) => {
            if (e.key === 'Enter') {
                finish();
            }
            if (e.key === 'Escape') {
                inlineEditor.removeEventListener('blur', onBlur);
                inlineEditor.classList.add('hidden');
                inlineEditor.classList.add('pointer-events-none');
            }
        };

        inlineEditor.addEventListener('blur', onBlur, { once: true });
        inlineEditor.addEventListener('keydown', onKey);
    }

    function initStage() {
        stage = new Konva.Stage({
            container: 'konva-container',
            width: container.clientWidth,
            height: container.clientHeight,
        });

        layer = new Konva.Layer();
        world = new Konva.Group({ x: 0, y: 0, name: 'world' });

        const floorBg = new Konva.Rect({
            name: 'floor-bg',
            x: 0,
            y: 0,
            width: WORLD_W,
            height: WORLD_H,
            fillPatternImage: patternImage ?? undefined,
            fillPatternRepeat: 'repeat',
            stroke: '#334155',
            strokeWidth: 1,
        });

        if (!patternImage) {
            floorBg.fill('#0f172a');
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

        transformer = new Konva.Transformer({
            rotateEnabled: true,
            rotationSnaps: [0, 45, 90, 135, 180, 225, 270, 315],
            rotationSnapTolerance: 6,
            anchorStroke: '#fb923c',
            anchorFill: '#fff7ed',
            borderStroke: '#fb923c',
            boundBoxFunc(oldBox, newBox) {
                if (newBox.width < MIN_SIZE || newBox.height < MIN_SIZE) {
                    return oldBox;
                }
                if (newBox.width > MAX_SIZE || newBox.height > MAX_SIZE) {
                    return oldBox;
                }
                return newBox;
            },
        });

        layer.add(transformer);

        stage.add(layer);

        stage.on('mousedown touchstart', (e) => {
            if (e.target.getAttr && e.target.getAttr('name') === 'floor-bg') {
                isPanning = true;
                lastPointer = stage.getPointerPosition();
                transformer.nodes([]);
                clearSelection();
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

            const newPos = {
                x: pointer.x - mousePointTo.x * newScale,
                y: pointer.y - mousePointTo.y * newScale,
            };
            world.position(newPos);
            layer.batchDraw();
        });

        stage.on('click tap', (e) => {
            const clickedOn = e.target;
            if (clickedOn.getAttr && clickedOn.getAttr('name') === 'floor-bg') {
                return;
            }
            let node = clickedOn;
            while (node && node.getAttr && node.getAttr('tableId') == null) {
                node = node.getParent();
            }
            if (node && node.getAttr('tableId') != null) {
                selectTable(node);
            }
        });

        window.addEventListener('resize', resizeStage);
    }

    async function loadTables() {
        const payload = await fetchJson(urlData);
        const tables = payload.tables ?? [];
        tablesLayer.destroyChildren();
        tableNodes.clear();

        const hooks = {
            onChange: onTableChange,
            preventOverlap: overlapsOthers,
            editLabel,
        };

        tables.forEach((t) => {
            const g = buildTableGroup(t, hooks);
            tablesLayer.add(g);
            tableNodes.set(t.id, g);
        });

        layer.batchDraw();
        setStatus('Floor plan loaded.');
    }

    async function saveAll(showOk = true) {
        const rows = [];
        tableNodes.forEach((group) => {
            rows.push(serializeTable(group));
        });

        if (rows.length === 0) {
            await fetchJson(urlSync, {
                method: 'POST',
                body: JSON.stringify({ tables: [] }),
            });
            setStatus('Nothing to sync.');
            return;
        }

        await fetchJson(urlSync, {
            method: 'POST',
            body: JSON.stringify({ tables: rows }),
        });

        if (showOk) {
            setStatus('Layout saved.');
        } else {
            setStatus('Auto-saved.');
        }
    }

    async function addTable(shape) {
        const cx = (container.clientWidth / 2 - world.x()) / worldScale;
        const cy = (container.clientHeight / 2 - world.y()) / worldScale;

        const defaults =
            shape === 'round'
                ? { shape: 'round', width: 100, height: 100 }
                : { shape: 'square', width: 120, height: 80 };

        const created = await fetchJson(urlStore, {
            method: 'POST',
            body: JSON.stringify({
                ...defaults,
                x_position: snap(cx),
                y_position: snap(cy),
                status: 'available',
            }),
        });

        const model = created.table;
        const hooks = {
            onChange: onTableChange,
            preventOverlap: overlapsOthers,
            editLabel,
        };
        const g = buildTableGroup(model, hooks);
        tablesLayer.add(g);
        tableNodes.set(model.id, g);
        selectTable(g);
        layer.batchDraw();
        setStatus('Table added — save or wait for auto-save.');
    }

    async function deleteSelected() {
        if (selectedId == null) {
            setStatus('Select a table first.', true);
            return;
        }

        const deleteUrl = `${window.location.origin}/dining-tables/${selectedId}`;
        await fetchJson(deleteUrl, { method: 'DELETE' });

        const node = tableNodes.get(selectedId);
        if (node) {
            node.destroy();
            tableNodes.delete(selectedId);
        }
        clearSelection();
        layer.batchDraw();
        setStatus('Table removed.');
    }

    function applyZoom(delta) {
        const oldScale = worldScale;
        let newScale = delta > 0 ? oldScale * 1.12 : oldScale / 1.12;
        newScale = Math.max(0.35, Math.min(2.25, newScale));
        const center = {
            x: stage.width() / 2,
            y: stage.height() / 2,
        };
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
    }

    function resetZoom() {
        worldScale = 1;
        world.scale({ x: 1, y: 1 });
        world.position({ x: 40, y: 40 });
        layer.batchDraw();
    }

    initStage();
    resizeStage();
    world.position({ x: 40, y: 40 });

    loadTables().catch((e) => setStatus(e.message, true));

    document.getElementById('fp-add-square')?.addEventListener('click', () => {
        addTable('square').catch((e) => setStatus(e.message, true));
    });
    document.getElementById('fp-add-round')?.addEventListener('click', () => {
        addTable('round').catch((e) => setStatus(e.message, true));
    });
    document.getElementById('fp-save')?.addEventListener('click', () => {
        saveAll(true).catch((e) => setStatus(e.message, true));
    });
    document.getElementById('fp-delete')?.addEventListener('click', () => {
        deleteSelected().catch((e) => setStatus(e.message, true));
    });
    document.getElementById('fp-zoom-in')?.addEventListener('click', () => applyZoom(1));
    document.getElementById('fp-zoom-out')?.addEventListener('click', () => applyZoom(-1));
    document.getElementById('fp-zoom-reset')?.addEventListener('click', () => resetZoom());

    drawerClose?.addEventListener('click', () => clearSelection());

    drawerBackdrop?.addEventListener('click', () => clearSelection());

    document.querySelectorAll('.fp-status-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const status = btn.getAttribute('data-status');
            if (!status || selectedId == null) {
                return;
            }
            const group = tableNodes.get(Number(selectedId));
            if (!group) {
                return;
            }

            const prevStatus = group.getAttr('tableStatus');
            group.setAttr('tableStatus', status);
            group.setAttr('customFill', null);
            applyFillToTable(group);
            updateSelectionOutline();
            layer.batchDraw();

            try {
                const data = await fetchJson(tableDetailUrl(selectedId), {
                    method: 'PATCH',
                    body: JSON.stringify({ status }),
                });
                populateDrawer(data, group, true);
                setStatus('Status updated.');
            } catch (err) {
                group.setAttr('tableStatus', prevStatus);
                applyFillToTable(group);
                layer.batchDraw();
                setStatus(err.message, true);
            }
        });
    });

    drawerApply?.addEventListener('click', async () => {
        if (selectedId == null) {
            return;
        }
        const group = tableNodes.get(Number(selectedId));
        if (!group) {
            return;
        }
        const name = drawerName?.value?.trim() ?? '';
        const seats = Math.min(99, Math.max(1, Number(drawerSeats?.value) || 4));

        try {
            const data = await fetchJson(tableDetailUrl(selectedId), {
                method: 'PATCH',
                body: JSON.stringify({
                    table_name: name || group.findOne('.table-label')?.text(),
                    seat_capacity: seats,
                }),
            });
            populateDrawer(data, group, true);
            setStatus('Table details saved.');
            onTableChange();
        } catch (err) {
            setStatus(err.message, true);
        }
    });

    drawerCopyUrl?.addEventListener('click', async () => {
        if (!currentOrderingUrl) {
            return;
        }
        try {
            await navigator.clipboard.writeText(currentOrderingUrl);
            setStatus('Ordering URL copied.');
        } catch {
            setStatus('Could not copy URL.', true);
        }
    });

    drawerDownloadQr?.addEventListener('click', () => {
        const svg = drawerQr?.querySelector('svg');
        if (!svg || selectedId == null) {
            return;
        }
        const blob = new Blob([svg.outerHTML], { type: 'image/svg+xml;charset=utf-8' });
        const a = document.createElement('a');
        const blobUrl = URL.createObjectURL(blob);
        a.href = blobUrl;
        a.download = `table-${selectedId}-order-qr.svg`;
        a.click();
        URL.revokeObjectURL(blobUrl);
        setStatus('QR downloaded.');
    });

    drawerPrintQr?.addEventListener('click', () => {
        const html = drawerQr?.innerHTML ?? '';
        if (!html) {
            return;
        }
        const w = window.open('', '_blank');
        if (!w) {
            setStatus('Allow pop-ups to print.', true);
            return;
        }
        w.document.write(
            `<!DOCTYPE html><html><head><title>Table QR</title><meta charset="utf-8"></head><body style="margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#fff">${html}<script>window.onload=function(){window.print();window.close();}<\/script></body></html>`,
        );
        w.document.close();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            clearSelection();
        }
    });

    setToolbarOffset();
    window.addEventListener('resize', setToolbarOffset);
});
