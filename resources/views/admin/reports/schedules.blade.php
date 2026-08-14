<x-app-layout>
    <x-async-filter scope="schedule-report" class="schedule-report-page">
        @php
            $resultCount = method_exists($schedules, 'total') ? $schedules->total() : $schedules->count();
        @endphp

        @include('shared.dashboard.role_header', [
            'kicker' => $reportContextLabel,
            'title' => 'รายงานตารางสอน',
            'description' => 'ค้นหาและส่งออกตารางที่เผยแพร่แล้วตามปี ภาคเรียน รายวิชา กลุ่มนักศึกษา ผู้สอน และห้อง',
        ])

        <section class="schedule-report-toolbar" aria-labelledby="schedule-report-filter-title">
            <div class="schedule-report-toolbar__heading">
                <div>
                    <p class="schedule-report-eyebrow">ตัวกรองรายงาน</p>
                    <h2 id="schedule-report-filter-title">เลือกขอบเขตตาราง</h2>
                </div>
                <p class="schedule-report-result" aria-live="polite">
                    พบ <strong>{{ number_format($resultCount) }}</strong> รายการ
                </p>
            </div>

            <form method="GET"
                  action="{{ route($reportRouteName) }}"
                  data-async-filter-form
                  class="schedule-report-filters">
                <x-filter-select name="academic_year_id"
                                 label="ปีการศึกษา"
                                 reset="term_sequence,curriculum_id,course_offering_id,student_group_id,instructor_id,room_id">
                    @foreach($academicYears as $academicYear)
                        <option value="{{ $academicYear->id }}" @selected($year?->id === $academicYear->id)>
                            {{ $academicYear->name }}{{ $academicYear->is_active ? ' · ปัจจุบัน' : '' }}
                        </option>
                    @endforeach
                </x-filter-select>

                <x-filter-select name="term_sequence"
                                 label="ภาคเรียน"
                                 reset="course_offering_id,student_group_id">
                    <option value="">ทุกภาคเรียน</option>
                    @foreach($termOptions as $term)
                        <option value="{{ $term->sequence }}" @selected($termSequence === (int) $term->sequence)>
                            {{ $term->name }}
                        </option>
                    @endforeach
                </x-filter-select>

                <x-filter-select name="curriculum_id"
                                 label="หลักสูตร"
                                 reset="course_offering_id,student_group_id">
                    <option value="">ทุกหลักสูตร</option>
                    @foreach($curriculums as $curriculum)
                        <option value="{{ $curriculum->id }}" @selected($curriculumId === $curriculum->id)>
                            {{ $curriculum->name }}
                        </option>
                    @endforeach
                </x-filter-select>

                <x-filter-select name="course_offering_id"
                                 label="รายวิชา"
                                 reset="student_group_id">
                    <option value="">ทุกรายวิชา</option>
                    @foreach($courseOfferings as $offering)
                        <option value="{{ $offering->id }}" @selected($courseOfferingId === $offering->id)>
                            {{ $offering->course?->course_code }} · {{ $offering->course?->name_th }}
                        </option>
                    @endforeach
                </x-filter-select>

                <x-filter-select name="student_group_id" label="กลุ่มนักศึกษา">
                    <option value="">ทุกกลุ่ม</option>
                    @foreach($studentGroups as $studentGroup)
                        <option value="{{ $studentGroup->id }}" @selected($studentGroupId === $studentGroup->id)>
                            {{ $studentGroup->group_code }} · {{ $studentGroup->courseOffering?->course?->course_code }}
                        </option>
                    @endforeach
                </x-filter-select>

                <x-filter-select name="instructor_id" label="อาจารย์ผู้สอน">
                    <option value="">อาจารย์ทุกคน</option>
                    @foreach($instructors as $instructor)
                        <option value="{{ $instructor->id }}" @selected($instructorId === $instructor->id)>
                            {{ $instructor->formatted_name }}
                        </option>
                    @endforeach
                </x-filter-select>

                <x-filter-select name="room_id" label="ห้อง / สถานที่">
                    <option value="">ทุกห้อง</option>
                    @foreach($rooms as $room)
                        <option value="{{ $room->id }}" @selected($roomId === $room->id)>
                            {{ $room->room_code }}{{ $room->room_name ? ' · ' . $room->room_name : '' }}
                        </option>
                    @endforeach
                </x-filter-select>
            </form>

            <div class="schedule-report-actions" aria-label="ส่งออกรายงาน">
                <a href="{{ route($pdfRouteName, $filters) }}"
                   class="schedule-report-button schedule-report-button--secondary"
                   data-testid="schedule-report-export-pdf">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <path d="M14 2v6h6M8 15h8M8 18h5"/>
                    </svg>
                    นำออก PDF
                </a>
                <a href="{{ route($excelRouteName, $filters) }}"
                   class="schedule-report-button schedule-report-button--primary"
                   data-testid="schedule-report-export-excel">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 3v12M7 10l5 5 5-5"/>
                        <path d="M5 21h14a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2"/>
                    </svg>
                    นำออก Excel
                </a>
            </div>
        </section>

        <section class="schedule-report-results" aria-labelledby="schedule-report-table-title">
            <div class="schedule-report-results__heading">
                <div>
                    <p class="schedule-report-eyebrow">ตารางที่เผยแพร่แล้ว</p>
                    <h2 id="schedule-report-table-title">
                        {{ $year?->name ?? 'ไม่ระบุปี' }} · {{ $selectedTerm?->name ?? 'ทุกภาคเรียน' }}
                    </h2>
                </div>
                <p>เรียงตามวันที่และเวลาเริ่มสอน</p>
            </div>

            @if($resultCount > 0)
                <div class="schedule-report-table-wrap">
                    <table class="schedule-report-table">
                        <thead>
                            <tr>
                                <th scope="col">วันที่ / เวลา</th>
                                <th scope="col">รายวิชา</th>
                                <th scope="col">กิจกรรม / หัวข้อ</th>
                                <th scope="col">กลุ่มนักศึกษา</th>
                                <th scope="col">ผู้สอน</th>
                                <th scope="col">ห้อง / สถานที่</th>
                                <th scope="col">หมายเหตุ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($schedules as $schedule)
                                @php
                                    $startDate = $schedule->start_date?->format('d/m/Y') ?? '-';
                                    $endDate = $schedule->end_date?->format('d/m/Y');
                                    $dateLabel = $endDate && $endDate !== $startDate
                                        ? $startDate . ' - ' . $endDate
                                        : $startDate;
                                @endphp
                                <tr>
                                    <td class="schedule-report-table__period">
                                        <strong>{{ $dateLabel }}</strong>
                                        <span>{{ substr((string) $schedule->start_time, 0, 5) }} - {{ substr((string) $schedule->end_time, 0, 5) }} น.</span>
                                        <small>{{ $schedule->term?->name ?? 'ไม่ระบุภาคเรียน' }}</small>
                                    </td>
                                    <td>
                                        <strong class="schedule-report-course-code">{{ $schedule->courseOffering?->course?->course_code }}</strong>
                                        <span>{{ $schedule->courseOffering?->course?->name_th }}</span>
                                        <small>{{ $schedule->courseOffering?->course?->curriculum?->name }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ $schedule->activityType?->name ?? '-' }}</strong>
                                        <span>{{ $schedule->topic ?: '-' }}</span>
                                    </td>
                                    <td>
                                        <div class="schedule-report-tags">
                                            @forelse($schedule->studentGroups as $group)
                                                <span>{{ $group->group_code }}</span>
                                            @empty
                                                <span>ไม่ระบุกลุ่ม</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td>
                                        <ul class="schedule-report-names">
                                            @forelse($schedule->instructors as $instructor)
                                                <li>{{ $instructor->formatted_name }}</li>
                                            @empty
                                                <li>-</li>
                                            @endforelse
                                        </ul>
                                    </td>
                                    <td>
                                        <strong>{{ $schedule->room?->room_name ?: $schedule->room?->room_code ?: '-' }}</strong>
                                        @if($schedule->room?->building)
                                            <span>{{ $schedule->room->building }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $schedule->remark ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(method_exists($schedules, 'hasPages') && $schedules->hasPages())
                    <nav class="schedule-report-pagination" aria-label="หน้ารายงาน">
                        @if($schedules->previousPageUrl())
                            <a href="{{ $schedules->previousPageUrl() }}" data-async-filter-link>หน้าก่อนหน้า</a>
                        @else
                            <span aria-disabled="true">หน้าก่อนหน้า</span>
                        @endif
                        <strong>หน้า {{ $schedules->currentPage() }} จาก {{ $schedules->lastPage() }}</strong>
                        @if($schedules->nextPageUrl())
                            <a href="{{ $schedules->nextPageUrl() }}" data-async-filter-link>หน้าถัดไป</a>
                        @else
                            <span aria-disabled="true">หน้าถัดไป</span>
                        @endif
                    </nav>
                @endif
            @else
                <div class="schedule-report-empty" role="status">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="17" rx="2"/>
                        <path d="M8 2v4M16 2v4M3 9h18M8 14h8"/>
                    </svg>
                    <h3>ไม่พบตารางสอนตามตัวกรอง</h3>
                    <p>ลองเลือกทุกหลักสูตรหรือทุกกลุ่ม เพื่อขยายขอบเขตข้อมูล</p>
                </div>
            @endif
        </section>

        <style>
            .schedule-report-page {
                display: grid;
                gap: 20px;
            }

            .schedule-report-toolbar,
            .schedule-report-results {
                border: 1px solid var(--border, #d7e0ea);
                border-radius: 10px;
                background: var(--surface, #fbfcfe);
            }

            .schedule-report-toolbar {
                padding: 20px;
            }

            .schedule-report-toolbar__heading,
            .schedule-report-results__heading {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 20px;
            }

            .schedule-report-toolbar h2,
            .schedule-report-results h2 {
                margin: 2px 0 0;
                color: var(--fg-1, #10213b);
                font-family: var(--font-display);
                font-size: 1.2rem;
                line-height: 1.35;
            }

            .schedule-report-eyebrow {
                margin: 0;
                color: var(--fg-3, #60718a);
                font-size: .75rem;
                font-weight: 700;
                letter-spacing: .04em;
            }

            .schedule-report-result {
                margin: 0;
                color: var(--fg-2, #40536e);
                font-variant-numeric: tabular-nums;
            }

            .schedule-report-result strong {
                color: var(--brand-navy, #002454);
                font-size: 1.15rem;
            }

            .schedule-report-filters {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 14px;
                margin-top: 18px;
            }

            .schedule-report-actions {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                margin-top: 18px;
                padding-top: 16px;
                border-top: 1px solid var(--border, #d7e0ea);
            }

            .schedule-report-button {
                display: inline-flex;
                min-height: 42px;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 9px 16px;
                border: 1px solid var(--brand-navy, #002454);
                border-radius: 8px;
                font-weight: 700;
                text-decoration: none;
                transition: background-color .18s ease-out, color .18s ease-out, border-color .18s ease-out;
            }

            .schedule-report-button svg {
                width: 18px;
                height: 18px;
                fill: none;
                stroke: currentColor;
                stroke-width: 1.8;
                stroke-linecap: round;
                stroke-linejoin: round;
            }

            .schedule-report-button--primary {
                background: var(--brand-navy, #002454);
                color: #f8fafc;
            }

            .schedule-report-button--secondary {
                background: #f8fafc;
                color: var(--brand-navy, #002454);
            }

            .schedule-report-button:hover,
            .schedule-report-button:focus-visible {
                border-color: #174c82;
                background: #174c82;
                color: #f8fafc;
            }

            .schedule-report-button:focus-visible {
                outline: 3px solid rgba(23, 76, 130, .22);
                outline-offset: 2px;
            }

            .schedule-report-results {
                overflow: hidden;
            }

            .schedule-report-results__heading {
                padding: 18px 20px;
                border-bottom: 1px solid var(--border, #d7e0ea);
            }

            .schedule-report-results__heading > p {
                margin: 3px 0 0;
                color: var(--fg-3, #60718a);
                font-size: .82rem;
            }

            .schedule-report-table-wrap {
                overflow-x: auto;
            }

            .schedule-report-table {
                width: 100%;
                min-width: 1120px;
                border-collapse: collapse;
                color: var(--fg-1, #10213b);
                font-size: .82rem;
            }

            .schedule-report-table th {
                padding: 12px 14px;
                background: #edf4f8;
                color: #233e5f;
                font-size: .75rem;
                font-weight: 700;
                text-align: left;
                white-space: nowrap;
            }

            .schedule-report-table td {
                padding: 14px;
                border-top: 1px solid #dde5ed;
                vertical-align: top;
                line-height: 1.55;
            }

            .schedule-report-table tbody tr:nth-child(even) {
                background: #f7f9fc;
            }

            .schedule-report-table td strong,
            .schedule-report-table td span,
            .schedule-report-table td small {
                display: block;
            }

            .schedule-report-table td span {
                color: var(--fg-2, #40536e);
            }

            .schedule-report-table td small {
                margin-top: 2px;
                color: var(--fg-3, #60718a);
            }

            .schedule-report-table__period,
            .schedule-report-course-code {
                font-variant-numeric: tabular-nums;
            }

            .schedule-report-course-code {
                color: var(--brand-navy, #002454);
                font-family: var(--font-mono, monospace);
            }

            .schedule-report-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }

            .schedule-report-tags span {
                display: inline-flex !important;
                padding: 3px 7px;
                border: 1px solid #c8d5e3;
                border-radius: 999px;
                background: #f3f7fa;
                color: #274766 !important;
                font-size: .74rem;
                font-weight: 700;
            }

            .schedule-report-names {
                margin: 0;
                padding: 0;
                list-style: none;
            }

            .schedule-report-names li + li {
                margin-top: 4px;
            }

            .schedule-report-empty {
                display: grid;
                justify-items: center;
                padding: 52px 20px;
                text-align: center;
            }

            .schedule-report-empty svg {
                width: 36px;
                height: 36px;
                fill: none;
                stroke: #617792;
                stroke-width: 1.6;
                stroke-linecap: round;
            }

            .schedule-report-empty h3 {
                margin: 12px 0 4px;
                color: var(--fg-1, #10213b);
                font-size: 1rem;
            }

            .schedule-report-empty p {
                margin: 0;
                color: var(--fg-3, #60718a);
            }

            .schedule-report-pagination {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 14px;
                padding: 14px 20px;
                border-top: 1px solid var(--border, #d7e0ea);
                color: var(--fg-2, #40536e);
            }

            .schedule-report-pagination a,
            .schedule-report-pagination span {
                min-width: 104px;
                padding: 8px 12px;
                border: 1px solid #c8d5e3;
                border-radius: 7px;
                color: var(--brand-navy, #002454);
                text-align: center;
                text-decoration: none;
            }

            .schedule-report-pagination span {
                color: #7b899c;
                background: #f3f5f8;
            }

            @media (max-width: 720px) {
                .schedule-report-toolbar__heading,
                .schedule-report-results__heading {
                    align-items: stretch;
                    flex-direction: column;
                    gap: 8px;
                }

                .schedule-report-filters {
                    grid-template-columns: 1fr;
                }

                .schedule-report-actions {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                }

                .schedule-report-pagination {
                    flex-wrap: wrap;
                }

                .schedule-report-pagination strong {
                    order: -1;
                    width: 100%;
                    text-align: center;
                }
            }
        </style>
    </x-async-filter>
</x-app-layout>
