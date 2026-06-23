<x-app-layout title="ตีกลับ / แก้ไข — ผู้บริหาร">
    <div class="role-dashboard">
        @include('shared.dashboard.role_header', [
            'kicker' => 'ภาพรวม / ผู้บริหาร',
            'title'  => 'ตีกลับ / แก้ไข',
            'desc'   => 'รายวิชาที่ตีกลับไปแล้ว — รอหัวหน้าวิชาแก้ไขและส่งขออนุมัติใหม่',
        ])

        @if($rejectedOfferings->isEmpty())
            @include('shared.dashboard.role_empty', [
                'icon'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
                'title' => 'ไม่มีรายวิชาที่ตีกลับ',
                'desc'  => 'รายวิชาที่คุณตีกลับให้แก้ไขจะแสดงที่นี่จนกว่าหัวหน้าวิชาจะส่งใหม่',
            ])
        @else
            <div class="card">
                <div class="card-hdr">
                    <div>
                        <div class="card-ttl">รายวิชาที่ตีกลับ</div>
                        <div class="caption" style="margin-top:4px;">{{ $rejectedOfferings->count() }} รายการรอแก้ไขและส่งใหม่</div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table data-testid="approver-rejected-table">
                        <thead>
                            <tr>
                                <th>รายวิชา</th>
                                <th>หัวหน้าวิชา</th>
                                <th>เหตุผลที่ตีกลับ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rejectedOfferings as $offering)
                                @php $lastReject = $offering->approvals->firstWhere('action', 'reject'); @endphp
                                <tr data-testid="approver-rejected-row">
                                    <td>
                                        <div style="font-weight:700;color:var(--fg-1);">{{ $offering->course?->course_code }}</div>
                                        <div class="body-sm" style="margin-top:3px;">{{ $offering->course?->name_th ?? $offering->course?->name_en ?? '-' }}</div>
                                        <div class="caption" style="margin-top:4px;">ปีการศึกษา {{ $offering->academicYear?->name }}</div>
                                    </td>
                                    <td class="body-sm">{{ $offering->coordinator?->formatted_name ?? $offering->coordinator?->name ?? '-' }}</td>
                                    <td class="body-sm" style="max-width:340px;color:var(--status-conflict-fg);">
                                        {{ $offering->rejection_reason ?? $lastReject?->comment ?? '-' }}
                                        @if($lastReject?->created_at)
                                            <div class="caption" style="margin-top:3px;color:var(--fg-3);">{{ $lastReject->created_at->format('d/m/Y H:i') }}</div>
                                        @endif
                                    </td>
                                    <td style="text-align:right;white-space:nowrap;">
                                        <a href="{{ route('approver.offerings.show', $offering) }}" class="btn btn-secondary btn-sm" data-testid="approver-rejected-view">ดูรายละเอียด</a>
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
