<x-app-layout title="รายงานภาระงานสอน">
    <x-async-filter scope="workload-report" class="role-dashboard">
        @include('shared.dashboard.role_header', [
            'kicker' => $reportContextLabel,
            'title'  => 'รายงานภาระงานสอน',
            'desc'   => 'สรุปชั่วโมงสอนจริงรายอาจารย์ทั้งคณะ (จากตารางที่อนุมัติแล้ว) เทียบกับเกณฑ์ภาระงาน'
                . ($exportRouteName ? ' และนำออกเป็นไฟล์ Excel ได้' : ''),
        ])

        <div class="workload-report-toolbar" data-testid="workload-report-filters">
            <form method="GET"
                  action="{{ route($reportRouteName) }}"
                  class="workload-report-filters">
                <x-filter-select
                    id="workload-academic-year"
                    name="academic_year_id"
                    label="ปีการศึกษา"
                    reset="term_sequence">
                        @forelse($academicYears as $academicYear)
                            <option value="{{ $academicYear->id }}" @selected($year?->id === $academicYear->id)>
                                {{ $academicYear->name }}{{ $academicYear->is_active ? ' (ปัจจุบัน)' : '' }}
                            </option>
                        @empty
                            <option value="">ยังไม่มีปีการศึกษา</option>
                        @endforelse
                </x-filter-select>

                <x-filter-select
                    id="workload-term"
                    name="term_sequence"
                    label="ภาคเรียน"
                    :disabled="$termOptions->isEmpty()">
                        <option value="">ทุกภาคเรียน</option>
                        @foreach($termOptions as $term)
                            <option value="{{ $term->sequence }}" @selected($termSequence === (int) $term->sequence)>
                                {{ $term->name ?: 'ภาคเรียนที่ ' . $term->sequence }}
                            </option>
                        @endforeach
                </x-filter-select>

            </form>

            @if($exportRouteName)
                <a href="{{ route($exportRouteName, array_filter([
                        'academic_year_id' => $year?->id,
                        'term_sequence' => $termSequence,
                    ], fn ($value) => $value !== null)) }}"
                   class="btn btn-primary workload-export-button"
                   data-testid="workload-export-csv">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    นำออก Excel (.csv)
                </a>
            @endif
        </div>

        <div class="workload-report-period" aria-live="polite">
            กำลังแสดง: ปีการศึกษา <strong>{{ $year?->name ?? '-' }}</strong>, <strong>{{ $selectedPeriodLabel }}</strong>
        </div>

        @if(($summary['total_hours'] ?? 0) > 0)
            @include('admin.reports._summary')
        @endif

        @include('shared.dashboard.instructors_workload', ['workloadTotalLabel' => $selectedPeriodLabel])
    </x-async-filter>

    <style>
        .workload-report-toolbar {
            display: grid;
            gap: 12px;
            padding: 16px;
            margin-bottom: 8px;
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            background: var(--surface);
        }

        .workload-report-filters {
            display: grid;
            gap: 12px;
        }

        .workload-export-button:focus-visible {
            outline: 2px solid var(--brand-navy);
            outline-offset: 2px;
        }

        .workload-export-button {
            min-height: 44px;
            justify-content: center;
        }

        .workload-export-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .workload-report-period {
            margin-bottom: 16px;
            color: var(--fg-3);
            font-size: 13px;
            line-height: 1.55;
        }

        .workload-report-period strong {
            color: var(--fg-1);
            font-weight: 700;
        }

        @media (min-width: 700px) {
            .workload-report-toolbar {
                grid-template-columns: minmax(0, 1fr) auto;
                align-items: end;
            }

            .workload-report-filters {
                grid-template-columns: minmax(180px, 240px) minmax(180px, 240px);
                align-items: end;
            }
        }
    </style>
</x-app-layout>
