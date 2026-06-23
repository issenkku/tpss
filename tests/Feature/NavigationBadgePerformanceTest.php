<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AlertController;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\User;
use App\Services\NavigationBadgeService;
use App\Services\ScheduleConflictIndex;
use App\Services\ScheduleConflictReadRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class NavigationBadgePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_does_not_run_heavy_badge_queries_directly(): void
    {
        $sidebar = file_get_contents(resource_path('views/components/sidebar.blade.php'));

        $this->assertStringNotContainsString('Schedule::query', $sidebar);
        $this->assertStringNotContainsString('ScheduleConflictChecker', $sidebar);
        $this->assertStringNotContainsString('AlertController::getSummary', $sidebar);
    }

    public function test_sidebar_has_no_placeholder_hash_links(): void
    {
        $sidebar = file_get_contents(resource_path('views/components/sidebar.blade.php'));

        $this->assertStringNotContainsString('href="#"', $sidebar);
        $this->assertStringNotContainsString("route('staff.dashboard')", $sidebar);
        $this->assertStringNotContainsString("route('maker.dashboard')", $sidebar);
        // M11: approver.dashboard เป็นหน้าจริงแล้ว (คิวอนุมัติ) — link ได้ ไม่ใช่ placeholder
        $this->assertStringContainsString("route('approver.dashboard')", $sidebar);
        $this->assertStringContainsString('nv-disabled', $sidebar);
        $this->assertStringContainsString('nv-label', $sidebar);
        $this->assertStringContainsString('กำลังพัฒนา', $sidebar);
    }

    public function test_alert_flush_also_clears_admin_sidebar_badge_cache(): void
    {
        Cache::put('sidebar.badges.admin', ['critical' => 99, 'warnings' => 0], 300);

        AlertController::flushCache();

        $this->assertFalse(Cache::has('sidebar.badges.admin'));
    }

    public function test_course_head_badge_cache_miss_does_not_compute_conflicts(): void
    {
        Cache::forget('sidebar.badges.course_head.123');
        $index = Mockery::mock(ScheduleConflictIndex::class);
        $index->shouldNotReceive('countForCoordinator');
        $repository = Mockery::mock(ScheduleConflictReadRepository::class);

        $service = new NavigationBadgeService($index, $repository);

        $this->assertSame(0, $service->courseHeadConflictCount(123));
    }

    public function test_course_head_badge_uses_cached_value_without_compute(): void
    {
        Cache::put('sidebar.badges.course_head.123', 7, 300);
        $index = Mockery::mock(ScheduleConflictIndex::class);
        $index->shouldNotReceive('countForCoordinator');
        $repository = Mockery::mock(ScheduleConflictReadRepository::class);

        $service = new NavigationBadgeService($index, $repository);

        $this->assertSame(7, $service->courseHeadConflictCount(123));
    }

    public function test_async_course_head_badge_reads_repository_without_conflict_index(): void
    {
        config(['conflicts.async_reads' => true]);
        Cache::flush();
        [$year, $head] = $this->makeCourseHeadOffering();
        $index = Mockery::mock(ScheduleConflictIndex::class);
        $index->shouldNotReceive('countForCoordinator');
        $repository = Mockery::mock(ScheduleConflictReadRepository::class);
        $repository->shouldReceive('getStatusForUser')
            ->once()
            ->with($head->id, $year->id)
            ->andReturn([
                'status' => 'ready',
                'generation' => 1,
                'run_id' => 1,
                'result_count' => 21,
                'updated_at' => now(),
                'has_scope' => true,
            ]);
        // badge นับ distinct schedule ที่ชน (ให้ตรงหน้าแจ้งเตือน) ไม่ใช่ row/edge
        $repository->shouldReceive('getDistinctScheduleCountForUser')
            ->once()
            ->with($head->id, $year->id)
            ->andReturn(21);

        $badge = (new NavigationBadgeService($index, $repository))->courseHeadConflictBadge($head->id);

        $this->assertSame(21, $badge['maker_conflict_count']);
        $this->assertSame('21', $badge['maker_conflict_label']);
    }

    private function makeCourseHeadOffering(): array
    {
        $year = AcademicYear::query()->create([
            'name' => '2578',
            'semester' => 1,
            'start_date' => '2034-08-01',
            'end_date' => '2034-12-31',
            'is_active' => true,
            'phase' => 'scheduling',
        ]);
        $head = User::query()->create([
            'username' => 'badge_head',
            'name' => 'Badge Head',
            'email' => 'badge_head@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
        $course = Course::query()->create([
            'course_code' => 'BADGE101',
            'curriculum_id' => Curriculum::query()->create([
                'name' => 'Badge Curriculum',
                'effective_year' => 2578,
                'is_active' => true,
            ])->id,
            'department_id' => Department::query()->create(['name' => 'Badge Department'])->id,
            'head_instructor_id' => $head->id,
            'name_th' => 'Badge Course',
            'name_en' => 'Badge Course',
            'course_type' => 'theory',
            'default_year_level' => 1,
            'default_semester' => 1,
            'requires_practicum_rotation' => false,
            'credits' => 1,
            'lecture_hours' => 1,
            'lab_hours' => 0,
            'self_study_hours' => 1,
            'status' => 'active',
        ]);

        CourseOffering::query()->create([
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'coordinator_id' => $head->id,
            'approval_status' => 'draft',
            'total_student_count' => 1,
        ]);

        return [$year, $head];
    }
}
