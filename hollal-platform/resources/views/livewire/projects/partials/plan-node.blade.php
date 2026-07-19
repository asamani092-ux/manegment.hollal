{{-- 06B-B2 — one node of the project plan tree, indented by depth. --}}
<tr wire:key="plan-node-{{ $node->id }}">
    <td style="padding-inline-start: {{ $depth * 18 }}px">{{ $node->title }}</td>
    <td>{{ $node->role_label ?? '—' }}</td>
    <td>{{ $node->assignee?->name ?? ($node->entity_visible ? 'الجهة (عبر رابطها)' : '—') }}</td>
    <td dir="ltr">{{ $node->due_date?->format('Y-m-d') ?? '—' }}</td>
    <td>{{ $node->required_evidence ?? '—' }}</td>
    <td>{{ $node->final_rating ?? '—' }}</td>
</tr>

@foreach ($node->children as $child)
    @include('livewire.projects.partials.plan-node', ['node' => $child, 'depth' => $depth + 1])
@endforeach
