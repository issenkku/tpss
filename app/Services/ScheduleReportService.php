<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudentGroup;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ScheduleReportService
{
    /**
     * @return array<string, mixed>
     */
    public function data(Request $request, bool $paginate = true): array
    {
        $validated = $request->validate([
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')],
            'term_sequence' => ['nullable', 'integer', 'between:1,3'],
            'curriculum_id' => ['nullable', 'integer', Rule::exists('curriculums', 'id')],
            'course_offering_id' => ['nullable', 'integer', Rule::exists('course_offerings', 'id')],
            'student_group_id' => ['nullable', 'integer', Rule::exists('student_groups', 'id')],
            'instructor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
        ]);

        $academicYears = AcademicYear::query()
            ->orderByDesc('is_active')
            ->orderByDesc('name')
            ->get();
        $year = isset($validated['academic_year_id'])
            ? $academicYears->firstWhere('id', (int) $validated['academic_year_id'])
            : $academicYears->firstWhere('is_active', true);

        $termOptions = $year
            ? $year->terms()->get()->unique('sequence')->sortBy('sequence')->values()
            : collect();
        $termSequence = isset($validated['term_sequence'])
            && $termOptions->contains('sequence', (int) $validated['term_sequence'])
                ? (int) $validated['term_sequence']
                : null;

        $curriculums = $this->curriculumOptions($year?->id);
        $curriculumId = isset($validated['curriculum_id'])
            && $curriculums->contains('id', (int) $validated['curriculum_id'])
                ? (int) $validated['curriculum_id']
                : null;

        $courseOfferings = $this->courseOfferingOptions($year?->id, $curriculumId);
        $courseOfferingId = isset($validated['course_offering_id'])
            && $courseOfferings->contains('id', (int) $validated['course_offering_id'])
                ? (int) $validated['course_offering_id']
                : null;

        $studentGroups = $this->studentGroupOptions($courseOfferings, $courseOfferingId);
        $studentGroupId = isset($validated['student_group_id'])
            && $studentGroups->contains('id', (int) $validated['student_group_id'])
                ? (int) $validated['student_group_id']
                : null;

        $instructors = $this->instructorOptions($year?->id);
        $instructorId = isset($validated['instructor_id'])
            && $instructors->contains('id', (int) $validated['instructor_id'])
                ? (int) $validated['instructor_id']
                : null;

        $rooms = $this->roomOptions($year?->id);
        $roomId = isset($validated['room_id'])
            && $rooms->contains('id', (int) $validated['room_id'])
                ? (int) $validated['room_id']
                : null;

        $query = $this->scheduleQuery(
            $year?->id,
            $termSequence,
            $curriculumId,
            $courseOfferingId,
            $studentGroupId,
            $instructorId,
            $roomId
        );

        /** @var LengthAwarePaginator|Collection $schedules */
        $schedules = $paginate
            ? $query->paginate(50)->withQueryString()
            : $query->get();

        $filters = array_filter([
            'academic_year_id' => $year?->id,
            'term_sequence' => $termSequence,
            'curriculum_id' => $curriculumId,
            'course_offering_id' => $courseOfferingId,
            'student_group_id' => $studentGroupId,
            'instructor_id' => $instructorId,
            'room_id' => $roomId,
        ], fn ($value) => $value !== null && $value !== '');

        $selectedTerm = $termSequence
            ? $termOptions->firstWhere('sequence', $termSequence)
            : null;

        return compact(
            'academicYears',
            'year',
            'termOptions',
            'termSequence',
            'curriculums',
            'curriculumId',
            'courseOfferings',
            'courseOfferingId',
            'studentGroups',
            'studentGroupId',
            'instructors',
            'instructorId',
            'rooms',
            'roomId',
            'schedules',
            'filters',
            'selectedTerm'
        );
    }

    private function curriculumOptions(?int $yearId): Collection
    {
        if (! $yearId) {
            return collect();
        }

        return Curriculum::query()
            ->whereHas('courses.courseOfferings', fn (Builder $query) => $query
                ->where('academic_year_id', $yearId)
                ->where('approval_status', 'published'))
            ->orderBy('name')
            ->get();
    }

    private function courseOfferingOptions(?int $yearId, ?int $curriculumId): Collection
    {
        if (! $yearId) {
            return collect();
        }

        return CourseOffering::query()
            ->with('course.curriculum')
            ->where('academic_year_id', $yearId)
            ->where('approval_status', 'published')
            ->when($curriculumId, fn (Builder $query) => $query
                ->whereHas('course', fn (Builder $course) => $course
                    ->where('curriculum_id', $curriculumId)))
            ->get()
            ->sortBy(fn (CourseOffering $offering) => $offering->course?->course_code)
            ->values();
    }

    private function studentGroupOptions(Collection $courseOfferings, ?int $courseOfferingId): Collection
    {
        $offeringIds = $courseOfferingId
            ? [$courseOfferingId]
            : $courseOfferings->pluck('id')->all();

        if ($offeringIds === []) {
            return collect();
        }

        return StudentGroup::query()
            ->with('courseOffering.course')
            ->whereIn('course_offering_id', $offeringIds)
            ->orderBy('group_code')
            ->get();
    }

    private function instructorOptions(?int $yearId): Collection
    {
        if (! $yearId) {
            return collect();
        }

        return User::query()
            ->with('instructorProfile')
            ->whereHas('schedules', fn (Builder $query) => $query
                ->where('status', 'approved')
                ->whereHas('courseOffering', fn (Builder $offering) => $offering
                    ->where('academic_year_id', $yearId)
                    ->where('approval_status', 'published')))
            ->get()
            ->sortBy('formatted_name')
            ->values();
    }

    private function roomOptions(?int $yearId): Collection
    {
        if (! $yearId) {
            return collect();
        }

        return Room::query()
            ->whereHas('schedules', fn (Builder $query) => $query
                ->where('status', 'approved')
                ->whereHas('courseOffering', fn (Builder $offering) => $offering
                    ->where('academic_year_id', $yearId)
                    ->where('approval_status', 'published')))
            ->orderBy('room_name')
            ->orderBy('room_code')
            ->get();
    }

    private function scheduleQuery(
        ?int $yearId,
        ?int $termSequence,
        ?int $curriculumId,
        ?int $courseOfferingId,
        ?int $studentGroupId,
        ?int $instructorId,
        ?int $roomId
    ): Builder {
        return Schedule::query()
            ->with([
                'courseOffering.course.curriculum',
                'term',
                'activityType',
                'room',
                'instructors.instructorProfile',
                'studentGroups',
            ])
            ->where('status', 'approved')
            ->whereHas('courseOffering', function (Builder $query) use ($yearId, $curriculumId): void {
                $query->where('approval_status', 'published')
                    ->when($yearId, fn (Builder $offering) => $offering
                        ->where('academic_year_id', $yearId))
                    ->when($curriculumId, fn (Builder $offering) => $offering
                        ->whereHas('course', fn (Builder $course) => $course
                            ->where('curriculum_id', $curriculumId)));
            })
            ->when($termSequence, fn (Builder $query) => $query
                ->whereHas('term', fn (Builder $term) => $term
                    ->where('sequence', $termSequence)))
            ->when($courseOfferingId, fn (Builder $query) => $query
                ->where('course_offering_id', $courseOfferingId))
            ->when($studentGroupId, fn (Builder $query) => $query
                ->whereHas('studentGroups', fn (Builder $group) => $group
                    ->where('student_groups.id', $studentGroupId)))
            ->when($instructorId, fn (Builder $query) => $query
                ->whereHas('instructors', fn (Builder $instructor) => $instructor
                    ->where('users.id', $instructorId)))
            ->when($roomId, fn (Builder $query) => $query
                ->where('room_id', $roomId))
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->orderBy('course_offering_id');
    }
}
