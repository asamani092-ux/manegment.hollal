{{-- Reusable canvas signature pad (Alpine). Emits PNG data URL to Livewire property. --}}
@props([
    'wireModel' => 'signaturePadData',
    'width' => 400,
    'height' => 160,
])

<div
    class="ds-signature-pad"
    x-data="{
        drawing: false,
        initPad() {
            const c = this.$refs.pad;
            const ctx = c.getContext('2d');
            ctx.strokeStyle = '#0F3446';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            const pos = (e) => {
                const r = c.getBoundingClientRect();
                const t = e.touches ? e.touches[0] : e;
                return { x: (t.clientX - r.left) * (c.width / r.width), y: (t.clientY - r.top) * (c.height / r.height) };
            };
            const start = (e) => { e.preventDefault(); this.drawing = true; const p = pos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); };
            const move = (e) => { if (!this.drawing) return; e.preventDefault(); const p = pos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); };
            const end = () => { this.drawing = false; $wire.set('{{ $wireModel }}', c.toDataURL('image/png')); };
            c.addEventListener('mousedown', start);
            c.addEventListener('mousemove', move);
            window.addEventListener('mouseup', end);
            c.addEventListener('touchstart', start, { passive: false });
            c.addEventListener('touchmove', move, { passive: false });
            c.addEventListener('touchend', end);
        },
        clear() {
            const c = this.$refs.pad;
            c.getContext('2d').clearRect(0, 0, c.width, c.height);
            $wire.set('{{ $wireModel }}', '');
        }
    }"
    x-init="initPad()"
>
    <canvas
        x-ref="pad"
        width="{{ (int) $width }}"
        height="{{ (int) $height }}"
        class="ds-input"
        style="touch-action: none; background: #E7EEF1; max-width: 100%; width: 100%;"
        wire:ignore
    ></canvas>
    <div style="margin-top: 0.5rem;">
        <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" @click="clear()">مسح التوقيع</button>
    </div>
</div>
