<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Schedule;
use App\Models\ScheduleConflictRun;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ScheduleConflictChecker;
use Database\Seeders\ScheduleFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DatabaseSeederBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seed_starts_without_selected_academic_year_or_course_offerings(): void
    {
        $this->seed();

        $this->assertGreaterThan(0, AcademicYear::count());
        $this->assertSame(0, AcademicYear::where('is_active', true)->count());
        $this->assertTrue(AcademicYear::all()->every(
            fn (AcademicYear $year) => Carbon::parse($year->start_date)->isWeekday()
                && Carbon::parse($year->end_date)->isWeekday()
        ));

        $this->assertGreaterThan(0, Course::count());
        $this->assertSame(0, Course::where('status', 'active')->count());

        $this->assertSame(0, CourseOffering::count());
    }

    public function test_schedule_flow_seed_creates_unique_generated_instructors_and_demo_conflicts(): void
    {
        config(['conflicts.async_reads' => false]);
        Cache::flush();

        $this->seed();
        $this->seed(ScheduleFlowSeeder::class);

        $generatedInstructors = User::where('username', 'like', 'schedule_offering_%')->get();

        $this->assertGreaterThan(0, $generatedInstructors->count());
        $this->assertSame(
            $generatedInstructors->count(),
            $generatedInstructors->pluck('name')->unique()->count()
        );

        $checker = app(ScheduleConflictChecker::class);
        $conflictingSchedules = Schedule::with(['instructors', 'studentGroups'])
            ->get()
            ->filter(function (Schedule $schedule) use ($checker): bool {
                $conflicts = $checker->check(
                    [
                        'start_date' => $schedule->start_date->toDateString(),
                        'end_date' => $schedule->end_date->toDateString(),
                        'start_time' => (string) $schedule->start_time,
                        'end_time' => (string) $schedule->end_time,
                        'room_id' => $schedule->room_id,
                    ],
                    $schedule->instructors->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    $schedule->studentGroups->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    $schedule->id
                );

                return collect($conflicts)->contains(fn (array $conflict) => $conflict['type'] === 'room_overlap');
            });

        $this->assertGreaterThan(0, $conflictingSchedules->count());

        $stackDemoSchedules = Schedule::where('topic', 'like', 'เวียนฐาน%')
            ->orWhere('topic', 'like', 'อภิปรายหลังเวียนฐาน%')
            ->orWhere('topic', 'like', 'สรุปผลการฝึกปฏิบัติรายกลุ่ม%')
            ->orderBy('start_time')
            ->get();

        $this->assertCount(6, $stackDemoSchedules);
        $this->assertSame(1, $stackDemoSchedules->pluck('course_offering_id')->unique()->count());
        // ตารางฝึกสมจริง: กระจายหลายวัน (ไม่ใช่กองวันเดียว) + ยังคงมีจุดจองชนตั้งใจ 1 จุด (E/F)
        $this->assertGreaterThan(1, $stackDemoSchedules->pluck('start_date')->map->toDateString()->unique()->count());
        $this->assertSame(6, $stackDemoSchedules->pluck('sub_group_label')->filter()->unique()->count());
        $this->assertGreaterThanOrEqual(2, $this->largestOverlappingStackSize($stackDemoSchedules));

        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();
        $coordinatorIds = CourseOffering::where('academic_year_id', $activeYear->id)
            ->pluck('coordinator_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $this->assertGreaterThan(0, $coordinatorIds->count());
        $this->assertTrue($coordinatorIds->every(fn (int $id) => Cache::has("sidebar.badges.course_head.{$id}")));
        $badgeCounts = $coordinatorIds->mapWithKeys(fn (int $id) => [
            $id => (int) Cache::get("sidebar.badges.course_head.{$id}", 0),
        ]);

        $this->assertGreaterThan(0, $badgeCounts->max());

        $visibleCoordinatorId = (int) $badgeCounts->sortDesc()->keys()->first();
        UserRole::query()->firstOrCreate(
            ['user_id' => $visibleCoordinatorId, 'role' => 'course_head'],
            ['is_primary' => true]
        );

        $this->actingAs(User::findOrFail($visibleCoordinatorId))
            ->withSession(['active_role' => 'course_head'])
            ->get(route('maker.dashboard'))
            ->assertOk()
            ->assertSee((string) $badgeCounts->get($visibleCoordinatorId), false);
    }

    public function test_schedule_flow_seed_warms_async_conflict_read_model(): void
    {
        config(['conflicts.async_reads' => true]);
        Cache::flush();
        Queue::fake();

        $this->seed();
        $this->seed(ScheduleFlowSeeder::class);

        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();
        $latestRun = ScheduleConflictRun::query()
            ->where('academic_year_id', $activeYear->id)
            ->orderByDesc('generation')
            ->firstOrFail();

        $this->assertSame('ready', $latestRun->status);
        $this->assertGreaterThan(0, $latestRun->result_count);
    }

    private function largestOverlappingStackSize($schedules): int
    {
        $stacks = [];

        foreach ($schedules as $schedule) {
            $inserted = false;

            foreach ($stacks as &$stack) {
                foreach ($stack as $existing) {
                    if ($schedule->start_time < $existing->end_time && $existing->start_time < $schedule->end_time) {
                        $stack[] = $schedule;
                        $inserted = true;
                        break 2;
                    }
                }
            }

            if (! $inserted) {
                $stacks[] = [$schedule];
            }
        }

        return collect($stacks)->map(fn (array $stack) => count($stack))->max() ?? 0;
    }
}
