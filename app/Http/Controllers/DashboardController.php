<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Instructor\PaController;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Room;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditLogger;
use App\Services\ScheduleConflictReadRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Redirect to the role-appropriate dashboard.
     */
    public function index()
    {
        $role = session('active_role');

        return match($role) {
            'admin'       => redirect()->route('admin.dashboard'),
            'staff'       => redirect()->route('staff.settings'),
            'course_head' => redirect()->route('maker.schedules.index'),
            'executive'   => redirect()->route('approver.dashboard'),
            'instructor'  => $this->instructorLandingRedirect(),
            default       => redirect()->route('dashboard.coming_soon'),
        };
    }

    public function comingSoon()
    {
        return view('shared.coming-soon');
    }

    private function instructorLandingRedirect()
    {
        $user = Auth::user();

        $canHelpSchedule = $user
            && CourseOffering::query()
                ->schedulableBy((int) $user->id)
                ->whereHas('academicYear', fn ($query) => $query->where('phase', 'scheduling'))
                ->exists();

        return $canHelpSchedule
            ? redirect()->route('maker.schedules.index')
            : redirect()->route('lecturer.dashboard');
    }

    // ── Per-role placeholders ──────────────────────────────────────

    public function admin()
    {
        ['instructors' => $instructors, 'teachingWeeks' => $teachingWeeks, 'hoursPerWeek' => $hoursPerWeek]
            = $this->instructorWorkloadData();

        $criticals = AlertController::getCriticals();
        $alerts    = AlertController::getSummary();
        $currentAcademicYear = AcademicYear::where('is_active', true)
            ->orderByDesc('name')
            ->first();

        $roomsByType = \App\Models\LocationType::withCount('rooms')
            ->orderByDesc('rooms_count')
            ->get()
            ->map(fn($lt) => ['label' => $lt->name, 'count' => $lt->rooms_count]);

        $curriculumsByLevel = Curriculum::select('education_level', DB::raw('COUNT(*) as cnt'))
            ->groupBy('education_level')
            ->pluck('cnt', 'education_level')
            ->toArray();

        $stats = [
            'users' => [
                'active' => User::where('is_active', true)->count(),
                'total'  => User::count(),
            ],
            'courses' => [
                'active' => Course::where('status', 'active')->count(),
                'total'  => Course::count(),
            ],
            'rooms' => [
                'total'    => Room::count(),
                'by_type'  => $roomsByType,
            ],
            'curriculums' => [
                'total'     => Curriculum::count(),
                'by_level'  => [
                    'bachelor'  => $curriculumsByLevel['bachelor']  ?? 0,
                    'master'    => $curriculumsByLevel['master']    ?? 0,
                    'doctorate' => $curriculumsByLevel['doctorate'] ?? 0,
                ],
            ],
        ];

        $pipelineCounts = $currentAcademicYear
            ? CourseOffering::where('academic_year_id', $currentAcademicYear->id)
                ->select('approval_status', DB::raw('COUNT(*) as count'))
                ->groupBy('approval_status')
                ->pluck('count', 'approval_status')
                ->toArray()
            : [];

        $pipeline = [
            'draft'     => $pipelineCounts['draft']     ?? 0,
            'pending'   => $pipelineCounts['pending']   ?? 0,
            'published' => $pipelineCounts['published'] ?? 0,
            'rejected'  => $pipelineCounts['rejected']  ?? 0,
        ];

        $conflictSummary = config('conflicts.async_reads') && $currentAcademicYear
            ? app(ScheduleConflictReadRepository::class)->getGlobalSummary((int) $currentAcademicYear->id)
            : ['status' => config('conflicts.async_reads') ? 'missing' : 'disabled', 'generation' => null, 'total' => null, 'by_type' => []];

        return view('admin.dashboard', compact('instructors', 'teachingWeeks', 'hoursPerWeek', 'alerts', 'criticals', 'currentAcademicYear', 'stats', 'pipeline', 'conflictSummary'));
    }

    public function staff()
    {
        ['instructors' => $instructors, 'teachingWeeks' => $teachingWeeks, 'hoursPerWeek' => $hoursPerWeek]
            = $this->instructorWorkloadData();

        $recentAuditLogs = AuditLog::with('user')
            ->orderedForAudit()
            ->limit(5)
            ->get();

        return view('staff.dashboard', compact('instructors', 'teachingWeeks', 'hoursPerWeek', 'recentAuditLogs'));
    }

    private function instructorWorkloadData(): array
    {
        return [
            'instructors'   => User::whereHas('roles', fn($q) => $q->where('role', 'instructor'))
                                   ->with(['instructorProfile.department'])->get(),
            'teachingWeeks' => SystemSetting::get('teaching_load_weeks', 39),
            'hoursPerWeek'  => SystemSetting::get('teaching_quota_hours_per_week', 35),
        ];
    }

    public function maker()
    {
        return view('course_head.dashboard');
    }

    public function approver()
    {
        $currentAcademicYear = AcademicYear::currentForApproval();
        $conflictSummary = config('conflicts.async_reads') && $currentAcademicYear
            ? app(ScheduleConflictReadRepository::class)->getExecutiveSummary((int) $currentAcademicYear->id)
            : ['status' => config('conflicts.async_reads') ? 'missing' : 'disabled', 'generation' => null, 'total' => null, 'by_type' => []];

        // M11 — รายวิชาที่รออนุมัติ (คิวของผู้บริหาร)
        $pendingOfferings = CourseOffering::query()
            ->with(['course', 'coordinator', 'academicYear'])
            ->withCount(['schedules', 'instructorPool'])
            ->where('approval_status', 'pending')
            ->when($currentAcademicYear, fn ($q) => $q->where('academic_year_id', $currentAcademicYear->id))
            ->orderBy('updated_at')
            ->get();

        // M11 — สรุปสถานะอนุมัติทั้งปี (reuse partial เดียวกับ admin)
        $pipelineCounts = $currentAcademicYear
            ? CourseOffering::where('academic_year_id', $currentAcademicYear->id)
                ->select('approval_status', DB::raw('COUNT(*) as count'))
                ->groupBy('approval_status')
                ->pluck('count', 'approval_status')
            : collect();
        $pipeline = [
            'draft'     => $pipelineCounts['draft']     ?? 0,
            'pending'   => $pipelineCounts['pending']   ?? 0,
            'published' => $pipelineCounts['published'] ?? 0,
            'rejected'  => $pipelineCounts['rejected']  ?? 0,
        ];

        return view('executive.dashboard', compact('currentAcademicYear', 'conflictSummary', 'pendingOfferings', 'pipeline'));
    }

    public function lecturer()
    {
        $paData = app(PaController::class)->workloadDataFor(Auth::user());

        return view('instructor.dashboard', $paData);
    }

    /**
     * Switch the active_role stored in session.
     */
    public function switchRole(Request $request)
    {
        $request->validate(['role' => 'required|string']);

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['username' => 'บัญชีผู้ใช้นี้ถูกระงับการใช้งาน']);
        }

        $hasRole = UserRole::where('user_id', $user->id)
            ->where('role', $request->role)
            ->exists();

        if ($hasRole) {
            $previousRole = $request->session()->get('active_role');
            $request->session()->put('active_role', $request->role);

            // 8.3: บันทึกประวัติการสลับบทบาท (ใคร/จากบทบาทใด/เป็นบทบาทใด)
            if ($previousRole !== $request->role) {
                AuditLogger::log(
                    action: 'ระบบ.เปลี่ยนบทบาท',
                    table: 'users',
                    recordId: $user->id,
                    oldValues: ['active_role' => $previousRole],
                    newValues: ['active_role' => $request->role],
                    category: 'ระบบ',
                    description: "เปลี่ยนบทบาท: {$user->name} ({$previousRole} → {$request->role})",
                );
            }
        }

        return redirect()->route('dashboard');
    }
}
