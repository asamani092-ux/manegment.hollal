{{-- Global toast: session flashes + Livewire 'toast' events --}}
<div class="ds-toast-container" id="ds-toast-root" dir="rtl">
    @if (session('success'))
        <div class="ds-toast ds-toast-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="ds-toast ds-toast-error">{{ session('error') }}</div>
    @endif
</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('toast', (payload) => {
            const data = Array.isArray(payload) ? payload[0] : payload;
            const type = data?.type === 'error' ? 'ds-toast-error' : 'ds-toast-success';
            const root = document.getElementById('ds-toast-root');
            const el = document.createElement('div');
            el.className = 'ds-toast ' + type;
            el.textContent = data?.message ?? '';
            root.appendChild(el);
            setTimeout(() => el.remove(), 4000);
        });
    });
</script>
