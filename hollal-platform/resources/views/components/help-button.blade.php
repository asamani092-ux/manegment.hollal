@props(['screen' => ''])

@php
    $topic = null;
    if ($screen !== '' && \App\Models\ReferenceList::query()->where('key', 'help_topics')->exists()) {
        $topic = app(\App\Services\ReferenceListService::class)->item('help_topics', $screen);
    }
    $canManage = auth()->check() && auth()->user()->can('settings.help.manage');
    $panelId = 'help-panel-'.preg_replace('/[^a-z0-9_-]/i', '-', $screen);
@endphp

@if ($topic)
    <button type="button" class="ds-btn ds-btn-sm" data-help-open="{{ $panelId }}">شرح</button>
    @teleport('body')
        <div class="ds-help-overlay" id="{{ $panelId }}-overlay" hidden>
            <aside id="{{ $panelId }}" class="ds-card ds-help-panel" role="dialog" aria-modal="true">
                <h3>{{ $topic->attributes['title_ar'] ?? $topic->name_ar }}</h3>
                <div>{!! \Illuminate\Support\Str::markdown((string) ($topic->attributes['body_ar'] ?? '')) !!}</div>
                @if (! empty($topic->attributes['steps']) && is_array($topic->attributes['steps']))
                    <ol>
                        @foreach ($topic->attributes['steps'] as $step)
                            <li>{{ is_array($step) ? ($step['text'] ?? '') : $step }}</li>
                        @endforeach
                    </ol>
                @endif
                <button type="button" class="ds-btn ds-btn-sm" data-help-close="{{ $panelId }}">إغلاق</button>
            </aside>
        </div>
    @endteleport
@elseif ($canManage)
    <a class="ds-btn ds-btn-sm" href="{{ route('settings.lists', ['list' => 'help_topics']) }}">أضف شرحًا</a>
@endif

@once
    <script>
        function helpOverlay(id) {
            return document.getElementById(id + '-overlay');
        }
        document.addEventListener('click', function (event) {
            var open = event.target.closest('[data-help-open]');
            if (open) {
                var overlay = helpOverlay(open.getAttribute('data-help-open'));
                if (overlay) overlay.hidden = false;
            }
            if (event.target.classList && event.target.classList.contains('ds-help-overlay')) {
                event.target.hidden = true;
            }
            var close = event.target.closest('[data-help-close]');
            if (close) {
                var overlay = helpOverlay(close.getAttribute('data-help-close'));
                if (overlay) overlay.hidden = true;
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            document.querySelectorAll('.ds-help-overlay').forEach(function (overlay) {
                overlay.hidden = true;
            });
        });
    </script>
@endonce
