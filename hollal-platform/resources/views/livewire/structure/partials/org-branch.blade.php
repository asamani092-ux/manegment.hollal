<li>
    <strong>{{ $node->name }}</strong>
    <span class="ds-badge">{{ $node->level }}</span>
    @if ($node->children?->isNotEmpty())
        <ul>
            @foreach ($node->children as $child)
                @include('livewire.structure.partials.org-branch', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
