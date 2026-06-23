<x-app-layout title="ตรวจสอบรายวิชา — ผู้บริหาร">
    @php
        $course = $courseOffering->course;
        $statusMeta = [
            'draft'     => ['label' => 'แบบร่าง',    'badge' => 'badge-gray'],
            'pending'   => ['label' => 'รออนุมัติ',  'badge' => 'badge-warn'],
            'published' => ['label' => 'อนุมัติแล้ว','badge' => 'badge-ok'],
            'rejected'  => ['label' => 'ตีกลับ',     'badge' => 'badge-err'],
        ];
        $meta = $statusMeta[$courseOffering->approval_status] ?? ['label' => $courseOffering->approval_status, 'badge' => 'badge-gray'];
        $fmtDate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '-';
        $fmtTime = fn ($t) => $t ? \Illuminate\Support\Str::of($t)->substr(0, 5) : '';
        $actionLabels = ['submit' => 'ส่งขออนุมัติ', 'approve' => 'อนุมัติ', 'reject' => 'ตีกลับ', 'revise' => 'ส่งกลับแก้ไข'];
        $stats = [
            ['label' => 'จำนวนกิจกรรม', 'value' => $courseOffering->schedules->count()],
            ['label' => 'ชุดผู้สอน',     'value' => $courseOffering->instructorPool->count()],
            ['label' => 'กลุ่มนักศึกษา', 'value' => $courseOffering->studentGroups->count()],
        ];
    @endphp

    <div class="role-dashboard" x-data="{ showRejectModal: false }">
        <a href="{{ route('approver.dashboard') }}" class="back-link" data-testid="approver-back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            <span>กลับไปคิวอนุมัติ</span>
        </a>

        {{-- Header --}}
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin:10px 0 18px;">
            <div style="min-width:240px;">
                <div class="caption" style="color:color-mix(in oklch, var(--brand-navy) 52%, var(--fg-3));font-weight:700;">ผู้บริหาร / ตรวจสอบรายวิชา</div>
                <h1 class="h1" style="margin:4px 0 6px;">{{ $course?->course_code }} {{ $course?->name_th }}</h1>
                <p class="body-sm" style="margin:0;">
                    {{ $course?->curriculum?->name ?? '-' }} · ปีการศึกษา {{ $courseOffering->academicYear?->name ?? '-' }}
                    · หัวหน้าวิชา {{ $courseOffering->coordinator?->formatted_name ?? $courseOffering->coordinator?->name ?? '-' }}
                </p>
            </div>
            <span class="badge {{ $meta['badge'] }}" data-testid="approver-offering-status" style="white-space:nowrap;">{{ $meta['label'] }}</span>
        </div>

        @if(session('error'))
            <div class="card" style="border-color:var(--status-conflict-border);background:var(--status-conflict-bg);margin-bottom:16px;">
                <div style="padding:12px 18px;color:var(--status-conflict-fg);font-weight:600;">{{ session('error') }}</div>
            </div>
        @endif

        {{-- สรุปย่อ (stat cards) --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px;">
            @foreach($stats as $stat)
                <div style="padding:14px 16px;background:var(--surface);border:2px solid var(--brand-navy-300, var(--border));border-top:4px solid var(--brand-navy);border-radius:10px;">
                    <div class="caption" style="color:var(--brand-navy-700, var(--fg-2));font-weight:700;">{{ $stat['label'] }}</div>
                    <div style="font-size:1.6rem;font-weight:800;color:var(--fg-1);font-variant-numeric:tabular-nums;line-height:1.2;margin-top:2px;">{{ $stat['value'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- ตารางกิจกรรม --}}
        <div class="card" style="margin-bottom:16px;">
            <div class="card-hdr"><div class="card-ttl">ตารางกิจกรรม</div></div>
            @if($courseOffering->schedules->isEmpty())
                <div style="padding:24px 20px;text-align:center;" class="caption">ยังไม่มีกิจกรรมในรายวิชานี้</div>
            @else
                <div class="table-responsive">
                    <table data-testid="approver-schedule-table">
                        <thead><tr><th>วันที่</th><th>เวลา</th><th>กิจกรรม</th><th>สถานที่</th><th>ผู้สอน</th></tr></thead>
                        <tbody>
                            @foreach($courseOffering->schedules as $s)
                                <tr>
                                    <td style="white-space:nowrap;font-variant-numeric:tabular-nums;">
                                        {{ $fmtDate($s->start_date) }}@if($s->end_date && $s->end_date != $s->start_date) – {{ $fmtDate($s->end_date) }}@endif
                                    </td>
                                    <td style="white-space:nowrap;font-variant-numeric:tabular-nums;">{{ $fmtTime($s->start_time) }}–{{ $fmtTime($s->end_time) }}</td>
                                    <td>{{ $s->topic ?? '-' }}</td>
                                    <td>{{ $s->room?->room_name ?? $s->room?->name ?? '-' }}</td>
                                    <td class="body-sm">{{ $s->instructors->map(fn ($i) => $i->formatted_name ?? $i->name)->join(', ') ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ประวัติการพิจารณา --}}
        @if($courseOffering->approvals->isNotEmpty())
            <div class="card" style="margin-bottom:16px;">
                <div class="card-hdr"><div class="card-ttl">ประวัติการพิจารณา</div></div>
                <div style="padding:8px 20px 14px;">
                    @foreach($courseOffering->approvals as $a)
                        <div style="display:flex;align-items:baseline;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);flex-wrap:wrap;">
                            <span class="caption" style="min-width:120px;font-variant-numeric:tabular-nums;">{{ $a->created_at?->format('d/m/Y H:i') }}</span>
                            <span class="badge {{ $a->action === 'approve' ? 'badge-ok' : ($a->action === 'reject' ? 'badge-err' : 'badge-gray') }}">{{ $actionLabels[$a->action] ?? $a->action }}</span>
                            <span class="body-sm" style="font-weight:600;">{{ $a->actor?->formatted_name ?? $a->actor?->name }}</span>
                            @if($a->comment)<span class="body-sm" style="color:var(--fg-2);">— {{ $a->comment }}</span>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- การพิจารณา (เฉพาะ pending) --}}
        @if($courseOffering->approval_status === 'pending')
            <div class="card" style="margin-bottom:16px;border-top:4px solid var(--brand-navy);">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:18px 20px;">
                    <div style="flex:1;min-width:200px;">
                        <div style="font-weight:700;color:var(--fg-1);">พิจารณารายวิชานี้</div>
                        <div class="caption" style="margin-top:2px;">อนุมัติเพื่อเผยแพร่ หรือตีกลับพร้อมเหตุผลให้หัวหน้าวิชาแก้ไข</div>
                    </div>
                    <form method="POST" action="{{ route('approver.offerings.approve', $courseOffering) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary" data-testid="approver-approve-button">อนุมัติ</button>
                    </form>
                    <button type="button" class="btn btn-danger" @click="showRejectModal = true" data-testid="approver-reject-button">ตีกลับ</button>
                </div>
            </div>

            {{-- modal ตีกลับ --}}
            <template x-teleport="body">
                <div class="overlay" x-show="showRejectModal" x-cloak
                     @click.self="showRejectModal = false" @keydown.escape.window="showRejectModal = false">
                    <div class="modal-center" style="max-width:460px;padding:22px;">
                        <div style="font-weight:700;font-size:1rem;color:var(--fg-1);margin-bottom:6px;">ตีกลับรายวิชา</div>
                        <div class="caption" style="margin-bottom:12px;">ระบุเหตุผลให้หัวหน้าวิชาเห็น เพื่อแก้ไขและส่งใหม่</div>
                        <form method="POST" action="{{ route('approver.offerings.reject', $courseOffering) }}">
                            @csrf
                            <textarea name="rejection_reason" rows="3" maxlength="1000" required
                                      data-testid="approver-reject-reason" placeholder="เช่น ภาระงาน อ.A เกินเกณฑ์ / มีตารางชนวันที่ ..."
                                      style="width:100%;box-sizing:border-box;border:1px solid var(--border);border-radius:8px;padding:10px;font-family:inherit;font-size:0.875rem;">{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason')<div style="color:var(--status-conflict-fg);font-size:0.8rem;margin-top:6px;">{{ $message }}</div>@enderror
                            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                                <button type="button" class="btn btn-secondary" @click="showRejectModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-danger" data-testid="approver-reject-confirm">ยืนยันตีกลับ</button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        @endif
    </div>
</x-app-layout>
