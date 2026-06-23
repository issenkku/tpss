<?php

namespace App\Models;

use App\Models\CourseRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CourseOffering extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id',
        'academic_year_id',
        'coordinator_id',
        'approval_status',
        'rejection_reason',
        'planned_lecture_hours',
        'planned_lab_hours',
        'teaching_weeks',
        'instructor_pool_note',
    ];

    protected $casts = [
        'planned_lecture_hours' => 'integer',
        'planned_lab_hours' => 'integer',
        'teaching_weeks' => 'integer',
    ];

    public function getRouteKey()
    {
        $baseKey = $this->readableRouteKeyBase();

        return $this->readableRouteKeyHasCollision($baseKey)
            ? "{$baseKey}-{$this->getKey()}"
            : $baseKey;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        $value = (string) $value;

        if (ctype_digit($value)) {
            return $this->whereKey($value)->first();
        }

        if ($offering = $this->resolveReadableRouteKeyWithIdSuffix($value)) {
            return $offering;
        }

        $matches = $this->offeringsMatchingReadableRouteKeyBase($value);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function readableRouteKeyBase(): string
    {
        $this->loadMissing(['course', 'academicYear']);

        // V2: offering ราย-ปี (1 วิชา 1 offering/ปี) → URL = course-year (เลิกฝังเทอม)
        return implode('-', [
            $this->routeSlug($this->course?->course_code, 'course'),
            $this->routeSlug($this->academicYear?->name, 'year'),
        ]);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeWithActiveCourse($query)
    {
        return $query->whereHas('course', fn ($courseQuery) => $courseQuery->where('status', 'active'));
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function studentGroups(): HasMany
    {
        return $this->hasMany(StudentGroup::class);
    }

    public function instructorPool(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_offering_instructors')
            ->withPivot('role_in_course', 'course_role_id', 'schedule_permission');
    }

    /**
     * V2 delegation: ใครจัดตาราง offering นี้ได้
     *  1. หัวหน้าวิชา (coordinator)
     *  2. อาจารย์ในชุดผู้สอนที่ได้รับมอบหมาย (schedule_permission = 'schedule')
     *  3. เจ้าหน้าที่ที่ admin มอบหมายดูแลวิชา (course_staff) — มอบหมายผ่าน modal รายวิชา
     */
    public function canBeScheduledBy(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }
        if ((int) $this->coordinator_id === (int) $userId) {
            return true;
        }

        if ($this->instructorPool()
            ->where('users.id', $userId)
            ->wherePivot('schedule_permission', 'schedule')
            ->exists()) {
            return true;
        }

        return $this->course()
            ->whereHas('assignedStaff', fn ($q) => $q->where('users.id', $userId))
            ->exists();
    }

    /** scope: offering ที่ user คนนี้จัดตารางได้ (coordinator, อาจารย์ที่ถูก delegate, หรือเจ้าหน้าที่ที่ดูแลวิชา) */
    public function scopeSchedulableBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('coordinator_id', $userId)
                ->orWhereHas('instructorPool', fn ($iq) => $iq
                    ->where('users.id', $userId)
                    ->where('course_offering_instructors.schedule_permission', 'schedule'))
                ->orWhereHas('course.assignedStaff', fn ($sq) => $sq
                    ->where('users.id', $userId));
        });
    }

    public function attachCoordinator(?int $coordinatorRoleId = null): void
    {
        if (!$this->coordinator_id) return;
        if ($this->instructorPool()->where('users.id', $this->coordinator_id)->exists()) return;

        $coordinatorRoleId ??= CourseRole::where('name_th', 'หัวหน้าวิชา')->value('id');
        $this->instructorPool()->attach($this->coordinator_id, [
            'role_in_course' => 'coordinator',
            'course_role_id' => $coordinatorRoleId,
        ]);
    }

    public function syncInstructorPoolFromCourseTemplate(?int $coordinatorRoleId = null): void
    {
        $course = $this->course ?? $this->course()->first();
        if (!$course) return;

        if ($course->head_instructor_id && (int) $this->coordinator_id !== (int) $course->head_instructor_id) {
            $this->forceFill(['coordinator_id' => $course->head_instructor_id])->save();
        }

        $coordinatorRoleId ??= CourseRole::where('name_th', 'หัวหน้าวิชา')->value('id');
        $sourcePool = $course->instructors()->get();
        $sourceIds = $sourcePool->pluck('id')->all();
        $templateIds = array_values(array_unique(array_filter([
            $this->coordinator_id,
            ...$sourceIds,
        ])));

        $existingIds = $this->instructorPool()->pluck('users.id')->all();
        $staleIds = array_diff($existingIds, $templateIds);
        if (!empty($staleIds)) {
            $this->instructorPool()->detach($staleIds);
        }

        $this->attachCoordinator($coordinatorRoleId);

        if ($this->coordinator_id) {
            $this->instructorPool()->updateExistingPivot($this->coordinator_id, [
                'role_in_course' => 'coordinator',
                'course_role_id' => $coordinatorRoleId,
            ]);
        }

        foreach ($sourcePool as $instructor) {
            if ((int) $instructor->id === (int) $this->coordinator_id) {
                continue;
            }

            $this->instructorPool()->syncWithoutDetaching([
                $instructor->id => [
                    'role_in_course' => 'instructor',
                    'course_role_id' => $instructor->pivot->course_role_id,
                ],
            ]);
        }
    }

    public function copyInstructorPoolFromCourse(): void
    {
        $course = $this->course ?? $this->course()->first();
        if (!$course) return;

        $sourcePool = $course->instructors()->get();
        $existing = $this->instructorPool()->pluck('users.id')->all();

        $payload = [];
        foreach ($sourcePool as $instructor) {
            if (in_array($instructor->id, $existing, true)) continue;
            $payload[$instructor->id] = [
                'role_in_course' => 'instructor',
                'course_role_id' => $instructor->pivot->course_role_id,
            ];
        }

        if (!empty($payload)) {
            $this->instructorPool()->attach($payload);
        }
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /** M11 — ประวัติการอนุมัติ (submit/approve/reject/revise) เรียงใหม่สุดก่อน */
    public function approvals(): HasMany
    {
        return $this->hasMany(CourseOfferingApproval::class)->latest('created_at');
    }

    public function scheduleTemplates(): HasMany
    {
        return $this->hasMany(ScheduleTemplate::class);
    }

    private function readableRouteKeyHasCollision(string $baseKey): bool
    {
        $this->loadMissing('academicYear');

        if (! $this->academicYear) {
            return false;
        }

        return $this->newQuery()
            ->with(['course', 'academicYear'])
            ->whereHas('academicYear', fn ($query) => $query
                ->where('name', $this->academicYear->name))
            ->get()
            ->filter(fn (self $offering) => $offering->readableRouteKeyBase() === $baseKey)
            ->count() > 1;
    }

    private function resolveReadableRouteKeyWithIdSuffix(string $value): ?self
    {
        if (! preg_match('/-(\d+)$/', $value, $matches)) {
            return null;
        }

        $offering = $this->newQuery()
            ->with(['course', 'academicYear'])
            ->whereKey((int) $matches[1])
            ->first();

        return $offering && $offering->getRouteKey() === $value ? $offering : null;
    }

    private function offeringsMatchingReadableRouteKeyBase(string $value)
    {
        // V2: course code/year name อาจมี hyphen → slug มีหลาย segment แยกไม่ได้ด้วย explode
        // เทียบ base ตรง ๆ แทน (ตาราง offering เล็ก) — กัน 404 จากชื่อปีที่มีขีด เช่น "2569-1"
        return $this->newQuery()
            ->with(['course', 'academicYear'])
            ->get()
            ->filter(fn (self $offering) => $offering->readableRouteKeyBase() === $value)
            ->values();
    }

    private function routeSlug(?string $value, string $fallback): string
    {
        return Str::slug((string) $value) ?: $fallback;
    }
}
