@php
    $course           = $courseOffering->course;
    $academicYear     = $courseOffering->academicYear;
    $canEdit          = $academicYear?->phase === 'scheduling';
    $lectureHours     = $course?->lecture_hours ?? 0;
    $labHours         = $course?->lab_hours ?? 0;
    $courseInfoErrorKeys = [];
    $instructorErrorKeys = ['user_id', 'course_role_id', 'instructor_pool'];
    $studentGroupErrorKeys = ['cohort_group_id', 'group_code', 'student_count', 'group_count', 'group_details', 'rows', 'student_groups'];
    $courseInfoErrorKey = collect($courseInfoErrorKeys)->first(fn ($key) => $errors->has($key));
    $instructorErrorKey = collect($instructorErrorKeys)->first(fn ($key) => $errors->has($key));
    $studentGroupErrorKey = collect($studentGroupErrorKeys)->first(fn ($key) => $errors->has($key));
    $errorSection = session('error_section');
@endphp

<script>
    (function () {
        var key = 'tpss.courseOffering.scrollY.{{ $courseOffering->id }}';
        try {
            if ('scrollRestoration' in history) {
                history.scrollRestoration = 'manual';
            }

            var saved = window.sessionStorage.getItem(key);
            if (saved !== null) {
                window.sessionStorage.removeItem(key);
                var top = parseInt(saved, 10);
                if (Number.isFinite(top) && top >= 0) {
                    window.scrollTo(0, top);
                    requestAnimationFrame(function () { window.scrollTo(0, top); });
                }
            }

            window.tpssRememberCourseOfferingScroll = function () {
                try {
                    window.sessionStorage.setItem(key, String(window.scrollY || window.pageYOffset || 0));
                } catch (error) {}
            };
        } catch (error) {}
    })();
</script>

<x-app-layout title="รายละเอียดรายวิชา">
    <style>
        .approval-workflow-panel {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 18px;
            padding: 18px 20px;
            margin-bottom: 18px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
        }
        .approval-workflow-panel.is-rejected {
            background: color-mix(in oklch, var(--status-conflict-bg) 58%, var(--surface));
            border-color: var(--status-conflict-border);
        }
        .approval-workflow-panel.is-pending {
            background: color-mix(in oklch, var(--status-info-bg) 54%, var(--surface));
            border-color: var(--status-info-border);
        }
        .approval-workflow-panel.is-published {
            background: color-mix(in oklch, var(--status-success-bg) 54%, var(--surface));
            border-color: var(--status-success-border);
        }
        .approval-workflow-status {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            min-width: 0;
        }
        .approval-workflow-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: var(--brand-navy);
            color: var(--surface);
            flex: 0 0 auto;
        }
        .approval-workflow-panel.is-rejected .approval-workflow-icon {
            background: var(--status-conflict-fg);
        }
        .approval-workflow-panel.is-pending .approval-workflow-icon {
            background: var(--status-info-fg);
        }
        .approval-workflow-panel.is-published .approval-workflow-icon {
            background: var(--status-success-fg);
        }
        .approval-workflow-title {
            margin: 0;
            color: var(--fg-1);
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.45;
        }
        .approval-workflow-copy {
            margin: 4px 0 0;
            color: var(--fg-2);
            font-size: 0.875rem;
            line-height: 1.6;
            max-width: 74ch;
        }
        .approval-reason-box {
            margin-top: 10px;
            padding: 10px 12px;
            background: color-mix(in oklch, var(--surface) 72%, var(--status-conflict-bg));
            border: 1px solid color-mix(in oklch, var(--status-conflict-border) 72%, var(--surface));
            border-radius: 8px;
            color: var(--status-conflict-fg);
            font-size: 0.9rem;
            font-weight: 650;
            line-height: 1.65;
            overflow-wrap: anywhere;
        }
        .approval-workflow-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        .approval-workflow-actions .btn {
            min-height: 44px;
        }
        @media (max-width: 860px) {
            .approval-workflow-panel {
                grid-template-columns: 1fr;
                align-items: stretch;
            }
            .approval-workflow-actions {
                justify-content: flex-start;
            }
        }
    </style>

    @if($canEdit)
        <script>
            document.addEventListener('alpine:init', () => {
                if (! Alpine.store('offeringPage')) {
                    const COLLAPSE_KEY = 'tpss.offeringPage.collapsed';
                    let saved = {};
                    try { saved = JSON.parse(localStorage.getItem(COLLAPSE_KEY) || '{}') || {}; } catch (e) {}
                    Alpine.store('offeringPage', {
                        editing: {
                            courseInfo: false,
                            instructors: false,
                            // V4: หัวหน้าวิชาจัดกลุ่มเป็นงานหลักตรงนี้ → เปิดให้แก้ไขได้ทันที (ไม่ต้องกด "แก้ไข" ก่อน)
                            studentGroups: true,
                        },
                        collapsed: {
                            courseInfo: !!saved.courseInfo,
                            instructors: !!saved.instructors,
                            studentGroups: !!saved.studentGroups,
                        },
                        toggleCollapse(key) {
                            this.collapsed[key] = !this.collapsed[key];
                            try { localStorage.setItem(COLLAPSE_KEY, JSON.stringify(this.collapsed)); } catch (e) {}
                        },
                        startEditing(key) {
                            // เปิด edit mode → expand section อัตโนมัติ
                            this.editing[key] = !this.editing[key];
                            if (this.editing[key] && this.collapsed[key]) {
                                this.collapsed[key] = false;
                                try { localStorage.setItem('tpss.offeringPage.collapsed', JSON.stringify(this.collapsed)); } catch (e) {}
                            }
                        },
                    });
                }
            });
        </script>

        <style>
            /* ── Section collapse chevron ── */
            .section-collapse-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
                margin-left: 4px;
                border: 1px solid var(--border);
                background: var(--bg-2);
                color: var(--fg-2);
                border-radius: 8px;
                cursor: pointer;
                font-family: inherit;
                transition: background 0.15s, color 0.15s, border-color 0.15s, transform 0.2s;
            }
            .section-collapse-toggle:hover {
                background: var(--brand-navy-50);
                color: var(--brand-navy);
                border-color: var(--brand-navy-300);
            }
            .section-collapse-toggle svg {
                transition: transform 0.2s ease;
            }
            .section-collapse-toggle.is-collapsed svg {
                transform: rotate(-90deg);
            }
            .section-collapse-summary {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                flex-wrap: wrap;
                margin-top: 4px;
                font-size: 0.75rem;
                color: var(--fg-3);
                font-weight: 500;
            }
            .section-collapse-summary strong {
                color: var(--fg-2);
                font-weight: 600;
            }

            /* ── Section quick toggle ("แก้ไข" ใน card-hdr ของแต่ละ section) ── */
            .section-edit-quick-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                height: 32px;
                padding: 0 14px;
                margin-left: auto;
                border: 1px solid var(--border);
                background: var(--bg-2);
                color: var(--fg-2);
                border-radius: 8px;
                font-size: 13px;
                font-weight: 600;
                line-height: 1;
                cursor: pointer;
                font-family: inherit;
                outline: none;
                white-space: nowrap;
                transition: all 0.15s;
                flex-shrink: 0;
            }
            .section-edit-quick-toggle:hover {
                border-color: var(--brand-navy);
                color: var(--brand-navy);
                background: var(--brand-navy-50);
            }
            /* override .is-locked-section button opacity — ปุ่มนี้ต้องเด่นเสมอ */
            .is-locked-section button.section-edit-quick-toggle {
                opacity: 1 !important;
            }
        </style>
    @endif

    @php
        $phase = $courseOffering->academicYear?->phase ?? 'preparation';
        $instructorCount = $courseOffering->instructorPool->count();
        $studentTotal = $courseOffering->studentGroups->sum('student_count');
        $scheduleCount = $courseOffering->schedules_count ?? 0;
        $approvalLabels = [
            'draft'     => ['label' => 'แบบร่าง',    'tone' => null],
            'pending'   => ['label' => 'รออนุมัติ',  'tone' => 'info'],
            'published' => ['label' => 'อนุมัติแล้ว','tone' => 'success'],
            'rejected'  => ['label' => 'ตีกลับ',     'tone' => 'conflict'],
        ];
        $approval = $courseOffering->approval_status ?? 'draft';
        $approvalMeta = $approvalLabels[$approval] ?? ['label' => $approval, 'tone' => null];
    @endphp

    {{-- Header --}}
    <div style="margin-bottom:16px;">
        <a href="{{ route('maker.course_offerings.index') }}" class="back-link" data-testid="back-to-offerings">
            <span class="back-link-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
            </span>
            <span>กลับไปรายการรายวิชา</span>
        </a>
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:220px;">
                <div style="font-size:12px;font-weight:700;line-height:1.35;color:color-mix(in oklch, var(--brand-navy) 52%, var(--fg-3));margin-bottom:4px;">หัวหน้าวิชา / รายละเอียดรายวิชา</div>
                <h1 class="h1" style="margin:0 0 6px;">{{ $course?->course_code ?? '-' }} {{ $course?->name_th ?? $course?->name_en ?? '' }}</h1>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <p class="body-sm" style="margin:0;">
                        {{ $course?->curriculum?->name ?? '-' }} · ปีการศึกษา {{ $academicYear?->name ?? '-' }}
                    </p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                @if($phase === 'scheduling')
                    <span class="badge" style="background:var(--status-success-bg);color:var(--status-success-fg);border:1px solid var(--status-success-border);">เปิดจัดตาราง</span>
                @elseif($phase === 'published')
                    <span class="badge badge-primary">เผยแพร่แล้ว</span>
                @else
                    <span class="badge badge-gray">ยังไม่เปิดจัดตาราง</span>
                @endif
                @if($approvalMeta['tone'])
                    <span class="badge" style="background:var(--status-{{ $approvalMeta['tone'] }}-bg);color:var(--status-{{ $approvalMeta['tone'] }}-fg);border:1px solid var(--status-{{ $approvalMeta['tone'] }}-border);">
                        {{ $approvalMeta['label'] }}
                    </span>
                @else
                    <span class="badge badge-gray">{{ $approvalMeta['label'] }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- M11 — แถบส่งขออนุมัติ / สถานะอนุมัติ --}}
    @php $approvalStatus = $courseOffering->approval_status ?? 'draft'; @endphp
    <div x-data="{ showSubmitModal: false }" style="margin-bottom:16px;" data-testid="offering-approval-bar">
        <div class="approval-workflow-panel {{ $approvalStatus === 'rejected' ? 'is-rejected' : ($approvalStatus === 'pending' ? 'is-pending' : ($approvalStatus === 'published' ? 'is-published' : '')) }}">
            <div class="approval-workflow-status">
                <span class="approval-workflow-icon" aria-hidden="true">
                    @if($approvalStatus === 'rejected')
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/>
                            <line x1="12" y1="7" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    @elseif($approvalStatus === 'pending')
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/>
                            <polyline points="12 7 12 12 15 14"/>
                        </svg>
                    @elseif($approvalStatus === 'published')
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/>
                            <polyline points="8 12.5 10.7 15 16 9"/>
                        </svg>
                    @else
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 4h14v16H5z"/>
                            <path d="M8 8h8"/>
                            <path d="M8 12h8"/>
                            <path d="M8 16h5"/>
                        </svg>
                    @endif
                </span>

                <div>
                    @if($approvalStatus === 'rejected')
                        <h2 class="approval-workflow-title">ต้องแก้ไขก่อนส่งขออนุมัติอีกครั้ง</h2>
                        <p class="approval-workflow-copy">ผู้บริหารตีกลับรายการนี้ ตรวจเหตุผลด้านล่างแล้วปรับข้อมูลหรือตารางให้เรียบร้อยก่อนส่งใหม่</p>
                        @if($courseOffering->rejection_reason)
                            <div class="approval-reason-box" data-testid="offering-rejection-reason">
                                {{ $courseOffering->rejection_reason }}
                            </div>
                        @endif
                    @elseif($approvalStatus === 'pending')
                        <h2 class="approval-workflow-title">ส่งขออนุมัติแล้ว</h2>
                        <p class="approval-workflow-copy" data-testid="offering-pending-note">กำลังรอผู้บริหารพิจารณา ตารางและข้อมูลรายวิชาถูกล็อกชั่วคราว</p>
                    @elseif($approvalStatus === 'published')
                        <h2 class="approval-workflow-title" data-testid="offering-published-note">อนุมัติแล้ว</h2>
                        <p class="approval-workflow-copy">รายวิชานี้ผ่านการพิจารณาแล้ว ตารางถูกล็อกเพื่อรักษาข้อมูลที่เผยแพร่</p>
                    @else
                        <h2 class="approval-workflow-title">พร้อมส่งให้ผู้บริหารพิจารณา</h2>
                        <p class="approval-workflow-copy">เมื่อส่งแล้ว ตารางจะถูกล็อกจนกว่าผู้บริหารจะอนุมัติหรือตีกลับ</p>
                    @endif
                </div>
            </div>

            <div class="approval-workflow-actions">
                @if($canEdit && in_array($approvalStatus, ['draft', 'rejected'], true))
                    <button type="button" class="btn btn-primary" @click="showSubmitModal = true" data-testid="offering-submit-button">
                        {{ $approvalStatus === 'rejected' ? 'ส่งขออนุมัติอีกครั้ง' : 'ส่งขออนุมัติ' }}
                    </button>
                @endif
            </div>
        </div>

        {{-- modal ยืนยันส่งขออนุมัติ --}}
        <template x-teleport="body">
            <div class="overlay" x-show="showSubmitModal" x-cloak
                 @click.self="showSubmitModal = false" @keydown.escape.window="showSubmitModal = false">
                <div class="modal-center" style="max-width:440px;padding:22px;">
                    <div style="font-weight:700;font-size:0.95rem;color:var(--fg-1);margin-bottom:6px;">ยืนยันส่งขออนุมัติ</div>
                    <div class="caption" style="margin-bottom:16px;">ส่งตารางรายวิชานี้ให้ผู้บริหารพิจารณา หลังส่งแล้ว <strong>ตารางจะถูกล็อก</strong> จนกว่าจะได้รับการอนุมัติหรือตีกลับ</div>
                    <form method="POST" action="{{ route('maker.course_offerings.submit', $courseOffering) }}" style="display:flex;justify-content:flex-end;gap:8px;">
                        @csrf
                        <button type="button" class="btn btn-secondary" @click="showSubmitModal = false">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary" data-testid="offering-submit-confirm">ยืนยันส่ง</button>
                    </form>
                </div>
            </div>
        </template>
    </div>

    {{-- Quick Summary Strip --}}
    @php
        $summaryStrip = [
            [
                'label' => 'จำนวนชุดผู้สอน',
                'value' => $instructorCount,
                'unit'  => 'คน',
                'href'  => '#instructors',
                'icon'  => '<circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.87"/>',
            ],
            [
                'label' => 'กลุ่มนักศึกษา',
                'value' => $courseOffering->studentGroups->count(),
                'unit'  => $courseOffering->studentGroups->count() ? 'กลุ่ม · ' . number_format($studentTotal) . ' คน' : 'ยังไม่มีกลุ่ม',
                'href'  => '#student-groups',
                'icon'  => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
            ],
            [
                'label' => 'ตารางสอนที่จัดแล้ว',
                'value' => $scheduleCount,
                'unit'  => 'รายการ',
                'href'  => $phase === 'scheduling' || $phase === 'published' ? route('maker.course_offerings.schedules.index', $courseOffering) : null,
                'icon'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
                'external' => true,
            ],
            [
                'label' => 'หน่วยกิต',
                'value' => $course?->credits ?? '-',
                'unit'  => 'หน่วยกิต · ' . ($lectureHours + $labHours) . ' ชม./สัปดาห์',
                'href'  => '#course-info',
                'icon'  => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
            ],
        ];
    @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px;">
        @foreach($summaryStrip as $item)
            <a href="{{ $item['href'] ?? '#' }}" class="course-summary-tile" style="
                display:flex;align-items:center;gap:12px;
                padding:14px 16px;
                background:var(--surface);
                border:2px solid var(--brand-navy-300);
                border-top:4px solid var(--brand-navy);
                border-radius:10px;
                text-decoration:none;
                color:var(--fg-1);
                transition:border-color 0.15s, background 0.15s;
            " onmouseover="this.style.background='var(--brand-navy-50)';this.style.borderColor='var(--brand-navy)'" onmouseout="this.style.background='var(--surface)';this.style.borderColor='var(--brand-navy-300)'">
                <div style="
                    display:flex;align-items:center;justify-content:center;
                    width:38px;height:38px;flex-shrink:0;
                    background:var(--brand-navy);color:#fff;
                    border-radius:8px;
                ">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        {!! $item['icon'] !!}
                    </svg>
                </div>
                <div style="min-width:0;flex:1;">
                    <div style="font-size:0.75rem;color:var(--fg-2);font-weight:600;">{{ $item['label'] }}</div>
                    <div style="display:flex;align-items:baseline;gap:4px;margin-top:3px;">
                        <span style="font-size:1.5rem;font-weight:700;color:var(--brand-navy);font-family:var(--font-display);line-height:1;">{{ $item['value'] }}</span>
                        <span style="font-size:0.75rem;color:var(--fg-3);">{{ $item['unit'] }}</span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    @if(session('error') && ! $errorSection)
        <div style="background:var(--status-conflict-bg);border:1px solid var(--status-conflict-border);color:var(--status-conflict-fg);padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:14px;">
            {{ session('error') }}
        </div>
    @endif

    @if(!$canEdit)
        <div style="background:var(--status-info-bg);border:1px solid var(--status-info-border);color:var(--status-info-fg);padding:12px 18px;border-radius:8px;margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:10px;">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <span>ยังไม่เปิดช่วงจัดตาราง — ดูข้อมูลได้อย่างเดียว การแก้ไขจะเปิดใช้งานเมื่อ Admin เปิดช่วงจัดตาราง</span>
        </div>
    @endif

    <div class="card" id="course-info">
        <div class="card-hdr">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;background:var(--brand-navy-50);color:var(--brand-navy);border-radius:8px;flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="card-ttl">ข้อมูลรายวิชา</div>
                    <div class="caption" style="margin-top:4px;" x-data x-show="!$store.offeringPage.collapsed.courseInfo">ข้อมูลจากรายวิชาหลักและการตั้งค่าระบบ</div>
                    <div x-data x-show="$store.offeringPage.collapsed.courseInfo" x-cloak class="section-collapse-summary">
                        <strong>{{ $course->course_code ?? '-' }}</strong>
                        <span>·</span>
                        <span>{{ $course->name_th ?? '-' }}</span>
                    </div>
                </div>
            </div>
            <div style="display:inline-flex;align-items:center;gap:0;margin-left:auto;" x-data>
                <button type="button"
                    @click="$store.offeringPage.toggleCollapse('courseInfo')"
                    :class="$store.offeringPage.collapsed.courseInfo ? 'section-collapse-toggle is-collapsed' : 'section-collapse-toggle'"
                    :aria-label="$store.offeringPage.collapsed.courseInfo ? 'ขยายส่วนข้อมูลรายวิชา' : 'ยุบส่วนข้อมูลรายวิชา'"
                    :aria-expanded="$store.offeringPage.collapsed.courseInfo ? 'false' : 'true'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
            </div>
        </div>
        <div style="padding:20px;" x-data x-show="!$store.offeringPage.collapsed.courseInfo" x-cloak>
            @if($courseInfoErrorKey)
                <div class="section-error-alert">
                    {{ $errors->first($courseInfoErrorKey) }}
                </div>
            @endif
            @if(session('error') && $errorSection === 'course-info')
                <div class="section-error-alert">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Primary fields --}}
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:18px;">
                <div>
                    <div class="caption">ภาควิชา</div>
                    <div style="font-weight:600;margin-top:4px;font-size:0.95rem;">{{ $course?->department?->name ?? '-' }}</div>
                </div>
                <div>
                    <div class="caption">หน่วยกิต</div>
                    <div style="font-weight:600;margin-top:4px;font-size:0.95rem;">{{ $course?->credits ?? '-' }} หน่วยกิต</div>
                </div>
                <div>
                    <div class="caption">ชั้นปี</div>
                    <div style="font-weight:600;margin-top:4px;font-size:0.95rem;">{{ $course?->default_year_level ? 'ชั้นปีที่ ' . $course->default_year_level : '-' }}</div>
                </div>
            </div>

            {{-- Secondary fields --}}
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:20px;padding-top:14px;border-top:1px dashed var(--border);">
                <div>
                    <div class="caption">ชั่วโมงเรียน (บรรยาย / ปฏิบัติ)</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $lectureHours }} / {{ $labHours }} <span class="caption">ชม.</span></div>
                </div>
                <div>
                    <div class="caption">จำนวนสัปดาห์สอน</div>
                    <div style="font-weight:600;margin-top:4px;">{{ $teachingWeeks }} <span class="caption">สัปดาห์ (ค่าตั้งระบบ)</span></div>
                </div>
            </div>

        </div>
    </div>

    @php
        $hasStudentGroups = $courseOffering->studentGroups->isNotEmpty();
        $cohortOptionsData = $availableCohortGroups->map(fn($cohort) => [
            'id' => $cohort->id,
            'code' => $cohort->code,
            'student_count' => $cohort->student_count,
            'label' => ($cohort->parent ? $cohort->parent->code . ' › ' : '') . $cohort->code . ' · ' . number_format($cohort->student_count) . ' คน',
        ])->values();
        $studentGroupRowsData = $courseOffering->studentGroups
            ->sortBy('group_code')
            ->values()
            ->map(fn($group) => [
                'id' => $group->id,
                'cohort_group_id' => $group->cohort_group_id,
                'group_code' => $group->group_code,
                'student_count' => $group->student_count,
                'color_code' => $group->color_code ?? '#2563eb',
            ]);
    @endphp

    <div class="card" id="student-groups" @if($canEdit) :class="!$store.offeringPage.editing.studentGroups ? 'is-locked-section' : ''" @endif style="overflow:visible;scroll-margin-top:72px;">
        <div class="card-hdr">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;background:var(--brand-navy-50);color:var(--brand-navy);border-radius:8px;flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                </div>
                <div>
                    <div class="card-ttl">กลุ่มนักศึกษา</div>
                    <div class="caption" style="margin-top:4px;" x-show="!$store.offeringPage.collapsed.studentGroups">
                        @if($hasStudentGroups)
                            จัดกลุ่มแล้ว {{ $courseOffering->studentGroups->count() }} กลุ่ม · {{ number_format($studentTotal) }} คน
                        @else
                            ยังไม่มีกลุ่มในรายวิชานี้
                        @endif
                    </div>
                    <div x-show="$store.offeringPage.collapsed.studentGroups" x-cloak class="section-collapse-summary">
                        <strong>{{ $courseOffering->studentGroups->count() }} กลุ่ม</strong>
                        <span>·</span>
                        <span>{{ number_format($studentTotal) }} คน</span>
                    </div>
                </div>
            </div>
            <div style="display:inline-flex;align-items:center;gap:0;margin-left:auto;" x-data>
                <button type="button" @click="$store.offeringPage.toggleCollapse('studentGroups')"
                    :class="$store.offeringPage.collapsed.studentGroups ? 'section-collapse-toggle is-collapsed' : 'section-collapse-toggle'"
                    :aria-expanded="$store.offeringPage.collapsed.studentGroups ? 'false' : 'true'"
                    aria-label="ยุบหรือขยายส่วนกลุ่มนักศึกษา">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
            </div>
        </div>

        <div class="{{ $hasStudentGroups ? 'student-groups-body has-existing' : 'student-groups-body' }}" style="padding:20px;" x-show="!$store.offeringPage.collapsed.studentGroups" x-cloak @if($canEdit) :inert="!$store.offeringPage.editing.studentGroups" @endif>
            @if($studentGroupErrorKey)
                <div class="section-error-alert">{{ $errors->first($studentGroupErrorKey) }}</div>
            @endif
            @if(session('error') && $errorSection === 'student-groups')
                <div class="section-error-alert">{{ session('error') }}</div>
            @endif
            @if($safeReturnToSchedule)
                <div class="student-groups-return-banner" data-testid="student-groups-return-banner">
                    <div>
                        <strong>จัดกลุ่มสำหรับตารางสอน</strong>
                        <span>เมื่อจัดกลุ่มเรียบร้อยแล้ว กลับไปเลือกกลุ่มในหน้าตารางสอนได้ทันที</span>
                    </div>
                    <a href="{{ $safeReturnToSchedule }}" class="btn btn-secondary" data-testid="student-groups-return-link">กลับไปหน้าจัดตารางสอน</a>
                </div>
            @endif

            @if($availableCohortGroups->isEmpty())
                <div class="student-group-empty">ยังไม่มีกลุ่มนักศึกษาใน Master Data สำหรับหลักสูตรและชั้นปีของรายวิชานี้</div>
            @else
                @if($canEdit)
                    <form method="POST" action="{{ route('maker.course_offerings.student_groups.save', $courseOffering) }}"
                        class="student-group-create-panel student-group-editor"
                        data-testid="student-groups-editor"
                        x-data="tpssStudentGroupBuilder({
                            cohorts: {{ $cohortOptionsData->toJson() }},
                            rows: {{ $studentGroupRowsData->toJson() }}
                        })"
                        x-init="syncSource(); sortRows()">
                        @csrf
                        @if($safeReturnToSchedule)
                            <input type="hidden" name="return_to" value="{{ $safeReturnToSchedule }}">
                        @endif

                        <div class="student-group-create-title">
                            <strong>จัดกลุ่มนักศึกษา</strong>
                        </div>

                        <div class="student-group-help">
                            <strong>วิธีใช้งาน</strong>
                            <span x-text="guideText()"></span>
                        </div>

                            <div class="student-group-preview" data-testid="student-group-list">
                                <div class="student-group-preview-note" x-text="hint()"></div>
                                <template x-if="capacityWarnings().length > 0">
                                    <div class="section-error-alert" data-testid="student-group-capacity-warning">
                                        <template x-for="warning in capacityWarnings()" :key="warning">
                                            <div x-text="warning"></div>
                                        </template>
                                    </div>
                                </template>
                                <div class="student-group-bulk-actions" x-show="rows.length > 0" x-cloak>
                                <label class="student-group-check-all">
                                    <input type="checkbox" :checked="allRowsSelected()" @change="toggleAllRows($event.target.checked)" data-testid="student-groups-select-all">
                                    <span>เลือกทั้งหมด</span>
                                </label>
                                <div class="student-group-inline-actions">
                                    <span class="student-group-preview-note" x-text="selectedSummary()"></span>
                                    <button type="button" class="btn btn-danger" @click="deleteSelected()" :disabled="selectedKeys.length === 0" data-testid="student-groups-delete-selected">ลบที่เลือก</button>
                                    <button type="button" class="btn btn-danger student-group-delete-all-btn" @click="deleteAll()" :disabled="rows.length === 0" data-testid="student-groups-delete-all">ลบทั้งหมด</button>
                                </div>
                            </div>
                            <div class="student-group-preview-empty" x-show="rows.length === 0">เลือกกลุ่มต้นทางและใส่จำนวนกลุ่มเพื่อเริ่มสร้างแถว</div>
                            <template x-if="rows.length > 0">
                                <div class="student-group-preview-head student-group-editor-head">
                                    <span>เลือก / สี</span>
                                    <span>ชื่อกลุ่ม</span>
                                    <span>กลุ่มต้นทาง</span>
                                    <span>จำนวนนักศึกษา</span>
                                    <span></span>
                                </div>
                            </template>
                            <template x-for="(row, index) in rows" :key="row.key">
                                <div :class="row.id ? 'student-group-preview-row student-group-editor-row' : 'student-group-preview-row student-group-editor-row is-new'" :data-testid="`student-group-row-${row.id || row.key}`">
                                    <input type="hidden" :name="`rows[${index}][id]`" x-model="row.id">
                                    <div class="student-group-mobile-row-head">
                                        <input type="checkbox" :value="row.key" :checked="selectedKeys.includes(row.key)" @change="toggleRow(row.key, $event.target.checked)" :data-testid="`student-group-select-${index}`">
                                        <div class="student-group-color-cell">
                                            <input type="color" x-model="row.color_code" :name="`rows[${index}][color_code]`" :data-testid="`student-group-color-${index}`">
                                            <span class="student-group-new-badge" x-show="!row.id">ใหม่</span>
                                        </div>
                                    </div>
                                    <label class="student-group-field">
                                        <span>ชื่อกลุ่ม</span>
                                        <input type="text" x-model="row.group_code" :name="`rows[${index}][group_code]`" required maxlength="255" :data-testid="`student-group-code-${index}`">
                                    </label>
                                    <label class="student-group-field">
                                        <span>กลุ่มต้นทาง</span>
                                        <select x-model="row.cohort_group_id" @change="sortRows()" :name="`rows[${index}][cohort_group_id]`" required :data-testid="`student-group-cohort-${index}`">
                                            <template x-for="cohort in cohorts" :key="cohort.id">
                                                <option :value="cohort.id" x-text="cohort.label"></option>
                                            </template>
                                        </select>
                                    </label>
                                    <label class="student-group-field">
                                        <span>จำนวนนักศึกษา</span>
                                        <input type="number" x-model.number="row.student_count" :name="`rows[${index}][student_count]`" min="1" max="9999" required :data-testid="`student-group-count-${index}`">
                                    </label>
                                    <button type="button" class="btn btn-danger" x-show="!row.id" @click="removeRow(index)">ลบ</button>
                                    <button type="button" class="btn btn-danger" x-show="row.id" @click="requestDeleteRows([row])">ลบ</button>
                                </div>
                            </template>
                            <div class="student-group-add-row">
                                <label>
                                    <span x-text="rows.length === 0 ? 'สร้างจากกลุ่มต้นทาง' : 'เพิ่มจากกลุ่มต้นทาง'"></span>
                                    <span class="student-group-select-wrap">
                                        <select class="student-group-source-select tpss-custom-select" x-model="cohortId" @change="syncSource()" data-testid="group-editor-source">
                                            <option value="">เลือกกลุ่มหลักหรือกลุ่มย่อย</option>
                                            @foreach($availableCohortGroups as $cohort)
                                                <option value="{{ $cohort->id }}">
                                                    {{ $cohort->parent ? $cohort->parent->code . ' › ' : '' }}{{ $cohort->code }} · {{ number_format($cohort->student_count) }} คน
                                                </option>
                                            @endforeach
                                        </select>
                                    </span>
                                </label>
                                <label>
                                    <span x-text="rows.length === 0 ? 'จำนวนกลุ่ม' : 'จำนวนกลุ่มใหม่'"></span>
                                    <input type="number" x-model.number="count" @input.debounce.600ms="autoAddGroups()" @change="autoAddGroups()" @keydown.enter.prevent="autoAddGroups()" min="1" max="100" placeholder="ระบุจำนวน" data-testid="group-editor-add-count">
                                </label>
                            </div>
                            <div class="student-group-create-actions">
                                <span class="student-group-preview-note" x-text="summary()"></span>
                                <button type="submit" class="btn btn-primary" :disabled="rows.length === 0 || capacityWarnings().length > 0" data-testid="student-groups-save">บันทึกการจัดกลุ่ม</button>
                            </div>
                        </div>

                        <template x-teleport="body">
                            <div class="student-group-delete-popover"
                                x-show="confirmDeleteOpen"
                                x-cloak
                                x-transition.opacity.duration.150ms
                                @keydown.escape.window="cancelDelete()"
                                data-testid="student-group-delete-confirm">
                                <div class="student-group-delete-backdrop" @click="cancelDelete()"></div>
                                <section class="student-group-delete-dialog" role="dialog" aria-modal="true" aria-labelledby="student-group-delete-title">
                                    <div class="student-group-delete-icon" aria-hidden="true"></div>
                                    <div>
                                        <h3 id="student-group-delete-title">ยืนยันการลบกลุ่มนักศึกษา</h3>
                                        <p x-text="deleteConfirmText()"></p>
                                    </div>
                                    <div class="student-group-delete-actions">
                                        <button type="button" class="btn btn-secondary student-group-delete-action-btn" @click="cancelDelete()">ยกเลิก</button>
                                        <button type="button" class="btn btn-danger student-group-delete-action-btn" @click="confirmPendingDelete()" x-text="deleteConfirmButtonText()" data-testid="student-group-delete-confirm-submit">ลบกลุ่ม</button>
                                    </div>
                                </section>
                            </div>
                        </template>
                    </form>

                    @foreach($courseOffering->studentGroups as $group)
                        <form id="delete-student-group-{{ $group->id }}" method="POST" action="{{ route('maker.course_offerings.student_groups.destroy', [$courseOffering, $group]) }}" style="display:none;">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endforeach
                    <form id="delete-student-groups-bulk" method="POST" action="{{ route('maker.course_offerings.student_groups.destroy_many', $courseOffering) }}" style="display:none;">
                        @csrf
                        @method('DELETE')
                    </form>
                @else
                    <div class="student-group-list" data-testid="student-group-list">
                        @forelse($courseOffering->studentGroups->sortBy('group_code') as $group)
                            <div class="student-group-edit-row" data-testid="student-group-row-{{ $group->id }}">
                                <div class="student-group-edit-form">
                                    <span class="student-group-swatch" style="background: {{ $group->color_code ?? '#2563eb' }}"></span>
                                    <strong>{{ $group->group_code }}</strong>
                                    <span>{{ $group->cohortGroup?->parent ? $group->cohortGroup->parent->code . ' › ' : '' }}{{ $group->cohortGroup?->code }}</span>
                                    <span>{{ number_format($group->student_count) }} คน</span>
                                </div>
                            </div>
                        @empty
                            <div class="student-group-empty">ยังไม่มีกลุ่มในรายวิชานี้</div>
                        @endforelse
                    </div>
                @endif
            @endif
        </div>
    </div>

    <script>
        function tpssStudentGroupBuilder(config) {
            return {
                cohorts: config.cohorts || [],
                rows: (config.rows || []).map((row) => ({
                    ...row,
                    key: `existing-${row.id}`,
                    cohort_group_id: row.cohort_group_id ? String(row.cohort_group_id) : '',
                    id: row.id ? String(row.id) : '',
                })),
                cohortId: '',
                selected: null,
                count: '',
                selectedKeys: [],
                confirmDeleteOpen: false,
                pendingDeleteRows: [],
                pendingDeleteMode: 'selected',
                colors: ['#2563eb', '#16a34a', '#d97706', '#dc2626', '#7c3aed', '#0891b2'],
                syncSource() {
                    this.selected = this.cohorts.find((cohort) => String(cohort.id) === String(this.cohortId)) || null;
                },
                cohortOrder(cohortId) {
                    const index = this.cohorts.findIndex((cohort) => String(cohort.id) === String(cohortId));
                    return index === -1 ? 9999 : index;
                },
                rowNumber(row) {
                    const match = String(row.group_code || '').match(/(\d+)$/u);
                    return match ? Number(match[1]) : 0;
                },
                sortRows() {
                    this.rows = [...this.rows].sort((a, b) => {
                        const byCohort = this.cohortOrder(a.cohort_group_id) - this.cohortOrder(b.cohort_group_id);
                        if (byCohort !== 0) return byCohort;

                        const byNumber = this.rowNumber(a) - this.rowNumber(b);
                        if (byNumber !== 0) return byNumber;

                        return String(a.group_code || '').localeCompare(String(b.group_code || ''), 'th');
                    });
                },
                escapeRegex(value) {
                    return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                },
                nextNumber() {
                    if (!this.selected) return 1;
                    const prefix = this.escapeRegex(this.selected.code);
                    const pattern = new RegExp(`^${prefix}(\\d+)$`, 'u');
                    const numbers = this.rows
                        .filter((row) => String(row.cohort_group_id) === String(this.selected.id))
                        .map((row) => row.group_code)
                        .map((code) => {
                            const match = String(code).match(pattern);
                            return match ? Number(match[1]) : null;
                        })
                        .filter((number) => Number.isFinite(number));

                    return numbers.length ? Math.max(...numbers) + 1 : 1;
                },
                canAdd() {
                    this.syncSource();
                    const n = parseInt(this.count, 10);
                    return !!this.selected && Number.isFinite(n) && n > 0;
                },
                autoAddGroups() {
                    if (!this.canAdd()) return;
                    this.addGroups();
                },
                addGroups() {
                    if (!this.canAdd()) return;
                    const n = parseInt(this.count, 10);
                    const startNumber = this.nextNumber();
                    const newRows = Array.from({ length: n }, (_, index) => ({
                        id: '',
                        key: `new-${Date.now()}-${index}-${Math.random().toString(36).slice(2)}`,
                        cohort_group_id: String(this.selected.id),
                        color_code: this.colors[index % this.colors.length],
                        group_code: n === 1 && startNumber === 1 && this.rowsForCohort(this.selected.id).length === 0
                            ? this.selected.code
                            : `${this.selected.code}${startNumber + index}`,
                        student_count: 1,
                    }));

                    this.rows = [...this.rows, ...newRows];
                    this.balanceCohort(this.selected.id);
                    this.sortRows();
                    this.count = '';
                },
                splitCount(total, parts) {
                    const safeParts = Math.max(1, parts);
                    const base = Math.floor(Math.max(0, Number(total || 0)) / safeParts);
                    const remainder = Math.max(0, Number(total || 0)) % safeParts;
                    return Array.from({ length: safeParts }, (_, index) => Math.max(1, base + (index < remainder ? 1 : 0)));
                },
                rowsForCohort(cohortId) {
                    return this.rows.filter((row) => String(row.cohort_group_id) === String(cohortId));
                },
                balanceCohort(cohortId) {
                    const cohort = this.cohorts.find((item) => String(item.id) === String(cohortId));
                    const targetRows = this.rowsForCohort(cohortId);
                    if (!cohort || targetRows.length === 0) return;

                    const counts = this.splitCount(cohort.student_count, targetRows.length);
                    let offset = 0;
                    this.rows = this.rows.map((row) => {
                        if (String(row.cohort_group_id) !== String(cohortId)) return row;
                        const next = { ...row, student_count: counts[offset] };
                        offset += 1;
                        return next;
                    });
                },
                removeRow(index) {
                    const [removed] = this.rows.splice(index, 1);
                    if (removed) {
                        this.selectedKeys = this.selectedKeys.filter((key) => key !== removed.key);
                    }
                },
                toggleRow(key, checked) {
                    if (checked) {
                        if (!this.selectedKeys.includes(key)) this.selectedKeys.push(key);
                        return;
                    }

                    this.selectedKeys = this.selectedKeys.filter((item) => item !== key);
                },
                allRowsSelected() {
                    return this.rows.length > 0 && this.selectedKeys.length === this.rows.length;
                },
                toggleAllRows(checked) {
                    this.selectedKeys = checked ? this.rows.map((row) => row.key) : [];
                },
                selectedRows() {
                    const keys = new Set(this.selectedKeys);
                    return this.rows.filter((row) => keys.has(row.key));
                },
                selectedSummary() {
                    if (this.selectedKeys.length === 0) return 'ยังไม่ได้เลือกกลุ่ม';
                    return `เลือกแล้ว ${this.selectedKeys.length.toLocaleString('th-TH')} กลุ่ม`;
                },
                hasNewRows() {
                    return this.rows.some((row) => !row.id);
                },
                deleteSelected() {
                    const rows = this.selectedRows();
                    if (rows.length === 0) return;
                    this.requestDeleteRows(rows, 'selected');
                },
                deleteAll() {
                    if (this.rows.length === 0) return;
                    this.requestDeleteRows([...this.rows], 'all');
                },
                requestDeleteRows(rows, mode = 'selected') {
                    const persistedIds = rows
                        .map((row) => row.id)
                        .filter((id) => id);

                    if (persistedIds.length === 0) {
                        this.deleteRows(rows);
                        return;
                    }

                    this.pendingDeleteRows = rows;
                    this.pendingDeleteMode = mode;
                    this.confirmDeleteOpen = true;
                },
                cancelDelete() {
                    this.confirmDeleteOpen = false;
                    this.pendingDeleteRows = [];
                    this.pendingDeleteMode = 'selected';
                },
                confirmPendingDelete() {
                    const rows = [...this.pendingDeleteRows];
                    this.cancelDelete();
                    this.deleteRows(rows);
                },
                deleteRows(rows) {
                    const persistedIds = rows
                        .map((row) => row.id)
                        .filter((id) => id);

                    this.rows = this.rows.filter((row) => !rows.some((target) => target.key === row.key));
                    this.selectedKeys = [];

                    if (persistedIds.length === 0) return;

                    const form = document.getElementById('delete-student-groups-bulk');
                    if (!form) return;

                    form.querySelectorAll('input[name="student_group_ids[]"]').forEach((input) => input.remove());
                    persistedIds.forEach((id) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'student_group_ids[]';
                        input.value = id;
                        form.appendChild(input);
                    });
                    form.submit();
                },
                deleteConfirmText() {
                    const total = this.pendingDeleteRows
                        .filter((row) => row.id)
                        .length;
                    const count = Number(total || 1).toLocaleString('th-TH');

                    if (this.pendingDeleteMode === 'all') {
                        return `ต้องการลบกลุ่มนักศึกษาทั้งหมด ${count} กลุ่มหรือไม่? เมื่อลบแล้วจะไม่สามารถกู้คืนจากหน้านี้ได้`;
                    }

                    return total > 1
                        ? `ต้องการลบกลุ่มนักศึกษา ${count} กลุ่มหรือไม่? เมื่อลบแล้วจะไม่สามารถกู้คืนจากหน้านี้ได้`
                        : 'ต้องการลบกลุ่มนักศึกษานี้หรือไม่? เมื่อลบแล้วจะไม่สามารถกู้คืนจากหน้านี้ได้';
                },
                deleteConfirmButtonText() {
                    return this.pendingDeleteMode === 'all' ? 'ลบทั้งหมด' : 'ลบกลุ่ม';
                },
                summary() {
                    if (this.rows.length === 0) return '';
                    const total = this.rows.reduce((sum, row) => sum + Number(row.student_count || 0), 0);
                    return `${this.rows.length} กลุ่ม · ${total.toLocaleString('th-TH')} คน`;
                },
                capacityWarnings() {
                    return this.cohorts
                        .map((cohort) => {
                            const total = this.rowsForCohort(cohort.id)
                                .reduce((sum, row) => sum + Number(row.student_count || 0), 0);

                            if (total <= Number(cohort.student_count || 0)) return null;

                            return `จำนวนนักศึกษาของกลุ่ม ${cohort.label} รวม ${total.toLocaleString('th-TH')} คน เกินกลุ่มต้นทางที่มี ${Number(cohort.student_count || 0).toLocaleString('th-TH')} คน`;
                        })
                        .filter(Boolean);
                },
                hint() {
                    if (this.capacityWarnings().length > 0) return 'จำนวนนักศึกษารวมเกินกลุ่มต้นทาง กรุณาปรับจำนวนก่อนบันทึก';
                    if (this.selectedKeys.length > 0) return 'เลือกกลุ่มแล้ว สามารถกดลบที่เลือก หรือติ๊กออกเพื่อยกเลิกการเลือก';
                    if (this.rows.length === 0 && !this.selected) return 'ขั้นที่ 1: เลือกกลุ่มต้นทางจาก Master Data';
                    if (this.rows.length === 0 && this.selected) return 'ขั้นที่ 2: ใส่จำนวนกลุ่ม ระบบจะสร้างแถวให้ทันที';
                    if (this.hasNewRows()) return 'มีแถวใหม่รอบันทึก ตรวจชื่อ สี และจำนวนนักศึกษาก่อนกดบันทึกการจัดกลุ่ม';
                    if (!this.selected) return 'แก้ไขกลุ่มที่มีอยู่ได้เลย หรือลงไปเลือกกลุ่มต้นทางเพื่อเพิ่มกลุ่มใหม่';
                    return 'ใส่จำนวนกลุ่มใหม่ ระบบจะเพิ่มแถวต่อท้ายและจัดให้อยู่ติดกับกลุ่มต้นทางเดียวกัน';
                },
                guideText() {
                    if (this.selectedKeys.length > 0) {
                        return `กำลังเลือก ${this.selectedKeys.length.toLocaleString('th-TH')} กลุ่ม เพื่อลบหลายรายการให้กดลบที่เลือก ถ้าไม่ต้องการลบให้ยกเลิกการเลือกก่อน`;
                    }
                    if (this.rows.length === 0 && !this.selected) {
                        return 'ยังไม่มีกลุ่มในรายวิชานี้ เริ่มจากเลือกกลุ่มต้นทางจาก Master Data ก่อน';
                    }
                    if (this.rows.length === 0 && this.selected) {
                        return 'เลือกกลุ่มต้นทางแล้ว ใส่จำนวนกลุ่มที่ต้องการ ระบบจะสร้างแถวให้แก้ชื่อ สี และจำนวนได้ทันที';
                    }
                    if (this.hasNewRows()) {
                        return 'ระบบเพิ่มแถวใหม่ให้แล้ว แก้ชื่อ สี และจำนวนนักศึกษาของแต่ละแถวได้เลย จากนั้นกดบันทึกการจัดกลุ่ม';
                    }
                    if (!this.selected) {
                        return 'ตอนนี้มีกลุ่มที่บันทึกแล้ว แก้ไขข้อมูลในตารางได้เลย หรือเลือกกลุ่มต้นทางด้านล่างเพื่อเพิ่มกลุ่มใหม่';
                    }

                    return 'เลือกกลุ่มต้นทางสำหรับเพิ่มกลุ่มแล้ว ใส่จำนวนกลุ่มใหม่ ระบบจะสร้างแถวต่อท้ายและเฉลี่ยจำนวนรวมกับกลุ่มเดิมของต้นทางเดียวกัน';
                },
            };
        }
    </script>

    @php
        $poolData = $courseOffering->instructorPool
            ->sort(function ($left, $right) use ($courseOffering) {
                $leftIsCoordinator = (int) $left->id === (int) $courseOffering->coordinator_id;
                $rightIsCoordinator = (int) $right->id === (int) $courseOffering->coordinator_id;

                if ($leftIsCoordinator !== $rightIsCoordinator) {
                    return $leftIsCoordinator ? -1 : 1;
                }

                return strnatcasecmp(
                    (string) ($left->formatted_name ?? $left->name ?? ''),
                    (string) ($right->formatted_name ?? $right->name ?? '')
                );
            })
            ->values()
            ->map(fn($u) => [
            'id'             => $u->id,
            'name'           => $u->formatted_name,
            'department'     => $u->instructorProfile?->department?->name ?? '-',
            'department_id'  => $u->instructorProfile?->department_id,
            'is_coordinator' => (int) $u->id === (int) $courseOffering->coordinator_id,
            'course_role_id' => $u->pivot->course_role_id,
            'role_name'      => optional($courseRoles->firstWhere('id', $u->pivot->course_role_id))->name_th
                ?? ($u->pivot->role_in_course === 'coordinator' ? 'หัวหน้าวิชา' : null),
            'can_schedule'   => ($u->pivot->schedule_permission ?? 'view') === 'schedule',
        ]);
        $allInstructors = $availableInstructors->map(fn($u) => [
            'id'           => $u->id,
            'name'         => $u->formatted_name,
            'department'   => $u->instructorProfile?->department?->name ?? '-',
            'department_id'=> $u->instructorProfile?->department_id,
        ]);
        $courseRolesData = $courseRoles->map(fn($r) => ['id' => $r->id, 'name' => $r->name_th]);
        $storeUrl    = route('maker.course_offerings.instructors.store', $courseOffering);
        $roleBase    = route('maker.course_offerings.instructors.role', [$courseOffering, '__ID__']);
        $destroyBase = route('maker.course_offerings.instructors.destroy', [$courseOffering, '__ID__']);
        $courseDeptId = $course?->department_id;
    @endphp

    <div class="card" id="instructors" @if($canEdit) :class="!$store.offeringPage.editing.instructors ? 'is-locked-section' : ''" @endif style="overflow:visible;scroll-margin-top:72px;" x-data="{
        pool: {{ $poolData->toJson() }},
        all: {{ $allInstructors->toJson() }},
        roles: {{ $courseRolesData->toJson() }},
        search: '',
        open: false,
        showAll: false,
        loading: false,
        error: '',
        warning: '',
        ddTop: 0, ddLeft: 0, ddWidth: 0,
        roleMenuId: null,
        storeUrl: '{{ $storeUrl }}',
        roleBase: '{{ $roleBase }}',
        destroyBase: '{{ $destroyBase }}',
        permissionBase: '{{ route('maker.course_offerings.instructors.permission', [$courseOffering, '__ID__']) }}',
        csrfToken: '{{ csrf_token() }}',
        courseDeptId: {{ $courseDeptId ?? 'null' }},
        get orderedPool() {
            return [...this.pool].sort((left, right) => {
                if (Boolean(left.is_coordinator) !== Boolean(right.is_coordinator)) {
                    return left.is_coordinator ? -1 : 1;
                }

                return String(left.name || '').localeCompare(String(right.name || ''), 'th');
            });
        },
        sortPool() {
            this.pool = this.orderedPool;
        },
        // โมดัลกรอกเหตุผล — เปิดเฉพาะเมื่อ action ทำให้ชุดผู้สอนต่างจากแม่แบบและยังไม่มีเหตุผล
        noteModal: { open: false, text: '', error: '', retry: null },
        openNoteModal(retry) {
            this.roleMenuId = null;
            this.noteModal = { open: true, text: '', error: '', retry };
        },
        async confirmNote() {
            const note = (this.noteModal.text || '').trim();
            if (!note) { this.noteModal.error = 'กรุณาระบุเหตุผล'; return; }
            const retry = this.noteModal.retry;
            this.noteModal.open = false;
            if (retry) { await retry(note); }
        },
        _needsNote(status, data) {
            return status === 422 && data && data.errors && data.errors.note;
        },
        outsideDepartment(user) {
            return Boolean(this.courseDeptId && user && Number(user.department_id) !== Number(this.courseDeptId));
        },
        async changeRole(userId, roleId, note = null) {
            this.loading = true; this.error = '';
            try {
                const r = await fetch(this.roleBase.replace('__ID__', userId), {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ course_role_id: roleId, note })
                });
                const data = await r.json();
                if (!r.ok) {
                    if (this._needsNote(r.status, data)) { this.openNoteModal((n) => this.changeRole(userId, roleId, n)); return; }
                    this.error = data.message ?? 'เกิดข้อผิดพลาด'; return;
                }
                const u = this.pool.find(x => x.id === userId);
                if (u) { u.course_role_id = data.course_role_id; u.role_name = data.role_name; }
                this.roleMenuId = null;
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
            finally { this.loading = false; }
        },
        get available() {
            const s = this.search.toLowerCase();
            const inPool = new Set(this.pool.map(u => u.id));
            return this.all.filter(u => {
                if (inPool.has(u.id)) return false;
                if (!this.showAll && this.courseDeptId) {
                    if (u.department_id !== this.courseDeptId) return false;
                }
                return s === '' || u.name.toLowerCase().includes(s) || u.department.toLowerCase().includes(s);
            });
        },
        openDropdown() {
            const r = this.$refs.searchInput.getBoundingClientRect();
            const viewportWidth = document.documentElement.clientWidth || window.innerWidth;
            const margin = viewportWidth <= 480 ? 10 : 0;
            const maxWidth = Math.max(240, viewportWidth - (margin * 2));
            const width = Math.min(r.width, maxWidth);
            this.ddTop = r.bottom + window.scrollY + 4;
            this.ddLeft = Math.max(margin, Math.min(r.left + window.scrollX, viewportWidth - width - margin));
            this.ddWidth = width;
            this.open = true;
        },
        async add(user, note = null) {
            this.loading = true; this.error = ''; this.warning = '';
            try {
                const r = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ user_id: user.id, note })
                });
                const data = await r.json();
                if (!r.ok) {
                    if (this._needsNote(r.status, data)) { this.openNoteModal((n) => this.add(user, n)); return; }
                    this.error = data.message ?? 'เกิดข้อผิดพลาด'; return;
                }
                this.pool.push(data);
                this.sortPool();
                if (data.warning) this.warning = data.warning;
                this.search = ''; this.open = false;
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
            finally { this.loading = false; }
        },
        async remove(userId, note = null) {
            this.error = '';
            const url = this.destroyBase.replace('__ID__', userId);
            try {
                const r = await fetch(url, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                    body: JSON.stringify({ note })
                });
                const data = await r.json();
                if (!r.ok) {
                    if (this._needsNote(r.status, data)) { this.openNoteModal((n) => this.remove(userId, n)); return; }
                    this.error = data.message ?? 'เกิดข้อผิดพลาด'; return;
                }
                this.pool = this.pool.filter(u => u.id !== userId);
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
        },
        async togglePermission(userId) {
            const u = this.pool.find(x => x.id === userId);
            if (!u) return;
            const next = u.can_schedule ? 'view' : 'schedule';
            this.loading = true; this.error = '';
            try {
                const r = await fetch(this.permissionBase.replace('__ID__', userId), {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ schedule_permission: next })
                });
                const data = await r.json();
                if (!r.ok) { this.error = data.message ?? 'เกิดข้อผิดพลาด'; return; }
                u.can_schedule = data.schedule_permission === 'schedule';
            } catch { this.error = 'ไม่สามารถเชื่อมต่อได้'; }
            finally { this.loading = false; }
        }
    }">
        {{-- โมดัลกรอกเหตุผล (โผล่เมื่อชุดผู้สอนต่างจากแม่แบบ) --}}
        <template x-teleport="body">
            <div x-show="noteModal.open" x-cloak
                 style="position:fixed;inset:0;z-index:1200;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,0.45);"
                 @click.self="noteModal.open = false" @keydown.escape.window="noteModal.open = false">
                <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;max-width:440px;width:100%;padding:22px;box-shadow:0 24px 60px -24px rgba(0,36,84,0.5);">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--status-warning-fg);flex-shrink:0;">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        <div style="font-weight:700;font-size:0.95rem;color:var(--fg-1);">ระบุเหตุผลที่ต่างจากแม่แบบ</div>
                    </div>
                    <div class="caption" style="margin-bottom:12px;">การแก้ชุดผู้สอนครั้งนี้ทำให้ต่างจากแม่แบบรายวิชา — กรุณาระบุเหตุผลเพื่อให้ผู้ดูแลระบบเห็นในหน้าแจ้งเตือน</div>
                    <textarea x-model="noteModal.text" rows="3" maxlength="1000"
                              data-testid="instructor-pool-note-text"
                              placeholder="เช่น ปีนี้ อ.A ลาศึกษาต่อ จึงให้ อ.B สอนแทน"
                              style="width:100%;box-sizing:border-box;" @keydown.enter.prevent="confirmNote()"></textarea>
                    <div x-show="noteModal.error" x-cloak style="color:var(--status-conflict-fg);font-size:0.8rem;margin-top:6px;" x-text="noteModal.error"></div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                        <button type="button" class="btn btn-secondary" @click="noteModal.open = false">ยกเลิก</button>
                        <button type="button" class="btn btn-primary" data-testid="instructor-pool-note-submit" @click="confirmNote()">บันทึกเหตุผล</button>
                    </div>
                </div>
            </div>
        </template>
        <div class="card-hdr">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;background:var(--brand-navy-50);color:var(--brand-navy);border-radius:8px;flex-shrink:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.87"/>
                    </svg>
                </div>
                <div>
                    <div class="card-ttl">ชุดผู้สอน</div>
                    <div class="caption" style="margin-top:4px;" x-show="!$store.offeringPage.collapsed.instructors" x-text="pool.length ? pool.length + ' คน' : 'ยังไม่มีผู้สอน'"></div>
                    <div x-show="$store.offeringPage.collapsed.instructors" x-cloak class="section-collapse-summary">
                        <strong x-text="pool.length + ' คน'"></strong>
                        <template x-if="pool.length > 0">
                            <span>·</span>
                        </template>
                        <template x-if="pool.length > 0">
                            <span x-text="orderedPool[0].name + (pool.length > 1 ? ' +' + (pool.length - 1) : '')"></span>
                        </template>
                    </div>
                </div>
            </div>
            <div style="display:inline-flex;align-items:center;gap:0;margin-left:auto;">
                @if($canEdit)
                    <button
                        type="button"
                        @click="$store.offeringPage.startEditing('instructors')"
                        class="section-edit-quick-toggle"
                        :aria-pressed="$store.offeringPage.editing.instructors ? 'true' : 'false'"
                        data-testid="section-edit-quick-toggle-instructors"
                        x-text="$store.offeringPage.editing.instructors ? 'เสร็จสิ้น' : 'แก้ไข'"
                    ></button>
                @endif
                <button type="button"
                    @click="$store.offeringPage.toggleCollapse('instructors')"
                    :class="$store.offeringPage.collapsed.instructors ? 'section-collapse-toggle is-collapsed' : 'section-collapse-toggle'"
                    :aria-label="$store.offeringPage.collapsed.instructors ? 'ขยายส่วนชุดผู้สอน' : 'ยุบส่วนชุดผู้สอน'"
                    :aria-expanded="$store.offeringPage.collapsed.instructors ? 'false' : 'true'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
            </div>
        </div>
        <div class="instructor-section-body" style="padding:20px;" x-show="!$store.offeringPage.collapsed.instructors" x-cloak @if($canEdit) :inert="!$store.offeringPage.editing.instructors" @endif>
            @if($instructorErrorKey)
                <div class="section-error-alert">
                    {{ $errors->first($instructorErrorKey) }}
                </div>
            @endif
            @if(session('error') && $errorSection === 'instructors')
                <div class="section-error-alert">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Error message --}}
            <div x-show="error" x-text="error" style="background:var(--status-conflict-bg);border:1px solid var(--status-conflict-border);color:var(--status-conflict-fg);padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:14px;"></div>
            <div x-show="warning" x-text="warning" style="background:var(--status-warning-bg);border:1px solid var(--status-warning-border);color:var(--status-warning-fg);padding:10px 14px;border-radius:6px;font-size:13px;font-weight:700;margin-bottom:14px;"></div>

            @if($canEdit)
            {{-- Combobox --}}
            <div style="position:relative;margin-bottom:20px;">
                <div style="position:relative;">
                    <input
                        x-ref="searchInput"
                        type="text"
                        x-model="search"
                        @focus="openDropdown()"
                        @input="openDropdown()"
                        placeholder="ค้นหาชื่ออาจารย์หรือภาควิชา..."
                        class="instructor-picker-input"
                        data-testid="instructor-pool-search"
                        autocomplete="off"
                    >
                    <svg x-show="!loading" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);opacity:0.4;pointer-events:none;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <svg x-show="loading" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);opacity:0.4;pointer-events:none;animation:spin 1s linear infinite;"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"/></svg>
                </div>

                {{-- Backdrop --}}
                <template x-teleport="body">
                    <div x-show="open" x-cloak @click="open = false; search = ''" style="position:fixed;inset:0;z-index:98;"></div>
                </template>

                {{-- Dropdown teleported to body --}}
                <template x-teleport="body">
                    <div
                        x-show="open"
                        x-cloak
                        class="instructor-picker-menu"
                        :style="`position:absolute;top:${ddTop}px;left:${ddLeft}px;width:${ddWidth}px;`"
                    >
                        {{-- Filter toggle inside dropdown --}}
                        <div x-show="courseDeptId" class="instructor-filter-segment-wrap">
                            <div class="instructor-filter-segment" role="tablist" aria-label="กรองอาจารย์">
                                <button type="button"
                                    class="instructor-filter-option"
                                    @click.stop="showAll = false"
                                    :class="{ 'is-active': !showAll }"
                                    :aria-selected="!showAll"
                                    role="tab">
                                    เฉพาะภาควิชานี้
                                </button>
                                <button type="button"
                                    class="instructor-filter-option"
                                    @click.stop="showAll = true"
                                    :class="{ 'is-active': showAll }"
                                    :aria-selected="showAll"
                                    role="tab">
                                    อาจารย์ทั้งหมด
                                </button>
                            </div>
                        </div>
                        {{-- Results --}}
                        <div class="instructor-picker-results">
                            <template x-for="user in available" :key="user.id">
                                <button type="button"
                                    @click="add(user)"
                                    class="instructor-picker-option"
                                    data-testid="instructor-pool-option"
                                >
                                    <div>
                                        <div class="instructor-picker-name" x-text="user.name"></div>
                                        <div class="instructor-picker-dept" x-text="user.department"></div>
                                    </div>
                                    <span x-show="outsideDepartment(user)" x-cloak class="instructor-picker-warning">ต่างภาค</span>
                                    <span class="instructor-picker-add" aria-hidden="true">+</span>
                                </button>
                            </template>
                            <div
                                x-show="available.length === 0"
                                class="instructor-picker-empty"
                            >ไม่พบอาจารย์ที่ตรงกัน</div>
                        </div>
                    </div>
                </template>
            </div>
            @endif

            {{-- Pills --}}
            <div style="display:flex;flex-direction:column;gap:8px;" x-show="pool.length > 0">
                <template x-for="user in orderedPool" :key="user.id">
                    <div class="instructor-pool-card" style="display:flex;align-items:center;gap:14px;background:#fff;border:1px solid var(--border);border-radius:8px;padding:12px 16px;transition:border-color 0.15s;"
                         @mouseover="$el.style.borderColor='var(--brand-navy-300)'"
                         @mouseout="$el.style.borderColor='var(--border)'">
                        <div style="display:flex;align-items:center;justify-content:center;width:36px;height:36px;flex-shrink:0;background:var(--brand-navy-50);color:var(--brand-navy);border-radius:50%;font-weight:700;font-size:0.875rem;font-family:var(--font-display);"
                             x-text="user.name.replace(/^(อ\.|ดร\.|ผศ\.|รศ\.|ศ\.|นาย|นาง|นางสาว|น\.ส\.)+\s*/g, '').charAt(0)">
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:14px;color:var(--fg-1);" x-text="user.name"></div>
                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;color:var(--fg-3);font-size:12px;margin-top:2px;">
                                <span x-text="user.department"></span>
                                <span x-show="outsideDepartment(user)" x-cloak style="border:1px solid var(--status-warning-border);border-radius:999px;background:var(--status-warning-bg);color:var(--status-warning-fg);padding:1px 7px;font-size:10.5px;font-weight:800;">ต่างภาค</span>
                            </div>
                        </div>

                        <div class="instructor-pool-actions">
                            {{-- Role selector (coordinator = static badge) --}}
                            <template x-if="user.is_coordinator">
                                <div class="course-role-badge course-role-badge-head">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z"/>
                                        <path d="M9 12l2 2 4-5"/>
                                    </svg>
                                    <span>หัวหน้าวิชา</span>
                                </div>
                            </template>
                            <template x-if="!user.is_coordinator">
                                <div class="course-role-control">
                                    @if($canEdit)
                                    <button type="button"
                                        class="course-role-trigger"
                                        :class="user.role_name ? 'is-assigned' : 'is-empty'"
                                        @click.stop="roleMenuId = roleMenuId === user.id ? null : user.id"
                                        :aria-expanded="roleMenuId === user.id"
                                        aria-haspopup="listbox">
                                        <span class="course-role-trigger-text" x-text="user.role_name || 'ยังไม่กำหนดบทบาท'"></span>
                                        <svg class="course-role-chevron" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M6 9l6 6 6-6"/>
                                        </svg>
                                    </button>
                                    <div x-show="roleMenuId === user.id"
                                        x-cloak
                                        @click.outside="roleMenuId = null"
                                        x-transition:enter="transition ease-out duration-150"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100"
                                        class="course-role-menu"
                                        role="listbox">
                                        <button type="button"
                                            class="course-role-option"
                                            :class="{ 'is-selected': !user.course_role_id }"
                                            @click="changeRole(user.id, null)"
                                            role="option">
                                            <span class="course-role-option-label">ยังไม่กำหนดบทบาท</span>
                                            <svg x-show="!user.course_role_id" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20 6L9 17l-5-5"/>
                                            </svg>
                                        </button>
                                        <template x-for="role in roles" :key="role.id">
                                            <button type="button"
                                                class="course-role-option"
                                                :class="{ 'is-selected': user.course_role_id === role.id }"
                                                @click="changeRole(user.id, role.id)"
                                                role="option">
                                                <span class="course-role-option-label" x-text="role.name"></span>
                                                <svg x-show="user.course_role_id === role.id" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M20 6L9 17l-5-5"/>
                                                </svg>
                                            </button>
                                        </template>
                                    </div>
                                    @else
                                    <div class="course-role-readonly" :class="user.role_name ? 'is-assigned' : 'is-empty'">
                                        <span class="course-role-dot"></span>
                                        <span x-text="user.role_name || 'ยังไม่กำหนดบทบาท'"></span>
                                    </div>
                                    @endif
                                </div>
                            </template>

                            {{-- V2 delegation: หัวหน้าวิชามอบหมายให้อาจารย์ช่วยจัดตาราง offering นี้ --}}
                            @if($canEdit)
                            <div x-show="user.is_coordinator" class="delegate-toggle-spacer" aria-hidden="true"></div>
                            <button type="button" x-show="!user.is_coordinator" @click="togglePermission(user.id)"
                                class="delegate-toggle" :class="user.can_schedule ? 'is-on' : 'is-off'"
                                :title="user.can_schedule ? 'ยกเลิกสิทธิ์ช่วยจัดตาราง' : 'ให้ช่วยจัดตาราง'"
                                :aria-pressed="user.can_schedule">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M9 16l2 2 4-4"/>
                                </svg>
                                <span x-text="user.can_schedule ? 'ช่วยจัดตาราง' : 'ให้ช่วยจัดตาราง'"></span>
                            </button>
                            @else
                            <template x-if="user.is_coordinator || !user.can_schedule">
                                <span class="delegate-toggle-spacer" aria-hidden="true"></span>
                            </template>
                            <template x-if="!user.is_coordinator && user.can_schedule">
                                <span class="delegate-toggle is-on" style="cursor:default;">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M9 16l2 2 4-4"/>
                                    </svg>
                                    <span>ช่วยจัดตาราง</span>
                                </span>
                            </template>
                            @endif

                            @if($canEdit)
                            <button type="button" x-show="!user.is_coordinator" @click="remove(user.id)" title="ลบอาจารย์ออกจากชุดผู้สอน"
                                data-testid="instructor-pool-remove"
                                class="instructor-remove-button"
                                @mouseenter="$el.style.background='var(--status-conflict-bg)';$el.style.color='var(--status-conflict-fg)'"
                                @mouseleave="$el.style.background='transparent';$el.style.color='var(--fg-3)'">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                            <div x-show="user.is_coordinator" class="instructor-action-spacer" aria-hidden="true"></div>
                            @endif
                        </div>
                    </div>
                </template>
            </div>
            <div x-show="pool.length === 0" style="color:var(--fg-3);font-size:14px;">ยังไม่มีผู้สอนในรายวิชานี้</div>
        </div>
    </div>

    <style>
        @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

        /* Major section separation — medium navy outline on the primary cards */
        .card#course-info,
        .card#student-groups,
        .card#instructors {
            border: 1px solid color-mix(in oklch, var(--brand-navy) 28%, var(--border));
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface) 44%),
                var(--surface);
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.08),
                0 18px 42px -30px rgba(0, 36, 84, 0.42);
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .card#course-info:hover,
        .card#student-groups:hover,
        .card#instructors:hover,
        .card#course-info:focus-within,
        .card#student-groups:focus-within,
        .card#instructors:focus-within {
            border-color: color-mix(in oklch, var(--brand-navy) 38%, var(--border));
            box-shadow:
                0 2px 6px rgba(0, 36, 84, 0.08),
                0 24px 50px -32px rgba(0, 36, 84, 0.52);
        }

        /* V2 delegation toggle — มอบหมายอาจารย์ช่วยจัดตาราง */
        .delegate-toggle {
            display:inline-flex; align-items:center; gap:6px; flex-shrink:0;
            justify-content:center; width:142px;
            height:28px; padding:0 11px; border-radius:999px;
            font-family:var(--font-sans); font-size:12px; font-weight:600;
            cursor:pointer; transition:background .15s,border-color .15s,color .15s; white-space:nowrap;
        }
        .instructor-pool-actions {
            display:grid;
            grid-template-columns:170px 142px 32px;
            align-items:center;
            gap:10px;
            flex:0 0 auto;
        }
        .delegate-toggle-spacer,
        .instructor-action-spacer {
            display:block;
            flex-shrink:0;
        }
        .delegate-toggle-spacer {
            width:142px;
            height:28px;
        }
        .instructor-action-spacer {
            width:32px;
            height:32px;
        }
        .instructor-remove-button {
            width:32px;
            height:32px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            flex-shrink:0;
            border:0;
            border-radius:50%;
            background:transparent;
            color:var(--fg-3);
            cursor:pointer;
            transition:all 0.15s;
        }
        .delegate-toggle.is-off {
            background:var(--bg-2); border:1px solid var(--line-2); color:var(--fg-3);
        }
        .delegate-toggle.is-off:hover {
            border-color:var(--brand-navy-500); color:var(--brand-navy);
        }
        .delegate-toggle.is-on {
            background:var(--status-success-bg); border:1px solid var(--status-success-border); color:var(--status-success-fg);
        }
        .delegate-toggle.is-on:hover { background:color-mix(in oklch, var(--status-success-fg) 16%, var(--surface)); }

        .instructor-picker-input {
            width: 100%;
            min-height: 44px;
            box-sizing: border-box;
            padding: 0 36px 0 14px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
            border-radius: 8px;
            background:
                linear-gradient(180deg, var(--surface), color-mix(in oklch, var(--brand-navy) 4%, var(--surface)));
            color: var(--fg-1);
            font: inherit;
            font-size: 14px;
            transition: border-color 140ms ease, box-shadow 140ms ease, background 140ms ease;
        }

        .instructor-picker-input:focus {
            outline: none;
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px color-mix(in oklch, var(--brand-navy) 16%, transparent);
            background: var(--surface);
        }

        .instructor-picker-menu {
            border: 1px solid color-mix(in oklch, var(--brand-navy) 26%, var(--border));
            border-radius: 10px;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface) 36%);
            box-shadow: 0 18px 38px -26px rgba(0, 36, 84, 0.5), 0 1px 2px rgba(0, 36, 84, 0.08);
            z-index: 99;
            overflow: hidden;
        }

        .instructor-filter-segment-wrap {
            display: flex;
            justify-content: flex-start;
            padding: 8px 10px 6px;
            border-bottom: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        }

        .instructor-filter-segment {
            width: auto;
            max-width: 100%;
            display: inline-grid;
            grid-template-columns: repeat(2, max-content);
            gap: 3px;
            padding: 2px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
            border-radius: 6px;
            background: color-mix(in oklch, var(--surface) 86%, var(--brand-navy));
        }

        .instructor-filter-option {
            min-width: 0;
            min-height: 26px;
            padding: 0 9px;
            border: 1px solid transparent;
            border-radius: 4px;
            background: transparent;
            color: var(--fg-2);
            cursor: pointer;
            font: inherit;
            font-size: 11.5px;
            font-weight: 800;
            line-height: 1.2;
            text-align: center;
            white-space: nowrap;
            transition: background 130ms ease, border-color 130ms ease, color 130ms ease, box-shadow 130ms ease;
        }

        .instructor-filter-option:hover,
        .instructor-filter-option:focus-visible {
            border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border));
            background: var(--surface);
            outline: none;
        }

        .instructor-filter-option.is-active {
            border-color: var(--brand-navy);
            background: var(--brand-navy);
            color: var(--fg-on-brand);
            box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--brand-navy) 86%, var(--surface));
        }

        .instructor-picker-results {
            max-height: min(356px, calc(100vh - 260px));
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
        }

        .instructor-picker-option {
            width: 100%;
            min-height: 58px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 0;
            border-bottom: 1px solid color-mix(in oklch, var(--brand-navy) 12%, var(--border));
            background: var(--surface);
            color: var(--fg-1);
            cursor: pointer;
            font: inherit;
            text-align: left;
            transition: background 130ms ease, color 130ms ease;
        }

        .instructor-picker-option:hover,
        .instructor-picker-option:focus-visible {
            background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
            outline: none;
        }

        .instructor-picker-name {
            min-width: 0;
            color: var(--fg-1);
            font-size: 14px;
            font-weight: 800;
            line-height: 1.35;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .instructor-picker-dept {
            margin-top: 3px;
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 650;
            line-height: 1.35;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .instructor-picker-warning {
            border: 1px solid var(--status-warning-border);
            border-radius: 999px;
            background: var(--status-warning-bg);
            color: var(--status-warning-fg);
            padding: 2px 8px;
            font-size: 10.5px;
            font-weight: 850;
            white-space: nowrap;
        }

        .instructor-picker-add {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            color: var(--brand-navy);
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            font-size: 17px;
            font-weight: 900;
            line-height: 1;
        }

        .instructor-picker-empty {
            padding: 14px;
            color: var(--fg-3);
            font-size: 13px;
            font-weight: 650;
            text-align: center;
        }

        .card#course-info > .card-hdr,
        .card#student-groups > .card-hdr,
        .card#instructors > .card-hdr {
            border-bottom: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 10%, var(--surface)), color-mix(in oklch, var(--brand-navy) 4%, var(--surface)));
            border-top-left-radius: var(--r-lg);
            border-top-right-radius: var(--r-lg);
        }

        .course-summary-tile {
            background:
                radial-gradient(circle at 92% 14%, color-mix(in oklch, var(--brand-navy) 12%, transparent), transparent 34%),
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 5%, var(--surface)), var(--surface)) !important;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 28%, var(--border)) !important;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.08),
                0 14px 30px -24px rgba(0, 36, 84, 0.46);
            transition: transform 150ms ease, border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
        }

        .course-summary-tile:hover,
        .course-summary-tile:focus-visible {
            transform: translateY(-2px);
            border-color: color-mix(in oklch, var(--brand-navy) 38%, var(--border)) !important;
            background:
                radial-gradient(circle at 92% 14%, color-mix(in oklch, var(--brand-navy) 16%, transparent), transparent 34%),
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 8%, var(--surface)), var(--surface)) !important;
            box-shadow:
                0 2px 5px rgba(0, 36, 84, 0.1),
                0 20px 36px -24px rgba(0, 36, 84, 0.62);
            outline: none;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 9px 16px 9px 12px;
            margin-bottom: 14px;
            background: var(--brand-navy);
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid var(--brand-navy);
            border-radius: 8px;
            transition: background 0.15s, transform 0.15s;
        }

        .back-link:hover {
            background: var(--brand-navy-700, #0f1e3a);
            color: #fff;
        }

        .back-link:hover .back-link-icon svg {
            transform: translateX(-2px);
        }

        .back-link:focus-visible {
            outline: 2px solid var(--brand-navy);
            outline-offset: 2px;
        }

        .back-link-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            background: rgba(255, 255, 255, 0.18);
            border-radius: 6px;
        }

        .back-link-icon svg {
            transition: transform 0.15s;
        }

        .section-error-alert {
            margin-bottom: 14px;
            padding: 10px 14px;
            border: 1px solid var(--status-conflict-border);
            border-radius: 6px;
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
            font-size: 13px;
            font-weight: 600;
            line-height: 1.55;
        }

        .student-groups-body {
            display: grid;
            gap: 16px;
        }

        .student-groups-return-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
            border-radius: 8px;
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        }

        .student-groups-return-banner div {
            display: grid;
            gap: 2px;
            min-width: 0;
        }

        .student-groups-return-banner strong {
            color: var(--brand-navy);
            font-weight: 900;
            font-size: 14px;
        }

        .student-groups-return-banner span {
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.55;
        }

        .student-group-create-panel {
            display: grid;
            gap: 14px;
            padding: 16px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 20%, var(--border));
            border-radius: 8px;
            background: color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
        }

        .student-group-create-title {
            display: flex;
            align-items: baseline;
            gap: 8px;
            flex-wrap: wrap;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--brand-navy);
        }

        .student-group-create-title strong {
            color: var(--brand-navy);
            font-size: 16px;
            font-weight: 900;
        }

        .student-group-help {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: start;
            gap: 10px;
            padding: 12px 14px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 28%, var(--border));
            border-radius: 8px;
            background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
            color: var(--brand-navy);
        }

        .student-group-help strong {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 10px;
            border-radius: 999px;
            background: var(--brand-navy);
            color: var(--fg-on-brand);
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
        }

        .student-group-help span {
            color: color-mix(in oklch, var(--brand-navy) 82%, var(--fg-2));
            font-size: 13px;
            font-weight: 800;
            line-height: 1.65;
        }

        .student-group-preview-note {
            color: var(--fg-3);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.55;
        }

        .student-group-create-grid {
            display: grid;
            grid-template-columns: minmax(280px, 1fr) minmax(140px, 220px) auto;
            gap: 14px;
            align-items: end;
        }

        .student-group-inline-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            min-height: 44px;
        }

        .student-group-delete-popover {
            position: fixed;
            inset: 0 0 0 var(--sidebar-w, 0px);
            z-index: calc(var(--z-modal, 200) + 20);
            display: grid;
            place-items: center;
            padding: 18px;
        }

        .student-group-delete-backdrop {
            position: absolute;
            inset: 0;
            background: color-mix(in oklch, var(--brand-navy) 34%, transparent);
        }

        .student-group-delete-dialog {
            position: relative;
            z-index: 1;
            width: min(460px, 100%);
            display: grid;
            grid-template-columns: 52px minmax(0, 1fr);
            gap: 14px;
            padding: 18px;
            border: 1px solid var(--status-conflict-border);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: 0 18px 44px color-mix(in oklch, var(--brand-navy) 24%, transparent);
            transform: translateY(0);
        }

        .student-group-delete-icon {
            position: relative;
            width: 52px;
            height: 52px;
            display: inline-grid;
            place-items: center;
            align-self: start;
            border-radius: 12px;
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
        }

        .student-group-delete-icon::before,
        .student-group-delete-icon::after {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: block;
            background: var(--status-conflict-fg);
        }

        .student-group-delete-icon::before {
            top: 13px;
            width: 4px;
            height: 19px;
            border-radius: 999px;
        }

        .student-group-delete-icon::after {
            top: 36px;
            width: 5px;
            height: 5px;
            border-radius: 50%;
        }

        .student-group-delete-dialog h3 {
            margin: 0 0 4px;
            color: var(--fg-1);
            font-size: 18px;
            font-weight: 900;
            line-height: 1.35;
        }

        .student-group-delete-dialog p {
            margin: 0;
            color: var(--fg-2);
            font-size: 13px;
            font-weight: 700;
            line-height: 1.6;
        }

        .student-group-delete-actions {
            grid-column: 1 / -1;
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            align-items: stretch;
            gap: 10px;
            padding-top: 4px;
        }

        .student-group-delete-action-btn {
            width: 100% !important;
            min-height: 44px !important;
            height: 44px !important;
            display: inline-grid !important;
            place-content: center !important;
            margin: 0 !important;
            padding: 0 14px !important;
            text-align: center !important;
            line-height: 1 !important;
            vertical-align: middle !important;
        }

        .student-group-add-row {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) minmax(140px, 220px);
            gap: 12px;
            align-items: end;
            margin-top: 8px;
            padding: 12px;
            border: 1px dashed color-mix(in oklch, var(--brand-navy) 28%, var(--border));
            border-radius: 8px;
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
        }

        .student-group-add-row label {
            display: grid;
            gap: 7px;
            color: var(--fg-1);
            font-size: 12px;
            font-weight: 800;
        }

        .student-group-select-wrap {
            position: relative;
            display: block;
            min-width: 0;
        }

        .student-group-select-wrap::after {
            content: '';
            position: absolute;
            right: 16px;
            top: 50%;
            width: 9px;
            height: 9px;
            border-right: 2.25px solid var(--brand-navy);
            border-bottom: 2.25px solid var(--brand-navy);
            transform: translateY(-62%) rotate(45deg);
            pointer-events: none;
            z-index: 1;
        }

        /* เมื่อ tpss-custom-select สร้าง trigger (มี chevron ของตัวเอง) → ซ่อนลูกศรเดิม กันซ้อน */
        .student-group-select-wrap:has(.tpss-select-trigger)::after {
            display: none;
        }

        .student-group-source-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            min-height: 44px;
            border: 2px solid var(--border);
            border-radius: 8px;
            background-color: var(--surface);
            background-image: none;
            color: var(--fg-1);
            font: inherit;
            font-weight: 700;
            line-height: 1.45;
            padding-right: 48px !important;
            text-overflow: ellipsis;
            cursor: pointer;
        }

        .student-group-source-select::-ms-expand {
            display: none;
        }

        .student-group-source-select option {
            background: var(--surface);
            color: var(--fg-1);
            font-weight: 700;
        }

        .student-group-source-select option:checked {
            background: color-mix(in oklch, var(--brand-navy) 12%, var(--surface));
            color: var(--brand-navy);
        }

        .student-group-create-grid label,
        .student-group-edit-form label,
        .student-group-field {
            display: grid;
            gap: 7px;
            color: var(--fg-1);
            font-size: 12px;
            font-weight: 800;
        }

        .student-group-field {
            min-width: 0;
        }

        .student-group-field > span {
            display: none;
            color: var(--fg-3);
            font-size: 11px;
            font-weight: 900;
            line-height: 1.35;
        }

        .student-group-create-grid input,
        .student-group-create-grid select,
        .student-group-add-row input,
        .student-group-add-row select,
        .student-group-preview-row input,
        .student-group-preview-row select,
        .student-group-edit-form input,
        .student-group-edit-form select {
            width: 100%;
            min-height: 44px;
            box-sizing: border-box;
            border: 2px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--fg-1);
            font: inherit;
            font-weight: 700;
            padding: 9px 12px;
        }

        .student-group-create-grid input:focus,
        .student-group-create-grid select:focus,
        .student-group-add-row input:focus,
        .student-group-add-row select:focus,
        .student-group-preview-row input:focus,
        .student-group-preview-row select:focus,
        .student-group-edit-form input:focus,
        .student-group-edit-form select:focus {
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px var(--brand-navy-50);
            outline: none;
        }

        .student-group-preview {
            display: grid;
            gap: 9px;
            padding: 12px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
            border-radius: 8px;
            background: var(--surface);
        }

        .student-group-preview-empty,
        .student-group-empty {
            padding: 18px 14px;
            border: 1px dashed color-mix(in oklch, var(--brand-navy) 24%, var(--border));
            border-radius: 8px;
            color: var(--fg-3);
            background: color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
            text-align: center;
            font-size: 13px;
            font-weight: 700;
        }

        .student-group-preview-head,
        .student-group-preview-row {
            display: grid;
            grid-template-columns: 92px minmax(160px, 1fr) minmax(180px, 260px) minmax(110px, 150px) auto;
            gap: 12px;
            align-items: center;
        }

        .student-group-bulk-actions {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) auto;
            align-items: stretch;
            justify-content: space-between;
            gap: 12px;
            min-height: 58px;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
        }

        .student-group-check-all {
            display: inline-grid;
            grid-template-columns: 18px auto;
            align-items: center;
            align-self: stretch;
            width: fit-content;
            gap: 8px;
            min-height: 58px;
            color: var(--fg-1);
            font-size: 13px;
            font-weight: 800;
            line-height: 1;
        }

        .student-group-preview-row input[type='checkbox'],
        .student-group-check-all input {
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: var(--brand-navy);
        }

        .student-group-check-all span {
            display: inline-flex;
            align-items: center;
            min-height: 18px;
            line-height: 1;
            transform: translateY(1px);
        }

        .student-group-editor-row input[type='hidden'] {
            display: none;
        }

        .student-group-mobile-row-head {
            display: grid;
            grid-template-columns: 18px 52px;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .student-group-preview-head {
            color: var(--fg-3);
            font-size: 11px;
            font-weight: 900;
        }

        .student-group-preview-row {
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: color-mix(in oklch, var(--brand-navy) 2%, var(--surface));
            min-width: 0;
        }

        .student-group-preview-row.is-new {
            border-style: dashed;
            border-color: color-mix(in oklch, var(--brand-navy) 34%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 6%, var(--surface));
        }

        .student-group-color-cell {
            display: grid;
            gap: 4px;
            justify-items: center;
        }

        .student-group-new-badge {
            min-height: 18px;
            padding: 0 7px;
            border-radius: 999px;
            background: color-mix(in oklch, var(--brand-navy) 10%, var(--surface));
            color: var(--brand-navy);
            font-size: 10px;
            font-weight: 900;
            line-height: 18px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
        }

        .student-group-preview-row input[type='color'],
        .student-group-edit-form input[type='color'] {
            width: 44px;
            min-height: 44px;
            padding: 3px;
            cursor: pointer;
        }

        .student-group-create-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border);
        }

        .student-group-list {
            display: grid;
            gap: 10px;
        }

        .student-group-edit-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
        }

        .student-group-edit-form {
            display: grid;
            grid-template-columns: 52px minmax(150px, 1fr) minmax(160px, 240px) minmax(110px, 150px) auto;
            gap: 10px;
            align-items: center;
        }

        .btn-danger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            border: 1px solid var(--status-conflict-border);
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
            border-radius: 8px;
            padding: 0 14px;
            font-weight: 800;
            cursor: pointer;
        }

        .course-role-control {
            position: relative;
            flex-shrink: 0;
            width: 170px;
        }

        .course-role-trigger,
        .course-role-readonly,
        .course-role-badge {
            min-height: 38px;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 8px;
            padding: 8px 12px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
            white-space: nowrap;
        }

        .course-role-trigger {
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .course-role-trigger:hover {
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
        }

        .course-role-trigger:focus-visible {
            outline: 2px solid rgba(0, 36, 84, 0.24);
            outline-offset: 2px;
        }

        .course-role-trigger.is-assigned,
        .course-role-readonly.is-assigned {
            background: oklch(96% 0.025 255);
            border: 1px solid oklch(82% 0.055 255);
            color: oklch(34% 0.09 255);
        }

        .course-role-trigger.is-empty,
        .course-role-readonly.is-empty {
            background: oklch(97% 0.045 82);
            border: 1px solid oklch(84% 0.09 82);
            color: oklch(43% 0.1 72);
            font-style: italic;
        }

        .course-role-badge-head {
            flex-shrink: 0;
            width: 170px;
            background: oklch(96% 0.055 150);
            border: 1px solid oklch(78% 0.12 150);
            color: oklch(33% 0.11 150);
        }

        .course-role-dot {
            width: 7px;
            height: 7px;
            flex: 0 0 7px;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.72;
        }

        .course-role-trigger-text {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: center;
        }

        .course-role-chevron {
            flex: 0 0 auto;
            opacity: 0.7;
        }

        .course-role-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            bottom: auto;
            width: min(236px, calc(100vw - 48px));
            max-height: 204px;
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 7px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 26%, var(--border));
            border-radius: 8px;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 3%, var(--surface)), var(--surface) 34%);
            box-shadow: 0 18px 34px -26px rgba(0, 36, 84, 0.44), 0 1px 2px rgba(0, 36, 84, 0.08);
            z-index: 40;
            transform-origin: top right;
            scrollbar-width: thin;
            scrollbar-color: color-mix(in oklch, var(--brand-navy) 34%, transparent) transparent;
        }

        .course-role-menu::-webkit-scrollbar {
            width: 8px;
        }

        .course-role-menu::-webkit-scrollbar-button {
            display: none;
            width: 0;
            height: 0;
        }

        .course-role-menu::-webkit-scrollbar-track {
            background: transparent;
        }

        .course-role-menu::-webkit-scrollbar-thumb {
            border: 2px solid transparent;
            border-radius: 999px;
            background: color-mix(in oklch, var(--brand-navy) 28%, transparent);
            background-clip: padding-box;
        }

        .course-role-menu::-webkit-scrollbar-thumb:hover {
            background: color-mix(in oklch, var(--brand-navy) 42%, transparent);
            background-clip: padding-box;
        }

        .course-role-option {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 9px;
            min-height: 34px;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: var(--fg-1);
            cursor: pointer;
            padding: 8px 10px;
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 700;
            line-height: 1.35;
            text-align: left;
            transition: background 130ms ease, color 130ms ease;
        }

        .course-role-option:hover {
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            color: var(--brand-navy);
        }

        .course-role-option.is-selected {
            background: var(--brand-navy);
            color: var(--fg-on-brand);
            box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--brand-navy) 88%, var(--surface));
        }

        .course-role-option-label {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .form-group:has(.course-rotation-select) .tpss-select-trigger {
            min-height: 44px;
            border-color: color-mix(in oklch, var(--brand-navy) 34%, var(--border));
            background:
                linear-gradient(180deg, var(--surface), color-mix(in oklch, var(--brand-navy) 5%, var(--surface)));
            color: var(--brand-navy);
            font-weight: 750;
        }

        .form-group:has(.course-rotation-select) .tpss-select-menu {
            min-width: min(100%, 420px);
        }

        .course-info-fields.is-locked .tpss-select-trigger,
        .course-info-fields.is-locked textarea {
            cursor: not-allowed;
            opacity: 0.72;
        }

        @keyframes banner-pulse {
            0%, 100% { box-shadow: 0 4px 12px rgba(190,140,0,0.15); }
            50% { box-shadow: 0 4px 24px rgba(190,140,0,0.45), 0 0 0 4px var(--status-warning-bg); }
        }

        [x-cloak] { display: none !important; }

        /* View-mode lock — pure visual; interactivity disabled via HTML `inert` attribute (a11y-safe) */
        .is-locked-section input,
        .is-locked-section textarea,
        .is-locked-section select {
            background: var(--bg-2) !important;
            color: var(--fg-2);
            cursor: default;
        }
        .is-locked-section input[type="checkbox"] {
            visibility: hidden;
        }
        .is-locked-section button:not([type="submit"]) {
            opacity: 0.35;
        }
        /* inert (HTML attr) handles pointer-events + tab order natively in all modern browsers */

        .group-builder {
            margin-bottom: 18px;
            padding: 0;
            border: 2px solid var(--brand-navy-300);
            border-radius: 12px;
            background: var(--surface);
            overflow: hidden;
        }

        .group-builder-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            background: var(--brand-navy-50);
            border-bottom: 1px solid var(--brand-navy-300);
        }

        .group-builder-header-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: var(--brand-navy);
            color: #fff;
            border-radius: 8px;
            flex-shrink: 0;
        }

        .group-builder-body {
            padding: 18px;
        }

        .group-builder-title {
            color: var(--fg-1);
            font-weight: 800;
            font-size: 15px;
            line-height: 1.2;
        }

        .group-builder-subtitle {
            color: var(--fg-3);
            font-size: 12px;
            margin-top: 2px;
        }

        .group-builder-section {
            margin-bottom: 16px;
        }

        .group-builder-section:last-child {
            margin-bottom: 0;
        }

        .group-builder-section-label {
            display: block;
            color: var(--fg-2);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .group-total-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--status-warning-border);
            border-radius: 999px;
            background: var(--status-warning-bg);
            color: var(--status-warning-fg);
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .group-preset-chip {
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 999px;
            font-family: inherit;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--fg-2);
            outline: none;
            transition: all 0.15s;
        }

        .group-preset-chip:hover {
            border-color: var(--brand-navy-300);
            color: var(--brand-navy);
        }

        .group-preset-chip.is-active {
            background: var(--brand-navy);
            color: #fff;
            border-color: var(--brand-navy);
        }

        .group-builder-fields {
            display: grid;
            grid-template-columns: 130px 130px 1fr;
            gap: 14px;
            align-items: end;
        }

        .group-builder-fields label {
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: var(--fg-1);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .group-builder-fields input {
            padding: 10px 14px;
            background: var(--surface);
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            color: var(--fg-1);
            font-family: inherit;
            outline: none;
            transition: all 0.15s;
            box-shadow: 0 1px 0 rgba(0,0,0,0.02);
        }

        .group-builder-fields input:hover {
            border-color: var(--brand-navy-300);
        }

        .group-builder-fields input:focus {
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px var(--brand-navy-50);
        }

        .group-stepper {
            display: inline-flex;
            align-items: stretch;
            border: 2px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            background: var(--surface);
            transition: all 0.15s;
            box-shadow: 0 1px 0 rgba(0,0,0,0.02);
        }

        .group-stepper:hover {
            border-color: var(--brand-navy-300);
        }

        .group-stepper:focus-within {
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px var(--brand-navy-50);
        }

        .group-stepper button {
            width: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-2);
            color: var(--fg-1);
            border: 0;
            cursor: pointer;
            font-size: 18px;
            font-weight: 700;
            outline: none;
            transition: background 0.15s;
        }

        .group-stepper button:hover {
            background: var(--brand-navy);
            color: #fff;
        }

        .group-stepper input {
            width: 70px;
            border: 0 !important;
            border-radius: 0;
            background: transparent;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            color: var(--fg-1);
            -moz-appearance: textfield;
            box-shadow: none;
            padding: 10px 0;
        }

        .group-stepper input::-webkit-outer-spin-button,
        .group-stepper input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .group-mode-row {
            display: inline-flex;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            padding: 3px;
            gap: 2px;
        }

        .group-mode-btn {
            border: 0;
            background: transparent;
            color: var(--fg-2);
            cursor: pointer;
            padding: 6px 14px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            outline: none;
            transition: all 0.15s;
        }

        .group-mode-btn.is-active {
            background: var(--brand-navy);
            color: #fff;
        }

        .group-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .group-preview-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            color: var(--fg-1);
            font-size: 13px;
            transition: border-color 0.15s;
        }

        .group-preview-chip:hover {
            border-color: var(--brand-navy-300);
        }

        .group-preview-chip strong {
            font-weight: 700;
            font-size: 13px;
        }

        .group-preview-chip span:last-child {
            color: var(--fg-3);
            font-size: 12px;
        }

        .group-preview-color {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex: 0 0 10px;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.08);
        }

        .group-count-mini {
            width: 56px;
            border: 1px solid var(--border) !important;
            border-radius: 6px;
            padding: 4px 8px;
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            outline: none;
        }

        .group-builder-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
        }

        .group-builder-submit {
            min-width: 160px;
        }

        .student-group-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .student-group-row {
            display: grid;
            grid-template-columns: minmax(220px, 1.3fr) minmax(120px, 0.55fr) minmax(100px, 0.45fr) auto;
            gap: 12px;
            align-items: center;
            padding: 10px 12px;
            border: 1px solid oklch(90% 0.014 235);
            border-radius: 8px;
            background: rgba(252, 254, 255, 0.98);
        }

        .student-group-row.has-bulk-select {
            grid-template-columns: 32px minmax(220px, 1.3fr) minmax(120px, 0.55fr) minmax(100px, 0.45fr) auto;
        }

        .student-group-row.is-readonly {
            grid-template-columns: minmax(220px, 1.3fr) minmax(120px, 0.55fr) minmax(100px, 0.45fr);
        }

        .student-group-select {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .student-group-select input {
            width: 18px;
            height: 18px;
            min-height: 18px;
            cursor: pointer;
        }

        .student-group-select-all {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--fg-2);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .student-group-select-all input {
            width: 17px;
            height: 17px;
            cursor: pointer;
        }

        .student-group-row label {
            display: flex;
            flex-direction: column;
            gap: 4px;
            color: var(--fg-3);
            font-size: 11px;
            font-weight: 700;
        }

        .student-group-row input {
            min-height: 36px;
            font-size: 14px;
        }

        .student-group-code {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            min-width: 0;
        }

        .student-group-code label {
            flex: 1;
            min-width: 0;
        }

        .student-group-code-display {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .student-group-count input {
            max-width: 120px;
        }

        .student-group-color input[type='color'] {
            width: 52px;
            padding: 3px;
            cursor: pointer;
        }

        .student-group-swatch {
            width: 16px;
            height: 16px;
            flex: 0 0 16px;
            border-radius: 999px;
            margin-bottom: 10px;
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.14);
        }

        .student-group-row.has-bulk-select .student-group-swatch {
            position: absolute;
            left: 0;
            top: 31px;
            margin: 0;
        }

        .student-group-row.has-bulk-select .student-group-code {
            position: relative;
            padding-left: 26px;
        }

        .student-group-actions {
            display: inline-flex;
            justify-content: flex-end;
            gap: 6px;
        }

        .student-group-bulkbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
            padding: 10px 14px;
            border: 1px solid var(--brand-navy-300);
            border-radius: 8px;
            background: var(--brand-navy-50);
        }

        .student-group-bulkbar-info {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8125rem;
            color: var(--fg-2);
            font-weight: 600;
        }

        .student-group-bulkbar-count {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 999px;
            background: var(--brand-navy);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .student-group-bulkbar-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .student-group-bulkbar-divider {
            width: 1px;
            height: 22px;
            background: var(--brand-navy-300);
            opacity: 0.6;
        }

        .btn-bulk-delete {
            min-height: 34px;
            border: 1px solid var(--status-conflict-border);
            border-radius: 8px;
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
            cursor: pointer;
            padding: 6px 12px;
            font-family: inherit;
            font-weight: 700;
        }

        .btn-bulk-delete:disabled {
            cursor: not-allowed;
            opacity: .45;
        }

        .student-group-confirm-overlay {
            position: fixed;
            inset: 0;
            z-index: var(--z-modal);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, .42);
        }

        .student-group-confirm-dialog {
            width: min(460px, 100%);
            border: 1px solid oklch(88% 0.014 235);
            border-radius: 14px;
            background: var(--surface);
            box-shadow: 0 24px 70px rgba(15, 23, 42, .2);
            padding: 22px;
        }

        .student-group-confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .icon-btn-save,
        .icon-btn-delete {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            background: rgba(252, 254, 255, 0.96);
        }

        .icon-btn-save {
            border: 1px solid oklch(82% 0.055 245);
            color: var(--brand-navy);
        }

        .icon-btn-delete {
            border: 1px solid oklch(88% 0.028 30);
            color: oklch(42% 0.12 30);
        }

        .icon-btn-save:hover {
            background: oklch(96% 0.025 245);
        }

        .icon-btn-delete:hover {
            background: oklch(96% 0.035 30);
        }

        .student-group-empty {
            padding: 24px;
            text-align: center;
            color: var(--fg-3);
            border: 1px dashed oklch(86% 0.018 235);
            border-radius: 8px;
            background: oklch(98% 0.008 230);
        }

        @media (max-width: 720px) {
            html,
            body {
                overflow-x: hidden;
            }

            .content-area {
                width: 100%;
                max-width: 100vw;
                overflow-x: hidden;
                padding-left: 10px !important;
                padding-right: 10px !important;
            }

            .page-shell,
            .card,
            .card#course-info,
            .card#student-groups,
            .card#instructors,
            .course-summary-tile,
            .student-group-create-panel,
            .student-group-preview,
            .student-group-add-row,
            .student-group-preview-row,
            .instructor-pool-card {
                max-width: 100%;
                min-width: 0;
                box-sizing: border-box;
            }

            .card#course-info,
            .card#student-groups,
            .card#instructors {
                overflow: hidden !important;
            }

            .card#course-info > .card-hdr,
            .card#student-groups > .card-hdr,
            .card#instructors > .card-hdr {
                padding: 14px;
            }

            .card#instructors > .card-hdr {
                gap: 12px;
                align-items: flex-start;
            }

            .card#instructors > .card-hdr > div:first-child {
                min-width: 0;
            }

            .instructor-section-body {
                padding: 14px !important;
            }

            .instructor-pool-card {
                align-items: flex-start !important;
                flex-wrap: wrap;
                gap: 10px !important;
                padding: 12px !important;
            }

            .instructor-pool-card > div:nth-child(2) {
                flex: 1 1 calc(100% - 48px) !important;
                min-width: 0;
            }

            .card#student-groups > .card-hdr {
                gap: 12px;
                align-items: flex-start;
            }

            .card#student-groups > .card-hdr > div:first-child {
                min-width: 0;
            }

            .student-groups-body {
                padding: 14px !important;
            }

            .student-groups-return-banner {
                grid-template-columns: 1fr;
                align-items: stretch;
            }

            .student-groups-return-banner .btn {
                width: 100%;
                justify-content: center;
            }

            .instructor-pool-actions {
                width: 100%;
                grid-template-columns: 1fr;
                gap:8px;
                margin-left: 48px;
            }
            .course-role-control,
            .course-role-badge-head {
                width: 100%;
            }
            .course-role-trigger,
            .course-role-readonly,
            .course-role-badge,
            .delegate-toggle,
            .delegate-toggle-spacer {
                width: 100%;
            }
            .course-role-menu {
                left: 0;
                right: 0;
                width: 100%;
                min-width: 0;
            }
            .instructor-remove-button {
                width: 100%;
                border-radius: 8px;
                background: var(--status-conflict-bg);
                color: var(--status-conflict-fg);
                border: 1px solid var(--status-conflict-border);
            }
            .instructor-action-spacer {
                display: none;
            }

            .group-builder-main {
                flex-direction: column;
            }

            .group-builder-submit {
                width: 100%;
            }

            .group-builder-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .group-builder-fields {
                grid-template-columns: 1fr 1fr;
            }

            .student-group-row,
            .student-group-row.is-readonly {
                grid-template-columns: 1fr;
            }

            .student-group-row.has-bulk-select {
                grid-template-columns: 32px 1fr;
            }

            .student-group-count input {
                max-width: none;
            }

            .student-group-actions {
                justify-content: flex-start;
            }

            .student-group-help {
                grid-template-columns: 1fr;
                gap: 8px;
                padding: 10px 12px;
            }

            .student-group-help strong {
                justify-self: flex-start;
            }

            .student-group-create-panel,
            .student-group-preview {
                padding: 12px;
            }

            .student-group-create-grid,
            .student-group-add-row,
            .student-group-preview-head,
            .student-group-preview-row {
                grid-template-columns: 1fr;
                width: 100%;
            }

            .student-group-preview-head {
                display: none;
            }

            .student-group-preview-row {
                gap: 9px;
                padding: 10px;
                overflow: hidden;
            }

            .student-group-preview-row input,
            .student-group-preview-row select,
            .student-group-add-row input,
            .student-group-add-row select {
                min-width: 0;
            }

            .student-group-mobile-row-head {
                grid-template-columns: 22px 44px minmax(0, 1fr);
                gap: 9px;
                padding-bottom: 2px;
            }

            .student-group-mobile-row-head::after {
                content: 'เลือกและกำหนดสี';
                color: var(--fg-3);
                font-size: 11px;
                font-weight: 800;
                line-height: 1.35;
            }

            .student-group-color-cell {
                justify-items: start;
                gap: 0;
            }

            .student-group-preview-row input[type='color'] {
                width: 38px;
                min-height: 38px;
                padding: 3px;
            }

            .student-group-new-badge {
                margin-top: 4px;
            }

            .student-group-field > span {
                display: block;
            }

            .student-group-inline-actions,
            .student-group-create-actions,
            .student-group-bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .student-group-bulk-actions {
                grid-template-columns: 1fr;
                min-height: 0;
                padding: 10px;
            }

            .student-group-check-all {
                width: 100%;
                min-height: 40px;
            }

            .student-group-inline-actions {
                justify-content: flex-start;
            }

            .student-group-inline-actions .btn,
            .student-group-create-actions .btn,
            .student-group-preview-row .btn-danger {
                width: 100%;
            }

            .student-group-create-actions {
                gap: 10px;
            }
        }

        @media (max-width: 390px) {
            .page-shell {
                padding-inline: 8px !important;
            }

            .content-area {
                padding-left: 6px !important;
                padding-right: 6px !important;
            }

            .card#course-info,
            .card#student-groups,
            .card#instructors {
                border-radius: 8px;
            }

            .card#student-groups > .card-hdr {
                padding: 14px 12px;
            }

            .card#instructors > .card-hdr {
                padding: 14px 12px;
            }

            .card#student-groups > .card-hdr > div:first-child {
                gap: 10px !important;
            }

            .card#student-groups .card-ttl,
            .card#instructors .card-ttl {
                font-size: 15px;
            }

            .instructor-section-body {
                padding: 10px !important;
            }

            .instructor-pool-card {
                padding: 10px !important;
            }

            .instructor-pool-actions {
                margin-left: 0;
            }

            .student-groups-body {
                padding: 10px !important;
            }

            .student-group-create-panel {
                gap: 12px;
                padding: 10px;
            }

            .student-group-create-title strong {
                font-size: 15px;
            }

            .student-group-help {
                padding: 10px;
            }

            .student-group-help span {
                font-size: 12px;
                line-height: 1.6;
            }

            .student-group-preview {
                padding: 10px;
            }

            .student-group-add-row {
                padding: 10px;
            }

            .student-group-preview-row {
                padding: 9px;
                gap: 8px;
            }

            .student-group-mobile-row-head {
                grid-template-columns: 20px 40px minmax(0, 1fr);
            }

            .student-group-preview-row input[type='color'] {
                width: 36px;
                min-height: 36px;
            }

            .student-group-create-grid input,
            .student-group-create-grid select,
            .student-group-add-row input,
            .student-group-add-row select,
            .student-group-preview-row input,
            .student-group-preview-row select,
            .student-group-edit-form input,
            .student-group-edit-form select {
                min-height: 42px;
                padding: 8px 10px;
            }
        }

        /* Narrow laptop (1280-1440px content area) — reduce paddings + group editor stacking */
        @media (max-width: 1280px) {
            .group-builder-fields {
                grid-template-columns: 110px 110px 1fr;
                gap: 10px;
            }
            .instructor-search-input,
            .course-instructor-search input {
                font-size: 13px;
            }
        }

        /* Student-group section dividers — clear visual separation between create form and list */
        .sg-section {
            margin-top: 22px;
        }
        .sg-section:first-of-type {
            margin-top: 0;
        }
        .sg-section-divider {
            margin: 22px 0 18px;
            border: 0;
            border-top: 1px dashed var(--border);
        }
        .sg-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--brand-navy);
        }
        .sg-section-header-title {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--brand-navy);
            line-height: 1.2;
        }
        .sg-section-header-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 6px;
            background: var(--brand-navy-50);
            color: var(--brand-navy);
            flex-shrink: 0;
        }
        .sg-section-header-meta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: var(--fg-3);
            font-weight: 600;
        }
        .sg-section-header-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 999px;
            background: var(--brand-navy-50);
            color: var(--brand-navy);
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        /* Student-group color swatch + palette popover (Mahidol Navy theme) */
        .sg-swatch-trigger {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px var(--border);
            cursor: pointer;
            outline: none;
            transition: box-shadow 0.15s ease, transform 0.1s ease;
            padding: 0;
        }
        .sg-swatch-trigger:hover {
            box-shadow: 0 0 0 1px var(--brand-navy-300), 0 2px 6px rgba(15, 23, 42, 0.12);
        }
        .sg-swatch-trigger.sg-swatch-open {
            box-shadow: 0 0 0 2px var(--brand-navy);
        }
        .sg-color-popover {
            position: absolute;
            top: 38px;
            left: 0;
            z-index: 30;
            min-width: 232px;
            background: #fff;
            border: 1px solid var(--border);
            border-top: 3px solid var(--brand-navy);
            border-radius: 8px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.16);
            overflow: hidden;
        }
        .sg-color-popover-hdr {
            padding: 12px 14px 10px;
            background: var(--brand-navy-50);
            border-bottom: 1px solid var(--border);
        }
        .sg-color-popover-ttl {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 0.875rem;
            color: var(--brand-navy);
            letter-spacing: 0.01em;
            line-height: 1.2;
        }
        .sg-color-popover-sub {
            margin-top: 4px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: var(--fg-2);
            font-weight: 600;
        }
        .sg-color-popover-chip {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 3px;
            box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.18);
        }
        .sg-color-popover-section {
            padding: 12px 14px 14px;
        }
        .sg-color-popover-label {
            font-size: 0.6875rem;
            font-weight: 700;
            color: var(--fg-3);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 8px;
        }
        .sg-color-swatches {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
        }
        .sg-color-swatch {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid rgba(15, 23, 42, 0.12);
            cursor: pointer;
            outline: none;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            transition: transform 0.1s ease, box-shadow 0.15s ease;
        }
        .sg-color-swatch:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.18);
        }
        .sg-color-swatch.is-selected {
            box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--brand-navy);
        }

        /* Custom color picker section */
        .sg-color-popover-custom {
            border-top: 1px dashed var(--border);
            padding-top: 12px;
        }
        .sg-color-custom-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px;
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            transition: border-color 0.15s ease, background 0.15s ease;
        }
        .sg-color-custom-row:hover {
            border-color: var(--brand-navy-300);
            background: var(--brand-navy-50);
        }
        .sg-color-custom-swatch {
            position: relative;
            display: inline-block;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid rgba(15, 23, 42, 0.12);
            box-shadow: inset 0 0 0 2px #fff;
            flex-shrink: 0;
            overflow: hidden;
        }
        .sg-color-custom-swatch input[type="color"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
            padding: 0;
            background: transparent;
            cursor: pointer;
            opacity: 0;
        }
        .sg-color-custom-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
            line-height: 1.2;
        }
        .sg-color-custom-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--fg-2);
        }
        .sg-color-custom-hex {
            font-size: 0.6875rem;
            font-weight: 700;
            color: var(--brand-navy);
            font-family: var(--font-mono, ui-monospace, SFMono-Regular, Menlo, monospace);
            letter-spacing: 0.04em;
        }

        /* Inline student group editor — at narrow widths fold to simpler 2-col layout */
    </style>


</x-app-layout>
