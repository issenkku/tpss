<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ScheduleReportController;
use App\Http\Controllers\CourseHead\CourseOfferingController;
use App\Http\Controllers\CourseHead\ConflictBadgeStatusController;
use App\Http\Controllers\CourseHead\ScheduleController;
use App\Http\Controllers\Instructor\PaController;
use Illuminate\Support\Facades\Route;

// ── Root redirect ──────────────────────────────────────────────────
Route::get('/', fn() => redirect()->route('login'));

// ── Auth ───────────────────────────────────────────────────────────
Route::get('/login',  [AuthController::class, 'showLoginForm'])->name('login')->middleware(['guest', 'no-back']);
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'no-back']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// ── Authenticated ──────────────────────────────────────────────────
Route::middleware(['auth', 'no-back'])->group(function () {

    // Session keepalive — ใช้โดย session-warning.js เพื่อต่ออายุ session
    Route::get('/session/ping', fn() => response()->json(['ok' => true]))->name('session.ping');

    // Hub: redirect to role-specific dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/coming-soon', [DashboardController::class, 'comingSoon'])->name('dashboard.coming_soon');
    // M11 — การแจ้งเตือน (กระดิ่ง)
    Route::get('/notifications/{notification}/open', [\App\Http\Controllers\NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.read_all');
    Route::get('/schedule-conflicts/{schedule}/details', [ScheduleController::class, 'conflictDetails'])
        ->name('schedule_conflicts.details')
        ->middleware('throttle:60,1');

    // Role-specific dashboards — guarded by role
    Route::get('/admin/dashboard',    [DashboardController::class, 'admin'])   ->name('admin.dashboard')   ->middleware('\App\Http\Middleware\CheckRole:admin');
    Route::get('/staff/dashboard',    [DashboardController::class, 'staff'])   ->name('staff.dashboard')   ->middleware('\App\Http\Middleware\CheckRole:staff');
    Route::get('/maker/dashboard',    [DashboardController::class, 'maker'])   ->name('maker.dashboard')   ->middleware('\App\Http\Middleware\CheckRole:course_head');
    Route::get('/approver/dashboard', [DashboardController::class, 'approver'])->name('approver.dashboard')->middleware('\App\Http\Middleware\CheckRole:executive');
    // M11 — ผู้บริหารพิจารณารายวิชา (read-only + อนุมัติ/ตีกลับ)
    Route::middleware('\App\Http\Middleware\CheckRole:executive')->group(function () {
        Route::get('/approver/offerings/rejected', [\App\Http\Controllers\Executive\OfferingApprovalController::class, 'rejectedQueue'])->name('approver.offerings.rejected');
        Route::get('/approver/offerings/{courseOffering}', [\App\Http\Controllers\Executive\OfferingApprovalController::class, 'show'])->name('approver.offerings.show');
        Route::post('/approver/offerings/{courseOffering}/approve', [\App\Http\Controllers\Executive\OfferingApprovalController::class, 'approve'])->name('approver.offerings.approve');
        Route::post('/approver/offerings/{courseOffering}/reject', [\App\Http\Controllers\Executive\OfferingApprovalController::class, 'reject'])->name('approver.offerings.reject');
    });
    Route::get('/lecturer/dashboard', [DashboardController::class, 'lecturer'])->name('lecturer.dashboard')->middleware('\App\Http\Middleware\CheckRole:instructor');
    Route::put('/lecturer/dashboard/pa', [PaController::class, 'update'])->name('lecturer.pa.update')->middleware('\App\Http\Middleware\CheckRole:instructor');

    // M6 — หน้ารายงานภาระงานชุดเดียวกันสำหรับผู้ดูแลระบบ เจ้าหน้าที่ และผู้บริหาร
    Route::get('/staff/reports/workload', [\App\Http\Controllers\Admin\WorkloadReportController::class, 'index'])
        ->name('staff.reports.workload')
        ->middleware('\App\Http\Middleware\CheckRole:staff');
    Route::middleware('\App\Http\Middleware\CheckRole:staff')->group(function () {
        Route::get('/staff/reports/schedules', [ScheduleReportController::class, 'index'])->name('staff.reports.schedules');
        Route::get('/staff/reports/schedules/excel', [ScheduleReportController::class, 'excel'])->name('staff.reports.schedules.excel');
        Route::get('/staff/reports/schedules/pdf', [ScheduleReportController::class, 'pdf'])->name('staff.reports.schedules.pdf');
    });
    Route::get('/approver/reports/workload', [\App\Http\Controllers\Admin\WorkloadReportController::class, 'index'])
        ->name('approver.reports.workload')
        ->middleware('\App\Http\Middleware\CheckRole:executive');
    Route::middleware('\App\Http\Middleware\CheckRole:executive')->group(function () {
        Route::get('/approver/reports/schedules', [ScheduleReportController::class, 'index'])->name('approver.reports.schedules');
        Route::get('/approver/reports/schedules/excel', [ScheduleReportController::class, 'excel'])->name('approver.reports.schedules.excel');
        Route::get('/approver/reports/schedules/pdf', [ScheduleReportController::class, 'pdf'])->name('approver.reports.schedules.pdf');
    });

    // V2 delegation: งานจัดตาราง (workspace/alerts/slot CRUD) เปิดให้หัวหน้าวิชา + อาจารย์ที่ถูกมอบหมาย + เจ้าหน้าที่ที่ดูแลวิชา
    // access จริงต่อ offering กรองด้วย CourseOffering::scopeSchedulableBy / canBeScheduledBy
    // (coordinator หรือ instructor schedule_permission='schedule' หรือ staff ใน course_staff)
    Route::middleware(['\App\Http\Middleware\CheckRole:course_head,instructor,staff'])->group(function () {
        Route::get('/maker/schedules', [ScheduleController::class, 'workspace'])->name('maker.schedules.index');
        Route::get('/maker/alerts', [ScheduleController::class, 'alerts'])->name('maker.alerts.index');
        Route::get('/maker/schedules/create', [ScheduleController::class, 'createGlobal'])->name('maker.schedules.create');
        Route::post('/maker/schedules', [ScheduleController::class, 'storeGlobal'])->name('maker.schedules.store');
    });

    // badge การชนบน sidebar = ใช้เฉพาะหัวหน้าวิชา (sidebar อาจารย์/เจ้าหน้าที่ไม่มี badge นี้)
    Route::middleware(['\App\Http\Middleware\CheckRole:course_head'])->group(function () {
        Route::get('/maker/conflict-badge-status', ConflictBadgeStatusController::class)
            ->name('maker.conflict_badge_status')
            ->middleware('throttle:30,1');
    });

    // จัดตาราง slot ต่อ offering — หัวหน้าวิชา + อาจารย์ที่ถูกมอบหมาย + เจ้าหน้าที่ที่ดูแลวิชา (delegation)
    Route::middleware(['\App\Http\Middleware\CheckRole:course_head,instructor,staff'])
        ->prefix('maker/course-offerings')
        ->name('maker.course_offerings.')
        ->group(function () {
            Route::get('/{courseOffering}/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
            Route::get('/{courseOffering}/schedules/create', [ScheduleController::class, 'create'])->name('schedules.create');
            Route::post('/{courseOffering}/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
            Route::post('/{courseOffering}/schedules/series', [ScheduleController::class, 'storeSeries'])->name('schedules.series.store');
            Route::get('/{courseOffering}/schedules/week-fragment', [ScheduleController::class, 'weekFragment'])->name('schedules.week_fragment');
            Route::post('/{courseOffering}/schedules/check-conflicts', [ScheduleController::class, 'checkConflicts'])->name('schedules.check_conflicts');
            Route::post('/{courseOffering}/schedules/copy-week/preview', [ScheduleController::class, 'previewCopyWeek'])->name('schedules.copy_week.preview');
            Route::post('/{courseOffering}/schedules/copy-week', [ScheduleController::class, 'copyWeek'])->name('schedules.copy_week');
            Route::put('/{courseOffering}/schedules/templates/{scheduleTemplate}', [ScheduleController::class, 'updateSeriesTemplate'])->name('schedules.templates.update');
            Route::delete('/{courseOffering}/schedules/templates/{scheduleTemplate}', [ScheduleController::class, 'destroySeriesTemplate'])->name('schedules.templates.destroy');
            Route::get('/{courseOffering}/schedules/{schedule}/edit', [ScheduleController::class, 'edit'])->name('schedules.edit');
            Route::put('/{courseOffering}/schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
            Route::delete('/{courseOffering}/schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
        });

    // จัดการ offering (ดูรายการ/รายละเอียด/แก้ไข/ชุดผู้สอน) — หัวหน้าวิชาเท่านั้น (อาจารย์ที่ช่วยจัดไม่แตะ pool/อนุมัติ)
    Route::middleware(['\App\Http\Middleware\CheckRole:course_head'])
        ->prefix('maker/course-offerings')
        ->name('maker.course_offerings.')
        ->group(function () {
            Route::get('/', [CourseOfferingController::class, 'index'])->name('index');
            Route::get('/{courseOffering}', [CourseOfferingController::class, 'show'])->name('show');
            // M11 — ส่งขออนุมัติ (draft/rejected → pending) + ล็อก Draft
            Route::post('/{courseOffering}/submit', [CourseOfferingController::class, 'submitForApproval'])->name('submit');
            Route::post('/{courseOffering}/instructors', [CourseOfferingController::class, 'storeInstructor'])->name('instructors.store');
            Route::patch('/{courseOffering}/instructors/{user}/role', [CourseOfferingController::class, 'updateInstructorRole'])->name('instructors.role');
            Route::patch('/{courseOffering}/instructors/{user}/permission', [CourseOfferingController::class, 'updateInstructorPermission'])->name('instructors.permission');
            Route::delete('/{courseOffering}/instructors/{user}', [CourseOfferingController::class, 'destroyInstructor'])->name('instructors.destroy');
            Route::post('/{courseOffering}/student-groups', [CourseOfferingController::class, 'storeStudentGroup'])->name('student_groups.store');
            Route::post('/{courseOffering}/student-groups/bulk', [CourseOfferingController::class, 'bulkStoreStudentGroups'])->name('student_groups.bulk_store');
            Route::post('/{courseOffering}/student-groups/save', [CourseOfferingController::class, 'saveStudentGroups'])->name('student_groups.save');
            Route::delete('/{courseOffering}/student-groups', [CourseOfferingController::class, 'destroyStudentGroups'])->name('student_groups.destroy_many');
            Route::put('/{courseOffering}/student-groups/{studentGroup}', [CourseOfferingController::class, 'updateStudentGroup'])->name('student_groups.update');
            Route::delete('/{courseOffering}/student-groups/{studentGroup}', [CourseOfferingController::class, 'destroyStudentGroup'])->name('student_groups.destroy');
        });

    // Admin User Management
    // ── Admin only ─────────────────────────────────────────────────────
    Route::middleware(['\App\Http\Middleware\CheckRole:admin'])->group(function () {
        Route::get('/admin/users', 'App\Http\Controllers\AdminUserController@index')->name('admin.users');
        Route::post('/admin/users', 'App\Http\Controllers\AdminUserController@store')->name('admin.users.store');
        Route::post('/admin/users/import', 'App\Http\Controllers\AdminUserController@importUsers')->name('admin.users.import');
        Route::put('/admin/users/{user}', 'App\Http\Controllers\AdminUserController@update')->name('admin.users.update');
        Route::patch('/admin/users/{user}/toggle', 'App\Http\Controllers\AdminUserController@toggleStatus')->name('admin.users.toggle');
        Route::delete('/admin/users/{user}', 'App\Http\Controllers\AdminUserController@destroy')->name('admin.users.destroy');
        Route::get('/admin/settings', 'App\Http\Controllers\AdminSettingController@index')->name('admin.settings');
        Route::post('/admin/settings/academic-years', 'App\Http\Controllers\AdminSettingController@storeYear')->name('admin.settings.years.store');
        Route::put('/admin/settings/academic-years/{year}', 'App\Http\Controllers\AdminSettingController@updateYear')->name('admin.settings.years.update');
        Route::delete('/admin/settings/academic-years/{year}', 'App\Http\Controllers\AdminSettingController@destroyYear')->name('admin.settings.years.destroy');
        Route::post('/admin/settings/update-constants', 'App\Http\Controllers\AdminSettingController@updateConstants')->name('admin.settings.constants.update');
        Route::patch('/admin/settings/scheduling/{year}/open', 'App\Http\Controllers\AdminSettingController@openSchedulingWindow')->name('admin.settings.scheduling.open');
        Route::patch('/admin/settings/scheduling/{year}/close', 'App\Http\Controllers\AdminSettingController@closeSchedulingWindow')->name('admin.settings.scheduling.close');
        // วันหยุดราชการ (V3 ข้อ 2.4)
        Route::post('/admin/settings/holidays', 'App\Http\Controllers\AdminSettingController@storeHoliday')->name('admin.settings.holidays.store');
        Route::put('/admin/settings/holidays/{holiday}', 'App\Http\Controllers\AdminSettingController@updateHoliday')->name('admin.settings.holidays.update');
        Route::delete('/admin/settings/holidays/{holiday}', 'App\Http\Controllers\AdminSettingController@destroyHoliday')->name('admin.settings.holidays.destroy');
        Route::post('/admin/settings/holidays/sync', 'App\Http\Controllers\AdminSettingController@syncHolidays')->name('admin.settings.holidays.sync');
        // ปฏิทินการศึกษาตามกลุ่ม (V4 ข้อ 8)
        Route::post('/admin/settings/academic-years/{year}/calendars', 'App\Http\Controllers\AdminSettingController@storeCalendar')->name('admin.settings.calendars.store');
        Route::put('/admin/settings/calendars/{calendar}', 'App\Http\Controllers\AdminSettingController@updateCalendar')->name('admin.settings.calendars.update');
        Route::delete('/admin/settings/calendars/{calendar}', 'App\Http\Controllers\AdminSettingController@destroyCalendar')->name('admin.settings.calendars.destroy');

        Route::get('/admin/master-data', 'App\Http\Controllers\Admin\MasterDataController@index')->name('admin.master_data');
        Route::get('/admin/alerts', [AlertController::class, 'index'])->name('admin.alerts');
        Route::post('/admin/alerts/dismissed', [AlertController::class, 'updateDismissed'])->name('admin.alerts.dismissed');
        Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit_logs.index');
        Route::get('/admin/reports/workload', [\App\Http\Controllers\Admin\WorkloadReportController::class, 'index'])->name('admin.reports.workload');
        Route::get('/admin/reports/workload/export', [\App\Http\Controllers\Admin\WorkloadReportController::class, 'export'])->name('admin.reports.workload.export');
        Route::get('/admin/reports/schedules', [ScheduleReportController::class, 'index'])->name('admin.reports.schedules');
        Route::get('/admin/reports/schedules/excel', [ScheduleReportController::class, 'excel'])->name('admin.reports.schedules.excel');
        Route::get('/admin/reports/schedules/pdf', [ScheduleReportController::class, 'pdf'])->name('admin.reports.schedules.pdf');
        Route::post('/admin/master-data/departments', 'App\Http\Controllers\Admin\MasterDataController@storeDepartment')->name('admin.departments.store');
        Route::put('/admin/master-data/departments/{department}', 'App\Http\Controllers\Admin\MasterDataController@updateDepartment')->name('admin.departments.update');
        Route::delete('/admin/master-data/departments/{department}', 'App\Http\Controllers\Admin\MasterDataController@destroyDepartment')->name('admin.departments.destroy');
        Route::put('/admin/master-data/instructors/{id}', 'App\Http\Controllers\Admin\MasterDataController@updateInstructor')->name('admin.instructors.update');
        Route::post('/admin/master-data/location-types', 'App\Http\Controllers\Admin\MasterDataController@storeLocationType')->name('admin.location_types.store');
        Route::put('/admin/master-data/location-types/{locationType}', 'App\Http\Controllers\Admin\MasterDataController@updateLocationType')->name('admin.location_types.update');
        Route::delete('/admin/master-data/location-types/{locationType}', 'App\Http\Controllers\Admin\MasterDataController@destroyLocationType')->name('admin.location_types.destroy');
        Route::post('/admin/master-data/rooms', 'App\Http\Controllers\Admin\MasterDataController@storeRoom')->name('admin.rooms.store');
        Route::post('/admin/master-data/rooms/import', 'App\Http\Controllers\Admin\MasterDataController@importRooms')->name('admin.rooms.import');
        Route::put('/admin/master-data/rooms/{room}', 'App\Http\Controllers\Admin\MasterDataController@updateRoom')->name('admin.rooms.update');
        Route::delete('/admin/master-data/rooms/{room}', 'App\Http\Controllers\Admin\MasterDataController@destroyRoom')->name('admin.rooms.destroy');
        Route::post('/admin/master-data/courses', 'App\Http\Controllers\Admin\MasterDataController@storeCourse')->name('admin.courses.store');
        Route::post('/admin/master-data/courses/import', 'App\Http\Controllers\Admin\MasterDataController@importCourses')->name('admin.courses.import');
        Route::put('/admin/master-data/courses/{course}', 'App\Http\Controllers\Admin\MasterDataController@updateCourse')->name('admin.courses.update');
        Route::delete('/admin/master-data/courses/{course}', 'App\Http\Controllers\Admin\MasterDataController@destroyCourse')->name('admin.courses.destroy');
        Route::get('/admin/master-data/courses/{course}/instructor-deviation', 'App\Http\Controllers\Admin\MasterDataController@courseInstructorDeviation')->name('admin.courses.instructor_deviation');
        Route::get('/admin/master-data/courses/{course}/topics', 'App\Http\Controllers\Admin\MasterDataController@courseTopics')->name('admin.courses.topics.index');
        Route::post('/admin/master-data/courses/{course}/topics', 'App\Http\Controllers\Admin\MasterDataController@syncCourseTopics')->name('admin.courses.topics.sync');
        Route::post('/admin/master-data/curriculums', 'App\Http\Controllers\Admin\MasterDataController@storeCurriculum')->name('admin.curriculums.store');
        Route::put('/admin/master-data/curriculums/{curriculum}', 'App\Http\Controllers\Admin\MasterDataController@updateCurriculum')->name('admin.curriculums.update');
        Route::post('/admin/master-data/curriculums/{curriculum}/clone', 'App\Http\Controllers\Admin\MasterDataController@cloneCurriculum')->name('admin.curriculums.clone');
        Route::delete('/admin/master-data/curriculums/{curriculum}', 'App\Http\Controllers\Admin\MasterDataController@destroyCurriculum')->name('admin.curriculums.destroy');
        Route::post('/admin/master-data/activity-types', 'App\Http\Controllers\Admin\MasterDataController@storeActivityType')->name('admin.activity_types.store');
        Route::put('/admin/master-data/activity-types/{activityType}', 'App\Http\Controllers\Admin\MasterDataController@updateActivityType')->name('admin.activity_types.update');
        Route::delete('/admin/master-data/activity-types/{activityType}', 'App\Http\Controllers\Admin\MasterDataController@destroyActivityType')->name('admin.activity_types.destroy');

        // กลุ่มชั้นปี (cohort — V2) — Admin เท่านั้น (staff เห็น read-only ใน tab)
        Route::post('/admin/master-data/student-cohorts', 'App\Http\Controllers\Admin\MasterDataController@storeStudentCohort')->name('admin.student_cohorts.store');
        Route::put('/admin/master-data/student-cohorts/{studentCohort}', 'App\Http\Controllers\Admin\MasterDataController@updateStudentCohort')->name('admin.student_cohorts.update');
        Route::delete('/admin/master-data/student-cohorts/{studentCohort}', 'App\Http\Controllers\Admin\MasterDataController@destroyStudentCohort')->name('admin.student_cohorts.destroy');

    });

    // ── Staff only ─────────────────────────────────────────────────────
    Route::middleware(['\App\Http\Middleware\CheckRole:staff'])->group(function () {
        Route::get('/staff/settings', 'App\Http\Controllers\Staff\SettingController@index')->name('staff.settings');
        Route::post('/staff/settings/academic-years', 'App\Http\Controllers\Staff\SettingController@storeYear')->name('staff.settings.years.store');
        Route::put('/staff/settings/academic-years/{year}', 'App\Http\Controllers\Staff\SettingController@updateYear')->name('staff.settings.years.update');
        Route::delete('/staff/settings/academic-years/{year}', 'App\Http\Controllers\Staff\SettingController@destroyYear')->name('staff.settings.years.destroy');
        Route::post('/staff/settings/holidays', 'App\Http\Controllers\Staff\SettingController@storeHoliday')->name('staff.settings.holidays.store');
        Route::put('/staff/settings/holidays/{holiday}', 'App\Http\Controllers\Staff\SettingController@updateHoliday')->name('staff.settings.holidays.update');
        Route::delete('/staff/settings/holidays/{holiday}', 'App\Http\Controllers\Staff\SettingController@destroyHoliday')->name('staff.settings.holidays.destroy');
        Route::post('/staff/settings/holidays/sync', 'App\Http\Controllers\Staff\SettingController@syncHolidays')->name('staff.settings.holidays.sync');
        // ปฏิทินการศึกษาตามกลุ่ม (V4 ข้อ 8)
        Route::post('/staff/settings/academic-years/{year}/calendars', 'App\Http\Controllers\Staff\SettingController@storeCalendar')->name('staff.settings.calendars.store');
        Route::put('/staff/settings/calendars/{calendar}', 'App\Http\Controllers\Staff\SettingController@updateCalendar')->name('staff.settings.calendars.update');
        Route::delete('/staff/settings/calendars/{calendar}', 'App\Http\Controllers\Staff\SettingController@destroyCalendar')->name('staff.settings.calendars.destroy');

        Route::get('/staff/master-data', 'App\Http\Controllers\Staff\MasterDataController@index')->name('staff.master_data');
        Route::post('/staff/master-data/departments', 'App\Http\Controllers\Staff\MasterDataController@storeDepartment')->name('staff.departments.store');
        Route::put('/staff/master-data/departments/{department}', 'App\Http\Controllers\Staff\MasterDataController@updateDepartment')->name('staff.departments.update');
        Route::delete('/staff/master-data/departments/{department}', 'App\Http\Controllers\Staff\MasterDataController@destroyDepartment')->name('staff.departments.destroy');
        Route::put('/staff/master-data/instructors/{id}', 'App\Http\Controllers\Staff\MasterDataController@updateInstructor')->name('staff.instructors.update');
        Route::post('/staff/master-data/location-types', 'App\Http\Controllers\Staff\MasterDataController@storeLocationType')->name('staff.location_types.store');
        Route::put('/staff/master-data/location-types/{locationType}', 'App\Http\Controllers\Staff\MasterDataController@updateLocationType')->name('staff.location_types.update');
        Route::delete('/staff/master-data/location-types/{locationType}', 'App\Http\Controllers\Staff\MasterDataController@destroyLocationType')->name('staff.location_types.destroy');
        Route::post('/staff/master-data/rooms', 'App\Http\Controllers\Staff\MasterDataController@storeRoom')->name('staff.rooms.store');
        Route::post('/staff/master-data/rooms/import', 'App\Http\Controllers\Staff\MasterDataController@importRooms')->name('staff.rooms.import');
        Route::put('/staff/master-data/rooms/{room}', 'App\Http\Controllers\Staff\MasterDataController@updateRoom')->name('staff.rooms.update');
        Route::delete('/staff/master-data/rooms/{room}', 'App\Http\Controllers\Staff\MasterDataController@destroyRoom')->name('staff.rooms.destroy');
        Route::post('/staff/master-data/courses', 'App\Http\Controllers\Staff\MasterDataController@storeCourse')->name('staff.courses.store');
        Route::post('/staff/master-data/courses/import', 'App\Http\Controllers\Staff\MasterDataController@importCourses')->name('staff.courses.import');
        Route::put('/staff/master-data/courses/{course}', 'App\Http\Controllers\Staff\MasterDataController@updateCourse')->name('staff.courses.update');
        Route::delete('/staff/master-data/courses/{course}', 'App\Http\Controllers\Staff\MasterDataController@destroyCourse')->name('staff.courses.destroy');
        Route::get('/staff/master-data/courses/{course}/instructor-deviation', 'App\Http\Controllers\Staff\MasterDataController@courseInstructorDeviation')->name('staff.courses.instructor_deviation');
        Route::get('/staff/master-data/courses/{course}/topics', 'App\Http\Controllers\Staff\MasterDataController@courseTopics')->name('staff.courses.topics.index');
        Route::post('/staff/master-data/courses/{course}/topics', 'App\Http\Controllers\Staff\MasterDataController@syncCourseTopics')->name('staff.courses.topics.sync');
        Route::post('/staff/master-data/curriculums', 'App\Http\Controllers\Staff\MasterDataController@storeCurriculum')->name('staff.curriculums.store');
        Route::put('/staff/master-data/curriculums/{curriculum}', 'App\Http\Controllers\Staff\MasterDataController@updateCurriculum')->name('staff.curriculums.update');
        Route::post('/staff/master-data/curriculums/{curriculum}/clone', 'App\Http\Controllers\Staff\MasterDataController@cloneCurriculum')->name('staff.curriculums.clone');
        Route::delete('/staff/master-data/curriculums/{curriculum}', 'App\Http\Controllers\Staff\MasterDataController@destroyCurriculum')->name('staff.curriculums.destroy');
        Route::post('/staff/master-data/activity-types', 'App\Http\Controllers\Staff\MasterDataController@storeActivityType')->name('staff.activity_types.store');
        Route::put('/staff/master-data/activity-types/{activityType}', 'App\Http\Controllers\Staff\MasterDataController@updateActivityType')->name('staff.activity_types.update');
        Route::delete('/staff/master-data/activity-types/{activityType}', 'App\Http\Controllers\Staff\MasterDataController@destroyActivityType')->name('staff.activity_types.destroy');
        Route::post('/staff/master-data/student-cohorts', 'App\Http\Controllers\Staff\MasterDataController@storeStudentCohort')->name('staff.student_cohorts.store');
        Route::put('/staff/master-data/student-cohorts/{studentCohort}', 'App\Http\Controllers\Staff\MasterDataController@updateStudentCohort')->name('staff.student_cohorts.update');
        Route::delete('/staff/master-data/student-cohorts/{studentCohort}', 'App\Http\Controllers\Staff\MasterDataController@destroyStudentCohort')->name('staff.student_cohorts.destroy');

    });

    // Role switcher
    Route::post('/switch-role', [DashboardController::class, 'switchRole'])->name('switch-role');

    // Profile Management
    Route::put('/profile/password', [AuthController::class, 'updatePassword'])->name('profile.password.update');
});
