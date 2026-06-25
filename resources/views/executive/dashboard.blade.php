<x-app-layout title="คิวอนุมัติ — ผู้บริหาร">
    @php
        $approvalBadges = [
            'draft' => 'badge-gray', 'pending' => 'badge-warn',
            'published' => 'badge-ok', 'rejected' => 'badge-err',
        ];
    @endphp

    <div class="role-dashboard">
        @include('shared.dashboard.role_header', [
            'kicker' => 'ภาพรวม / ผู้บริหาร',
            'title'  => 'คิวอนุมัติ',
            'desc'   => 'ตรวจสอบภาระงานและการชนของตารางสอน เพื่ออนุมัติหรือตีกลับรายวิชาที่หัวหน้าวิชาส่งเข้ามา',
        ])

        @include('shared.dashboard.conflict_summary')

        @include('shared.dashboard.offering_pipeline')

        {{-- M6 — ภาระงานสอนรายอาจารย์ทั้งคณะ (read-only สำหรับผู้บริหาร) --}}
        <div style="margin-bottom:16px;">
            @include('shared.dashboard.instructors_workload')
        </div>

        @if(session('success'))
            <div class="card" style="border-color:var(--status-success-border);background:var(--status-success-bg);margin-bottom:16px;">
                <div style="padding:12px 18px;color:var(--status-success-fg);font-weight:600;" data-testid="approver-flash-success">{{ session('success') }}</div>
            </div>
        @endif
        @if(session('error'))
            <div class="card" style="border-color:var(--status-conflict-border);background:var(--status-conflict-bg);margin-bottom:16px;">
                <div style="padding:12px 18px;color:var(--status-conflict-fg);font-weight:600;" data-testid="approver-flash-error">{{ session('error') }}</div>
            </div>
        @endif

        @if($pendingOfferings->isEmpty())
            @include('shared.dashboard.role_empty', [
                'icon'  => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
                'title' => 'ไม่มีรายวิชารออนุมัติ',
                'desc'  => 'เมื่อหัวหน้าวิชาส่งตารางขออนุมัติ รายการจะแสดงที่นี่ให้พิจารณา',
            ])
        @else
            <div class="card">
                <div class="card-hdr">
                    <div>
                        <div class="card-ttl">รายวิชารออนุมัติ</div>
                        <div class="caption" style="margin-top:4px;">{{ $pendingOfferings->count() }} รายการรอการพิจารณา</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table data-testid="approver-pending-table">
                        <thead>
                            <tr>
                                <th>รายวิชา</th>
                                <th>หัวหน้าวิชา</th>
                                <th style="text-align:center;white-space:nowrap;">กิจกรรม</th>
                                <th style="text-align:center;white-space:nowrap;">ผู้สอน</th>
                                <th style="white-space:nowrap;">สถานะ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingOfferings as $offering)
                                <tr data-testid="approver-pending-row" data-offering="{{ $offering->getRouteKey() }}">
                                    <td>
                                        <div style="font-weight:700;color:var(--fg-1);">{{ $offering->course?->course_code }}</div>
                                        <div class="body-sm" style="margin-top:3px;">{{ $offering->course?->name_th ?? $offering->course?->name_en ?? '-' }}</div>
                                        <div class="caption" style="margin-top:4px;">ปีการศึกษา {{ $offering->academicYear?->name }}</div>
                                    </td>
                                    <td class="body-sm">{{ $offering->coordinator?->formatted_name ?? $offering->coordinator?->name ?? '-' }}</td>
                                    <td style="text-align:center;font-variant-numeric:tabular-nums;">{{ $offering->schedules_count }}</td>
                                    <td style="text-align:center;font-variant-numeric:tabular-nums;">{{ $offering->instructor_pool_count }}</td>
                                    <td><span class="badge {{ $approvalBadges[$offering->approval_status] ?? 'badge-gray' }}" style="white-space:nowrap;">รออนุมัติ</span></td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <a href="{{ route('approver.offerings.show', $offering) }}" class="btn btn-primary btn-sm" data-testid="approver-review-link">ตรวจสอบ</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
