<div>
    <x-ds-page>
        <x-ds-page-header title="وثائق بانتظار المراجعة" screen="hr.document-reviews" />
        @forelse ($rows as $row)
            <p wire:key="doc-{{ $row->id }}">
                {{ $row->user?->name }} — {{ $row->type }} — قيد المراجعة
                @if ($row->file_path)
                    <a class="ds-link" href="{{ route('employee-documents.files.download', $row) }}?inline=1" target="_blank" rel="noopener">معاينة</a>
                @endif
                <button type="button" class="ds-btn ds-btn-sm" wire:click="approve({{ $row->id }})">اعتماد</button>
                <button type="button" class="ds-btn ds-btn-sm" wire:click="reject({{ $row->id }}, 'غير مكتملة')">رفض</button>
            </p>
        @empty
            <p>لا توجد وثائق بانتظار المراجعة</p>
        @endforelse
    </x-ds-page>
</div>
