<x-app-layout title="รายงานภาระงานสอน">
    <div class="role-dashboard">
        @include('shared.dashboard.role_header', [
            'kicker' => 'ตารางและรายงาน / ผู้ดูแลระบบ',
            'title'  => 'รายงานภาระงานสอน',
            'desc'   => 'สรุปชั่วโมงสอนจริงรายอาจารย์ทั้งคณะ (จากตารางที่อนุมัติแล้ว) เทียบกับเกณฑ์ภาระงาน นำออกเป็นไฟล์ Excel ได้',
        ])

        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
            <div class="caption">
                @if($year)
                    ปีการศึกษา {{ $year->name }}
                @else
                    ยังไม่ได้ตั้งค่าปีการศึกษาที่ใช้งาน
                @endif
            </div>
            <a href="{{ route('admin.reports.workload.export') }}"
               class="btn btn-primary"
               data-testid="workload-export-csv">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                นำออก Excel (.csv)
            </a>
        </div>

        @include('admin.reports._summary')

        @include('shared.dashboard.instructors_workload')
    </div>
</x-app-layout>
