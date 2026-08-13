<x-app-layout title="ภาพรวม — เจ้าหน้าที่">
    <div class="role-dashboard">
        @include('shared.dashboard.role_header', [
            'kicker' => 'ภาพรวม / เจ้าหน้าที่',
            'title'  => 'ภาพรวมเจ้าหน้าที่',
            'desc'   => 'แสดงข้อมูลสรุปสถานะการทำงานและภาระงานสอนของอาจารย์ทั้งหมด',
        ])

        @include('shared.dashboard.instructors_workload', [
            'workloadReportUrl' => route('staff.reports.workload'),
        ])
    </div>
</x-app-layout>
