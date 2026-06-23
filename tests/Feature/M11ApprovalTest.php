<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CourseOffering;
use App\Models\CourseOfferingApproval;
use App\Models\Notification;
use App\Models\Schedule;
use App\Models\User;
use Tests\Feature\Schedule\ScheduleTestCase;

/**
 * M11 — Approval Workflow (Task17 ส่ง+ล็อก / Task18 อนุมัติ-ตีกลับ)
 * reuse helper สร้างข้อมูลจาก ScheduleTestCase
 */
class M11ApprovalTest extends ScheduleTestCase
{
    private function actingAsExecutive(User $user): void
    {
        $this->actingAs($user);
        $this->withSession(['active_role' => 'executive']);
    }

    // ---------- Task17: submit + lock ----------

    public function test_course_head_can_submit_offering_for_approval(): void
    {
        [$head, $offering, $instructor, $group, $activity, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activity, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);
        $this->post(route('maker.course_offerings.submit', $offering))
            ->assertRedirect(route('maker.course_offerings.show', $offering));

        $this->assertDatabaseHas('course_offerings', [
            'id' => $offering->id, 'approval_status' => 'pending',
        ]);
        $this->assertDatabaseHas('course_offering_approvals', [
            'course_offering_id' => $offering->id, 'action' => 'submit',
            'actor_user_id' => $head->id,
            'from_status' => 'draft', 'to_status' => 'pending',
        ]);
        // audit: ใคร (user_id) ทำอะไร (action) — หัวหน้าวิชาส่งขออนุมัติ
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'การอนุมัติ.ส่ง', 'category' => 'การอนุมัติ',
            'user_id' => $head->id, 'table_affected' => 'course_offerings', 'record_id' => $offering->id,
        ]);
    }

    public function test_submit_notifies_executives(): void
    {
        $executive = $this->makeUser('executive');
        [$head, $offering, $instructor, $group, $activity, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activity, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);
        $this->post(route('maker.course_offerings.submit', $offering));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $executive->id,
            'course_offering_id' => $offering->id,
            'type' => 'approval_update',
        ]);
    }

    public function test_submit_blocked_when_no_schedule(): void
    {
        [$head, $offering] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);
        $this->post(route('maker.course_offerings.submit', $offering))->assertRedirect();

        $this->assertDatabaseHas('course_offerings', [
            'id' => $offering->id, 'approval_status' => 'draft',
        ]);
    }

    public function test_schedule_locked_after_submit(): void
    {
        [$head, $offering, $instructor, $group, $activity, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activity, $room, [$instructor], [$group]);
        $offering->update(['approval_status' => 'pending']);

        $this->actingAsCourseHead($head);
        $before = Schedule::where('course_offering_id', $offering->id)->count();
        $this->post(
            route('maker.course_offerings.schedules.store', $offering),
            $this->schedulePayload($instructor, $group, $activity, $room, ['start_date' => '2026-08-10', 'end_date' => '2026-08-14'])
        );

        $this->assertSame($before, Schedule::where('course_offering_id', $offering->id)->count());
    }

    // ---------- Task18: approve / reject ----------

    public function test_executive_can_approve_offering(): void
    {
        $executive = $this->makeUser('executive');
        [$head, $offering] = $this->makeReadyOffering();
        $offering->update(['approval_status' => 'pending']);

        $this->actingAsExecutive($executive);
        $this->post(route('approver.offerings.approve', $offering))->assertRedirect();

        $this->assertDatabaseHas('course_offerings', [
            'id' => $offering->id, 'approval_status' => 'published',
        ]);
        $this->assertDatabaseHas('course_offering_approvals', [
            'course_offering_id' => $offering->id, 'action' => 'approve',
            'actor_user_id' => $executive->id, 'to_status' => 'published',
        ]);
        // audit: ใคร (ผู้บริหาร) อนุมัติรายวิชาไหน
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'การอนุมัติ.อนุมัติ', 'category' => 'การอนุมัติ',
            'user_id' => $executive->id, 'table_affected' => 'course_offerings', 'record_id' => $offering->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $head->id, 'course_offering_id' => $offering->id, 'type' => 'approval_update',
        ]);
    }

    public function test_executive_reject_requires_reason(): void
    {
        $executive = $this->makeUser('executive');
        [$head, $offering] = $this->makeReadyOffering();
        $offering->update(['approval_status' => 'pending']);

        $this->actingAsExecutive($executive);
        $this->post(route('approver.offerings.reject', $offering), [])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertDatabaseHas('course_offerings', [
            'id' => $offering->id, 'approval_status' => 'pending',
        ]);
    }

    public function test_executive_can_reject_with_reason(): void
    {
        $executive = $this->makeUser('executive');
        [$head, $offering] = $this->makeReadyOffering();
        $offering->update(['approval_status' => 'pending']);

        $this->actingAsExecutive($executive);
        $this->post(route('approver.offerings.reject', $offering), ['rejection_reason' => 'ภาระงานเกินเกณฑ์'])
            ->assertRedirect();

        $this->assertDatabaseHas('course_offerings', [
            'id' => $offering->id, 'approval_status' => 'rejected', 'rejection_reason' => 'ภาระงานเกินเกณฑ์',
        ]);
        $this->assertDatabaseHas('course_offering_approvals', [
            'course_offering_id' => $offering->id, 'action' => 'reject',
            'actor_user_id' => $executive->id, 'comment' => 'ภาระงานเกินเกณฑ์',
        ]);
        // audit: ใคร (ผู้บริหาร) ตีกลับรายวิชาไหน
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'การอนุมัติ.ปฏิเสธ', 'category' => 'การอนุมัติ',
            'user_id' => $executive->id, 'table_affected' => 'course_offerings', 'record_id' => $offering->id,
        ]);
    }

    public function test_rejected_offering_can_be_resubmitted(): void
    {
        [$head, $offering, $instructor, $group, $activity, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activity, $room, [$instructor], [$group]);
        $offering->update(['approval_status' => 'rejected', 'rejection_reason' => 'แก้เวลา']);

        $this->actingAsCourseHead($head);
        $this->post(route('maker.course_offerings.submit', $offering))->assertRedirect();

        $this->assertDatabaseHas('course_offerings', [
            'id' => $offering->id, 'approval_status' => 'pending', 'rejection_reason' => null,
        ]);
    }

    public function test_non_executive_cannot_approve(): void
    {
        [$head, $offering] = $this->makeReadyOffering();
        $offering->update(['approval_status' => 'pending']);

        $this->actingAsCourseHead($head);
        $this->post(route('approver.offerings.approve', $offering))->assertForbidden();

        $this->assertDatabaseHas('course_offerings', [
            'id' => $offering->id, 'approval_status' => 'pending',
        ]);
    }

    // ---------- RBAC matrix ----------

    public function test_executive_cannot_submit_offering(): void
    {
        $exec = $this->makeUser('executive');
        [$head, $offering, $instructor, $group, $activity, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activity, $room, [$instructor], [$group]);

        $this->actingAsExecutive($exec);
        $this->post(route('maker.course_offerings.submit', $offering))->assertForbidden();
        $this->assertDatabaseHas('course_offerings', ['id' => $offering->id, 'approval_status' => 'draft']);
    }

    public function test_course_head_cannot_reject(): void
    {
        [$head, $offering] = $this->makeReadyOffering();
        $offering->update(['approval_status' => 'pending']);

        $this->actingAsCourseHead($head);
        $this->post(route('approver.offerings.reject', $offering), ['rejection_reason' => 'x'])->assertForbidden();
        $this->assertDatabaseHas('course_offerings', ['id' => $offering->id, 'approval_status' => 'pending']);
    }

    public function test_instructor_cannot_access_approver_pages(): void
    {
        $instructor = $this->makeUser('instructor');
        $this->actingAs($instructor);
        $this->withSession(['active_role' => 'instructor']);

        $this->get(route('approver.dashboard'))->assertForbidden();
        $this->get(route('approver.offerings.rejected'))->assertForbidden();
    }

    public function test_coordinator_cannot_submit_other_coordinators_offering(): void
    {
        [$head, $offering, $instructor, $group, $activity, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activity, $room, [$instructor], [$group]);
        $otherHead = $this->makeUser('course_head');

        $this->actingAsCourseHead($otherHead);
        $this->post(route('maker.course_offerings.submit', $offering))->assertForbidden();
        $this->assertDatabaseHas('course_offerings', ['id' => $offering->id, 'approval_status' => 'draft']);
    }

    // ---------- render: หน้าจอ render ได้ (blade compile) ----------

    public function test_submit_button_shows_on_draft_offering(): void
    {
        [$head, $offering] = $this->makeReadyOffering();

        $this->actingAsCourseHead($head);
        $this->get(route('maker.course_offerings.show', $offering))
            ->assertOk()
            ->assertSee('offering-submit-button', false);
    }

    public function test_executive_queue_lists_pending_offering(): void
    {
        $executive = $this->makeUser('executive');
        [$head, $offering] = $this->makeReadyOffering();
        $offering->update(['approval_status' => 'pending']);

        $this->actingAsExecutive($executive);
        $this->get(route('approver.dashboard'))
            ->assertOk()
            ->assertSee($offering->course->course_code);

        $this->get(route('approver.offerings.show', $offering))
            ->assertOk()
            ->assertSee('approver-approve-button', false);
    }

    public function test_executive_rejected_queue_lists_rejected_offering(): void
    {
        $executive = $this->makeUser('executive');
        [$head, $offering] = $this->makeReadyOffering();
        $offering->update(['approval_status' => 'rejected', 'rejection_reason' => 'ภาระงานเกินเกณฑ์']);

        $this->actingAsExecutive($executive);
        $this->get(route('approver.offerings.rejected'))
            ->assertOk()
            ->assertSee($offering->course->course_code)
            ->assertSee('ภาระงานเกินเกณฑ์');
    }

    // ---------- M11 — กระดิ่งแจ้งเตือน ----------

    public function test_opening_notification_marks_it_read_and_redirects(): void
    {
        [$head, $offering] = $this->makeReadyOffering();
        $notification = Notification::create([
            'user_id' => $head->id,
            'course_offering_id' => $offering->id,
            'type' => 'approval_update',
            'message' => 'รายวิชาได้รับการอนุมัติแล้ว',
            'is_read' => false,
            'created_at' => now(),
        ]);

        $this->actingAsCourseHead($head);
        $this->get(route('notifications.open', $notification))
            ->assertRedirect(route('maker.course_offerings.show', $offering));

        $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'is_read' => true]);
    }

    public function test_user_cannot_open_others_notification(): void
    {
        [$head, $offering] = $this->makeReadyOffering();
        $other = $this->makeUser('course_head');
        $notification = Notification::create([
            'user_id' => $head->id,
            'course_offering_id' => $offering->id,
            'type' => 'approval_update',
            'message' => 'ส่วนตัว',
            'is_read' => false,
            'created_at' => now(),
        ]);

        $this->actingAsCourseHead($other);
        $this->get(route('notifications.open', $notification))->assertForbidden();
        $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'is_read' => false]);
    }

    public function test_mark_all_notifications_read(): void
    {
        $executive = $this->makeUser('executive');
        foreach (range(1, 3) as $i) {
            Notification::create([
                'user_id' => $executive->id,
                'type' => 'approval_update',
                'message' => "แจ้งเตือน {$i}",
                'is_read' => false,
                'created_at' => now(),
            ]);
        }

        $this->actingAsExecutive($executive);
        $this->post(route('notifications.read_all'))->assertRedirect();

        $this->assertSame(0, Notification::where('user_id', $executive->id)->where('is_read', false)->count());
    }

    // ---------- Fix 1: กฎล็อกรวมไว้จุดเดียว (CourseOffering::isLocked) ----------

    public function test_is_locked_only_for_pending_and_published(): void
    {
        $this->assertFalse((new CourseOffering(['approval_status' => 'draft']))->isLocked());
        $this->assertTrue((new CourseOffering(['approval_status' => 'pending']))->isLocked());
        $this->assertTrue((new CourseOffering(['approval_status' => 'published']))->isLocked());
        $this->assertFalse((new CourseOffering(['approval_status' => 'rejected']))->isLocked());
    }

    // ---------- Fix 3: ตัวเลือกปีของ flow อนุมัติรวมไว้จุดเดียว ----------

    public function test_current_for_approval_prefers_active_year_over_scheduling(): void
    {
        $scheduling = AcademicYear::create([
            'name' => '2570', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31',
            'is_active' => false, 'phase' => 'scheduling',
        ]);
        $active = AcademicYear::create([
            'name' => '2571', 'start_date' => '2027-08-01', 'end_date' => '2027-12-31',
            'is_active' => true, 'phase' => 'preparation',
        ]);

        // ปี active มาก่อนปีที่อยู่ในช่วงจัดตาราง (เหมือนกันทั้ง dashboard + หน้าตีกลับ)
        $this->assertSame($active->id, AcademicYear::currentForApproval()?->id);
    }

    public function test_current_for_approval_falls_back_to_scheduling_year(): void
    {
        $scheduling = AcademicYear::create([
            'name' => '2570', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31',
            'is_active' => false, 'phase' => 'scheduling',
        ]);

        // ไม่มีปี active → ใช้ปีที่อยู่ในช่วงจัดตาราง
        $this->assertSame($scheduling->id, AcademicYear::currentForApproval()?->id);
    }

    // ---------- Fix 2: กดซ้ำไม่เด้ง log/แจ้งเตือนซ้ำ (idempotency contract) ----------

    public function test_double_submit_does_not_duplicate_approval_or_notifications(): void
    {
        $executive = $this->makeUser('executive');
        [$head, $offering, $instructor, $group, $activity, $room] = $this->makeReadyOffering();
        $this->makeSchedule($offering, $activity, $room, [$instructor], [$group]);

        $this->actingAsCourseHead($head);
        $this->post(route('maker.course_offerings.submit', $offering));
        $this->post(route('maker.course_offerings.submit', $offering)); // กดส่งซ้ำ

        $this->assertSame(1, CourseOfferingApproval::where('course_offering_id', $offering->id)
            ->where('action', 'submit')->count());
        $this->assertSame(1, Notification::where('user_id', $executive->id)
            ->where('course_offering_id', $offering->id)->count());
    }

    public function test_double_approve_does_not_duplicate_approval_or_notifications(): void
    {
        $executive = $this->makeUser('executive');
        [$head, $offering] = $this->makeReadyOffering();
        $offering->update(['approval_status' => 'pending']);

        $this->actingAsExecutive($executive);
        $this->post(route('approver.offerings.approve', $offering));
        $this->post(route('approver.offerings.approve', $offering)); // กดอนุมัติซ้ำ

        $this->assertSame(1, CourseOfferingApproval::where('course_offering_id', $offering->id)
            ->where('action', 'approve')->count());
        $this->assertSame(1, Notification::where('user_id', $head->id)
            ->where('course_offering_id', $offering->id)->count());
    }
}
