<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\WorkloadCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * M6-05 — หน้ารายงานภาระงานสอน (admin read-only) + นำออก Excel (CSV)
 * ใช้ตัวเลขจริงจาก WorkloadCalculator::facultyTotalsForYear (single source เดียวกับ dashboard)
 */
class WorkloadReportController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->reportData($request, includeCourseDetails: true);

        $calculator = new WorkloadCalculator;
        $summary = $calculator->facultySummary(
            $data['instructors'],
            $data['instructorHours'],
            $data['teachingWeeks'],
            $data['hoursPerWeek']
        );
        $byLevel = $data['year']
            ? $calculator->facultyHoursByEducationLevel($data['year']->id, null, $data['termSequence'])
            : ['bachelor' => 0, 'master' => 0, 'doctorate' => 0];

        $activeRole = (string) $request->session()->get('active_role');
        $reportRouteName = $this->reportRouteName($activeRole);
        $exportRouteName = $activeRole === 'admin' ? 'admin.reports.workload.export' : null;
        $reportContextLabel = match ($activeRole) {
            'staff' => 'รายงาน / เจ้าหน้าที่',
            'executive' => 'รายงานภาพรวม / ผู้บริหาร',
            default => 'ตารางและรายงาน / ผู้ดูแลระบบ',
        };

        return view('admin.reports.workload', [
            ...$data,
            'summary' => $summary,
            'byLevel' => $byLevel,
            'reportRouteName' => $reportRouteName,
            'exportRouteName' => $exportRouteName,
            'reportContextLabel' => $reportContextLabel,
        ]);
    }

    public function export(Request $request): Response
    {
        $data = $this->reportData($request);

        $termSuffix = $data['termSequence'] ? '-term-' . $data['termSequence'] : '';
        $filename = 'workload-report-' . ($data['year']?->name ?? 'all') . $termSuffix . '.csv';
        $calculator = new WorkloadCalculator;

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['รหัส', 'ชื่อ-นามสกุล', 'ภาควิชา', 'ชั่วโมงสะสมถึงวันนี้', 'ชั่วโมงตามช่วงที่เลือก', 'ชั่วโมงฝึกปฏิบัติ', 'เกณฑ์ทั้งปี (ชม.)', 'ใช้ไปเทียบเกณฑ์ทั้งปี (%)']);

        foreach ($data['instructors'] as $instructor) {
            $hours = $data['instructorHours'][$instructor->id] ?? ['accrued' => 0, 'total' => 0, 'by_category' => []];
            $quota = $calculator->quotaFor($instructor->instructorProfile, $data['teachingWeeks'], $data['hoursPerWeek']);
            $usagePct = ($quota && $quota > 0) ? (int) round($hours['total'] / $quota * 100) : null;

            fputcsv($handle, [
                $instructor->employee_id ?: '-',
                $instructor->formatted_name,
                $instructor->instructorProfile?->department?->name ?? '-',
                $hours['accrued'],
                $hours['total'],
                $hours['by_category']['practicum'] ?? 0,
                $quota !== null ? round($quota, 1) : '-',
                $usagePct ?? '-',
            ]);
        }

        rewind($handle);
        // UTF-8 BOM ให้ Excel (Windows ภาษาไทย) อ่านไม่เพี้ยน
        $csv = "\xEF\xBB\xBF" . stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @return array{
     *     year: ?AcademicYear,
     *     academicYears: Collection,
     *     termOptions: Collection,
     *     termSequence: ?int,
     *     selectedPeriodLabel: string,
     *     instructors: Collection,
     *     instructorHours: array,
     *     instructorCourseDetails: array,
     *     teachingWeeks: int,
     *     hoursPerWeek: int
     * }
     */
    private function reportData(Request $request, bool $includeCourseDetails = false): array
    {
        $validatedYear = $request->validate([
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')],
        ]);

        $academicYears = AcademicYear::query()->orderByDesc('name')->get();
        $year = isset($validatedYear['academic_year_id'])
            ? $academicYears->firstWhere('id', (int) $validatedYear['academic_year_id'])
            : $academicYears->firstWhere('is_active', true);

        $termOptions = $year
            ? $year->terms()->get()->unique('sequence')->sortBy('sequence')->values()
            : collect();
        $availableTermSequences = $termOptions->pluck('sequence')->map(fn ($sequence) => (int) $sequence)->all();
        $validatedTerm = $request->validate([
            'term_sequence' => ['nullable', 'integer', Rule::in($availableTermSequences)],
        ]);
        $termSequence = isset($validatedTerm['term_sequence']) ? (int) $validatedTerm['term_sequence'] : null;
        $selectedTerm = $termSequence
            ? $termOptions->firstWhere('sequence', $termSequence)
            : null;
        $selectedPeriodLabel = $selectedTerm?->name ?: 'ทั้งปีการศึกษา';

        $instructors = User::whereHas('roles', fn ($q) => $q->where('role', 'instructor'))
            ->with(['instructorProfile.department'])
            ->get();
        $calculator = new WorkloadCalculator;
        $instructorHours = $year
            ? $calculator->facultyTotalsForYear($year->id, null, $termSequence)
            : [];
        $instructorCourseDetails = $year && $includeCourseDetails
            ? $calculator->facultyCourseDetailsForYear($year->id, $termSequence)
            : [];
        $teachingWeeks = (int) SystemSetting::get('teaching_load_weeks', 39);
        $hoursPerWeek = (int) SystemSetting::get('teaching_quota_hours_per_week', 35);

        return compact(
            'year',
            'academicYears',
            'termOptions',
            'termSequence',
            'selectedPeriodLabel',
            'instructors',
            'instructorHours',
            'instructorCourseDetails',
            'teachingWeeks',
            'hoursPerWeek'
        );
    }

    private function reportRouteName(string $activeRole): string
    {
        return match ($activeRole) {
            'staff' => 'staff.reports.workload',
            'executive' => 'approver.reports.workload',
            default => 'admin.reports.workload',
        };
    }
}
