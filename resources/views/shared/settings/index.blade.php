<x-app-layout title="{{ $isAdmin ? 'ตั้งค่าระบบ' : 'ตั้งค่าปีการศึกษา' }}">
    @php
        $canManageHolidays = $canManageHolidays ?? in_array($routePrefix ?? null, ['admin', 'staff'], true);

        // ปฏิทินอิง "ปีการศึกษาปัจจุบัน" (active) เสมอ — ไม่มี dropdown ให้เลือกปีซ้ำ
        $calDefaultYearId = optional(($academicYears ?? collect())->firstWhere('is_active', true))->id;

        // เปิด modal ปฏิทินค้างไว้หลัง save/error/delete (flash จาก controller)
        $calReopenPayload = null;
        if ($reopenYearId = session('open_calendar_year')) {
            $reopenYear = ($academicYears ?? collect())->firstWhere('id', $reopenYearId);
            if ($reopenYear) {
                $calReopenPayload = [
                    'year' => ['id' => $reopenYear->id, 'name' => $reopenYear->name, 'calendars' => $reopenYear->calendars],
                    'hasError' => $errors->has('calendar_terms') || $errors->has('curriculum_id'),
                    'editId' => session('open_calendar_id'),
                    'old' => [
                        'name' => old('name'),
                        'curriculum_id' => old('curriculum_id'),
                        'year_levels' => old('year_levels', []),
                        'terms' => old('terms', []),
                    ],
                ];
            }
        }
    @endphp
    <div class="settings-page" x-data="{
        activeTab: new URLSearchParams(window.location.search).get('tab') || 'academic',
        workloadWeeks: {{ $workloadWeeks }},
        teachingWeeks: {{ $teachingWeeks }},
        workloadHoursPerWeek: {{ $workloadHoursPerWeek }},
        get totalQuota() { return this.workloadWeeks * this.workloadHoursPerWeek },
        get teachingQuota() { return this.teachingWeeks * this.workloadHoursPerWeek },
        showModal: {{ $errors->hasAny(['name', 'terms', 'is_active']) ? 'true' : 'false' }},
        editMode: {{ ($errors->hasAny(['name', 'terms', 'is_active'])) && old('_method') === 'PUT' ? 'true' : 'false' }},
        currentYear: {
            id: '{{ old('year_id', '') }}',
            name: '{{ old('name', '') }}',
            start_date: {{ Js::from(\App\Support\ThaiDate::formatForInput(old('start_date', ''))) }},
            end_date: {{ Js::from(\App\Support\ThaiDate::formatForInput(old('end_date', ''))) }},
            is_active: {{ old('is_active') ? 'true' : 'false' }},
            hasSummer: false,
            terms: [],
        },
        copyFromYearId: '',
        openScheduleConfirmForm: null,
        openScheduleConfirmLabel: '',
        openScheduleCountdown: 0,
        openScheduleTimer: null,
        closeScheduleConfirmForm: null,
        closeScheduleConfirmLabel: '',
        closeScheduleCountdown: 0,
        closeScheduleTimer: null,
        emptyTerm(name) {
            return { name: name || '', start_date: '', end_date: '', midterm_start: '', midterm_end: '', final_start: '', final_end: '' };
        },
        buildTerms(yearTerms) {
            const list = Array.isArray(yearTerms) ? yearTerms : [];
            const slot = (i, fallbackName) => {
                const t = list[i];
                if (!t) return this.emptyTerm(fallbackName);
                return {
                    name: t.name || fallbackName,
                    start_date: this.thaiDateForInput(t.start_date),
                    end_date: this.thaiDateForInput(t.end_date),
                    midterm_start: this.thaiDateForInput(t.midterm_start),
                    midterm_end: this.thaiDateForInput(t.midterm_end),
                    final_start: this.thaiDateForInput(t.final_start),
                    final_end: this.thaiDateForInput(t.final_end),
                };
            };
            return [slot(0, 'ภาคเรียนที่ 1'), slot(1, 'ภาคเรียนที่ 2'), slot(2, 'ภาคฤดูร้อน')];
        },
        resetSummerTerm() {
            this.currentYear.terms[2] = this.emptyTerm('ภาคฤดูร้อน');
        },
        /* ── ปฏิทินการศึกษาตามกลุ่ม (V4 ข้อ 8) ── */
        calCurriculums: {{ Js::from($calendarCurriculums ?? []) }},
        calYearsData: {{ Js::from(($academicYears ?? collect())->mapWithKeys(fn ($y) => [(string) $y->id => ['id' => $y->id, 'name' => $y->name, 'calendars' => $y->calendars]])) }},
        calYearOptions: {{ Js::from(($academicYears ?? collect())->map(fn ($y) => ['id' => (string) $y->id, 'name' => $y->name])->values()) }},
        isAdminView: {{ $isAdmin ? 'true' : 'false' }},
        {{-- ── ปฏิทินแยกตามหลักสูตร/ชั้นปี (override) — section ใต้ตารางปี · ปฏิทินกลาง=เทอมของปี ── --}}
        calOverrideYear: '',          // ปีที่เลือกใน dropdown ของ section override
        calYearId: '', calYearName: '', calList: [],
        calGroupOpen: {},             // หลักสูตร id → กางชั้นปีอยู่ไหม (collapse)
        showCalEditor: false,         // เปิด modal กรอกวันของ scope ที่เลือก
        editCalMode: false,           // scope ที่กำลังแก้มี override อยู่แล้วไหม
        currentScope: { key: '', curriculum_id: '', year: null, isDefault: false },
        currentCalIsDefault: false,
        currentCal: { id: '', curriculum_id: '', year_levels: [], hasSummer: false, terms: [] },
        initCalOverride() {
            const def = '{{ $calDefaultYearId }}';
            if (def) this.selectOverrideYear(def);
        },
        selectOverrideYear(yearId) {
            this.calOverrideYear = String(yearId);
            const y = this.calYearsData[String(yearId)];
            this.calYearId = y ? y.id : '';
            this.calYearName = y ? y.name : '';
            this.calList = (y && Array.isArray(y.calendars)) ? y.calendars : [];
            this.calGroupOpen = {};
            this.showCalEditor = false;
        },
        defaultScope() { return { key: 'default', curriculum_id: '', year: null, isDefault: true }; },
        scopeOf(cid, year) {
            if (!cid) return this.defaultScope();
            return { key: 'c' + cid + (year ? ('y' + year) : ''), curriculum_id: String(cid), year: year ? Number(year) : null, isDefault: false };
        },
        // โครงรายการสำหรับ render accordion: กลุ่มหลักสูตร (header) + แถวชั้นปี (ไม่มี default — ปฏิทินกลางอยู่ที่ปี)
        calRows() {
            const rows = [];
            for (const c of this.calCurriculums) {
                const cid = String(c.id);
                if (c.uses_year_level) {
                    rows.push({ kind: 'group', key: 'g' + cid, cid: cid, curriculum_id: cid, year: null, label: c.name });
                    const dur = c.duration_years || 4;
                    for (let y = 1; y <= dur; y++) {
                        rows.push({ kind: 'scope', key: 'c' + cid + 'y' + y, label: 'ปี ' + y, curriculum_id: cid, year: y, isDefault: false, parent: cid });
                    }
                } else {
                    rows.push({ kind: 'scope', key: 'c' + cid, label: c.name, curriculum_id: cid, year: null, isDefault: false, parent: '' });
                }
            }
            return rows;
        },
        // หา calendar ที่ตรงกับ scope · null = ยังไม่ตั้งค่า (ใช้ค่าเริ่มต้น)
        calForScope(scope) {
            return this.calList.find(c => {
                const cid = c.curriculum_id ? String(c.curriculum_id) : '';
                const yl = Array.isArray(c.year_levels) ? c.year_levels.map(Number) : [];
                if (scope.isDefault) return !cid && yl.length === 0;
                if (cid !== String(scope.curriculum_id)) return false;
                if (scope.year === null) return yl.length === 0;        // ทั้งหลักสูตร (ไม่มีชั้นปี)
                return yl.includes(Number(scope.year));                 // ราย-ชั้นปี
            }) || null;
        },
        scopeIsSet(scope) { return !!this.calForScope(scope); },
        // สถานะ inheritance ของ scope: 'self'=ตั้งตรงนี้ · 'curriculum'=ใช้ของทั้งหลักสูตร · 'central'=ปฏิทินกลาง
        scopeStatus(row) {
            if (this.calForScope({ curriculum_id: row.curriculum_id, year: row.year, isDefault: false })) return 'self';
            if (row.year && this.calForScope({ curriculum_id: row.curriculum_id, year: null, isDefault: false })) return 'curriculum';
            return 'central';
        },
        // จำนวนปฏิทินแยกที่ตั้งไว้ (override · ไม่นับปฏิทินกลางของปี)
        calOverrideCount() { return this.calList.filter(c => c.curriculum_id).length; },
        // ปฏิทินกลางของปี (fallback) กรอกเทอมแล้วหรือยัง
        centralHasTerms() {
            const c = this.calForScope({ curriculum_id: '', year: null, isDefault: true });
            return !!(c && Array.isArray(c.terms) && c.terms.length);
        },
        // สรุปกลุ่มหลักสูตร: ตั้งค่าแล้วกี่ชั้นปีจากทั้งหมด
        groupSummary(cid) {
            const c = this.calCurriculums.find(x => String(x.id) === String(cid));
            const dur = c ? (c.duration_years || 4) : 4;
            let set = 0;
            for (let y = 1; y <= dur; y++) if (this.scopeIsSet(this.scopeOf(cid, y))) set++;
            return { set, total: dur };
        },
        toggleGroup(cid) { this.calGroupOpen[cid] = !this.calGroupOpen[cid]; },
        calBuildTerms(list) {
            const a = Array.isArray(list) ? list : [];
            const s = (i, fn) => {
                const t = a[i];
                if (!t) return this.emptyTerm(fn);
                return { name: t.name || fn, start_date: this.thaiDateForInput(t.start_date), end_date: this.thaiDateForInput(t.end_date), midterm_start: this.thaiDateForInput(t.midterm_start), midterm_end: this.thaiDateForInput(t.midterm_end), final_start: this.thaiDateForInput(t.final_start), final_end: this.thaiDateForInput(t.final_end) };
            };
            return [s(0, 'ภาคเรียนที่ 1'), s(1, 'ภาคเรียนที่ 2'), s(2, 'ภาคฤดูร้อน')];
        },
        // ชื่อปฏิทินอัตโนมัติจาก scope (ไม่ต้องให้ผู้ใช้พิมพ์)
        scopeAutoName(scope) {
            if (scope.isDefault) return 'ปฏิทินกลางของคณะ';
            const c = this.calCurriculums.find(x => String(x.id) === String(scope.curriculum_id));
            const cn = c ? c.name : 'หลักสูตร';
            if (scope.year) return cn + ' · ปี ' + scope.year;
            return (c && c.uses_year_level) ? (cn + ' · ทุกชั้นปี') : cn;
        },
        calAllScopes() {
            const scopes = [];
            for (const c of this.calCurriculums) {
                if (c.uses_year_level) {
                    const dur = c.duration_years || 4;
                    for (let y = 1; y <= dur; y++) scopes.push(this.scopeOf(String(c.id), y));
                } else {
                    scopes.push(this.scopeOf(String(c.id), null));
                }
            }
            return scopes;
        },
        calCoverage() {
            const all = this.calAllScopes();
            return { set: all.filter(s => this.scopeIsSet(s)).length, total: all.length };
        },
        // เปิด modal กรอกวันของ scope (แยกต่อขอบเขต · ทีละอัน)
        openScopeModal(row) {
            const scope = { key: row.key, curriculum_id: row.curriculum_id, year: row.year, isDefault: !!row.isDefault };
            this.currentScope = scope;
            this.currentCalIsDefault = scope.isDefault;
            const cal = this.calForScope(scope);
            if (cal) {
                const terms = cal.terms || [];
                this.editCalMode = true;
                this.currentCal = { id: cal.id, curriculum_id: cal.curriculum_id ? String(cal.curriculum_id) : '', year_levels: Array.isArray(cal.year_levels) ? cal.year_levels.map(Number) : [], hasSummer: terms.length >= 3, terms: this.calBuildTerms(terms) };
            } else {
                this.editCalMode = false;
                this.currentCal = { id: '', curriculum_id: scope.curriculum_id || '', year_levels: scope.year ? [Number(scope.year)] : [], hasSummer: false, terms: this.calBuildTerms([]) };
            }
            this.showCalEditor = true;
        },
        calBoot(payload) {
            // เลือกปี + เปิด modal ของขอบเขตที่แก้ค้างหลัง save/error/delete (เรียกจาก x-init ด้วย flash)
            if (!payload || !payload.year) return;
            this.calYearsData[String(payload.year.id)] = payload.year;   // sync calendars ล่าสุด
            this.selectOverrideYear(payload.year.id);
            const o = payload.old || {};
            const cid = o.curriculum_id ? String(o.curriculum_id) : '';
            const yls = Array.isArray(o.year_levels) ? o.year_levels.map(Number) : [];
            const y = yls.length ? yls[0] : null;
            if (!cid) return;                              // ไม่มี scope (ปฏิทินกลาง) → ไม่ต้องเปิด modal
            if (y) this.calGroupOpen[cid] = true;          // กางกลุ่มหลักสูตรของ scope ที่แก้
            if (payload.hasError) {
                // คืนค่าที่กรอกไว้ก่อน error (อยู่ใน modal เดิม)
                this.openScopeModal({ key: this.scopeOf(cid, y).key, curriculum_id: cid, year: y, isDefault: false });
                this.editCalMode = !!payload.editId;
                this.currentCal.id = payload.editId || this.currentCal.id;
                this.currentCal.terms = this.calBuildTerms(o.terms || []);
                this.currentCal.hasSummer = Array.isArray(o.terms) && o.terms.length >= 3;
            }
        },
        showHolidayModal: false,
        editHolidayMode: false,
        currentHoliday: { id: '', date: '', name: '', remark: '' },
        openAddHoliday() {
            this.editHolidayMode = false;
            this.currentHoliday = { id: '', date: '', name: '', remark: '' };
            this.showHolidayModal = true;
        },
        openEditHoliday(h) {
            this.editHolidayMode = true;
            this.currentHoliday = {
                id: h.id,
                date: this.thaiDateForInput(h.date),
                name: h.name || '',
                remark: h.remark || '',
            };
            this.showHolidayModal = true;
        },
        openAddModal() {
            this.editMode = false;
            this.copyFromYearId = '';
            this.currentYear = { id: '', name: '', start_date: '', end_date: '', is_active: false, hasSummer: false, terms: this.buildTerms([]), calendars: [] };
            this.showModal = true;
        },
        openEditModal(year) {
            this.editMode = true;
            const terms = year.terms || [];
            this.currentYear = {
                id: year.id,
                name: year.name,
                start_date: this.thaiDateForInput(year.start_date),
                end_date: this.thaiDateForInput(year.end_date),
                is_active: !!year.is_active,
                hasSummer: terms.length >= 3,
                terms: this.buildTerms(terms),
                calendars: year.calendars || [],
            };
            this.showModal = true;
        },
        thaiDateForInput(value) {
            return window.tpssThaiDate.formatForInput(value);
        },
        startOpenScheduleCountdown(formId, label) {
            clearInterval(this.openScheduleTimer);
            this.openScheduleConfirmForm = formId;
            this.openScheduleConfirmLabel = label;
            this.openScheduleCountdown = 3;
            this.openScheduleTimer = setInterval(() => {
                this.openScheduleCountdown = Math.max(0, this.openScheduleCountdown - 1);
                if (this.openScheduleCountdown === 0) {
                    clearInterval(this.openScheduleTimer);
                }
            }, 1000);
        },
        cancelOpenScheduleCountdown() {
            clearInterval(this.openScheduleTimer);
            this.openScheduleConfirmForm = null;
            this.openScheduleConfirmLabel = '';
            this.openScheduleCountdown = 0;
        },
        confirmOpenSchedule() {
            if (this.openScheduleCountdown > 0 || !this.openScheduleConfirmForm) return;
            document.getElementById(this.openScheduleConfirmForm)?.submit();
        },
        scrollToSettingsTarget(targetId) {
            this.$nextTick(() => {
                const target = document.getElementById(targetId);
                if (!target) return;
                const offset = 104;
                const top = target.getBoundingClientRect().top + window.scrollY - offset;
                window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
            });
        },
        startCloseScheduleConfirm(formId, label) {
            clearInterval(this.closeScheduleTimer);
            this.closeScheduleConfirmForm = formId;
            this.closeScheduleConfirmLabel = label;
            this.closeScheduleCountdown = 3;
            this.closeScheduleTimer = setInterval(() => {
                this.closeScheduleCountdown = Math.max(0, this.closeScheduleCountdown - 1);
                if (this.closeScheduleCountdown === 0) {
                    clearInterval(this.closeScheduleTimer);
                }
            }, 1000);
        },
        cancelCloseScheduleConfirm() {
            clearInterval(this.closeScheduleTimer);
            this.closeScheduleConfirmForm = null;
            this.closeScheduleConfirmLabel = '';
            this.closeScheduleCountdown = 0;
        },
        confirmCloseSchedule() {
            if (this.closeScheduleCountdown > 0 || !this.closeScheduleConfirmForm) return;
            document.getElementById(this.closeScheduleConfirmForm)?.submit();
        }
    }" x-init="initCalOverride(); calBoot({{ Js::from($calReopenPayload) }})">

        @if($isAdmin)
        <div class="tabs-container" style="display: flex; justify-content: flex-end; margin-bottom: 24px; width: 100%; overflow: hidden;">
            <div class="tabs"
                style="display: flex; gap: 8px; background: var(--bg-2); padding: 4px; border-radius: 8px; border: 1px solid var(--border); overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; max-width: 100%;">
                <button type="button" @click="activeTab = 'academic'"
                    :class="activeTab === 'academic' ? 'btn-primary' : 'btn btn-ghost'"
                    class="settings-tab-button">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        class="settings-tab-icon">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span class="settings-tab-copy">
                        <span>ปีการศึกษา</span>
                        <small>ขั้นตอนหลักก่อนเริ่มจัดตาราง</small>
                    </span>
                </button>
                @if($canManageHolidays)
                <button type="button" @click="activeTab = 'holidays'"
                    :class="activeTab === 'holidays' ? 'btn-primary' : 'btn btn-ghost'"
                    class="settings-tab-button">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="settings-tab-icon">
                        <path d="M8 2v4"/><path d="M16 2v4"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/>
                    </svg>
                    <span class="settings-tab-copy">
                        <span>วันหยุด</span>
                        <small>ข้อมูลประกอบปฏิทิน</small>
                    </span>
                </button>
                @endif
                <button type="button" @click="activeTab = 'pa'"
                    :class="activeTab === 'pa' ? 'btn-primary' : 'btn btn-ghost'"
                    class="settings-tab-button">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                        class="settings-tab-icon">
                        <line x1="19" y1="5" x2="5" y2="19"></line>
                        <circle cx="6.5" cy="6.5" r="2.5"></circle>
                        <circle cx="17.5" cy="17.5" r="2.5"></circle>
                    </svg>
                    <span class="settings-tab-copy">
                        <span>เกณฑ์ภาระงาน</span>
                        <small>ค่าคงที่สำหรับคำนวณ PA</small>
                    </span>
                </button>
            </div>
        </div>
        @endif

        <!-- Tab: Academic Year (รวมการจัดการช่วงจัดตารางในตารางเดียวกัน) -->
        @php
            $schedulingCriticals = $schedulingCriticals ?? [];
            $hasSchedulingCriticals = $isAdmin && count($schedulingCriticals) > 0;
            $firstSchedulingCritical = collect($schedulingCriticals)->first();
            $activeYear = $academicYears->firstWhere('is_active', true);
        @endphp
        <div x-show="!isAdminView || activeTab === 'academic'" {{ $isAdmin ? 'x-cloak' : '' }}>
            @if(session('success'))
                <div class="settings-flash settings-flash--success">
                    <span class="settings-flash-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if($isAdmin && session('error'))
                <div class="settings-flash settings-flash--error">
                    <span class="settings-flash-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                    </span>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if($isAdmin)
                <section class="settings-start-guide" aria-label="ลำดับการตั้งค่าระบบ">
                    <div class="settings-start-guide__header">
                        <div>
                            <div class="settings-start-guide__eyebrow">เริ่มที่นี่</div>
                            <h2>เริ่มตั้งค่าระบบ</h2>
                            <p>ทำตามลำดับนี้ก่อนเปิดช่วงจัดตาราง ระบบจะบอกให้เห็นทันทีว่าขั้นไหนเสร็จแล้วและควรทำอะไรต่อ</p>
                        </div>
                        <div class="settings-start-guide__summary">
                            @if($activeYear)
                                <span>ปีปัจจุบัน</span>
                                <strong>{{ $activeYear->name }}</strong>
                            @else
                                <span>ยังไม่มีปีปัจจุบัน</span>
                                <strong>เริ่มจากเพิ่มปี</strong>
                            @endif
                        </div>
                    </div>

                    <div class="settings-setup-steps">
                        <article class="settings-setup-step {{ $activeYear ? 'is-done' : 'is-next' }}">
                            <div class="settings-setup-step__mark">1</div>
                            <div class="settings-setup-step__body">
                                <div class="settings-setup-step__top">
                                    <h3>ตั้งค่าปีการศึกษาปัจจุบัน</h3>
                                    <span class="settings-step-badge">{{ $activeYear ? 'เสร็จแล้ว' : 'ทำขั้นนี้ต่อ' }}</span>
                                </div>
                                <div class="settings-step-task">{{ $activeYear ? 'ตรวจสอบ: ปีนี้ถูกตั้งเป็นปีปัจจุบันแล้ว' : 'สิ่งที่ต้องทำ: เพิ่มปีการศึกษาและติ๊กเป็นปีปัจจุบัน' }}</div>
                                <p>{{ $activeYear ? 'มีปีปัจจุบันสำหรับอ้างอิงปฏิทินและช่วงจัดตารางแล้ว' : 'เพิ่มปีการศึกษาและตั้งให้เป็นปีปัจจุบันก่อน' }}</p>
                                @if($activeYear)
                                    <button type="button" class="settings-step-link" @click="scrollToSettingsTarget('academic-year-section')">ดูตารางปีการศึกษา</button>
                                @else
                                    <button type="button" class="btn btn-primary settings-step-action" @click="openAddModal()">เพิ่มปีการศึกษา</button>
                                @endif
                            </div>
                        </article>

                        <article class="settings-setup-step"
                            :class="{
                                'is-waiting': !calYearName,
                                'is-next': calYearName && !centralHasTerms(),
                                'is-done': calYearName && centralHasTerms()
                            }">
                            <div class="settings-setup-step__mark">2</div>
                            <div class="settings-setup-step__body">
                                <div class="settings-setup-step__top">
                                    <h3>กำหนดปฏิทินกลางของคณะ</h3>
                                    <span class="settings-step-badge" x-show="!calYearName">รอก่อน</span>
                                    <span class="settings-step-badge" x-show="calYearName && !centralHasTerms()" x-cloak>ทำขั้นนี้ต่อ</span>
                                    <span class="settings-step-badge" x-show="calYearName && centralHasTerms()" x-cloak>เสร็จแล้ว</span>
                                </div>
                                <div class="settings-step-task" x-show="!calYearName">สิ่งที่ต้องทำ: ทำขั้นที่ 1 ให้เสร็จก่อน</div>
                                <div class="settings-step-task" x-show="calYearName && !centralHasTerms()" x-cloak>สิ่งที่ต้องทำ: กรอกวันเปิด-ปิดเทอมและช่วงสอบกลาง</div>
                                <div class="settings-step-task" x-show="calYearName && centralHasTerms()" x-cloak>ตรวจสอบ: ปฏิทินกลางถูกกำหนดแล้ว</div>
                                <p x-show="!calYearName">ต้องมีปีการศึกษาปัจจุบันก่อนจึงจะกำหนดปฏิทินได้</p>
                                <p x-show="calYearName && !centralHasTerms()" x-cloak>กำหนดวันเปิด-ปิดเทอมและช่วงสอบที่เป็นค่ากลางของทั้งคณะ</p>
                                <p x-show="calYearName && centralHasTerms()" x-cloak>ปฏิทินกลางพร้อมเป็นฐานให้ทุกหลักสูตรแล้ว</p>
                                <button type="button" class="btn btn-primary settings-step-action"
                                    x-show="calYearName && !centralHasTerms()" x-cloak
                                    @click="openScopeModal({ key: 'central', curriculum_id: '', year: null, isDefault: true })">กำหนดปฏิทินกลาง</button>
                                <button type="button" class="settings-step-link"
                                    x-show="calYearName && centralHasTerms()" x-cloak
                                    @click="scrollToSettingsTarget('central-calendar-section')">ดูปฏิทินกลาง</button>
                            </div>
                        </article>

                        <article class="settings-setup-step"
                            :class="{
                                'is-waiting': !calYearName || !centralHasTerms(),
                                'is-optional': calYearName && centralHasTerms() && calOverrideCount() === 0,
                                'is-done': calYearName && centralHasTerms() && calOverrideCount() > 0
                            }">
                            <div class="settings-setup-step__mark">3</div>
                            <div class="settings-setup-step__body">
                                <div class="settings-setup-step__top">
                                    <h3>ตั้งปฏิทินแยกเฉพาะกรณีที่ต่าง</h3>
                                    <span class="settings-step-badge" x-show="!calYearName || !centralHasTerms()">รอก่อน</span>
                                    <span class="settings-step-badge" x-show="calYearName && centralHasTerms() && calOverrideCount() === 0" x-cloak>ข้ามได้</span>
                                    <span class="settings-step-badge" x-show="calYearName && centralHasTerms() && calOverrideCount() > 0" x-cloak>เสร็จแล้ว</span>
                                </div>
                                <div class="settings-step-task" x-show="!calYearName || !centralHasTerms()">สิ่งที่ต้องทำ: ทำปฏิทินกลางให้เสร็จก่อน</div>
                                <div class="settings-step-task" x-show="calYearName && centralHasTerms() && calOverrideCount() === 0" x-cloak>สิ่งที่ต้องทำ: ตรวจว่ามีหลักสูตร/ชั้นปีที่วันไม่ตรงหรือไม่</div>
                                <div class="settings-step-task" x-show="calYearName && centralHasTerms() && calOverrideCount() > 0" x-cloak>ตรวจสอบ: มีรายการที่ตั้งต่างจากปฏิทินกลางแล้ว</div>
                                <p x-show="!calYearName || !centralHasTerms()">ทำปฏิทินกลางให้เสร็จก่อน แล้วค่อยตั้งเฉพาะหลักสูตร/ชั้นปีที่ต่าง</p>
                                <p x-show="calYearName && centralHasTerms() && calOverrideCount() === 0" x-cloak>ข้ามได้ถ้าไม่มีวันเปิด-ปิดเทอมหรือช่วงสอบที่ต่างจากปฏิทินกลาง</p>
                                <p x-show="calYearName && centralHasTerms() && calOverrideCount() > 0" x-cloak x-text="'ตั้งปฏิทินแยกแล้ว ' + calOverrideCount() + ' รายการ'"></p>
                                <button type="button" class="settings-step-link"
                                    x-show="calYearName && centralHasTerms()" x-cloak
                                    @click="scrollToSettingsTarget('cal-override-section')">ตรวจปฏิทินแยก</button>
                            </div>
                        </article>

                        <article class="settings-setup-step"
                            :class="{
                                'is-waiting': !calYearName || !centralHasTerms(),
                                'is-issue': calYearName && centralHasTerms() && {{ $hasSchedulingCriticals ? 'true' : 'false' }},
                                'is-next': calYearName && centralHasTerms() && {{ (!$hasSchedulingCriticals && $activeYear && $activeYear->phase === 'preparation') ? 'true' : 'false' }},
                                'is-done': calYearName && centralHasTerms() && {{ ($activeYear && in_array($activeYear->phase, ['scheduling', 'published'], true)) ? 'true' : 'false' }}
                            }">
                            <div class="settings-setup-step__mark">4</div>
                            <div class="settings-setup-step__body">
                                <div class="settings-setup-step__top">
                                    <h3>เปิดช่วงจัดตารางเมื่อข้อมูลพร้อม</h3>
                                    <span class="settings-step-badge" x-show="!calYearName || !centralHasTerms()">รอก่อน</span>
                                    @if($hasSchedulingCriticals)
                                        <span class="settings-step-badge" x-show="calYearName && centralHasTerms()" x-cloak>มีข้อมูลต้องแก้</span>
                                    @elseif($activeYear && $activeYear->phase === 'preparation')
                                        <span class="settings-step-badge" x-show="calYearName && centralHasTerms()" x-cloak>ทำขั้นนี้ต่อ</span>
                                    @elseif($activeYear && in_array($activeYear->phase, ['scheduling', 'published'], true))
                                        <span class="settings-step-badge" x-show="calYearName && centralHasTerms()" x-cloak>เสร็จแล้ว</span>
                                    @else
                                        <span class="settings-step-badge" x-show="calYearName && centralHasTerms()" x-cloak>รอก่อน</span>
                                    @endif
                                </div>
                                <div class="settings-step-task" x-show="!calYearName || !centralHasTerms()">สิ่งที่ต้องทำ: ทำขั้นที่ 1-2 ให้ครบก่อน</div>
                                @if($hasSchedulingCriticals)
                                    <div class="settings-step-task" x-show="calYearName && centralHasTerms()" x-cloak>สิ่งที่ต้องทำ: แก้ข้อมูลที่ระบบแจ้งก่อนเปิดช่วงจัดตาราง</div>
                                @elseif($activeYear && $activeYear->phase === 'preparation')
                                    <div class="settings-step-task" x-show="calYearName && centralHasTerms()" x-cloak>สิ่งที่ต้องทำ: กดเปิดช่วงจัดตารางเมื่อข้อมูลพร้อม</div>
                                @elseif($activeYear && in_array($activeYear->phase, ['scheduling', 'published'], true))
                                    <div class="settings-step-task" x-show="calYearName && centralHasTerms()" x-cloak>ตรวจสอบ: ขั้นตอนเปิดช่วงจัดตารางเสร็จแล้ว</div>
                                @else
                                    <div class="settings-step-task" x-show="calYearName && centralHasTerms()" x-cloak>สิ่งที่ต้องทำ: ตั้งปีการศึกษาปัจจุบันให้เรียบร้อย</div>
                                @endif
                                <p x-show="!calYearName || !centralHasTerms()">ต้องมีปีปัจจุบันและปฏิทินกลางก่อนเปิดช่วงจัดตาราง</p>
                                @if($hasSchedulingCriticals)
                                    <p x-show="calYearName && centralHasTerms()" x-cloak>ยังมีข้อมูลจำเป็นที่ต้องแก้ก่อนเปิดช่วงจัดตาราง</p>
                                    @if(!empty($firstSchedulingCritical['link']))
                                        <a class="btn btn-primary settings-step-action" x-show="calYearName && centralHasTerms()" x-cloak href="{{ $firstSchedulingCritical['link'] }}">แก้ข้อมูลที่ขาด</a>
                                    @endif
                                @elseif($activeYear && $activeYear->phase === 'preparation')
                                    <p x-show="calYearName && centralHasTerms()" x-cloak>ตรวจข้อมูลครบแล้วจึงเปิดช่วงให้หัวหน้าวิชาเริ่มจัดตาราง</p>
                                    <button type="button" class="btn btn-primary settings-step-action"
                                        x-show="calYearName && centralHasTerms()" x-cloak
                                        @click="startOpenScheduleCountdown('open-scheduling-{{ $activeYear->id }}', 'ปีการศึกษา {{ $activeYear->name }}')">เปิดช่วงจัดตาราง</button>
                                @elseif($activeYear && $activeYear->phase === 'scheduling')
                                    <p x-show="calYearName && centralHasTerms()" x-cloak>เปิดช่วงจัดตารางอยู่แล้ว</p>
                                    <button type="button" class="settings-step-link" @click="scrollToSettingsTarget('schedule-phase-section')">ดูสถานะช่วงจัดตาราง</button>
                                @elseif($activeYear && $activeYear->phase === 'published')
                                    <p x-show="calYearName && centralHasTerms()" x-cloak>ปีนี้เผยแพร่ตารางแล้ว</p>
                                    <button type="button" class="settings-step-link" @click="scrollToSettingsTarget('schedule-phase-section')">ดูสถานะปีการศึกษา</button>
                                @else
                                    <p x-show="calYearName && centralHasTerms()" x-cloak>ตั้งค่าปีการศึกษาปัจจุบันให้เรียบร้อยก่อน</p>
                                @endif
                            </div>
                        </article>
                    </div>
                </section>
            @endif

            @if($hasSchedulingCriticals)
                <div style="background:var(--status-conflict-bg);border:1px solid var(--status-conflict-border);border-radius:8px;margin-bottom:16px;padding:14px 16px;color:var(--status-conflict-fg);">
                    <div style="font-weight:700;margin-bottom:6px;">ยังไม่สามารถเปิดช่วงจัดตารางได้</div>
                    <div style="font-size:13px;line-height:1.55;margin-bottom:10px;">ต้องแก้ Critical ให้หมดก่อนเปิดช่วงจัดตาราง เพื่อให้รายวิชาทุกวิชาพร้อมถูกสร้างเป็น Course Offering</div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;">
                        @foreach($schedulingCriticals as $critical)
                            <a href="{{ $critical['link'] }}" style="text-decoration:none;color:var(--status-conflict-fg);background:color-mix(in oklch,var(--status-conflict) 8%,white);border:1px solid color-mix(in oklch,var(--status-conflict) 22%,white);border-radius:999px;padding:5px 10px;font-size:12px;font-weight:700;">
                                {{ $critical['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="card" id="academic-year-section">
                <div class="card-hdr">
                    <div>
                        <div class="card-ttl settings-section-title"><span class="settings-section-step">1</span>ตั้งค่าปีการศึกษา</div>
                    </div>
                    <div class="card-actions">
                        <button class="btn btn-primary" data-testid="settings-add-year-button" @click="openAddModal()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            เพิ่มปีการศึกษา
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ปีการศึกษา</th>
                                <th>เทอม</th>
                                <th>วันที่เริ่ม - สิ้นสุด</th>
                                <th>สถานะปัจจุบัน</th>
                                <th>ช่วงจัดตาราง</th>
                                <th style="text-align: center;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($academicYears as $year)
                                <tr data-testid="settings-year-row" data-year-name="{{ $year->name }}">
                                    <td style="font-weight: 600; color: var(--fg-1);">{{ $year->name }}</td>
                                    <td style="font-size: 12px; color: var(--fg-2);">
                                        @php
                                            $fallbackCal = $year->calendars->first(fn ($c) => is_null($c->curriculum_id) && empty($c->year_levels));
                                            $needsTerms = ! $fallbackCal || $fallbackCal->terms->isEmpty();
                                        @endphp
                                        @foreach($year->terms as $t)
                                            <span class="badge badge-gray" style="margin:1px 2px;display:inline-block;">{{ $t->name }}</span>
                                        @endforeach
                                        @if($needsTerms)
                                            <span class="badge" title="ยังไม่ได้กำหนดเทอม/ช่วงสอบในปฏิทินค่าเริ่มต้น (ทุกหลักสูตร)" style="display:inline-block;margin:1px 2px;background:oklch(95% 0.05 75);color:oklch(45% 0.13 65);border:1px solid oklch(80% 0.12 75);">⚠ ยังไม่ได้กำหนดเทอม</span>
                                        @endif
                                    </td>
                                    <td style="color: var(--fg-2); font-size: 13px;">
                                        {{ \App\Support\ThaiDate::formatForInput($year->start_date) }} -
                                        {{ \App\Support\ThaiDate::formatForInput($year->end_date) }}
                                    </td>
                                    <td>
                                        @if($year->is_active)
                                            <span class="badge badge-primary">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor; margin-right: 6px;"></span>
                                                ปีปัจจุบัน
                                            </span>
                                        @else
                                            <span class="badge badge-gray">ทั่วไป</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($year->phase === 'scheduling')
                                            <span class="badge" style="background: oklch(90% 0.1 145); color: oklch(30% 0.15 145); border: 1px solid oklch(70% 0.15 145);">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: oklch(50% 0.2 145); margin-right: 6px; display: inline-block;"></span>
                                                เปิดช่วงจัดตาราง
                                            </span>
                                        @elseif($year->phase === 'published')
                                            <span class="badge badge-primary">เผยแพร่แล้ว</span>
                                        @else
                                            <span class="badge badge-gray">เตรียมข้อมูล</span>
                                        @endif
                                    </td>
                                    <td class="settings-action-cell">
                                        <div class="academic-year-icons">
                                            <button class="action-btn" title="แก้ไข"
                                                @click="openEditModal({{ json_encode($year) }})">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--fg-3);">
                                        ยังไม่มีข้อมูลปีการศึกษา</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ── ปฏิทินกลางของคณะ (บน) — เลือกปี + ตั้งเทอม/ช่วงสอบฐานของทั้งคณะ ── --}}
            <div class="card" id="central-calendar-section" style="margin-top:16px;">
                <div class="card-hdr">
                    <div>
                        <div class="card-ttl settings-section-title"><span class="settings-section-step">2</span>ปฏิทินกลางของคณะ</div>
                        <div style="font-size:12px;color:var(--fg-3);margin-top:2px;line-height:1.55;">เทอม/ช่วงสอบของทั้งคณะ — เป็นฐานให้ทุกหลักสูตร/ชั้นปีที่ไม่ได้ตั้งปฏิทินแยก</div>
                    </div>
                </div>
                <div class="central-calendar-card-body">
                    {{-- ยังไม่มีปีปัจจุบัน → ให้ตั้งก่อน --}}
                    <template x-if="!calYearName">
                        <div style="font-size:12px;color:var(--fg-3);padding:14px;text-align:center;background:var(--surface-sunken);border-radius:8px;">ยังไม่มีปีการศึกษาปัจจุบัน — ตั้งปีปัจจุบันที่ตารางด้านบนก่อน จึงจะกำหนดปฏิทินได้</div>
                    </template>
                    <div x-show="calYearName" class="central-calendar-summary">
                        <div class="central-calendar-main">
                            <span class="central-calendar-icon">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </span>
                            <div class="central-calendar-copy">
                                <div style="font-size:11px;color:var(--fg-3);font-weight:600;">ปีการศึกษาปัจจุบัน</div>
                                <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;">
                                    <span style="font-weight:800;font-size:18px;color:var(--fg-1);font-family:var(--font-display);" x-text="calYearName"></span>
                                    <span x-show="centralHasTerms()" style="font-size:10px;font-weight:700;color:var(--status-success-fg);background:color-mix(in oklch,var(--status-success-fg) 14%,var(--surface));padding:2px 9px;border-radius:999px;white-space:nowrap;">กำหนดเทอมแล้ว</span>
                                    <span x-show="!centralHasTerms()" x-cloak style="font-size:10px;font-weight:700;color:var(--status-warning-fg, #a87600);background:color-mix(in oklch,var(--status-warning-fg, #a87600) 16%,var(--surface));padding:2px 9px;border-radius:999px;white-space:nowrap;">ยังไม่กำหนดเทอม</span>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary central-calendar-action"
                            @click="openScopeModal({ key: 'central', curriculum_id: '', year: null, isDefault: true })">
                            <span x-show="centralHasTerms()">แก้ไขปฏิทินกลาง</span>
                            <span x-show="!centralHasTerms()" x-cloak>กำหนดเทอม</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ── ปฏิทินแยกตามหลักสูตร/ชั้นปี (ล่าง · override) ── --}}
            <div class="card" id="cal-override-section" style="margin-top:16px;">
                <div class="card-hdr">
                    <div>
                        <div class="card-ttl settings-section-title"><span class="settings-section-step">3</span>ปฏิทินแยกตามหลักสูตร/ชั้นปี</div>
                        <div style="font-size:12px;color:var(--fg-3);margin-top:2px;line-height:1.55;">ตั้งเฉพาะหลักสูตร/ชั้นปีที่วันเปิด-ปิดเทอม/ช่วงสอบ <strong>ต่างจากปฏิทินกลาง</strong> · ที่ไม่ตั้ง = ใช้ <strong>ปฏิทินกลางของคณะ</strong> (การ์ดด้านบน)</div>
                    </div>
                    <div class="card-actions">
                        <span style="font-size:11px;color:var(--fg-4);white-space:nowrap;" x-text="'ตั้งปฏิทินแยกแล้ว ' + calOverrideCount() + ' รายการ'"></span>
                    </div>
                </div>
                <div style="padding:16px 20px 18px;">
                    <template x-if="!calYearName">
                        <div style="font-size:12px;color:var(--fg-3);padding:16px;text-align:center;background:var(--surface-sunken);border-radius:8px;">ตั้งปีการศึกษาปัจจุบันก่อน จึงจะตั้งปฏิทินแยกได้</div>
                    </template>
                    <template x-if="calYearName && !calCurriculums.length">
                        <div style="font-size:12px;color:var(--fg-3);padding:16px;text-align:center;background:var(--surface-sunken);border-radius:8px;">ยังไม่มีหลักสูตรใน Master Data — เพิ่มหลักสูตรก่อนจึงตั้งปฏิทินแยกได้</div>
                    </template>
                    <div class="cal-override-list" x-show="calYearName && calCurriculums.length">
                        <template x-for="row in calRows()" :key="row.key">
                            <div>
                                {{-- หัวกลุ่มหลักสูตร = ปฏิทิน "ทั้งหลักสูตร (ทุกชั้นปี)" · กดชื่อ=กางชั้นปี · ปุ่ม=แก้ทุกชั้นปี --}}
                                <template x-if="row.kind === 'group'">
                                    <div class="cal-override-row is-group">
                                        <button type="button" @click="toggleGroup(row.cid)"
                                            class="cal-override-title-button">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;transition:transform .15s;color:var(--fg-3);" :style="calGroupOpen[row.cid] ? 'transform:rotate(90deg);' : ''"><polyline points="9 18 15 12 9 6"/></svg>
                                            <span style="font-weight:700;font-size:13px;color:var(--fg-1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="row.label"></span>
                                        </button>
                                        <span style="display:flex;align-items:center;gap:11px;flex-shrink:0;">
                                            <span x-show="scopeStatus(row) === 'self'" style="font-size:10px;font-weight:700;color:var(--status-success-fg);white-space:nowrap;">ทุกชั้นปี · ตั้งค่าแล้ว</span>
                                            <span x-show="scopeStatus(row) !== 'self'" x-cloak style="font-size:10px;color:var(--fg-4);white-space:nowrap;">ทุกชั้นปี · ใช้ปฏิทินกลาง</span>
                                            <span x-show="groupSummary(row.cid).set" x-cloak style="font-size:10px;color:var(--fg-3);white-space:nowrap;" x-text="'(' + groupSummary(row.cid).set + ' ปีตั้งต่าง)'"></span>
                                            <button type="button" @click="openScopeModal(row)" class="cal-override-action">
                                                <span x-show="scopeStatus(row) === 'self'">แก้ไข</span>
                                                <span x-show="scopeStatus(row) !== 'self'" x-cloak>ตั้งค่า</span>
                                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                            </button>
                                        </span>
                                    </div>
                                </template>
                                {{-- แถว scope — มีปุ่มแก้ไข → เปิด modal ของขอบเขตนั้น --}}
                                <template x-if="row.kind === 'scope'">
                                    <div x-show="!row.parent || calGroupOpen[row.parent]"
                                        class="cal-override-row"
                                        :class="{ 'is-child': row.parent, 'is-group': !row.parent }">
                                        {{-- ซ้าย: ช่อง icon (จุดสำหรับหลักสูตรไม่มีชั้นปี — กว้างเท่า chevron หัวกลุ่ม) + ชื่อ → ทุกแถว align แนวเดียว --}}
                                        <span style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
                                            <template x-if="!row.parent">
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;color:var(--fg-4);"><circle cx="12" cy="12" r="3"/></svg>
                                            </template>
                                            <span :style="'font-size:13px;color:var(--fg-1);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;' + (row.parent ? '' : 'font-weight:700;')" x-text="row.label"></span>
                                        </span>
                                        <span style="display:flex;align-items:center;gap:11px;flex-shrink:0;">
                                            <span x-show="scopeStatus(row) === 'self'" style="font-size:10px;font-weight:700;color:var(--status-success-fg);white-space:nowrap;">ตั้งค่าแล้ว</span>
                                            <span x-show="scopeStatus(row) === 'curriculum'" x-cloak style="font-size:10px;color:var(--fg-3);white-space:nowrap;">ใช้ของทั้งหลักสูตร</span>
                                            <span x-show="scopeStatus(row) === 'central'" x-cloak style="font-size:10px;color:var(--fg-4);white-space:nowrap;">ใช้ปฏิทินกลาง</span>
                                            <button type="button" @click="openScopeModal(row)" class="cal-override-action">
                                                <span x-show="scopeStatus(row) === 'self'">แก้ไข</span>
                                                <span x-show="scopeStatus(row) !== 'self'" x-cloak>ตั้งค่า</span>
                                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                            </button>
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            @if($isAdmin)
                @if($activeYear)
                    <div id="schedule-phase-section" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;justify-content:space-between;border:1.5px solid color-mix(in oklch,var(--brand-navy) 28%,var(--border));border-radius:12px;padding:16px 20px;margin-top:16px;margin-bottom:16px;background:linear-gradient(180deg,color-mix(in oklch,var(--brand-navy) 7%,var(--surface)),var(--surface));box-shadow:0 1px 2px rgba(0,36,84,.08),0 14px 30px -24px rgba(0,36,84,.4);">
                        <div style="display:flex;align-items:center;gap:14px;min-width:0;">
                            <span class="settings-section-step">4</span>
                            <span style="width:46px;height:46px;border-radius:11px;background:var(--brand-navy);color:#fff;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </span>
                            <div style="min-width:0;">
                                <div style="font-size:12px;color:var(--fg-3);font-weight:600;">ปีการศึกษาปัจจุบัน</div>
                                <div style="font-size:18px;font-weight:800;color:var(--fg-1);font-family:var(--font-display);">{{ $activeYear->name }}
                                    @if($activeYear->phase === 'scheduling')
                                        <span class="badge" style="background:oklch(90% 0.1 145);color:oklch(30% 0.15 145);border:1px solid oklch(70% 0.15 145);font-size:11px;margin-left:6px;vertical-align:middle;">เปิดช่วงจัดตารางอยู่</span>
                                    @elseif($activeYear->phase === 'published')
                                        <span class="badge badge-primary" style="font-size:11px;margin-left:6px;vertical-align:middle;">เผยแพร่แล้ว</span>
                                    @else
                                        <span class="badge badge-gray" style="font-size:11px;margin-left:6px;vertical-align:middle;">เตรียมข้อมูล</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if($activeYear->phase === 'preparation')
                                <form id="open-scheduling-{{ $activeYear->id }}" method="POST" action="{{ route('admin.settings.scheduling.open', $activeYear) }}" style="margin:0;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="button"
                                        class="{{ $hasSchedulingCriticals ? 'btn btn-ghost is-locked' : 'btn btn-primary' }}"
                                        style="font-size:14px;padding:10px 22px;font-weight:800;"
                                        @if($hasSchedulingCriticals)
                                            disabled
                                            title="ต้องแก้ Critical ให้หมดก่อนเปิดช่วงจัดตาราง"
                                        @else
                                            @click="startOpenScheduleCountdown('open-scheduling-{{ $activeYear->id }}', 'ปีการศึกษา {{ $activeYear->name }}')"
                                        @endif>
                                        เปิดช่วงจัดตาราง
                                    </button>
                                </form>
                            @elseif($activeYear->phase === 'scheduling')
                                <form id="close-scheduling-{{ $activeYear->id }}" method="POST" action="{{ route('admin.settings.scheduling.close', $activeYear) }}" style="margin:0;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="button" class="btn btn-ghost"
                                        style="font-size:14px;padding:10px 22px;border:1px solid var(--border);"
                                        @click="startCloseScheduleConfirm('close-scheduling-{{ $activeYear->id }}', 'ปีการศึกษา {{ $activeYear->name }}')">
                                        ปิดช่วงจัดตาราง
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif
            @endif

            {{-- ── Modal กรอกวันของขอบเขต (แยกต่อหลักสูตร/ชั้นปี) ── --}}
            <template x-if="showCalEditor">
                <div class="overlay" x-cloak @keydown.escape.window="showCalEditor = false">
                    <div class="modal-center cal-editor-modal" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                        <div class="modal-hdr" style="background: var(--bg-2);">
                            <div style="min-width:0;">
                                <div style="font-size:11px;color:var(--fg-4);" x-text="'ปฏิทินแยก · ปี ' + calYearName"></div>
                                <div class="modal-ttl" style="font-family: var(--font-display);" x-text="scopeAutoName(currentScope)"></div>
                            </div>
                            <button type="button" class="modal-cls" @click="showCalEditor = false">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg>
                            </button>
                        </div>
                        <form method="POST" class="cal-editor-form"
                            :action="editCalMode ? '{{ url($routePrefix . '/settings/calendars') }}/' + currentCal.id : ('{{ url($routePrefix . '/settings/academic-years') }}/' + calYearId + '/calendars')">
                            @csrf
                            <div class="modal-body cal-editor-body">
                                <div x-show="!editCalMode" x-cloak
                                    style="display:flex;gap:10px;align-items:flex-start;padding:11px 13px;margin-bottom:14px;border:1px solid color-mix(in oklch,var(--brand-navy) 24%,var(--border));border-radius:9px;background:color-mix(in oklch,var(--brand-navy) 4%,var(--surface));">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--brand-navy)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                    <div style="font-size:12px;color:var(--fg-3);line-height:1.6;">ขอบเขตนี้ใช้<strong style="color:var(--fg-2);">ปฏิทินกลางของปี</strong>อยู่ · ตั้งที่นี่<strong style="color:var(--fg-2);">เฉพาะเมื่อวันเปิด-ปิด/สอบต่างจากปฏิทินกลาง</strong> — กรอกวันแล้วกดบันทึก</div>
                                </div>
                                @if($errors->has('calendar_terms'))
                                    <div style="margin-bottom:10px;padding:8px 10px;background:oklch(97% 0.02 20);border:1px solid oklch(82% 0.08 25);border-radius:6px;color:var(--status-conflict-fg);font-size:12px;line-height:1.6;">
                                        @foreach($errors->get('calendar_terms') as $msg)<div>• {{ $msg }}</div>@endforeach
                                    </div>
                                @endif
                                <template x-if="editCalMode"><input type="hidden" name="_method" value="PUT"></template>
                                <input type="hidden" name="name" :value="scopeAutoName(currentScope)">
                                <template x-if="currentCal.curriculum_id"><input type="hidden" name="curriculum_id" :value="currentCal.curriculum_id"></template>
                                <template x-for="y in currentCal.year_levels" :key="'h'+y"><input type="hidden" name="year_levels[]" :value="y"></template>
                                @include('shared.settings._term_fields', ['index' => 0, 'seq' => 1, 'label' => 'ภาคเรียนที่ 1', 'model' => 'currentCal.terms'])
                                @include('shared.settings._term_fields', ['index' => 1, 'seq' => 2, 'label' => 'ภาคเรียนที่ 2', 'model' => 'currentCal.terms'])
                                <div x-show="currentCal.hasSummer" x-cloak>
                                    @include('shared.settings._term_fields', ['index' => 2, 'seq' => 3, 'label' => 'ภาคฤดูร้อน', 'model' => 'currentCal.terms'])
                                    <button type="button" @click="currentCal.hasSummer = false; currentCal.terms[2] = { name:'ภาคฤดูร้อน', start_date:'', end_date:'', midterm_start:'', midterm_end:'', final_start:'', final_end:'' }" style="font-size:12px;color:var(--status-conflict-fg);background:none;border:none;cursor:pointer;padding:2px 0;margin-bottom:6px;">ลบภาคฤดูร้อน</button>
                                </div>
                                <button type="button" x-show="!currentCal.hasSummer" @click="currentCal.hasSummer = true" class="btn btn-ghost" style="font-size:12px;padding:5px 12px;">+ เพิ่มภาคฤดูร้อน</button>
                            </div>
                            <div class="modal-foot cal-editor-foot">
                                <button type="button" x-show="editCalMode"
                                    @click="if (confirm('ลบการตั้งค่าของ ' + scopeAutoName(currentScope) + ' และกลับไปใช้ปฏิทินกลาง?')) $refs.calDelForm.submit()"
                                    class="btn btn-ghost" style="color:var(--status-conflict-fg);font-size:13px;">ลบการตั้งค่านี้</button>
                                <div style="display:flex;gap:8px;margin-left:auto;">
                                    <button type="button" class="btn btn-ghost" style="font-size:13px;" @click="showCalEditor = false">ยกเลิก</button>
                                    <button type="submit" class="btn btn-primary" style="font-size:13px;">บันทึก</button>
                                </div>
                            </div>
                        </form>
                        <form x-ref="calDelForm" method="POST" :action="'{{ url($routePrefix . '/settings/calendars') }}/' + currentCal.id" style="display:none;">
                            @csrf
                            @method('DELETE')
                        </form>
                    </div>
                </div>
            </template>

            @if($isAdmin)
                {{-- ใช้โครง modal มาตรฐาน (.overlay/.modal-center) เหมือน modal อื่น — กลางจอชัวร์ + เข้าชุด --}}
                <div class="overlay" x-show="openScheduleConfirmForm" x-cloak
                    @keydown.escape.window="cancelOpenScheduleCountdown()">
                    <div class="modal-center" style="max-width:520px;">
                        <div class="modal-hdr" style="background: var(--bg-2);">
                            <div style="min-width:0;">
                                <div class="modal-ttl" style="font-family: var(--font-display);">ยืนยันเปิดช่วงจัดตาราง</div>
                                <div style="font-size:13px;color:var(--fg-3);margin-top:4px;" x-text="openScheduleConfirmLabel"></div>
                            </div>
                            <button type="button" class="modal-cls" @click="cancelOpenScheduleCountdown()">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div style="font-size:14px;color:var(--fg-2);line-height:1.65;">
                                ระบบจะสร้างและซิงก์ Course Offering จากรายวิชา active ทั้งหมด จากนั้นหัวหน้าวิชาจะเริ่มแก้ข้อมูลเพื่อจัดตารางได้
                            </div>
                            <div style="margin-top:14px;padding:12px 14px;border:1px solid oklch(84% 0.08 80);border-radius:8px;background:oklch(98% 0.025 85);color:oklch(38% 0.08 75);font-size:13px;font-weight:700;"
                                x-text="openScheduleCountdown > 0 ? 'รอ ' + openScheduleCountdown + ' วินาที ก่อนยืนยัน' : 'พร้อมยืนยันเปิดช่วงจัดตาราง'"></div>
                        </div>
                        <div class="modal-foot" style="display:flex;justify-content:flex-end;gap:8px;">
                            <button type="button" class="btn btn-ghost" @click="cancelOpenScheduleCountdown()">ยกเลิก</button>
                            <button type="button" class="btn btn-primary" :disabled="openScheduleCountdown > 0" :style="openScheduleCountdown > 0 ? 'opacity:.55;cursor:not-allowed;' : ''" @click="confirmOpenSchedule()">
                                ยืนยันเปิดช่วงจัดตาราง
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overlay" x-show="closeScheduleConfirmForm" x-cloak
                    @keydown.escape.window="cancelCloseScheduleConfirm()">
                    <div class="modal-center" style="max-width:520px;">
                        <div class="modal-hdr" style="background: var(--bg-2);">
                            <div style="min-width:0;">
                                <div class="modal-ttl" style="font-family: var(--font-display);">ยืนยันปิดช่วงจัดตาราง</div>
                                <div style="font-size:13px;color:var(--fg-3);margin-top:4px;" x-text="closeScheduleConfirmLabel"></div>
                            </div>
                            <button type="button" class="modal-cls" @click="cancelCloseScheduleConfirm()">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div style="font-size:14px;color:var(--fg-2);line-height:1.65;">
                                ระบบจะเปลี่ยนสถานะกลับเป็น <strong style="color:var(--fg-1);">เตรียมข้อมูล</strong> และหัวหน้าวิชาจะไม่สามารถจัดหรือแก้ไขตารางในรอบนี้ต่อได้ จนกว่า Admin จะเปิดช่วงจัดตารางอีกครั้ง
                            </div>
                            <div style="margin-top:14px;padding:12px 14px;border:1px solid oklch(82% 0.055 235);border-radius:8px;background:oklch(97% 0.018 235);color:oklch(32% 0.075 245);font-size:13px;font-weight:700;line-height:1.55;">
                                ข้อมูลตารางที่จัดไว้แล้วจะยังอยู่ (ระบบจะปิดเฉพาะสิทธิ์การจัด/แก้ไขตารางชั่วคราว)
                            </div>
                            <div style="margin-top:10px;padding:12px 14px;border:1px solid oklch(84% 0.08 80);border-radius:8px;background:oklch(98% 0.025 85);color:oklch(38% 0.08 75);font-size:13px;font-weight:700;"
                                x-text="closeScheduleCountdown > 0 ? 'รอ ' + closeScheduleCountdown + ' วินาที ก่อนยืนยัน' : 'พร้อมยืนยันปิดช่วงจัดตาราง'"></div>
                        </div>
                        <div class="modal-foot" style="display:flex;justify-content:flex-end;gap:8px;">
                            <button type="button" class="btn btn-ghost" @click="cancelCloseScheduleConfirm()">ยกเลิก</button>
                            <button type="button" class="btn btn-primary" :disabled="closeScheduleCountdown > 0" :style="closeScheduleCountdown > 0 ? 'opacity:.55;cursor:not-allowed;' : ''" @click="confirmCloseSchedule()">
                                ยืนยันปิดช่วงจัดตาราง
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ── TAB: วันหยุด (แยกออกจากแท็บปีการศึกษา) · staff เห็นต่อจากปีการศึกษา ── --}}
        @if($canManageHolidays)
        <div x-show="!isAdminView || activeTab === 'holidays'" {{ $isAdmin ? 'x-cloak' : '' }} {{ $isAdmin ? '' : 'style=margin-top:16px;' }}>
            <div class="card">
                <div class="card-hdr">
                    <div>
                        <div class="card-ttl">วันหยุดราชการ (ระบบจะสร้างวันหยุดให้อัติโนมัติเมื่อเลือกปีการศึกษาปัจจุบัน)</div>
                    </div>
                    <div class="card-actions" style="display: flex; gap: 8px;">
                        <form method="POST" action="{{ route($routePrefix . '.settings.holidays.sync') }}" style="margin: 0;">
                            @csrf
                            <button type="submit" class="btn btn-ghost" style="font-size: 13px;">ดึงวันหยุดซ้ำ</button>
                        </form>
                        <button type="button" class="btn btn-primary" data-testid="settings-add-holiday-button" @click="openAddHoliday()">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            เพิ่มวันหยุด
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 160px;">วันที่</th>
                                <th>ชื่อวันหยุด</th>
                                <th style="width: 110px;">ที่มา</th>
                                <th style="text-align: center; width: 80px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($holidays as $h)
                                <tr data-testid="settings-holiday-row" data-holiday-name="{{ $h->name }}" @if($h->source === 'manual') style="background: var(--accent-bg, #eef4ff);" @endif>
                                    <td style="font-family: var(--font-mono);">{{ \App\Support\ThaiDate::formatForInput($h->date) }}</td>
                                    <td style="color: var(--fg-1);">{{ $h->name }}@if($h->remark)<span style="color: var(--fg-3); font-size: 12px;"> · {{ $h->remark }}</span>@endif</td>
                                    <td>
                                        @if($h->source === 'manual')
                                            <span class="badge" style="background: var(--accent-fg, #2563EB); color: #fff;">เพิ่มเอง</span>
                                        @else
                                            <span class="badge badge-gray">อัตโนมัติ</span>
                                        @endif
                                    </td>
                                    <td style="text-align: center;">
                                        <button type="button" class="action-btn" title="แก้ไข"
                                            @click="openEditHoliday({{ Js::from(['id' => $h->id, 'date' => optional($h->date)->format('Y-m-d'), 'name' => $h->name, 'remark' => $h->remark]) }})">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align: center; color: var(--fg-3); padding: 28px;">ยังไม่มีวันหยุด — ระบบดึงให้อัตโนมัติเมื่อสร้างปีการศึกษา หรือกด "ดึงวันหยุดซ้ำ"</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <!-- Add/Edit Modal (Academic Year) -->
        <template x-if="showModal && activeTab === 'academic'">
            <div class="overlay" x-cloak @keydown.escape.window="showModal = false">
                <div class="modal-center" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);"
                            x-text="editMode ? 'แก้ไขปีการศึกษา' : 'เพิ่มปีการศึกษาใหม่'"></div>
                        <button type="button" class="modal-cls" @click="showModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                    <form
                        :action="editMode ? '{{ url($routePrefix . '/settings/academic-years') }}/' + currentYear.id : '{{ route($routePrefix . '.settings.years.store') }}'"
                        method="POST">
                        @csrf
                        <template x-if="editMode">
                            <input type="hidden" name="_method" value="PUT">
                        </template>
                        <template x-if="editMode">
                            <input type="hidden" name="year_id" x-model="currentYear.id">
                        </template>
                        {{-- โหมดเพิ่มปี: เผื่อที่ว่างด้านล่างให้ dropdown "ลอกจากปีก่อน" เปิดลงล่างได้ ไม่เด้งขึ้นทับช่องบน --}}
                        <div class="modal-body" :style="!editMode ? 'min-height: 320px;' : ''">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>ปีการศึกษา (พ.ศ.)</label>
                                    <input type="text" name="name" x-model="currentYear.name" required
                                        data-testid="settings-year-name"
                                        placeholder="เช่น 2569"
                                        @class(['input-invalid' => $errors->has('name')])>
                                    @error('name')
                                        <span style="color: var(--red, #dc2626); font-size: 12px; margin-top: 4px; display: block;">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <template x-if="!editMode && calYearOptions.length > 0">
                                <div class="form-group" style="margin-top: 4px;">
                                    <label>คัดลอกปฏิทินจากปีก่อน <span style="font-weight:400;color:var(--fg-4);font-size:11px;">(ไม่บังคับ)</span></label>
                                    {{-- tpss-choices + tpssInitChoices = ใช้ custom dropdown ของระบบ (เหมือน admin/users, ตารางสอน) แทน native --}}
                                    <select name="copy_from_year_id" x-model="copyFromYearId" class="tpss-choices"
                                        x-init="$nextTick(() => window.tpssInitChoices && window.tpssInitChoices($el))">
                                        <option value="">ไม่คัดลอก</option>
                                        <template x-for="y in calYearOptions" :key="y.id">
                                            <option :value="y.id" x-text="'คัดลอกจากปี ' + y.name"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                            <template x-if="editMode">
                                <div style="margin-top: 4px; border-top: 1px solid var(--border); padding-top: 14px;">
                                    <button type="button" class="btn btn-ghost" style="font-size:12px;padding:6px 14px;"
                                        @click="showModal = false; selectOverrideYear(currentYear.id); $nextTick(() => document.getElementById('cal-override-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))">ตั้งปฏิทินแยกตามหลักสูตร/ชั้นปี →</button>
                                </div>
                            </template>
                            <div class="form-group" style="margin-top: 18px;">
                                <label
                                    :style="(currentYear.is_active
                                        ? 'border-color:var(--brand-navy);background:color-mix(in oklch,var(--brand-navy) 8%,var(--surface));box-shadow:0 1px 2px rgba(0,36,84,.1);'
                                        : 'border-color:var(--border);background:var(--surface);')
                                        + 'display:flex;align-items:center;gap:14px;cursor:pointer;border-width:1.5px;border-style:solid;border-radius:10px;padding:14px 16px;transition:all .15s ease;'">
                                    <input type="checkbox" name="is_active" value="1" x-model="currentYear.is_active" style="display:none;">
                                    <span :style="(currentYear.is_active ? 'background:var(--brand-navy);color:#fff;' : 'background:var(--bg-3);color:var(--fg-3);') + 'width:40px;height:40px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s ease;'">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/></svg>
                                    </span>
                                    <div style="flex:1;min-width:0;">
                                        <div style="font-weight:700;font-size:14px;color:var(--fg-1);"
                                            x-text="currentYear.is_active ? 'เป็นปีการศึกษาปัจจุบัน' : 'ตั้งเป็นปีการศึกษาปัจจุบัน'"></div>
                                    </div>
                                    <span :style="(currentYear.is_active ? 'background:var(--brand-navy);' : 'background:var(--border-strong,#cbd5e1);') + 'width:44px;height:25px;border-radius:999px;position:relative;flex-shrink:0;transition:background .15s ease;'">
                                        <span :style="(currentYear.is_active ? 'left:22px;' : 'left:3px;') + 'position:absolute;top:3px;width:19px;height:19px;border-radius:50%;background:#fff;transition:left .15s ease;box-shadow:0 1px 2px rgba(0,0,0,.25);'"></span>
                                    </span>
                                </label>
                                @error('is_active')
                                    <span style="color: var(--red, #dc2626); font-size: 12px; margin-top: 6px; display: block;">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-foot" style="display:flex;justify-content:space-between;align-items:center;">
                            <template x-if="editMode && !currentYear.is_active">
                                <button type="button" class="btn btn-ghost" style="color:var(--status-conflict-fg);"
                                    @click="if (confirm('ลบปีการศึกษา ' + currentYear.name + ' และปฏิทิน/เทอมทั้งหมดของปีนี้?\n(ลบไม่ได้ถ้ามีรายวิชาที่เปิดสอนผูกอยู่)')) $refs.deleteYearForm.submit()">ลบปีการศึกษา</button>
                            </template>
                            <div style="display:flex;gap:8px;margin-left:auto;">
                                <button type="button" class="btn btn-ghost" @click="showModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary" data-testid="settings-year-submit">บันทึกข้อมูล</button>
                            </div>
                        </div>
                    </form>
                    <form x-ref="deleteYearForm" method="POST" :action="'{{ url($routePrefix . '/settings/academic-years') }}/' + currentYear.id" style="display:none;">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </template>


        @if($canManageHolidays)
        <!-- Add/Edit Holiday Modal -->
        <template x-if="showHolidayModal">
            <div class="overlay" x-cloak>
                <div class="modal-center" style="max-width: 480px;"
                    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                    <div class="modal-hdr" style="background: var(--bg-2);">
                        <div class="modal-ttl" style="font-family: var(--font-display);" x-text="editHolidayMode ? 'แก้ไขวันหยุด' : 'เพิ่มวันหยุด'"></div>
                        <button type="button" class="modal-cls" @click="showHolidayModal = false">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <form :action="editHolidayMode ? '{{ url($routePrefix . '/settings/holidays') }}/' + currentHoliday.id : '{{ route($routePrefix . '.settings.holidays.store') }}'" method="POST">
                        @csrf
                        <input type="hidden" name="_method" value="PUT" :disabled="!editHolidayMode">
                        <div class="modal-body">
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label>วันที่ <span style="color: var(--status-conflict-fg)">*</span></label>
                                <x-thai-date-input name="date" x-model="currentHoliday.date" required helper="" data-testid="settings-holiday-date" />
                                @error('date')<span style="color: var(--red, #dc2626); font-size: 12px; display: block; margin-top: 4px;">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label>ชื่อวันหยุด <span style="color: var(--status-conflict-fg)">*</span></label>
                                <input type="text" name="name" x-model="currentHoliday.name" required maxlength="255" placeholder="เช่น วันสงกรานต์" data-testid="settings-holiday-name">
                                @error('name')<span style="color: var(--red, #dc2626); font-size: 12px; display: block; margin-top: 4px;">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-group">
                                <label>หมายเหตุ</label>
                                <input type="text" name="remark" x-model="currentHoliday.remark" maxlength="255" placeholder="(ถ้ามี)">
                            </div>
                        </div>
                        <div class="modal-foot" style="display: flex; justify-content: space-between;">
                            <button type="button" class="btn btn-ghost" x-show="editHolidayMode"
                                @click="$refs.deleteHolidayForm.submit()"
                                style="color: var(--status-conflict-fg);">ลบ</button>
                            <div style="display: flex; gap: 8px; margin-left: auto;">
                                <button type="button" class="btn btn-ghost" @click="showHolidayModal = false">ยกเลิก</button>
                                <button type="submit" class="btn btn-primary" data-testid="settings-holiday-submit">บันทึก</button>
                            </div>
                        </div>
                    </form>
                    <form x-ref="deleteHolidayForm" :action="'{{ url($routePrefix . '/settings/holidays') }}/' + currentHoliday.id" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </template>

        @endif
        @if($isAdmin)
        <!-- Tab: PA Rules -->
        <div x-show="activeTab === 'pa'" x-cloak>
            <form action="{{ route('admin.settings.constants.update') }}" method="POST">
                @csrf
                <div class="settings-grid">

                    <div class="card">
                        <div class="card-hdr">
                            <div class="card-ttl">ค่าคงที่ภาระงานประจำปี</div>
                        </div>

                        {{-- Input rows --}}
                        <div style="border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 12px 20px;">
                            <div>
                                <div style="font-size: 13px; font-weight: 600; color: var(--fg-1);">สัปดาห์ทำงานรวม / ปี</div>
                                <div style="font-size: 11px; color: var(--fg-3); margin-top: 1px;">ใช้คำนวณปฏิบัติงานรวม</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="number" name="teaching_quota_weeks" x-model.number="workloadWeeks" min="1" required style="width: 72px; text-align: center; font-weight: 700;">
                                <span style="font-size: 12px; color: var(--fg-3); width: 100px;">สัปดาห์</span>
                            </div>
                        </div>
                        <div style="border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 12px 20px;">
                            <div>
                                <div style="font-size: 13px; font-weight: 600; color: var(--fg-1);">สัปดาห์งานสอน / ปี</div>
                                <div style="font-size: 11px; color: var(--fg-3); margin-top: 1px;">ใช้คำนวณฐานงานสอน (PA)</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="number" name="teaching_load_weeks" x-model.number="teachingWeeks" min="1" required style="width: 72px; text-align: center; font-weight: 700;">
                                <span style="font-size: 12px; color: var(--fg-3); width: 100px;">สัปดาห์</span>
                            </div>
                        </div>
                        <div style="border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 12px 20px;">
                            <div>
                                <div style="font-size: 13px; font-weight: 600; color: var(--fg-1);">ชั่วโมงทำงาน / สัปดาห์</div>
                                <div style="font-size: 11px; color: var(--fg-3); margin-top: 1px;">ใช้กับทั้งสองสูตรข้างต้น</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="number" name="teaching_quota_hours_per_week" x-model.number="workloadHoursPerWeek" min="1" required style="width: 72px; text-align: center; font-weight: 700;">
                                <span style="font-size: 12px; color: var(--fg-3); width: 100px;">ชั่วโมง/สัปดาห์</span>
                            </div>
                        </div>

                        {{-- Calculated results --}}
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; border-bottom: 1px solid var(--border); background: var(--bg-2);">
                            <span style="font-size: 12px; color: var(--fg-3);">ปฏิบัติงานรวม</span>
                            <span style="font-size: 15px; font-weight: 700; color: var(--fg-1);" x-text="totalQuota + ' ชั่วโมง/ปี'"></span>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; background: var(--brand-navy);">
                            <span style="font-size: 12px; color: rgba(255,255,255,0.7);">เกณฑ์ภาระงานสอน</span>
                            <span style="font-size: 15px; font-weight: 700; color: #fff;" x-text="teachingQuota + ' ชั่วโมง/ปี'"></span>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-hdr">
                            <div class="card-ttl">สัดส่วนเกณฑ์ PA ตามตำแหน่งทางวิชาการ</div>
                            <div style="font-size: 12px; color: var(--fg-3);">ระบุช่วง ต่ำสุด – สูงสุด (%)</div>
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ตำแหน่ง / ประเภท</th>
                                        @foreach(['สอน','วิจัย','บริการฯ','ศิลปะฯ','มอบหมาย'] as $col)
                                        <th style="text-align: center;">{{ $col }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($paCriteria as $rank => $ranges)
                                    <tr>
                                        <td style="font-weight: 600; color: var(--fg-1); white-space: nowrap;">{{ str_replace('_', ' ', $rank) }}</td>
                                        @foreach(['t','r','s','c','o'] as $f)
                                        @php $range = $ranges[$f] ?? ['min'=>0,'max'=>100]; @endphp
                                        <td style="padding: 6px 8px;">
                                            <div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
                                                <div style="display: flex; align-items: center; gap: 4px;">
                                                    <span style="font-size: 10px; color: var(--fg-3); width: 22px;">min</span>
                                                    <input type="number" name="pa_criteria[{{ $rank }}][{{ $f }}][min]"
                                                           value="{{ $range['min'] }}" min="0" max="100"
                                                           class="pa-input" style="width: 52px; text-align: center;">
                                                    <span style="font-size: 11px; color: var(--fg-3);">%</span>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 4px;">
                                                    <span style="font-size: 10px; color: var(--fg-3); width: 22px;">max</span>
                                                    <input type="number" name="pa_criteria[{{ $rank }}][{{ $f }}][max]"
                                                           value="{{ $range['max'] }}" min="0" max="100"
                                                           class="pa-input" style="width: 52px; text-align: center;">
                                                    <span style="font-size: 11px; color: var(--fg-3);">%</span>
                                                </div>
                                            </div>
                                        </td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div style="padding: 16px 20px; border-top: 1px solid var(--border); background: var(--surface); text-align: right;">
                            <button type="submit" class="btn btn-primary">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                บันทึกการตั้งค่า
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        @endif

    </div>

    <style>
        [x-cloak] { display: none !important; }

        .settings-grid {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 24px;
            align-items: start;
        }
        .settings-grid > div { min-width: 0; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            width: 100%;
        }

        .settings-hdr {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--surface);
            gap: 16px;
        }
        .hdr-helper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .copy-box {
            display: flex;
            align-items: center;
            gap: 4px;
            background: var(--surface);
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        .academic-year-icons {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        .settings-action-cell {
            text-align: center;
        }
        .settings-page .is-locked {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .settings-page input.input-invalid {
            border-color: var(--red, #dc2626) !important;
        }

        .settings-section-title {
            display: inline-flex;
            align-items: center;
            gap: 9px;
        }

        .settings-section-step {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 28px;
            width: 28px;
            height: 28px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 42%, var(--border));
            border-radius: 999px;
            background: color-mix(in oklch, var(--brand-navy) 14%, var(--surface));
            color: var(--brand-navy);
            font-family: var(--font-display);
            font-size: 13px;
            font-weight: 800;
            line-height: 1;
        }

        .settings-tab-button {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            padding: 8px 14px !important;
            border-radius: 6px !important;
            text-align: left;
        }

        .settings-tab-icon {
            flex-shrink: 0;
        }

        .settings-tab-copy {
            display: flex;
            flex-direction: column;
            gap: 1px;
            line-height: 1.2;
        }

        .settings-tab-copy small {
            max-width: 170px;
            overflow: hidden;
            color: currentColor;
            font-size: 10px;
            font-weight: 500;
            opacity: .72;
            text-overflow: ellipsis;
        }

        .settings-start-guide {
            margin-bottom: 14px;
            overflow: hidden;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 36%, var(--border));
            border-radius: 10px;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 12%, var(--surface)), var(--surface) 62%),
                var(--surface);
            box-shadow: 0 2px 4px rgba(0, 36, 84, .12), 0 14px 28px -24px rgba(0, 36, 84, .55);
        }

        .settings-start-guide__header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 14px;
            padding: 12px 18px;
            border-bottom: 1px solid color-mix(in oklch, var(--brand-navy) 28%, var(--border));
        }

        .settings-start-guide__eyebrow {
            margin-bottom: 4px;
            color: var(--brand-navy);
            font-size: 11px;
            font-weight: 800;
        }

        .settings-start-guide h2 {
            margin: 0;
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 800;
            line-height: 1.25;
        }

        .settings-start-guide p {
            margin: 3px 0 0;
            color: var(--fg-2);
            font-size: 12px;
            line-height: 1.4;
        }

        .settings-start-guide__summary {
            display: grid;
            gap: 2px;
            min-width: 136px;
            padding: 8px 12px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 28%, var(--border));
            border-radius: 8px;
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            text-align: right;
        }

        .settings-start-guide__summary span {
            color: var(--fg-2);
            font-size: 11px;
            font-weight: 600;
        }

        .settings-start-guide__summary strong {
            color: var(--fg-1);
            font-family: var(--font-display);
            font-size: 15px;
        }

        .settings-setup-steps {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            padding: 10px;
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
        }

        .settings-setup-step {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: stretch;
            gap: 10px;
            min-height: 128px;
            padding: 12px 13px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
            border-radius: 16px;
            background: color-mix(in oklch, var(--brand-navy) 2%, var(--surface));
            box-shadow: 0 8px 18px -18px rgba(0, 36, 84, .42);
        }

        .settings-setup-step:last-child {
            border-bottom: 0;
        }

        .settings-setup-step__mark {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 38%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 12%, var(--surface));
            color: var(--brand-navy);
            font-size: 13px;
            font-weight: 800;
        }

        .settings-setup-step__body {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            grid-template-rows: auto minmax(34px, 1fr) 28px;
            min-width: 0;
            height: 100%;
            align-items: stretch;
        }

        .settings-setup-step__top {
            display: grid;
            gap: 5px;
            min-width: 0;
        }

        .settings-setup-step h3 {
            margin: 0;
            color: var(--fg-1);
            font-size: 13px;
            font-weight: 800;
            line-height: 1.3;
        }

        .settings-setup-step p {
            display: none;
            margin: 0;
            color: var(--fg-3);
            font-size: 12px;
            line-height: 1.55;
        }

        .settings-step-task {
            grid-column: 1;
            grid-row: 2;
            display: -webkit-box;
            overflow: hidden;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            margin-top: 7px;
            color: color-mix(in oklch, var(--brand-navy) 76%, var(--fg-1));
            font-size: 11px;
            font-weight: 700;
            line-height: 1.35;
        }

        .settings-step-badge {
            width: fit-content;
            max-width: 100%;
            padding: 3px 8px;
            border-radius: 999px;
            background: color-mix(in oklch, var(--brand-navy) 10%, var(--surface));
            color: var(--brand-navy);
            font-size: 10px;
            font-weight: 800;
            line-height: 1.2;
            white-space: nowrap;
        }

        .settings-step-action,
        .settings-step-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            grid-column: 1;
            grid-row: 3;
            align-self: end;
            justify-self: start;
            min-height: 28px;
            height: 28px;
            margin-top: 0;
            font-size: 11px;
            line-height: 1.2;
        }

        .settings-step-link {
            appearance: none;
            -webkit-appearance: none;
            max-width: 100%;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 28%, var(--border));
            border-radius: 999px;
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            color: var(--brand-navy);
            cursor: pointer;
            font-weight: 800;
            padding: 6px 12px;
            text-align: left;
            text-decoration: none;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
        }

        .settings-step-link:hover,
        .settings-step-link:focus-visible {
            border-color: color-mix(in oklch, var(--brand-navy) 48%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 14%, var(--surface));
            box-shadow: 0 6px 14px -12px rgba(0, 36, 84, .7);
            outline: none;
        }

        .settings-step-action.btn {
            padding: 6px 12px;
            border-radius: 999px;
            line-height: 1.2;
        }

        .settings-setup-step.is-done {
            background: color-mix(in oklch, var(--brand-navy) 6%, var(--surface));
        }

        .settings-setup-step.is-done .settings-setup-step__mark {
            border-color: color-mix(in oklch, var(--status-success-fg) 48%, var(--border));
            background: color-mix(in oklch, var(--status-success-fg) 24%, var(--surface));
            color: var(--status-success-fg);
            box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--status-success-fg) 18%, transparent);
        }

        .settings-setup-step.is-done .settings-step-badge {
            border-color: color-mix(in oklch, var(--brand-navy) 28%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 11%, var(--surface));
            color: var(--brand-navy);
        }

        .settings-setup-step.is-next {
            background: color-mix(in oklch, var(--brand-navy) 12%, var(--surface));
            border-color: color-mix(in oklch, var(--brand-navy) 42%, var(--border));
            box-shadow: inset 0 3px 0 var(--brand-navy), 0 10px 20px -18px rgba(0, 36, 84, .68);
        }

        .settings-setup-step.is-next .settings-setup-step__mark,
        .settings-setup-step.is-next .settings-step-badge {
            border-color: color-mix(in oklch, var(--brand-navy) 34%, var(--border));
            background: var(--brand-navy);
            color: var(--surface);
        }

        .settings-setup-step.is-waiting {
            background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
        }

        .settings-setup-step.is-waiting .settings-setup-step__mark,
        .settings-setup-step.is-waiting .settings-step-badge {
            border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            color: color-mix(in oklch, var(--brand-navy) 70%, var(--fg-2));
        }

        .settings-setup-step.is-optional .settings-setup-step__mark,
        .settings-setup-step.is-optional .settings-step-badge {
            border-color: color-mix(in oklch, var(--brand-navy) 36%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 15%, var(--surface));
            color: var(--brand-navy);
        }

        .settings-setup-step.is-issue {
            background: color-mix(in oklch, var(--brand-navy) 6%, var(--surface));
        }

        .settings-setup-step.is-issue .settings-setup-step__mark,
        .settings-setup-step.is-issue .settings-step-badge {
            border-color: color-mix(in oklch, var(--status-conflict-fg) 40%, var(--border));
            background: color-mix(in oklch, var(--status-conflict-fg) 18%, var(--surface));
            color: var(--status-conflict-fg);
        }

        #schedule-phase-section,
        #academic-year-section,
        #central-calendar-section,
        #cal-override-section {
            scroll-margin-top: 112px;
        }

        .central-calendar-card-body {
            padding: 16px 20px 18px;
        }

        .central-calendar-summary {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 18px;
            min-height: 76px;
            padding: 16px 18px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 14%, var(--border));
            border-radius: 8px;
            background: color-mix(in oklch, var(--brand-navy) 2%, var(--surface));
        }

        .central-calendar-main {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .central-calendar-icon {
            display: flex;
            flex: 0 0 48px;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 8px;
            background: var(--brand-navy);
            box-shadow: 0 8px 18px -14px rgba(0, 36, 84, 0.55);
        }

        .central-calendar-copy {
            min-width: 0;
        }

        .central-calendar-action {
            flex-shrink: 0;
            min-width: 150px;
            justify-content: center;
            font-size: 13px;
            white-space: nowrap;
        }

        .cal-override-list {
            overflow: hidden;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
            border-radius: 8px;
            background: color-mix(in oklch, var(--brand-navy) 3%, var(--surface));
        }

        .cal-override-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            min-height: 44px;
            padding: 12px 18px;
            border-bottom: 1px solid color-mix(in oklch, var(--brand-navy) 12%, var(--border));
            transition: background-color .16s ease, box-shadow .16s ease;
        }

        .cal-override-row:last-child {
            border-bottom: 0;
        }

        .cal-override-row:hover,
        .cal-override-row:focus-within {
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
            box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--brand-navy) 8%, transparent);
        }

        .cal-override-row.is-group {
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        }

        .cal-override-row.is-group:hover,
        .cal-override-row.is-group:focus-within {
            background: color-mix(in oklch, var(--brand-navy) 7%, var(--surface));
        }

        .cal-override-row.is-child {
            padding-left: 44px;
            background: color-mix(in oklch, var(--brand-navy) 2%, var(--surface));
        }

        .cal-override-row.is-child:hover,
        .cal-override-row.is-child:focus-within {
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        }

        .cal-override-title-button,
        .cal-override-action {
            appearance: none;
            -webkit-appearance: none;
            border: 0;
            background: transparent !important;
            box-shadow: none !important;
            color: inherit;
            font: inherit;
        }

        .cal-override-title-button {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
            padding: 0;
            text-align: left;
            cursor: pointer;
        }

        .cal-override-title-button:hover,
        .cal-override-title-button:active,
        .cal-override-title-button:focus {
            background: transparent !important;
            outline: none;
        }

        .cal-override-title-button:focus-visible,
        .cal-override-action:focus-visible {
            outline: 2px solid color-mix(in oklch, var(--brand-navy) 44%, transparent);
            outline-offset: 3px;
            border-radius: 4px;
        }

        .cal-override-action {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0;
            color: var(--brand-navy);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
            cursor: pointer;
        }

        .cal-override-action:hover,
        .cal-override-action:active,
        .cal-override-action:focus {
            background: transparent !important;
            color: color-mix(in oklch, var(--brand-navy) 78%, var(--fg-1));
            outline: none;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .cal-editor-modal {
            display: flex;
            flex-direction: column;
            width: min(720px, calc(100vw - 32px));
            max-width: 720px;
            max-height: min(92vh, 820px);
            overflow: hidden;
        }

        .cal-editor-modal .modal-hdr {
            flex: 0 0 auto;
        }

        .cal-editor-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
            padding-bottom: 0 !important;
        }

        .cal-editor-form {
            display: flex;
            flex: 1 1 auto;
            min-height: 0;
            flex-direction: column;
            overflow: hidden;
        }

        .cal-editor-foot {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin: 0;
            padding: 14px 24px calc(14px + env(safe-area-inset-bottom, 0px));
            border-top: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border)) !important;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 6%, var(--surface)), var(--surface));
            box-shadow: 0 -12px 24px -20px rgba(0, 36, 84, 0.36);
        }

        @media (max-width: 1120px) {
            .settings-setup-steps {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-height: 760px) {
            .cal-editor-modal {
                max-height: calc(100vh - 24px);
            }

            .cal-editor-body {
                padding-top: 16px !important;
            }

            .cal-editor-foot {
                padding-top: 12px;
                padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
            }
        }

        @media (max-width: 680px) {
            .settings-tab-copy small {
                display: none;
            }

            .settings-start-guide__header {
                align-items: stretch;
                grid-template-columns: 1fr;
                padding: 16px;
            }

            .settings-start-guide__summary {
                min-width: 0;
                text-align: left;
            }

            .settings-setup-steps {
                grid-template-columns: 1fr;
                gap: 8px;
                padding: 8px;
            }

            .settings-setup-step {
                min-height: 0;
                padding: 14px;
                border-radius: 14px;
            }

            .central-calendar-card-body {
                padding: 14px;
            }

            .central-calendar-summary {
                grid-template-columns: 1fr;
                align-items: stretch;
                gap: 14px;
                padding: 14px;
            }

            .central-calendar-action {
                width: 100%;
                min-width: 0;
            }

            .cal-editor-modal {
                width: calc(100vw - 20px);
                max-height: calc(100vh - 20px);
            }

            .cal-editor-foot {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .cal-editor-foot > div {
                width: 100%;
            }

            .cal-editor-foot .btn {
                justify-content: center;
                width: 100%;
            }
        }

        .settings-flash {
            position: relative;
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            padding: 14px 16px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
            border-radius: var(--r-lg);
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 6%, var(--surface)), var(--surface));
            color: var(--fg-2);
            font-size: 14px;
            line-height: 1.6;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.08),
                0 16px 34px -28px rgba(0, 36, 84, 0.42);
            overflow: hidden;
        }

        .settings-flash::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: var(--settings-flash-accent, var(--brand-navy));
        }

        .settings-flash-icon {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: color-mix(in oklch, var(--settings-flash-accent, var(--brand-navy)) 12%, transparent);
            color: var(--settings-flash-accent, var(--brand-navy));
        }

        .settings-flash--success {
            --settings-flash-accent: var(--status-success-fg);
        }

        .settings-flash--error {
            --settings-flash-accent: var(--status-conflict-fg);
        }

        @media (max-width: 1024px) {
            .settings-grid { grid-template-columns: 1fr; }
            .settings-hdr {
                flex-direction: column;
                align-items: flex-start;
            }
            .hdr-helper {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
            .tabs-container {
                justify-content: flex-start !important;
                width: 100%;
            }
        }

        .pa-input {
            width: 80px; margin: 0 auto; display: block;
            padding: 8px !important; font-size: 13px !important;
            text-align: center; border: 1px solid var(--border) !important;
            border-radius: 6px !important; background: white; color: var(--fg-1);
        }
        .pa-input:focus {
            border-color: var(--brand-navy) !important; outline: none;
            box-shadow: inset 0 0 0 1px var(--brand-navy);
        }
        .btn-ghost:hover { background: var(--bg-3); }

        .settings-page {
            padding: clamp(14px, 2vw, 28px);
            background:
                radial-gradient(circle at 8% 0%, color-mix(in oklch, var(--brand-navy) 10%, transparent), transparent 30%),
                linear-gradient(180deg,
                    color-mix(in oklch, var(--brand-navy) 7%, var(--bg)) 0%,
                    color-mix(in oklch, var(--brand-navy) 4%, var(--bg)) 34%,
                    var(--bg) 100%);
        }

        .settings-page .card,
        .settings-page .settings-panel,
        .settings-page .settings-card,
        .settings-page .stats-card,
        .settings-page .stat-card,
        .settings-page .panel {
            border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border)) !important;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 5%, var(--surface)), var(--surface) 46%),
                var(--surface) !important;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.09),
                0 16px 34px -22px rgba(0, 36, 84, 0.42) !important;
        }

        .settings-page .card-hdr,
        .settings-page .settings-hdr,
        .settings-page .panel-hdr,
        .settings-page thead th {
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 20%, var(--border)) !important;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 10%, var(--surface)), color-mix(in oklch, var(--brand-navy) 4%, var(--surface))) !important;
        }

        .settings-page .tabs,
        .settings-page [role="tablist"] {
            border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border)) !important;
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface)) !important;
            box-shadow: 0 1px 2px rgba(0, 36, 84, 0.08);
        }

        .settings-page .btn-primary {
            border-color: var(--brand-navy) !important;
            background: var(--brand-navy) !important;
            color: var(--fg-on-brand) !important;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.16),
                0 10px 20px -16px rgba(0, 36, 84, 0.64);
        }

        .settings-page .btn-ghost,
        .settings-page .btn-secondary,
        .settings-page .btn:not(.btn-primary) {
            border-color: color-mix(in oklch, var(--brand-navy) 24%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
            color: var(--brand-navy);
        }

        .settings-page .btn-ghost:hover,
        .settings-page .btn-secondary:hover,
        .settings-page .btn:not(.btn-primary):hover {
            border-color: color-mix(in oklch, var(--brand-navy) 36%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 9%, var(--surface));
        }

        .settings-page .form-ctrl,
        .settings-page input,
        .settings-page select,
        .settings-page textarea,
        .settings-page .pa-input {
            border-color: color-mix(in oklch, var(--brand-navy) 22%, var(--border)) !important;
            /* ใช้ background-color (ไม่ใช่ shorthand background) เพื่อไม่ลบ background-image
               ของ select กลาง (ลูกศร chevron + gradient) — dropdown จะเข้าชุดกับหน้าอื่น */
            background-color: color-mix(in oklch, var(--brand-navy) 3%, var(--surface)) !important;
        }

        /* modal กลาง "พื้นที่เนื้อหา" ไม่นับ sidebar — dim เต็มจอ แต่ขยับจุดกึ่งกลางขวาเท่าความกว้าง sidebar
           (--sidebar-w = 0 บนมือถือ → กลับมาเต็มจอเอง) */
        .settings-page .form-ctrl:focus,
        .settings-page input:focus,
        .settings-page select:focus,
        .settings-page textarea:focus,
        .settings-page .pa-input:focus {
            border-color: var(--brand-navy) !important;
            box-shadow: 0 0 0 3px color-mix(in oklch, var(--brand-navy) 12%, transparent) !important;
        }

        .settings-page table th {
            color: color-mix(in oklch, var(--brand-navy) 72%, var(--fg-2));
        }

        .settings-page table td {
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 10%, var(--border-subtle));
        }

        .settings-page tbody tr:hover {
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
        }
    </style>
</x-app-layout>
