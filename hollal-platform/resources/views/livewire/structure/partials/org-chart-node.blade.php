<li class="org-li" style="--accent: {{ $node['accent'] }}" data-collapse-mobile="{{ $node['collapse_mobile'] ? '1' : '0' }}" data-org-label="{{ $node['title'] }} {{ $node['head'] }} @foreach ($node['occupants'] as $person) {{ $person['name'] }} @endforeach">
    <article class="org-card org-card--{{ $node['type'] }} {{ $node['vacant'] ? 'is-vacant' : '' }}">
        @if ($node['children'] !== [])
            <button type="button" class="org-toggle" data-org-toggle>
                <span class="org-toggle-open">−</span>
                <span class="org-toggle-closed">+ {{ $node['descendants'] }}</span>
            </button>
        @endif
        @if (in_array($node['type'], ['top', 'job'], true))
            <button type="button" class="org-title" wire:click="openDrawer({{ $node['id'] }})">{{ $node['title'] }}</button>
            @if ($node['vacant'])
                <p class="org-person">{{ $node['title'] }} — شاغر</p>
            @else
                @foreach ($node['occupants'] as $person)
                    <a class="org-person" href="{{ route('users.profile', $person['id']) }}">
                        <span class="org-avatar">{{ $person['initials'] }}</span>
                        {{ $node['type'] === 'job' ? $node['title'].' — '.$person['name'] : $person['name'] }}
                    </a>
                @endforeach
                @if ($node['extra'] > 0)
                    <p class="org-extra">+{{ $node['extra'] }}</p>
                @endif
            @endif
        @else
            <button type="button" class="org-title" wire:click="openDrawer({{ $node['id'] }})">{{ $node['title'] }}</button>
            <p class="org-head">المسؤول: {{ $node['head'] ?: '—' }}</p>
            @if ($node['type'] === 'admin')
                <span class="org-count-chip">{{ $node['member_count'] }} موظف</span>
            @endif
        @endif
        @if ($node['role'])
            @php $roleLabel = hollal_role_label($node['role']); @endphp
            @if (! preg_match('/[A-Za-z]{3,}/', $roleLabel))
                <span class="org-role">{{ $roleLabel }}</span>
            @endif
        @endif
    </article>
    @if ($node['children'] !== [])
        <ul>
            @foreach ($node['children'] as $child)
                @include('livewire.structure.partials.org-chart-node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
