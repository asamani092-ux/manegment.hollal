@props(['request' => null])

@if ($request)
    <ol class="ds-journey-steps">
        @foreach ($request->steps as $step)
            @php
                $stage = (string) ($step->definition['legacy_stage'] ?? '');
                if ($stage === 'department_manager') {
                    $who = 'المدير المباشر';
                } elseif ($stage === 'department_head') {
                    $who = 'رئيس القسم';
                } elseif (str_starts_with($stage, 'user:')) {
                    $who = \App\Models\User::query()->whereKey((int) substr($stage, 5))->value('name') ?: 'موظف';
                } elseif (str_starts_with($stage, 'users:')) {
                    $ids = array_filter(array_map('intval', explode(',', substr($stage, 6))));
                    $who = \App\Models\User::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->implode(' أو ');
                    $who = $who !== '' ? $who : 'موظفون';
                } else {
                    $who = \App\Support\ArabicStatus::label($stage);
                }
                $state = match ($step->status) {
                    'pending' => 'بانتظار',
                    'waiting' => 'لاحقاً',
                    'approved' => 'تم',
                    default => \App\Support\ArabicStatus::label($step->status),
                };
            @endphp
            <li>
                {{ $who }} — {{ $state }}
                @if ($step->acted_on_behalf_of)
                    — بالإنابة
                @endif
            </li>
        @endforeach
    </ol>
@endif
