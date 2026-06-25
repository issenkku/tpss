@php
    $instructorHours = $instructorHours ?? [];
    $workloadRows = $instructors->values()->map(function ($instructor) use ($teachingWeeks, $hoursPerWeek, $instructorHours) {
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

        return [
            'id' => $instructor->id,
            'employeeId' => $instructor->employee_id ?: '-',
            'name' => $instructor->formatted_name,
            'employmentType' => $employmentType,
            'department' => $profile?->department?->name ?: '-',
            'teachingHours' => number_format($hours['accrued'], 1),
            'totalHours' => number_format($hours['total'], 1),
            'practicumHours' => number_format($practicumHours, 1),
            'hasPracticum' => $practicumHours > 0,
            'usagePct' => $usagePct,
            'overQuota' => $usagePct !== null && $usagePct > 100,
            'hasQuota' => (bool) $hasQuota,
            'quota' => $quota,
            'period' => $period,
            'searchText' => mb_strtolower(trim(($instructor->employee_id ?? '') . ' ' . $instructor->formatted_name)),
        ];
    });

    $workloadPagerEnabled = isset($workloadPageSize);
    $workloadPageSize = (int) ($workloadPageSize ?? max($workloadRows->count(), 1));
@endphp

<div class="card workload-card"
     x-data="instructorsWorkloadWidget({ rows: {{ Js::from($workloadRows) }}, perPage: {{ $workloadPageSize }} })">
    <div class="card-hdr">
        <div class="card-ttl">ภาระงานสอนของอาจารย์</div>
        <div class="card-actions">
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
            <tbody>
                <template x-for="row in pagedRows" :key="row.id">
                    <tr>
                        <td class="workload-code-cell" style="font-weight: 600; color: var(--fg-2);" x-text="row.employeeId"></td>
                        <td class="workload-name-cell">
                            <div class="workload-primary-text" style="font-weight: 600; color: var(--fg-1);" x-text="row.name"></div>
                            <template x-if="row.employmentType">
                                <div class="workload-sub-text" style="font-size: 11px; color: var(--fg-3); margin-top: 2px;" x-text="row.employmentType"></div>
                            </template>
                        </td>
                        <td class="workload-department-cell" style="color: var(--fg-2); font-size: 13px;" x-text="row.department"></td>
                        <td style="text-align: right;">
                            <div style="font-weight: 700; color: var(--fg-1); font-size: 14px; font-variant-numeric: tabular-nums;" x-text="row.teachingHours"></div>
                            <div style="font-size: 11px; color: var(--fg-3);">/ <span style="font-variant-numeric: tabular-nums;" x-text="row.totalHours"></span> ทั้งปี</div>
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
                </template>

                <tr x-show="filteredRows.length === 0">
                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--fg-3);">
                        ไม่พบข้อมูลอาจารย์
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="workload-pagination" x-show="{{ $workloadPagerEnabled ? 'rows.length > 0' : 'false' }}">
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
            },

            goToPage(page) {
                if (page === '...') return;
                this.currentPage = Math.min(Math.max(Number(page), 1), this.totalPages);
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
