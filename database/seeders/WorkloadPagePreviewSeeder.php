<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorPaAllocation;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\PaRound;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudentGroup;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkloadPagePreviewSeeder extends Seeder
{
    private const TAG = '[workload-page-preview]';

    public function run(): void
    {
        DB::transaction(function (): void {
            $year = $this->activeAcademicYear();
            $department = $this->department();
            $curriculum = $this->curriculum();
            $instructor = $this->instructor($department);
            $assistant = $this->assistantInstructor($department);
            $roleIds = $this->courseRoleIds();
            $activities = $this->activityTypes();
            $rooms = $this->rooms();
            $round = $this->paRound($year);

            $this->seedPa($instructor, $round);
            $this->clearPreviewSchedules($year, $instructor);

            $courses = $this->courses($curriculum, $department, $instructor, $assistant, $roleIds);
            $offerings = $this->offerings($courses, $year, $instructor, $assistant, $roleIds);
            $groups = $this->studentGroups($offerings);

            $this->schedules($offerings, $groups, $activities, $rooms, $instructor, $assistant);

            $this->command?->info('WorkloadPagePreviewSeeder: seeded preview workload data for username instructor_01 / password password');
        });
    }

    private function activeAcademicYear(): AcademicYear
    {
        $year = AcademicYear::query()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();

        if ($year) {
            return $year;
        }

        return AcademicYear::query()->updateOrCreate(
            ['name' => '2569'],
            [
                'start_date' => '2026-06-01',
                'end_date' => '2027-03-15',
                'is_active' => true,
                'phase' => 'published',
            ]
        );
    }

    private function department(): Department
    {
        return Department::query()->firstOrCreate([
            'name' => 'ภาควิชาการพยาบาลรากฐาน',
        ]);
    }

    private function curriculum(): Curriculum
    {
        return Curriculum::query()->where('is_active', true)->orderByDesc('effective_year')->first()
            ?? Curriculum::query()->updateOrCreate(
                ['name' => 'หลักสูตรพยาบาลศาสตรบัณฑิต (Preview ภาระงาน)'],
                [
                    'effective_year' => 2569,
                    'education_level' => 'bachelor',
                    'duration_years' => 4,
                    'uses_year_level' => true,
                    'total_credits_required' => 132,
                    'counts_service_only' => false,
                    'is_active' => true,
                ]
            );
    }

    private function instructor(Department $department): User
    {
        $user = User::query()->updateOrCreate(
            ['username' => 'instructor_01'],
            [
                'prefix' => 'นางสาว',
                'employee_id' => '40102',
                'name' => 'นภัส ใจดี',
                'email' => 'teacher@tpss.demo',
                'password' => 'password',
                'is_active' => true,
            ]
        );

        UserRole::query()->updateOrCreate(
            ['user_id' => $user->id, 'role' => 'instructor'],
            ['is_primary' => true]
        );

        InstructorProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'title' => 'อาจารย์',
                'department_id' => $department->id,
                'employment_type' => 'พนักงานมหาวิทยาลัย',
                'academic_degree' => 'ปริญญาโท',
                'hired_at' => '2019-06-01',
                'teaching_pct' => 55,
                'research_pct' => 22,
                'service_pct' => 13,
                'culture_pct' => 5,
                'other_pct' => 5,
                'teaching_quota' => $this->teachingQuota(55),
            ]
        );

        return $user->fresh(['roles', 'instructorProfile']);
    }

    private function assistantInstructor(Department $department): User
    {
        $user = User::query()->updateOrCreate(
            ['username' => 'workload_preview_assistant'],
            [
                'prefix' => 'นาย',
                'employee_id' => '40991',
                'name' => 'ปกรณ์ ร่วมสอน',
                'email' => 'workload.assistant@tpss.demo',
                'password' => 'password',
                'is_active' => true,
            ]
        );

        UserRole::query()->updateOrCreate(
            ['user_id' => $user->id, 'role' => 'instructor'],
            ['is_primary' => false]
        );

        InstructorProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'title' => 'อาจารย์',
                'department_id' => $department->id,
                'employment_type' => 'พนักงานมหาวิทยาลัย',
                'academic_degree' => 'ปริญญาโท',
                'hired_at' => '2020-06-01',
                'teaching_pct' => 50,
                'research_pct' => 25,
                'service_pct' => 10,
                'culture_pct' => 10,
                'other_pct' => 5,
                'teaching_quota' => $this->teachingQuota(50),
            ]
        );

        return $user;
    }

    private function courseRoleIds(): array
    {
        $head = CourseRole::query()->firstOrCreate(['name_th' => 'หัวหน้าวิชา']);
        $instructor = CourseRole::query()->firstOrCreate(['name_th' => 'อาจารย์ผู้สอน']);
        $mentor = CourseRole::query()->firstOrCreate(['name_th' => 'อาจารย์พี่เลี้ยง']);

        return [
            'head' => $head->id,
            'instructor' => $instructor->id,
            'mentor' => $mentor->id,
        ];
    }

    private function activityTypes(): array
    {
        return [
            'lecture' => ActivityType::query()->updateOrCreate(
                ['name' => 'บรรยาย'],
                ['color_code' => '#2563eb', 'category' => 'lecture', 'counts_toward_workload' => true]
            ),
            'lab' => ActivityType::query()->updateOrCreate(
                ['name' => 'ปฏิบัติการ'],
                ['color_code' => '#059669', 'category' => 'practicum', 'counts_toward_workload' => true]
            ),
            'practicum' => ActivityType::query()->updateOrCreate(
                ['name' => 'ฝึกปฏิบัติ'],
                ['color_code' => '#7c3aed', 'category' => 'practicum', 'counts_toward_workload' => true]
            ),
            'meeting' => ActivityType::query()->updateOrCreate(
                ['name' => 'ประชุมเตรียมการสอน (ไม่นับภาระงาน)'],
                ['color_code' => '#64748b', 'category' => 'other', 'counts_toward_workload' => false]
            ),
        ];
    }

    private function rooms(): array
    {
        $classroomType = LocationType::query()->firstOrCreate(
            ['name' => 'ห้องเรียนทั่วไป'],
            ['is_shared' => false]
        );
        $labType = LocationType::query()->firstOrCreate(
            ['name' => 'ห้องปฏิบัติการ'],
            ['is_shared' => false]
        );
        $wardType = LocationType::query()->firstOrCreate(
            ['name' => 'หอผู้ป่วย'],
            ['is_shared' => true]
        );

        return [
            'lecture' => Room::query()->updateOrCreate(
                ['room_code' => 'R-WL-401'],
                ['room_name' => 'ห้องบรรยาย 401', 'building' => 'อาคารเรียนรวม', 'capacity' => 80, 'location_type_id' => $classroomType->id, 'status' => 'active']
            ),
            'lab' => Room::query()->updateOrCreate(
                ['room_code' => 'LAB-WL-1'],
                ['room_name' => 'ห้องปฏิบัติการพยาบาล 1', 'building' => 'ศูนย์ฝึกทักษะ', 'capacity' => 40, 'location_type_id' => $labType->id, 'status' => 'active']
            ),
            'ward' => Room::query()->updateOrCreate(
                ['room_code' => 'WARD-WL-A'],
                ['room_name' => 'หอผู้ป่วยจำลอง A', 'building' => 'ศูนย์ฝึกปฏิบัติ', 'capacity' => 24, 'location_type_id' => $wardType->id, 'status' => 'active']
            ),
        ];
    }

    private function paRound(AcademicYear $year): PaRound
    {
        return PaRound::query()->updateOrCreate(
            ['academic_year_id' => $year->id, 'code' => PaRound::CODE_ANNUAL],
            [
                'name' => "PA {$year->name} รอบปี",
                'start_date' => $year->start_date,
                'end_date' => $year->end_date,
            ]
        );
    }

    private function seedPa(User $instructor, PaRound $round): void
    {
        InstructorPaAllocation::query()->updateOrCreate(
            ['user_id' => $instructor->id, 'pa_round_id' => $round->id],
            [
                'teaching_pct' => 55,
                'research_pct' => 22,
                'service_pct' => 13,
                'culture_pct' => 5,
                'other_pct' => 5,
                'teaching_quota' => $this->teachingQuota(55),
                'submitted_at' => now(),
            ]
        );
    }

    private function courses(Curriculum $curriculum, Department $department, User $instructor, User $assistant, array $roleIds): array
    {
        $rows = [
            [
                'code' => 'WLNS 221',
                'name' => 'การพยาบาลเด็ก 2',
                'type' => 'theory',
                'lecture' => 2,
                'lab' => 0,
                'year' => 2,
                'head' => 'instructor',
                'instructor_role' => 'head',
                'assistant_role' => 'instructor',
            ],
            [
                'code' => 'WLNS 314',
                'name' => 'สุขภาพจิตและการพยาบาลจิตเวช 2',
                'type' => 'theory_practicum',
                'lecture' => 2,
                'lab' => 2,
                'year' => 3,
                'head' => 'assistant',
                'instructor_role' => 'instructor',
                'assistant_role' => 'head',
            ],
            [
                'code' => 'WLNS 371',
                'name' => 'ปฏิบัติการพยาบาลผู้ใหญ่และผู้สูงอายุ',
                'type' => 'practicum',
                'lecture' => 0,
                'lab' => 6,
                'year' => 3,
                'head' => 'assistant',
                'instructor_role' => 'mentor',
                'assistant_role' => 'head',
            ],
        ];

        $courses = [];
        foreach ($rows as $row) {
            $course = Course::query()->updateOrCreate(
                ['course_code' => $row['code'], 'curriculum_id' => $curriculum->id],
                [
                    'department_id' => $department->id,
                    'head_instructor_id' => $row['head'] === 'assistant'
                        ? $assistant->id
                        : $instructor->id,
                    'name_th' => $row['name'],
                    'name_en' => null,
                    'course_type' => $row['type'],
                    'default_year_level' => $row['year'],
                    'is_required' => true,
                    'credits' => 3,
                    'lecture_hours' => $row['lecture'],
                    'lab_hours' => $row['lab'],
                    'self_study_hours' => 3,
                    'color_code' => '#002454',
                    'status' => 'active',
                ]
            );

            $course->instructors()->syncWithoutDetaching([
                $instructor->id => ['course_role_id' => $roleIds[$row['instructor_role']]],
                $assistant->id => ['course_role_id' => $roleIds[$row['assistant_role']]],
            ]);

            $courses[$row['code']] = $course;
        }

        return $courses;
    }

    private function offerings(array $courses, AcademicYear $year, User $instructor, User $assistant, array $roleIds): array
    {
        $offerings = [];

        foreach ($courses as $code => $course) {
            $courseInstructorRoles = $course->instructors()
                ->get()
                ->mapWithKeys(fn (User $user) => [
                    (int) $user->id => (int) $user->pivot->course_role_id,
                ]);
            $offering = CourseOffering::query()->updateOrCreate(
                ['course_id' => $course->id, 'academic_year_id' => $year->id],
                [
                    'coordinator_id' => $course->head_instructor_id,
                    'approval_status' => 'published',
                    'planned_lecture_hours' => $course->lecture_hours,
                    'planned_lab_hours' => $course->lab_hours,
                    'teaching_weeks' => 12,
                    'instructor_pool_note' => self::TAG,
                ]
            );

            $offering->instructorPool()->syncWithoutDetaching([
                $instructor->id => [
                    'role_in_course' => $course->head_instructor_id === $instructor->id
                        ? 'coordinator'
                        : 'instructor',
                    'course_role_id' => $courseInstructorRoles->get($instructor->id),
                    'schedule_permission' => $course->head_instructor_id === $instructor->id
                        ? 'schedule'
                        : 'view',
                ],
                $assistant->id => [
                    'role_in_course' => $course->head_instructor_id === $assistant->id
                        ? 'coordinator'
                        : 'instructor',
                    'course_role_id' => $courseInstructorRoles->get($assistant->id),
                    'schedule_permission' => $course->head_instructor_id === $assistant->id
                        ? 'schedule'
                        : 'view',
                ],
            ]);

            $offerings[$code] = $offering;
        }

        return $offerings;
    }

    private function studentGroups(array $offerings): array
    {
        $groups = [];

        foreach ($offerings as $code => $offering) {
            $groups[$code] = [
                'A' => StudentGroup::query()->updateOrCreate(
                    ['course_offering_id' => $offering->id, 'group_code' => 'A'],
                    ['student_count' => 42, 'color_code' => '#2563eb']
                ),
                'B' => StudentGroup::query()->updateOrCreate(
                    ['course_offering_id' => $offering->id, 'group_code' => 'B'],
                    ['student_count' => 38, 'color_code' => '#059669']
                ),
            ];
        }

        return $groups;
    }

    private function schedules(
        array $offerings,
        array $groups,
        array $activities,
        array $rooms,
        User $instructor,
        User $assistant
    ): void {
        $rows = [
            ['WLNS 221', '2026-06-08', '09:00', '12:00', 'พัฒนาการเด็กและการประเมินสุขภาพ', 'lecture', 'lecture', ['A', 'B'], [$instructor->id => true]],
            ['WLNS 221', '2026-06-15', '09:00', '12:00', 'การวางแผนการพยาบาลเด็ก', 'lecture', 'lecture', ['A', 'B'], [$instructor->id => true]],
            ['WLNS 314', '2026-06-17', '13:00', '16:00', 'แนวคิดสุขภาพจิตและการสื่อสารเพื่อการบำบัด', 'lecture', 'lecture', ['A'], [$instructor->id => true]],
            ['WLNS 314', '2026-06-24', '13:00', '16:00', 'ปฏิบัติการประเมินภาวะสุขภาพจิต', 'lab', 'lab', ['A'], [$instructor->id => true, $assistant->id => false]],
            ['WLNS 371', '2026-07-02', '07:00', '15:00', 'ฝึกปฏิบัติการพยาบาลผู้ใหญ่: การดูแลผู้ป่วยเรื้อรัง', 'practicum', 'ward', ['A'], [$instructor->id => true, $assistant->id => false]],
            ['WLNS 371', '2026-07-09', '07:00', '15:00', 'ฝึกปฏิบัติการพยาบาลผู้สูงอายุ: การประเมินและวางแผนดูแล', 'practicum', 'ward', ['B'], [$instructor->id => false, $assistant->id => true]],
            ['WLNS 221', '2026-07-13', '10:00', '12:00', 'ประชุมเตรียมการสอนก่อนขึ้นหน่วย', 'meeting', 'lecture', ['A'], [$instructor->id => true], 'approved'],
            ['WLNS 314', '2026-07-20', '09:00', '12:00', 'คาบร่างที่ยังไม่เผยแพร่', 'lecture', 'lecture', ['B'], [$instructor->id => true], 'draft'],
        ];

        foreach ($rows as $row) {
            [$code, $date, $start, $end, $topic, $activityKey, $roomKey, $groupCodes, $instructors] = $row;
            $status = $row[9] ?? 'approved';
            $offering = $offerings[$code];
            $activity = $activities[$activityKey];
            $room = $rooms[$roomKey];

            $schedule = Schedule::query()->create([
                'course_offering_id' => $offering->id,
                'activity_type_id' => $activity->id,
                'room_id' => $room->id,
                'teaching_date' => $date,
                'start_date' => $date,
                'end_date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'topic' => $topic,
                'capacity_required' => 40,
                'status' => $status,
                'remark' => self::TAG,
            ]);

            $schedule->instructors()->sync(
                collect($instructors)->mapWithKeys(fn (bool $isLead, int $userId) => [
                    $userId => ['is_lead' => $isLead],
                ])->all()
            );

            $schedule->studentGroups()->sync(
                collect($groupCodes)->map(fn (string $groupCode) => $groups[$code][$groupCode]->id)->all()
            );
        }
    }

    private function clearPreviewSchedules(AcademicYear $year, User $instructor): void
    {
        Schedule::query()
            ->where('remark', self::TAG)
            ->whereHas('courseOffering', fn ($query) => $query->where('academic_year_id', $year->id))
            ->whereHas('instructors', fn ($query) => $query->where('users.id', $instructor->id))
            ->get()
            ->each(function (Schedule $schedule): void {
                $schedule->instructors()->detach();
                $schedule->studentGroups()->detach();
                $schedule->delete();
            });
    }

    private function teachingQuota(int $teachingPct): int
    {
        $weeks = (int) SystemSetting::get('teaching_load_weeks', 39);
        $hoursPerWeek = (int) SystemSetting::get('teaching_quota_hours_per_week', 35);

        return (int) round(($weeks * $hoursPerWeek * $teachingPct) / 100);
    }
}
