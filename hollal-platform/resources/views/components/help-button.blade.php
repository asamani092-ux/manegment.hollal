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
    <aside id="{{ $panelId }}" hidden class="ds-card" style="position:fixed;inset-inline-start:1rem;top:4rem;width:min(24rem,90vw);z-index:40;">
        <h3>{{ $topic->attributes['title_ar'] ?? $topic->name_ar }}</h3>
        <div>{!! \Illuminate\Support\Str::markdown((string) ($topic->attributes['body_ar'] ?? '')) !!}</div>
        @if (! empty($topic->attributes['steps']) && is_array($topic->attributes['steps']))
            <ol>
                @foreach ($topic->attributes['steps'] as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        @endif
        <button type="button" class="ds-btn ds-btn-sm" data-help-close="{{ $panelId }}">إغلاق</button>
    </aside>
@elseif ($canManage)
    <a class="ds-btn ds-btn-sm" href="{{ route('settings.lists', ['list' => 'help_topics']) }}">أضف شرحًا</a>
@endif

@once
    <script>
        document.addEventListener('click', function (event) {
            var open = event.target.closest('[data-help-open]');
            if (open) {
                var panel = document.getElementById(open.getAttribute('data-help-open'));
                if (panel) panel.hidden = false;
            }
            var close = event.target.closest('[data-help-close]');
            if (close) {
                var panel = document.getElementById(close.getAttribute('data-help-close'));
                if (panel) panel.hidden = true;
            }
        });
    </script>
@endonce
