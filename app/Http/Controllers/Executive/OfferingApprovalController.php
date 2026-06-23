<?php

namespace App\Http\Controllers\Executive;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CourseOffering;
use App\Models\CourseOfferingApproval;
use App\Services\AuditLogger;
use App\Services\NavigationBadgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * M11 Task18 — ผู้บริหารพิจารณารายวิชาที่รออนุมัติ (อนุมัติ/ตีกลับ)
 * ผู้บริหาร = read-only + approve/reject เท่านั้น (ห้ามแก้ตาราง)
 */
class OfferingApprovalController extends Controller
{
    /** หน้ารายวิชาที่ถูกตีกลับ (ผู้บริหารติดตามว่ารอหัวหน้าวิชาแก้ไขแล้วส่งใหม่) */
    public function rejectedQueue(): View
    {
        $year = AcademicYear::query()
            ->where('is_active', true)
            ->orWhere('phase', 'scheduling')
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->first();

        $rejectedOfferings = CourseOffering::query()
            ->with(['course', 'coordinator', 'academicYear', 'approvals.actor'])
            ->withCount(['schedules', 'instructorPool'])
            ->where('approval_status', 'rejected')
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->orderByDesc('updated_at')
            ->get();

        return view('executive.offerings.rejected', compact('rejectedOfferings', 'year'));
    }

    /** หน้ารายละเอียดรายวิชา (read-only) ให้ผู้บริหารตรวจก่อนตัดสินใจ */
    public function show(CourseOffering $courseOffering): View
    {
        $courseOffering->load([
            'course.curriculum',
            'course.department',
            'academicYear',
            'coordinator',
            'instructorPool',
            'studentGroups',
            'schedules' => fn ($q) => $q->orderBy('start_date')->orderBy('start_time'),
            'schedules.instructors',
            'schedules.room',
            'approvals.actor',
        ]);

        return view('executive.offerings.show', compact('courseOffering'));
    }

    /** อนุมัติ: pending → published */
    public function approve(CourseOffering $courseOffering): RedirectResponse
    {
        if ($courseOffering->approval_status !== 'pending') {
            return $this->backToQueue()->with('error', 'รายวิชานี้ไม่ได้อยู่ในสถานะรออนุมัติ');
        }

        DB::transaction(function () use ($courseOffering) {
            $courseOffering->update(['approval_status' => 'published']);

            CourseOfferingApproval::create([
                'course_offering_id' => $courseOffering->id,
                'actor_user_id'      => Auth::id(),
                'action'             => 'approve',
                'from_status'        => 'pending',
                'to_status'          => 'published',
            ]);

            $this->notifyCoordinator($courseOffering, "รายวิชา {$this->label($courseOffering)} ได้รับการอนุมัติแล้ว");
        });

        AuditLogger::log(
            action: 'การอนุมัติ.อนุมัติ',
            table: 'course_offerings',
            recordId: $courseOffering->id,
            oldValues: ['approval_status' => 'pending'],
            newValues: ['approval_status' => 'published'],
            category: 'การอนุมัติ',
            description: "อนุมัติรายวิชา {$this->label($courseOffering)}",
        );

        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        return $this->backToQueue()->with('success', "อนุมัติรายวิชา {$this->label($courseOffering)} เรียบร้อยแล้ว");
    }

    /** ตีกลับ: pending → rejected (ต้องมีเหตุผล) */
    public function reject(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ], [], ['rejection_reason' => 'เหตุผลการตีกลับ']);

        if ($courseOffering->approval_status !== 'pending') {
            return $this->backToQueue()->with('error', 'รายวิชานี้ไม่ได้อยู่ในสถานะรออนุมัติ');
        }

        DB::transaction(function () use ($courseOffering, $validated) {
            $courseOffering->update([
                'approval_status'  => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            CourseOfferingApproval::create([
                'course_offering_id' => $courseOffering->id,
                'actor_user_id'      => Auth::id(),
                'action'             => 'reject',
                'comment'            => $validated['rejection_reason'],
                'from_status'        => 'pending',
                'to_status'          => 'rejected',
            ]);

            $this->notifyCoordinator($courseOffering, "รายวิชา {$this->label($courseOffering)} ถูกตีกลับ — โปรดแก้ไขและส่งใหม่");
        });

        AuditLogger::log(
            action: 'การอนุมัติ.ปฏิเสธ',
            table: 'course_offerings',
            recordId: $courseOffering->id,
            oldValues: ['approval_status' => 'pending'],
            newValues: ['approval_status' => 'rejected', 'rejection_reason' => $validated['rejection_reason']],
            category: 'การอนุมัติ',
            description: "ตีกลับรายวิชา {$this->label($courseOffering)}",
        );

        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        return $this->backToQueue()->with('success', "ตีกลับรายวิชา {$this->label($courseOffering)} แล้ว");
    }

    private function notifyCoordinator(CourseOffering $courseOffering, string $message): void
    {
        if (! $courseOffering->coordinator_id) {
            return;
        }

        DB::table('notifications')->insert([
            'user_id'            => $courseOffering->coordinator_id,
            'course_offering_id' => $courseOffering->id,
            'type'               => 'approval_update',
            'message'            => $message,
            'is_read'            => false,
            'created_at'         => now(),
        ]);
    }

    private function label(CourseOffering $courseOffering): string
    {
        $courseOffering->loadMissing('course');

        return trim(($courseOffering->course?->course_code ?? '') . ' ' . ($courseOffering->course?->name_th ?? ''));
    }

    private function backToQueue(): RedirectResponse
    {
        return redirect()->route('approver.dashboard');
    }
}
