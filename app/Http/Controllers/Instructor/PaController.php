<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\AdminSettingController;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\InstructorPaAllocation;
use App\Models\InstructorProfile;
use App\Models\PaRound;
use App\Models\Schedule;
use App\Models\SystemSetting;
use App\Services\WorkloadCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class PaController extends Controller
{
    private const FIELDS = [
        'teaching_pct' => 'ด้านการสอน',
        'research_pct' => 'ด้านวิจัย',
        'service_pct' => 'บริการวิชาการ',
        'culture_pct' => 'ศิลปวัฒนธรรม',
        'other_pct' => 'งานอื่นๆ ที่ได้รับมอบหมาย',
    ];

    private const CRITERIA_KEYS = [
        'teaching_pct' => 't',
        'research_pct' => 'r',
        'service_pct' => 's',
        'culture_pct' => 'c',
        'other_pct' => 'o',
    ];

    public function edit()
    {
        return redirect()->route('lecturer.dashboard');
    }

    public function workloadDataFor($user): array
    {
        $user->load(['instructorProfile', 'paAllocations.paRound']);
        $profile = $user->instructorProfile;
        $academicYear = $this->currentAcademicYear();
        $round = ($academicYear && $profile) ? $this->currentRound($academicYear, $profile) : null;
        $allocation = $round
            ? $user->paAllocations->firstWhere('pa_round_id', $round->id)
            : null;

        $paCriteria = $this->paCriteria();
        $criteriaGroup = $profile ? AlertController::paGroup((string) $profile->title, (string) $profile->academic_degree) : null;
        $paRules = $criteriaGroup ? ($paCriteria[$criteriaGroup] ?? []) : [];
        $isGovernment = $profile ? $this->isGovernmentEmployee($profile) : false;
        $periodLabel = $isGovernment ? '6 เดือน' : 'ปี';
        $quotaBase = $profile ? $this->quotaBase($profile) : null;
        $teachingQuota = $allocation?->teaching_quota
            ?? ($profile ? $this->calculateTeachingQuota((int) ($allocation?->teaching_pct ?? $profile->teaching_pct), $profile) : null);
        $approvedTeachingSchedules = ($academicYear && $profile)
            ? $this->approvedTeachingSchedules($user->id, $academicYear->id)
            : collect();
        $approvedTeachingHours = round($approvedTeachingSchedules->sum('workload_hours'), 1);
        // สะสมถึงวันนี้ = ผลรวม accrued_hours (นับรายวันถึงวันนี้ — แตก block ถูกต้อง)
        // เดิมกรองด้วย teaching_date ซึ่ง = null สำหรับแถวที่สร้างหลัง migration block-date → สะสมเป็น 0 เสมอ
        $taughtTeachingHours = round($approvedTeachingSchedules->sum('accrued_hours'), 1);
        $upcomingTeachingHours = round(max(0, $approvedTeachingHours - $taughtTeachingHours), 1);
        $remainingTeachingHours = $teachingQuota !== null
            ? round($teachingQuota - $approvedTeachingHours, 1)
            : null;
        $remainingTeachingHoursToday = $teachingQuota !== null
            ? round($teachingQuota - $taughtTeachingHours, 1)
            : null;
        $workloadCourseSummaries = $this->workloadCourseSummaries($approvedTeachingSchedules);

        return compact(
            'user',
            'profile',
            'academicYear',
            'round',
            'allocation',
            'paRules',
            'criteriaGroup',
            'periodLabel',
            'quotaBase',
            'teachingQuota',
            'approvedTeachingSchedules',
            'workloadCourseSummaries',
            'approvedTeachingHours',
            'taughtTeachingHours',
            'upcomingTeachingHours',
            'remainingTeachingHours',
            'remainingTeachingHoursToday'
        );
    }

    private function approvedTeachingSchedules(int $userId, int $academicYearId): Collection
    {
        $calculator = new WorkloadCalculator;
        $today = CarbonImmutable::today();

        return Schedule::query()
            ->with([
                'activityType',
                'room',
                'studentGroups',
                'courseOffering.course',
                'courseOffering.academicYear',
                'instructors' => fn ($query) => $query->where('users.id', $userId),
            ])
            ->where('status', 'approved')
            ->whereHas('activityType', fn ($query) => $query->where('counts_toward_workload', true))
            ->whereHas('courseOffering', fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->whereHas('instructors', fn ($query) => $query->where('users.id', $userId))
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get()
            ->map(function (Schedule $schedule) use ($userId, $calculator, $today) {
                $schedule->setAttribute('workload_hours', $calculator->hoursForInstructor($schedule));
                $schedule->setAttribute('accrued_hours', $calculator->accruedHoursFor($schedule, $today));
                $assigned = $schedule->instructors->firstWhere('id', $userId);
                $schedule->setAttribute('workload_role', $assigned?->pivot?->is_lead ? 'ผู้สอนหลัก' : 'ผู้ร่วมสอน');

                return $schedule;
            });
    }

    private function workloadCourseSummaries(Collection $schedules): Collection
    {
        return $schedules
            ->groupBy(fn (Schedule $schedule) => $schedule->course_offering_id)
            ->map(function (Collection $courseSchedules) {
                $first = $courseSchedules->first();
                $course = $first?->courseOffering?->course;
                $activityNames = $courseSchedules
                    ->pluck('activityType.name')
                    ->filter()
                    ->unique()
                    ->values();
                $groupCodes = $courseSchedules
                    ->flatMap(fn (Schedule $schedule) => $schedule->studentGroups->pluck('group_code'))
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'course_code' => $course?->course_code ?? '-',
                    'course_name' => $course?->name_th ?? $course?->name_en ?? '-',
                    'activity_names' => $activityNames,
                    'group_codes' => $groupCodes,
                    'schedule_count' => $courseSchedules->count(),
                    'hours' => round($courseSchedules->sum('workload_hours'), 1),
                ];
            })
            ->sortBy('course_code')
            ->values();
    }

    public function update(Request $request)
    {
        $user = Auth::user()->load('instructorProfile');
        $profile = $user->instructorProfile;

        if (! $profile) {
            return back()->withErrors([
                'pa_profile' => 'ยังไม่พบข้อมูลโปรไฟล์อาจารย์ กรุณาให้ผู้ดูแลระบบบันทึกข้อมูลพื้นฐานก่อน',
            ])->withInput();
        }

        $academicYear = $this->currentAcademicYear();
        if (! $academicYear) {
            return back()->withErrors([
                'pa_round' => 'ยังไม่มีปีการศึกษาที่ใช้สำหรับกรอก PA',
            ])->withInput();
        }

        $validated = $request->validate([
            'teaching_pct' => 'required|integer|min:0|max:100',
            'research_pct' => 'required|integer|min:0|max:100',
            'service_pct' => 'required|integer|min:0|max:100',
            'culture_pct' => 'required|integer|min:0|max:100',
            'other_pct' => 'required|integer|min:0|max:100',
        ]);

        $total = array_sum(array_map('intval', $validated));
        if ($total !== 100) {
            return back()->withErrors([
                'teaching_pct' => "สัดส่วนภาระงานรวมต้องเท่ากับ 100% (ปัจจุบัน {$total}%)",
            ])->withInput();
        }

        $criteria = $this->paCriteria();
        $group = AlertController::paGroup((string) $profile->title, (string) $profile->academic_degree);
        $rules = $criteria[$group] ?? [];
        $rangeErrors = $this->rangeErrors($validated, $rules);
        if ($rangeErrors !== []) {
            return back()->withErrors([
                'teaching_pct' => 'สัดส่วน PA อยู่นอกช่วงเกณฑ์: ' . implode(', ', $rangeErrors),
            ])->withInput();
        }

        $round = $this->currentRound($academicYear, $profile);
        $teachingQuota = $this->calculateTeachingQuota((int) $validated['teaching_pct'], $profile);

        DB::transaction(function () use ($user, $profile, $round, $validated, $teachingQuota): void {
            InstructorPaAllocation::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'pa_round_id' => $round->id,
                ],
                [
                    'teaching_pct' => (int) $validated['teaching_pct'],
                    'research_pct' => (int) $validated['research_pct'],
                    'service_pct' => (int) $validated['service_pct'],
                    'culture_pct' => (int) $validated['culture_pct'],
                    'other_pct' => (int) $validated['other_pct'],
                    'teaching_quota' => $teachingQuota,
                    'submitted_at' => now(),
                ]
            );

            $profile->update([
                'teaching_pct' => (int) $validated['teaching_pct'],
                'research_pct' => (int) $validated['research_pct'],
                'service_pct' => (int) $validated['service_pct'],
                'culture_pct' => (int) $validated['culture_pct'],
                'other_pct' => (int) $validated['other_pct'],
                'teaching_quota' => $teachingQuota,
            ]);
        });

        AlertController::flushCache();

        return redirect()->route('lecturer.dashboard')->with('success', 'บันทึกสัดส่วน PA เรียบร้อยแล้ว');
    }

    private function currentAcademicYear(): ?AcademicYear
    {
        return AcademicYear::query()
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first()
            ?? AcademicYear::query()->orderByDesc('start_date')->first();
    }

    private function currentRound(AcademicYear $academicYear, InstructorProfile $profile): PaRound
    {
        $start = CarbonImmutable::parse($academicYear->start_date);
        $end = CarbonImmutable::parse($academicYear->end_date);

        if (! $this->isGovernmentEmployee($profile)) {
            return PaRound::firstOrCreate(
                ['academic_year_id' => $academicYear->id, 'code' => PaRound::CODE_ANNUAL],
                [
                    'name' => "PA {$academicYear->name} รอบปี",
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                ]
            );
        }

        $firstEnd = $start->addMonths(6)->subDay();
        if ($firstEnd->greaterThan($end)) {
            $firstEnd = $end;
        }

        $today = CarbonImmutable::now();
        $isFirstHalf = $today->lessThanOrEqualTo($firstEnd);
        $code = $isFirstHalf ? PaRound::CODE_HALF_1 : PaRound::CODE_HALF_2;
        $roundStart = $isFirstHalf ? $start : $firstEnd->addDay();
        $roundEnd = $isFirstHalf ? $firstEnd : $end;
        $roundName = $isFirstHalf ? 'รอบ 6 เดือนที่ 1' : 'รอบ 6 เดือนที่ 2';

        return PaRound::firstOrCreate(
            ['academic_year_id' => $academicYear->id, 'code' => $code],
            [
                'name' => "PA {$academicYear->name} {$roundName}",
                'start_date' => $roundStart->toDateString(),
                'end_date' => $roundEnd->toDateString(),
            ]
        );
    }

    private function paCriteria(): array
    {
        $criteria = json_decode(SystemSetting::get('pa_criteria_config', '{}'), true);
        $firstGroup = is_array($criteria) && $criteria !== [] ? reset($criteria) : null;
        $firstField = is_array($firstGroup) ? reset($firstGroup) : null;

        if (! is_array($criteria) || ! is_array($firstField)) {
            return AdminSettingController::defaultPaCriteria();
        }

        return $criteria;
    }

    private function rangeErrors(array $values, array $rules): array
    {
        $errors = [];

        foreach (self::FIELDS as $field => $label) {
            $key = self::CRITERIA_KEYS[$field];
            $range = $rules[$key] ?? null;
            if (! is_array($range)) {
                continue;
            }

            $value = (int) $values[$field];
            $min = (int) ($range['min'] ?? 0);
            $max = (int) ($range['max'] ?? 100);

            if ($value < $min || $value > $max) {
                $errors[] = "{$label} {$value}% (เกณฑ์ {$min}-{$max}%)";
            }
        }

        return $errors;
    }

    private function calculateTeachingQuota(int $teachingPct, InstructorProfile $profile): int
    {
        return (int) round(($this->quotaBase($profile) * $teachingPct) / 100);
    }

    private function quotaBase(InstructorProfile $profile): float
    {
        $weeks = (int) SystemSetting::get('teaching_load_weeks', 39);
        $hoursPerWeek = (int) SystemSetting::get('teaching_quota_hours_per_week', 35);
        $base = $weeks * $hoursPerWeek;

        if ($this->isGovernmentEmployee($profile)) {
            $base /= 2;
        }

        return $base;
    }

    private function isGovernmentEmployee(InstructorProfile $profile): bool
    {
        return trim((string) $profile->employment_type) === 'ข้าราชการ';
    }
}
