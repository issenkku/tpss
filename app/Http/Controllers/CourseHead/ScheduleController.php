<?php

namespace App\Http\Controllers\CourseHead;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseOffering;
use App\Models\Schedule;
use App\Models\ScheduleConflictRun;
use App\Models\ScheduleTemplate;
use App\Services\AcademicCalendar;
use App\Services\AuditLogger;
use App\Services\CoordinatorAlertService;
use App\Services\NavigationBadgeService;
use App\Services\ReferenceDataCache;
use App\Services\ScheduleConflictChecker;
use App\Services\ScheduleConflictInvalidationService;
use App\Services\ScheduleConflictIndex;
use App\Services\ScheduleConflictReadRepository;
use App\Services\ScheduleSeriesGenerator;
use App\Support\ThaiDate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Throwable;

class ScheduleController extends Controller
{
    public function workspace(Request $request): View|RedirectResponse
    {
        if ($targetOfferingId = $this->coordinatorScheduleOfferingRedirectTarget($request)) {
            return redirect()->route('maker.course_offerings.schedules.index', array_filter([
                'courseOffering' => $targetOfferingId,
                'week_start' => $request->query('week_start'),
                'date' => $request->query('date'),
                'period' => $request->query('period'),
                'include_weekends' => $request->query('include_weekends'),
                'modal' => $request->query('modal'),
            ]));
        }

        $offerings = $this->coordinatorScheduleOfferings(includeSchedulingData: false);

        return view('shared.schedules.index', $this->schedulePageData(
            request: $request,
            courseOffering: null,
            isWorkspace: true,
            availableOfferings: $offerings,
        ));
    }

    /**
     * V2: หน้าแจ้งเตือนรวมของหัวหน้าวิชา (warning — ไม่ใช่การชน)
     * แทนหน้า conflicts เดิม · การชนข้ามวิชาเป็น card แยก (เพิ่มด่านถัดไป)
     */
    public function alerts(Request $request): View
    {
        $userId = (int) Auth::id();
        $availableYears = $this->coordinatorAcademicYears($userId);
        $defaultAcademicYear = $this->defaultConflictAcademicYear($availableYears);
        $selectedAcademicYear = $this->selectedConflictAcademicYear($request, $availableYears, $defaultAcademicYear);
        $selectedAcademicYearId = $selectedAcademicYear?->id ? (int) $selectedAcademicYear->id : null;

        $warnings = collect();

        if ($selectedAcademicYearId) {
            // warning (ไม่ใช่การชน) — ใช้ service เดียวกับ sidebar badge เพื่อให้เลขรวมตรงกัน
            $warnings = app(CoordinatorAlertService::class)->warningItems($userId, $selectedAcademicYearId);

            // 🔴 การชนข้ามวิชา — ดึงจาก conflict index ทำเป็น item type 'conflict' (สีแดง ขึ้นบนสุด)
            $conflictResult = app(ScheduleConflictIndex::class)->conflictsForCoordinator($userId, $selectedAcademicYearId);
            $conflictMap = $conflictResult['conflictMap'];
            $typeWord = ['instructor_overlap' => 'ผู้สอน', 'room_overlap' => 'ห้อง', 'group_overlap' => 'กลุ่ม'];
            $conflictItems = $conflictResult['schedules']->toBase()->map(function (Schedule $schedule) use ($conflictMap, $typeWord) {
                $entries = $conflictMap->get($schedule->id, collect());
                $parts = $entries->groupBy('type')->map(function ($byType, $type) use ($typeWord) {
                    $res = $byType->pluck('resource_label')->filter()->unique()->implode(', ');
                    return ($typeWord[$type] ?? 'ชน') . ($res !== '' ? " ({$res})" : '');
                })->values()->implode(' · ');

                return ['type' => 'conflict', 'schedule' => $schedule, 'label' => $this->scheduleAlertLabel($schedule), 'message' => 'ชนกับรายการอื่น — ' . $parts];
            });

            $warnings = $conflictItems->merge($warnings); // การชนขึ้นก่อน warning
        }

        return view('shared.alerts.index', [
            'availableYears' => $availableYears,
            'selectedAcademicYear' => $selectedAcademicYear,
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'warnings' => $warnings,
            'totalWarningCount' => $warnings->count(),
            'warningTypeCounts' => $warnings->groupBy('type')->map->count(),
            'emptyStateKey' => $this->conflictEmptyStateKey($availableYears),
        ]);
    }

    private function scheduleAlertLabel(Schedule $schedule): string
    {
        $date = $schedule->start_date ? ThaiDate::date($schedule->start_date) : '-';
        $start = substr((string) $schedule->start_time, 0, 5);
        $end = substr((string) $schedule->end_time, 0, 5);
        $activity = $schedule->activityType?->name ?? 'กิจกรรม';

        return trim("{$activity} · {$date} {$start}-{$end}");
    }

    public function conflictDetails(Request $request, Schedule $schedule): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ]);
        $academicYearId = (int) $validated['academic_year_id'];

        $schedule->loadMissing('courseOffering:id,course_id,academic_year_id,coordinator_id');

        abort_unless(
            $schedule->courseOffering && (int) $schedule->courseOffering->academic_year_id === $academicYearId,
            404
        );

        $role = session('active_role');
        abort_unless(in_array($role, ['admin', 'course_head'], true), 403);

        $repository = app(ScheduleConflictReadRepository::class);
        $userId = (int) Auth::id();

        abort_unless(
            $repository->canReadScheduleDetails($schedule, $userId, $academicYearId, (string) $role),
            403
        );

        $conflicts = $repository->getDetailsForSchedule($schedule, $userId, $academicYearId, (string) $role);
        $typeCounts = $conflicts
            ->groupBy('type')
            ->map(fn (Collection $items) => $items->count())
            ->all();

        return response()->json([
            'html' => view('course_head.schedule_conflicts._conflict_sets', [
                'conflicts' => $conflicts,
                'conflictTypeLabels' => $this->conflictTypeLabels(),
            ])->render(),
            'total' => $conflicts->count(),
            'type_counts' => $typeCounts,
        ]);
    }

    private function conflictGroupsFromSummaries(Collection $summaries): Collection
    {
        return $summaries
            ->groupBy(fn (array $summary) => (int) $summary['schedule']->course_offering_id)
            ->map(function (Collection $offeringSummaries) {
                $firstSummary = $offeringSummaries->first();
                /** @var Schedule $firstSchedule */
                $firstSchedule = $firstSummary['schedule'];

                return [
                    'offering' => $firstSchedule->courseOffering,
                    'schedules' => $offeringSummaries->values(),
                    'conflict_count' => $offeringSummaries->sum(fn (array $summary) => (int) $summary['conflict_count']),
                ];
            })
            ->values();
    }

    private function conflictTypeLabels(): array
    {
        return [
            'instructor_overlap' => 'ผู้สอนชน',
            'room_overlap' => 'ห้อง/สถานที่ชน',
            'group_overlap' => 'กลุ่มนักศึกษาชน',
        ];
    }

    private function conflictGroupsFromStoredResults(Collection $storedResults): array
    {
        $conflictMap = collect();
        $schedules = collect();

        foreach ($storedResults as $result) {
            /** @var Schedule|null $schedule */
            $schedule = $result->getRelation('sourceSchedule');

            if (! $schedule) {
                continue;
            }

            $schedules->put($schedule->id, $schedule);
            $conflicts = $conflictMap->get($schedule->id, collect());
            $conflicts->push([
                'type' => $result->conflict_type,
                'message' => $result->message,
                'schedule_id' => (int) $result->conflicting_schedule_id,
            ]);
            $conflictMap->put($schedule->id, $conflicts);
        }

        $conflictGroups = $schedules
            ->values()
            ->groupBy('course_offering_id')
            ->map(function (Collection $offeringSchedules) use ($conflictMap) {
                /** @var Schedule $firstSchedule */
                $firstSchedule = $offeringSchedules->first();

                return [
                    'offering' => $firstSchedule->courseOffering,
                    'schedules' => $offeringSchedules->values(),
                    'conflict_count' => $offeringSchedules->sum(
                        fn (Schedule $schedule) => $conflictMap->get($schedule->id, collect())->count()
                    ),
                ];
            })
            ->values();

        return [$conflictGroups, $conflictMap];
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    /**
     * Empty-state key สำหรับหน้าแจ้งเตือนการชน
     * 'ready' → 'no_conflicts' (มี offering แต่ไม่พบการชน)
     */
    private function conflictEmptyStateKey(Collection $availableYears): string
    {
        $key = \App\Support\CoordinatorEmptyState::forCoordinator((int) Auth::id());

        return $key === \App\Support\CoordinatorEmptyState::READY ? 'no_conflicts' : $key;
    }

    private function coordinatorAcademicYears(int $userId): Collection
    {
        return AcademicYear::query()
            ->select(['id', 'name', 'start_date', 'end_date', 'is_active', 'phase'])
            ->whereIn('id', CourseOffering::query()
                ->select('academic_year_id')
                ->withActiveCourse()
                ->schedulableBy($userId))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, AcademicYear>  $availableYears
     */
    private function selectedConflictAcademicYear(
        Request $request,
        Collection $availableYears,
        ?AcademicYear $defaultAcademicYear
    ): ?AcademicYear
    {
        if ($availableYears->isEmpty()) {
            return null;
        }

        $requestedYearId = (int) $request->query('academic_year_id', 0);

        if ($requestedYearId > 0) {
            $requestedYear = $availableYears->firstWhere('id', $requestedYearId);

            if ($requestedYear) {
                return $requestedYear;
            }
        }

        return $defaultAcademicYear;
    }

    /**
     * @param  Collection<int, AcademicYear>  $availableYears
     */
    private function defaultConflictAcademicYear(Collection $availableYears): ?AcademicYear
    {
        return $availableYears->firstWhere('phase', 'scheduling')
            ?: $availableYears->firstWhere('is_active', true)
            ?: $availableYears->first();
    }

    public function index(Request $request, CourseOffering $courseOffering): View
    {
        $this->authorizeCourseHeadOffering($courseOffering);

        return view('shared.schedules.index', $this->schedulePageData(
            request: $request,
            courseOffering: $courseOffering,
            isWorkspace: false,
            availableOfferings: $this->coordinatorScheduleOfferings(includeSchedulingData: false),
        ));
    }

    public function weekFragment(Request $request, CourseOffering $courseOffering): JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);

        $weekStart = $this->validScheduleDate($request, 'week_start')
            ?->startOfWeek(CarbonInterface::MONDAY);

        abort_unless($weekStart, 422);

        $weekEnd = $weekStart->addDays(6);
        $selectedInstructorId = $request->integer('instructor_id') ?: null;
        $selectedTermId = $request->integer('term_id') ?: null;
        $includeWeekends = $request->boolean('include_weekends');

        $cacheKey = $this->weekFragmentCacheKey(
            $request,
            $courseOffering,
            $weekStart,
            $weekEnd,
            $selectedTermId,
            $selectedInstructorId,
            $includeWeekends,
        );

        $buildPayload = function () use (
            $courseOffering,
            $weekStart,
            $weekEnd,
            $selectedInstructorId,
            $selectedTermId,
            $includeWeekends
        ): array {
            return $this->renderWeekFragmentPayload(
                $courseOffering,
                $weekStart,
                $weekEnd,
                $selectedTermId,
                $selectedInstructorId,
                $includeWeekends,
            );
        };

        $payload = $this->rememberWeekFragmentPayload($cacheKey, $buildPayload);

        return response()
            ->json($payload)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function createGlobal(Request $request): View|RedirectResponse
    {
        $offerings = $this->coordinatorScheduleOfferings()
            ->filter(fn (CourseOffering $offering) => $offering->academicYear?->phase === 'scheduling')
            ->values();

        if ($offerings->isEmpty()) {
            return redirect()
                ->route('maker.schedules.index')
                ->withErrors(['schedule' => 'ยังไม่มีรายวิชาที่ต้องจัดตาราง']);
        }

        $selectedOfferingId = (int) old('course_offering_id', $request->query('course_offering_id'));
        $targetOffering = $selectedOfferingId ? $offerings->firstWhere('id', $selectedOfferingId) : null;
        $targetOffering = $targetOffering ?: $offerings->first();

        return redirect()->route('maker.course_offerings.schedules.index', array_filter([
            'courseOffering' => $targetOffering,
            'modal' => 'create',
            'week_start' => $request->query('week_start'),
            'date' => $request->query('date'),
            'period' => $request->query('period'),
            'include_weekends' => $request->query('include_weekends'),
        ]));
    }

    public function storeGlobal(
        Request $request,
        ScheduleConflictChecker $conflictChecker
    ): RedirectResponse {
        $offerings = $this->coordinatorScheduleOfferings();
        $courseOffering = $offerings->firstWhere('id', (int) $request->input('course_offering_id'));

        if (! $courseOffering) {
            throw ValidationException::withMessages([
                'course_offering_id' => 'เลือกรายวิชาที่คุณรับผิดชอบ',
            ]);
        }

        return $this->storeForOffering($request, $courseOffering, $conflictChecker, true);
    }

    private function schedulePageData(
        Request $request,
        ?CourseOffering $courseOffering,
        bool $isWorkspace,
        Collection $availableOfferings
    ): array {
        $courseOffering?->load([
            'course.curriculum',
            'course.department',
            'academicYear',
            'instructorPool.instructorProfile.department',
            'studentGroups' => fn ($query) => $query->orderBy('group_code'),
        ]);
        $offeringIds = $isWorkspace
            ? $availableOfferings->pluck('id')->all()
            : ($courseOffering ? [$courseOffering->id] : []);

        // Bug #2: กรองตามผู้สอน — แสดงเฉพาะ slot ที่อาจารย์คนนี้สอน (ใช้ได้ทุก view)
        $selectedInstructorId = $request->integer('instructor_id') ?: null;
        $instructorFilter = fn ($query) => $query->when(
            $selectedInstructorId,
            fn ($q) => $q->whereHas('instructors', fn ($iq) => $iq->where('users.id', $selectedInstructorId))
        );

        // V2: filter เทอม — วิชาเปิดทั้งปี จึงเลือกเทอมเพื่อโฟกัสช่วงที่จัด
        $calendarYear = $courseOffering?->academicYear
            ?? $availableOfferings->first()?->academicYear
            ?? AcademicYear::where('is_active', true)->first();
        $terms = $calendarYear ? $calendarYear->terms()->get() : collect();
        $currentTerm = $terms->first(fn ($t) => $t->start_date && $t->end_date
            && CarbonImmutable::now()->startOfDay()->betweenIncluded(
                CarbonImmutable::parse($t->start_date)->startOfDay(),
                CarbonImmutable::parse($t->end_date)->startOfDay()
            ));
        $termIdParam = $request->query('term_id'); // absent = null → default เทอมปัจจุบัน · '' หรือ 0 = ทุกเทอม
        $termExplicit = $termIdParam !== null;
        $selectedTermId = $termExplicit ? (((int) $termIdParam) ?: null) : $currentTerm?->id;
        $selectedTerm = $selectedTermId ? $terms->firstWhere('id', $selectedTermId) : null;
        $termFilter = fn ($query) => $query->when($selectedTermId, function ($q) use ($selectedTermId, $selectedTerm) {
            $q->where(function ($termQuery) use ($selectedTermId, $selectedTerm) {
                $termQuery->where('term_id', $selectedTermId);

                if ($selectedTerm?->start_date && $selectedTerm?->end_date) {
                    $termQuery->orWhere(function ($fallback) use ($selectedTerm) {
                        $fallback
                            ->whereNull('term_id')
                            ->whereDate('start_date', '<=', CarbonImmutable::parse($selectedTerm->end_date)->toDateString())
                            ->whereDate('end_date', '>=', CarbonImmutable::parse($selectedTerm->start_date)->toDateString());
                    });
                }
            });
        });

        $firstScheduleDate = empty($offeringIds)
            ? null
            : $this->firstScheduleDate($offeringIds);

        $period = $this->validSchedulePeriod($request);
        $includeWeekends = $request->boolean('include_weekends');
        $baseDate = $this->validScheduleDate($request, 'date')
            ?? $this->validWeekStart($request)
            // เลือกเทอมชัดเจน → เด้งปฏิทินไปวันเริ่มเทอมนั้น · เทอมปัจจุบัน (default) → อยู่ที่วันนี้
            ?? ($termExplicit && $selectedTerm?->start_date ? CarbonImmutable::parse($selectedTerm->start_date) : null)
            ?? ($currentTerm && ! $termExplicit ? CarbonImmutable::now() : null)
            ?? ($firstScheduleDate ? CarbonImmutable::parse($firstScheduleDate) : null)
            ?? ($courseOffering?->academicYear?->start_date ? CarbonImmutable::parse($courseOffering->academicYear->start_date) : null)
            ?? ($availableOfferings->first()?->academicYear?->start_date ? CarbonImmutable::parse($availableOfferings->first()->academicYear->start_date) : null)
            ?? CarbonImmutable::now();

        $periodStart = match ($period) {
            'day' => CarbonImmutable::parse($baseDate)->startOfDay(),
            'month' => CarbonImmutable::parse($baseDate)->startOfMonth(),
            default => CarbonImmutable::parse($baseDate)->startOfWeek(CarbonInterface::MONDAY),
        };
        $periodEnd = match ($period) {
            'day' => $periodStart,
            'month' => $periodStart->endOfMonth(),
            default => $periodStart->addDays(6),
        };
        $gridEnd = match ($period) {
            'day' => $periodStart,
            'month' => $periodEnd,
            default => $includeWeekends ? $periodEnd : $periodStart->addDays(4),
        };

        $scheduleRelations = $this->scheduleRelations();

        $schedules = empty($offeringIds)
            ? collect()
            : $this->orderSchedulesByDate(
                $this->filterSchedulesByDateRange(
                    Schedule::query()
                        ->with($scheduleRelations)
                        ->whereIn('course_offering_id', $offeringIds)
                        ->tap($instructorFilter)
                        ->tap($termFilter),
                    $periodStart->toDateString(),
                    $periodEnd->toDateString()
                )
            )->get();

        $allSchedules = empty($offeringIds)
            ? collect()
            : ($isWorkspace
                ? $schedules
                : $this->attachCourseOfferingToSchedules(
                    $this->orderSchedulesByDate(
                        Schedule::query()
                            ->with($this->scheduleSummaryRelations())
                            ->whereIn('course_offering_id', $offeringIds)
                            ->tap($instructorFilter)
                            ->tap($termFilter)
                    )->get(),
                    $courseOffering
                ));

        $lazyScheduleList = ! $isWorkspace && $courseOffering !== null;
        $focusedScheduleId = (int) $request->query('focus_schedule_id', $request->query('edit_schedule_id', 0));
        $forcedSchedule = $focusedScheduleId
            ? $allSchedules->firstWhere('id', $focusedScheduleId)
            : null;
        $initialListWeekStart = $lazyScheduleList && $forcedSchedule?->start_date
            ? CarbonImmutable::parse($forcedSchedule->start_date)->startOfWeek(CarbonInterface::MONDAY)
            : null;
        $initialListWeekEnd = $initialListWeekStart?->addDays(6);
        $initialListSchedules = $lazyScheduleList
            ? ($initialListWeekStart && $initialListWeekEnd && $courseOffering
                ? $this->loadScheduleWeekSchedules(
                    $courseOffering,
                    $initialListWeekStart,
                    $initialListWeekEnd,
                    $selectedTermId,
                    $selectedInstructorId
                )
                : collect())
            : $allSchedules;
        $initialModalSchedules = $lazyScheduleList
            ? $initialListSchedules
                ->merge($period === 'month' ? $schedules : collect())
                ->unique('id')
                ->values()
            : null;
        $visibleConflictSchedules = $isWorkspace
            ? $schedules
            : $schedules->merge($initialListSchedules)->unique('id')->values();

        $totalScheduleCount = $isWorkspace
            ? (empty($offeringIds) ? 0 : Schedule::query()->whereIn('course_offering_id', $offeringIds)->count())
            : $allSchedules->count();

        $weekDays = collect(CarbonPeriod::create($periodStart, $gridEnd))
            ->map(fn ($date) => CarbonImmutable::parse($date))
            ->filter(fn (CarbonImmutable $date) => $period === 'day' || $includeWeekends || $date->dayOfWeekIso <= 5)
            ->values();
        // day/month แสดงทุกวันที่เกี่ยวข้องอยู่แล้ว (day=วันเดียว, month=ตารางครบ 7 วันรวมเสาร์อาทิตย์)
        // จึงต้องรวม occurrence วันเสาร์อาทิตย์ด้วย ไม่งั้นกิจกรรม (รวม recurring) ที่ตกเสาร์อาทิตย์จะหาย
        // week คงเคารพปุ่ม toggle weekend ตามเดิม
        $occurrenceIncludeWeekends = $includeWeekends || $period !== 'week';
        $occurrences = $this->scheduleOccurrences($schedules, $periodStart, $gridEnd, $occurrenceIncludeWeekends);
        $timeSlots = $this->scheduleTimeSlots($occurrences);
        $occurrencesByDate = $occurrences->groupBy(fn ($item) => $item['date']->toDateString());
        // List view: group by actual calendar date (start_date) เรียงตามวันที่จริง
        // เดิมใช้ dayOfWeekIso → ทุกวันจันทร์ของหลายสัปดาห์ clump รวมกัน
        $groupedSchedules = $this->groupSchedulesForList($allSchedules);
        $selectedDate = CarbonImmutable::parse($baseDate)->startOfDay();
        $previousPeriod = match ($period) {
            'day' => $selectedDate->subDay(),
            'month' => $selectedDate->subMonthNoOverflow(),
            default => $selectedDate->subWeek(),
        };
        $nextPeriod = match ($period) {
            'day' => $selectedDate->addDay(),
            'month' => $selectedDate->addMonthNoOverflow(),
            default => $selectedDate->addWeek(),
        };

        return [
            'courseOffering' => $courseOffering,
            'availableOfferings' => $availableOfferings,
            'isWorkspace' => $isWorkspace,
            ...$this->scheduleDatePickerYearRange(),
            'schedules' => $schedules,
            'allSchedules' => $allSchedules,
            'totalScheduleCount' => $totalScheduleCount,
            'selectedInstructorId' => $selectedInstructorId,
            'terms' => $terms,
            'selectedTermId' => $selectedTermId,
            'currentTermId' => $currentTerm?->id,
            'academicCalendar' => AcademicCalendar::forYear($calendarYear),
            'schedulePeriod' => $period,
            'includeWeekends' => $includeWeekends,
            'selectedScheduleDate' => $selectedDate,
            'weekStart' => $periodStart,
            'weekEnd' => $periodEnd,
            'weekDays' => $weekDays,
            'occurrences' => $occurrences,
            'occurrencesByDate' => $occurrencesByDate,
            'gridOccurrencesByDate' => $occurrencesByDate,
            'groupedSchedules' => $groupedSchedules,
            'scheduleConflicts' => $this->scheduleConflictMap($visibleConflictSchedules),
            'timeSlots' => $timeSlots,
            'activityTypes' => app(ReferenceDataCache::class)->activityTypes(),
            'rooms' => app(ReferenceDataCache::class)->activeRooms(),
            'lazyScheduleList' => $lazyScheduleList,
            'loadedScheduleIds' => $initialListSchedules->pluck('id')->map(fn ($id) => (string) $id)->values(),
            'loadedWeekStarts' => $lazyScheduleList && $initialListWeekStart
                ? collect([$initialListWeekStart->toDateString()])
                : collect(),
            'initialModalSchedules' => $initialModalSchedules,
            'lazyWeekFragmentUrl' => $courseOffering ? route('maker.course_offerings.schedules.week_fragment', $courseOffering, false) : null,
            'dayViewUrl' => $this->schedulePeriodUrl($courseOffering, $periodStart, $isWorkspace, 'day', $includeWeekends, $selectedInstructorId, $selectedTermId),
            'weekViewUrl' => $this->schedulePeriodUrl($courseOffering, $periodStart, $isWorkspace, 'week', $includeWeekends, $selectedInstructorId, $selectedTermId),
            'monthViewUrl' => $this->schedulePeriodUrl($courseOffering, $periodStart, $isWorkspace, 'month', $includeWeekends, $selectedInstructorId, $selectedTermId),
            'previousWeekUrl' => $this->schedulePeriodUrl($courseOffering, $previousPeriod, $isWorkspace, $period, $includeWeekends, $selectedInstructorId, $selectedTermId),
            'nextWeekUrl' => $this->schedulePeriodUrl($courseOffering, $nextPeriod, $isWorkspace, $period, $includeWeekends, $selectedInstructorId, $selectedTermId),
            'weekendToggleUrl' => $this->schedulePeriodUrl($courseOffering, $selectedDate, $isWorkspace, $period, $period === 'week' ? ! $includeWeekends : $includeWeekends, $selectedInstructorId, $selectedTermId),
            'coordinatorEmptyStateKey' => \App\Support\CoordinatorEmptyState::forCoordinator((int) Auth::id()),
        ];
    }

    private function scheduleDatePickerYearRange(): array
    {
        $years = AcademicYear::query()
            ->get(['name', 'start_date', 'end_date'])
            ->flatMap(function (AcademicYear $academicYear): array {
                $values = [];

                foreach (['start_date', 'end_date'] as $field) {
                    if ($academicYear->{$field}) {
                        $values[] = CarbonImmutable::parse($academicYear->{$field})->year;
                    }
                }

                if (is_numeric($academicYear->name)) {
                    $year = (int) $academicYear->name;
                    $values[] = $year >= 2400 ? $year - 543 : $year;
                }

                return $values;
            })
            ->filter()
            ->values();

        $currentYear = CarbonImmutable::now()->year;
        $minAcademicYear = (int) ($years->min() ?: $currentYear);
        $maxAcademicYear = (int) ($years->max() ?: $currentYear);

        return [
            'scheduleDatePickerYearStart' => min($minAcademicYear - 10, $currentYear - 20),
            'scheduleDatePickerYearEnd' => max($maxAcademicYear + 5, $currentYear + 1),
        ];
    }

    private function scheduleRelations(): array
    {
        return [
            'courseOffering.course.curriculum',
            'courseOffering.course.department',
            'courseOffering.academicYear',
            'courseOffering.instructorPool.instructorProfile.department',
            'courseOffering.studentGroups' => fn ($query) => $query->orderBy('group_code'),
            'activityType',
            'scheduleTemplate',
            'term',
            'room.locationType',
            'instructors.instructorProfile.department',
            'studentGroups',
        ];
    }

    private function scheduleSummaryRelations(): array
    {
        return [
            'activityType',
            'term',
            'room',
            'instructors.instructorProfile.department',
            'studentGroups',
        ];
    }

    private function rememberWeekFragmentPayload(string $cacheKey, callable $buildPayload): array
    {
        if (! $this->canUseWeekFragmentCache()) {
            return $buildPayload();
        }

        try {
            return Cache::remember($cacheKey, now()->addSeconds(120), $buildPayload);
        } catch (Throwable) {
            return $buildPayload();
        }
    }

    private function canUseWeekFragmentCache(): bool
    {
        if (config('cache.default') !== 'database') {
            return true;
        }

        $table = (string) config('cache.stores.database.table', 'cache');

        return $table !== '' && Schema::hasTable($table);
    }

    private function renderWeekFragmentPayload(
        CourseOffering $courseOffering,
        CarbonImmutable $weekStart,
        CarbonImmutable $weekEnd,
        ?int $selectedTermId,
        ?int $selectedInstructorId,
        bool $includeWeekends
    ): array {
        $schedules = $this->loadScheduleWeekSchedules(
            $courseOffering,
            $weekStart,
            $weekEnd,
            $selectedTermId,
            $selectedInstructorId,
        );

        $activityTypes = app(ReferenceDataCache::class)->activityTypes();
        $rooms = app(ReferenceDataCache::class)->activeRooms();
        $academicCalendar = AcademicCalendar::forYear($courseOffering->academicYear);
        $scheduleConflicts = $this->scheduleConflictMap($schedules);
        $groupedSchedules = $this->groupSchedulesForList($schedules);
        $scheduleReturnUrlParams = [
            $courseOffering,
            'week_start' => $weekStart->toDateString(),
        ];

        if ($selectedTermId) {
            $scheduleReturnUrlParams['term_id'] = $selectedTermId;
        }

        if ($selectedInstructorId) {
            $scheduleReturnUrlParams['instructor_id'] = $selectedInstructorId;
        }

        if ($includeWeekends) {
            $scheduleReturnUrlParams['include_weekends'] = 1;
        }

        $viewData = [
            'courseOffering' => $courseOffering,
            ...$this->scheduleDatePickerYearRange(),
            'allSchedules' => $schedules,
            'modalSchedules' => $schedules,
            'groupedSchedules' => $groupedSchedules,
            'scheduleConflicts' => $scheduleConflicts,
            'activityTypes' => $activityTypes,
            'rooms' => $rooms,
            'academicCalendar' => $academicCalendar,
            'selectedTermId' => $selectedTermId,
            'academicYear' => $courseOffering->academicYear,
            'canEdit' => $courseOffering->academicYear?->phase === 'scheduling',
            'includeWeekends' => $includeWeekends,
            'lazyWeekStart' => $weekStart->toDateString(),
            'scheduleReturnUrl' => route('maker.course_offerings.schedules.index', $scheduleReturnUrlParams),
        ];

        return [
            'html' => view('shared.schedules._lazy_week_rows', $viewData)->render(),
            'modal_html' => view('shared.schedules._lazy_detail_modals', $viewData)->render(),
            'schedule_items' => $this->scheduleFilterItems($schedules, $courseOffering->academicYear),
            'loaded_schedule_ids' => $schedules->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
        ];
    }

    private function loadScheduleWeekSchedules(
        CourseOffering $courseOffering,
        CarbonImmutable $weekStart,
        CarbonImmutable $weekEnd,
        ?int $selectedTermId = null,
        ?int $selectedInstructorId = null
    ): Collection {
        $courseOffering->loadMissing([
            'course.curriculum',
            'course.department',
            'academicYear.terms',
            'instructorPool.instructorProfile.department',
            'studentGroups' => fn ($query) => $query->orderBy('group_code'),
        ]);
        $selectedTerm = $selectedTermId
            ? $courseOffering->academicYear?->terms?->firstWhere('id', $selectedTermId)
            : null;

        $schedules = $this->orderSchedulesByDate(
            $this->filterSchedulesByDateRange(
                Schedule::query()
                    ->with($this->scheduleRelations())
                    ->where('course_offering_id', $courseOffering->id)
                    ->when($selectedInstructorId, fn ($q) => $q
                        ->whereHas('instructors', fn ($iq) => $iq->where('users.id', $selectedInstructorId)))
                    ->when($selectedTermId, function ($q) use ($selectedTermId, $selectedTerm) {
                        $q->where(function ($termQuery) use ($selectedTermId, $selectedTerm) {
                            $termQuery->where('term_id', $selectedTermId);

                            if ($selectedTerm?->start_date && $selectedTerm?->end_date) {
                                $termQuery->orWhere(function ($fallback) use ($selectedTerm) {
                                    $fallback
                                        ->whereNull('term_id')
                                        ->whereDate('start_date', '<=', CarbonImmutable::parse($selectedTerm->end_date)->toDateString())
                                        ->whereDate('end_date', '>=', CarbonImmutable::parse($selectedTerm->start_date)->toDateString());
                                });
                            }
                        });
                    }),
                $weekStart->toDateString(),
                $weekEnd->toDateString()
            )
        )->get();

        return $this->attachCourseOfferingToSchedules($schedules, $courseOffering);
    }

    private function attachCourseOfferingToSchedules(Collection $schedules, ?CourseOffering $courseOffering): Collection
    {
        if (! $courseOffering) {
            return $schedules;
        }

        return $schedules
            ->each(fn (Schedule $schedule) => $schedule->setRelation('courseOffering', $courseOffering))
            ->values();
    }

    private function groupSchedulesForList(Collection $schedules): Collection
    {
        return $schedules
            ->filter(fn (Schedule $schedule) => $schedule->start_date)
            ->flatMap(function (Schedule $schedule) {
                $start = CarbonImmutable::parse($schedule->start_date)->startOfDay();
                $end = $schedule->end_date
                    ? CarbonImmutable::parse($schedule->end_date)->startOfDay()
                    : $start;

                if ($end->lt($start)) {
                    $end = $start;
                }

                return collect(CarbonPeriod::create($start, $end))
                    ->map(function ($date) use ($schedule) {
                        $occurrence = clone $schedule;
                        $occurrence->setAttribute('list_date', CarbonImmutable::parse($date)->toDateString());

                        return $occurrence;
                    });
            })
            ->sortBy(fn (Schedule $schedule) => ($schedule->list_date ?? $schedule->start_date?->toDateString()) . ' ' . ($schedule->start_time ?? '00:00:00'))
            ->groupBy(fn (Schedule $schedule) => $schedule->list_date ?? $schedule->start_date->toDateString());
    }

    private function weekFragmentCacheKey(
        Request $request,
        CourseOffering $courseOffering,
        CarbonImmutable $weekStart,
        CarbonImmutable $weekEnd,
        ?int $selectedTermId,
        ?int $selectedInstructorId,
        bool $includeWeekends
    ): string {
        $statsQuery = $this->filterSchedulesByDateRange(
            Schedule::query()
                ->where('course_offering_id', $courseOffering->id)
                ->when($selectedInstructorId, fn ($q) => $q
                    ->whereHas('instructors', fn ($iq) => $iq->where('users.id', $selectedInstructorId)))
                ->when($selectedTermId, fn ($q) => $q->where('term_id', $selectedTermId)),
            $weekStart->toDateString(),
            $weekEnd->toDateString()
        );

        $stats = (clone $statsQuery)
            ->selectRaw('COUNT(*) as schedule_count, MAX(updated_at) as max_updated_at')
            ->first();
        $conflictGeneration = (int) ScheduleConflictRun::query()
            ->where('academic_year_id', $courseOffering->academic_year_id)
            ->max('generation');
        $sessionHash = hash('sha256', (string) $request->session()->token());

        return 'schedules:week-fragment:' . hash('sha256', implode('|', [
            'user:' . (int) Auth::id(),
            'session:' . $sessionHash,
            'offering:' . (int) $courseOffering->id,
            'week:' . $weekStart->toDateString(),
            'term:' . (string) ($selectedTermId ?? ''),
            'instructor:' . (string) ($selectedInstructorId ?? ''),
            'weekends:' . ($includeWeekends ? '1' : '0'),
            'count:' . (int) ($stats?->schedule_count ?? 0),
            'updated:' . (string) ($stats?->max_updated_at ?? ''),
            'conflicts:' . $conflictGeneration,
        ]));
    }

    private function scheduleFilterItems(Collection $schedules, ?AcademicYear $academicYear): array
    {
        $academicStartDate = $academicYear?->start_date
            ? CarbonImmutable::parse($academicYear->start_date)->startOfWeek(CarbonInterface::MONDAY)
            : null;

        $academicWeekOf = fn ($date) => (string) (max(1, (int) floor(
            $academicStartDate->diffInDays(
                CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY),
                false
            ) / 7
        ) + 1));

        return $schedules->map(function (Schedule $schedule) use ($academicStartDate, $academicWeekOf) {
            $instructors = $schedule->instructors ?? collect();
            $week = '';
            $weeks = [];

            if ($academicStartDate && $schedule->start_date) {
                $week = $academicWeekOf($schedule->start_date);

                // ทุกสัปดาห์ที่ schedule ครอบ (start_date→end_date) — ตรงกับ list ที่กระจายรายวัน
                $spanStart = CarbonImmutable::parse($schedule->start_date)->startOfDay();
                $spanEnd = $schedule->end_date
                    ? CarbonImmutable::parse($schedule->end_date)->startOfDay()
                    : $spanStart;
                if ($spanEnd->lt($spanStart)) {
                    $spanEnd = $spanStart;
                }
                $weeks = collect(CarbonPeriod::create($spanStart, $spanEnd))
                    ->map(fn ($date) => $academicWeekOf($date))
                    ->unique()
                    ->values()
                    ->all();
            }

            return [
                'id' => (string) $schedule->id,
                'activity' => (string) $schedule->activity_type_id,
                'groups' => $schedule->studentGroups->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                'instructors' => $instructors->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                'week' => $week,
                'weeks' => $weeks,
                'date' => $schedule->start_date?->toDateString(),
                'search' => mb_strtolower(collect([
                    $schedule->start_date ? ThaiDate::date($schedule->start_date) : null,
                    $schedule->end_date ? ThaiDate::date($schedule->end_date) : null,
                    substr((string) $schedule->start_time, 0, 5),
                    substr((string) $schedule->end_time, 0, 5),
                    $schedule->activityType?->name,
                    $schedule->topic,
                    $schedule->remark,
                    $schedule->room?->room_code,
                    $schedule->room?->room_name,
                    $schedule->studentGroups->pluck('group_code')->implode(' '),
                    $instructors->map(fn ($instructor) => $instructor->formatted_name ?? $instructor->name)->implode(' '),
                ])->filter()->implode(' '), 'UTF-8'),
            ];
        })->values()->all();
    }

    private function hasScheduleBlockDates(): bool
    {
        return Schema::hasColumn('schedules', 'start_date') && Schema::hasColumn('schedules', 'end_date');
    }

    private function firstScheduleDate(array $offeringIds): ?string
    {
        $column = $this->hasScheduleBlockDates() ? 'start_date' : 'teaching_date';

        return Schedule::query()
            ->whereIn('course_offering_id', $offeringIds)
            ->whereNotNull($column)
            ->orderBy($column)
            ->value($column);
    }

    private function filterSchedulesByDateRange($query, string $startDate, string $endDate)
    {
        if ($this->hasScheduleBlockDates()) {
            return $query
                ->whereDate('start_date', '<=', $endDate)
                ->whereDate('end_date', '>=', $startDate);
        }

        return $query
            ->whereDate('teaching_date', '>=', $startDate)
            ->whereDate('teaching_date', '<=', $endDate);
    }

    private function orderSchedulesByDate($query)
    {
        if ($this->hasScheduleBlockDates()) {
            return $query
                ->orderBy('start_date')
                ->orderBy('end_date')
                ->orderBy('start_time');
        }

        return $query
            ->orderBy('teaching_date')
            ->orderBy('start_time');
    }

    private function scheduleDatePayload(array $validated): array
    {
        if ($this->hasScheduleBlockDates()) {
            return [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'teaching_date' => null,
            ];
        }

        return [
            'teaching_date' => $validated['start_date'],
        ];
    }

    private function scheduleConflictMap(Collection $schedules): Collection
    {
        if ($schedules->isEmpty()) {
            return collect();
        }

        if (! config('conflicts.async_reads')) {
            // Fallback: real-time scan (used when async_reads is disabled)
            return app(ScheduleConflictIndex::class)->conflictsFor($schedules);
        }

        $userId = (int) Auth::id();
        $repository = app(ScheduleConflictReadRepository::class);

        // Group by academic_year_id so workspace mode (multiple offerings) works correctly.
        // courseOffering.academicYear is already eager-loaded via scheduleRelations().
        $result = collect();

        foreach ($schedules->groupBy(fn (Schedule $s) => (int) ($s->courseOffering?->academic_year_id ?? 0)) as $academicYearId => $yearSchedules) {
            if (! $academicYearId) {
                continue;
            }

            $ids = $yearSchedules->pluck('id')->map(fn ($id) => (int) $id)->all();
            $conflictMap = $repository->getConflictMapForSchedules($ids, $userId, $academicYearId);
            $result = $result->union($conflictMap);
        }

        return $result;
    }

    /**
     * Build a conflict map for the OWNED schedules — includes cross-course conflicts
     * (owned vs. anyone else's overlapping schedule). Single fetch of all overlapping
     * schedules in the relevant date range, then in-memory pairwise comparison.
     */
    private function buildOwnedConflictMap(Collection $ownedSchedules, ScheduleConflictChecker $conflictChecker): Collection
    {
        if ($ownedSchedules->isEmpty()) {
            return collect();
        }

        $ownedIds = $ownedSchedules->pluck('id')->map(fn ($v) => (int) $v)->all();
        $minStart = $ownedSchedules->pluck('start_date')->filter()->min();
        $maxEnd   = $ownedSchedules->pluck('end_date')->filter()->max();

        if (! $minStart || ! $maxEnd) {
            return collect();
        }

        // Fetch all schedules system-wide that could overlap any owned schedule's date window.
        // Reuse owned schedules' loaded relations to avoid re-querying them.
        $otherSchedules = Schedule::query()
            ->with($this->scheduleRelations())
            ->whereNotIn('id', $ownedIds)
            ->whereDate('start_date', '<=', $maxEnd)
            ->whereDate('end_date', '>=', $minStart)
            ->get();

        $candidates = $ownedSchedules->concat($otherSchedules);

        // Bulk pairwise comparison; output map keyed by every schedule id involved.
        return $conflictChecker
            ->bulkConflictMap($candidates)
            ->only($ownedIds);
    }

    private function scheduleTimeSlots(Collection $occurrences): array
    {
        return collect(range(6, 16))
            ->map(fn (int $hour) => sprintf('%02d:00', $hour))
            ->merge($occurrences->pluck('time_slot'))
            ->filter()
            ->unique()
            ->sortBy(fn (string $slot) => (int) substr($slot, 0, 2))
            ->values()
            ->all();
    }

    private function validWeekStart(Request $request): ?CarbonImmutable
    {
        return $this->validScheduleDate($request, 'week_start');
    }

    private function validScheduleDate(Request $request, string $key): ?CarbonImmutable
    {
        $value = $request->query($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $value
            ? $date
            : null;
    }

    private function validSchedulePeriod(Request $request): string
    {
        $period = $request->query('period');

        return in_array($period, ['day', 'week', 'month'], true) ? $period : 'week';
    }

    private function coordinatorScheduleOfferingRedirectTarget(Request $request): ?int
    {
        if (\App\Support\CoordinatorEmptyState::forCoordinator((int) Auth::id())
            !== \App\Support\CoordinatorEmptyState::READY) {
            return null;
        }

        $query = CourseOffering::query()
            ->withActiveCourse()
            ->schedulableBy(Auth::id())
            ->whereHas('academicYear', fn ($query) => $query->where('phase', 'scheduling'));

        $selectedId = (int) $request->query('course_offering_id');

        if ($selectedId && (clone $query)->whereKey($selectedId)->exists()) {
            return $selectedId;
        }

        return $this->coordinatorScheduleOfferings(includeSchedulingData: false)
            ->first(fn (CourseOffering $offering) => $offering->academicYear?->phase === 'scheduling')
            ?->id;
    }

    private function coordinatorScheduleOfferings(bool $includeSchedulingData = true): Collection
    {
        $relations = [
            'course.curriculum',
            'course.department',
            'academicYear',
        ];

        if ($includeSchedulingData) {
            $relations = [
                ...$relations,
                'instructorPool.instructorProfile.department',
                'studentGroups' => fn ($query) => $query->orderBy('group_code'),
            ];
        }

        return CourseOffering::query()
            ->with($relations)
            ->withCount(['schedules', 'studentGroups', 'instructorPool'])
            ->withActiveCourse()
            ->schedulableBy(Auth::id())
            ->get()
            ->sortBy(fn (CourseOffering $offering) => implode('|', [
                $offering->academicYear?->phase === 'scheduling' ? '0' : '1',
                mb_strtolower($offering->course?->course_code ?? ''),
                str_pad((string) $offering->id, 10, '0', STR_PAD_LEFT),
            ]), SORT_NATURAL)
            ->values();
    }

    private function scheduleWeekUrl(?CourseOffering $courseOffering, CarbonImmutable $weekStart, bool $isWorkspace): string
    {
        return $this->schedulePeriodUrl($courseOffering, $weekStart, $isWorkspace, 'week');
    }

    private function schedulePeriodUrl(
        ?CourseOffering $courseOffering,
        CarbonImmutable $date,
        bool $isWorkspace,
        string $period,
        bool $includeWeekends = false,
        ?int $instructorId = null,
        ?int $termId = null
    ): string
    {
        $keepWeekendParam = $period === 'week' && $includeWeekends;

        if ($isWorkspace || ! $courseOffering) {
            return route('maker.schedules.index', array_filter([
                'course_offering_id' => $courseOffering?->id,
                'week_start' => $date->toDateString(),
                'date' => $date->toDateString(),
                'period' => $period,
                'include_weekends' => $keepWeekendParam ? 1 : null,
                'instructor_id' => $instructorId,
                'term_id' => $termId,
            ]));
        }

        return route('maker.course_offerings.schedules.index', array_filter([
            $courseOffering,
            'week_start' => $date->toDateString(),
            'date' => $date->toDateString(),
            'period' => $period,
            'include_weekends' => $keepWeekendParam ? 1 : null,
            'instructor_id' => $instructorId,
            'term_id' => $termId,
        ]));
    }

    public function create(Request $request, CourseOffering $courseOffering): View|RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $courseOffering->load('academicYear');

        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->route('maker.course_offerings.schedules.index', $courseOffering)
                ->withErrors(['schedule' => 'ยังไม่เปิดช่วงจัดตาราง — Admin ต้องเปิดช่วงจัดตารางก่อน']);
        }

        return redirect()->route('maker.course_offerings.schedules.index', array_filter([
            $courseOffering,
            'modal' => 'create',
            'week_start' => $request->query('week_start'),
        ]));
    }

    public function store(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleConflictChecker $conflictChecker
    ): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);

        return $this->storeForOffering($request, $courseOffering, $conflictChecker);
    }

    private function storeForOffering(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleConflictChecker $conflictChecker,
        bool $redirectToWorkspace = false
    ): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, $redirectToWorkspace)) return $redirect;

        $validated = $this->validateSchedule($request, $courseOffering);
        $validated['course_offering_id'] = $courseOffering->id;
        $this->assertScheduleWithinAcademicYear($courseOffering, $validated);
        $this->assertScheduleNotOnBlockedDay($courseOffering, $validated);
        $this->assertSelectedGroupsFitCapacity($courseOffering, $validated);
        $this->assertLeadInstructorSelected($validated);
        $conflicts = $this->detectConflicts($conflictChecker, $validated);
        $this->assertNoBlockingScheduleConflicts($conflicts);
        $warnings = $this->selectedInstructorDepartmentWarnings($courseOffering, $validated['instructor_ids']);

        $schedule = null;

        DB::transaction(function () use ($courseOffering, $validated, &$schedule): void {
            $schedule = Schedule::create([
                'course_offering_id' => $courseOffering->id,
                'activity_type_id' => $validated['activity_type_id'],
                'room_id' => $validated['room_id'] ?? null,
                'practicum_series_id' => null,
                ...$this->scheduleDatePayload($validated),
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'topic' => $validated['topic'] ?? null,
                'capacity_required' => $validated['capacity_required'] ?? null,
                'sub_group_label' => $validated['sub_group_label'] ?? null,
                'status' => 'draft',
                'remark' => $validated['remark'] ?? null,
            ]);

            $this->syncInstructors($schedule, $validated);
            $schedule->studentGroups()->sync($validated['student_group_ids']);
        });

        \App\Services\NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);
        app(ScheduleConflictInvalidationService::class)->markScheduleDirty($schedule, 'pivot');

        AuditLogger::log(
            action: 'ตารางสอน.สร้าง',
            table: 'schedules',
            recordId: $schedule->id,
            oldValues: null,
            newValues: $this->scheduleSnapshot($schedule->fresh([
                'courseOffering.course.curriculum',
                'courseOffering.academicYear',
                'activityType',
                'room',
                'instructors',
                'studentGroups',
            ])),
            category: 'ตารางสอน',
            description: "สร้างตารางสอน: {$schedule->topic}",
        );

        $redirect = redirect()
            ->to($this->scheduleReturnUrl($request, $courseOffering, $validated['start_date'], $redirectToWorkspace))
            ->with('success', 'เพิ่มรายการสอนเรียบร้อยแล้ว');

        return $warnings === []
            ? $redirect
            : $redirect->with('schedule_conflict_warning', $warnings);
    }

    public function storeSeries(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleSeriesGenerator $seriesGenerator
    ): RedirectResponse {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $this->validateScheduleSeries($request, $courseOffering);
        $this->assertSelectedGroupsFitCapacity($courseOffering, $validated);
        $this->assertLeadInstructorSelected($validated);
        $warnings = $this->selectedInstructorDepartmentWarnings($courseOffering, $validated['instructor_ids']);

        $template = null;
        $instances = collect();

        DB::transaction(function () use ($courseOffering, $validated, $seriesGenerator, &$template, &$instances): void {
            $template = ScheduleTemplate::create([
                'course_offering_id' => $courseOffering->id,
                'activity_type_id' => $validated['activity_type_id'],
                'weekday' => $validated['weekday'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'start_week' => $validated['start_week'],
                'end_week' => $validated['end_week'],
                'starts_on' => $validated['starts_on'] ?? null,
                'ends_on' => $validated['ends_on'] ?? null,
                'topic' => $validated['topic'] ?? null,
                'capacity_required' => $validated['capacity_required'] ?? null,
                'sub_group_label' => $validated['sub_group_label'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $instances = $seriesGenerator->generateFromTemplate($template, [
                'room_id' => $validated['room_id'] ?? null,
                'instructor_ids' => $validated['instructor_ids'] ?? [],
                'lead_instructor_id' => $validated['lead_instructor_id'] ?? null,
                'student_group_ids' => $validated['student_group_ids'] ?? [],
                'status' => 'draft',
                'remark' => $validated['remark'] ?? null,
                'check_conflicts' => true,
                'populate_resources' => 'first',
            ]);
        });

        \App\Services\NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);
        if ($instances->isNotEmpty()) {
            app(ScheduleConflictInvalidationService::class)->markScheduleDirty($instances->first(), 'pivot');
        }

        AuditLogger::log(
            action: 'schedule.series.create',
            table: 'schedule_templates',
            recordId: $template?->id,
            oldValues: null,
            newValues: [
                'course_offering_id' => $courseOffering->id,
                'activity_type_id' => $validated['activity_type_id'],
                'weekday' => $validated['weekday'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'start_week' => $validated['start_week'],
                'end_week' => $validated['end_week'],
                'instance_count' => $instances->count(),
            ],
            category: 'schedule',
            description: 'Create weekly schedule series: ' . ($validated['topic'] ?? '-'),
        );

        $redirect = redirect()
            ->to($this->scheduleReturnUrl($request, $courseOffering, $instances->first()?->start_date?->toDateString()))
            ->with('success', "Created {$instances->count()} weekly schedule items.");

        return $warnings === []
            ? $redirect
            : $redirect->with('schedule_conflict_warning', $warnings);
    }

    public function updateSeriesTemplate(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleTemplate $scheduleTemplate,
        ScheduleSeriesGenerator $seriesGenerator
    ): RedirectResponse {
        $this->authorizeCourseHeadOffering($courseOffering);
        abort_unless((int) $scheduleTemplate->course_offering_id === (int) $courseOffering->id, 404);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $this->validateScheduleSeriesTemplate($request, $courseOffering);
        $snapshotBefore = $scheduleTemplate->only([
            'activity_type_id',
            'weekday',
            'start_time',
            'end_time',
            'start_week',
            'end_week',
            'starts_on',
            'ends_on',
            'topic',
            'capacity_required',
            'sub_group_label',
        ]);

        $instances = DB::transaction(function () use ($scheduleTemplate, $validated, $seriesGenerator) {
            $scheduleTemplate->update([
                'activity_type_id' => $validated['activity_type_id'],
                'weekday' => $validated['weekday'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'start_week' => $validated['start_week'],
                'end_week' => $validated['end_week'],
                'starts_on' => $validated['starts_on'] ?? null,
                'ends_on' => $validated['ends_on'] ?? null,
                'topic' => $validated['topic'] ?? null,
                'capacity_required' => $validated['capacity_required'] ?? null,
                'sub_group_label' => $validated['sub_group_label'] ?? null,
                'updated_by' => Auth::id(),
            ]);

            return $seriesGenerator->syncInstancesFromTemplate($scheduleTemplate);
        });

        \App\Services\NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);
        if ($instances->isNotEmpty()) {
            app(ScheduleConflictInvalidationService::class)->markScheduleDirty($instances->first(), 'pivot');
        }

        $snapshotAfter = $scheduleTemplate->fresh()->only(array_keys($snapshotBefore));
        $diff = AuditLogger::diff($snapshotBefore, $snapshotAfter);

        if (! empty($diff['old']) || ! empty($diff['new'])) {
            AuditLogger::log(
                action: 'schedule.series.update',
                table: 'schedule_templates',
                recordId: $scheduleTemplate->id,
                oldValues: $diff['old'],
                newValues: $diff['new'],
                category: 'schedule',
                description: 'Update weekly schedule series: ' . ($snapshotAfter['topic'] ?? '-'),
            );
        }

        return redirect()
            ->to($this->scheduleReturnUrl($request, $courseOffering, $instances->first()?->start_date?->toDateString()))
            ->with('success', "Updated {$instances->count()} weekly schedule items.");
    }

    /**
     * Dry-run: คำนวณว่าถ้าคัดลอกทั้งสัปดาห์ slot ไหน "พร้อมคัดลอก" และ slot ไหน "ชน"
     * โดยไม่เขียนลง DB — ใช้ render preview ก่อน commit
     */
    public function previewCopyWeek(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleConflictChecker $conflictChecker
    ): JsonResponse {
        $this->authorizeCourseHeadOffering($courseOffering);
        $copy = $this->resolveCopyScheduleRequest($request);
        $courseOffering->loadMissing('academicYear');

        $candidates = $this->collectCopyCandidates(
            $courseOffering,
            $copy['source_start'],
            $copy['source_end'],
            $copy['target_start']
        );

        $ready = [];
        $blocked = [];

        foreach ($candidates as $candidate) {
            $reasons = $this->weekCopyBlockReasons($courseOffering, $candidate, $conflictChecker);

            if (empty($reasons)) {
                $ready[] = $candidate['preview'];
            } else {
                $blocked[] = $candidate['preview'] + ['reasons' => $reasons];
            }
        }

        return response()->json([
            'copy_mode' => $copy['mode'],
            'source_week_start' => $copy['mode'] === 'week' ? $copy['source_start'] : null,
            'target_week_start' => $copy['mode'] === 'week' ? $copy['target_start'] : null,
            'source_start_date' => $copy['source_start'],
            'source_end_date' => $copy['source_end'],
            'target_start_date' => $copy['target_start'],
            'target_end_date' => $copy['target_end'],
            'total' => count($candidates),
            'ready' => $ready,
            'blocked' => $blocked,
        ]);
    }

    /**
     * Commit: คัดลอกเฉพาะ slot ที่ผ่าน (clean) — slot ที่ชนถูกข้าม ไม่ persist
     * เช็คแบบ sequential เพื่อจับการชนภายใน batch เอง (slot ที่เพิ่งสร้างนับเป็นของจริง)
     */
    public function copyWeek(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleConflictChecker $conflictChecker
    ): RedirectResponse {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $copy = $this->resolveCopyScheduleRequest($request);
        $courseOffering->loadMissing('academicYear');

        $candidates = $this->collectCopyCandidates(
            $courseOffering,
            $copy['source_start'],
            $copy['source_end'],
            $copy['target_start']
        );

        if (empty($candidates)) {
            return redirect()
                ->to($this->copyWeekReturnUrl($request, $courseOffering, $copy['target_start']))
                ->withErrors(['schedule' => "ไม่พบรายการใน{$copy['source_label']}ต้นทางให้คัดลอก"]);
        }

        $readyCandidates = [];
        $blocked = [];
        foreach ($candidates as $candidate) {
            $reasons = $this->weekCopyBlockReasons($courseOffering, $candidate, $conflictChecker);

            if (! empty($reasons)) {
                $blocked[] = $candidate['preview'] + ['reason' => implode(' / ', $reasons)];
                continue;
            }

            $readyCandidates[] = $candidate;
        }

        if (empty($readyCandidates)) {
            return redirect()
                ->to($this->copyWeekReturnUrl($request, $courseOffering, $copy['target_start']))
                ->with('warning', 'ไม่พบรายการที่คัดลอกได้ เนื่องจากรายการทั้งหมดชนกับตารางเดิม')
                ->with('schedule_copy_skipped', $blocked)
                ->with('schedule_copy_blocked', $blocked);
        }

        $createdCount = 0;

        DB::transaction(function () use ($readyCandidates, $courseOffering, &$createdCount): void {
            foreach ($readyCandidates as $candidate) {
                $payload = $candidate['payload'];
                $schedule = Schedule::create([
                    'course_offering_id' => $courseOffering->id,
                    'term_id' => $payload['term_id'],
                    'activity_type_id' => $payload['activity_type_id'],
                    'room_id' => $payload['room_id'],
                    'practicum_series_id' => null,
                    'start_date' => $payload['start_date'],
                    'end_date' => $payload['end_date'],
                    'start_time' => $payload['start_time'],
                    'end_time' => $payload['end_time'],
                    'topic' => $payload['topic'],
                    'capacity_required' => $payload['capacity_required'],
                    'sub_group_label' => $payload['sub_group_label'],
                    'status' => 'draft',
                    'remark' => $payload['remark'],
                ]);

                $this->syncInstructors($schedule, [
                    'instructor_ids' => $candidate['instructor_ids'],
                    'lead_instructor_id' => $candidate['lead_instructor_id'],
                ]);
                $schedule->studentGroups()->sync($candidate['group_ids']);
                app(ScheduleConflictInvalidationService::class)->markScheduleDirty($schedule, 'pivot');
                $createdCount++;
            }
        });

        \App\Services\NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        AuditLogger::log(
            action: 'schedule.week.copy',
            table: 'course_offerings',
            recordId: $courseOffering->id,
            oldValues: null,
            newValues: [
                'course_offering_id' => $courseOffering->id,
                'copy_mode' => $copy['mode'],
                'source_start_date' => $copy['source_start'],
                'source_end_date' => $copy['source_end'],
                'target_start_date' => $copy['target_start'],
                'target_end_date' => $copy['target_end'],
                'created' => $createdCount,
                'blocked' => count($blocked),
            ],
            category: 'schedule',
            description: "คัดลอก{$copy['source_label']} {$copy['source_start']} → {$copy['target_start']}",
        );

        $message = "คัดลอกสำเร็จ {$createdCount} รายการ";
        if (! empty($blocked)) {
            $message .= " (ข้าม " . count($blocked) . " รายการที่ชน)";
        }

        $redirect = redirect()
            ->to($this->copyWeekReturnUrl($request, $courseOffering, $copy['target_start']))
            ->with('success', $message);

        if (! empty($blocked)) {
            $redirect
                ->with('schedule_copy_skipped', $blocked)
                ->with('schedule_copy_blocked', $blocked);
        }

        return $redirect;
    }

    /**
     * @return array{0:string,1:string}  [sourceWeekStart, targetWeekStart] เป็น Y-m-d
     */
    private function copyWeekReturnUrl(Request $request, CourseOffering $courseOffering, string $targetWeekStart): string
    {
        $targetWeek = CarbonImmutable::parse($targetWeekStart)->startOfDay();
        $query = [];
        $returnUrl = (string) $request->input('return_url', '');

        if ($this->isScheduleReturnUrl($request, $returnUrl)) {
            parse_str((string) (parse_url($returnUrl, PHP_URL_QUERY) ?? ''), $query);
        }

        $period = in_array(($query['period'] ?? 'week'), ['day', 'week', 'month'], true)
            ? (string) ($query['period'] ?? 'week')
            : 'week';
        $includeWeekends = $period === 'week' && (bool) ($query['include_weekends'] ?? false);
        $instructorId = isset($query['instructor_id']) && (int) $query['instructor_id'] > 0
            ? (int) $query['instructor_id']
            : null;
        $termId = AcademicCalendar::forYear($courseOffering->academicYear)->termIdForDate($targetWeek);

        $params = [
            $courseOffering,
            'week_start' => $targetWeek->toDateString(),
            'date' => $targetWeek->toDateString(),
            'period' => $period,
            'include_weekends' => $includeWeekends ? 1 : null,
            'instructor_id' => $instructorId,
            'term_id' => $termId ?: 0,
        ];

        return route('maker.course_offerings.schedules.index', array_filter($params, fn ($value) => $value !== null && $value !== false && $value !== ''));
    }

    private function resolveCopyScheduleRequest(Request $request): array
    {
        $mode = in_array($request->input('copy_mode', 'week'), ['week', 'day', 'range'], true)
            ? (string) $request->input('copy_mode', 'week')
            : 'week';

        if ($mode === 'day') {
            $data = $request->validate([
                'source_date' => ['required', 'date'],
                'target_date' => ['required', 'date', 'different:source_date'],
            ], [
                'target_date.different' => 'วันปลายทางต้องไม่ใช่วันเดียวกับต้นทาง',
            ]);

            $sourceStart = CarbonImmutable::parse($data['source_date'])->startOfDay();
            $targetStart = CarbonImmutable::parse($data['target_date'])->startOfDay();

            return [
                'mode' => 'day',
                'source_start' => $sourceStart->toDateString(),
                'source_end' => $sourceStart->toDateString(),
                'target_start' => $targetStart->toDateString(),
                'target_end' => $targetStart->toDateString(),
                'source_label' => 'วัน',
            ];
        }

        if ($mode === 'range') {
            $data = $request->validate([
                'source_start_date' => ['required', 'date'],
                'source_end_date' => ['required', 'date', 'after_or_equal:source_start_date'],
                'target_start_date' => ['required', 'date', 'different:source_start_date'],
            ], [
                'source_end_date.after_or_equal' => 'วันที่สิ้นสุดช่วงต้นทางต้องไม่อยู่ก่อนวันที่เริ่ม',
                'target_start_date.different' => 'ช่วงปลายทางต้องไม่เริ่มวันเดียวกับช่วงต้นทาง',
            ]);

            $sourceStart = CarbonImmutable::parse($data['source_start_date'])->startOfDay();
            $sourceEnd = CarbonImmutable::parse($data['source_end_date'])->startOfDay();
            $targetStart = CarbonImmutable::parse($data['target_start_date'])->startOfDay();
            $targetEnd = $targetStart->addDays((int) $sourceStart->diffInDays($sourceEnd));

            return [
                'mode' => 'range',
                'source_start' => $sourceStart->toDateString(),
                'source_end' => $sourceEnd->toDateString(),
                'target_start' => $targetStart->toDateString(),
                'target_end' => $targetEnd->toDateString(),
                'source_label' => 'ช่วงวันที่',
            ];
        }

        $data = $request->validate([
            'source_week_start' => ['required', 'date'],
            'target_week_start' => ['required', 'date', 'different:source_week_start'],
        ], [
            'target_week_start.different' => 'สัปดาห์ปลายทางต้องไม่ใช่สัปดาห์เดียวกับต้นทาง',
        ]);

        $sourceStart = CarbonImmutable::parse($data['source_week_start'])->startOfDay();
        $targetStart = CarbonImmutable::parse($data['target_week_start'])->startOfDay();

        return [
            'mode' => 'week',
            'source_start' => $sourceStart->toDateString(),
            'source_end' => $sourceStart->addDays(6)->toDateString(),
            'target_start' => $targetStart->toDateString(),
            'target_end' => $targetStart->addDays(6)->toDateString(),
            'source_label' => 'สัปดาห์',
        ];
    }

    /**
     * อ่าน slot ทั้งหมดในช่วงต้นทาง แล้ว clone เป็น payload สำหรับช่วงปลายทาง (เลื่อนวันที่ตาม delta)
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectCopyCandidates(
        CourseOffering $courseOffering,
        string $sourceStartDate,
        string $sourceEndDate,
        string $targetStartDate
    ): array {
        $srcStart = CarbonImmutable::parse($sourceStartDate)->startOfDay();
        $srcEnd = CarbonImmutable::parse($sourceEndDate)->startOfDay();
        $deltaDays = $srcStart->diffInDays(CarbonImmutable::parse($targetStartDate)->startOfDay(), false);
        $academicCalendar = AcademicCalendar::forYear($courseOffering->academicYear);

        return Schedule::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereBetween('start_date', [$srcStart->toDateString(), $srcEnd->toDateString()])
            ->with(['instructors', 'studentGroups', 'activityType', 'room'])
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get()
            ->map(function (Schedule $source) use ($courseOffering, $deltaDays, $academicCalendar) {
                $newStart = CarbonImmutable::parse($source->start_date)->addDays($deltaDays);
                $newEnd = $source->end_date
                    ? CarbonImmutable::parse($source->end_date)->addDays($deltaDays)
                    : $newStart;
                $startTime = substr((string) $source->start_time, 0, 5);
                $endTime = substr((string) $source->end_time, 0, 5);
                $lead = $source->instructors->first(fn ($user) => (bool) ($user->pivot->is_lead ?? false));

                $payload = [
                    'course_offering_id' => $courseOffering->id,
                    'term_id' => $academicCalendar->termIdForDate($newStart),
                    'activity_type_id' => $source->activity_type_id,
                    'room_id' => $source->room_id,
                    'start_date' => $newStart->toDateString(),
                    'end_date' => $newEnd->toDateString(),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'topic' => $source->topic,
                    'capacity_required' => $source->capacity_required,
                    'sub_group_label' => $source->sub_group_label,
                    'remark' => $source->remark,
                ];

                return [
                    'payload' => $payload,
                    'instructor_ids' => $source->instructors->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    'group_ids' => $source->studentGroups->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    'lead_instructor_id' => $lead?->id,
                    'preview' => [
                        'source_date' => $source->start_date?->toDateString(),
                        'target_date' => $newStart->toDateString(),
                        'time' => $startTime . '–' . $endTime,
                        'activity' => $source->activityType?->name,
                        'room' => $source->room?->room_code ?? $source->room?->room_name,
                        'topic' => $source->topic,
                    ],
                ];
            })
            ->all();
    }

    /**
     * เหตุผลที่ slot นี้คัดลอกไม่ได้ — ว่าง = พร้อมคัดลอก
     *
     * @param  array<string, mixed>  $candidate
     * @return array<int, string>
     */
    private function weekCopyBlockReasons(
        CourseOffering $courseOffering,
        array $candidate,
        ScheduleConflictChecker $conflictChecker
    ): array {
        $payload = $candidate['payload'];

        if (! $this->scheduleWithinAcademicYear($courseOffering, $payload['start_date'], $payload['end_date'])) {
            return ['วันที่ปลายทางอยู่นอกช่วงปีการศึกษา'];
        }

        $conflicts = $conflictChecker->check(
            $payload,
            $candidate['instructor_ids'],
            $candidate['group_ids'],
            null
        );

        return collect($conflicts)->pluck('message')->unique()->values()->all();
    }

    private function scheduleWithinAcademicYear(CourseOffering $courseOffering, string $startDate, string $endDate): bool
    {
        $year = $courseOffering->academicYear;

        if (! $year?->start_date || ! $year?->end_date) {
            return true;
        }

        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();

        return ! ($start->lt(CarbonImmutable::parse($year->start_date)->startOfDay())
            || $end->gt(CarbonImmutable::parse($year->end_date)->startOfDay()));
    }

    /**
     * Realtime schedule check (ไม่บันทึก)
     * คืน fields สำหรับ hard validation ที่บล็อกการบันทึก และ warnings สำหรับข้อเตือนที่ยังบันทึกได้
     */
    public function checkConflicts(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleConflictChecker $conflictChecker
    ): JsonResponse {
        $this->authorizeCourseHeadOffering($courseOffering);

        $dateErrors = $this->normalizeScheduleThaiDateFields($request, [
            'start_date' => 'วันที่เริ่ม',
            'end_date' => 'วันที่สิ้นสุด',
        ]);

        $ignoreScheduleId = $request->integer('schedule_id') ?: null;
        $editingSchedule = $ignoreScheduleId
            ? Schedule::query()
                ->where('course_offering_id', $courseOffering->id)
                ->find($ignoreScheduleId)
            : null;
        $scheduleDate = fn (?CarbonInterface $value): ?string => $value?->toDateString();
        $scheduleTime = fn ($value): ?string => $value ? substr((string) $value, 0, 5) : null;

        $data = [
            'course_offering_id' => $courseOffering->id,
            'start_date' => $request->input('start_date') ?: $scheduleDate($editingSchedule?->start_date),
            'end_date' => $request->input('end_date') ?: $scheduleDate($editingSchedule?->end_date),
            'start_time' => $request->input('start_time') ?: $scheduleTime($editingSchedule?->start_time),
            'end_time' => $request->input('end_time') ?: $scheduleTime($editingSchedule?->end_time),
            'activity_type_id' => $request->input('activity_type_id') ?: $editingSchedule?->activity_type_id,
            'room_id' => $request->input('room_id') ?: $editingSchedule?->room_id,
            'capacity_required' => $request->input('capacity_required') ?: $editingSchedule?->capacity_required,
            'sub_group_label' => $request->input('sub_group_label') ?: $editingSchedule?->sub_group_label,
            'instructor_ids' => array_values(array_filter(array_map('intval', (array) $request->input('instructor_ids', [])))),
            'student_group_ids' => array_values(array_filter(array_map('intval', (array) $request->input('student_group_ids', [])))),
            'lead_instructor_id' => $request->input('lead_instructor_id') ?: $editingSchedule?->lead_instructor_id,
        ];

        $fields = $dateErrors;
        $warnings = [];
        $collect = function (string $field, callable $assert) use (&$fields) {
            try {
                $assert();
            } catch (ValidationException $e) {
                foreach ($e->errors() as $messages) {
                    foreach ((array) $messages as $message) {
                        $fields[$field][] = $message;
                    }
                }
            }
        };

        // Gates — reuse logic เดียวกับตอน store (ไม่ throw, แค่เก็บข้อความ)
        if (empty($dateErrors) && $data['start_date'] && $data['end_date']) {
            $collect('start_date', fn () => $this->assertScheduleWithinAcademicYear($courseOffering, $data));
            $collect('schedule', fn () => $this->assertScheduleNotOnBlockedDay($courseOffering, $data));
        }
        if (! empty($data['instructor_ids'])) {
            $collect('lead_instructor_id', fn () => $this->assertLeadInstructorSelected($data));
            $departmentWarnings = $this->selectedInstructorDepartmentWarnings($courseOffering, $data['instructor_ids']);
            if ($departmentWarnings !== []) {
                $warnings['instructor_ids'] = array_merge($warnings['instructor_ids'] ?? [], $departmentWarnings);
            }
        }
        if (empty($data['student_group_ids'])) {
            $fields['student_group_ids'][] = 'กรุณาเลือกกลุ่มนักศึกษาอย่างน้อย 1 กลุ่ม';
        }
        $collect('student_group_ids', fn () => $this->assertSelectedGroupsFitCapacity($courseOffering, $data));

        // All resource overlaps are conflicts: in-course and cross-course both block saving.
        if (empty($dateErrors) && $data['start_date'] && $data['end_date'] && $data['start_time'] && $data['end_time'] && $data['activity_type_id']) {
            $conflicts = $conflictChecker->check(
                Arr::only($data, ['course_offering_id', 'activity_type_id', 'start_date', 'end_date', 'start_time', 'end_time', 'room_id', 'sub_group_label']),
                $data['instructor_ids'],
                $data['student_group_ids'],
                $ignoreScheduleId
            );

            foreach ($conflicts as $conflict) {
                $field = $this->fieldForScheduleConflict($conflict['type']);
                $fields[$field][] = $conflict['message'];
            }
        }

        foreach ($fields as $key => $messages) {
            $fields[$key] = array_values(array_unique($messages));
        }
        foreach ($warnings as $key => $messages) {
            $warnings[$key] = array_values(array_unique($messages));
        }

        return response()->json([
            'blocking' => ! empty($fields),
            'fields' => (object) $fields,
            'warnings' => (object) $warnings,
        ]);
    }

    public function edit(CourseOffering $courseOffering, Schedule $schedule): View|RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $this->assertScheduleBelongsToOffering($courseOffering, $schedule);

        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        return redirect()->route('maker.course_offerings.schedules.index', [
            $courseOffering,
            'edit_schedule_id' => $schedule->id,
            'week_start' => $schedule->start_date?->toDateString(),
        ]);
    }

    public function update(
        Request $request,
        CourseOffering $courseOffering,
        Schedule $schedule,
        ScheduleConflictChecker $conflictChecker
    ): RedirectResponse {
        $this->authorizeCourseHeadOffering($courseOffering);
        $this->assertScheduleBelongsToOffering($courseOffering, $schedule);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $this->validateSchedule($request, $courseOffering, (bool) $schedule->schedule_template_id);
        $validated['course_offering_id'] = $courseOffering->id;

        if ($schedule->schedule_template_id) {
            $validated = $this->preserveSeriesInstanceTemplateFields($schedule, $validated);
        }

        $this->assertScheduleWithinAcademicYear($courseOffering, $validated);
        $this->assertScheduleNotOnBlockedDay($courseOffering, $validated);
        $this->assertSelectedGroupsFitCapacity($courseOffering, $validated);
        $this->assertLeadInstructorSelected($validated);
        $warnings = $this->selectedInstructorDepartmentWarnings($courseOffering, $validated['instructor_ids']);

        $conflicts = $this->detectConflicts($conflictChecker, $validated, $schedule->id);
        $this->assertNoBlockingScheduleConflicts($conflicts);

        // Snapshot before for diffing
        $schedule->loadMissing([
            'courseOffering.course.curriculum',
            'courseOffering.academicYear',
            'activityType',
            'room',
            'instructors',
            'studentGroups',
        ]);
        $snapshotBefore = $this->scheduleSnapshot($schedule);

        DB::transaction(function () use ($schedule, $validated): void {
            $schedule->update([
                'activity_type_id' => $validated['activity_type_id'],
                'room_id' => $validated['room_id'] ?? null,
                ...$this->scheduleDatePayload($validated),
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'topic' => $validated['topic'] ?? null,
                'capacity_required' => $validated['capacity_required'] ?? null,
                'sub_group_label' => $validated['sub_group_label'] ?? null,
                'remark' => $validated['remark'] ?? null,
            ]);

            $this->syncInstructors($schedule, $validated);
            $schedule->studentGroups()->sync($validated['student_group_ids']);
        });

        \App\Services\NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);
        app(ScheduleConflictInvalidationService::class)->markScheduleDirty($schedule, 'pivot');

        $snapshotAfter = $this->scheduleSnapshot($schedule->fresh([
            'courseOffering.course.curriculum',
            'courseOffering.academicYear',
            'activityType',
            'room',
            'instructors',
            'studentGroups',
        ]));

        $diff = AuditLogger::diff($snapshotBefore, $snapshotAfter);

        if (! empty($diff['old']) || ! empty($diff['new'])) {
            AuditLogger::log(
                action: 'ตารางสอน.แก้ไข',
                table: 'schedules',
                recordId: $schedule->id,
                oldValues: $diff['old'],
                newValues: $diff['new'],
                category: 'ตารางสอน',
                description: "แก้ไขตารางสอน: {$schedule->topic}",
            );
        }

        $redirect = redirect()
            ->to($this->scheduleReturnUrl($request, $courseOffering, $validated['start_date']))
            ->with('success', 'อัปเดตรายการสอนเรียบร้อยแล้ว');

        return $warnings === []
            ? $redirect
            : $redirect->with('schedule_conflict_warning', $warnings);
    }

    public function destroy(Request $request, CourseOffering $courseOffering, Schedule $schedule): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $this->assertScheduleBelongsToOffering($courseOffering, $schedule);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        if ($schedule->schedule_template_id && in_array($request->input('series_delete_scope'), ['all', 'from_current'], true)) {
            return $this->destroyScheduleSeriesFromSchedule($request, $courseOffering, $schedule);
        }

        $schedule->loadMissing([
            'courseOffering.course.curriculum',
            'courseOffering.academicYear',
            'activityType',
            'room',
            'instructors',
            'studentGroups',
        ]);
        $snapshot = $this->scheduleSnapshot($schedule);
        $scheduleId = $schedule->id;

        $schedule->delete();

        \App\Services\NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        AuditLogger::log(
            action: 'ตารางสอน.ลบ',
            table: 'schedules',
            recordId: $scheduleId,
            oldValues: $snapshot,
            newValues: null,
            category: 'ตารางสอน',
            description: "ลบตารางสอน: {$snapshot['topic']}",
        );

        return redirect()
            ->to($this->scheduleReturnUrl($request, $courseOffering))
            ->with('warning', 'ลบรายการสอนเรียบร้อยแล้ว');
    }

    private function destroyScheduleSeriesFromSchedule(
        Request $request,
        CourseOffering $courseOffering,
        Schedule $schedule
    ): RedirectResponse {
        $schedule->loadMissing('scheduleTemplate');
        $scheduleTemplate = $schedule->scheduleTemplate;
        abort_unless($scheduleTemplate && (int) $scheduleTemplate->course_offering_id === (int) $courseOffering->id, 404);

        $validated = $request->validate([
            'series_delete_scope' => ['required', Rule::in(['all', 'from_current'])],
        ]);

        return $this->deleteSeriesTemplateSchedules(
            $request,
            $courseOffering,
            $scheduleTemplate,
            $validated['series_delete_scope'],
            $schedule->series_week_number ? (int) $schedule->series_week_number : null
        );
    }

    public function destroySeriesTemplate(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleTemplate $scheduleTemplate
    ): RedirectResponse {
        $this->authorizeCourseHeadOffering($courseOffering);
        abort_unless((int) $scheduleTemplate->course_offering_id === (int) $courseOffering->id, 404);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $request->validate([
            'delete_scope' => ['required', Rule::in(['all', 'from_current'])],
            'current_week' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->deleteSeriesTemplateSchedules(
            $request,
            $courseOffering,
            $scheduleTemplate,
            $validated['delete_scope'],
            isset($validated['current_week']) ? (int) $validated['current_week'] : null
        );
    }

    private function deleteSeriesTemplateSchedules(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleTemplate $scheduleTemplate,
        string $scope,
        ?int $currentWeek
    ): RedirectResponse {
        $scheduleTemplate->loadMissing(['activityType']);
        $snapshot = $scheduleTemplate->only([
            'course_offering_id',
            'activity_type_id',
            'weekday',
            'start_time',
            'end_time',
            'start_week',
            'end_week',
            'starts_on',
            'ends_on',
            'topic',
            'capacity_required',
            'sub_group_label',
        ]);

        $deleteAll = $scope === 'all'
            || $currentWeek === null
            || $currentWeek <= (int) $scheduleTemplate->start_week;

        $deletedCount = 0;

        DB::transaction(function () use ($scheduleTemplate, $deleteAll, $currentWeek, &$deletedCount): void {
            $query = $scheduleTemplate->schedules()->orderBy('series_week_number');

            if (! $deleteAll) {
                $query->where('series_week_number', '>=', $currentWeek);
            }

            $schedules = $query->get();
            $deletedCount = $schedules->count();
            $schedules->each->delete();

            if ($deleteAll) {
                $scheduleTemplate->delete();
                return;
            }

            $scheduleTemplate->update([
                'end_week' => max((int) $scheduleTemplate->start_week, $currentWeek - 1),
                'updated_by' => Auth::id(),
            ]);
        });

        \App\Services\NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        AuditLogger::log(
            action: $deleteAll ? 'schedule.series.delete' : 'schedule.series.delete_from_week',
            table: 'schedule_templates',
            recordId: $scheduleTemplate->id,
            oldValues: $snapshot,
            newValues: [
                'deleted_schedule_count' => $deletedCount,
                'delete_scope' => $scope,
                'current_week' => $currentWeek,
            ],
            category: 'schedule',
            description: ($deleteAll ? 'Delete weekly schedule series: ' : 'Delete weekly schedule series from week: ')
                . ($snapshot['topic'] ?? '-'),
        );

        $message = $deleteAll
            ? "ลบชุดทำซ้ำทั้งหมด {$deletedCount} รายการแล้ว"
            : "ลบรายการตั้งแต่สัปดาห์ {$currentWeek} เป็นต้นไป {$deletedCount} รายการแล้ว";

        return redirect()
            ->to($this->scheduleReturnUrl($request, $courseOffering))
            ->with('warning', $message);
    }

    private function authorizeCourseHeadOffering(CourseOffering $courseOffering): void
    {
        $courseOffering->loadMissing('course');
        abort_unless($courseOffering->canBeScheduledBy((int) Auth::id()), 403);
        abort_unless($courseOffering->course?->status === 'active', 403);
    }

    private function assertScheduleBelongsToOffering(CourseOffering $courseOffering, Schedule $schedule): void
    {
        abort_unless((int) $schedule->course_offering_id === (int) $courseOffering->id, 404);
    }

    private function requireSchedulingPhase(CourseOffering $courseOffering, bool $redirectToWorkspace = false): ?RedirectResponse
    {
        $courseOffering->loadMissing('academicYear');

        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->route('maker.course_offerings.schedules.index', $courseOffering)
                ->withErrors(['schedule' => 'ยังไม่เปิดช่วงจัดตาราง — Admin ต้องเปิดช่วงจัดตารางก่อน']);
        }

        // M11: ล็อกตารางเมื่อส่งขออนุมัติแล้ว (pending) หรืออนุมัติแล้ว (published)
        if (in_array($courseOffering->approval_status, ['pending', 'published'], true)) {
            $state = $courseOffering->approval_status === 'pending' ? 'อยู่ระหว่างรออนุมัติ' : 'อนุมัติแล้ว';
            return redirect()
                ->route('maker.course_offerings.schedules.index', $courseOffering)
                ->withErrors(['schedule' => "รายวิชานี้{$state} — ตารางถูกล็อก แก้ไขไม่ได้"]);
        }

        return null;
    }

    private function workspaceRedirectUrl(CourseOffering $courseOffering, string $date): string
    {
        $weekStart = CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY);

        return route('maker.course_offerings.schedules.index', [
            $courseOffering,
            'week_start' => $weekStart->toDateString(),
        ]);
    }

    private function scheduleReturnUrl(
        Request $request,
        CourseOffering $courseOffering,
        ?string $date = null,
        bool $redirectToWorkspace = false
    ): string {
        $returnUrl = (string) $request->input('return_url', '');

        if ($request->boolean('return_to_conflicts')) {
            return route('maker.alerts.index');
        }

        if ($this->isScheduleReturnUrl($request, $returnUrl)) {
            return $this->cleanScheduleReturnUrl($request, $returnUrl);
        }

        if ($redirectToWorkspace && $date) {
            return $this->workspaceRedirectUrl($courseOffering, $date);
        }

        return route('maker.course_offerings.schedules.index', $courseOffering);
    }

    private function isScheduleReturnUrl(Request $request, string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        $requestHost = $request->getHost();
        $host = $parts['host'] ?? $requestHost;
        $path = $parts['path'] ?? '';

        if ($host !== $requestHost) {
            return false;
        }

        if ($path === '/maker/schedules') {
            return true;
        }

        return preg_match('#^/maker/course-offerings/[^/]+/schedules/?$#', $path) === 1;
    }

    private function cleanScheduleReturnUrl(Request $request, string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        $query = [];

        parse_str((string) ($parts['query'] ?? ''), $query);

        $nestedReturnUrl = (string) ($query['return_url'] ?? '');
        if ($this->isAlertsReturnUrl($request, $nestedReturnUrl)) {
            return $nestedReturnUrl;
        }

        foreach ([
            'edit_schedule_id',
            'edit_series_template_id',
            'focus_schedule_id',
            'modal',
            'from_conflict',
            'return_url',
        ] as $transientKey) {
            unset($query[$transientKey]);
        }

        $query = array_filter($query, fn ($value) => $value !== null && $value !== false && $value !== '');

        return $path . ($query === [] ? '' : ('?' . http_build_query($query)));
    }

    private function isAlertsReturnUrl(Request $request, string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        $requestHost = $request->getHost();
        $host = $parts['host'] ?? $requestHost;
        $path = $parts['path'] ?? '';

        return $host === $requestHost && $path === '/maker/alerts';
    }

    private function scheduleOccurrences($schedules, CarbonImmutable $weekStart, CarbonImmutable $weekEnd, bool $includeWeekends = false)
    {
        return $schedules
            ->flatMap(function (Schedule $schedule) use ($weekStart, $weekEnd, $includeWeekends) {
                $startDate = CarbonImmutable::parse($schedule->start_date ?? $schedule->teaching_date);
                $endDate = CarbonImmutable::parse($schedule->end_date ?? $schedule->teaching_date);
                $rangeStart = $startDate->greaterThan($weekStart) ? $startDate : $weekStart;
                $rangeEnd = $endDate->lessThan($weekEnd) ? $endDate : $weekEnd;

                return collect(CarbonPeriod::create($rangeStart, $rangeEnd))
                    ->filter(fn ($date) => $includeWeekends || $date->dayOfWeekIso <= 5)
                    ->map(fn ($date) => [
                        'schedule' => $schedule,
                        'date' => CarbonImmutable::parse($date),
                        'duration_minutes' => $this->durationMinutes($schedule),
                        'time_slot' => substr((string) $schedule->start_time, 0, 2) . ':00',
                    ]);
            })
            // Laravel 13: sortBy([closure, closure, ...]) เรียงผิด — ใช้ closure เดียวสร้าง composite key
            ->sortBy(fn ($item) => $item['date']->toDateString()
                . ' ' . substr((string) $item['schedule']->start_time, 0, 8)
                . ' ' . str_pad((string) $item['schedule']->id, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    private function durationMinutes(Schedule $schedule): int
    {
        $start = CarbonImmutable::createFromFormat('H:i:s', strlen((string) $schedule->start_time) === 5 ? $schedule->start_time . ':00' : (string) $schedule->start_time);
        $end = CarbonImmutable::createFromFormat('H:i:s', strlen((string) $schedule->end_time) === 5 ? $schedule->end_time . ':00' : (string) $schedule->end_time);

        return (int) max(0, $start->diffInMinutes($end));
    }

    /**
     * Normalize Thai Buddhist date inputs before Laravel date validation.
     *
     * Invalid non-empty values must stay as validation errors, not fall through to
     * Carbon parsing during redirect-back rendering.
     *
     * @param  array<string, string>  $fieldLabels
     * @return array<string, array<int, string>>
     */
    private function normalizeScheduleThaiDateFields(Request $request, array $fieldLabels): array
    {
        $errors = [];

        foreach ($fieldLabels as $field => $label) {
            if (! $request->has($field)) {
                continue;
            }

            $value = trim((string) $request->input($field));
            if ($value === '') {
                continue;
            }

            $iso = ThaiDate::parseToIso($value);
            if ($iso === null) {
                $errors[$field][] = "{$label}ไม่ถูกต้อง กรุณากรอกวันที่ในรูปแบบ วว/ดด/พ.ศ. เช่น 21/05/2569";
                continue;
            }

            $request->merge([$field => $iso]);
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $fieldLabels
     */
    private function assertScheduleThaiDateFields(Request $request, array $fieldLabels): void
    {
        $errors = $this->normalizeScheduleThaiDateFields($request, $fieldLabels);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validateSchedule(Request $request, CourseOffering $courseOffering, bool $allowEmptyResources = false): array
    {
        // ฟอร์มส่งวันที่เป็น วว/ดด/พ.ศ. (x-thai-date-input) — normalize เป็น ISO ก่อน validate
        $this->assertScheduleThaiDateFields($request, [
            'start_date' => 'วันที่เริ่ม',
            'end_date' => 'วันที่สิ้นสุด',
        ]);

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'activity_type_id' => ['required', 'integer', 'exists:activity_types,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'topic' => ['required', 'string', 'max:255'],
            'remark' => ['nullable', 'string'],
            'capacity_required' => ['nullable', 'integer', 'min:1'],
            'sub_group_label' => ['nullable', 'string', 'max:20'],
            'lead_instructor_id' => ['nullable', 'integer'],
            'instructor_ids' => $allowEmptyResources ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'instructor_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('course_offering_instructors', 'user_id')
                    ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id)),
            ],
            // V4: ทุกกิจกรรมต้องเลือกกลุ่มนักศึกษา เพื่อให้ตรวจกลุ่มชนได้ตั้งแต่สร้างกิจกรรม
            'student_group_ids' => ['required', 'array', 'min:1'],
            'student_group_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('student_groups', 'id')
                    ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id)),
            ],
        ], [
            'end_date.after_or_equal' => 'วันที่สิ้นสุดต้องไม่อยู่ก่อนวันที่เริ่ม',
            'end_time.after' => 'เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่ม',
            'start_time.date_format' => 'กรุณาระบุเวลาเริ่มในรูปแบบ 24 ชั่วโมง เช่น 10:00',
            'end_time.date_format' => 'กรุณาระบุเวลาสิ้นสุดในรูปแบบ 24 ชั่วโมง เช่น 12:00',
            'topic.required' => 'กรุณาระบุหัวข้อกิจกรรม',
            'instructor_ids.required' => 'กรุณาเลือกผู้สอนอย่างน้อย 1 คน',
            'student_group_ids.required' => 'กรุณาเลือกกลุ่มนักศึกษาอย่างน้อย 1 กลุ่ม',
        ], [
            'start_date' => 'วันที่เริ่ม',
            'end_date' => 'วันที่สิ้นสุด',
            'start_time' => 'เวลาเริ่ม',
            'end_time' => 'เวลาสิ้นสุด',
            'activity_type_id' => 'ประเภทกิจกรรม',
            'room_id' => 'ห้อง/สถานที่',
            'topic' => 'หัวข้อ',
            'remark' => 'หมายเหตุ',
            'capacity_required' => 'จำนวนรองรับ',
            'sub_group_label' => 'ป้ายกลุ่มย่อย',
            'lead_instructor_id' => 'ผู้สอนหลัก',
            'instructor_ids' => 'ผู้สอน',
            'instructor_ids.*' => 'ผู้สอน',
            'student_group_ids' => 'กลุ่มนักศึกษา',
            'student_group_ids.*' => 'กลุ่มนักศึกษา',
        ]);

        $validated['instructor_ids'] = array_values(array_unique(array_map('intval', $validated['instructor_ids'] ?? [])));
        $validated['student_group_ids'] = array_values(array_unique(array_map('intval', $validated['student_group_ids'] ?? [])));

        if (
            CarbonImmutable::parse($validated['start_date'])->isSameDay(CarbonImmutable::parse($validated['end_date']))
            && $validated['end_time'] <= $validated['start_time']
        ) {
            throw ValidationException::withMessages([
                'end_time' => 'เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่มเมื่อจัดรายการในวันเดียวกัน',
            ]);
        }

        return $validated;
    }

    private function preserveSeriesInstanceTemplateFields(Schedule $schedule, array $validated): array
    {
        $schedule->loadMissing(['instructors']);

        $validated['activity_type_id'] = $schedule->activity_type_id;
        $validated['start_date'] = $schedule->start_date?->toDateString() ?? $schedule->teaching_date?->toDateString();
        $validated['end_date'] = $schedule->end_date?->toDateString() ?? $schedule->teaching_date?->toDateString();
        $validated['topic'] = $schedule->topic;
        $validated['capacity_required'] = $schedule->capacity_required;
        $validated['sub_group_label'] = $schedule->sub_group_label;
        return $validated;
    }

    private function validateScheduleSeries(Request $request, CourseOffering $courseOffering): array
    {
        if (! $request->boolean('use_custom_series_range')) {
            $request->merge([
                'starts_on' => null,
                'ends_on' => null,
            ]);
        }

        $this->assertScheduleThaiDateFields($request, [
            'starts_on' => 'วันที่เริ่มช่วงที่เลือกเอง',
            'ends_on' => 'วันที่สิ้นสุดช่วงที่เลือกเอง',
        ]);

        $defaultWeeks = max(1, (int) ($courseOffering->teaching_weeks ?? 1));
        $request->merge([
            'start_week' => $request->input('start_week', 1),
            'end_week' => $request->input('end_week', $defaultWeeks),
        ]);

        $validated = $request->validate($this->scheduleSeriesValidationRules($courseOffering) + [
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'remark' => ['nullable', 'string'],
            'lead_instructor_id' => ['nullable', 'integer'],
            'instructor_ids' => ['nullable', 'array'],
            'instructor_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('course_offering_instructors', 'user_id')
                    ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id)),
            ],
            'student_group_ids' => ['required', 'array', 'min:1'],
            'student_group_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('student_groups', 'id')
                    ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id)),
            ],
        ]);

        $validated['instructor_ids'] = array_values(array_unique(array_map('intval', $validated['instructor_ids'] ?? [])));
        $validated['student_group_ids'] = array_values(array_unique(array_map('intval', $validated['student_group_ids'] ?? [])));

        $this->assertScheduleSeriesTimeOrder($validated);

        return $validated;
    }

    private function validateScheduleSeriesTemplate(Request $request, CourseOffering $courseOffering): array
    {
        $this->assertScheduleThaiDateFields($request, [
            'starts_on' => 'วันที่เริ่มช่วงที่เลือกเอง',
            'ends_on' => 'วันที่สิ้นสุดช่วงที่เลือกเอง',
        ]);

        $validated = $request->validate($this->scheduleSeriesValidationRules($courseOffering));
        $this->assertScheduleSeriesTimeOrder($validated);

        return $validated;
    }

    private function scheduleSeriesValidationRules(CourseOffering $courseOffering): array
    {
        $maxWeeks = max(1, (int) ($courseOffering->teaching_weeks ?? 52));

        return [
            'weekday' => ['required', 'integer', 'between:1,7'],
            'start_week' => ['required', 'integer', 'min:1', "max:{$maxWeeks}"],
            'end_week' => ['required', 'integer', 'min:1', "max:{$maxWeeks}", 'gte:start_week'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'activity_type_id' => ['required', 'integer', 'exists:activity_types,id'],
            'topic' => ['required', 'string', 'max:255'],
            'capacity_required' => ['nullable', 'integer', 'min:1'],
            'sub_group_label' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function assertScheduleSeriesTimeOrder(array $validated): void
    {
        if (($validated['end_time'] ?? null) <= ($validated['start_time'] ?? null)) {
            throw ValidationException::withMessages([
                'end_time' => __('messages.end_time_after_start'),
            ]);
        }
    }

    private function assertScheduleWithinAcademicYear(CourseOffering $courseOffering, array $validated): void
    {
        $courseOffering->loadMissing('academicYear');
        $academicYear = $courseOffering->academicYear;

        if (! $academicYear?->start_date || ! $academicYear?->end_date) {
            return;
        }

        $scheduleStart = CarbonImmutable::parse($validated['start_date'])->startOfDay();
        $scheduleEnd = CarbonImmutable::parse($validated['end_date'])->startOfDay();
        $academicStart = CarbonImmutable::parse($academicYear->start_date)->startOfDay();
        $academicEnd = CarbonImmutable::parse($academicYear->end_date)->startOfDay();

        if ($scheduleStart->lt($academicStart) || $scheduleEnd->gt($academicEnd)) {
            throw ValidationException::withMessages([
                'schedule' => 'วันที่จัดรายการสอนต้องอยู่ในช่วงปีการศึกษาของรายวิชา ('
                    . ThaiDate::formatForInput($academicYear->start_date)
                    . ' - '
                    . ThaiDate::formatForInput($academicYear->end_date)
                    . ')',
            ]);
        }
    }

    /**
     * V2: บล็อกการจัดกิจกรรมในช่วงสัปดาห์สอบ / ปิดภาคเรียน (วันที่ไม่มีเทอมคลุม)
     * — เฉพาะปีที่ตั้งเทอมแล้ว (ปียังไม่ตั้งเทอม → ไม่บล็อก)
     */
    private function assertScheduleNotOnBlockedDay(CourseOffering $courseOffering, array $validated): void
    {
        $courseOffering->loadMissing('academicYear');
        $reason = AcademicCalendar::forYear($courseOffering->academicYear)
            ->blockReasonForRange($validated['start_date'], $validated['end_date'] ?? $validated['start_date']);

        if ($reason) {
            throw ValidationException::withMessages(['schedule' => [$reason['message']]]);
        }
    }

    private function assertSelectedGroupsFitCapacity(CourseOffering $courseOffering, array $validated): void
    {
        $capacity = $validated['capacity_required'] ?? null;

        if (! $capacity) {
            return;
        }

        $studentGroupIds = $validated['student_group_ids'] ?? [];
        if (empty($studentGroupIds)) {
            return;
        }

        $selectedStudentCount = (int) $courseOffering->studentGroups()
            ->whereIn('id', array_map('intval', $studentGroupIds))
            ->sum('student_count');

        if ($selectedStudentCount > (int) $capacity) {
            throw ValidationException::withMessages([
                'capacity_required' => "จำนวนผู้เรียนที่เลือก ({$selectedStudentCount} คน) เกินจำนวนที่รองรับ ({$capacity} คน)",
            ]);
        }
    }

    private function assertLeadInstructorSelected(array $validated): void
    {
        $leadId = $validated['lead_instructor_id'] ?? null;
        if (! $leadId) {
            return;
        }

        if (! in_array((int) $leadId, array_map('intval', $validated['instructor_ids']), true)) {
            throw ValidationException::withMessages([
                'lead_instructor_id' => 'ผู้สอนหลักต้องอยู่ในรายชื่อผู้สอนที่เลือก',
            ]);
        }
    }

    private function selectedInstructorDepartmentWarnings(CourseOffering $courseOffering, array $instructorIds): array
    {
        $courseOffering->loadMissing(['course.department']);
        $departmentId = $courseOffering->course?->department_id;
        $selectedIds = array_values(array_unique(array_map('intval', $instructorIds)));

        if (! $departmentId || $selectedIds === []) {
            return [];
        }

        $outside = $courseOffering->instructorPool()
            ->with('instructorProfile.department')
            ->whereIn('users.id', $selectedIds)
            ->get()
            ->filter(fn ($instructor) => (int) ($instructor->instructorProfile?->department_id) !== (int) $departmentId)
            ->values();

        if ($outside->isEmpty()) {
            return [];
        }

        $courseDepartment = $courseOffering->course?->department?->name ?? 'ภาควิชาของรายวิชา';
        $names = $outside
            ->map(function ($instructor) {
                $department = $instructor->instructorProfile?->department?->name;
                $name = $instructor->formatted_name ?? $instructor->name;

                return $department ? "{$name} ({$department})" : $name;
            })
            ->implode(', ');

        return [
            "ผู้สอนต่างภาควิชา: {$names} ไม่ได้สังกัด {$courseDepartment} ระบบอนุญาตให้บันทึกได้ แต่จะแสดงเป็นข้อเตือน",
        ];
    }

    private function detectConflicts(
        ScheduleConflictChecker $conflictChecker,
        array $validated,
        ?int $ignoreScheduleId = null
    ): array {
        return $conflictChecker->check(
            [
                'course_offering_id' => $validated['course_offering_id'] ?? null,
                ...Arr::only($validated, ['activity_type_id', 'start_date', 'end_date', 'start_time', 'end_time', 'room_id', 'sub_group_label']),
            ],
            array_map('intval', $validated['instructor_ids'] ?? []),
            array_map('intval', $validated['student_group_ids'] ?? []),
            $ignoreScheduleId
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $conflicts
     */
    private function assertNoBlockingScheduleConflicts(array $conflicts): void
    {
        $messages = collect($conflicts)
            ->pluck('message')
            ->unique()
            ->values()
            ->all();

        if ($messages === []) {
            return;
        }

        throw ValidationException::withMessages([
            'schedule' => $messages,
        ]);
    }

    private function fieldForScheduleConflict(string $type): string
    {
        return match ($type) {
            'instructor_overlap' => 'instructor_ids',
            'room_overlap' => 'room_id',
            'group_overlap' => 'student_group_ids',
            default => 'schedule',
        };
    }

    private function syncInstructors(Schedule $schedule, array $validated): void
    {
        $leadId = isset($validated['lead_instructor_id']) ? (int) $validated['lead_instructor_id'] : null;
        $payload = collect($validated['instructor_ids'] ?? [])
            ->mapWithKeys(fn ($id) => [
                (int) $id => ['is_lead' => $leadId ? (int) $id === $leadId : false],
            ])
            ->all();

        $schedule->instructors()->sync($payload);
    }

    /**
     * Build a flat, serializable snapshot of a schedule for audit logging.
     * Relations must be loaded by caller before calling this.
     */
    private function scheduleSnapshot(Schedule $schedule): array
    {
        $co   = $schedule->courseOffering;
        $year = $co?->academicYear;

        $instructors = $schedule->relationLoaded('instructors')
            ? $schedule->instructors->map(fn ($u) => [
                'id'      => $u->id,
                'name'    => $u->name,
                'is_lead' => (bool) $u->pivot?->is_lead,
            ])->toArray()
            : [];

        $leadInstructor = collect($instructors)->firstWhere('is_lead', true);

        $groups = $schedule->relationLoaded('studentGroups')
            ? $schedule->studentGroups->map(fn ($g) => [
                'id'         => $g->id,
                'group_code' => $g->group_code,
            ])->toArray()
            : [];

        return [
            'course_offering_id' => $co?->id,
            'course_code'        => $co?->course?->course_code,
            'course_name_th'     => $co?->course?->name_th,
            'academic_year'      => $year?->name,
            'start_date'         => $schedule->start_date?->toDateString(),
            'end_date'           => $schedule->end_date?->toDateString(),
            'start_time'         => (string) $schedule->start_time,
            'end_time'           => (string) $schedule->end_time,
            'activity_type'      => $schedule->activityType?->name,
            'room'               => $schedule->room?->room_code,
            'topic'              => $schedule->topic,
            'capacity_required'  => $schedule->capacity_required,
            'sub_group_label'    => $schedule->sub_group_label,
            'remark'             => $schedule->remark,
            'instructors'        => $instructors,
            'lead_instructor'    => $leadInstructor ? ['id' => $leadInstructor['id'], 'name' => $leadInstructor['name']] : null,
            'student_groups'     => $groups,
        ];
    }
}
