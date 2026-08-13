<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 24px; }
        {!! $pdfFontFace !!}
        body {
            font-family: Amiri, dejavu sans, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #2a3f5f;
            direction: rtl;
            unicode-bidi: embed;
            text-align: right;
        }
        h1 { font-size: 18px; color: #005c7b; margin-bottom: 4px; }
        h2 { font-size: 14px; color: #005c7b; margin-top: 18px; border-bottom: 1px solid #e8eef5; padding-bottom: 4px; }
        .meta { color: #788fa0; font-size: 11px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #e8eef5; padding: 6px 8px; text-align: right; vertical-align: top; }
        th { background: #f8fafb; color: #005c7b; font-size: 11px; }
        .section { margin-bottom: 16px; }
        ul { margin: 4px 0; padding-right: 16px; }
        .sig-line { display: inline-block; min-width: 110px; border-bottom: 1px solid #2a3f5f; height: 22px; }
    </style>
</head>
<body>
    <h1>محضر اجتماع: {{ $meeting->title }}</h1>
    <div class="meta">
        التاريخ: {{ $meeting->scheduled_at?->format('Y-m-d H:i') ?? '—' }}
        @if ($meeting->location)
            | المكان: {{ $meeting->location }}
        @endif
        @if ($meeting->link)
            | رابط عن بُعد: {{ $meeting->link }}
        @endif
    </div>

    @if ($meeting->agenda)
        <div class="section">
            <h2>جدول الأعمال</h2>
            <p>{{ $meeting->agenda }}</p>
        </div>
    @endif

    <div class="section">
        <h2>الحضور والتوقيع</h2>
        <table>
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>المسمى الوظيفي</th>
                    <th>التوقيع</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($meeting->attendees as $attendee)
                    <tr>
                        <td>{{ $attendee->name }}</td>
                        <td>{{ $attendee->profile?->job_title ?? '—' }}</td>
                        <td><span class="sig-line">&nbsp;</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">—</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>بنود المحضر</h2>
        <table>
            <thead>
                <tr>
                    <th>الموضوع</th>
                    <th>النقاش</th>
                    <th>القرار</th>
                    <th>المسؤول</th>
                    <th>الاستحقاق</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($meeting->items as $item)
                    <tr>
                        <td>{{ $item->topic }}</td>
                        <td>{{ $item->discussion_summary ?? '—' }}</td>
                        <td>{{ $item->decision ?? '—' }}</td>
                        <td>{{ $item->responsible?->name ?? '—' }}</td>
                        <td>{{ $item->due_date?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">لا توجد بنود</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($openDecisions->isNotEmpty())
        <div class="section">
            <h2>قرارات مفتوحة (مرجع)</h2>
            <table>
                <thead>
                    <tr>
                        <th>الاجتماع</th>
                        <th>القرار</th>
                        <th>المسؤول</th>
                        <th>الاستحقاق</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($openDecisions as $decision)
                        <tr>
                            <td>{{ $decision->meeting?->title ?? '—' }}</td>
                            <td>{{ $decision->decision }}</td>
                            <td>{{ $decision->responsible?->name ?? '—' }}</td>
                            <td>{{ $decision->due_date?->format('Y-m-d') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
