{{-- Browser preview of 80mm layout (content mirrors thermal ticket sections). --}}
<div class="mx-auto max-w-[320px] rounded border border-slate-700 bg-white p-3 font-mono text-[11px] leading-snug text-slate-900 shadow-lg">
    <div class="text-center font-bold">{{ config('app.name', 'Restaurant') }}</div>
    <div class="text-center text-[10px] uppercase text-slate-600">Kitchen ticket</div>
    <div class="my-2 border-t border-slate-300"></div>
    <div><strong>Table:</strong> 12</div>
    <div><strong>Order #</strong>3</div>
    <div><strong>Time</strong> {{ now()->format('Y-m-d H:i') }}</div>
    <div><strong>Guest</strong> Alex</div>
    <div class="my-2 border-t border-slate-300"></div>
    <div class="font-bold">2x</div>
    <div>Ramen · shoyu</div>
    <div class="text-[10px] text-slate-600">NOTE: No scallions</div>
    <div class="my-2 border-t border-slate-300"></div>
    <div class="text-center text-slate-600">Thank you</div>
</div>
