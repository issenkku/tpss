<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * แหล่งคำนวณชั่วโมงภาระงานจาก schedule จริง (M6) — single source of truth
 * ใช้ร่วมกันทั้งหน้า PA อาจารย์ / widget admin / ผู้บริหาร / Excel export
 *
 * กฎที่เคาะไว้ (ดู .claude/rules/sprint-status.md M6):
 * - ภาระงาน = บวกชั่วโมงทุกการ์ดที่มีจริง นับ "รายวัน" ใน [start_date, end_date]
 *   (ไม่กรองชนิดวัน — เสาร์-อาทิตย์/วันหยุดถ้าสร้างการ์ดได้ก็นับ ตามที่ตกลง)
 * - คีย์ที่ start_date/end_date เท่านั้น (accessor fallback teaching_date) — ห้ามอ่าน teaching_date ตรง ๆ
 *   เพราะแถวที่สร้างหลัง migration block-date จะมี teaching_date = null
 * - กิจกรรมที่ activity_type.counts_toward_workload = false → 0 ชม.
 */
class WorkloadCalculator
{
    /**
     * โหมดคิดชั่วโมงเมื่อ 1 กิจกรรมมีผู้สอนหลายคน (ยังรอลูกค้ายืนยัน — daily update)
     * 'full'  = ทุกคนได้เต็ม (default ที่ตกลง)
     * 'split' = หารตามจำนวนผู้สอน
     * พลิกที่ค่าคงที่นี้จุดเดียวเมื่อลูกค้าเคาะ — ไม่ต้องแก้ที่อื่น/ไม่ต้อง migrate ข้อมูล
     */
    private const TEAM_TEACHING_MODE = 'full';

    /**
     * ชั่วโมงภาระงานรวมของ 1 การ์ด = (ชม./วัน) × จำนวนวันใน [start_date, end_date]
     * บรรยายครั้งเดียว (start_date = end_date) → 1 วัน
     */
    public function hoursFor(Schedule $schedule): float
    {
        if ($schedule->activityType && $schedule->activityType->counts_toward_workload === false) {
            return 0.0;
        }

        return $this->hoursPerDay($schedule) * $this->dayCount($schedule->start_date, $schedule->end_date);
    }

    /**
     * ชั่วโมงสะสมถึงวัน asOf = นับเฉพาะวันที่ผ่านไปแล้วใน [start_date, min(end_date, asOf)]
     * block ที่เพิ่งผ่านครึ่งทาง → ได้เฉพาะวันที่ผ่านมา (ไม่ใช่ทั้งก้อน)
     */
    public function accruedHoursFor(Schedule $schedule, CarbonInterface|string|null $asOf = null): float
    {
        if ($schedule->activityType && $schedule->activityType->counts_toward_workload === false) {
            return 0.0;
        }

        $asOfDay = $asOf
            ? CarbonImmutable::parse($asOf)->startOfDay()
            : CarbonImmutable::now()->startOfDay();

        $start = $schedule->start_date ? CarbonImmutable::parse($schedule->start_date)->startOfDay() : null;

        if (! $start || $asOfDay->lt($start)) {
            return 0.0;
        }

        $end = $schedule->end_date ? CarbonImmutable::parse($schedule->end_date)->startOfDay() : $start;
        $cappedEnd = $asOfDay->lt($end) ? $asOfDay : $end;

        return $this->hoursPerDay($schedule) * ($this->wholeDaysBetween($start, $cappedEnd) + 1);
    }

    /**
     * ชั่วโมงที่จะนับให้ "ผู้สอน 1 คน" บนการ์ดนี้ — รองรับ team mode
     * full → ทุกคนได้เต็ม (= hoursFor) · split → หารจำนวนผู้สอน
     */
    public function hoursForInstructor(Schedule $schedule): float
    {
        if (self::TEAM_TEACHING_MODE === 'split') {
            $count = max(1, $schedule->instructors->count());

            return $this->hoursFor($schedule) / $count;
        }

        return $this->hoursFor($schedule);
    }

    /**
     * รวมชั่วโมงภาระงานรายอาจารย์ทั้งคณะในปีการศึกษา (approved + counts_toward_workload)
     * single source สำหรับ widget dashboard (admin/staff/executive) + หน้ารายงานภาระงาน
     *
     * @return array<int, array{accrued: float, total: float, by_category: array<string, float>}>
     */
    public function facultyTotalsForYear(int $academicYearId, CarbonInterface|string|null $asOf = null): array
    {
        $today = $asOf ? CarbonImmutable::parse($asOf)->startOfDay() : CarbonImmutable::today();
        $totals = [];

        Schedule::query()
            ->where('status', 'approved')
            ->whereHas('activityType', fn ($q) => $q->where('counts_toward_workload', true))
            ->whereHas('courseOffering', fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->with(['activityType', 'instructors:id'])
            ->get()
            ->each(function (Schedule $schedule) use ($today, &$totals): void {
                $perInstructorTotal = $this->hoursForInstructor($schedule);
                $perInstructorAccrued = $this->accruedHoursFor($schedule, $today);
                $category = $schedule->activityType?->category ?: 'other';

                foreach ($schedule->instructors as $instructor) {
                    $totals[$instructor->id] ??= ['accrued' => 0.0, 'total' => 0.0, 'by_category' => []];
                    $totals[$instructor->id]['total'] += $perInstructorTotal;
                    $totals[$instructor->id]['accrued'] += $perInstructorAccrued;
                    $totals[$instructor->id]['by_category'][$category]
                        = ($totals[$instructor->id]['by_category'][$category] ?? 0.0) + $perInstructorTotal;
                }
            });

        return array_map(fn (array $row) => [
            'accrued' => round($row['accrued'], 1),
            'total' => round($row['total'], 1),
            'by_category' => array_map(fn ($hours) => round($hours, 1), $row['by_category']),
        ], $totals);
    }

    /**
     * รวมชั่วโมงภาระงานทั้งคณะแยกตามระดับหลักสูตร (person-hours — สอดคล้องกับ facultyTotalsForYear)
     * ผู้บริหารดูว่าอาจารย์สอนระดับใดบ้าง จำนวนเท่าไร
     *
     * @return array{bachelor: float, master: float, doctorate: float}
     */
    public function facultyHoursByEducationLevel(int $academicYearId, CarbonInterface|string|null $asOf = null): array
    {
        $totals = ['bachelor' => 0.0, 'master' => 0.0, 'doctorate' => 0.0];

        Schedule::query()
            ->where('status', 'approved')
            ->whereHas('activityType', fn ($q) => $q->where('counts_toward_workload', true))
            ->whereHas('courseOffering', fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->with(['activityType', 'instructors:id', 'courseOffering.course.curriculum:id,education_level'])
            ->get()
            ->each(function (Schedule $schedule) use (&$totals): void {
                $level = $schedule->courseOffering?->course?->curriculum?->education_level;

                if (! $level || ! array_key_exists($level, $totals)) {
                    return;
                }

                $perInstructor = $this->hoursForInstructor($schedule);
                foreach ($schedule->instructors as $instructor) {
                    $totals[$level] += $perInstructor;
                }
            });

        return array_map(fn ($hours) => round($hours, 1), $totals);
    }

    private function hoursPerDay(Schedule $schedule): float
    {
        if (! $schedule->start_time || ! $schedule->end_time) {
            return 0.0;
        }

        $start = CarbonImmutable::parse((string) $schedule->start_time);
        $end = CarbonImmutable::parse((string) $schedule->end_time);

        return max(0, $start->diffInMinutes($end, false) / 60);
    }

    private function dayCount(CarbonInterface|string|null $start, CarbonInterface|string|null $end): int
    {
        if (! $start) {
            return 0;
        }

        $startDay = CarbonImmutable::parse($start)->startOfDay();
        $endDay = $end ? CarbonImmutable::parse($end)->startOfDay() : $startDay;

        return $this->wholeDaysBetween($startDay, $endDay) + 1;
    }

    private function wholeDaysBetween(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        return (int) round($start->diffInDays($end));
    }
}
