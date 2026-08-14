{{-- Global toast: session flashes + Livewire 'toast' and 'ds-toast' events --}}
<div class="ds-toast-container" id="ds-toast-root" dir="rtl" aria-live="polite">
    @if (session('success'))
        <div class="ds-toast ds-toast-success" data-ds-toast>{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="ds-toast ds-toast-error" data-ds-toast>{{ session('error') }}</div>
    @endif
</div>

<script>
    (function () {
        const DISMISS_MS = 4000;
        const FADE_MS = 280;

        function dismissToast(el) {
            if (!el || el.dataset.dismissing === '1') {
                return;
            }
            el.dataset.dismissing = '1';
            el.classList.add('ds-toast-hide');
            setTimeout(() => el.remove(), FADE_MS);
        }

        function armToast(el) {
            if (!el || el.dataset.armed === '1') {
                return;
            }
            el.dataset.armed = '1';

            if (!el.querySelector('.ds-toast-dismiss')) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ds-toast-dismiss';
                btn.setAttribute('aria-label', 'إغلاق');
                btn.textContent = '×';
                btn.addEventListener('click', () => dismissToast(el));
                el.appendChild(btn);
            }

            setTimeout(() => dismissToast(el), DISMISS_MS);
        }

        function armExisting() {
            document.querySelectorAll('#ds-toast-root [data-ds-toast], #ds-toast-root .ds-toast').forEach(armToast);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', armExisting);
        } else {
            armExisting();
        }

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
                el.dataset.dsToast = '';
                el.textContent = data?.message ?? '';
                root.appendChild(el);
                armToast(el);
            };

            Livewire.on('toast', showToast);
            Livewire.on('ds-toast', showToast);
        });
    })();
</script>
