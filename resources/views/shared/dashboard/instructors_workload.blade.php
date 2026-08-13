@php
    $instructorHours = $instructorHours ?? [];
    $showCourseDetails = isset($workloadCourseDetails);
    $workloadCourseDetails = $workloadCourseDetails ?? [];
    $instructorWeeklyAverages = $instructorWeeklyAverages ?? [];
    $workloadTotalLabel = $workloadTotalLabel ?? 'ทั้งปี';
    $workloadRows = $instructors->values()->map(function ($instructor) use ($teachingWeeks, $hoursPerWeek, $instructorHours, $workloadCourseDetails, $instructorWeeklyAverages, $workloadTotalLabel) {
        $profile = $instructor->instructorProfile;
        $employmentType = $profile?->employment_type;
        $hasQuota = $profile && $profile->teaching_pct;
        $quota = null;
        $quotaValue = null;
        $period = null;

        if ($hasQuota) {
            $isGov = $employmentType === 'ข้าราชการ';
            $base = $isGov ? ($teachingWeeks * $hoursPerWeek / 2) : ($teachingWeeks * $hoursPerWeek);
            $period = $isGov ? '6 เดือน' : 'ปี';
            $quotaValue = ($base * $profile->teaching_pct) / 100;
            $quota = number_format($quotaValue, 1);
        }

        // ชั่วโมงจริงจาก schedule (M6) — accrued = สะสมถึงวันนี้, total = ทั้งปีที่อนุมัติ
        $hours = $instructorHours[$instructor->id] ?? ['accrued' => 0, 'total' => 0, 'by_category' => []];
        $practicumHours = $hours['by_category']['practicum'] ?? 0;

        // M6-03 — % การใช้เทียบเกณฑ์ (กราฟแท่ง ใช้ vs เกณฑ์)
        $usagePct = ($quotaValue && $quotaValue > 0) ? (int) round($hours['total'] / $quotaValue * 100) : null;
        $rawCourseDetails = collect($workloadCourseDetails[$instructor->id] ?? []);
        $courseDetails = $rawCourseDetails->map(function (array $detail) use ($teachingWeeks) {
            $categories = $detail['by_category'] ?? [];
            $otherHours = collect($categories)->except(['lecture', 'practicum'])->sum();
            $weekCount = max(1, (int) ($detail['teaching_weeks'] ?? $teachingWeeks));
            $weeklyAverage = $detail['weekly_average'] ?? ($detail['total_hours'] / $weekCount);

            return [
                'key' => $detail['course_offering_id'] . ':' . ($detail['term_id'] ?? 'none'),
                'courseCode' => $detail['course_code'],
                'courseName' => $detail['course_name'],
                'termLabel' => $detail['term_label'],
                'courseRole' => $detail['course_role'],
                'scheduleRoles' => $detail['schedule_roles'],
                'scheduleCount' => $detail['schedule_count'],
                'lectureHours' => number_format($categories['lecture'] ?? 0, 1),
                'practicumHours' => number_format($categories['practicum'] ?? 0, 1),
                'otherHours' => number_format($otherHours, 1),
                'totalHours' => number_format($detail['total_hours'], 1),
                'weekCount' => $weekCount,
                'weeklyAverage' => number_format($weeklyAverage, 1),
            ];
        })->values();
        $courseRoleSummaries = collect($workloadCourseDetails[$instructor->id] ?? [])
            ->groupBy('course_role')
            ->map(fn ($details, $role) => [
                'role' => $role,
                'courseCount' => $details->pluck('course_offering_id')->unique()->count(),
                'hours' => number_format($details->sum('total_hours'), 1),
            ])
            ->sortByDesc(fn ($summary) => (float) $summary['hours'])
            ->values();
        $weeklyAverage = $instructorWeeklyAverages[$instructor->id]
            ?? ($rawCourseDetails->isNotEmpty()
                ? $rawCourseDetails->sum('weekly_average')
                : (($hours['total'] ?? 0) / max(1, $teachingWeeks)));

        return [
            'id' => $instructor->id,
            'employeeId' => $instructor->employee_id ?: '-',
            'name' => $instructor->formatted_name,
            'employmentType' => $employmentType,
            'department' => $profile?->department?->name ?: '-',
            'teachingHours' => number_format($hours['accrued'], 1),
            'totalHours' => number_format($hours['total'], 1),
            'weeklyAverage' => number_format($weeklyAverage, 1),
            'totalLabel' => $workloadTotalLabel,
            'sortHours' => (float) $hours['total'],
            'practicumHours' => number_format($practicumHours, 1),
            'hasPracticum' => $practicumHours > 0,
            'usagePct' => $usagePct,
            'overQuota' => $usagePct !== null && $usagePct > 100,
            'hasQuota' => (bool) $hasQuota,
            'quota' => $quota,
            'period' => $period,
            'courseDetails' => $courseDetails,
            'courseRoleSummaries' => $courseRoleSummaries,
            'searchText' => mb_strtolower(trim(($instructor->employee_id ?? '') . ' ' . $instructor->formatted_name)),
        ];
    })
    // A1 — เรียงภาระงานมาก→น้อย ให้คนสอนเยอะ/เกินเกณฑ์ขึ้นก่อน (ชื่อเป็นตัวรอง)
    ->sortBy(fn ($row) => [-$row['sortHours'], $row['name']])
    ->values();

    // A3 — มีใครมีภาระงานจริงไหม (กัน zero-state กำแพง 0)
    $hasWorkload = $workloadRows->contains(fn ($row) => $row['sortHours'] > 0);

    $workloadPagerEnabled = isset($workloadPageSize);
    $workloadPageSize = (int) ($workloadPageSize ?? max($workloadRows->count(), 1));
@endphp

<div class="card workload-card"
     x-data="instructorsWorkloadWidget({ rows: {{ Js::from($workloadRows) }}, perPage: {{ $workloadPageSize }} })">
    <div class="card-hdr">
        <div class="card-ttl">ภาระงานสอนของอาจารย์</div>
        <div class="card-actions">
            @if(!empty($workloadReportUrl))
                <a href="{{ $workloadReportUrl }}" class="workload-report-link">ดูรายงานทั้งหมด</a>
            @endif
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                <input type="text"
                       x-model="searchQuery"
                       @input="resetPage()"
                       placeholder="ค้นหารหัสหรือชื่ออาจารย์...">
            </div>
        </div>
    </div>

    @if($hasWorkload)
    <div class="table-responsive">
        <table>
            <colgroup>
                <col class="workload-col-code">
                <col class="workload-col-name">
                <col class="workload-col-department">
                <col class="workload-col-hours">
                <col class="workload-col-quota">
            </colgroup>
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>ภาควิชา</th>
                    <th style="text-align: right;">ชั่วโมงสอนสะสม</th>
                    <th style="text-align: right; padding-right: 24px;">เกณฑ์ภาระงานสอน</th>
                </tr>
            </thead>
            <template x-for="row in pagedRows" :key="row.id">
                <tbody>
                    <tr :class="{ 'wl-row-over': row.overQuota }">
                        <td class="workload-code-cell" style="font-weight: 600; color: var(--fg-2);" x-text="row.employeeId"></td>
                        <td class="workload-name-cell">
                            <div class="workload-primary-text" style="font-weight: 600; color: var(--fg-1);" x-text="row.name"></div>
                            <template x-if="row.employmentType">
                                <div class="workload-sub-text" style="font-size: 11px; color: var(--fg-3); margin-top: 2px;" x-text="row.employmentType"></div>
                            </template>
                            @if($showCourseDetails)
                                <template x-if="row.courseDetails.length > 0">
                                    <button type="button"
                                            class="workload-detail-toggle"
                                            data-testid="workload-course-details-toggle"
                                            :aria-expanded="(expandedRowId === row.id).toString()"
                                            :aria-controls="`workload-course-details-${row.id}`"
                                            @click="toggleDetails(row.id)">
                                        <span x-text="expandedRowId === row.id ? 'ซ่อนรายละเอียด' : `ดู ${row.courseDetails.length} รายวิชา`"></span>
                                        <svg viewBox="0 0 20 20" aria-hidden="true" :class="{ 'is-open': expandedRowId === row.id }">
                                            <path d="m6 8 4 4 4-4"></path>
                                        </svg>
                                    </button>
                                </template>
                            @endif
                        </td>
                        <td class="workload-department-cell" style="color: var(--fg-2); font-size: 13px;" x-text="row.department"></td>
                        <td style="text-align: right;">
                            <div style="font-weight: 700; color: var(--fg-1); font-size: 14px; font-variant-numeric: tabular-nums;" x-text="row.teachingHours"></div>
                            <div style="font-size: 11px; color: var(--fg-3);">/ <span style="font-variant-numeric: tabular-nums;" x-text="row.totalHours"></span> <span x-text="row.totalLabel"></span></div>
                            <div class="workload-weekly-average" data-testid="workload-weekly-average">
                                เฉลี่ย <span x-text="row.weeklyAverage"></span> ชม./สัปดาห์
                            </div>
                            <template x-if="row.hasPracticum">
                                <div style="font-size: 10px; color: var(--fg-3); margin-top: 2px;">ฝึกปฏิบัติ <span style="font-variant-numeric: tabular-nums; font-weight: 600;" x-text="row.practicumHours"></span> ชม.</div>
                            </template>
                        </td>
                        <td class="workload-quota-cell" style="text-align: right; padding-right: 24px;">
                            <template x-if="row.hasQuota">
                                <div>
                                    <div style="font-weight: 700; color: var(--brand-navy); font-size: 14px;" x-text="row.quota"></div>
                                    <div style="font-size: 11px; color: var(--fg-3);">
                                        ชั่วโมงทำการ / <span x-text="row.period"></span>
                                    </div>
                                    <div class="wl-usage" :class="{ 'is-over': row.overQuota }" :title="`ใช้ไป ${row.usagePct}% ของเกณฑ์`">
                                        <div class="wl-usage-track">
                                            <div class="wl-usage-fill" :style="`width:${Math.min(100, row.usagePct)}%`"></div>
                                        </div>
                                        <span class="wl-usage-label"><span x-text="row.usagePct"></span>%</span>
                                    </div>
                                </div>
                            </template>
                            <template x-if="!row.hasQuota">
                                <span style="color: var(--fg-3); font-style: italic;">- ไม่ระบุ -</span>
                            </template>
                        </td>
                    </tr>

                    @if($showCourseDetails)
                        <tr class="workload-course-detail-row"
                            x-cloak
                            x-show="expandedRowId === row.id">
                            <td colspan="5">
                                <section class="workload-course-detail"
                                    :id="`workload-course-details-${row.id}`"
                                    data-testid="workload-course-details"
                                    :aria-label="`รายละเอียดภาระงานแยกรายวิชาของ ${row.name}`">
                                    <div class="workload-course-detail-head">
                                        <div>
                                            <div class="workload-course-detail-title">รายละเอียดภาระงานแยกรายวิชา</div>
                                            <div class="workload-course-detail-subtitle" x-text="row.name"></div>
                                        </div>
                                        <div class="workload-course-detail-total">
                                            <span>รวมตามช่วงที่เลือก</span>
                                            <strong><span x-text="row.totalHours"></span> ชม.</strong>
                                            <small>เฉลี่ย <span x-text="row.weeklyAverage"></span> ชม./สัปดาห์</small>
                                        </div>
                                    </div>

                                    <div class="workload-role-summary"
                                        data-testid="workload-course-role-summary"
                                        aria-label="สรุปชั่วโมงตามบทบาทรายวิชา">
                                        <template x-for="summary in row.courseRoleSummaries" :key="summary.role">
                                            <button type="button"
                                                class="workload-role-summary-item"
                                                data-testid="workload-course-role-filter"
                                                :class="{ 'is-active': selectedCourseRole === summary.role }"
                                                :aria-pressed="(selectedCourseRole === summary.role).toString()"
                                                :aria-label="`กรองบทบาท ${summary.role} ${summary.hours} ชั่วโมง`"
                                                @click="toggleCourseRole(summary.role)">
                                                <span class="workload-role-summary-name" x-text="summary.role"></span>
                                                <strong><span x-text="summary.hours"></span> ชม.</strong>
                                                <small><span x-text="summary.courseCount"></span> รายวิชา</small>
                                            </button>
                                        </template>
                                    </div>

                                    <div class="workload-course-detail-scroll">
                                        <table class="workload-course-detail-table">
                                            <thead>
                                                <tr>
                                                    <th>รายวิชา</th>
                                                    <th>ภาคเรียน</th>
                                                    <th>บทบาทรายวิชา</th>
                                                    <th>หน้าที่ในคาบ</th>
                                                    <th class="is-number">จำนวนคาบ</th>
                                                    <th class="is-number">บรรยาย</th>
                                                    <th class="is-number">ฝึกปฏิบัติ</th>
                                                    <th class="is-number">อื่น ๆ</th>
                                                    <th class="is-number">รวม</th>
                                                    <th class="is-number">เฉลี่ย/สัปดาห์</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="detail in filteredCourseDetails(row)" :key="detail.key">
                                                    <tr>
                                                        <td>
                                                            <div class="workload-detail-course-code" x-text="detail.courseCode"></div>
                                                            <div class="workload-detail-course-name" x-text="detail.courseName"></div>
                                                        </td>
                                                        <td x-text="detail.termLabel"></td>
                                                        <td><span class="workload-course-role" x-text="detail.courseRole"></span></td>
                                                        <td>
                                                            <div class="workload-detail-roles">
                                                                <template x-for="role in detail.scheduleRoles" :key="role">
                                                                    <span x-text="role"></span>
                                                                </template>
                                                            </div>
                                                        </td>
                                                        <td class="is-number"><span x-text="detail.scheduleCount"></span> คาบ</td>
                                                        <td class="is-number"><span x-text="detail.lectureHours"></span> ชม.</td>
                                                        <td class="is-number"><span x-text="detail.practicumHours"></span> ชม.</td>
                                                        <td class="is-number"><span x-text="detail.otherHours"></span> ชม.</td>
                                                        <td class="is-number is-total"><span x-text="detail.totalHours"></span> ชม.</td>
                                                        <td class="is-number workload-detail-average">
                                                            <strong><span x-text="detail.weeklyAverage"></span> ชม.</strong>
                                                            <small><span x-text="detail.weekCount"></span> สัปดาห์</small>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </template>

            <tbody>
                <tr x-show="filteredRows.length === 0">
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--fg-3);">
                        ไม่พบข้อมูลอาจารย์
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    @else
        <div class="workload-empty" data-testid="workload-empty-state">
            <div class="workload-empty-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 11l3 3L22 4"></path>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                </svg>
            </div>
            <div class="workload-empty-title">ยังไม่มีภาระงานให้แสดง</div>
            <div class="workload-empty-desc">ตัวเลขจะปรากฏเมื่อมีรายวิชาที่ผู้บริหาร<strong>อนุมัติแล้ว</strong> — ตารางที่ยังเป็นร่างหรือรออนุมัติจะยังไม่ถูกนับ</div>
        </div>
    @endif

    <div class="workload-pagination" x-show="{{ $workloadPagerEnabled && $hasWorkload ? 'rows.length > 0' : 'false' }}">
        <div class="workload-pagination-meta">
            <span class="workload-pagination-summary" x-show="totalPages > 1">
                แสดง <span x-text="rangeStart"></span>–<span x-text="rangeEnd"></span> จาก <span x-text="filteredRows.length.toLocaleString()"></span> รายการ
            </span>
            <span class="workload-pagination-summary" x-show="totalPages <= 1">
                ทั้งหมด <span x-text="filteredRows.length.toLocaleString()"></span> รายการ
            </span>
            <a href="{{ route('admin.master_data', ['tab' => 'instructors']) }}" class="workload-view-all">
                ดูข้อมูลอาจารย์ทั้งหมด
            </a>
        </div>

        <nav class="workload-pagination-nav" aria-label="Pagination" x-show="totalPages > 1">
            <button type="button"
                    class="workload-page-btn"
                    :disabled="currentPage === 1"
                    @click.prevent="goToPage(currentPage - 1)"
                    aria-label="หน้าก่อนหน้า">&lt;</button>

            <template x-for="(page, index) in pageNumbers" :key="`${page}-${index}`">
                <span class="workload-page-slot">
                    <span x-show="page === '...'" class="workload-page-gap">...</span>
                    <button type="button"
                            class="workload-page-btn"
                            x-show="page !== '...'"
                            :class="{ 'is-current': page === currentPage }"
                            :aria-current="page === currentPage ? 'page' : null"
                            @click.prevent="goToPage(page)"
                            x-text="page"></button>
                </span>
            </template>

            <button type="button"
                    class="workload-page-btn"
                    :disabled="currentPage === totalPages"
                    @click.prevent="goToPage(currentPage + 1)"
                    aria-label="หน้าถัดไป">&gt;</button>
        </nav>
    </div>
</div>

<script>
    window.instructorsWorkloadWidget = function(config) {
        return {
            rows: config.rows || [],
            searchQuery: '',
            currentPage: 1,
            expandedRowId: null,
            selectedCourseRole: null,
            perPage: Math.max(Number(config.perPage || 5), 1),

            get filteredRows() {
                const query = this.searchQuery.trim().toLowerCase();
                if (!query) return this.rows;

                return this.rows.filter((row) => String(row.searchText || '').includes(query));
            },

            get totalPages() {
                return Math.max(1, Math.ceil(this.filteredRows.length / this.perPage));
            },

            get pagedRows() {
                if (this.currentPage > this.totalPages) this.currentPage = this.totalPages;
                const start = (this.currentPage - 1) * this.perPage;
                return this.filteredRows.slice(start, start + this.perPage);
            },

            get rangeStart() {
                if (this.filteredRows.length === 0) return 0;
                return ((this.currentPage - 1) * this.perPage) + 1;
            },

            get rangeEnd() {
                return Math.min(this.currentPage * this.perPage, this.filteredRows.length);
            },

            get pageNumbers() {
                const total = this.totalPages;
                if (total <= 7) {
                    return Array.from({ length: total }, (_, index) => index + 1);
                }

                const pages = [1];
                const start = Math.max(2, this.currentPage - 1);
                const end = Math.min(total - 1, this.currentPage + 1);

                if (start > 2) pages.push('...');
                for (let page = start; page <= end; page += 1) pages.push(page);
                if (end < total - 1) pages.push('...');
                pages.push(total);

                return pages;
            },

            resetPage() {
                this.currentPage = 1;
                this.expandedRowId = null;
                this.selectedCourseRole = null;
            },

            goToPage(page) {
                if (page === '...') return;
                this.currentPage = Math.min(Math.max(Number(page), 1), this.totalPages);
                this.expandedRowId = null;
                this.selectedCourseRole = null;
            },

            toggleDetails(rowId) {
                const isClosing = this.expandedRowId === rowId;
                this.expandedRowId = isClosing ? null : rowId;
                this.selectedCourseRole = null;
            },

            toggleCourseRole(role) {
                this.selectedCourseRole = this.selectedCourseRole === role ? null : role;
            },

            filteredCourseDetails(row) {
                if (!this.selectedCourseRole) return row.courseDetails;

                return row.courseDetails.filter((detail) => detail.courseRole === this.selectedCourseRole);
            },
        };
    };
</script>

<style>
    .workload-card {
        --workload-head-height: 58px;
        --workload-row-height: 89px;
        overflow: hidden;
    }

    .workload-card .card-hdr {
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 9%, var(--surface)), transparent 72%),
            color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
    }

    .workload-card .card-hdr {
        flex-wrap: wrap;
        gap: 10px 14px;
    }

    .workload-card .card-actions {
        flex: 0 1 360px;
        min-width: 260px;
    }

    .workload-report-link {
        display: inline-flex;
        min-height: 40px;
        align-items: center;
        padding: 8px 12px;
        border-radius: var(--r-md);
        color: var(--brand-navy);
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        white-space: nowrap;
    }

    .workload-report-link:hover,
    .workload-report-link:focus-visible {
        background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
    }

    .workload-report-link:focus-visible {
        outline: 2px solid var(--brand-navy);
        outline-offset: 2px;
    }

    .workload-card .search-box {
        width: 100%;
        max-width: 360px;
        min-height: 42px;
        gap: 10px;
        padding: 0 14px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 20%, var(--border));
        border-radius: var(--r-md);
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface));
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.06),
            inset 0 1px 0 rgba(255, 255, 255, 0.76);
        transition:
            border-color 160ms ease,
            background 160ms ease,
            box-shadow 160ms ease,
            transform 160ms ease;
    }

    .workload-card .search-box:hover {
        border-color: color-mix(in oklch, var(--brand-navy) 30%, var(--border));
        background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        box-shadow:
            0 2px 5px rgba(0, 36, 84, 0.08),
            inset 0 1px 0 rgba(255, 255, 255, 0.8);
    }

    .workload-card .search-box:focus-within {
        border-color: color-mix(in oklch, var(--brand-navy) 70%, var(--border));
        background: var(--surface);
        box-shadow:
            0 0 0 3px color-mix(in oklch, var(--brand-navy) 13%, transparent),
            0 8px 18px -16px rgba(0, 36, 84, 0.36);
        transform: translateY(-1px);
    }

    .workload-card .search-box svg {
        width: 16px;
        height: 16px;
        color: color-mix(in oklch, var(--brand-navy) 56%, var(--fg-3));
        flex: 0 0 auto;
    }

    .workload-card .search-box input {
        max-width: none;
        min-width: 0;
        height: 40px;
        font-size: 13px;
        font-weight: 650;
        line-height: 1.45;
        color: var(--fg-1);
    }

    .workload-card .search-box input::placeholder {
        color: color-mix(in oklch, var(--brand-navy) 34%, var(--fg-3));
    }

    .workload-card .table-responsive {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
    }

    .workload-card table {
        table-layout: fixed;
        min-width: 760px;
    }

    .workload-card thead tr {
        height: var(--workload-head-height);
        background: color-mix(in oklch, var(--brand-navy) 9%, var(--surface));
    }

    .workload-card tbody tr {
        height: var(--workload-row-height);
        background: var(--surface);
        transition:
            background 160ms ease,
            box-shadow 160ms ease;
    }

    .workload-card tbody tr:nth-child(even) {
        background: color-mix(in oklch, var(--brand-navy) 2.5%, var(--surface));
    }

    .workload-card tbody tr:hover {
        background: color-mix(in oklch, var(--brand-navy) 6%, var(--surface));
        box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--brand-navy) 18%, transparent);
    }

    .workload-card th,
    .workload-card td {
        overflow: hidden;
        vertical-align: middle;
    }

    .workload-card th {
        color: color-mix(in oklch, var(--brand-navy) 70%, var(--fg-2));
    }

    .workload-col-code { width: 10%; }
    .workload-col-name { width: 23%; }
    .workload-col-department { width: 33%; }
    .workload-col-hours { width: 16%; }
    .workload-col-quota { width: 18%; }

    .workload-weekly-average {
        margin-top: 3px;
        color: color-mix(in oklch, var(--brand-navy) 72%, var(--fg-3));
        font-size: 10px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        line-height: 1.35;
    }

    .workload-code-cell,
    .workload-name-cell,
    .workload-department-cell,
    .workload-quota-cell {
        min-width: 0;
    }

    .workload-primary-text,
    .workload-sub-text,
    .workload-department-cell {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .workload-detail-toggle {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        min-height: 28px;
        margin-top: 5px;
        padding: 3px 7px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
        border-radius: var(--r-sm);
        background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
        color: var(--brand-navy);
        font-family: inherit;
        font-size: 11px;
        font-weight: 750;
        line-height: 1.35;
        cursor: pointer;
        transition: background 160ms ease, border-color 160ms ease;
    }

    .workload-detail-toggle:hover {
        border-color: color-mix(in oklch, var(--brand-navy) 45%, var(--border));
        background: color-mix(in oklch, var(--brand-navy) 9%, var(--surface));
    }

    .workload-detail-toggle:focus-visible {
        outline: 2px solid var(--brand-navy);
        outline-offset: 2px;
    }

    .workload-detail-toggle svg {
        width: 15px;
        height: 15px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
        transition: transform 180ms cubic-bezier(.22, 1, .36, 1);
    }

    .workload-detail-toggle svg.is-open {
        transform: rotate(180deg);
    }

    .workload-card tbody tr.workload-course-detail-row,
    .workload-card tbody tr.workload-course-detail-row:hover {
        height: auto;
        background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
        box-shadow: none;
    }

    .workload-course-detail-row > td {
        padding: 0;
        overflow: visible;
    }

    .workload-course-detail {
        padding: 18px 20px 20px;
        border-top: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
        border-bottom: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
        background: color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
    }

    .workload-course-detail-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 12px;
    }

    .workload-course-detail-title {
        color: var(--fg-1);
        font-size: 14px;
        font-weight: 800;
        line-height: 1.55;
    }

    .workload-course-detail-subtitle {
        margin-top: 1px;
        color: var(--fg-3);
        font-size: 12px;
        line-height: 1.55;
    }

    .workload-course-detail-total {
        flex: 0 0 auto;
        text-align: right;
    }

    .workload-course-detail-total span {
        display: block;
        color: var(--fg-3);
        font-size: 10px;
        line-height: 1.4;
    }

    .workload-course-detail-total strong {
        display: block;
        margin-top: 2px;
        color: var(--brand-navy);
        font-size: 15px;
        font-variant-numeric: tabular-nums;
        line-height: 1.4;
    }

    .workload-course-detail-total strong span {
        display: inline;
        color: inherit;
        font-size: inherit;
    }

    .workload-course-detail-total small {
        display: block;
        margin-top: 2px;
        color: color-mix(in oklch, var(--brand-navy) 70%, var(--fg-3));
        font-size: 10px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        line-height: 1.4;
    }

    .workload-course-detail-total small span {
        display: inline;
        color: inherit;
        font-size: inherit;
    }

    .workload-role-summary {
        display: flex;
        align-items: stretch;
        gap: 6px;
        margin-bottom: 10px;
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .workload-role-summary-item {
        display: grid;
        grid-template-columns: minmax(104px, 1fr) auto;
        align-items: baseline;
        gap: 0 10px;
        min-width: 178px;
        padding: 6px 9px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 16%, var(--border));
        border-radius: var(--r-sm);
        background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
        color: inherit;
        font-family: inherit;
        text-align: left;
        cursor: pointer;
        transition: background 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
    }

    .workload-role-summary-item:hover {
        border-color: color-mix(in oklch, var(--brand-navy) 42%, var(--border));
        background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
    }

    .workload-role-summary-item:focus-visible {
        outline: 2px solid var(--brand-navy);
        outline-offset: 2px;
    }

    .workload-role-summary-item.is-active {
        border-color: var(--brand-navy);
        background: color-mix(in oklch, var(--brand-navy) 12%, var(--surface));
        box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--brand-navy) 16%, transparent);
    }

    .workload-role-summary-name {
        overflow: hidden;
        color: var(--fg-1);
        font-size: 13px;
        font-weight: 800;
        line-height: 1.5;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .workload-role-summary-item strong {
        color: var(--brand-navy);
        font-size: 12px;
        font-variant-numeric: tabular-nums;
        line-height: 1.4;
        white-space: nowrap;
    }

    .workload-role-summary-item small {
        grid-column: 1 / -1;
        margin-top: 1px;
        color: var(--fg-3);
        font-size: 9px;
        line-height: 1.4;
    }

    .workload-course-detail-scroll {
        overflow-x: auto;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 17%, var(--border));
        border-radius: var(--r-md);
        background: var(--surface);
    }

    .workload-card table.workload-course-detail-table {
        width: 100%;
        min-width: 1140px;
        table-layout: auto;
    }

    .workload-card .workload-course-detail-table thead tr {
        height: 42px;
        background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
    }

    .workload-card .workload-course-detail-table tbody tr,
    .workload-card .workload-course-detail-table tbody tr:nth-child(even),
    .workload-card .workload-course-detail-table tbody tr:hover {
        height: auto;
        min-height: 54px;
        background: var(--surface);
        box-shadow: none;
    }

    .workload-card .workload-course-detail-table tbody tr + tr {
        border-top: 1px solid color-mix(in oklch, var(--brand-navy) 10%, var(--border));
    }

    .workload-card .workload-course-detail-table th,
    .workload-card .workload-course-detail-table td {
        padding: 9px 11px;
        overflow: visible;
        color: var(--fg-2);
        font-size: 11px;
        line-height: 1.5;
        white-space: nowrap;
    }

    .workload-card .workload-course-detail-table th {
        font-weight: 800;
    }

    .workload-card .workload-course-detail-table th:first-child,
    .workload-card .workload-course-detail-table td:first-child {
        min-width: 190px;
        white-space: normal;
    }

    .workload-card .workload-course-detail-table .is-number {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .workload-card .workload-course-detail-table .is-total {
        color: var(--brand-navy);
        font-weight: 800;
    }

    .workload-detail-average strong,
    .workload-detail-average small {
        display: block;
        white-space: nowrap;
    }

    .workload-detail-average strong {
        color: var(--brand-navy);
        font-size: 11px;
        font-weight: 800;
    }

    .workload-detail-average small {
        margin-top: 1px;
        color: var(--fg-3);
        font-size: 9px;
        line-height: 1.35;
    }

    .workload-detail-course-code {
        color: var(--brand-navy);
        font-weight: 800;
    }

    .workload-detail-course-name {
        margin-top: 1px;
        color: var(--fg-3);
    }

    .workload-detail-roles {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
    }

    .workload-detail-roles span {
        display: inline-flex;
        align-items: center;
        min-height: 23px;
        padding: 2px 7px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 14%, var(--border));
        border-radius: 999px;
        background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        color: color-mix(in oklch, var(--brand-navy) 78%, var(--fg-2));
        font-size: 10px;
        font-weight: 700;
    }

    .workload-course-role {
        display: inline-flex;
        align-items: center;
        min-height: 25px;
        padding: 3px 8px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 23%, var(--border));
        border-radius: var(--r-sm);
        background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
        color: var(--brand-navy);
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
    }

    /* M6-03 — แท่งภาระงาน ใช้ vs เกณฑ์ */
    .wl-usage {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
        margin-top: 5px;
    }

    .wl-usage-track {
        width: 64px;
        height: 6px;
        border-radius: 999px;
        background: color-mix(in oklch, var(--brand-navy) 12%, var(--surface));
        overflow: hidden;
    }

    .wl-usage-fill {
        height: 100%;
        border-radius: 999px;
        background: var(--brand-navy);
        transition: width 200ms ease;
    }

    .wl-usage-label {
        min-width: 30px;
        font-size: 10px;
        font-weight: 700;
        color: var(--fg-3);
        font-variant-numeric: tabular-nums;
        text-align: right;
    }

    .wl-usage.is-over .wl-usage-fill {
        background: var(--status-warning-fg);
    }

    .wl-usage.is-over .wl-usage-label {
        color: var(--status-warning-fg);
    }

    /* A2 — แถวเกินเกณฑ์: ขอบซ้าย + พื้นโทนเตือน (semantic signal เท่านั้น) */
    .workload-card tbody tr.wl-row-over {
        background: var(--status-warning-bg);
        box-shadow: inset 3px 0 0 var(--status-warning-fg);
    }

    .workload-card tbody tr.wl-row-over:hover {
        background: color-mix(in oklch, var(--status-warning-fg) 12%, var(--surface));
    }

    /* A3 — zero-state (flat, navy-muted) */
    .workload-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 8px;
        padding: 44px 24px;
    }

    .workload-empty-icon {
        width: 52px;
        height: 52px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
        color: color-mix(in oklch, var(--brand-navy) 65%, var(--fg-3));
        border: 1px solid color-mix(in oklch, var(--brand-navy) 16%, var(--border));
    }

    .workload-empty-title {
        margin-top: 4px;
        font-family: var(--font-display);
        font-size: 17px;
        font-weight: 800;
        color: var(--fg-1);
    }

    .workload-empty-desc {
        max-width: 460px;
        font-size: 13px;
        color: var(--fg-3);
        line-height: 1.6;
    }

    .workload-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 12px 20px;
        border-top: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
        background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
    }

    .workload-pagination-summary {
        color: var(--fg-3);
        font-size: 12px;
        line-height: 1.45;
    }

    .workload-pagination-meta {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        min-width: 0;
    }

    .workload-view-all {
        display: inline-flex;
        align-items: center;
        min-height: 34px;
        padding: 6px 12px;
        border: 1px solid var(--brand-navy);
        border-radius: var(--r-sm);
        background: var(--brand-navy);
        color: var(--fg-on-brand);
        font-size: 12px;
        font-weight: 800;
        line-height: 1.25;
        text-decoration: none;
        box-shadow:
            0 1px 2px rgba(0, 36, 84, 0.16),
            0 10px 18px -16px rgba(0, 36, 84, 0.58);
        transition:
            background 160ms ease,
            border-color 160ms ease,
            color 160ms ease,
            box-shadow 160ms ease,
            transform 160ms ease;
    }

    .workload-view-all:hover,
    .workload-view-all:focus-visible {
        border-color: var(--brand-navy-700);
        background: var(--brand-navy-700);
        color: var(--fg-on-brand);
        box-shadow:
            0 2px 4px rgba(0, 36, 84, 0.16),
            0 12px 22px -16px rgba(0, 36, 84, 0.58);
        transform: translateY(-1px);
        outline: none;
    }

    .workload-pagination-nav {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
    }

    .workload-page-slot {
        display: contents;
    }

    .workload-page-btn,
    .workload-page-gap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 28%, var(--border));
        border-radius: 6px;
        background: var(--surface);
        color: var(--brand-navy);
        font-size: 13px;
        text-decoration: none;
    }

    .workload-page-btn {
        cursor: pointer;
    }

    .workload-page-btn:hover:not(:disabled):not(.is-current),
    .workload-page-btn:focus-visible:not(:disabled):not(.is-current) {
        border-color: var(--brand-navy);
        background: color-mix(in oklch, var(--brand-navy) 10%, var(--surface));
        outline: none;
    }

    .workload-page-btn.is-current {
        border-color: var(--brand-navy);
        background: var(--brand-navy);
        color: var(--fg-on-brand);
        cursor: default;
    }

    .workload-page-btn:disabled {
        color: var(--fg-3);
        cursor: default;
        opacity: .55;
    }

    .workload-page-gap {
        border-color: transparent;
        background: transparent;
        color: var(--fg-3);
    }

    @media (max-width: 720px) {
        .workload-card .card-actions,
        .workload-card .search-box {
            flex-basis: 100%;
            max-width: none;
            min-width: 0;
        }

        .workload-card table {
            min-width: 680px;
        }

        .workload-pagination {
            align-items: flex-start;
            justify-content: flex-start;
            padding: 12px 14px;
        }

        .workload-pagination-meta {
            width: 100%;
        }
    }

    @media (max-width: 540px) {
        .workload-pagination-nav {
            width: 100%;
        }
    }
</style>
