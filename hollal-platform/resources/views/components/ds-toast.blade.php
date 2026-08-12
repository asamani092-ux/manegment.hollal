{{-- Global toast: session flashes + Livewire 'toast' and 'ds-toast' events --}}
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
        const showToast = (payload) => {
            const data = Array.isArray(payload) ? payload[0] : payload;
            const type = data?.type === 'error' ? 'ds-toast-error' : 'ds-toast-success';
            const root = document.getElementById('ds-toast-root');
            if (!root) {
                return;
            }
            const el = document.createElement('div');
            el.className = 'ds-toast ' + type;
            el.textContent = data?.message ?? '';
            root.appendChild(el);
            setTimeout(() => el.remove(), 4000);
        };

        Livewire.on('toast', showToast);
        Livewire.on('ds-toast', showToast);
    });
</script>
