{{-- 09-B1 — one node of the org chart, indented by depth. --}}
<tr wire:key="org-node-{{ $node->id }}">
    <td style="padding-inline-start: {{ $depth * 18 }}px">{{ $node->name }}</td>
    <td>{{ $node->level }}</td>
    <td>{{ $node->manager?->name ?? '—' }}</td>
    <td class="ds-ltr-num">{{ $node->members()->count() }}</td>
    <td>
        @if ($node->isJobCard())
            <button type="button" class="ds-btn ds-btn-sm" wire:click="viewJobCard({{ $node->id }})">بطاقة الوظيفة</button>
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
    @include('livewire.structure.partials.org-node', ['node' => $child, 'depth' => $depth + 1])
@endforeach
