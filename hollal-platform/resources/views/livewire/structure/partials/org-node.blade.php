{{-- 09-B1 — one node of the org chart, indented by depth, level-styled. --}}
@php
    $levelClass = match ($node->level) {
        \App\Models\OrgUnit::LEVEL_TOP => 'org-node--top',
        \App\Models\OrgUnit::LEVEL_TOP_POSITION => 'org-node--top',
        \App\Models\OrgUnit::LEVEL_ADMINISTRATION => 'org-node--admin',
        \App\Models\OrgUnit::LEVEL_UNIT => 'org-node--unit',
        \App\Models\OrgUnit::LEVEL_JOB => 'org-node--job',
        default => 'org-node--unit',
    };
    $levelIcon = match ($node->level) {
        \App\Models\OrgUnit::LEVEL_ADMINISTRATION => 'fa-building',
        \App\Models\OrgUnit::LEVEL_UNIT => 'fa-sitemap',
        \App\Models\OrgUnit::LEVEL_JOB => 'fa-briefcase',
        default => 'fa-circle',
    };
    $badgeMod = $node->level === 'إدارة' ? 'admin' : ($node->level === \App\Models\OrgUnit::LEVEL_UNIT ? 'unit' : 'job');
@endphp
<tr wire:key="org-node-{{ $node->id }}" class="org-node {{ $levelClass }}" @if ($node->level === \App\Models\OrgUnit::LEVEL_ADMINISTRATION) style="--accent: {{ $adminColors[$node->id] ?? ($adminColor ?? '#1B6B93') }}" @endif>
    <td style="padding-inline-start: {{ $depth * 22 }}px">
        <span class="org-node__badge org-node__badge--{{ $badgeMod }}">
            <i class="fas {{ $levelIcon }}" aria-hidden="true"></i>
            {{ $node->level }}
        </span>
        <strong class="org-node__name">{{ $node->name }}</strong>
    </td>
    <td>
        <span class="org-node__level-pill org-node__level-pill--{{ $badgeMod }}">{{ $node->level }}</span>
    </td>
    <td>{{ $node->manager?->name ?? '—' }}</td>
    <td class="ds-ltr-num">{{ $node->members_count ?? $node->members()->count() }}</td>
    <td>
        @if ($node->isJobCard())
            <button type="button" class="ds-btn ds-btn-sm" wire:click="viewJobCard({{ $node->id }})">بطاقة الوظيفة</button>
            @can('structure.positions.manage')
                <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('structure.jobs', ['edit' => $node->id]) }}">تعديل</a>
            @endcan
        @endif
        @can('structure.manage')
            @if ($node->level === \App\Models\OrgUnit::LEVEL_ADMINISTRATION)
                <button type="button" class="ds-btn ds-btn-outline ds-btn-sm" wire:click="askDeleteUnit({{ $node->id }})">حذف</button>
                <select class="ds-input" wire:change="followTop({{ $node->id }}, $event.target.value)">
                    <option value="0">يتبع لـ</option>
                    @foreach ($topPositions as $seat)
                        <option value="{{ $seat->id }}" @selected($node->parent_id === $seat->id)>{{ $seat->name }}</option>
                    @endforeach
                </select>
            @endif
            @if (\App\Models\OrgUnit::addLabel($node->level))
                <button type="button" class="ds-btn ds-btn-sm" wire:click="openUnitModal({{ $node->id }})">
                    إضافة {{ \App\Models\OrgUnit::addLabel($node->level) }}
                </button>
            @endif
        @endcan
    </td>
</tr>

@foreach ($node->children as $child)
    @include('livewire.structure.partials.org-node', ['node' => $child, 'depth' => $depth + 1, 'adminColor' => $adminColor ?? '#1B6B93', 'adminColors' => $adminColors ?? []])
@endforeach
