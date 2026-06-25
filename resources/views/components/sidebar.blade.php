@php
    $user = auth()->user();
    $activeRole = session('active_role', 'staff');
    $roles = $user ? $user->roles : collect();
    $sidebarBadges = $sidebarBadges ?? [];

    $roleNames = [
        'admin' => 'ผู้ดูแลระบบ',
        'staff' => 'เจ้าหน้าที่',
        'course_head' => 'หัวหน้าวิชา',
        'executive' => 'ผู้บริหาร',
        'instructor' => 'อาจารย์ผู้สอน',
    ];

    // Get current path to highlight active menu
    $currentPath = request()->path();
@endphp

<div class="sidebar" :class="{ 'is-open': sidebarOpen }">
    <!-- Logo -->
    <div class="sb-logo">
        <img src="{{ asset('picture/Mahidol_U_logo.png') }}" alt="Logo"
            style="width: 42px; height: 42px; object-fit: contain; flex-shrink: 0;">
        <div>
            <div class="sb-name">ระบบจัดตารางสอน</div>
            <div class="sb-sub">คณะพยาบาลศาสตร์ ม.มหิดล</div>
        </div>
    </div>

    <!-- User & Role Switcher -->
    @php
        $roleTheme = [
            'admin'       => ['bg' => 'oklch(95% 0.02 240 / 0.1)', 'fg' => 'var(--brand-gold)', 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
            'staff'       => ['bg' => 'oklch(96% 0.02 200 / 0.1)', 'fg' => 'oklch(85% 0.10 200)', 'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
            'course_head' => ['bg' => 'oklch(96% 0.04 80 / 0.1)',  'fg' => 'oklch(85% 0.12 80)',  'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
            'executive'   => ['bg' => 'oklch(95% 0.04 290 / 0.1)', 'fg' => 'oklch(85% 0.15 290)', 'icon' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
            'instructor'  => ['bg' => 'oklch(96% 0.05 150 / 0.1)', 'fg' => 'oklch(85% 0.15 150)', 'icon' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>'],
        ][$activeRole] ?? ['bg' => 'rgba(255,255,255,0.1)', 'fg' => '#fff', 'icon' => ''];
    @endphp

    <div class="sb-user" x-data="{ roleMenuOpen: false }">
        <div class="sb-av" style="background: {{ $roleTheme['bg'] }}; color: {{ $roleTheme['fg'] }}; border-color: color-mix(in oklch, {{ $roleTheme['fg'] }} 40%, transparent); border-radius: 10px;">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                {!! $roleTheme['icon'] !!}
            </svg>
        </div>


        <div>
            <div class="sb-uname">{{ $user->name ?? 'Guest' }}</div>

            <div class="role-sw" @click="roleMenuOpen = !roleMenuOpen" @click.outside="roleMenuOpen = false">
                {{ $roleNames[$activeRole] ?? $activeRole }}
                <svg class="role-sw-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 9l6 6 6-6" />
                </svg>

                <!-- Role Dropdown Menu -->
                <div class="role-drop" :class="{ 'open': roleMenuOpen }" x-cloak @click.stop>
                    <div class="rd-hd">สลับบทบาท</div>
                    @if($roles->count() > 0)
                        @php
                            $roleIcons = [
                                'admin'       => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
                                'staff'       => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
                                'course_head' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
                                'executive'   => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
                                'instructor'  => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
                            ];
                        @endphp
                        @foreach($roles as $r)
                            @if($r->role === $activeRole)
                                <div class="rd-item rd-active">
                                    <svg class="rd-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $roleIcons[$r->role] ?? '' !!}</svg>
                                    <span class="rd-label">{{ $roleNames[$r->role] ?? $r->role }}</span>
                                    <span class="rd-cur">✓ ใช้งานอยู่</span>
                                </div>
                            @else
                                <form method="POST" action="{{ route('switch-role') }}" style="margin:0;">
                                    @csrf
                                    <input type="hidden" name="role" value="{{ $r->role }}">
                                    <button type="submit" class="rd-item rd-switch">
                                        <svg class="rd-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $roleIcons[$r->role] ?? '' !!}</svg>
                                        <span class="rd-label">{{ $roleNames[$r->role] ?? $r->role }}</span>
                                        <span class="rd-badge">สลับ →</span>
                                    </button>
                                </form>
                            @endif
                        @endforeach
                    @else
                        <div class="rd-item rd-active" style="opacity:.5;">ไม่มีบทบาทอื่น</div>
                    @endif
                </div>
                {{-- end .role-drop --}}
            </div>
            {{-- end .role-sw --}}
        </div>
    </div>

    <!-- Navigation Menus -->
    <div
        class="sb-nav"
        data-sidebar-scroll
        data-testid="sidebar-scroll"
        x-data="{
            storageKey: 'tpss.sidebar.scrollTop.{{ $activeRole ?: 'default' }}',
            restoreSidebarScroll() {
                try {
                    const saved = window.localStorage.getItem(this.storageKey);
                    const scrollTop = Number.parseInt(saved, 10);

                    if (Number.isFinite(scrollTop) && scrollTop >= 0) {
                        this.$el.scrollTop = scrollTop;
                    }
                } catch (error) {
                    // localStorage can be unavailable in private or restricted browser modes.
                }

                this.$el.style.visibility = 'visible';
            },
            saveSidebarScroll() {
                try {
                    window.localStorage.setItem(this.storageKey, String(this.$el.scrollTop || 0));
                } catch (error) {
                    // Keep navigation working even if storage is blocked.
                }
            }
        }"
        x-init="restoreSidebarScroll()"
        @scroll.debounce.100ms="saveSidebarScroll()"
        @click.capture="saveSidebarScroll()"
        style="visibility:hidden;"
    >

        @if($activeRole === 'staff')
            <div class="sb-sec">เมนูหลัก</div>
            <!-- Staff Menus -->
            <span class="nv nv-disabled" role="link" aria-disabled="true" title="ฟีเจอร์ภาพรวมเจ้าหน้าที่กำลังอยู่ในช่วงพัฒนา">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                </svg>
                <span class="nv-label">ภาพรวม</span>
                <span class="nv-dev-badge">กำลังพัฒนา</span>
            </span>
            <a href="{{ route('staff.settings') }}" class="nv {{ Request::routeIs('staff.settings') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span class="nv-label">ตั้งค่าปีการศึกษา</span>
            </a>
            <a href="{{ route('staff.master_data') }}" class="nv {{ str_contains($currentPath, 'master-data') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="3" y1="9" x2="21" y2="9"></line>
                    <line x1="9" y1="21" x2="9" y2="9"></line>
                </svg>
                <span class="nv-label">จัดการข้อมูลหลัก</span>
            </a>
            @if(!empty($canHelpSchedule))
            {{-- V2 delegation: เจ้าหน้าที่ที่ admin มอบหมายดูแลวิชา → ช่วยจัดตาราง --}}
            <a href="{{ route('maker.schedules.index') }}" class="nv {{ Request::routeIs('maker.schedules.*') || Request::routeIs('maker.course_offerings.schedules.*') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                    <path d="M9 16l2 2 4-4"></path>
                </svg>
                <span class="nv-label">ช่วยจัดตาราง</span>
            </a>
            @endif
            <span class="nv nv-disabled" role="link" aria-disabled="true" title="ฟีเจอร์รายงานของเจ้าหน้าที่กำลังอยู่ในช่วงพัฒนา">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span class="nv-label">รายงาน</span>
                <span class="nv-dev-badge">กำลังพัฒนา</span>
            </span>

        @elseif($activeRole === 'course_head')
            @php
                $makerConflictCount = $sidebarBadges['maker_conflict_count'] ?? 0;
                $makerConflictStatus = $sidebarBadges['maker_conflict_status'] ?? 'ready';
                $makerConflictPending = (bool) ($sidebarBadges['maker_conflict_pending'] ?? false);
                $makerConflictLabel = $sidebarBadges['maker_conflict_label'] ?? null;
                $makerConflictNumericCount = is_numeric($makerConflictCount) ? (int) $makerConflictCount : null;
                $makerConflictPoll = in_array($makerConflictStatus, ['missing', 'pending', 'processing'], true);
                $makerConflictFailed = $makerConflictStatus === 'failed';
                $makerConflictVisible = ($makerConflictNumericCount !== null && $makerConflictNumericCount > 0) || $makerConflictFailed;
                $makerConflictTone = ($makerConflictNumericCount !== null && $makerConflictNumericCount > 0) || $makerConflictStatus === 'failed'
                    ? 'nv-bd-red'
                    : 'nv-bd-warning';
                $makerConflictText = $makerConflictVisible
                    ? (($makerConflictNumericCount !== null && $makerConflictNumericCount > 0)
                        ? (string) $makerConflictNumericCount
                        : ($makerConflictLabel ?: 'ตรวจสอบไม่สำเร็จ'))
                    : '';
                $makerConflictTitle = $makerConflictVisible
                    ? (($makerConflictNumericCount !== null && $makerConflictNumericCount > 0)
                        ? "{$makerConflictNumericCount} รายการชน"
                        : 'ตรวจสอบรายการชนไม่สำเร็จ')
                    : '';
            @endphp
            <div class="sb-sec">เมนูหลัก</div>
            <!-- Maker Menus -->
            <span class="nv nv-disabled" role="link" aria-disabled="true" title="ฟีเจอร์ภาพรวมและแจ้งเตือนของหัวหน้าวิชากำลังอยู่ในช่วงพัฒนา">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span class="nv-label">ภาพรวมและแจ้งเตือน</span>
                <span class="nv-dev-badge">กำลังพัฒนา</span>
            </span>
            <a href="{{ route('maker.schedules.index') }}" class="nv {{ Request::routeIs('maker.schedules.*') || Request::routeIs('maker.course_offerings.schedules.*') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span class="nv-label">ตารางสอน</span>
            </a>
            <a href="{{ route('maker.alerts.index') }}" class="nv {{ Request::routeIs('maker.alerts.*') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <span class="nv-label">แจ้งเตือน</span>
                <span
                    class="nv-alert-badges"
                    data-conflict-badge
                    data-endpoint="{{ route('maker.conflict_badge_status') }}"
                    data-status="{{ $makerConflictStatus }}"
                    data-pending="{{ $makerConflictPending ? 'true' : 'false' }}"
                    data-poll="{{ $makerConflictPoll ? 'true' : 'false' }}"
                    @if(! $makerConflictVisible) hidden @endif
                >
                    <span
                        class="nv-bd {{ $makerConflictTone }}"
                        data-conflict-badge-pill
                        title="{{ $makerConflictTitle }}"
                    >{{ $makerConflictText }}</span>
                </span>
            </a>
            <a href="{{ route('maker.course_offerings.index') }}" class="nv {{ Request::routeIs('maker.course_offerings.*') && ! Request::routeIs('maker.course_offerings.schedules.*') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
                <span class="nv-label">จัดการรายวิชา</span>
            </a>
            <span class="nv nv-disabled" role="link" aria-disabled="true" title="ฟีเจอร์ประวัติส่งอนุมัติกำลังอยู่ในช่วงพัฒนา">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span class="nv-label">ประวัติส่งอนุมัติ</span>
                <span class="nv-dev-badge">กำลังพัฒนา</span>
            </span>

        @elseif($activeRole === 'instructor')
            <div class="sb-sec">เมนูหลัก</div>
            <!-- Lecturer Menus -->
            <span class="nv nv-disabled" role="link" aria-disabled="true" title="ฟีเจอร์ภาพรวมอาจารย์กำลังอยู่ในช่วงพัฒนา">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span class="nv-label">ตารางสอนของฉัน</span>
                <span class="nv-dev-badge">กำลังพัฒนา</span>
            </span>
            @if(!empty($canHelpSchedule))
            {{-- V2 delegation: เข้าหน้าจัดตารางในฐานะอาจารย์ที่หัวหน้าวิชามอบหมาย --}}
            <a href="{{ route('maker.schedules.index') }}" class="nv {{ Request::routeIs('maker.schedules.*') || Request::routeIs('maker.course_offerings.schedules.*') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                    <path d="M9 16l2 2 4-4"></path>
                </svg>
                <span class="nv-label">ช่วยจัดตาราง</span>
            </a>
            @endif
            <a href="{{ route('lecturer.dashboard') }}" class="nv {{ Request::routeIs('lecturer.dashboard') || Request::routeIs('lecturer.pa.*') ? 'on' : '' }}" data-testid="sidebar-instructor-workload">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span class="nv-label">ภาระงานสอน</span>
            </a>
            <span class="nv nv-disabled" role="link" aria-disabled="true" title="ฟีเจอร์วิชาที่รับผิดชอบกำลังอยู่ในช่วงพัฒนา">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
                <span class="nv-label">วิชาที่รับผิดชอบ</span>
                <span class="nv-dev-badge">กำลังพัฒนา</span>
            </span>

        @elseif($activeRole === 'executive')
            @php
                $approverOffering = request()->route('courseOffering');
                $approverOfferingStatus = is_object($approverOffering) ? ($approverOffering->approval_status ?? null) : null;
                $approverRejectedActive = Request::routeIs('approver.offerings.rejected')
                    || (Request::routeIs('approver.offerings.show') && $approverOfferingStatus === 'rejected');
                $approverQueueActive = Request::routeIs('approver.dashboard')
                    || (Request::routeIs('approver.offerings.show') && ! $approverRejectedActive);
            @endphp
            <div class="sb-sec">เมนูหลัก</div>
            <!-- Approver Menus -->
            <a href="{{ route('approver.dashboard') }}" class="nv {{ $approverQueueActive ? 'on' : '' }}" data-testid="nav-approver-queue">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span class="nv-label">รออนุมัติ</span>
            </a>
            <span class="nv nv-disabled" role="link" aria-disabled="true" title="ฟีเจอร์ตารางทั้งหมดสำหรับผู้บริหารกำลังอยู่ในช่วงพัฒนา">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span class="nv-label">ตารางทั้งหมด</span>
                <span class="nv-dev-badge">กำลังพัฒนา</span>
            </span>
            <a href="{{ route('approver.offerings.rejected') }}" class="nv {{ $approverRejectedActive ? 'on' : '' }}" data-testid="nav-approver-rejected">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span class="nv-label">ตีกลับ / แก้ไข</span>
            </a>
            <span class="nv nv-disabled" role="link" aria-disabled="true" title="ฟีเจอร์รายงานภาพรวมสำหรับผู้บริหารกำลังอยู่ในช่วงพัฒนา">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                <span class="nv-label">รายงานภาพรวม</span>
                <span class="nv-dev-badge">กำลังพัฒนา</span>
            </span>

        @elseif($activeRole === 'admin')
            @php
                $alertSummary = $sidebarBadges['admin_alert_summary'] ?? ['critical' => 0, 'warnings' => 0];
                $alertCritical = $alertSummary['critical'];
                $alertWarnings = $alertSummary['warnings'];
                $alertDeviations = $alertSummary['deviations'] ?? 0;
            @endphp
            {{-- 1. ภาพรวม --}}
            <div class="sb-sec">ภาพรวม</div>
            <a href="{{ route('admin.dashboard') }}" class="nv {{ Request::routeIs('admin.dashboard') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span class="nv-label">ภาพรวมระบบ</span>
            </a>
            <a href="{{ route('admin.alerts') }}" class="nv {{ Request::routeIs('admin.alerts') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <span class="nv-label">แจ้งเตือน</span>
                @if($alertCritical > 0 || $alertWarnings > 0 || $alertDeviations > 0)
                    <span class="nv-alert-badges">
                        @if($alertCritical > 0)
                            {{-- CRITICAL: circle-info icon (sync กับหน้า admin.alerts) --}}
                            <span class="nv-bd nv-bd-red" title="{{ $alertCritical }} รายการวิกฤต">
                                <svg class="nv-bd-ic" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="8" x2="12" y2="12"/>
                                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                                </svg>
                                {{ $alertCritical }}
                            </span>
                        @endif
                        @if($alertWarnings > 0)
                            {{-- WARNING: triangle icon (sync กับหน้า admin.alerts) --}}
                            <span class="nv-bd nv-bd-warning" title="{{ $alertWarnings }} รายการเตือน">
                                <svg class="nv-bd-ic" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                {{ $alertWarnings }}
                            </span>
                        @endif
                        @if($alertDeviations > 0)
                            {{-- SOFT: รายวิชาที่ผู้สอนต่างจากแม่แบบ (โทนนุ่มกว่า warning) --}}
                            <span class="nv-bd nv-bd-soft" title="{{ $alertDeviations }} วิชาที่ผู้สอนต่างจากแม่แบบ">
                                <svg class="nv-bd-ic" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                {{ $alertDeviations }}
                            </span>
                        @endif
                    </span>
                @endif
            </a>

            {{-- 2. ข้อมูลพื้นฐาน --}}
            <div class="sb-sec" style="margin-top: 15px;">ข้อมูลพื้นฐาน</div>
            <a href="{{ route('admin.users') }}" class="nv {{ Request::routeIs('admin.users') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span class="nv-label">จัดการผู้ใช้งาน</span>
            </a>
            <a href="{{ route('admin.master_data') }}" class="nv {{ Request::routeIs('admin.master_data') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 22h14a2 2 0 002-2V7.5L14.5 2H6a2 2 0 00-2 2v4"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <path d="M2 15h10"></path>
                    <path d="M2 18h10"></path>
                    <path d="M2 12h10"></path>
                </svg>
                <span class="nv-label">ข้อมูลหลัก</span>
            </a>

            {{-- 3. ตารางและรายงาน (admin = read-only, ไม่มีสิทธิแก้ไขตารางสอน) --}}
            <div class="sb-sec" style="margin-top: 15px;">ตารางและรายงาน</div>
            <span class="nv nv-disabled" role="link" aria-disabled="true" title="ฟีเจอร์ตารางสอนที่เผยแพร่แล้วกำลังอยู่ในช่วงพัฒนา">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span class="nv-label">ตารางสอนที่เผยแพร่</span>
                <span class="nv-dev-badge">กำลังพัฒนา</span>
            </span>
            <a href="{{ route('admin.reports.workload') }}"
               class="nv {{ Request::routeIs('admin.reports.workload') ? 'on' : '' }}"
               data-testid="sidebar-workload-report">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                <span class="nv-label">รายงานภาระงาน</span>
            </a>

            {{-- 5. ระบบ --}}
            <div class="sb-sec" style="margin-top: 15px;">ระบบ</div>
            <a href="{{ route('admin.settings') }}" class="nv {{ Request::routeIs('admin.settings') ? 'on' : '' }}">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
                <span class="nv-label">ตั้งค่าระบบ</span>
            </a>
            <a href="{{ route('admin.audit_logs.index') }}"
               class="nv {{ Request::routeIs('admin.audit_logs.*') ? 'on' : '' }}"
               data-testid="sidebar-audit-logs">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span class="nv-label">บันทึกกิจกรรม</span>
            </a>
        @endif

    </div>

    <!-- Sidebar Footer -->
    <div class="sb-foot">

        <button type="button" class="nv" @click="$dispatch('open-profile-modal')">
            <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span class="nv-label">ตั้งค่าบัญชี</span>
        </button>
        <span class="nv nv-disabled" role="link" aria-disabled="true" title="คู่มือการใช้งานกำลังอยู่ในช่วงพัฒนา">
            <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            <span class="nv-label">คู่มือการใช้งาน</span>
            <span class="nv-dev-badge">กำลังพัฒนา</span>
        </span>
        <form method="POST" action="{{ route('logout') }}" style="margin:0">
            @csrf
            <button type="submit" class="nv" data-testid="sidebar-logout">
                <svg class="nv-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                <span class="nv-label">ออกจากระบบ</span>
            </button>
        </form>
    </div>
</div>

@if($activeRole === 'course_head')
    <script>
        (() => {
            const POLL_INTERVAL_MS = 8000;
            const text = {
                failed: @json('ตรวจสอบไม่สำเร็จ'),
                failedTitle: @json('ตรวจสอบรายการชนไม่สำเร็จ'),
                conflictSuffix: @json(' รายการชน'),
            };

            function initConflictBadge(root) {
                const pill = root.querySelector('[data-conflict-badge-pill]');
                const endpoint = root.dataset.endpoint;

                if (! pill || ! endpoint) {
                    return;
                }

                let polling = root.dataset.poll === 'true';
                const silent = root.dataset.silent === 'true';
                let timer = null;
                let inflight = false;

                function stop() {
                    if (timer) {
                        window.clearInterval(timer);
                        timer = null;
                    }
                }

                function applyBadge(data) {
                    const status = String(data.status || 'missing');
                    const rawCount = data.count === null || data.count === undefined ? null : Number(data.count);
                    const hasCount = Number.isFinite(rawCount) && rawCount > 0;
                    const pending = Boolean(data.pending);
                    const label = data.label || null;

                    polling = Boolean(data.poll);
                    root.dataset.status = status;
                    root.dataset.pending = pending ? 'true' : 'false';
                    root.dataset.poll = polling ? 'true' : 'false';

                    let visible = false;
                    let tone = 'warning';
                    let badgeText = '';
                    let title = '';

                    if (status === 'ready') {
                        if (hasCount) {
                            visible = true;
                            tone = 'red';
                            badgeText = String(rawCount);
                            title = `${rawCount}${text.conflictSuffix}`;
                        }
                    } else if (status === 'failed') {
                        visible = true;
                        tone = 'red';
                        badgeText = label || text.failed;
                        title = text.failedTitle;
                    } else if (pending || polling) {
                        visible = false;
                    }

                    const shouldShow = ! silent && visible;

                    pill.hidden = ! shouldShow;
                    pill.textContent = shouldShow ? badgeText : '';
                    pill.title = shouldShow ? title : '';
                    pill.classList.toggle('nv-bd-red', shouldShow && tone === 'red');
                    pill.classList.toggle('nv-bd-warning', shouldShow && tone !== 'red');
                    root.hidden = ! shouldShow;

                    if (! polling) {
                        stop();
                    }
                }

                async function pollOnce() {
                    if (inflight || ! polling || document.hidden) {
                        return;
                    }

                    inflight = true;

                    try {
                        const response = await window.fetch(endpoint, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });

                        if ([401, 403, 429].includes(response.status)) {
                            polling = false;
                            root.dataset.poll = 'false';
                            stop();
                            return;
                        }

                        if (! response.ok) {
                            return;
                        }

                        applyBadge(await response.json());
                    } catch (error) {
                        // Keep the sidebar quiet on transient network errors.
                    } finally {
                        inflight = false;
                    }
                }

                function start() {
                    if (timer || ! polling || document.hidden) {
                        return;
                    }

                    pollOnce();
                    timer = window.setInterval(pollOnce, POLL_INTERVAL_MS);
                }

                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        stop();
                        return;
                    }

                    start();
                });

                start();
            }

            document
                .querySelectorAll('[data-conflict-badge]')
                .forEach(initConflictBadge);
        })();
    </script>
@endif
