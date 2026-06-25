<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\WorkloadCalculator;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * M6-05 — หน้ารายงานภาระงานสอน (admin read-only) + นำออก Excel (CSV)
 * ใช้ตัวเลขจริงจาก WorkloadCalculator::facultyTotalsForYear (single source เดียวกับ dashboard)
 */
class WorkloadReportController extends Controller
{
    public function index()
    {
        [$year, $instructors, $instructorHours, $teachingWeeks, $hoursPerWeek] = $this->reportData();

        $summary = $this->summarize($instructors, $instructorHours, $teachingWeeks, $hoursPerWeek);
        $byLevel = $year ? (new WorkloadCalculator)->facultyHoursByEducationLevel($year->id) : ['bachelor' => 0, 'master' => 0, 'doctorate' => 0];

        return view('admin.reports.workload', compact(
            'year', 'instructors', 'instructorHours', 'teachingWeeks', 'hoursPerWeek', 'summary', 'byLevel'
        ));
    }

    /**
     * สรุปภาพรวมภาระงานทั้งคณะ (จากข้อมูลรายอาจารย์ที่ดึงมาแล้ว — ไม่ query ซ้ำ)
     *
     * @return array{instructor_count: int, total_hours: float, practicum_hours: float, over_quota_count: int}
     */
    private function summarize(Collection $instructors, array $instructorHours, int $teachingWeeks, int $hoursPerWeek): array
    {
        $instructorCount = 0;
        $totalHours = 0.0;
        $practicumHours = 0.0;
        $overQuota = 0;

        foreach ($instructors as $instructor) {
            $hours = $instructorHours[$instructor->id] ?? null;

            if (! $hours || $hours['total'] <= 0) {
                continue;
            }

            $instructorCount++;
            $totalHours += $hours['total'];
            $practicumHours += $hours['by_category']['practicum'] ?? 0;

            $quota = $this->quotaFor($instructor, $teachingWeeks, $hoursPerWeek);
            if ($quota && $quota > 0 && $hours['total'] > $quota) {
                $overQuota++;
            }
        }

        return [
            'instructor_count' => $instructorCount,
            'total_hours' => round($totalHours, 1),
            'practicum_hours' => round($practicumHours, 1),
            'over_quota_count' => $overQuota,
        ];
    }

    public function export(): Response
    {
        [$year, $instructors, $instructorHours, $teachingWeeks, $hoursPerWeek] = $this->reportData();

        $filename = 'workload-report-' . ($year?->name ?? 'all') . '.csv';

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['รหัส', 'ชื่อ-นามสกุล', 'ภาควิชา', 'ชั่วโมงสะสมถึงวันนี้', 'ชั่วโมงทั้งปี', 'ชั่วโมงฝึกปฏิบัติ', 'เกณฑ์ (ชม.)', 'ใช้ไป (%)']);

        foreach ($instructors as $instructor) {
            $hours = $instructorHours[$instructor->id] ?? ['accrued' => 0, 'total' => 0, 'by_category' => []];
            $quota = $this->quotaFor($instructor, $teachingWeeks, $hoursPerWeek);
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
     * @return array{0: ?AcademicYear, 1: Collection, 2: array, 3: int, 4: int}
     */
    private function reportData(): array
    {
        $year = AcademicYear::where('is_active', true)->orderByDesc('name')->first();
        $instructors = User::whereHas('roles', fn ($q) => $q->where('role', 'instructor'))
            ->with(['instructorProfile.department'])
            ->get();
        $instructorHours = $year ? (new WorkloadCalculator)->facultyTotalsForYear($year->id) : [];
        $teachingWeeks = (int) SystemSetting::get('teaching_load_weeks', 39);
        $hoursPerWeek = (int) SystemSetting::get('teaching_quota_hours_per_week', 35);

        return [$year, $instructors, $instructorHours, $teachingWeeks, $hoursPerWeek];
    }

    private function quotaFor(User $instructor, int $teachingWeeks, int $hoursPerWeek): ?float
    {
        $profile = $instructor->instructorProfile;

        if (! $profile || ! $profile->teaching_pct) {
            return null;
        }

        $isGov = $profile->employment_type === 'ข้าราชการ';
        $base = $isGov ? ($teachingWeeks * $hoursPerWeek / 2) : ($teachingWeeks * $hoursPerWeek);

        return ($base * $profile->teaching_pct) / 100;
    }
}
