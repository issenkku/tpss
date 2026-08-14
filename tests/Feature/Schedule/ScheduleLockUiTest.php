<?php

namespace Tests\Feature\Schedule;

/**
 * กัน regression: วิชาที่ส่งอนุมัติ/อนุมัติแล้ว (locked) → หน้าตารางต้อง "ซ่อนปุ่มแก้"
 * ให้ UI ตรงกับ server guard (requireSchedulingPhase) ไม่ใช่โชว์ปุ่มแล้วเด้ง error
 */
class ScheduleLockUiTest extends ScheduleTestCase
{
    public function test_schedule_page_hides_add_controls_when_offering_is_locked(): void
    {
        [$head, $offering] = $this->makeReadyOffering();
        $this->actingAsCourseHead($head);

        // ไปสัปดาห์ที่อยู่ในปีการศึกษา (ปีตั้งต้น ส.ค.–ธ.ค.) เพื่อให้ cell เพิ่มได้โผล่ตอนยังไม่ล็อก
        $url = route('maker.course_offerings.schedules.index', $offering) . '?period=week&date=2026-09-07';

        // draft (scheduling) → ยังแก้ได้ มีปุ่มเพิ่มกิจกรรม
        $this->get($url)
            ->assertOk()
            ->assertSee('data-testid="grid-empty-cell"', false);

        // ส่งอนุมัติแล้ว (pending) → ล็อก ไม่มีปุ่มเพิ่ม
        $offering->update(['approval_status' => 'pending']);
        $this->get($url)
            ->assertOk()
            ->assertDontSee('data-testid="grid-empty-cell"', false);

        // อนุมัติแล้ว (published) → ล็อกเช่นกัน
        $offering->update(['approval_status' => 'published']);
        $this->get($url)
            ->assertOk()
            ->assertDontSee('data-testid="grid-empty-cell"', false);
    }
}
