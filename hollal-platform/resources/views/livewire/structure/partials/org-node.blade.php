{{-- 09-B1 — one node of the org chart, indented by depth, level-styled. --}}
@php
    $levelClass = match ($node->level) {
        \App\Models\OrgUnit::LEVEL_ADMINISTRATION => 'org-node--admin',
        \App\Models\OrgUnit::LEVEL_UNIT => 'org-node--unit',
        \App\Models\OrgUnit::LEVEL_JOB => 'org-node--job',
        default => 'org-node--unit',
    };
@endphp
<tr wire:key="org-node-{{ $node->id }}" class="org-node {{ $levelClass }}" style="border-inline-start: 4px solid {{ $adminColor ?? '#0F3446' }}">
    <td style="padding-inline-start: {{ $depth * 18 }}px">
        <span class="org-node__badge org-node__badge--{{ $node->level === 'إدارة' ? 'admin' : ($node->level === 'وحدة' ? 'unit' : 'job') }}">{{ $node->level }}</span>
        <strong class="org-node__name">{{ $node->name }}</strong>
    </td>
    <td>{{ $node->level }}</td>
    <td>{{ $node->manager?->name ?? '—' }}</td>
    <td class="ds-ltr-num">{{ $node->members_count ?? $node->members()->count() }}</td>
    <td>
        @if ($node->isJobCard())
            <button type="button" class="ds-btn ds-btn-sm" wire:click="viewJobCard({{ $node->id }})">بطاقة الوظيفة</button>
            @can('structure.positions.manage')
                <a class="ds-btn ds-btn-outline ds-btn-sm" href="{{ route('structure.jobs') }}?edit={{ $node->id }}">تعديل</a>
            @endcan
        @endif
        @can('structure.departments.create')
            @if (\App\Models\OrgUnit::CHILD_LEVEL[$node->level] !== null)
                <button type="button" class="ds-btn ds-btn-sm" wire:click="openUnitModal({{ $node->id }})">
                    إضافة {{ \App\Models\OrgUnit::CHILD_LEVEL[$node->level] }}
                </button>
            @endif
        @endcan
    </td>
</tr>

@foreach ($node->children as $child)
    @include('livewire.structure.partials.org-node', ['node' => $child, 'depth' => $depth + 1, 'adminColor' => $adminColor ?? '#0F3446'])
@endforeach
