<?php

namespace Tests\Feature\Schedule;

use App\Http\Controllers\Instructor\PaController;
use App\Models\AcademicCalendar;
use App\Models\AcademicYear;
use App\Models\CourseOffering;
use App\Models\StudentGroup;
use App\Models\SystemSetting;
use App\Models\Term;

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

    public function test_executive_dashboard_shows_faculty_workload(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();

        AcademicYear::where('id', $offering->academic_year_id)->update([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'status' => 'approved',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '12:30',
        ]);

        $executive = $this->makeUser('executive');
        $this->actingAs($executive)->withSession(['active_role' => 'executive']);

        $response = $this->get(route('approver.dashboard'));
        $response->assertOk();

        $hours = $response->viewData('instructorHours');
        $this->assertSame(3.5, $hours[$instructor->id]['total']);
        $response->assertSee('ภาระงานสอนของอาจารย์'); // widget reuse บนหน้าผู้บริหาร

        // M6 เฟส B — ผู้บริหารเห็นการ์ดสรุปด้วย (ไม่ใช่ตารางดิบอย่างเดียว)
        $response->assertSee('data-testid="workload-summary"', false);
        $summary = $response->viewData('summary');
        $this->assertSame(1, $summary['instructor_count']);
        $this->assertSame(3.5, $summary['total_hours']);
    }

    public function test_workload_splits_hours_by_activity_category(): void
    {
        [$head, $offering, $instructor, $group, $lecture, $room] = $this->makeReadyOffering();
        $practicum = $this->makePracticumActivityType();

        AcademicYear::where('id', $offering->academic_year_id)->update([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        // บรรยาย 1 วัน 09:00–12:00 = 3 ชม.
        $this->makeSchedule($offering, $lecture, $room, [$instructor], [$group], [
            'status' => 'approved',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        // ฝึกปฏิบัติ 2 วัน 08:00–16:00 = 16 ชม.
        $this->makeSchedule($offering, $practicum, $room, [$instructor], [$group], [
            'status' => 'approved',
            'start_date' => '2026-06-02',
            'end_date' => '2026-06-03',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ]);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $hours = $response->viewData('instructorHours');

        $this->assertSame(19.0, $hours[$instructor->id]['total']);            // 3 + 16
        $this->assertSame(3.0, $hours[$instructor->id]['by_category']['lecture']);
        $this->assertSame(16.0, $hours[$instructor->id]['by_category']['practicum']);
        $response->assertSee('ฝึกปฏิบัติ'); // widget แสดงชั่วโมงฝึกปฏิบัติแยก
    }

    public function test_workload_bar_shows_usage_percent_vs_criteria(): void
    {
        // เกณฑ์เล็ก ๆ ให้คำนวณ % ชัด: base = 1 สัปดาห์ × 2 ชม. = 2 ชม.
        SystemSetting::set('teaching_load_weeks', 1);
        SystemSetting::set('teaching_quota_hours_per_week', 2);

        [$head, $offering, $instructor, $group, $lecture, $room] = $this->makeReadyOffering();
        AcademicYear::where('id', $offering->academic_year_id)->update([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        // teaching_pct = 100 → เกณฑ์ = 2 ชม.
        $instructor->instructorProfile()->update([
            'teaching_pct' => 100,
            'employment_type' => 'พนักงานมหาวิทยาลัย',
        ]);

        // กิจกรรม 3.5 ชม. > เกณฑ์ 2 → ใช้ไป 175% (เกินเกณฑ์)
        $this->makeSchedule($offering, $lecture, $room, [$instructor], [$group], [
            'status' => 'approved',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '12:30',
        ]);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $response = $this->get(route('admin.dashboard'))->assertOk();

        $response->assertSee('wl-usage-fill'); // แท่งกราฟ render
        $response->assertSee('175');           // % การใช้เทียบเกณฑ์ (ใน row data)
    }

    public function test_admin_can_view_workload_report_page(): void
    {
        [$head, $offering, $instructor, $group, $lecture, $room] = $this->makeReadyOffering();
        AcademicYear::where('id', $offering->academic_year_id)->update([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $this->makeSchedule($offering, $lecture, $room, [$instructor], [$group], [
            'status' => 'approved',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '12:30',
        ]);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $response = $this->get(route('admin.reports.workload'))
            ->assertOk()
            ->assertSee('รายงานภาระงานสอน')
            ->assertSee('นำออก Excel')
            ->assertSee('data-testid="workload-summary"', false)
            ->assertViewHas('instructorHours');

        // สรุปภาพรวมทั้งคณะ
        $summary = $response->viewData('summary');
        $this->assertSame(1, $summary['instructor_count']);
        $this->assertSame(3.5, $summary['total_hours']);
        $this->assertSame(0.0, $summary['practicum_hours']);

        // แยกตามระดับหลักสูตร (curriculum helper = ป.ตรี)
        $response->assertSee('data-testid="workload-by-level"', false);
        $byLevel = $response->viewData('byLevel');
        $this->assertSame(3.5, $byLevel['bachelor']);
        $this->assertSame(0.0, $byLevel['master']);
    }

    public function test_workload_report_exports_csv_with_bom(): void
    {
        [$head, $offering, $instructor, $group, $lecture, $room] = $this->makeReadyOffering();
        AcademicYear::where('id', $offering->academic_year_id)->update([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $this->makeSchedule($offering, $lecture, $room, [$instructor], [$group], [
            'status' => 'approved',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '12:30',
        ]);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $response = $this->get(route('admin.reports.workload.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);   // UTF-8 BOM (Excel ไทย)
        $this->assertStringContainsString('ชั่วโมงตามช่วงที่เลือก', $content); // header
        $this->assertStringContainsString('3.5', $content);          // ชั่วโมงจริงของอาจารย์
    }

    public function test_workload_report_filters_by_academic_year_and_term_and_preserves_export_filters(): void
    {
        [$head, $currentOffering, $instructor, $currentGroup, $lecture, $room] = $this->makeReadyOffering();
        $currentYear = $currentOffering->academicYear;
        $currentYear->update([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $currentCalendar = AcademicCalendar::create([
            'academic_year_id' => $currentYear->id,
            'name' => 'ทุกหลักสูตร',
        ]);
        $currentTerm = Term::create([
            'academic_calendar_id' => $currentCalendar->id,
            'sequence' => 1,
            'name' => 'ภาคเรียนที่ 1',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);
        $this->makeSchedule($currentOffering, $lecture, $room, [$instructor], [$currentGroup], [
            'term_id' => $currentTerm->id,
            'status' => 'approved',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '11:30',
        ]);

        $historicalYear = AcademicYear::create([
            'name' => '2568',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_active' => false,
            'phase' => 'published',
        ]);
        $historicalCalendar = AcademicCalendar::create([
            'academic_year_id' => $historicalYear->id,
            'name' => 'ทุกหลักสูตร',
        ]);
        $historicalTermOne = Term::create([
            'academic_calendar_id' => $historicalCalendar->id,
            'sequence' => 1,
            'name' => 'ภาคเรียนที่ 1',
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);
        $historicalTermTwo = Term::create([
            'academic_calendar_id' => $historicalCalendar->id,
            'sequence' => 2,
            'name' => 'ภาคเรียนที่ 2',
            'start_date' => '2025-07-01',
            'end_date' => '2025-12-31',
        ]);
        $historicalOffering = CourseOffering::create([
            'course_id' => $currentOffering->course_id,
            'academic_year_id' => $historicalYear->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'published',
            'total_student_count' => 30,
        ]);
        $historicalOffering->instructorPool()->attach($instructor->id, ['role_in_course' => 'instructor']);
        $historicalGroup = StudentGroup::create([
            'course_offering_id' => $historicalOffering->id,
            'group_code' => 'H1',
            'student_count' => 15,
        ]);

        $this->makeSchedule($historicalOffering, $lecture, $room, [$instructor], [$historicalGroup], [
            'term_id' => $historicalTermOne->id,
            'status' => 'approved',
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-01',
            'start_time' => '08:00',
            'end_time' => '13:30',
        ]);
        $this->makeSchedule($historicalOffering, $lecture, $room, [$instructor], [$historicalGroup], [
            'term_id' => $historicalTermTwo->id,
            'status' => 'approved',
            'start_date' => '2025-08-01',
            'end_date' => '2025-08-01',
            'start_time' => '08:00',
            'end_time' => '12:30',
        ]);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);
        $filters = [
            'academic_year_id' => $historicalYear->id,
            'term_sequence' => 1,
        ];

        $response = $this->get(route('admin.reports.workload', $filters))
            ->assertOk()
            ->assertSee('data-testid="workload-report-filters"', false)
            ->assertSee('ภาคเรียนที่ 1')
            ->assertViewHas('termSequence', 1);

        $hours = $response->viewData('instructorHours');
        $this->assertSame(5.5, $hours[$instructor->id]['total']);
        $this->assertSame('2568', $response->viewData('year')->name);

        $export = $this->get(route('admin.reports.workload.export', $filters))->assertOk();
        $content = $export->getContent();
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $rows = array_values(array_filter(array_map(
            'str_getcsv',
            preg_split('/\r\n|\r|\n/', trim($csv))
        )));
        $this->assertSame('5.5', $rows[1][4]);
        $this->assertStringContainsString('workload-report-2568-term-1.csv', $export->headers->get('Content-Disposition'));
    }

    public function test_publishing_offering_finalizes_schedules_and_instructor_sees_workload(): void
    {
        [$head, $offering, $instructor, $group, $lecture, $room] = $this->makeReadyOffering();
        AcademicYear::where('id', $offering->academic_year_id)->update([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        // หัวหน้าวิชาสร้างกิจกรรม (draft) — ยังไม่อนุมัติ
        $schedule = $this->makeSchedule($offering, $lecture, $room, [$instructor], [$group], [
            'status' => 'draft',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-01',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        // ก่อนอนุมัติ: อาจารย์เห็นภาระงาน = 0 (schedule ยัง draft)
        $before = app(PaController::class)->workloadDataFor($instructor->fresh());
        $this->assertSame(0.0, $before['approvedTeachingHours']);

        // ผู้บริหารอนุมัติ (pending → published)
        $offering->update(['approval_status' => 'pending']);
        $exec = $this->makeUser('executive');
        $this->actingAs($exec)->withSession(['active_role' => 'executive']);
        $this->post(route('approver.offerings.approve', $offering))->assertRedirect();

        // เผยแพร่แล้ว → schedule กลายเป็น approved
        $this->assertSame('published', $offering->fresh()->approval_status);
        $this->assertSame('approved', $schedule->fresh()->status);

        // อาจารย์เปิดดูภาระงานตัวเอง → เห็นจริง 3 ชม.
        $after = app(PaController::class)->workloadDataFor($instructor->fresh());
        $this->assertSame(3.0, $after['approvedTeachingHours']);
    }

    public function test_workload_report_shows_empty_state_when_nothing_approved(): void
    {
        // มีอาจารย์ แต่ไม่มีตารางที่ approved → ต้องขึ้น zero-state อธิบาย ไม่ใช่กำแพง 0
        [$head, $offering, $instructor] = $this->makeReadyOffering();

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $this->get(route('admin.reports.workload'))
            ->assertOk()
            ->assertSee('data-testid="workload-empty-state"', false)
            ->assertSee('ยังไม่มีภาระงานให้แสดง')
            ->assertDontSee('data-testid="workload-summary"', false); // ไม่โชว์การ์ดสรุป 0
    }

    public function test_staff_and_executive_can_view_workload_report_but_cannot_use_admin_export(): void
    {
        $staff = $this->makeUser('staff');
        $this->actingAs($staff)->withSession(['active_role' => 'staff']);
        $this->get(route('staff.reports.workload'))
            ->assertOk()
            ->assertSee('data-testid="sidebar-staff-workload-report"', false)
            ->assertDontSee('data-testid="workload-export-csv"', false)
            ->assertDontSee('และนำออกเป็นไฟล์ Excel ได้');
        $this->get(route('admin.reports.workload.export'))->assertForbidden();

        $executive = $this->makeUser('executive');
        $this->actingAs($executive)->withSession(['active_role' => 'executive']);
        $this->get(route('approver.reports.workload'))
            ->assertOk()
            ->assertSee('data-testid="sidebar-executive-workload-report"', false)
            ->assertDontSee('data-testid="workload-export-csv"', false)
            ->assertDontSee('และนำออกเป็นไฟล์ Excel ได้');
        $this->get(route('admin.reports.workload.export'))->assertForbidden();
    }

    public function test_unsupported_roles_cannot_access_workload_reports(): void
    {
        $instructor = $this->makeUser('instructor');
        $this->actingAs($instructor)->withSession(['active_role' => 'instructor']);

        $this->get(route('admin.reports.workload'))->assertForbidden();
        $this->get(route('staff.reports.workload'))->assertForbidden();
        $this->get(route('approver.reports.workload'))->assertForbidden();
    }
}
