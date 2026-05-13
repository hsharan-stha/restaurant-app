@php
    use App\Enums\PrinterConnectionType;
    use App\Enums\PrinterRole;
@endphp

<div class="grid gap-3 sm:grid-cols-2">
    <label class="block sm:col-span-2">
        <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Name</span>
        <input name="name" value="{{ old('name', $printer->name ?? '') }}" required class="w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-sm text-white">
    </label>
    <label class="block">
        <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Role</span>
        <select name="role" class="w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-sm text-white">
            @foreach(PrinterRole::cases() as $r)
                <option value="{{ $r->value }}" @selected(old('role', $printer->role->value ?? '') === $r->value)>{{ $r->label() }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Paper width</span>
        <select name="paper_width" class="w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-sm text-white">
            <option value="80" @selected(old('paper_width', $printer->paper_width ?? '80') === '80')>80 mm</option>
            <option value="58" @selected(old('paper_width', $printer->paper_width ?? '') === '58')>58 mm</option>
        </select>
    </label>
    <label class="block sm:col-span-2">
        <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Connection</span>
        <select name="connection_type" id="printer-connection-type" class="w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 text-sm text-white">
            @foreach(PrinterConnectionType::cases() as $c)
                <option value="{{ $c->value }}" @selected(old('connection_type', $printer->connection_type->value ?? PrinterConnectionType::NetworkEscpos->value) === $c->value)>{{ $c->label() }}</option>
            @endforeach
        </select>
    </label>
    <label class="block sm:col-span-2">
        <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500"><span id="host-label">IP or hostname</span></span>
        <input name="host" value="{{ old('host', $printer->host ?? '') }}" required class="w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 font-mono text-sm text-white" placeholder="192.168.1.50">
    </label>
    <label class="block" id="port-wrap">
        <span class="mb-0.5 block text-[10px] font-semibold uppercase text-slate-500">Port</span>
        <input name="port" type="number" value="{{ old('port', $printer->port ?? 9100) }}" class="w-full rounded border border-slate-700 bg-slate-950 px-2 py-1.5 font-mono text-sm text-white" min="1" max="65535">
    </label>
    <div class="flex flex-col gap-2 sm:col-span-2 sm:flex-row sm:items-center">
        <label class="inline-flex items-center gap-2 text-xs text-slate-300">
            <input type="hidden" name="is_enabled" value="0">
            <input type="checkbox" name="is_enabled" value="1" class="rounded border-slate-600" @checked(old('is_enabled', ($printer->is_enabled ?? true) ? '1' : '0') === '1')>
            Enabled
        </label>
        <label class="inline-flex items-center gap-2 text-xs text-slate-300">
            <input type="hidden" name="auto_print_enabled" value="0">
            <input type="checkbox" name="auto_print_enabled" value="1" class="rounded border-slate-600" @checked(old('auto_print_enabled', ($printer->auto_print_enabled ?? true) ? '1' : '0') === '1')>
            Auto-print eligible (still requires global toggle in settings)
        </label>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            const sel = document.getElementById('printer-connection-type');
            const portWrap = document.getElementById('port-wrap');
            const hostLabel = document.getElementById('host-label');
            function sync() {
                const v = sel.value;
                const net = v === 'network_escpos';
                portWrap.style.display = net ? '' : 'none';
                hostLabel.textContent = net ? 'IP or hostname' : 'Raw device path (server)';
            }
            sel.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
