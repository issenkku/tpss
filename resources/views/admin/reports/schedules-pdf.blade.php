<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>รายงานตารางสอน</title>
    <style>
        @page { margin: 24px 26px 30px; }
        body {
            margin: 0;
            color: #14243a;
            font-family: "IBM Plex Sans Thai", sans-serif;
            font-size: 9px;
            line-height: 1.45;
        }
        h1 { margin: 0; color: #002454; font-size: 20px; }
        .meta { margin: 2px 0 14px; color: #52657e; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th {
            padding: 7px 6px;
            border: 1px solid #b9c8d8;
            background: #dfeaf3;
            color: #183b60;
            font-weight: bold;
            text-align: left;
        }
        td {
            padding: 7px 6px;
            border: 1px solid #cbd6e1;
            vertical-align: top;
        }
        tbody tr:nth-child(even) td { background: #f4f7fa; }
        .nowrap { white-space: nowrap; }
        .course { color: #002454; font-weight: bold; }
        .muted { color: #60718a; }
        .empty { padding: 24px; text-align: center; }
        .footer { margin-top: 8px; color: #60718a; text-align: right; }
        h2 { margin: 14px 0 6px; color: #183b60; font-size: 13px; }
        .metrics { margin-bottom: 10px; }
        .metrics td { width: 25%; background: #edf4f8; text-align: center; }
        .metric-label { color: #60718a; font-size: 8px; }
        .metric-value { color: #002454; font-size: 15px; font-weight: bold; }
        .summary-table th, .summary-table td { padding: 5px; }
        .summary-page-break { page-break-after: always; }
    </style>
</head>
<body>
    <h1>รายงานตารางสอน</h1>
    <p class="meta">
        ปีการศึกษา {{ $year?->name ?? '-' }} · {{ $selectedTerm?->name ?? 'ทุกภาคเรียน' }} ·
        สร้างเมื่อ {{ now()->timezone(config('app.timezone'))->format('d/m/Y H:i') }} น.
    </p>

    <table class="metrics">
        <tr>
            <td><span class="metric-label">รายการตาราง</span><br><span class="metric-value">{{ number_format($summaryTotals['schedule_count']) }}</span></td>
            <td><span class="metric-label">ชั่วโมงใช้ห้องรวม</span><br><span class="metric-value">{{ number_format($summaryTotals['room_hours'], 1) }}</span></td>
            <td><span class="metric-label">ห้องที่ถูกใช้งาน</span><br><span class="metric-value">{{ number_format($summaryTotals['room_count']) }}</span></td>
            <td><span class="metric-label">ภาควิชาที่มีตาราง</span><br><span class="metric-value">{{ number_format($summaryTotals['department_count']) }}</span></td>
        </tr>
    </table>

    <h2>สรุปการใช้ห้อง</h2>
    <table class="summary-table">
        <thead>
            <tr>
                <th>ห้อง / สถานที่</th>
                <th>อาคาร</th>
                <th>ความจุ</th>
                <th>จำนวนครั้ง</th>
                <th>ชั่วโมงใช้ห้อง</th>
                <th>อัตราใช้ความจุเฉลี่ย</th>
            </tr>
        </thead>
        <tbody>
            @forelse($roomUtilization as $summary)
                <tr>
                    <td><strong>{{ $summary['room_name'] }}</strong><br><span class="muted">{{ $summary['room_code'] }}</span></td>
                    <td>{{ $summary['building'] ?: '-' }}</td>
                    <td>{{ $summary['capacity'] ? number_format($summary['capacity']) : '-' }}</td>
                    <td>{{ number_format($summary['schedule_count']) }}</td>
                    <td>{{ number_format($summary['scheduled_hours'], 1) }}</td>
                    <td>{{ $summary['average_capacity_rate'] !== null ? number_format($summary['average_capacity_rate'], 1) . '%' : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">ไม่มีข้อมูลห้องตามตัวกรองที่เลือก</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>สรุปตารางสอนตามภาควิชา</h2>
    <table class="summary-table summary-page-break">
        <thead>
            <tr>
                <th>ภาควิชา</th>
                <th>รายวิชา</th>
                <th>ผู้สอน</th>
                <th>กลุ่มนักศึกษา</th>
                <th>จำนวนครั้ง</th>
                <th>ชั่วโมงรวม</th>
            </tr>
        </thead>
        <tbody>
            @forelse($departmentSummary as $summary)
                <tr>
                    <td>{{ $summary['department_name'] }}</td>
                    <td>{{ number_format($summary['course_count']) }}</td>
                    <td>{{ number_format($summary['instructor_count']) }}</td>
                    <td>{{ number_format($summary['student_group_count']) }}</td>
                    <td>{{ number_format($summary['schedule_count']) }}</td>
                    <td>{{ number_format($summary['scheduled_hours'], 1) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">ไม่มีข้อมูลภาควิชาตามตัวกรองที่เลือก</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>รายละเอียดตารางสอน</h2>

    <table>
        <thead>
            <tr>
                <th style="width:11%">วันที่ / เวลา</th>
                <th style="width:15%">รายวิชา</th>
                <th style="width:16%">กิจกรรม / หัวข้อ</th>
                <th style="width:10%">กลุ่ม</th>
                <th style="width:17%">ผู้สอน</th>
                <th style="width:12%">สถานที่</th>
                <th style="width:19%">หมายเหตุ</th>
            </tr>
        </thead>
        <tbody>
            @forelse($schedules as $schedule)
                @php
                    $startDate = $schedule->start_date?->format('d/m/Y') ?? '-';
                    $endDate = $schedule->end_date?->format('d/m/Y');
                    $dateLabel = $endDate && $endDate !== $startDate
                        ? $startDate . ' - ' . $endDate
                        : $startDate;
                @endphp
                <tr>
                    <td class="nowrap">
                        <strong>{{ $dateLabel }}</strong><br>
                        {{ substr((string) $schedule->start_time, 0, 5) }} - {{ substr((string) $schedule->end_time, 0, 5) }} น.<br>
                        <span class="muted">{{ $schedule->term?->name ?? '-' }}</span>
                    </td>
                    <td>
                        <span class="course">{{ $schedule->courseOffering?->course?->course_code }}</span><br>
                        {{ $schedule->courseOffering?->course?->name_th }}
                    </td>
                    <td>
                        <strong>{{ $schedule->activityType?->name ?? '-' }}</strong><br>
                        {{ $schedule->topic ?: '-' }}
                    </td>
                    <td>{{ $schedule->studentGroups->pluck('group_code')->implode(', ') ?: '-' }}</td>
                    <td>{{ $schedule->instructors->pluck('formatted_name')->implode(', ') ?: '-' }}</td>
                    <td>{{ $schedule->room?->room_name ?: $schedule->room?->room_code ?: '-' }}</td>
                    <td>{{ $schedule->remark ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">ไม่พบตารางสอนตามตัวกรองที่เลือก</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">สร้างจากระบบ TPSS · {{ number_format($schedules->count()) }} รายการ</p>
</body>
</html>
