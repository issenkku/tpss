@php
    $approvalLabels = [
        'draft'     => ['label' => 'แบบร่าง',    'badge' => 'badge-gray'],
        'pending'   => ['label' => 'รออนุมัติ',  'badge' => 'badge-warn'],
        'published' => ['label' => 'อนุมัติแล้ว','badge' => 'badge-ok'],
        'rejected'  => ['label' => 'ตีกลับ',     'badge' => 'badge-err'],
    ];
@endphp

<x-app-layout title="จัดการรายวิชา">
    <x-async-filter scope="course-offerings">
    <div class="co-hero">
        <div class="co-hero-kicker">หัวหน้าวิชา / จัดการรายวิชา</div>
        <h1 class="co-hero-title">รายวิชาที่รับผิดชอบ</h1>
        <p class="co-hero-desc">ดูรายวิชาที่คุณดูแล จัดตารางสอน และติดตามสถานะการอนุมัติของแต่ละรายวิชา</p>
    </div>

    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
        @if($availableYears->count() > 0)
            <form method="GET"
                  action="{{ route('maker.course_offerings.index') }}"
                  class="course-offering-year-filter"
                  style="
                display:inline-flex;
                align-items:stretch;
                border:2px solid var(--brand-navy);
                border-radius:10px;
                overflow:hidden;
                background:var(--surface);
            ">
                <x-filter-select
                    id="year-filter"
                    name="year"
                    field-class="course-offering-year-field"
                    label-class="course-offering-year-label"
                    select-wrapper-class="course-offering-year-select"
                    class="tpss-custom-select"
                    data-menu-anchor=".course-offering-year-filter"
                    data-testid="offering-year-filter">
                    <x-slot:labelContent>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        ปีการศึกษา
                    </x-slot:labelContent>
                        @foreach($availableYears as $year)
                            <option value="{{ $year->id }}" @selected($year->id === $selectedYearId)>
                                ปีการศึกษา {{ $year->name }}@if($year->is_active) · ปัจจุบัน @endif
                            </option>
                        @endforeach
                </x-filter-select>
            </form>
        @endif
    </x-async-filter>

    @if($summary['total'] > 0)
        @php
            $summaryCards = [
                ['key' => 'total',     'label' => 'รายวิชาทั้งหมด',     'hint' => 'ในภาคการศึกษานี้',     'tone' => null,        'active' => true],
                ['key' => 'draft',     'label' => 'แบบร่าง',            'hint' => 'รอคุณส่งขออนุมัติ',     'tone' => 'neutral',   'active' => $summary['draft'] > 0],
                ['key' => 'pending',   'label' => 'รออนุมัติ',          'hint' => 'รอผู้บริหารพิจารณา',    'tone' => 'info',      'active' => $summary['pending'] > 0],
                ['key' => 'published', 'label' => 'อนุมัติแล้ว',        'hint' => 'ผ่านอนุมัติเรียบร้อย',  'tone' => 'success',   'active' => $summary['published'] > 0],
                ['key' => 'rejected',  'label' => 'ตีกลับ',             'hint' => 'ต้องแก้ไขและส่งใหม่',   'tone' => 'conflict',  'active' => $summary['rejected'] > 0],
            ];
        @endphp
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:16px;" data-testid="offering-summary">
            @foreach($summaryCards as $c)
                @php
                    $tone = $c['tone'];
                    if ($tone === 'neutral') {
                        // Default/idle state — gray tone (เช่น แบบร่าง)
                        $accent = 'var(--fg-3)';
                        $borderColor = 'var(--border)';
                        $labelColor = 'var(--fg-2)';
                    } elseif ($tone) {
                        $accent = "var(--status-{$tone})";
                        $borderColor = "var(--status-{$tone}-border)";
                        $labelColor = "var(--status-{$tone}-fg)";
                    } else {
                        $accent = 'var(--brand-navy)';
                        $borderColor = 'var(--brand-navy-300)';
                        $labelColor = 'var(--brand-navy-700)';
                    }
                    $dotOpacity = $c['active'] ? '1' : '0.25';
                @endphp
                <div style="
                    position:relative;
                    padding:12px 14px;
                    background:var(--surface);
                    border:2px solid {{ $borderColor }};
                    border-top:4px solid {{ $accent }};
                    border-radius:10px;
                    overflow:hidden;
                ">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;min-width:0;">
                        <div style="font-size:0.8125rem;font-weight:700;color:{{ $labelColor }};letter-spacing:0.01em;min-width:0;overflow-wrap:break-word;">
                            {{ $c['label'] }}
                        </div>
                        <span style="display:inline-block;width:9px;height:9px;background:{{ $accent }};border-radius:50%;flex-shrink:0;opacity:{{ $dotOpacity }};"></span>
                    </div>
                    <div style="font-size:1.75rem;font-weight:700;line-height:1;color:var(--fg-1);margin-top:8px;font-family:var(--font-display);letter-spacing:-0.01em;">
                        {{ $summary[$c['key']] }}
                    </div>
                    <div style="font-size:0.7rem;color:var(--fg-3);margin-top:6px;line-height:1.3;">
                        {{ $c['hint'] }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($errors->any())
        <div class="card" style="border-color:var(--status-conflict-border);background:var(--status-conflict-bg);margin-bottom:16px;">
            <div style="padding:14px 18px;color:var(--status-conflict-fg);font-weight:600;">
                {{ $errors->first() }}
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-hdr">
            <div>
                <div class="card-ttl">รายวิชาที่รับผิดชอบ</div>
                <div class="caption" style="margin-top:4px;">{{ $offerings->count() }} รายการ</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="course-offerings-table">
                <thead>
                    <tr>
                        <th>รายวิชา</th>
                        <th>หลักสูตร / ปีการศึกษา</th>
                        <th>ชั่วโมงแผน</th>
                        <th style="white-space:nowrap;">สถานะการจัดตาราง</th>
                        <th style="white-space:nowrap;">สถานะรายวิชา</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($offerings as $offering)
                        @php
                            $course      = $offering->course;
                            $year        = $offering->academicYear;
                            $phase       = $year?->phase ?? 'preparation';
                            $approval    = $offering->approval_status ?? 'draft';
                            $approvalMeta = $approvalLabels[$approval] ?? ['label' => $approval, 'badge' => 'badge-gray'];
                            $lectureHours  = $offering->planned_lecture_hours ?? $course?->lecture_hours ?? 0;
                            $labHours      = $offering->planned_lab_hours ?? $course?->lab_hours ?? 0;
                        @endphp
                        <tr class="course-offering-row">
                            <td>
                                <div style="font-weight:700;color:var(--fg-1);">{{ $course?->course_code ?? '-' }}</div>
                                <div class="body-sm" style="margin-top:3px;">{{ $course?->name_th ?? $course?->name_en ?? '-' }}</div>
                                <div style="display:flex;align-items:center;gap:8px;margin-top:5px;flex-wrap:wrap;">
                                    <span class="caption">{{ $course?->credits ?? '-' }} หน่วยกิต</span>
                                </div>
                            </td>
                            <td>
                                <div class="body-sm">{{ $course?->curriculum?->name ?? '-' }}</div>
                                <div class="caption" style="margin-top:4px;">
                                    ปีการศึกษา {{ $year?->name ?? '-' }}
                                </div>
                            </td>
                            <td style="white-space:nowrap;">
                                <div class="body-sm" style="white-space:nowrap;">
                                    บรรยาย <strong>{{ $lectureHours }}</strong> · ปฏิบัติ <strong>{{ $labHours }}</strong> ชม.
                                </div>
                            </td>
                            <td style="white-space:nowrap;">
                                @if($phase === 'scheduling')
                                    <span class="badge badge-ok" style="white-space:nowrap;">เปิดจัดตาราง</span>
                                @elseif($phase === 'published')
                                    <span class="badge badge-primary" style="white-space:nowrap;">เผยแพร่แล้ว</span>
                                @else
                                    <span class="badge badge-gray" style="white-space:nowrap;">ยังไม่เปิด</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                <span class="badge {{ $approvalMeta['badge'] }}" style="white-space:nowrap;">{{ $approvalMeta['label'] }}</span>
                            </td>
                            <td class="course-offering-action-cell">
                                <div class="course-offering-actions">
                                    @if($phase === 'scheduling')
                                        <a class="course-offering-action-link is-primary" data-testid="course-offering-schedule-link" href="{{ route('maker.course_offerings.schedules.index', $offering) }}">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="3" y="4" width="18" height="18" rx="2"/>
                                                <line x1="3" y1="10" x2="21" y2="10"/>
                                            </svg>
                                            จัดตาราง
                                        </a>
                                    @endif
                                    <a class="course-offering-action-link is-secondary" data-testid="course-offering-show-link" href="{{ route('maker.course_offerings.show', $offering) }}">
                                        รายละเอียด
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        @php
                            $emptyKey = $coordinatorEmptyStateKey ?? 'no_offerings';
                            $emptyMessages = [
                                'preparation' => [
                                    'title' => 'อยู่ในสถานะเตรียมข้อมูล',
                                    'sub' => 'ยังไม่ถึงช่วงเวลาการจัดตารางเรียน — ระบบจะเปิดรายวิชาเมื่อผู้ดูแลตั้งค่าเป็นช่วงจัดตาราง',
                                ],
                                'no_offerings' => [
                                    'title' => 'ไม่พบรายวิชาที่รับผิดชอบในรอบนี้',
                                    'sub' => 'คุณยังไม่ได้รับมอบหมายเป็นหัวหน้าวิชาในรอบ scheduling นี้ — ติดต่อผู้ดูแลระบบหากต้องการรับผิดชอบรายวิชา',
                                ],
                                'ready' => [
                                    'title' => 'ไม่พบรายวิชาที่รับผิดชอบ',
                                    'sub' => 'ลองเลือกปีการศึกษาอื่นจากตัวกรองด้านบน',
                                ],
                            ];
                            $msg = $emptyMessages[$emptyKey] ?? $emptyMessages['ready'];
                        @endphp
                        <tr>
                            <td colspan="6" style="text-align:center;padding:34px 20px;" data-empty-state="{{ $emptyKey }}">
                                <div style="font-weight:950;font-size:15px;color:var(--brand-navy);margin-bottom:4px;">{{ $msg['title'] }}</div>
                                <div style="font-weight:700;font-size:12.5px;color:var(--fg-2);line-height:1.55;max-width:520px;margin:0 auto;">{{ $msg['sub'] }}</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .co-hero {
            padding: 22px 24px;
            margin-bottom: 16px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 26%, var(--border));
            border-radius: var(--r-lg, 10px);
            background:
                radial-gradient(circle at 8% 0%, color-mix(in oklch, var(--brand-navy) 12%, transparent), transparent 32%),
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 6%, var(--surface)), var(--surface) 40%),
                var(--surface);
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.08),
                0 18px 42px -30px rgba(0, 36, 84, 0.42),
                inset 0 1px 0 color-mix(in oklch, var(--surface) 84%, transparent);
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }
        .co-hero:hover {
            border-color: color-mix(in oklch, var(--brand-navy) 36%, var(--border));
            box-shadow:
                0 2px 6px rgba(0, 36, 84, 0.08),
                0 22px 48px -30px rgba(0, 36, 84, 0.5),
                inset 0 1px 0 color-mix(in oklch, var(--surface) 84%, transparent);
        }
        .co-hero-kicker {
            font-size: 12px;
            font-weight: 700;
            line-height: 1.35;
            color: color-mix(in oklch, var(--brand-navy) 52%, var(--fg-3));
            margin-bottom: 4px;
        }
        .co-hero-title {
            margin: 0;
            font-family: var(--font-display);
            font-size: 24px;
            font-weight: 800;
            line-height: 1.25;
            color: var(--fg-1);
        }
        .co-hero-desc {
            margin: 8px 0 0;
            max-width: 72ch;
            color: var(--fg-2);
            font-size: 13px;
            line-height: 1.6;
        }

        .course-offerings-table {
            table-layout: auto;
        }

        [data-testid="offering-summary"] > div {
            background:
                radial-gradient(circle at 92% 14%, color-mix(in oklch, var(--brand-navy) 10%, transparent), transparent 34%),
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface)) !important;
            border-width: 1px !important;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.08),
                0 14px 30px -24px rgba(0, 36, 84, 0.46);
            transition: transform 150ms ease, border-color 150ms ease, box-shadow 150ms ease;
        }

        [data-testid="offering-summary"] > div:hover {
            transform: translateY(-2px);
            border-color: color-mix(in oklch, var(--brand-navy) 34%, var(--border)) !important;
            box-shadow:
                0 2px 5px rgba(0, 36, 84, 0.1),
                0 20px 36px -24px rgba(0, 36, 84, 0.62);
        }

        .course-offerings-table th {
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 10%, var(--surface)), color-mix(in oklch, var(--brand-navy) 5%, var(--surface))) !important;
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 20%, var(--border)) !important;
            color: color-mix(in oklch, var(--brand-navy) 76%, var(--fg-2));
        }

        .course-offering-row {
            height: 104px;
            transition: background 150ms ease, box-shadow 150ms ease;
        }

        .course-offering-row > td {
            vertical-align: middle;
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 10%, var(--border-subtle));
        }

        .course-offering-row:hover > td {
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        }

        .course-offering-row:hover > td:first-child {
            box-shadow: inset 3px 0 0 var(--brand-navy);
        }

        .course-offering-action-cell {
            width: 148px;
            text-align: right;
        }

        .course-offering-actions {
            min-height: 76px;
            display: inline-flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: center;
            gap: 8px;
            width: 120px;
        }

        .course-offering-action-link {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 7px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 600;
            line-height: 1.2;
            text-decoration: none;
            white-space: nowrap;
            box-sizing: border-box;
            transition: transform 150ms ease, box-shadow 150ms ease, background 150ms ease, border-color 150ms ease, color 150ms ease;
        }

        .course-offering-action-link:hover,
        .course-offering-action-link:focus-visible {
            transform: translateY(-1px);
            box-shadow: 0 12px 22px -18px rgba(0, 36, 84, 0.62);
            outline: none;
        }

        .course-offering-action-link svg {
            flex: 0 0 auto;
        }

        .course-offering-action-link.is-primary {
            background: var(--brand-navy);
            color: #fff;
            border-color: var(--brand-navy);
        }

        .course-offering-action-link.is-secondary {
            background: var(--surface);
            color: var(--brand-navy);
            border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border));
        }

        .course-offering-action-link.is-secondary:hover,
        .course-offering-action-link.is-secondary:focus-visible {
            background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
            border-color: color-mix(in oklch, var(--brand-navy) 36%, var(--border));
        }

        .course-offering-year-select {
            width: clamp(210px, 24vw, 300px);
            min-width: 0;
        }

        .course-offering-year-field {
            display: contents;
        }

        .course-offering-year-filter .tpss-select {
            height: 40px;
        }

        .course-offering-year-filter .tpss-select-trigger {
            min-height: 40px;
            height: 40px;
            border: 0;
            border-radius: 0;
            background: var(--surface);
            box-shadow: none;
            padding: 8px 12px 8px 16px;
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--brand-navy);
        }

        .course-offering-year-filter .tpss-select-trigger:hover,
        .course-offering-year-filter .tpss-select-trigger:focus {
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
            box-shadow: none;
        }

        .course-offering-year-filter .tpss-select-menu {
            min-width: 260px;
        }

        @media (max-width: 640px) {
            .course-offering-year-filter {
                width: 100%;
            }

            .course-offering-year-filter label {
                flex: 0 0 auto;
            }

            .course-offering-year-select {
                flex: 1 1 auto;
                width: auto;
            }
        }
    </style>
    </div>
</x-app-layout>
