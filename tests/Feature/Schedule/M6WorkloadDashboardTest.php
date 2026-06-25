<?php

namespace Tests\Feature\Schedule;

use App\Models\AcademicYear;

/**
 * M6 — กัน regression: widget admin ต้องโชว์ชั่วโมงสอน "จริง" จาก schedule
 * (เดิม blade ฮาร์ดโค้ด '0.0' ทุกคน — ถ้า wiring หลุดจะกลับไป 0 เงียบ ๆ)
 */
class M6WorkloadDashboardTest extends ScheduleTestCase
{
    public function test_admin_dashboard_shows_real_instructor_teaching_hours(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        // ขยายปีให้คร่อมปัจจุบัน เพื่อให้กิจกรรมในอดีตนับเป็น "สะสมถึงวันนี้" ได้
        AcademicYear::where('id', $offering->academic_year_id)->update([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        // กิจกรรมอนุมัติ วันเดียวในอดีต 09:00–12:30 = 3.5 ชม. (accrued = total เพราะผ่านไปแล้ว)
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'status' => 'approved',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '12:30',
        ]);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $response = $this->get(route('admin.dashboard'));
        $response->assertOk();

        // 1) controller คำนวณเลขจริง (ไม่ใช่ 0)
        $hours = $response->viewData('instructorHours');
        $this->assertSame(3.5, $hours[$instructor->id]['total']);
        $this->assertSame(3.5, $hours[$instructor->id]['accrued']);

        // 2) blade render เลขจริงออกหน้า (กันบั๊กฮาร์ดโค้ด 0.0 กลับมา)
        $response->assertSee('3.5');
    }

    public function test_future_schedule_accrues_zero_but_counts_in_annual_total(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        AcademicYear::where('id', $offering->academic_year_id)->update([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        // กิจกรรมอนาคต (หลังวันนี้) → สะสม = 0 แต่ยอดทั้งปี = 2.0
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'status' => 'approved',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-01',
            'start_time' => '13:00',
            'end_time' => '15:00',
        ]);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $hours = $this->get(route('admin.dashboard'))->assertOk()->viewData('instructorHours');

        $this->assertSame(2.0, $hours[$instructor->id]['total']);
        $this->assertSame(0.0, $hours[$instructor->id]['accrued']);
    }
}
