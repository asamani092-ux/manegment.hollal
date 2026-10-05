<x-ds-page>
    <x-ds-page-header title="مساحتي" />
    @if (($substituteInbox ?? collect())->isNotEmpty())
        <section class="ds-card" style="padding:0.75rem;margin-bottom:1rem">
            <h2>طلبات إنابة بانتظار موافقتك</h2>
            @foreach ($substituteInbox as $row)
                <p>{{ $row->employee?->name }} — {{ $row->from_date?->format('Y-m-d') }}</p>
            @endforeach
        </section>
    @endif
    <livewire:users.employee-profile-show :user="auth()->user()" :key="'self-file-'.auth()->id()" />
</x-ds-page>
