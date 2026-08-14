<?php

namespace Tests\Feature\Schedule;

use App\Models\CourseOffering;
use App\Models\StudentGroup;
use App\Models\Term;
use Illuminate\Testing\TestResponse;
use ZipArchive;

class M9ScheduleReportTest extends ScheduleTestCase
{
    public function test_admin_can_filter_published_schedule_report_by_year_term_course_group_instructor_and_room(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $year = $offering->academicYear;
        $term = $this->makeTerm($year->fallbackCalendar()->id, 1);
        $offering->update(['approval_status' => 'published']);

        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'term_id' => $term->id,
            'status' => 'approved',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'topic' => 'การพยาบาลผู้ใหญ่',
            'remark' => 'เตรียมกรณีศึกษา',
        ]);

        $secondHead = $this->makeUser('course_head');
        $secondCourse = $this->makeCourse($secondHead);
        $secondOffering = CourseOffering::create([
            'course_id' => $secondCourse->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $secondHead->id,
            'approval_status' => 'published',
        ]);
        $secondGroup = StudentGroup::create([
            'course_offering_id' => $secondOffering->id,
            'group_code' => 'B1',
            'student_count' => 18,
        ]);
        $this->makeSchedule($secondOffering, $activityType, $room, [$instructor], [$secondGroup], [
            'term_id' => $term->id,
            'status' => 'approved',
            'start_date' => '2026-08-04',
            'end_date' => '2026-08-04',
            'topic' => 'การพยาบาลเด็ก',
        ]);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);

        $response = $this->get(route('admin.reports.schedules', [
            'academic_year_id' => $year->id,
            'term_sequence' => 1,
            'curriculum_id' => $offering->course->curriculum_id,
            'course_offering_id' => $offering->id,
            'student_group_id' => $group->id,
            'instructor_id' => $instructor->id,
            'room_id' => $room->id,
        ]));

        $response->assertOk()
            ->assertSee('รายงานตารางสอน')
            ->assertSee('data-async-filter-scope="schedule-report"', false)
            ->assertSee('การพยาบาลผู้ใหญ่')
            ->assertSee('เตรียมกรณีศึกษา')
            ->assertSee($group->group_code)
            ->assertSee('name="instructor_id"', false)
            ->assertSee('name="room_id"', false)
            ->assertSee($instructor->formatted_name)
            ->assertSee($room->room_code)
            ->assertDontSee('การพยาบาลเด็ก')
            ->assertSee('นำออก PDF')
            ->assertSee('นำออก Excel');
    }

    public function test_schedule_report_exports_real_xlsx_and_pdf_files(): void
    {
        [$head, $offering, $instructor, $group, $activityType, $room] = $this->makeReadyOffering();
        $year = $offering->academicYear;
        $term = $this->makeTerm($year->fallbackCalendar()->id, 1);
        $offering->update(['approval_status' => 'published']);
        $this->makeSchedule($offering, $activityType, $room, [$instructor], [$group], [
            'term_id' => $term->id,
            'status' => 'approved',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'topic' => 'ทดสอบรายงาน M9',
        ]);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->withSession(['active_role' => 'admin']);
        $filters = ['academic_year_id' => $year->id, 'term_sequence' => 1];

        $xlsx = $this->get(route('admin.reports.schedules.excel', $filters))->assertOk();
        $xlsx->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', $xlsx->headers->get('content-disposition'));
        $workbookXml = $this->readXlsxResponse($xlsx);
        $this->assertStringContainsString('ทดสอบรายงาน M9', $workbookXml);

        $pdf = $this->get(route('admin.reports.schedules.pdf', $filters))->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('.pdf', $pdf->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_staff_and_executive_can_use_schedule_reports_but_instructor_cannot(): void
    {
        $staff = $this->makeUser('staff');
        $this->actingAs($staff)->withSession(['active_role' => 'staff']);
        $this->get(route('staff.reports.schedules'))->assertOk();

        $executive = $this->makeUser('executive');
        $this->actingAs($executive)->withSession(['active_role' => 'executive']);
        $this->get(route('approver.reports.schedules'))->assertOk();

        $instructor = $this->makeUser('instructor');
        $this->actingAs($instructor)->withSession(['active_role' => 'instructor']);
        $this->get('/admin/reports/schedules')->assertForbidden();
        $this->get('/staff/reports/schedules')->assertForbidden();
        $this->get('/approver/reports/schedules')->assertForbidden();
    }

    private function makeTerm(int $calendarId, int $sequence): Term
    {
        return Term::create([
            'academic_calendar_id' => $calendarId,
            'sequence' => $sequence,
            'name' => "ภาคเรียนที่ {$sequence}",
            'start_date' => '2026-08-01',
            'end_date' => '2026-12-31',
        ]);
    }

    private function readXlsxResponse(TestResponse $response): string
    {
        $path = tempnam(sys_get_temp_dir(), 'm9-report-');
        file_put_contents($path, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $sharedStrings = $zip->getFromName('xl/sharedStrings.xml') ?: '';
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
        $zip->close();
        @unlink($path);

        return $sharedStrings . $sheet;
    }
}
