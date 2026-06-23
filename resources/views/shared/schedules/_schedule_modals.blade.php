        @php
            $modalSchedules = $modalSchedules ?? collect();
            $activityTypes = $activityTypes ?? collect();
            $rooms = $rooms ?? collect();
            $scheduleConflicts = $scheduleConflicts ?? collect();
            $thaiDays = $thaiDays ?? [
                1 => 'วันจันทร์',
                2 => 'วันอังคาร',
                3 => 'วันพุธ',
                4 => 'วันพฤหัสบดี',
                5 => 'วันศุกร์',
                6 => 'วันเสาร์',
                7 => 'วันอาทิตย์',
            ];
            $formatDate = $formatDate ?? fn ($date) => $date ? \App\Support\ThaiDate::date($date) : '-';
            $formatTime = $formatTime ?? fn ($value) => substr((string) $value, 0, 5);
            $formatDuration = $formatDuration ?? fn (int $minutes) => $minutes >= 60
                ? (int) floor($minutes / 60) . ' ชม.' . ($minutes % 60 ? ' ' . ($minutes % 60) . ' นาที' : '')
                : $minutes . ' นาที';
            $durationForSchedule = $durationForSchedule ?? function ($schedule) {
                $startTime = (string) $schedule->start_time;
                $endTime = (string) $schedule->end_time;
                $start = \Carbon\CarbonImmutable::createFromFormat('H:i:s', strlen($startTime) === 5 ? $startTime . ':00' : $startTime);
                $end = \Carbon\CarbonImmutable::createFromFormat('H:i:s', strlen($endTime) === 5 ? $endTime . ':00' : $endTime);

                return (int) max(0, $start->diffInMinutes($end));
            };
            $activityTone = $activityTone ?? function ($schedule) {
                $color = $schedule->activityType?->color_code ?: 'var(--brand-navy)';

                return str_starts_with((string) $color, '#') || str_starts_with((string) $color, 'oklch') || str_starts_with((string) $color, 'var(')
                    ? $color
                    : 'var(--brand-navy)';
            };
            $groupTone = $groupTone ?? function ($group) {
                $color = (string) ($group->color_code ?? '');

                return str_starts_with($color, '#') || str_starts_with($color, 'oklch') || str_starts_with($color, 'var(')
                    ? $color
                    : 'oklch(58% 0.095 84)';
            };
            $eligibleScheduleInstructors = $eligibleScheduleInstructors ?? function ($offering) {
                $pool = $offering?->instructorPool ?? collect();

                return $pool
                    ->sortBy(fn ($instructor) => $instructor->formatted_name ?? $instructor->name)
                    ->values();
            };
            $instructorDepartmentMismatch = $instructorDepartmentMismatch ?? function ($offering, $instructor) {
                $departmentId = $offering?->course?->department_id;

                return $departmentId
                    && (int) ($instructor?->instructorProfile?->department_id) !== (int) $departmentId;
            };
            $scheduleDepartmentInstructors = $scheduleDepartmentInstructors ?? function ($schedule) use ($eligibleScheduleInstructors) {
                $eligibleIds = $eligibleScheduleInstructors($schedule?->courseOffering)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                return $schedule?->instructors
                    ? $schedule->instructors
                        ->filter(fn ($instructor) => in_array((int) $instructor->id, $eligibleIds, true))
                        ->values()
                    : collect();
            };
            $scheduleIncompleteReasons = $scheduleIncompleteReasons ?? function ($schedule) use ($scheduleDepartmentInstructors) {
                return collect([
                    $scheduleDepartmentInstructors($schedule)->isEmpty() ? 'รอกำหนดผู้สอน' : null,
                ])->filter()->values();
            };
            $scheduleAlertMessages = $scheduleAlertMessages ?? function ($errors, ?string $key = null) {
                $messages = $key && $errors->has($key)
                    ? collect($errors->get($key))
                    : collect($errors->all());

                return $messages
                    ->flatMap(fn ($message) => preg_split('/\s+\/\s+/u', (string) $message) ?: [])
                    ->map(fn ($message) => trim((string) $message))
                    ->filter()
                    ->unique()
                    ->values();
            };
            $conflictFieldNote = $conflictFieldNote ?? function ($conflicts, array $types, string $fieldLabel) {
                $items = collect($conflicts)
                    ->filter(fn ($conflict) => in_array($conflict['type'] ?? '', $types, true))
                    ->values();

                return $items->isEmpty()
                    ? null
                    : $fieldLabel . 'มีข้อมูลซ้ำกับรายการอื่น ' . $items->count() . ' จุด';
            };
            $scheduleResourceCopyItems = $scheduleResourceCopyItems ?? collect();
            $scheduleReturnUrl = $scheduleReturnUrl ?? request()->fullUrl();
            $scheduleGroupManageUrl = $scheduleGroupManageUrl ?? function ($offering) {
                // return_to = หน้า schedule เต็ม (ไม่ใช่ week-fragment ที่ modal โหลดผ่าน AJAX)
                return route('maker.course_offerings.show', [
                    'courseOffering' => $offering,
                    'return_to' => route('maker.course_offerings.schedules.index', $offering),
                ]) . '#student-groups';
            };
        @endphp

        @foreach($modalSchedules as $schedule)
            @php
                $activity = $schedule->activityType;
                $room = $schedule->room;
                $offering = $schedule->courseOffering;
                $offeringCourse = $offering?->course;
                $timeText = $formatTime($schedule->start_time) . '-' . $formatTime($schedule->end_time);
                $dateText = $schedule->start_date?->format('Y-m-d') === $schedule->end_date?->format('Y-m-d')
                    ? $formatDate($schedule->start_date)
                    : ($formatDate($schedule->start_date) . ' - ' . $formatDate($schedule->end_date));
                $scheduleCanEdit = $offering?->academicYear?->phase === 'scheduling';
                $detailInstructors = $scheduleDepartmentInstructors($schedule);
                $detailIncompleteReasons = $scheduleIncompleteReasons($schedule);
            @endphp
            @if($lazyModal ?? false)
                <div data-lazy-schedule-modal="{{ $schedule->id }}">
            @endif
            <div class="schedule-modal-backdrop is-detail-modal" x-show="detailModal === 'schedule-{{ $schedule->id }}'" x-cloak @click.self="detailModal = null" data-testid="schedule-detail-modal" data-schedule-modal-id="{{ $schedule->id }}">
                <template x-if="detailModal === 'schedule-{{ $schedule->id }}'">
                    <section class="schedule-modal" role="dialog" aria-modal="true" aria-labelledby="schedule-detail-title-{{ $schedule->id }}" style="--activity-color: {{ $activityTone($schedule) }};">
                    <div class="modal-handle"></div>
                    <div class="modal-head-detail">
                        <div style="min-width:0;">
                            <div class="modal-title-detail" id="schedule-detail-title-{{ $schedule->id }}" title="{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}">{{ $schedule->topic ?: ($activity?->name ?? 'รายการสอน') }}</div>
                            <span class="activity-tag" style="--activity-color: {{ $activityTone($schedule) }}; margin-top:5px;">{{ $activity?->name ?? 'กิจกรรม' }}</span>
                            @if($schedule->schedule_template_id)
                                <span class="series-badge" style="margin-top:5px;" data-tip="กิจกรรมทำซ้ำรายสัปดาห์">
                                    <span class="series-dot" aria-hidden="true"></span>
                                    <span>ทำซ้ำ</span>
                                    @if($schedule->series_week_number)
                                        <span>สัปดาห์ {{ $schedule->series_week_number }}</span>
                                    @endif
                                </span>
                            @endif
                            <span style="display:inline-flex;margin-top:5px;">
                                @include('shared.schedules._incomplete_badge', ['reasons' => $detailIncompleteReasons])
                            </span>
                        </div>
                        <button type="button" class="modal-close" @click="detailModal = null" aria-label="ปิด">×</button>
                    </div>
                    <div class="detail-body">
                        <div class="detail-grid">
                            <div class="detail-row">
                                <div class="detail-row-label">วันที่</div>
                                <div class="detail-row-value">{{ $dateText }} · {{ $timeText }} <span class="sub">({{ $formatDuration($durationForSchedule($schedule)) }})</span></div>
                            </div>
                            @if($schedule->schedule_template_id)
                                <div class="detail-row">
                                    <div class="detail-row-label">ชุดทำซ้ำ</div>
                                    <div class="detail-row-value">รายการนี้มาจากกิจกรรมรายสัปดาห์ ห้องและกลุ่มนักศึกษาปรับรายสัปดาห์ได้</div>
                                </div>
                            @endif
                            <div class="detail-row">
                                <div class="detail-row-label">รายวิชา</div>
                                <div class="detail-row-value">{{ $offeringCourse?->course_code ?? '-' }} {{ $offeringCourse?->name_th ?? $offeringCourse?->name_en ?? '' }}</div>
                            </div>
                            <div class="detail-row" style="align-items:flex-start;">
                                <div class="detail-row-label" style="padding-top:1px;">ผู้สอน</div>
                                <div class="detail-row-value">
                                    @if($detailInstructors->isNotEmpty())
                                        <div style="display:flex;flex-direction:column;gap:3px;">
                                            @foreach($detailInstructors as $inst)
                                                @php
                                                    $roleObj = $inst->pivot?->courseRole;
                                                    $roleName = $roleObj?->name_th ?? 'อาจารย์ผู้สอน';
                                                @endphp
                                                <div>
                                                    <span>{{ $inst->formatted_name ?? $inst->name }}</span>
                                                    @if($roleName !== 'อาจารย์ผู้สอน')<span style="color:var(--fg-3);font-size:11px;margin-left:4px;">({{ $roleName }})</span>@endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span style="color:var(--fg-3);">-</span>
                                    @endif
                                </div>
                            </div>
                            <div class="detail-row" style="align-items:flex-start;">
                                <div class="detail-row-label" style="padding-top:1px;">กลุ่มนักศึกษา</div>
                                <div class="detail-row-value">
                                    @if($schedule->studentGroups->isNotEmpty())
                                        <div class="detail-groups-list">
                                            @foreach($schedule->studentGroups as $group)
                                                <span class="co-group-badge" style="--group-color: {{ $groupTone($group) }};">
                                                    <span class="co-group-dot" aria-hidden="true"></span>
                                                    <span>{{ $group->group_code }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span style="color:var(--fg-3);">-</span>
                                    @endif
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-row-label">สถานที่</div>
                                <div class="detail-row-value">
                                    @if($room?->room_name || $room?->room_code)
                                        {{ $room->room_name ?? $room->room_code }}@if($room?->building) <span class="sub">· {{ $room->building }}</span>@endif
                                    @else
                                        <span style="color:var(--fg-3);">-</span>
                                    @endif
                                </div>
                            </div>
                            @if($schedule->remark)
                                <div class="detail-row">
                                    <div class="detail-row-label">หมายเหตุ</div>
                                    <div class="detail-row-value" style="color:var(--fg-2);">{{ $schedule->remark }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                    @if($scheduleCanEdit)
                        <div class="modal-actions schedule-detail-actions {{ $schedule->schedule_template_id ? 'has-many-actions' : 'has-two-actions' }} {{ ($schedule->schedule_template_id && $schedule->scheduleTemplate) ? 'has-series-delete-options' : '' }}">
                            <form id="delete-schedule-{{ $schedule->id }}" method="POST" action="{{ route('maker.course_offerings.schedules.destroy', [$offering, $schedule]) }}" style="display:none;">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="return_url" value="{{ $scheduleReturnUrl }}">
                            </form>
                            @if($schedule->schedule_template_id && $schedule->scheduleTemplate)
                                <form id="delete-series-from-{{ $schedule->id }}" method="POST" action="{{ route('maker.course_offerings.schedules.destroy', [$offering, $schedule]) }}" style="display:none;">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="series_delete_scope" value="from_current">
                                    <input type="hidden" name="return_url" value="{{ $scheduleReturnUrl }}">
                                </form>
                                <form id="delete-series-all-{{ $schedule->id }}" method="POST" action="{{ route('maker.course_offerings.schedules.destroy', [$offering, $schedule]) }}" style="display:none;">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="series_delete_scope" value="all">
                                    <input type="hidden" name="return_url" value="{{ $scheduleReturnUrl }}">
                                </form>
                            @endif
                            @php
                                $seriesLabel = $schedule->topic ?: ($activity?->name ?? 'รายการทำซ้ำ');
                                $weekLabel = $schedule->series_week_number ? ' (สัปดาห์ ' . $schedule->series_week_number . ')' : '';
                                $seriesTimeLabel = $schedule->start_time && $schedule->end_time
                                    ? ' · ' . substr((string) $schedule->start_time, 0, 5) . '-' . substr((string) $schedule->end_time, 0, 5)
                                    : '';
                            @endphp
                            @if($schedule->schedule_template_id)
                                <button type="button" class="btn btn-secondary schedule-detail-edit-action" data-testid="schedule-series-edit-modal-trigger" @click="openSeriesEdit('{{ $schedule->id }}')" style="display:inline-flex;align-items:center;gap:5px;">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"></path><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><path d="M7 23l-4-4 4-4"></path><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>
                                    แก้ชุดทำซ้ำ
                                </button>
                            @endif
                            <button type="button" class="btn btn-secondary schedule-detail-edit-action" data-testid="schedule-edit-modal-trigger" @click="openEdit('{{ $schedule->id }}')" style="display:inline-flex;align-items:center;gap:5px;">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                แก้ไข
                            </button>
                            @if($schedule->schedule_template_id && $schedule->scheduleTemplate)
                                <button type="button" class="btn {{ session('active_role') === 'course_head' ? 'btn-red' : 'btn-secondary' }} schedule-detail-delete-action" data-form="delete-series-from-{{ $schedule->id }}" data-label="{{ $seriesLabel }}{{ $weekLabel }} ตั้งแต่ {{ $formatDate($schedule->start_date ?? $schedule->teaching_date) }}{{ $seriesTimeLabel }}" data-warn="ระบบจะลบรายการนี้และรายการสัปดาห์ถัดไปในชุดเดียวกัน ส่วนสัปดาห์ก่อนหน้าจะยังอยู่" onclick="tpssDelete(this)" data-testid="schedule-series-delete-from-button" style="display:inline-flex;align-items:center;gap:5px;">
                                    ลบตั้งแต่สัปดาห์นี้
                                </button>
                                <button type="button" class="btn btn-red schedule-detail-delete-action" data-form="delete-series-all-{{ $schedule->id }}" data-label='ชุดทำซ้ำ "{{ $seriesLabel }}"{{ $seriesTimeLabel }}' data-warn="ระบบจะลบรายการทุกสัปดาห์ในชุดทำซ้ำนี้ทั้งหมด" onclick="tpssDelete(this)" data-testid="schedule-series-delete-all-button" style="display:inline-flex;align-items:center;gap:5px;">
                                    ลบทั้งชุด
                                </button>
                            @endif
                            <button type="button" class="btn btn-red schedule-detail-delete-action" data-form="delete-schedule-{{ $schedule->id }}" data-label="{{ $seriesLabel }}{{ $weekLabel }} · {{ $timeText }}" onclick="tpssDelete(this)" data-testid="schedule-delete-button" style="display:inline-flex;align-items:center;gap:5px;">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                {{ $schedule->schedule_template_id ? 'ลบการ์ดนี้' : 'ลบ' }}
                            </button>
                        </div>
                    @endif
                </section>
                </template>
            </div>

            @if($scheduleCanEdit && $schedule->scheduleTemplate)
                @php
                    $seriesTemplate = $schedule->scheduleTemplate;
                    $seriesUsesOld = (string) old('edit_series_template_id') === (string) $schedule->id;
                    $seriesOld = fn (string $key, mixed $default = null) => $seriesUsesOld ? old($key, $default) : $default;
                @endphp
                <div class="schedule-modal-backdrop" x-show="editSeriesModal === 'schedule-{{ $schedule->id }}'" x-cloak @click.self="editSeriesModal = null" data-testid="schedule-series-edit-modal">
                    <template x-if="editSeriesModal === 'schedule-{{ $schedule->id }}'">
                        <section class="schedule-modal is-form" role="dialog" aria-modal="true" aria-labelledby="schedule-series-edit-title-{{ $schedule->id }}">
                            <div class="modal-handle"></div>
                            <div class="modal-head">
                                <div>
                                    <div class="modal-title" id="schedule-series-edit-title-{{ $schedule->id }}">แก้ชุดทำซ้ำรายสัปดาห์</div>
                                    <div style="font-size:12px;font-weight:700;color:var(--fg-2);margin-top:3px;">การเปลี่ยนวัน เวลา ประเภทกิจกรรม และหัวข้อ จะ sync ไปยังรายการในชุดนี้ โดยไม่ทับห้องและกลุ่มรายสัปดาห์</div>
                                </div>
                                <button type="button" class="modal-close" @click="editSeriesModal = null" aria-label="ปิด">×</button>
                            </div>
                            <form method="POST" action="{{ route('maker.course_offerings.schedules.templates.update', [$offering, $seriesTemplate]) }}" data-testid="schedule-series-edit-form">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="modal_mode" value="series_edit">
                                <input type="hidden" name="edit_series_template_id" value="{{ $schedule->id }}">
                                <input type="hidden" name="return_url" value="{{ $scheduleReturnUrl }}">
                                <div class="modal-form-body">
                                    @if($seriesUsesOld && $errors->any())
                                        @php
                                            $alertMessages = $scheduleAlertMessages($errors);
                                        @endphp
                                        <div class="schedule-empty" style="margin-bottom:12px;border-color:var(--status-conflict-border);background:var(--status-conflict-bg);color:var(--status-conflict-fg);font-weight:800;text-align:left;">
                                            @foreach($alertMessages as $message)
                                                <div style="{{ ! $loop->last ? 'margin-bottom:6px;' : '' }}">{{ $message }}</div>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="modal-form-grid">
                                        <div class="modal-field-full">
                                            <label class="modal-label" for="series_edit_weekday_{{ $schedule->id }}">วันในสัปดาห์ <span class="required-mark">*</span></label>
                                            <select id="series_edit_weekday_{{ $schedule->id }}" name="weekday" required class="modal-control">
                                                @foreach($thaiDays as $dayIso => $dayName)
                                                    <option value="{{ $dayIso }}" @selected((string) $seriesOld('weekday', $seriesTemplate->weekday) === (string) $dayIso)>{{ $dayName }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="modal-label" for="series_edit_start_week_{{ $schedule->id }}">สัปดาห์เริ่ม <span class="required-mark">*</span></label>
                                            <input id="series_edit_start_week_{{ $schedule->id }}" name="start_week" type="number" min="1" max="{{ max(1, (int) ($offering->teaching_weeks ?? 52)) }}" required class="modal-control" value="{{ $seriesOld('start_week', $seriesTemplate->start_week) }}">
                                        </div>
                                        <div>
                                            <label class="modal-label" for="series_edit_end_week_{{ $schedule->id }}">สัปดาห์สิ้นสุด <span class="required-mark">*</span></label>
                                            <input id="series_edit_end_week_{{ $schedule->id }}" name="end_week" type="number" min="1" max="{{ max(1, (int) ($offering->teaching_weeks ?? 52)) }}" required class="modal-control" value="{{ $seriesOld('end_week', $seriesTemplate->end_week) }}">
                                        </div>
                                        <div>
                                            <label class="modal-label" for="series_edit_start_time_{{ $schedule->id }}">เวลาเริ่ม <span class="required-mark">*</span></label>
                                            @php
                                                $seriesStart = (string) $seriesOld('start_time', substr((string) $seriesTemplate->start_time, 0, 5));
                                                [$seriesStartHour, $seriesStartMin] = preg_match('/^\d{2}:\d{2}$/', $seriesStart)
                                                    ? explode(':', $seriesStart)
                                                    : ['08', '00'];
                                            @endphp
                                            <input type="hidden" id="series_edit_start_time_{{ $schedule->id }}" name="start_time" value="{{ $seriesStart }}">
                                            <div class="time-picker-group">
                                                <div class="time-picker" id="tp_series_edit_start_{{ $schedule->id }}" data-tp-hidden="series_edit_start_time_{{ $schedule->id }}" tabindex="0">
                                                    <span class="tp-val tp-val-hour">{{ $seriesStartHour }}</span>
                                                    <span class="time-separator">:</span>
                                                    <span class="tp-val tp-val-min">{{ $seriesStartMin }}</span>
                                                    <div class="tp-drop">
                                                        <div class="tp-drop-columns">
                                                            <div class="tp-col tp-col-hour">
                                                                <ul>
                                                                    @for($cycle = 0; $cycle < 3; $cycle++)
                                                                        @for($h = 0; $h < 24; $h++)
                                                                            @php $hh = sprintf('%02d', $h); @endphp
                                                                            <li data-val="{{ $hh }}" data-cycle="{{ $cycle }}" class="tp-hour-item {{ $cycle === 1 && $hh === $seriesStartHour ? 'tp-sel' : '' }}">{{ $hh }}</li>
                                                                        @endfor
                                                                    @endfor
                                                                </ul>
                                                            </div>
                                                            <div class="tp-col-divider">:</div>
                                                            <div class="tp-col tp-col-min">
                                                                <ul>
                                                                    @for($cycle = 0; $cycle < 3; $cycle++)
                                                                        @foreach(range(0, 59) as $m)
                                                                            @php $mm = sprintf('%02d', $m); @endphp
                                                                            <li data-val="{{ $mm }}" data-cycle="{{ $cycle }}" class="tp-min-item {{ $cycle === 1 && $mm === $seriesStartMin ? 'tp-sel' : '' }}">{{ $mm }}</li>
                                                                        @endforeach
                                                                    @endfor
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <span class="time-unit">น.</span>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="modal-label" for="series_edit_end_time_{{ $schedule->id }}">เวลาสิ้นสุด <span class="required-mark">*</span></label>
                                            @php
                                                $seriesEnd = (string) $seriesOld('end_time', substr((string) $seriesTemplate->end_time, 0, 5));
                                                [$seriesEndHour, $seriesEndMin] = preg_match('/^\d{2}:\d{2}$/', $seriesEnd)
                                                    ? explode(':', $seriesEnd)
                                                    : ['09', '00'];
                                            @endphp
                                            <input type="hidden" id="series_edit_end_time_{{ $schedule->id }}" name="end_time" value="{{ $seriesEnd }}">
                                            <div class="time-picker-group">
                                                <div class="time-picker" id="tp_series_edit_end_{{ $schedule->id }}" data-tp-hidden="series_edit_end_time_{{ $schedule->id }}" tabindex="0">
                                                    <span class="tp-val tp-val-hour">{{ $seriesEndHour }}</span>
                                                    <span class="time-separator">:</span>
                                                    <span class="tp-val tp-val-min">{{ $seriesEndMin }}</span>
                                                    <div class="tp-drop">
                                                        <div class="tp-drop-columns">
                                                            <div class="tp-col tp-col-hour">
                                                                <ul>
                                                                    @for($cycle = 0; $cycle < 3; $cycle++)
                                                                        @for($h = 0; $h < 24; $h++)
                                                                            @php $hh = sprintf('%02d', $h); @endphp
                                                                            <li data-val="{{ $hh }}" data-cycle="{{ $cycle }}" class="tp-hour-item {{ $cycle === 1 && $hh === $seriesEndHour ? 'tp-sel' : '' }}">{{ $hh }}</li>
                                                                        @endfor
                                                                    @endfor
                                                                </ul>
                                                            </div>
                                                            <div class="tp-col-divider">:</div>
                                                            <div class="tp-col tp-col-min">
                                                                <ul>
                                                                    @for($cycle = 0; $cycle < 3; $cycle++)
                                                                        @foreach(range(0, 59) as $m)
                                                                            @php $mm = sprintf('%02d', $m); @endphp
                                                                            <li data-val="{{ $mm }}" data-cycle="{{ $cycle }}" class="tp-min-item {{ $cycle === 1 && $mm === $seriesEndMin ? 'tp-sel' : '' }}">{{ $mm }}</li>
                                                                        @endforeach
                                                                    @endfor
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <span class="time-unit">น.</span>
                                            </div>
                                        </div>
                                        <div class="modal-field-full">
                                            <label class="modal-label" for="series_edit_activity_type_id_{{ $schedule->id }}">ประเภทกิจกรรม <span class="required-mark">*</span></label>
                                            <select id="series_edit_activity_type_id_{{ $schedule->id }}" name="activity_type_id" required class="modal-control">
                                                @foreach($activityTypes as $activityType)
                                                    <option value="{{ $activityType->id }}" @selected((string) $seriesOld('activity_type_id', $seriesTemplate->activity_type_id) === (string) $activityType->id)>
                                                        {{ $activityType->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @php $seriesTopicOptions = $offering?->course?->topics ?? collect(); @endphp
                                        <div class="modal-field-full">
                                            <label class="modal-label" for="series_edit_topic_{{ $schedule->id }}">หัวข้อกิจกรรม <span class="required-mark">*</span></label>
                                            <input id="series_edit_topic_{{ $schedule->id }}" name="topic" type="text" maxlength="255" required class="modal-control"
                                                @if($seriesTopicOptions->isNotEmpty()) list="series-edit-topic-options-{{ $schedule->id }}" autocomplete="off" @endif
                                                value="{{ $seriesOld('topic', $seriesTemplate->topic) }}" placeholder="{{ $seriesTopicOptions->isNotEmpty() ? 'เลือกหัวข้อสำเร็จรูป หรือพิมพ์เอง' : '' }}">
                                            @if($seriesTopicOptions->isNotEmpty())
                                                <datalist id="series-edit-topic-options-{{ $schedule->id }}">
                                                    @foreach($seriesTopicOptions as $topic)
                                                        <option value="{{ $topic->name }}"></option>
                                                    @endforeach
                                                </datalist>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-actions">
                                    <button type="button" class="btn btn-secondary" @click="editSeriesModal = null">ยกเลิก</button>
                                    <button type="submit" class="btn btn-primary" data-testid="schedule-series-submit">บันทึกชุดทำซ้ำ</button>
                                </div>
                            </form>
                        </section>
                    </template>
                </div>
            @endif

            @if($scheduleCanEdit)
                @php
                    $editUsesOld = (string) old('edit_schedule_id') === (string) $schedule->id;
                    $editDepartmentInstructors = $scheduleDepartmentInstructors($schedule);
                    $editInstructorIds = collect($editUsesOld ? old('instructor_ids', []) : $editDepartmentInstructors->pluck('id')->all())
                        ->map(fn ($id) => (string) $id)
                        ->all();
                    $editGroupIds = collect($editUsesOld ? old('student_group_ids', []) : $schedule->studentGroups->pluck('id')->all())
                        ->map(fn ($id) => (string) $id)
                        ->all();
                    $editLeadInstructorId = (string) ($editUsesOld
                        ? old('lead_instructor_id', '')
                        : ($editDepartmentInstructors->first(fn ($instructor) => (bool) $instructor->pivot?->is_lead)?->id ?? ''));
                    $editOld = fn (string $key, mixed $default = null) => $editUsesOld ? old($key, $default) : $default;
                    $editDateDisplay = function (string $key, $date) use ($editOld, $formatDate) {
                        $value = (string) $editOld($key, $date?->format('Y-m-d'));

                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                            return $formatDate(\Carbon\CarbonImmutable::parse($value));
                        }

                        return $value;
                    };
                    $editConflicts = $scheduleConflicts->get($schedule->id, collect());
                    $showConflictHints = $editConflicts->isNotEmpty();
                    $dateTimeConflictNote = $showConflictHints ? $conflictFieldNote($editConflicts, ['instructor_overlap', 'room_overlap', 'group_overlap'], 'วันและเวลา') : null;
                    $roomConflictNote = $showConflictHints ? $conflictFieldNote($editConflicts, ['room_overlap'], 'ห้อง/สถานที่') : null;
                    $instructorConflictNote = $showConflictHints ? $conflictFieldNote($editConflicts, ['instructor_overlap'], 'ผู้สอน') : null;
                    $groupConflictNote = $showConflictHints ? $conflictFieldNote($editConflicts, ['group_overlap'], 'กลุ่มนักศึกษา') : null;
                @endphp
                <div class="schedule-modal-backdrop" x-show="editModal === 'schedule-{{ $schedule->id }}'" x-cloak @click.self="closeEdit()" data-testid="schedule-edit-modal" data-schedule-modal-id="{{ $schedule->id }}">
                    <template x-if="editModal === 'schedule-{{ $schedule->id }}'">
                        <section class="schedule-modal is-form" role="dialog" aria-modal="true" aria-labelledby="schedule-edit-title-{{ $schedule->id }}">
                        <div class="modal-handle"></div>
                        <div class="modal-head">
                            <div>
                                <!-- removed edit tag -->
                                <div class="modal-title" id="schedule-edit-title-{{ $schedule->id }}">แก้ไขรายละเอียดกิจกรรม</div>
                            </div>
                            <button type="button" class="modal-close" @click="closeEdit()" aria-label="ปิด">×</button>
                        </div>
                        <form
                            method="POST"
                            action="{{ route('maker.course_offerings.schedules.update', [$offering, $schedule]) }}"
                            data-testid="schedule-edit-form"
                            data-schedule-success-toast="บันทึกสำเร็จ"
                            data-schedule-check
                            data-check-url="{{ route('maker.course_offerings.schedules.check_conflicts', $offering, false) }}"
                            @submit="beginScheduleSubmit()"
                            @input="queueScheduleCheck($el)"
                            @change="queueScheduleCheck($el)"
                            x-data="{
                                startDateDisplay: @js($editDateDisplay('start_date', $schedule->start_date)),
                                endDateDisplay: @js($editDateDisplay('end_date', $schedule->end_date)),
                                multiDay: @js((bool) ($schedule->start_date && $schedule->end_date && $schedule->start_date->toDateString() !== $schedule->end_date->toDateString())),
                                editInstructorSearch: '',
                                editGroupSearch: '',
                                resourceCopySource: '',
                                init() {
                                    this.$watch('multiDay', (v) => { if (!v) this.endDateDisplay = this.startDateDisplay; });
                                    this.$watch('startDateDisplay', (v) => { if (!this.multiDay) this.endDateDisplay = v; });
                                },
                            }"
                        >
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="modal_mode" value="edit">
                            <input type="hidden" name="edit_schedule_id" value="{{ $schedule->id }}">
                            <input type="hidden" name="schedule_id" value="{{ $schedule->id }}">
                            <input type="hidden" name="return_url" value="{{ $scheduleReturnUrl }}">
                            @if(request()->boolean('from_conflict'))
                                <input type="hidden" name="return_to_conflicts" value="1">
                            @endif
                            <div class="modal-form-body">
                                @if($editUsesOld && $errors->any())
                                    @php
                                        $alertMessages = $scheduleAlertMessages($errors);
                                    @endphp
                                    <div class="schedule-empty" style="margin-bottom:12px;border-color:var(--status-conflict-border);background:var(--status-conflict-bg);color:var(--status-conflict-fg);font-weight:800;text-align:left;">
                                        @foreach($alertMessages as $message)
                                            <div style="{{ ! $loop->last ? 'margin-bottom:6px;' : '' }}">{{ $message }}</div>
                                        @endforeach
                                    </div>
                                @endif

                                @if($schedule->schedule_template_id)
                                    @php
                                        $resourceCopyOptions = $scheduleResourceCopyItems
                                            ->filter(fn ($item) => $item['template_id'] === (string) $schedule->schedule_template_id && $item['id'] !== (string) $schedule->id)
                                            ->values();
                                    @endphp
                                    <div class="series-toggle-panel" style="margin-bottom:12px;">
                                        <span class="series-badge" data-tip="กิจกรรมทำซ้ำรายสัปดาห์">
                                            <span class="series-dot" aria-hidden="true"></span>
                                            <span>รายการทำซ้ำ</span>
                                            @if($schedule->series_week_number)
                                                <span>สัปดาห์ {{ $schedule->series_week_number }}</span>
                                            @endif
                                        </span>
                                        <div style="font-size:12px;font-weight:700;color:var(--fg-2);line-height:1.55;">
                                            ปรับ<strong>ห้อง · เวลา · ผู้สอน · กลุ่มนักศึกษา · หมายเหตุ</strong>แยกรายสัปดาห์ได้ ประเภทกิจกรรมและหัวข้อยึดตามชุดทำซ้ำ
                                        </div>
                                    </div>
                                    @if($resourceCopyOptions->isNotEmpty())
                                        <div class="resource-copy-panel">
                                            <div class="resource-copy-row">
                                                <div>
                                                    <label class="modal-label" for="resource_copy_{{ $schedule->id }}">ดึงรายละเอียดจากสัปดาห์อื่น</label>
                                                    <select id="resource_copy_{{ $schedule->id }}" class="modal-control" x-model="resourceCopySource">
                                                        <option value="">เลือกการ์ดต้นทาง</option>
                                                        @foreach($resourceCopyOptions as $copyOption)
                                                            <option value="{{ $copyOption['id'] }}">{{ $copyOption['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <button type="button" class="btn btn-secondary" @click="applyResourceCopy($el.closest('form'), resourceCopySource)" :disabled="!resourceCopySource">
                                                    คัดลอกรายละเอียด
                                                </button>
                                            </div>
                                            <div class="resource-copy-note">คัดลอกเฉพาะห้อง ผู้สอน กลุ่มนักศึกษา และหมายเหตุ แล้วกดบันทึกเพื่อใช้กับสัปดาห์นี้</div>
                                        </div>
                                    @endif
                                @endif

                                <div class="modal-form-grid">
                                    <div class="modal-field-full schedule-date-block {{ $dateTimeConflictNote ? 'modal-field-has-conflict' : '' }}">
                                        <div class="schedule-date-fields">
                                            <div>
                                                <label class="modal-label" for="edit_start_date_{{ $schedule->id }}"><span x-text="multiDay ? 'วันที่เริ่ม' : 'วันที่'">วันที่</span> <span class="required-mark">*</span></label>
                                                <x-thai-date-input
                                                    name="start_date"
                                                    :value="$editOld('start_date', $schedule->start_date?->format('Y-m-d'))"
                                                    id="edit_start_date_{{ $schedule->id }}"
                                                    class="modal-control"
                                                    :required="true"
                                                    :helper="false"
                                                    :year-start="$scheduleDatePickerYearStart"
                                                    :year-end="$scheduleDatePickerYearEnd"
                                                    x-model="startDateDisplay" />
                                                <div class="date-day-hint" x-show="scheduleDateHint(startDateDisplay)" x-cloak x-text="scheduleDateHint(startDateDisplay)"></div>
                                                <div class="date-holiday-warn" x-show="scheduleDateWarning(startDateDisplay)" x-cloak x-text="scheduleDateWarning(startDateDisplay)"></div>
                                                <label class="schedule-multiday-toggle">
                                                    <input type="checkbox" :checked="multiDay" @change="multiDay = $event.target.checked" data-testid="edit-multiday-toggle">
                                                    <span>กิจกรรมต่อเนื่องหลายวัน <small>(เช่น บล็อกฝึกปฏิบัติ)</small></span>
                                                </label>
                                            </div>
                                            <div x-show="multiDay" x-cloak>
                                                <label class="modal-label" for="edit_end_date_{{ $schedule->id }}">ถึงวันที่ <span class="required-mark">*</span></label>
                                                <x-thai-date-input
                                                    name="end_date"
                                                    :value="$editOld('end_date', $schedule->end_date?->format('Y-m-d'))"
                                                    id="edit_end_date_{{ $schedule->id }}"
                                                    class="modal-control"
                                                    :required="false"
                                                    :helper="false"
                                                    :year-start="$scheduleDatePickerYearStart"
                                                    :year-end="$scheduleDatePickerYearEnd"
                                                    x-bind:required="multiDay"
                                                    x-model="endDateDisplay" />
                                                <div class="date-day-hint" x-show="scheduleDateHint(endDateDisplay)" x-cloak x-text="scheduleDateHint(endDateDisplay)"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <template x-if="liveIssue('start_date').length || liveIssue('schedule').length">
                                        <div class="modal-field-full field-live-error" data-testid="live-error-start_date">
                                            <template x-for="msg in [...liveIssue('start_date'), ...liveIssue('schedule')]" :key="msg"><div x-text="msg"></div></template>
                                        </div>
                                    </template>
                                    <div class="{{ $dateTimeConflictNote ? 'modal-field-has-conflict' : '' }}">
                                        <label class="modal-label" for="edit_start_time_{{ $schedule->id }}">เวลาเริ่ม <span class="required-mark">*</span></label>
                                            @php
                                                $editStart = $editOld('start_time', $formatTime($schedule->start_time));
                                                [$editStartHour, $editStartMin] = $editStart ? explode(':', $editStart) : ['08','00'];
                                            @endphp
                                            <input type="hidden" id="edit_start_time_{{ $schedule->id }}" name="start_time" value="{{ $editStart }}">
                                            <div class="time-picker-group">
                                                <div class="time-picker" id="tp_edit_start_{{ $schedule->id }}" data-tp-hidden="edit_start_time_{{ $schedule->id }}" tabindex="0">
                                                    <span class="tp-val tp-val-hour">{{ $editStartHour ?? '08' }}</span>
                                                    <span class="time-separator">:</span>
                                                    <span class="tp-val tp-val-min">{{ $editStartMin ?? '00' }}</span>
                                                    <div class="tp-drop">
                                                        <div class="tp-drop-columns">
                                                            <div class="tp-col tp-col-hour">
                                                                <ul>
                                                                    @for($cycle = 0; $cycle < 3; $cycle++)
                                                                        @for($h = 0; $h < 24; $h++)
                                                                            @php $hh = sprintf('%02d', $h); @endphp
                                                                            <li data-val="{{ $hh }}" data-cycle="{{ $cycle }}" class="tp-hour-item {{ $cycle === 1 && $hh === ($editStartHour ?? '08') ? 'tp-sel' : '' }}">{{ $hh }}</li>
                                                                        @endfor
                                                                    @endfor
                                                                </ul>
                                                            </div>
                                                            <div class="tp-col-divider">:</div>
                                                            <div class="tp-col tp-col-min">
                                                                <ul>
                                                                    @for($cycle = 0; $cycle < 3; $cycle++)
                                                                        @foreach(range(0,59) as $m)
                                                                            @php $mm = sprintf('%02d', $m); @endphp
                                                                            <li data-val="{{ $mm }}" data-cycle="{{ $cycle }}" class="tp-min-item {{ $cycle === 1 && $mm === ($editStartMin ?? '00') ? 'tp-sel' : '' }}">{{ $mm }}</li>
                                                                        @endforeach
                                                                    @endfor
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <span class="time-unit">น.</span>
                                            </div>
                                    </div>
                                    <div class="{{ $dateTimeConflictNote ? 'modal-field-has-conflict' : '' }}">
                                        <label class="modal-label" for="edit_end_time_{{ $schedule->id }}">เวลาสิ้นสุด <span class="required-mark">*</span></label>
                                        @php
                                            $editEnd = $editOld('end_time', $formatTime($schedule->end_time));
                                            [$editEndHour, $editEndMin] = $editEnd ? explode(':', $editEnd) : ['09','00'];
                                        @endphp
                                        <input type="hidden" id="edit_end_time_{{ $schedule->id }}" name="end_time" value="{{ $editEnd }}">
                                        <div class="time-picker-group">
                                            <div class="time-picker" id="tp_edit_end_{{ $schedule->id }}" data-tp-hidden="edit_end_time_{{ $schedule->id }}" tabindex="0">
                                                <span class="tp-val tp-val-hour">{{ $editEndHour ?? '09' }}</span>
                                                <span class="time-separator">:</span>
                                                <span class="tp-val tp-val-min">{{ $editEndMin ?? '00' }}</span>
                                                <div class="tp-drop">
                                                    <div class="tp-drop-columns">
                                                        <div class="tp-col tp-col-hour">
                                                            <ul>
                                                                @for($cycle = 0; $cycle < 3; $cycle++)
                                                                    @for($h = 0; $h < 24; $h++)
                                                                        @php $hh = sprintf('%02d', $h); @endphp
                                                                        <li data-val="{{ $hh }}" data-cycle="{{ $cycle }}" class="tp-hour-item {{ $cycle === 1 && $hh === ($editEndHour ?? '09') ? 'tp-sel' : '' }}">{{ $hh }}</li>
                                                                    @endfor
                                                                @endfor
                                                            </ul>
                                                        </div>
                                                        <div class="tp-col-divider">:</div>
                                                        <div class="tp-col tp-col-min">
                                                            <ul>
                                                                @for($cycle = 0; $cycle < 3; $cycle++)
                                                                    @foreach(range(0,59) as $m)
                                                                        @php $mm = sprintf('%02d', $m); @endphp
                                                                        <li data-val="{{ $mm }}" data-cycle="{{ $cycle }}" class="tp-min-item {{ $cycle === 1 && $mm === ($editEndMin ?? '00') ? 'tp-sel' : '' }}">{{ $mm }}</li>
                                                                    @endforeach
                                                                @endfor
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="time-unit">น.</span>
                                        </div>
                                        @if($dateTimeConflictNote)
                                            <div class="modal-conflict-field" data-testid="schedule-edit-conflict-focus">{{ $dateTimeConflictNote }}</div>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="modal-label" for="edit_activity_type_id_{{ $schedule->id }}">ประเภทกิจกรรม <span class="required-mark">*</span></label>
                                        <select id="edit_activity_type_id_{{ $schedule->id }}" name="activity_type_id" required class="modal-control tpss-choices">
                                            @foreach($activityTypes as $activityType)
                                                <option value="{{ $activityType->id }}" @selected((string) $editOld('activity_type_id', $schedule->activity_type_id) === (string) $activityType->id)>
                                                    {{ $activityType->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="{{ $roomConflictNote ? 'modal-field-has-conflict' : '' }}">
                                        <label class="modal-label" for="edit_room_id_{{ $schedule->id }}">ห้อง/สถานที่</label>
                                        <select id="edit_room_id_{{ $schedule->id }}" name="room_id" class="modal-control tpss-choices" :class="liveIssue('room_id').length ? 'has-live-error' : (liveWarning('room_id').length ? 'has-live-warning' : '')">
                                            <option value="">ไม่ระบุสถานที่</option>
                                            @foreach($rooms as $roomOption)
                                                <option value="{{ $roomOption->id }}" @selected((string) $editOld('room_id', $schedule->room_id) === (string) $roomOption->id)>
                                                    {{ $roomOption->room_code }} · {{ $roomOption->room_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <template x-if="liveIssue('room_id').length">
                                            <div class="field-live-error" data-testid="live-error-room_id">
                                                <template x-for="msg in liveIssue('room_id')" :key="msg"><div x-text="msg"></div></template>
                                            </div>
                                        </template>
                                        <template x-if="liveWarning('room_id').length">
                                            <div class="field-live-warning" data-testid="live-warning-room_id">
                                                <template x-for="msg in liveWarning('room_id')" :key="msg"><div x-text="msg"></div></template>
                                            </div>
                                        </template>
                                        @if($roomConflictNote)
                                            <div class="modal-conflict-field">{{ $roomConflictNote }}</div>
                                        @endif
                                    </div>
                                    @php $editTopicOptions = $offering?->course?->topics ?? collect(); @endphp
                                    <div class="modal-field-full">
                                        <label class="modal-label" for="edit_topic_{{ $schedule->id }}">หัวข้อกิจกรรม <span class="required-mark">*</span></label>
                                        <input id="edit_topic_{{ $schedule->id }}" name="topic" type="text" maxlength="255" required class="modal-control"
                                            @if($editTopicOptions->isNotEmpty()) list="edit-topic-options-{{ $schedule->id }}" autocomplete="off" @endif
                                            value="{{ $editOld('topic', $schedule->topic) }}" placeholder="{{ $editTopicOptions->isNotEmpty() ? 'เลือกหัวข้อสำเร็จรูป หรือพิมพ์เอง' : 'เช่น บรรยายเรื่องการประเมินผู้ป่วย' }}">
                                        @if($editTopicOptions->isNotEmpty())
                                            <datalist id="edit-topic-options-{{ $schedule->id }}">
                                                @foreach($editTopicOptions as $topic)
                                                    <option value="{{ $topic->name }}"></option>
                                                @endforeach
                                            </datalist>
                                        @endif
                                    </div>
                                    <div class="modal-field-full">
                                        <label class="modal-label" for="edit_remark_{{ $schedule->id }}">หมายเหตุ</label>
                                        <textarea id="edit_remark_{{ $schedule->id }}" name="remark" rows="2" class="modal-control" placeholder="เช่น ให้นักศึกษาเตรียมเอกสารก่อนเข้าเรียน หรือแจ้งอุปกรณ์ที่ต้องใช้">{{ $editOld('remark', $schedule->remark) }}</textarea>
                                    </div>
                                </div>

                                <div class="modal-section {{ $instructorConflictNote ? 'modal-field-has-conflict' : '' }}">
                                    <div class="modal-section-title">ผู้สอน <span class="required-mark">*</span></div>
                                    <template x-if="liveIssue('instructor_ids').length || liveIssue('lead_instructor_id').length">
                                        <div class="field-live-error" data-testid="live-error-instructor_ids">
                                            <template x-for="msg in [...liveIssue('instructor_ids'), ...liveIssue('lead_instructor_id')]" :key="msg"><div x-text="msg"></div></template>
                                        </div>
                                    </template>
                                    <template x-if="liveWarning('instructor_ids').length">
                                        <div class="field-live-warning" data-testid="live-warning-instructor_ids">
                                            <template x-for="msg in liveWarning('instructor_ids')" :key="msg"><div x-text="msg"></div></template>
                                        </div>
                                    </template>
                                    @php
                                        $editInstructorOptions = $eligibleScheduleInstructors($offering);
                                        $editInstructorSearchItems = $editInstructorOptions
                                            ->map(fn ($instructor) => mb_strtolower(($instructor->formatted_name ?? $instructor->name) . ' ' . ($instructor->instructorProfile?->department?->name ?? ''), 'UTF-8'))
                                            ->values();
                                    @endphp
                                    <input type="search" class="modal-choice-search" x-model="editInstructorSearch" placeholder="ค้นหาชื่อผู้สอน" aria-label="ค้นหาผู้สอน">
                                    <div class="modal-choice-grid is-scroll-list is-instructor-scroll">
                                        @foreach($editInstructorOptions as $instructor)
                                            @php
                                                $isOutsideDepartment = $instructorDepartmentMismatch($offering, $instructor);
                                                $instructorDepartment = $instructor->instructorProfile?->department?->name;
                                                $editInstructorSearchText = mb_strtolower(($instructor->formatted_name ?? $instructor->name) . ' ' . ($instructorDepartment ?? '') . ($isOutsideDepartment ? ' ต่างภาค' : ''), 'UTF-8');
                                            @endphp
                                            <label class="modal-choice {{ $isOutsideDepartment ? 'is-warning-choice' : '' }}" x-show="matchesCreateSearch(@js($editInstructorSearchText), editInstructorSearch)" x-cloak>
                                                <input type="checkbox" name="instructor_ids[]" value="{{ $instructor->id }}" @checked(in_array((string) $instructor->id, $editInstructorIds, true)) data-testid="schedule-instructor">
                                                <span class="modal-choice-main">
                                                    <span>{{ $instructor->formatted_name ?? $instructor->name }}</span>
                                                    @if($instructorDepartment)
                                                        <small>{{ $instructorDepartment }}</small>
                                                    @endif
                                                </span>
                                                @if($isOutsideDepartment)
                                                    <span class="modal-choice-warning">ต่างภาค</span>
                                                @endif
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="modal-choice-empty" x-show="hasCreateSearch(editInstructorSearch) && !hasCreateSearchMatches(@js($editInstructorSearchItems), editInstructorSearch)" x-cloak>ไม่พบข้อมูลที่ค้นหา</div>
                                    @if($instructorConflictNote)
                                        <div class="modal-conflict-field">{{ $instructorConflictNote }}</div>
                                    @endif
                                </div>

                                <div class="modal-section {{ $groupConflictNote ? 'modal-field-has-conflict' : '' }}" data-testid="schedule-edit-group-selector">
                                    @php
                                        $editGroupOptions = $offering->studentGroups
                                            ->sortBy(fn ($group) => sprintf('%010d-%s', (int) preg_replace('/\D+/', '', (string) $group->group_code), (string) $group->group_code))
                                            ->values();
                                        $editGroupSearchItems = $editGroupOptions
                                            ->map(fn ($group) => mb_strtolower($group->group_code . ' ' . $group->student_count . ' คน', 'UTF-8'))
                                            ->values();
                                    @endphp
                                    <div class="modal-section-title">กลุ่มนักศึกษา <span class="required-mark">*</span></div>
                                    <template x-if="liveIssue('student_group_ids').length">
                                        <div class="field-live-error" data-testid="live-error-student-group-ids">
                                            <template x-for="msg in liveIssue('student_group_ids')" :key="msg"><div x-text="msg"></div></template>
                                        </div>
                                    </template>
                                    @if($offering->studentGroups->isEmpty())
                                        <div class="schedule-empty schedule-group-empty">
                                            ยังไม่มีกลุ่มนักศึกษาในรายวิชานี้
                                            @if((int) $offering->coordinator_id === (int) auth()->id())
                                                <a href="{{ $scheduleGroupManageUrl($offering) }}" data-testid="schedule-edit-manage-groups-link">ไปสร้างกลุ่มในหน้ารายวิชา</a>
                                            @endif
                                        </div>
                                    @else
                                        <input type="search" class="modal-choice-search" x-model="editGroupSearch" placeholder="ค้นหารหัสกลุ่มนักศึกษา" aria-label="ค้นหากลุ่มนักศึกษา">
                                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                                            @if((int) $offering->coordinator_id === (int) auth()->id())
                                                <a href="{{ $scheduleGroupManageUrl($offering) }}" class="schedule-group-manage-mini" data-testid="schedule-edit-manage-groups-link">จัดการกลุ่มในหน้ารายวิชา</a>
                                            @else
                                                <span></span>
                                            @endif
                                            <button type="button" class="btn btn-secondary" style="padding:6px 10px;font-size:12px;"
                                                @click="$el.closest('.modal-section').querySelectorAll('input[name=&quot;student_group_ids[]&quot;]').forEach(input => { input.checked = true; input.dispatchEvent(new Event('change', { bubbles: true })); })">
                                                เลือกทุกกลุ่ม
                                            </button>
                                        </div>
                                        <div class="modal-choice-grid is-scroll-list">
                                            @foreach($editGroupOptions as $group)
                                                @php
                                                    $editGroupSearchText = mb_strtolower($group->group_code . ' ' . $group->student_count . ' คน', 'UTF-8');
                                                @endphp
                                                <label class="modal-choice" x-show="matchesCreateSearch(@js($editGroupSearchText), editGroupSearch)" x-cloak>
                                                    <input type="checkbox" name="student_group_ids[]" value="{{ $group->id }}"
                                                        @checked(in_array((string) $group->id, $editGroupIds, true))
                                                        @change="$nextTick(() => window.tpssScheduleRunLiveCheck?.($el.closest('form')))"
                                                        data-testid="schedule-edit-group-option">
                                                    <span style="display:inline-flex;align-items:center;gap:7px;">
                                                        <span style="flex:0 0 9px;width:9px;height:9px;border-radius:50%;background:{{ $group->color_code ?: 'var(--brand-navy)' }};"></span>
                                                        {{ $group->group_code }} · {{ $group->student_count }} คน
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <div class="modal-choice-empty" x-show="hasCreateSearch(editGroupSearch) && !hasCreateSearchMatches(@js($editGroupSearchItems), editGroupSearch)" x-cloak>ไม่พบกลุ่มที่ค้นหา</div>
                                    @endif
                                    @if($groupConflictNote)
                                        <div class="modal-conflict-field">{{ $groupConflictNote }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="schedule-live-warning" x-show="liveWarningActive && !liveBlocking" x-cloak data-testid="schedule-live-warning">
                                <span class="schedule-live-warning-icon" aria-hidden="true">!</span>
                                <span>พบข้อเตือนที่ยังบันทึกได้ ระบบจะทำเครื่องหมายไว้ให้ตรวจสอบก่อนส่งอนุมัติ</span>
                            </div>
                            <div class="schedule-live-block" x-show="liveBlocking" x-cloak data-testid="schedule-live-block">
                                <span class="schedule-live-block-icon" aria-hidden="true">!</span>
                                <span>พบข้อมูลไม่ถูกต้อง แก้ไขจุดที่ไฮไลต์สีแดงก่อนจึงจะบันทึกได้</span>
                            </div>
                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" @click="closeEdit()">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary" data-testid="schedule-submit" x-bind:disabled="liveBlocking" x-bind:data-tip="liveBlocking ? 'แก้ไขข้อมูลที่บล็อกก่อนบันทึก' : ''">บันทึกการแก้ไข</button>
                            </div>
                        </form>
                    </section>
                    </template>
                </div>
            @endif
            @if($lazyModal ?? false)
                </div>
            @endif
        @endforeach
