<div {{ $attributes->merge(['class' => 'ds-table-wrap ds-table-scroll']) }}>
    <table class="ds-table">
        @if (isset($head))
            <thead>{{ $head }}</thead>
        @endif
        <tbody>{{ $slot }}</tbody>
    </table>
</div>
