@php
    $summary = $conflictSummary ?? ['status' => 'disabled', 'total' => null, 'by_type' => []];
    $status = $summary['status'] ?? 'disabled';
    $isReady = $status === 'ready';
    $total = $summary['total'];
    $byType = $summary['by_type'] ?? [];

    $statusLabels = [
        'ready'    => 'พร้อมแล้ว',
        'disabled' => 'ปิดใช้งาน',
        'missing'  => 'ยังไม่ได้ประมวลผล',
        'pending'  => 'กำลังประมวลผล',
        'computing'=> 'กำลังประมวลผล',
    ];
    $statusLabel = $statusLabels[$status] ?? 'กำลังประมวลผล';
    $statusPill = $isReady ? 'p-success' : ($status === 'disabled' ? 'badge-gray' : 'p-warning');
@endphp

<div class="card" data-testid="dashboard-conflict-summary">
    <div class="card-hdr">
        <div>
            <div class="card-ttl">สรุปการชนของตารางสอน</div>
            <div style="font-size:12px;color:var(--fg-3);margin-top:2px;">
                @if($currentAcademicYear ?? null)
                    ปีการศึกษา {{ $currentAcademicYear->name }}
                @else
                    ยังไม่มีปีการศึกษาที่ใช้งาน
                @endif
            </div>
        </div>
        <span class="pill {{ $statusPill }}">{{ $statusLabel }}</span>
    </div>
    <div style="padding:16px 18px;">
        @if($isReady)
            <div style="font-size:28px;font-weight:850;color:{{ (int) $total > 0 ? 'var(--status-conflict-fg)' : 'var(--status-success-fg)' }};line-height:1;font-variant-numeric:tabular-nums;">
                {{ number_format((int) $total) }}
            </div>
            <div style="font-size:12px;color:var(--fg-3);margin-top:6px;">
                {{ (int) $total > 0 ? 'รายการที่ตรวจพบการชน — ควรตรวจสอบก่อนอนุมัติ' : 'ไม่พบการชนในตารางสอน' }}
            </div>
            @if((int) $total > 0)
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
                    <span class="pill p-conflict">ผู้สอนซ้ำ {{ $byType['instructor_overlap'] ?? 0 }}</span>
                    <span class="pill p-warning">ห้องซ้ำ {{ $byType['room_overlap'] ?? 0 }}</span>
                    <span class="pill p-info">กลุ่มซ้ำ {{ $byType['group_overlap'] ?? 0 }}</span>
                </div>
            @endif
        @else
            <div style="font-size:13px;color:var(--fg-2);line-height:1.55;">
                @if($status === 'disabled')
                    ระบบตรวจการชนแบบเรียลไทม์ปิดอยู่
                @else
                    ระบบกำลังประมวลผลการตรวจการชน — กรุณารอสักครู่แล้วรีเฟรช
                @endif
            </div>
        @endif
    </div>
</div>
