<?php

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudentGroup;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRole;
use App\Observers\ScheduleConflictInvalidationObserver;
use App\Observers\PerformanceCacheObserver;
use App\Services\ScheduleConflictInvalidationService;
use App\Services\NavigationBadgeService;
use App\Services\ReferenceDataCache;
use App\Services\ScheduleConflictIndex;
use App\Services\ScheduleConflictPolicy;
use App\Services\ScheduleConflictReadRepository;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Observers\ScheduleTermObserver::class);
        $this->app->scoped(ReferenceDataCache::class);
        $this->app->scoped(ScheduleConflictPolicy::class);
        $this->app->scoped(ScheduleConflictIndex::class);
        $this->app->scoped(ScheduleConflictInvalidationService::class);
        $this->app->scoped(ScheduleConflictReadRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $observer = PerformanceCacheObserver::class;

        foreach ([
            AcademicYear::class,
            ActivityType::class,
            Course::class,
            CourseOffering::class,
            CourseRole::class,
            Curriculum::class,
            Department::class,
            InstructorProfile::class,
            LocationType::class,
            Room::class,
            Schedule::class,
            StudentGroup::class,
            SystemSetting::class,
            User::class,
            UserRole::class,
        ] as $model) {
            $model::observe($observer);
        }

        Schedule::observe(ScheduleConflictInvalidationObserver::class);
        Schedule::observe(\App\Observers\ScheduleTermObserver::class);

        View::composer('components.sidebar', function ($view): void {
            $user = auth()->user();
            $activeRole = session('active_role', 'staff');

            $view->with('sidebarBadges', app(NavigationBadgeService::class)->forRole(
                is_string($activeRole) ? $activeRole : null,
                $user?->id,
            ));

            // V2 delegation: อาจารย์/เจ้าหน้าที่เห็นเมนู "ช่วยจัดตาราง" เฉพาะเมื่อถูกมอบหมายให้ดูแลวิชาที่อยู่ในช่วงจัดตาราง
            $canHelpSchedule = false;
            if ($user && in_array($activeRole, ['instructor', 'staff'], true)) {
                $canHelpSchedule = \App\Models\CourseOffering::query()
                    ->schedulableBy((int) $user->id)
                    ->whereHas('academicYear', fn ($q) => $q->where('phase', 'scheduling'))
                    ->exists();
            }
            $view->with('canHelpSchedule', $canHelpSchedule);
        });

        // M11 — กระดิ่งแจ้งเตือนใน topbar (ฉีดเข้า layout ทุกหน้า)
        View::composer('components.app-layout', function ($view): void {
            $userId = auth()->id();
            if (! $userId) {
                $view->with('navUnreadCount', 0)->with('navNotifications', collect());

                return;
            }

            $view->with(
                'navUnreadCount',
                \App\Models\Notification::forUser($userId)->unread()->count()
            );
            $view->with(
                'navNotifications',
                \App\Models\Notification::forUser($userId)
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get()
            );
        });
    }
}
