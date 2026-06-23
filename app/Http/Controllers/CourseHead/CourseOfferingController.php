<?php

namespace App\Http\Controllers\CourseHead;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\CourseOfferingApproval;
use App\Models\CourseRole;
use App\Models\StudentCohort;
use App\Models\StudentGroup;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NavigationBadgeService;
use App\Services\ReferenceDataCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourseOfferingController extends Controller
{
    private const AUDIT_CATEGORY = 'รายวิชาและผู้รับผิดชอบ';

    public function index(Request $request): View
    {
        $coordinatorId = Auth::id();

        $availableYears = \App\Models\AcademicYear::query()
            ->whereIn('id', CourseOffering::query()
                ->withActiveCourse()
                ->where('coordinator_id', $coordinatorId)
                ->select('academic_year_id'))
            ->orderByDesc('name')
            ->get();

        $activeYear = \App\Models\AcademicYear::where('is_active', true)->first();
        $schedulingYear = $availableYears->firstWhere('phase', 'scheduling');
        $defaultYearId = $request->integer('year')
            ?: ($schedulingYear?->id
                ?? ($activeYear && $availableYears->contains('id', $activeYear->id) ? $activeYear->id : $availableYears->first()?->id));

        $offerings = CourseOffering::query()
            ->select('course_offerings.*')
            ->distinct()
            ->with(['course.curriculum', 'course.department', 'academicYear'])
            ->withCount(['instructorPool'])
            ->withActiveCourse()
            ->where('coordinator_id', $coordinatorId)
            ->when($defaultYearId, fn ($q) => $q->where('academic_year_id', $defaultYearId))
            ->latest('updated_at')
            ->get();

        $summary = [
            'total'     => $offerings->count(),
            'draft'     => $offerings->where('approval_status', 'draft')->count(),
            'pending'   => $offerings->where('approval_status', 'pending')->count(),
            'published' => $offerings->where('approval_status', 'published')->count(),
            'rejected'  => $offerings->where('approval_status', 'rejected')->count(),
        ];

        return view('course_head.course_offerings.index', [
            'offerings'       => $offerings,
            'availableYears'  => $availableYears,
            'selectedYearId'  => $defaultYearId,
            'summary'         => $summary,
            'coordinatorEmptyStateKey' => \App\Support\CoordinatorEmptyState::forCoordinator((int) $coordinatorId),
        ]);
    }

    public function show(Request $request, CourseOffering $courseOffering): View
    {
        $this->authorizeCourseHeadOffering($courseOffering);

        $courseOffering->load([
            'course.curriculum',
            'course.department',
            'academicYear',
            'coordinator',
            'instructorPool.instructorProfile.department',
            'studentGroups.cohortGroup.parent',
        ])->loadCount('schedules');
        $courseOffering->setRelation('instructorPool', $courseOffering->instructorPool
            ->sort(function (User $left, User $right) use ($courseOffering): int {
                $leftIsCoordinator = (int) $left->id === (int) $courseOffering->coordinator_id;
                $rightIsCoordinator = (int) $right->id === (int) $courseOffering->coordinator_id;

                if ($leftIsCoordinator !== $rightIsCoordinator) {
                    return $leftIsCoordinator ? -1 : 1;
                }

                return strnatcasecmp(
                    (string) ($left->formatted_name ?? $left->name ?? ''),
                    (string) ($right->formatted_name ?? $right->name ?? '')
                );
            })
            ->values());

        $availableInstructors = User::query()
            ->with('instructorProfile.department')
            ->where('is_active', true)
            ->whereHas('instructorProfile')
            ->whereHas('roles', fn ($q) => $q->whereIn('role', ['instructor', 'course_head']))
            ->orderBy('name')
            ->get();

        // Roles ที่เลือกได้ในชุดผู้สอน — ซ่อน "หัวหน้าวิชา" (auto-assigned ให้ coordinator)
        $courseRoles = app(ReferenceDataCache::class)
            ->courseRoles()
            ->filter(fn ($role) => $role->name_th !== 'หัวหน้าวิชา')
            ->values();

        $cohortAcademicYearId = $courseOffering->academic_year_id;
        $cohortsHaveAcademicYear = Schema::hasColumn('student_cohorts', 'academic_year_id');
        $availableCohortGroups = StudentCohort::query()
            ->with('parent')
            ->where('curriculum_id', $courseOffering->course?->curriculum_id)
            ->when($cohortsHaveAcademicYear, fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('academic_year_id')
                ->when($cohortAcademicYearId, fn ($q) => $q->orWhere('academic_year_id', $cohortAcademicYearId))))
            ->when(
                $courseOffering->course?->default_year_level,
                fn ($query, $yearLevel) => $query->where('year_level', $yearLevel)
            )
            ->when($cohortsHaveAcademicYear && $cohortAcademicYearId, fn ($query) => $query
                ->orderByRaw('CASE WHEN academic_year_id = ? THEN 0 ELSE 1 END', [$cohortAcademicYearId]))
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('code')
            ->get();

        return view('course_head.course_offerings.show', [
            'courseOffering' => $courseOffering,
            'availableInstructors' => $availableInstructors,
            'availableCohortGroups' => $availableCohortGroups,
            'safeReturnToSchedule' => $this->safeReturnToSchedule($request),
            'courseRoles' => $courseRoles,
            'teachingWeeks' => (int) SystemSetting::get('teaching_load_weeks', 39),
        ]);
    }

    /**
     * M11 Task17 — หัวหน้าวิชาส่งรายวิชาขออนุมัติ (draft/rejected → pending) + ล็อก Draft
     */
    public function submitForApproval(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $courseOffering->loadMissing('course', 'academicYear');

        // ต้องอยู่ในช่วงจัดตาราง (แต่ไม่ใช้ requireSchedulingPhase เพราะ pending จะโดน lock เอง)
        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return back()->with('error', 'ยังไม่เปิดช่วงจัดตาราง — ส่งขออนุมัติไม่ได้');
        }

        // ส่งได้เฉพาะ draft หรือ rejected (ส่งใหม่หลังถูกตีกลับ)
        if (! in_array($courseOffering->approval_status, ['draft', 'rejected'], true)) {
            return back()->with('error', 'รายวิชานี้ส่งขออนุมัติไปแล้ว ไม่สามารถส่งซ้ำได้');
        }

        // ต้องมีกิจกรรมอย่างน้อย 1 รายการก่อนส่ง
        if (! $courseOffering->schedules()->exists()) {
            return back()->with('error', 'ยังไม่มีกิจกรรมในตาราง — ต้องจัดตารางอย่างน้อย 1 รายการก่อนส่งขออนุมัติ');
        }

        $from = $courseOffering->approval_status;

        DB::transaction(function () use ($courseOffering, $from) {
            $courseOffering->update([
                'approval_status'  => 'pending',
                'rejection_reason' => null,
            ]);

            CourseOfferingApproval::create([
                'course_offering_id' => $courseOffering->id,
                'actor_user_id'      => Auth::id(),
                'action'             => 'submit',
                'from_status'        => $from,
                'to_status'          => 'pending',
            ]);

            $this->notifyExecutives($courseOffering);
        });

        AuditLogger::log(
            action: 'การอนุมัติ.ส่ง',
            table: 'course_offerings',
            recordId: $courseOffering->id,
            oldValues: ['approval_status' => $from],
            newValues: ['approval_status' => 'pending'],
            category: 'การอนุมัติ',
            description: "ส่งขออนุมัติรายวิชา {$this->offeringCourseLabel($courseOffering)}",
        );

        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        return redirect()->to(route('maker.course_offerings.show', $courseOffering))
            ->with('success', 'ส่งขออนุมัติเรียบร้อยแล้ว — รออนุมัติจากผู้บริหาร');
    }

    /** M11 — แจ้งเตือนผู้บริหารทุกคน (active) ว่ามีรายวิชารออนุมัติ */
    private function notifyExecutives(CourseOffering $courseOffering): void
    {
        $execIds = DB::table('user_roles')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->where('user_roles.role', 'executive')
            ->where('users.is_active', true)
            ->pluck('user_roles.user_id')
            ->unique();

        if ($execIds->isEmpty()) {
            return;
        }

        $label = $this->offeringCourseLabel($courseOffering);
        $rows = $execIds->map(fn ($uid) => [
            'user_id'            => $uid,
            'course_offering_id' => $courseOffering->id,
            'type'               => 'approval_update',
            'message'            => "มีรายวิชารออนุมัติ: {$label}",
            'is_read'            => false,
            'created_at'         => now(),
        ])->all();

        DB::table('notifications')->insert($rows);
    }

    public function storeInstructor(Request $request, CourseOffering $courseOffering): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'instructors')) return $redirect;
        $courseOffering->loadMissing('course');

        $validated = $request->validate([
            'user_id'        => ['required', 'integer', 'exists:users,id'],
            'course_role_id' => ['nullable', 'integer', 'exists:course_roles,id'],
            'note'           => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::with('instructorProfile.department')->find($validated['user_id']);

        if (! $user || ! $user->is_active || ! $user->instructorProfile) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'เลือกได้เฉพาะอาจารย์ที่ยังใช้งานอยู่และมีข้อมูลโปรไฟล์อาจารย์'], 422);
            }
            return $this->redirectToInstructors($courseOffering)
                ->withErrors(['user_id' => 'เลือกได้เฉพาะอาจารย์ที่ยังใช้งานอยู่และมีข้อมูลโปรไฟล์อาจารย์'])
                ->withInput();
        }

        $departmentWarning = null;
        if (
            $courseOffering->course?->department_id
            && (int) $user->instructorProfile->department_id !== (int) $courseOffering->course->department_id
        ) {
            $departmentWarning = 'อาจารย์คนนี้อยู่ต่างภาควิชาของรายวิชา ระบบอนุญาตให้เพิ่มได้และจะแสดงเป็นข้อเตือน';
        }

        if ($courseOffering->instructorPool()->where('users.id', $user->id)->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'อาจารย์คนนี้อยู่ในชุดผู้สอนของรายวิชานี้แล้ว'], 422);
            }
            return $this->redirectToInstructors($courseOffering)
                ->withErrors(['user_id' => 'อาจารย์คนนี้อยู่ในชุดผู้สอนของรายวิชานี้แล้ว'])
                ->withInput();
        }

        $roleId = $validated['course_role_id'] ?? CourseRole::where('name_th', 'อาจารย์ผู้สอน')->value('id');

        // ทำนาย pool หลังเพิ่ม → ถ้าผลลัพธ์ต่างจากแม่แบบและยังไม่มีเหตุผล จะ throw ขอ note ก่อน mutate
        $predicted = $this->currentPoolMap($courseOffering);
        $predicted[(int) $user->id] = $roleId ? (int) $roleId : null;
        $note = $this->resolveInstructorPoolNote($request, $courseOffering, $predicted);

        $courseOffering->instructorPool()->attach($user->id, [
            'role_in_course' => 'instructor',
            'course_role_id' => $roleId,
        ]);
        $courseOffering->update(['instructor_pool_note' => $note]);
        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        $this->logCourseManagementCreate(
            table: 'course_offering_instructors',
            recordId: $courseOffering->id,
            newValues: $this->offeringInstructorAuditValues($courseOffering, $user, $roleId, 'instructor') + ['note' => $note],
            description: "เพิ่มผู้สอนในรายวิชา {$this->offeringCourseLabel($courseOffering)}",
        );

        if ($request->expectsJson()) {
            $role = $roleId ? CourseRole::find($roleId) : null;
            return response()->json([
                'id'             => $user->id,
                'name'           => $user->formatted_name,
                'department'     => $user->instructorProfile?->department?->name ?? '-',
                'department_id'  => $user->instructorProfile?->department_id,
                'course_role_id' => $roleId,
                'role_name'      => $role?->name_th,
                'is_coordinator' => false,
                'can_schedule'   => false,
                'warning'        => $departmentWarning,
            ]);
        }

        $redirect = back()->with('success', 'เพิ่มอาจารย์ในรายวิชาเรียบร้อยแล้ว');

        return $departmentWarning
            ? $redirect->with('warning', $departmentWarning)
            : $redirect;
    }

    public function updateInstructorRole(Request $request, CourseOffering $courseOffering, User $user): JsonResponse|RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'instructors')) return $redirect;

        $validated = $request->validate([
            'course_role_id' => ['nullable', 'integer', 'exists:course_roles,id'],
            'note'           => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $courseOffering->instructorPool()->where('users.id', $user->id)->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'อาจารย์คนนี้ไม่อยู่ในชุดผู้สอน'], 422);
            }
            return $this->redirectToInstructors($courseOffering)
                ->withErrors(['user_id' => 'อาจารย์คนนี้ไม่อยู่ในชุดผู้สอน']);
        }

        $currentInstructor = $courseOffering->instructorPool()
            ->where('users.id', $user->id)
            ->first();
        $oldRoleId = $currentInstructor?->pivot?->course_role_id ? (int) $currentInstructor->pivot->course_role_id : null;
        $newRoleId = isset($validated['course_role_id']) ? (int) $validated['course_role_id'] : null;

        // ทำนาย pool หลังเปลี่ยนบทบาท → ขอ note ถ้าผลต่างจากแม่แบบและยังไม่มีเหตุผล
        $predicted = $this->currentPoolMap($courseOffering);
        if (array_key_exists((int) $user->id, $predicted)) {
            $predicted[(int) $user->id] = $newRoleId;
        }
        $note = $this->resolveInstructorPoolNote($request, $courseOffering, $predicted);

        $courseOffering->instructorPool()->updateExistingPivot($user->id, [
            'course_role_id' => $newRoleId,
        ]);
        $courseOffering->update(['instructor_pool_note' => $note]);
        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        if ($oldRoleId !== $newRoleId) {
            $this->logCourseManagementUpdate(
                table: 'course_offering_instructors',
                recordId: $courseOffering->id,
                oldValues: $this->offeringInstructorAuditValues($courseOffering, $user, $oldRoleId, 'instructor'),
                newValues: $this->offeringInstructorAuditValues($courseOffering, $user, $newRoleId, 'instructor') + ['note' => $note],
                description: "เปลี่ยนบทบาทผู้สอนในรายวิชา {$this->offeringCourseLabel($courseOffering)}",
            );
        }

        if ($request->expectsJson()) {
            $role = $newRoleId ? CourseRole::find($newRoleId) : null;
            return response()->json([
                'ok'             => true,
                'course_role_id' => $newRoleId,
                'role_name'      => $role?->name_th,
            ]);
        }

        return back()->with('success', 'อัปเดตบทบาทเรียบร้อยแล้ว');
    }

    /**
     * V2 delegation: หัวหน้าวิชา toggle สิทธิ์ "ให้ช่วยจัดตาราง" ของอาจารย์ในชุดผู้สอน
     * (schedule_permission: 'view' = ดูอย่างเดียว · 'schedule' = ช่วยจัดตาราง offering นี้ได้)
     */
    public function updateInstructorPermission(Request $request, CourseOffering $courseOffering, User $user): JsonResponse|RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'instructors')) return $redirect;

        $validated = $request->validate([
            'schedule_permission' => ['required', 'in:view,schedule'],
        ]);

        if ((int) $courseOffering->coordinator_id === (int) $user->id) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'หัวหน้าวิชาจัดตารางได้อยู่แล้ว ไม่ต้องมอบหมาย'], 422);
            }
            return $this->redirectToInstructors($courseOffering)
                ->withErrors(['instructor_pool' => 'หัวหน้าวิชาจัดตารางได้อยู่แล้ว ไม่ต้องมอบหมาย']);
        }

        $currentInstructor = $courseOffering->instructorPool()->where('users.id', $user->id)->first();
        if (! $currentInstructor) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'อาจารย์คนนี้ไม่อยู่ในชุดผู้สอน'], 422);
            }
            return $this->redirectToInstructors($courseOffering)
                ->withErrors(['user_id' => 'อาจารย์คนนี้ไม่อยู่ในชุดผู้สอน']);
        }

        $oldPermission = $currentInstructor->pivot->schedule_permission ?? 'view';
        $newPermission = $validated['schedule_permission'];

        $courseOffering->instructorPool()->updateExistingPivot($user->id, [
            'schedule_permission' => $newPermission,
        ]);
        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        if ($oldPermission !== $newPermission) {
            $label = $newPermission === 'schedule' ? 'มอบหมายให้ช่วยจัดตาราง' : 'ยกเลิกสิทธิ์ช่วยจัดตาราง';
            $this->logCourseManagementUpdate(
                table: 'course_offering_instructors',
                recordId: $courseOffering->id,
                oldValues: ['schedule_permission' => $oldPermission],
                newValues: ['schedule_permission' => $newPermission],
                description: "{$label} ({$user->name}) ในรายวิชา {$this->offeringCourseLabel($courseOffering)}",
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'schedule_permission' => $newPermission]);
        }

        return back()->with('success', 'อัปเดตสิทธิ์ช่วยจัดตารางเรียบร้อยแล้ว');
    }

    public function destroyInstructor(Request $request, CourseOffering $courseOffering, User $user): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'instructors')) return $redirect;

        if ((int) $courseOffering->coordinator_id === (int) $user->id) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'ไม่สามารถนำหัวหน้าวิชาหลักออกจากชุดผู้สอนได้'], 422);
            }
            return $this->redirectToInstructors($courseOffering)
                ->withErrors(['instructor_pool' => 'ไม่สามารถนำหัวหน้าวิชาหลักออกจากชุดผู้สอนได้']);
        }

        $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $currentInstructor = $courseOffering->instructorPool()
            ->where('users.id', $user->id)
            ->first();
        $oldRoleId = $currentInstructor?->pivot?->course_role_id ? (int) $currentInstructor->pivot->course_role_id : null;

        // ทำนาย pool หลังนำออก → ขอ note ถ้าผลต่างจากแม่แบบและยังไม่มีเหตุผล (ก่อน detach)
        $predicted = $this->currentPoolMap($courseOffering);
        unset($predicted[(int) $user->id]);
        $note = $this->resolveInstructorPoolNote($request, $courseOffering, $predicted);

        $detached = $courseOffering->instructorPool()->detach($user->id);
        if ($detached > 0) {
            $courseOffering->update(['instructor_pool_note' => $note]);
        }
        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        if ($detached > 0) {
            $this->logCourseManagementDelete(
                table: 'course_offering_instructors',
                recordId: $courseOffering->id,
                oldValues: $this->offeringInstructorAuditValues($courseOffering, $user, $oldRoleId, $currentInstructor?->pivot?->role_in_course ?? 'instructor') + ['note' => $note],
                newValues: $this->offeringAuditContext($courseOffering),
                description: "ลบผู้สอนออกจากรายวิชา {$this->offeringCourseLabel($courseOffering)}",
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'ลบอาจารย์ออกจากชุดผู้สอนเรียบร้อยแล้ว');
    }

    /**
     * แผนผังบทบาทของชุดผู้สอนในแม่แบบรายวิชา (ตัดหัวหน้าวิชาออก) — [userId => roleId|null]
     */
    private function templatePoolMap(?\App\Models\Course $course): array
    {
        if (! $course) {
            return [];
        }
        $coordId = (int) ($course->head_instructor_id ?? 0);

        return $course->instructors()->get()
            ->reject(fn ($u) => (int) $u->id === $coordId)
            ->mapWithKeys(fn ($u) => [(int) $u->id => ((int) ($u->pivot->course_role_id ?? 0)) ?: null])
            ->all();
    }

    /**
     * แผนผังบทบาทของชุดผู้สอนปัจจุบันใน offering (ตัดหัวหน้าวิชาออก) — [userId => roleId|null]
     */
    private function currentPoolMap(CourseOffering $courseOffering): array
    {
        $coordId = (int) ($courseOffering->coordinator_id ?? 0);

        return $courseOffering->instructorPool()->get()
            ->reject(fn ($u) => (int) $u->id === $coordId)
            ->mapWithKeys(fn ($u) => [(int) $u->id => ((int) ($u->pivot->course_role_id ?? 0)) ?: null])
            ->all();
    }

    /**
     * เทียบ pool ที่ทำนายไว้กับแม่แบบ — true = ต่างจากแม่แบบ (deviate)
     *
     * @param  array<int, int|null>  $template
     * @param  array<int, int|null>  $actual
     */
    private function poolMapDeviates(array $template, array $actual): bool
    {
        if (count($template) !== count($actual)) {
            return true;
        }
        foreach ($template as $id => $role) {
            if (! array_key_exists($id, $actual) || $actual[$id] !== $role) {
                return true;
            }
        }

        return false;
    }

    /**
     * ตัดสินค่า instructor_pool_note ที่จะเซฟตามผล deviation หลังการเปลี่ยน pool
     *  - ไม่ต่างจากแม่แบบ → null (เคลียร์เหตุผลเดิม)
     *  - ต่าง + มีเหตุผล (จาก request หรือที่เคยกรอกไว้) → ใช้เหตุผลนั้น (ไม่ต้องกรอกซ้ำ)
     *  - ต่าง + ยังไม่มีเหตุผล → throw ValidationException (errors.note) — caller ยังไม่ mutate
     *
     * @param  array<int, int|null>  $predictedActual
     */
    private function resolveInstructorPoolNote(Request $request, CourseOffering $courseOffering, array $predictedActual): ?string
    {
        $courseOffering->loadMissing('course');
        $template = $this->templatePoolMap($courseOffering->course);

        // วิชาที่ยังไม่ได้ตั้งแม่แบบผู้สอน → ไม่มีฐานให้เทียบ → ไม่บังคับเหตุผล (เก็บได้ถ้ากรอกมา)
        if ($template === []) {
            $provided = trim((string) $request->input('note', ''));
            return $provided !== '' ? $provided : null;
        }

        if (! $this->poolMapDeviates($template, $predictedActual)) {
            return null;
        }

        $provided = trim((string) $request->input('note', ''));
        if ($provided !== '') {
            return $provided;
        }

        $existing = trim((string) ($courseOffering->instructor_pool_note ?? ''));
        if ($existing !== '') {
            return (string) $courseOffering->instructor_pool_note;
        }

        throw ValidationException::withMessages([
            'note' => 'กรุณาระบุเหตุผลที่ชุดผู้สอนต่างจากแม่แบบรายวิชา',
        ]);
    }

    public function storeStudentGroup(Request $request, CourseOffering $courseOffering): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'student-groups')) return $redirect;

        $validated = $request->validate(
            $this->studentGroupRules($courseOffering),
            $this->studentGroupValidationMessages()
        );

        $cohort = $this->eligibleCohortGroup($courseOffering, (int) $validated['cohort_group_id']);
        $this->assertCohortGroupHasRoom($courseOffering, $cohort, (int) $validated['student_count']);

        $studentGroup = $courseOffering->studentGroups()->create($validated);
        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        $this->logCourseManagementCreate(
            table: 'student_groups',
            recordId: $studentGroup->id,
            newValues: $this->studentGroupAuditValues($courseOffering, $studentGroup),
            description: "สร้างกลุ่มนักศึกษา {$studentGroup->group_code} ใน {$this->offeringCourseLabel($courseOffering)}",
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'เพิ่มกลุ่มนักศึกษาเรียบร้อยแล้ว',
                'group' => $this->studentGroupResponse($studentGroup->fresh('cohortGroup.parent')),
            ], 201);
        }

        return $this->redirectToStudentGroups($courseOffering)->with('success', 'เพิ่มกลุ่มนักศึกษาเรียบร้อยแล้ว');
    }

    public function bulkStoreStudentGroups(Request $request, CourseOffering $courseOffering): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'student-groups')) return $redirect;

        $validated = $request->validate([
            'cohort_group_id' => ['required', 'integer'],
            'group_count' => ['required', 'integer', 'min:1', 'max:100'],
            'group_details' => ['nullable', 'array', 'max:100'],
            'group_details.*.group_code' => ['required_with:group_details', 'string', 'max:255'],
            'group_details.*.student_count' => ['required_with:group_details', 'integer', 'min:1', 'max:9999'],
            'group_details.*.color_code' => ['nullable', 'string', 'max:10'],
            'rebalance_existing' => ['nullable', 'boolean'],
        ], [
            'cohort_group_id.required' => 'กรุณาเลือกกลุ่มต้นทางจาก Master Data',
            'group_count.required' => 'กรุณาระบุจำนวนกลุ่ม',
            'group_details.*.group_code.required_with' => 'กรุณาระบุชื่อกลุ่ม',
            'group_details.*.student_count.required_with' => 'กรุณาระบุจำนวนนักศึกษา',
        ]);

        $cohort = $this->eligibleCohortGroup($courseOffering, (int) $validated['cohort_group_id']);
        $groupCount = (int) $validated['group_count'];
        $rebalanceExisting = $request->boolean('rebalance_existing');
        $colors = ['#2563eb', '#16a34a', '#d97706', '#dc2626', '#7c3aed', '#0891b2'];

        $existingCohortGroups = StudentGroup::query()
            ->where('course_offering_id', $courseOffering->id)
            ->where('cohort_group_id', $cohort->id)
            ->orderBy('group_code')
            ->get();

        $details = collect($validated['group_details'] ?? [])->take($groupCount)->values();
        if ($details->count() !== $groupCount) {
            $details = $this->defaultStudentGroupDetails(
                $cohort,
                $groupCount,
                $existingCohortGroups,
                $rebalanceExisting,
                $colors
            );
        }

        $groupCodes = $details->pluck('group_code')->map(fn ($code) => trim((string) $code));
        if ($groupCodes->contains('')) {
            throw ValidationException::withMessages(['group_details' => 'กรุณาระบุชื่อกลุ่มให้ครบทุกแถว']);
        }
        if ($groupCodes->unique()->count() !== $groupCodes->count()) {
            throw ValidationException::withMessages(['group_details' => 'ชื่อกลุ่มซ้ำกัน กรุณาตรวจสอบอีกครั้ง']);
        }

        $duplicateCodes = StudentGroup::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('group_code', $groupCodes->all())
            ->pluck('group_code')
            ->all();

        if ($duplicateCodes !== []) {
            throw ValidationException::withMessages([
                'group_details' => 'มีชื่อกลุ่มนี้อยู่แล้วในรายวิชา: ' . implode(', ', $duplicateCodes),
            ]);
        }

        $newTotal = (int) $details->sum(fn ($row) => (int) $row['student_count']);
        $limit = $rebalanceExisting
            ? (int) $cohort->student_count
            : $this->remainingCohortStudentCount($courseOffering, $cohort);

        if ($newTotal > $limit) {
            throw ValidationException::withMessages([
                'group_details' => "จำนวนนักศึกษาเกินกลุ่มต้นทาง {$cohort->code} ที่เหลือ {$limit} คน",
            ]);
        }

        $createdGroups = DB::transaction(function () use (
            $courseOffering,
            $cohort,
            $details,
            $rebalanceExisting,
            $existingCohortGroups,
            $colors
        ) {
            if ($rebalanceExisting) {
                $allCount = $existingCohortGroups->count() + $details->count();
                $balanced = $this->balancedCounts((int) $cohort->student_count, $allCount);

                $existingCohortGroups->values()->each(function (StudentGroup $group, int $index) use ($balanced) {
                    $group->update(['student_count' => $balanced[$index]]);
                });

                $details = $details->values()->map(function ($row, int $index) use ($balanced, $existingCohortGroups) {
                    $row['student_count'] = $balanced[$existingCohortGroups->count() + $index];
                    return $row;
                });
            }

            return $details->values()->map(fn ($row, int $index) => $courseOffering->studentGroups()->create([
                'cohort_group_id' => $cohort->id,
                'group_code' => trim((string) $row['group_code']),
                'student_count' => (int) $row['student_count'],
                'color_code' => $row['color_code'] ?: $colors[$index % count($colors)],
            ]));
        });

        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);
        $this->logCourseManagementCreate(
            table: 'student_groups',
            recordId: $courseOffering->id,
            newValues: $this->bulkStudentGroupAuditValues($courseOffering, $createdGroups),
            description: "สร้างกลุ่มนักศึกษา {$createdGroups->count()} กลุ่ม ใน {$this->offeringCourseLabel($courseOffering)}",
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "สร้างกลุ่มนักศึกษา {$createdGroups->count()} กลุ่มเรียบร้อยแล้ว",
                'groups' => $createdGroups
                    ->map(fn (StudentGroup $group) => $this->studentGroupResponse($group->fresh('cohortGroup.parent')))
                    ->values(),
            ], 201);
        }

        return $this->redirectToStudentGroups($courseOffering)->with('success', "สร้างกลุ่มนักศึกษา {$createdGroups->count()} กลุ่มเรียบร้อยแล้ว");
    }

    public function updateStudentGroup(Request $request, CourseOffering $courseOffering, StudentGroup $studentGroup): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'student-groups')) return $redirect;
        $this->assertStudentGroupBelongsToOffering($courseOffering, $studentGroup);

        $validated = $request->validate(
            $this->studentGroupRules($courseOffering, $studentGroup),
            $this->studentGroupValidationMessages()
        );
        $cohort = $this->eligibleCohortGroup($courseOffering, (int) $validated['cohort_group_id']);
        $this->assertCohortGroupHasRoom($courseOffering, $cohort, (int) $validated['student_count'], $studentGroup);

        $auditBefore = $this->studentGroupAuditValues($courseOffering, $studentGroup);
        $studentGroup->update($validated);
        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);
        $auditAfter = $this->studentGroupAuditValues($courseOffering, $studentGroup->fresh('cohortGroup.parent'));
        $diff = AuditLogger::diff($auditBefore, $auditAfter);

        $this->logCourseManagementUpdate(
            table: 'student_groups',
            recordId: $studentGroup->id,
            oldValues: $diff['old'],
            newValues: $diff['new'] + $this->offeringAuditContext($courseOffering),
            description: "แก้ไขกลุ่มนักศึกษา {$studentGroup->group_code} ใน {$this->offeringCourseLabel($courseOffering)}",
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => 'บันทึกแล้ว', 'group' => $this->studentGroupResponse($studentGroup)]);
        }

        return $this->redirectToStudentGroups($courseOffering)->with('success', 'อัปเดตกลุ่มนักศึกษาเรียบร้อยแล้ว');
    }

    public function saveStudentGroups(Request $request, CourseOffering $courseOffering): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'student-groups')) return $redirect;

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:150'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.cohort_group_id' => ['required', 'integer'],
            'rows.*.group_code' => ['required', 'string', 'max:255'],
            'rows.*.student_count' => ['required', 'integer', 'min:1', 'max:9999'],
            'rows.*.color_code' => ['nullable', 'string', 'max:10'],
        ], [
            'rows.required' => 'กรุณาเพิ่มกลุ่มนักศึกษาก่อนบันทึก',
            'rows.*.cohort_group_id.required' => 'กรุณาเลือกกลุ่มต้นทาง',
            'rows.*.group_code.required' => 'กรุณากรอกชื่อกลุ่ม',
            'rows.*.student_count.required' => 'กรุณาระบุจำนวนนักศึกษา',
        ]);

        $rows = collect($validated['rows'])
            ->map(fn (array $row) => [
                'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
                'cohort_group_id' => (int) $row['cohort_group_id'],
                'group_code' => trim((string) $row['group_code']),
                'student_count' => (int) $row['student_count'],
                'color_code' => $row['color_code'] ?? null,
            ])
            ->values();

        if ($rows->contains(fn ($row) => $row['group_code'] === '')) {
            throw ValidationException::withMessages(['rows' => 'กรุณาระบุชื่อกลุ่มให้ครบทุกแถว']);
        }

        $ids = $rows->pluck('id')->filter()->unique()->values();
        $existingGroups = StudentGroup::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($existingGroups->count() !== $ids->count()) {
            throw ValidationException::withMessages(['rows' => 'มีกลุ่มที่ไม่อยู่ในรายวิชานี้']);
        }

        $codes = $rows->pluck('group_code');
        if ($codes->unique()->count() !== $codes->count()) {
            throw ValidationException::withMessages(['rows' => 'ชื่อกลุ่มซ้ำกัน กรุณาตรวจสอบอีกครั้ง']);
        }

        $duplicateCodes = StudentGroup::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('group_code', $codes->all())
            ->whereNotIn('id', $ids)
            ->pluck('group_code')
            ->all();

        if ($duplicateCodes !== []) {
            throw ValidationException::withMessages([
                'rows' => 'มีชื่อกลุ่มนี้อยู่แล้วในรายวิชา: ' . implode(', ', $duplicateCodes),
            ]);
        }

        $cohorts = collect();
        foreach ($rows->pluck('cohort_group_id')->unique() as $cohortId) {
            $cohort = $this->eligibleCohortGroup($courseOffering, (int) $cohortId);
            $cohorts->put((int) $cohort->id, $cohort);
        }

        foreach ($rows->groupBy('cohort_group_id') as $cohortId => $cohortRows) {
            $cohort = $cohorts->get((int) $cohortId);
            $otherUsed = StudentGroup::query()
                ->where('course_offering_id', $courseOffering->id)
                ->where('cohort_group_id', (int) $cohortId)
                ->whereNotIn('id', $ids)
                ->sum('student_count');
            $total = (int) $otherUsed + (int) $cohortRows->sum('student_count');

            if ($total > (int) $cohort->student_count) {
                throw ValidationException::withMessages([
                    'rows' => "จำนวนนักศึกษาเกินกลุ่มต้นทาง {$cohort->code} ที่มี {$cohort->student_count} คน",
                ]);
            }
        }

        $savedGroups = DB::transaction(function () use ($courseOffering, $rows, $existingGroups) {
            return $rows->map(function (array $row) use ($courseOffering, $existingGroups) {
                $values = [
                    'cohort_group_id' => $row['cohort_group_id'],
                    'group_code' => $row['group_code'],
                    'student_count' => $row['student_count'],
                    'color_code' => $row['color_code'] ?: '#2563eb',
                ];

                if ($row['id']) {
                    $group = $existingGroups->get($row['id']);
                    $group->update($values);
                    return $group->fresh('cohortGroup.parent');
                }

                return $courseOffering->studentGroups()->create($values)->fresh('cohortGroup.parent');
            });
        });

        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);
        $this->logCourseManagementUpdate(
            table: 'student_groups',
            recordId: $courseOffering->id,
            oldValues: $this->offeringAuditContext($courseOffering),
            newValues: $this->bulkStudentGroupAuditValues($courseOffering, $savedGroups),
            description: "บันทึกกลุ่มนักศึกษา {$savedGroups->count()} กลุ่ม ใน {$this->offeringCourseLabel($courseOffering)}",
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'บันทึกกลุ่มนักศึกษาเรียบร้อยแล้ว',
                'groups' => $savedGroups->map(fn (StudentGroup $group) => $this->studentGroupResponse($group))->values(),
            ]);
        }

        return $this->redirectToStudentGroups($courseOffering)->with('success', 'บันทึกกลุ่มนักศึกษาเรียบร้อยแล้ว');
    }

    public function destroyStudentGroup(Request $request, CourseOffering $courseOffering, StudentGroup $studentGroup): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'student-groups')) return $redirect;
        $this->assertStudentGroupBelongsToOffering($courseOffering, $studentGroup);

        if ($this->studentGroupsWithDownstreamReferences([$studentGroup->id]) !== []) {
            $message = 'ไม่สามารถลบกลุ่มที่ถูกอ้างอิงในตารางสอนได้ กรุณาจัดการตารางสอนที่เกี่ยวข้องก่อน';
            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : $this->redirectToStudentGroups($courseOffering)->withErrors(['student_groups' => $message]);
        }

        $auditBefore = $this->studentGroupAuditValues($courseOffering, $studentGroup);
        $studentGroup->delete();
        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        $this->logCourseManagementDelete(
            table: 'student_groups',
            recordId: $studentGroup->id,
            oldValues: $auditBefore,
            newValues: $this->offeringAuditContext($courseOffering),
            description: "ลบกลุ่มนักศึกษา {$studentGroup->group_code} ใน {$this->offeringCourseLabel($courseOffering)}",
        );

        return $request->expectsJson()
            ? response()->json(['message' => 'ลบกลุ่มแล้ว'])
            : $this->redirectToStudentGroups($courseOffering)->with('warning', 'ลบกลุ่มนักศึกษาเรียบร้อยแล้ว');
    }

    public function destroyStudentGroups(Request $request, CourseOffering $courseOffering): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'student-groups')) return $redirect;

        $validated = $request->validate([
            'student_group_ids' => ['required', 'array', 'min:1'],
            'student_group_ids.*' => ['integer'],
        ], [
            'student_group_ids.required' => 'กรุณาเลือกกลุ่มที่ต้องการลบ',
        ]);

        $ids = collect($validated['student_group_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $groups = StudentGroup::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('id', $ids)
            ->get();

        if ($groups->count() !== count($ids)) {
            throw ValidationException::withMessages(['student_groups' => 'มีกลุ่มที่ไม่อยู่ในรายวิชานี้']);
        }

        if ($this->studentGroupsWithDownstreamReferences($ids) !== []) {
            $message = 'ไม่สามารถลบกลุ่มที่ถูกอ้างอิงในตารางสอนได้ กรุณาจัดการตารางสอนที่เกี่ยวข้องก่อน';
            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : $this->redirectToStudentGroups($courseOffering)->withErrors(['student_groups' => $message]);
        }

        $auditBefore = $groups
            ->map(fn (StudentGroup $group) => $this->studentGroupAuditValues($courseOffering, $group))
            ->values()
            ->all();

        StudentGroup::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('id', $ids)
            ->delete();
        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        $this->logCourseManagementDelete(
            table: 'student_groups',
            recordId: $courseOffering->id,
            oldValues: ['groups' => $auditBefore],
            newValues: $this->offeringAuditContext($courseOffering),
            description: "ลบกลุ่มนักศึกษา {$groups->count()} กลุ่ม ใน {$this->offeringCourseLabel($courseOffering)}",
        );

        return $request->expectsJson()
            ? response()->json(['message' => 'ลบกลุ่มที่เลือกแล้ว'])
            : $this->redirectToStudentGroups($courseOffering)->with('warning', "ลบกลุ่มนักศึกษา {$groups->count()} กลุ่มเรียบร้อยแล้ว");
    }

    private function authorizeCourseHeadOffering(CourseOffering $courseOffering): void
    {
        $courseOffering->loadMissing('course');
        abort_unless((int) $courseOffering->coordinator_id === (int) Auth::id(), 403);
        abort_unless($courseOffering->course?->status === 'active', 403);
    }

    private function requireSchedulingPhase(CourseOffering $courseOffering, string $section = 'course-info'): ?RedirectResponse
    {
        $courseOffering->loadMissing('academicYear');
        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->to(route('maker.course_offerings.show', $courseOffering) . '#' . $section)
                ->with('error', 'ยังไม่เปิดช่วงจัดตาราง — Admin ต้องเปิดช่วงจัดตารางก่อนจึงจะแก้ไขข้อมูลรายวิชาได้')
                ->with('error_section', $section);
        }

        // M11: ล็อกแก้ไขเมื่อส่งขออนุมัติแล้ว (pending) หรืออนุมัติแล้ว (published)
        if (in_array($courseOffering->approval_status, ['pending', 'published'], true)) {
            $state = $courseOffering->approval_status === 'pending' ? 'อยู่ระหว่างรออนุมัติ' : 'อนุมัติแล้ว';
            return redirect()
                ->to(route('maker.course_offerings.show', $courseOffering) . '#' . $section)
                ->with('error', "รายวิชานี้{$state} — ตารางถูกล็อก แก้ไขไม่ได้")
                ->with('error_section', $section);
        }
        return null;
    }

    private function redirectToInstructors(CourseOffering $courseOffering): RedirectResponse
    {
        return redirect()->to(route('maker.course_offerings.show', $courseOffering) . '#instructors');
    }

    private function redirectToStudentGroups(CourseOffering $courseOffering): RedirectResponse
    {
        $params = ['courseOffering' => $courseOffering];
        $returnTo = $this->safeReturnToSchedule(request());

        if ($returnTo) {
            $params['return_to'] = $returnTo;
        }

        return redirect()->to(route('maker.course_offerings.show', $params) . '#student-groups');
    }

    private function safeReturnToSchedule(Request $request): ?string
    {
        $returnTo = $request->input('return_to');

        return is_string($returnTo) && Str::startsWith($returnTo, url('/'))
            ? $returnTo
            : null;
    }

    private function studentGroupRules(CourseOffering $courseOffering, ?StudentGroup $studentGroup = null): array
    {
        return [
            'cohort_group_id' => ['required', 'integer'],
            'group_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('student_groups', 'group_code')
                    ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id))
                    ->ignore($studentGroup?->id),
            ],
            'student_count' => ['required', 'integer', 'min:1', 'max:9999'],
            'color_code' => ['nullable', 'string', 'max:10'],
        ];
    }

    private function studentGroupValidationMessages(): array
    {
        return [
            'cohort_group_id.required' => 'กรุณาเลือกกลุ่มต้นทาง',
            'group_code.required' => 'กรุณากรอกชื่อกลุ่ม',
            'group_code.unique' => 'มีชื่อกลุ่มนี้ในรายวิชาแล้ว',
            'student_count.required' => 'กรุณาระบุจำนวนนักศึกษา',
        ];
    }

    private function eligibleCohortGroup(CourseOffering $courseOffering, int $cohortId): StudentCohort
    {
        $courseOffering->loadMissing('course');

        $cohort = StudentCohort::query()
            ->with('parent')
            ->whereKey($cohortId)
            ->where('curriculum_id', $courseOffering->course?->curriculum_id)
            ->when(Schema::hasColumn('student_cohorts', 'academic_year_id'), fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('academic_year_id')
                ->when($courseOffering->academic_year_id, fn ($q, $yearId) => $q->orWhere('academic_year_id', $yearId))))
            ->when(
                $courseOffering->course?->default_year_level,
                fn ($query, $yearLevel) => $query->where('year_level', $yearLevel)
            )
            ->first();

        if (! $cohort) {
            throw ValidationException::withMessages([
                'cohort_group_id' => 'กลุ่มต้นทางไม่อยู่ในหลักสูตรหรือชั้นปีของรายวิชานี้',
            ]);
        }

        return $cohort;
    }

    private function remainingCohortStudentCount(
        CourseOffering $courseOffering,
        StudentCohort $cohort,
        ?StudentGroup $ignore = null
    ): int {
        $used = StudentGroup::query()
            ->where('course_offering_id', $courseOffering->id)
            ->where('cohort_group_id', $cohort->id)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->sum('student_count');

        return max(0, (int) $cohort->student_count - (int) $used);
    }

    private function assertCohortGroupHasRoom(
        CourseOffering $courseOffering,
        StudentCohort $cohort,
        int $requestedCount,
        ?StudentGroup $ignore = null
    ): void {
        $remaining = $this->remainingCohortStudentCount($courseOffering, $cohort, $ignore);
        if ($requestedCount <= $remaining) {
            return;
        }

        throw ValidationException::withMessages([
            'student_count' => "จำนวนนักศึกษาเกินกลุ่มต้นทาง {$cohort->code} ที่เหลือ {$remaining} คน",
        ]);
    }

    private function defaultStudentGroupDetails(
        StudentCohort $cohort,
        int $groupCount,
        $existingGroups,
        bool $rebalanceExisting,
        array $colors
    ) {
        $existingCount = collect($existingGroups)->count();
        $totalSlots = $rebalanceExisting ? $existingCount + $groupCount : $groupCount;
        $totalStudents = $rebalanceExisting
            ? (int) $cohort->student_count
            : max(0, (int) $cohort->student_count - (int) collect($existingGroups)->sum('student_count'));
        $counts = $this->balancedCounts($totalStudents, max(1, $totalSlots));
        $offset = $rebalanceExisting ? $existingCount : 0;
        $nextNumber = $this->nextStudentGroupNumber($cohort, collect($existingGroups)->pluck('group_code')->all());

        return collect(range(0, $groupCount - 1))->map(function (int $index) use ($cohort, $groupCount, $existingCount, $counts, $offset, $nextNumber, $colors) {
            return [
                'group_code' => $groupCount === 1 && $existingCount === 0 && $nextNumber === 1
                    ? $cohort->code
                    : $this->defaultStudentGroupCode($cohort, $nextNumber + $index),
                'student_count' => $counts[$offset + $index] ?? 1,
                'color_code' => $colors[$index % count($colors)],
            ];
        });
    }

    /** @return array<int, int> */
    private function balancedCounts(int $total, int $parts): array
    {
        $parts = max(1, $parts);
        $base = intdiv(max(0, $total), $parts);
        $remainder = max(0, $total) % $parts;

        return collect(range(0, $parts - 1))
            ->map(fn (int $index) => max(1, $base + ($index < $remainder ? 1 : 0)))
            ->all();
    }

    private function defaultStudentGroupCode(StudentCohort $cohort, int $number): string
    {
        return $cohort->code . $number;
    }

    /** @param array<int, string> $existingCodes */
    private function nextStudentGroupNumber(StudentCohort $cohort, array $existingCodes): int
    {
        $prefix = preg_quote((string) $cohort->code, '/');
        $numbers = collect($existingCodes)
            ->map(function (string $code) use ($prefix) {
                if (preg_match('/^' . $prefix . '(\d+)$/u', $code, $matches)) {
                    return (int) $matches[1];
                }

                return null;
            })
            ->filter()
            ->values();

        return $numbers->isEmpty() ? 1 : ((int) $numbers->max()) + 1;
    }

    private function assertStudentGroupBelongsToOffering(CourseOffering $courseOffering, StudentGroup $studentGroup): void
    {
        abort_unless((int) $studentGroup->course_offering_id === (int) $courseOffering->id, 404);
    }

    /** @param array<int> $ids */
    private function studentGroupsWithDownstreamReferences(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('schedule_student_groups')) {
            return [];
        }

        return DB::table('schedule_student_groups')
            ->whereIn('student_group_id', $ids)
            ->pluck('student_group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function studentGroupResponse(StudentGroup $studentGroup): array
    {
        $studentGroup->loadMissing('cohortGroup.parent');

        return [
            'id' => $studentGroup->id,
            'group_code' => $studentGroup->group_code,
            'student_count' => $studentGroup->student_count,
            'color_code' => $studentGroup->color_code,
            'cohort_group_id' => $studentGroup->cohort_group_id,
            'cohort_code' => $studentGroup->cohortGroup?->code,
            'root_cohort_id' => $studentGroup->cohortGroup?->rootGroupId(),
            'root_cohort_code' => $studentGroup->cohortGroup?->parent?->code ?? $studentGroup->cohortGroup?->code,
        ];
    }

    private function studentGroupAuditValues(CourseOffering $courseOffering, StudentGroup $studentGroup): array
    {
        return $this->studentGroupResponse($studentGroup) + $this->offeringAuditContext($courseOffering);
    }

    private function bulkStudentGroupAuditValues(CourseOffering $courseOffering, $groups): array
    {
        return [
            'groups' => collect($groups)->map(fn (StudentGroup $group) => $this->studentGroupResponse($group))->values()->all(),
        ] + $this->offeringAuditContext($courseOffering);
    }

    private function auditModelSnapshot(object $model, array $fields): array
    {
        return collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => $model->{$field}])
            ->all();
    }

    private function offeringAuditContext(CourseOffering $courseOffering): array
    {
        $courseOffering->loadMissing(['course', 'academicYear']);

        return [
            'course_offering_id' => $courseOffering->id,
            'course_id' => $courseOffering->course_id,
            'course_code' => $courseOffering->course?->course_code,
            'course_name' => $courseOffering->course?->name_th,
            'academic_year' => $courseOffering->academicYear?->name,
        ];
    }

    private function offeringCourseLabel(CourseOffering $courseOffering): string
    {
        $courseOffering->loadMissing('course');

        return trim(($courseOffering->course?->course_code ?? 'รายวิชา') . ' ' . ($courseOffering->course?->name_th ?? ''));
    }

    private function offeringInstructorAuditValues(
        CourseOffering $courseOffering,
        User $user,
        ?int $courseRoleId,
        string $roleInCourse
    ): array {
        return [
            'user_id' => $user->id,
            'instructor_name' => $user->formatted_name ?? $user->name,
            'course_role_id' => $courseRoleId,
            'course_role_name' => $courseRoleId ? CourseRole::find($courseRoleId)?->name_th : null,
            'role_in_course' => $roleInCourse,
        ] + $this->offeringAuditContext($courseOffering);
    }

    private function logCourseManagementCreate(string $table, int $recordId, array $newValues, string $description): void
    {
        AuditLogger::log(
            action: self::AUDIT_CATEGORY . '.สร้าง',
            table: $table,
            recordId: $recordId,
            oldValues: null,
            newValues: $newValues,
            category: self::AUDIT_CATEGORY,
            description: $description,
        );
    }

    private function logCourseManagementUpdate(string $table, int $recordId, array $oldValues, array $newValues, string $description): void
    {
        if (empty($oldValues)) {
            return;
        }

        AuditLogger::log(
            action: self::AUDIT_CATEGORY . '.แก้ไข',
            table: $table,
            recordId: $recordId,
            oldValues: $oldValues,
            newValues: $newValues,
            category: self::AUDIT_CATEGORY,
            description: $description,
        );
    }

    private function logCourseManagementDelete(string $table, int $recordId, array $oldValues, ?array $newValues, string $description): void
    {
        AuditLogger::log(
            action: self::AUDIT_CATEGORY . '.ลบ',
            table: $table,
            recordId: $recordId,
            oldValues: $oldValues,
            newValues: $newValues,
            category: self::AUDIT_CATEGORY,
            description: $description,
        );
    }
}
