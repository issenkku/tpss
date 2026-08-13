<?php

namespace App\Services;

use App\Models\InstructorProfile;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

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
    public function facultyTotalsForYear(
        int $academicYearId,
        CarbonInterface|string|null $asOf = null,
        ?int $termSequence = null
    ): array
    {
        $today = $asOf ? CarbonImmutable::parse($asOf)->startOfDay() : CarbonImmutable::today();
        $totals = [];

        Schedule::query()
            ->where('status', 'approved')
            ->whereHas('activityType', fn ($q) => $q->where('counts_toward_workload', true))
            ->whereHas('courseOffering', fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->when($termSequence, fn ($q) => $q->whereHas(
                'term',
                fn ($termQuery) => $termQuery->where('sequence', $termSequence)
            ))
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
     * รายละเอียดภาระงานรายอาจารย์ แยกตามรายวิชาและภาคเรียน
     * ใช้กติกาเดียวกับ facultyTotalsForYear เพื่อให้ผลรวมของรายละเอียดตรงกับยอดสรุปเสมอ
     *
     * @return array<int, array<int, array{
     *     course_offering_id: int,
     *     course_code: string,
     *     course_name: string,
     *     term_id: ?int,
     *     term_sequence: ?int,
     *     term_label: string,
     *     roles: array<int, string>,
     *     schedule_count: int,
     *     by_category: array<string, float>,
     *     total_hours: float
     * }>>
     */
    public function facultyCourseDetailsForYear(int $academicYearId, ?int $termSequence = null): array
    {
        $details = [];

        Schedule::query()
            ->where('status', 'approved')
            ->whereHas('activityType', fn ($q) => $q->where('counts_toward_workload', true))
            ->whereHas('courseOffering', fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->when($termSequence, fn ($q) => $q->whereHas(
                'term',
                fn ($termQuery) => $termQuery->where('sequence', $termSequence)
            ))
            ->with([
                'activityType:id,category',
                'term:id,name,sequence',
                'courseOffering:id,course_id',
                'courseOffering.course:id,course_code,name_th,name_en',
                'instructors:id',
            ])
            ->get()
            ->each(function (Schedule $schedule) use (&$details): void {
                $offering = $schedule->courseOffering;
                $course = $offering?->course;

                if (! $offering || ! $course) {
                    return;
                }

                $hours = $this->hoursForInstructor($schedule);
                $category = $schedule->activityType?->category ?: 'other';
                $termKey = $schedule->term_id ?: 0;

                foreach ($schedule->instructors as $instructor) {
                    $key = $offering->id . ':' . $termKey;
                    $details[$instructor->id][$key] ??= [
                        'course_offering_id' => (int) $offering->id,
                        'course_code' => (string) ($course->course_code ?: '-'),
                        'course_name' => (string) ($course->name_th ?: $course->name_en ?: '-'),
                        'term_id' => $schedule->term_id ? (int) $schedule->term_id : null,
                        'term_sequence' => $schedule->term?->sequence
                            ? (int) $schedule->term->sequence
                            : null,
                        'term_label' => (string) ($schedule->term?->name ?: 'ไม่ระบุภาคเรียน'),
                        'roles' => [],
                        'schedule_count' => 0,
                        'by_category' => [],
                        'total_hours' => 0.0,
                    ];

                    $role = $instructor->pivot?->is_lead ? 'ผู้สอนหลัก' : 'ผู้ร่วมสอน';
                    $details[$instructor->id][$key]['roles'][$role] = true;
                    $details[$instructor->id][$key]['schedule_count']++;
                    $details[$instructor->id][$key]['by_category'][$category]
                        = ($details[$instructor->id][$key]['by_category'][$category] ?? 0.0) + $hours;
                    $details[$instructor->id][$key]['total_hours'] += $hours;
                }
            });

        foreach ($details as $instructorId => $rows) {
            $details[$instructorId] = collect($rows)
                ->map(function (array $row): array {
                    $roles = array_keys($row['roles']);
                    usort($roles, fn (string $left, string $right) => match (true) {
                        $left === $right => 0,
                        $left === 'ผู้สอนหลัก' => -1,
                        $right === 'ผู้สอนหลัก' => 1,
                        default => strcmp($left, $right),
                    });

                    $row['roles'] = $roles;
                    $row['by_category'] = array_map(
                        fn ($hours) => round($hours, 1),
                        $row['by_category']
                    );
                    $row['total_hours'] = round($row['total_hours'], 1);

                    return $row;
                })
                ->sortBy(fn (array $row) => sprintf(
                    '%s|%04d',
                    $row['course_code'],
                    $row['term_sequence'] ?? 9999
                ))
                ->values()
                ->all();
        }

        return $details;
    }

    /**
     * รวมชั่วโมงภาระงานทั้งคณะแยกตามระดับหลักสูตร (person-hours — สอดคล้องกับ facultyTotalsForYear)
     * ผู้บริหารดูว่าอาจารย์สอนระดับใดบ้าง จำนวนเท่าไร
     *
     * @return array{bachelor: float, master: float, doctorate: float}
     */
    public function facultyHoursByEducationLevel(
        int $academicYearId,
        CarbonInterface|string|null $asOf = null,
        ?int $termSequence = null
    ): array
    {
        $totals = ['bachelor' => 0.0, 'master' => 0.0, 'doctorate' => 0.0];

        Schedule::query()
            ->where('status', 'approved')
            ->whereHas('activityType', fn ($q) => $q->where('counts_toward_workload', true))
            ->whereHas('courseOffering', fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->when($termSequence, fn ($q) => $q->whereHas(
                'term',
                fn ($termQuery) => $termQuery->where('sequence', $termSequence)
            ))
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

    /**
     * เกณฑ์ชั่วโมงสอนของอาจารย์ 1 คน = ฐาน (สัปดาห์ × ชม./สัปดาห์ · ข้าราชการ ÷2) × teaching_pct
     * null = ไม่ได้กรอก % การสอน
     */
    public function quotaFor(?InstructorProfile $profile, int $teachingWeeks, int $hoursPerWeek): ?float
    {
        if (! $profile || ! $profile->teaching_pct) {
            return null;
        }

        $isGov = $profile->employment_type === 'ข้าราชการ';
        $base = $isGov ? ($teachingWeeks * $hoursPerWeek / 2) : ($teachingWeeks * $hoursPerWeek);

        return ($base * $profile->teaching_pct) / 100;
    }

    /**
     * สรุปภาพรวมภาระงานทั้งคณะ (จากข้อมูลรายอาจารย์ที่ดึงมาแล้ว — ไม่ query ซ้ำ)
     * ใช้ร่วม: หน้ารายงาน (admin) + dashboard ผู้บริหาร
     *
     * @return array{instructor_count: int, total_hours: float, practicum_hours: float, over_quota_count: int}
     */
    public function facultySummary(Collection $instructors, array $instructorHours, int $teachingWeeks, int $hoursPerWeek): array
    {
        $count = 0;
        $total = 0.0;
        $practicum = 0.0;
        $over = 0;

        foreach ($instructors as $instructor) {
            $hours = $instructorHours[$instructor->id] ?? null;

            if (! $hours || $hours['total'] <= 0) {
                continue;
            }

            $count++;
            $total += $hours['total'];
            $practicum += $hours['by_category']['practicum'] ?? 0;

            $quota = $this->quotaFor($instructor->instructorProfile, $teachingWeeks, $hoursPerWeek);
            if ($quota && $quota > 0 && $hours['total'] > $quota) {
                $over++;
            }
        }

        return [
            'instructor_count' => $count,
            'total_hours' => round($total, 1),
            'practicum_hours' => round($practicum, 1),
            'over_quota_count' => $over,
        ];
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
