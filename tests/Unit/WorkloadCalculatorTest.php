<?php

namespace Tests\Unit;

use App\Models\ActivityType;
use App\Models\Schedule;
use App\Models\User;
use App\Services\WorkloadCalculator;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Unit ของสูตรนับชั่วโมงภาระงาน M6 — สร้าง Schedule ในหน่วยความจำ (ไม่แตะ DB)
 * เคสตามที่เคาะใน grilling: บรรยายครั้งเดียว / block รายวัน / ไม่นับ workload / team full / accrual
 */
class WorkloadCalculatorTest extends TestCase
{
    private function makeSchedule(array $attributes, ?bool $countsTowardWorkload = true, int $instructorCount = 1): Schedule
    {
        $schedule = (new Schedule)->forceFill($attributes);

        if ($countsTowardWorkload !== null) {
            $schedule->setRelation('activityType', (new ActivityType)->forceFill([
                'counts_toward_workload' => $countsTowardWorkload,
            ]));
        }

        $instructors = new Collection;
        for ($i = 0; $i < $instructorCount; $i++) {
            $instructors->push(new User);
        }
        $schedule->setRelation('instructors', $instructors);

        return $schedule;
    }

    public function test_single_day_lecture_counts_once(): void
    {
        // บรรยายครั้งเดียว 09:00–12:00 (start_date = end_date) → 3 ชม.
        $schedule = $this->makeSchedule([
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-02',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $this->assertSame(3.0, (new WorkloadCalculator)->hoursFor($schedule));
    }

    public function test_block_counts_every_calendar_day_in_range(): void
    {
        // block 08:00–16:00 (8 ชม./วัน) ตั้งแต่ 2–13 มี.ค. = 12 วันปฏิทิน (รวมเสาร์-อาทิตย์ ตามที่ตกลง)
        $schedule = $this->makeSchedule([
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-13',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $this->assertSame(96.0, (new WorkloadCalculator)->hoursFor($schedule)); // 8 × 12
    }

    public function test_activity_not_counted_returns_zero(): void
    {
        // ปฐมนิเทศ/SDL: counts_toward_workload = false → 0 ชม.
        $schedule = $this->makeSchedule([
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-02',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ], countsTowardWorkload: false);

        $this->assertSame(0.0, (new WorkloadCalculator)->hoursFor($schedule));
    }

    public function test_team_teaching_full_mode_gives_each_instructor_full_hours(): void
    {
        // 1 การ์ด 3 อาจารย์ mode=full → แต่ละคนได้เต็ม (ไม่หาร)
        $schedule = $this->makeSchedule([
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-02',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ], instructorCount: 3);

        $this->assertSame(3.0, (new WorkloadCalculator)->hoursForInstructor($schedule));
    }

    public function test_accrual_counts_only_elapsed_days_of_block(): void
    {
        // block 2–13 มี.ค. 8 ชม./วัน · asOf = 6 มี.ค. → นับ 2–6 มี.ค. = 5 วัน = 40 ชม. (< ยอดทั้งก้อน 96)
        $schedule = $this->makeSchedule([
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-13',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $calculator = new WorkloadCalculator;

        $this->assertSame(40.0, $calculator->accruedHoursFor($schedule, '2026-03-06'));
        // ก่อนเริ่ม → 0
        $this->assertSame(0.0, $calculator->accruedHoursFor($schedule, '2026-03-01'));
        // หลังจบ → เท่ายอดทั้งก้อน
        $this->assertSame(96.0, $calculator->accruedHoursFor($schedule, '2026-03-20'));
    }
}
