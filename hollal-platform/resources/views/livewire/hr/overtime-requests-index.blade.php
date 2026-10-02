<div>
    <x-ds-page>
        <x-ds-page-header title="طلبات العمل الإضافي" screen="hr.overtime" />
        <p class="ds-text-muted">لا يُحتسب إلا العمل الإضافي المعتمد.</p>
        @foreach ($rows as $row)
            <p>{{ $row->date?->toDateString() }} — {{ $row->hours }} — {{ $row->status }}</p>
        @endforeach
    </x-ds-page>
</div>
