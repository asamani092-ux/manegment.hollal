@props(['request' => null])

@if ($request)
    <ol class="ds-journey-steps">
        @foreach ($request->steps as $step)
            <li>
                {{ $step->definition['legacy_stage'] ?? $step->definition['label_ar'] ?? ('خطوة '.$step->step_index) }}
                — {{ $step->status }}
                @if ($step->acted_on_behalf_of)
                    — بالإنابة عن {{ $step->acted_on_behalf_of }}
                @endif
                @if ($step->note)
                    — {{ $step->note }}
                @endif
            </li>
        @endforeach
    </ol>
@endif
