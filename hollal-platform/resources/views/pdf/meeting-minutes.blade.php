<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 18mm 15mm; }
        {!! $fontFaceCss !!}
        h1 { font-size: 18px; color: #0F3446; margin: 0 0 8px; }
        h2 { font-size: 13px; color: #0F3446; margin: 16px 0 8px; border-bottom: 2px solid #C4A052; padding-bottom: 4px; }
        .zone { margin-bottom: 16px; }
        .meta { color: #5E6E73; font-size: 10px; }
        .gold-bar { height: 3px; background: linear-gradient(90deg, #C4A052, #27A588); margin: 10px 0; }
        .sig-box { min-height: 40px; }
        .header-row { width: 100%; }
        .header-row td { border: none; padding: 2px 0; }
        .num, .pdf-num { direction: ltr; unicode-bidi: embed; }
    </style>
</head>
<body>
    <div class="zone">
        {!! \App\Support\PdfArabic::header('محضر اجتماع: '.$meeting->title, includeCr: false) !!}
        <table class="header-row">
            <tr>
                <td style="width:50%;" class="meta">
                    تاريخ الاجتماع: <span class="num">{{ $meeting->scheduled_at?->format('Y-m-d') ?? '—' }}</span><br>
                    تاريخ الطباعة: <span class="num">{{ hollal_dt(now()) }}</span>
                </td>
                <td style="width:50%;"></td>
            </tr>
        </table>
    </div>

    <div class="zone">
        <h2>تفاصيل الاجتماع</h2>
        <table class="pdf-meta">
            <tr>
                <td class="pdf-label">العنوان</td>
                <td>{{ $meeting->title }}</td>
            </tr>
            <tr>
                <td class="pdf-label">الوقت</td>
                <td class="num">{{ hollal_dt($meeting->scheduled_at) }}</td>
            </tr>
            <tr>
                <td class="pdf-label">المكان</td>
                <td>
                    @if ($meeting->link)
                        عن بعد
                    @else
                        {{ $meeting->location ?: '—' }}
                    @endif
                </td>
            </tr>
            <tr>
                <td class="pdf-label">الرئيس</td>
                <td>{{ $meeting->chair?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="pdf-label">محرر الاجتماع</td>
                <td>{{ $meeting->secretary?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="pdf-label">الحضور</td>
                <td>{{ $meeting->attendees->pluck('name')->filter()->implode('، ') ?: '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="zone">
        <h2>جدول الأعمال</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:8%">#</th>
                    <th>البند</th>
                </tr>
            </thead>
            <tbody>
                @php $agendaLines = collect(preg_split('/\r\n|\r|\n/', (string) $meeting->agenda))->map(fn ($l) => trim($l))->filter()->values(); @endphp
                @forelse ($agendaLines as $i => $line)
                    <tr>
                        <td style="text-align:center;" class="num">{{ $i + 1 }}</td>
                        <td>{{ $line }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" style="text-align:center;">—</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="zone">
        <h2>بنود المحضر</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:55%;">البند</th>
                    <th>القرار أو التوصية</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($meeting->items as $item)
                    <tr>
                        <td>
                            {{ $item->topic }}
                            @if ($item->discussion_summary)
                                <br><span class="meta">{{ $item->discussion_summary }}</span>
                            @endif
                            @if ($item->responsible)
                                <br><span class="meta">المسؤول: {{ $item->responsible->name }}</span>
                            @endif
                        </td>
                        <td>{{ $item->decision ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" style="text-align:center;">لا توجد بنود</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="zone">
        <h2>الحضور والتوقيع</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:50%;">الاسم</th>
                    <th>التوقيع</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($meeting->attendees as $attendee)
                    <tr>
                        <td>{{ $attendee->name }}</td>
                        <td class="sig-box">
                            <x-signature-cell :path="$attendee->pivot->signature_image_path ?? null" :text="$attendee->pivot->signature_text ?? null" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2" style="text-align:center;">—</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($meeting->minutes_missing_signatures_reason)
            <p class="meta" style="margin-top:6px;">ملاحظة اعتماد مع نقص توقيع: {{ $meeting->minutes_missing_signatures_reason }}</p>
        @endif
    </div>

    @if ($meeting->guests->isNotEmpty())
        <div class="zone">
            <h2>الضيوف الخارجيون</h2>
            <table>
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>البريد</th>
                        <th>التأكيد</th>
                        <th>التوقيع</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($meeting->guests as $guest)
                        <tr>
                            <td>{{ $guest->name }}</td>
                            <td class="num">{{ $guest->email }}</td>
                            <td class="num">{{ hollal_dt($guest->confirmed_at) }}</td>
                            <td class="sig-box"><x-signature-cell :path="$guest->signature_image_path" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
