<x-app-layout title="ภาพรวม — ผู้ดูแลระบบ">
    <div class="admin-dashboard"
         x-data="{ dashboardReady: (window.performance?.getEntriesByType?.('navigation')?.[0]?.type === 'back_forward') }"
         x-init="if (!dashboardReady) setTimeout(() => dashboardReady = true, 220)">
        <div class="admin-dashboard-skeleton" x-show="!dashboardReady">
            <div class="dash-skel-card dash-skel-hero">
                <span></span><span></span><span></span>
            </div>
            <div class="dash-skel-grid">
                <div class="dash-skel-card"></div>
                <div class="dash-skel-card"></div>
            </div>
            <div class="dash-skel-strip">
                <span></span><span></span><span></span><span></span>
            </div>
            <div class="dash-skel-grid">
                <div class="dash-skel-card"></div>
                <div class="dash-skel-card"></div>
            </div>
        </div>

        <div class="admin-dashboard-content" x-show="dashboardReady" x-cloak>

        {{-- HERO: title + system status + quick action --}}
        @include('shared.dashboard.admin_hero')

        <section class="admin-section">
            <div class="admin-action-grid">
                @include('shared.dashboard.master_data_alerts')
                @include('shared.dashboard.offering_pipeline')
            </div>
        </section>

        <section class="admin-section">
            @include('shared.dashboard.admin_stats_strip')
        </section>

        {{-- Visual overview (V3 8.1 — กราฟสรุปสถานะ) --}}
        @include('shared.dashboard.admin_visual_overview')

        <section class="admin-section">
            @include('shared.dashboard.instructors_workload', [
                'workloadPageSize' => 5,
                'workloadReportUrl' => route('admin.reports.workload'),
            ])
        </section>

        <section class="admin-section">
            <div class="admin-secondary-grid">
                @include('shared.dashboard.recent-activity')
                @include('shared.dashboard.upcoming-schedules')
            </div>
        </section>
        </div>
    </div>

    <script>
        (() => {
            const scrollKey = 'adminDashboardScrollY';
            const pendingKey = 'adminDashboardRestorePending';
            const dashboard = () => document.querySelector('.admin-dashboard');

            const resetDashboardScroll = () => {
                if (!dashboard()) return;
                sessionStorage.removeItem(pendingKey);
                sessionStorage.removeItem(scrollKey);

                requestAnimationFrame(() => {
                    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
                });
            };

            window.addEventListener('pageshow', resetDashboardScroll);
            window.addEventListener('load', () => setTimeout(resetDashboardScroll, 0));
            document.addEventListener('alpine:initialized', () => setTimeout(resetDashboardScroll, 0));
        })();
    </script>

    <style>
        .admin-dashboard {
            width: 100%;
            max-width: 100%;
            padding: clamp(14px, 2vw, 28px) clamp(14px, 2vw, 28px) clamp(22px, 2.4vw, 32px);
            min-width: 0;
            overflow-x: hidden;
            background:
                radial-gradient(circle at 8% 0%, color-mix(in oklch, var(--brand-navy) 10%, transparent), transparent 30%),
                linear-gradient(180deg,
                    color-mix(in oklch, var(--brand-navy) 7%, var(--bg)) 0%,
                    color-mix(in oklch, var(--brand-navy) 4%, var(--bg)) 34%,
                    var(--bg) 100%);
        }

        .admin-dashboard-content,
        .admin-dashboard-skeleton {
            display: flex;
            flex-direction: column;
            gap: clamp(18px, 1.8vw, 24px);
            min-width: 0;
        }

        .admin-dashboard-skeleton {
            pointer-events: none;
        }

        .dash-skel-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: clamp(12px, 1.4vw, 18px);
        }

        .dash-skel-card,
        .dash-skel-strip {
            border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
            border-radius: var(--r-lg);
            background:
                linear-gradient(90deg,
                    color-mix(in oklch, var(--brand-navy) 8%, var(--surface)) 0%,
                    color-mix(in oklch, var(--brand-navy) 14%, var(--surface)) 42%,
                    color-mix(in oklch, var(--brand-navy) 8%, var(--surface)) 82%);
            background-size: 220% 100%;
            animation: dashboardSkeleton 1150ms ease-in-out infinite;
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.08),
                0 16px 34px -24px rgba(0, 36, 84, 0.34);
        }

        .dash-skel-card {
            min-height: 180px;
        }

        .dash-skel-hero {
            min-height: 260px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .dash-skel-hero span {
            display: block;
            height: 18px;
            max-width: 460px;
            border-radius: 999px;
            background: color-mix(in oklch, var(--brand-navy) 17%, var(--surface));
        }

        .dash-skel-hero span:first-child {
            width: 32%;
            height: 28px;
        }

        .dash-skel-hero span:nth-child(2) {
            width: 58%;
        }

        .dash-skel-hero span:nth-child(3) {
            width: 78%;
            margin-top: auto;
        }

        .dash-skel-strip {
            min-height: 162px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            overflow: hidden;
        }

        .dash-skel-strip span {
            background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface));
            border-right: 1px solid color-mix(in oklch, var(--brand-navy) 18%, var(--border));
        }

        .dash-skel-strip span:last-child {
            border-right: 0;
        }

        @keyframes dashboardSkeleton {
            0% { background-position: 120% 0; }
            100% { background-position: -120% 0; }
        }

        .admin-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-width: 0;
        }

        .admin-action-grid,
        .admin-secondary-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: clamp(12px, 1.4vw, 18px);
            align-items: stretch;
            min-width: 0;
        }

        /* action-grid: การ์ดซ้าย/ขวาสูงเท่ากัน และให้ body เติมพื้นที่ใต้หัวการ์ด */
        .admin-action-grid > .card {
            display: flex;
            flex-direction: column;
        }
        .admin-action-grid > .card > .card-hdr {
            flex: 0 0 auto;
            min-height: 84px;
            border-bottom: 1px solid var(--border);
        }

        .admin-action-grid > .card > :not(.card-hdr) {
            flex: 1 1 auto;
        }

        /* ---- Depth / elevation — ให้การ์ดมีมิติ ไม่แบน (ตาม feedback) ---- */
        .admin-dashboard .card,
        .admin-dashboard .dash-chart-card,
        .admin-dashboard .admin-stats-strip {
            border-color: color-mix(in oklch, var(--brand-navy) 22%, var(--border));
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.08),
                0 12px 28px -16px rgba(0, 36, 84, 0.26);
            transition:
                border-color 180ms ease,
                box-shadow 180ms ease,
                transform 180ms ease,
                background 180ms ease;
        }

        .admin-dashboard .card:hover,
        .admin-dashboard .card:focus-within,
        .admin-dashboard .dash-chart-card:hover,
        .admin-dashboard .dash-chart-card:focus-within,
        .admin-dashboard .admin-stats-strip:hover,
        .admin-dashboard .admin-stats-strip:focus-within {
            border-color: color-mix(in oklch, var(--brand-navy) 34%, var(--border));
            box-shadow:
                0 2px 4px rgba(0, 36, 84, 0.1),
                0 18px 34px -18px rgba(0, 36, 84, 0.34);
            transform: translateY(-1px);
        }

        .admin-secondary-grid > .card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .admin-secondary-grid > .card > .card-hdr {
            flex: 0 0 70px;
            min-height: 70px;
            box-sizing: border-box;
        }

        .admin-secondary-grid > .card > :not(.card-hdr) {
            flex: 1 1 auto;
        }

        .admin-dashboard .card {
            margin-bottom: 0;
            min-width: 0;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 3%, var(--surface)), var(--surface) 38%),
                var(--surface);
        }

        .admin-dashboard .card-hdr {
            min-height: 54px;
            flex-wrap: wrap;
            gap: 10px 14px;
            min-width: 0;
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 18%, var(--border));
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 8%, var(--surface)), color-mix(in oklch, var(--brand-navy) 3%, var(--surface)));
            transition:
                background 180ms ease,
                border-color 180ms ease;
        }

        .admin-dashboard .card:hover .card-hdr,
        .admin-dashboard .card:focus-within .card-hdr {
            border-bottom-color: color-mix(in oklch, var(--brand-navy) 26%, var(--border));
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 12%, var(--surface)), color-mix(in oklch, var(--brand-navy) 5%, var(--surface)));
        }

        .admin-dashboard .card-ttl {
            min-width: 0;
            overflow-wrap: anywhere;
            color: color-mix(in oklch, var(--brand-navy) 84%, var(--fg-1));
        }

        .admin-dashboard .card-actions {
            min-width: 0;
            flex-wrap: wrap;
        }

        .admin-dashboard .table-responsive {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
        }

        .admin-dashboard .pill {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 4px 9px;
            border-radius: var(--r-pill);
            border: 1px solid color-mix(in oklch, var(--brand-navy) 16%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 6%, var(--surface));
            color: var(--fg-2);
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            text-align: center;
            white-space: normal;
        }

        .admin-dashboard .pill.p-conflict {
            border-color: var(--status-conflict-border);
            background: var(--status-conflict-bg);
            color: var(--status-conflict-fg);
        }

        .admin-dashboard .pill.p-warning {
            border-color: var(--status-warning-border);
            background: var(--status-warning-bg);
            color: var(--status-warning-fg);
        }

        .admin-dashboard .pill.p-success {
            border-color: var(--status-success-border);
            background: var(--status-success-bg);
            color: var(--status-success-fg);
        }

        .admin-dashboard .pill.p-info {
            border-color: var(--status-info-border);
            background: var(--status-info-bg);
            color: var(--status-info-fg);
        }

        .admin-dashboard .pill.badge-gray {
            border-color: color-mix(in oklch, var(--brand-navy) 16%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 6%, var(--surface));
            color: var(--fg-2);
        }

        /* ---- Pill variants for dashboard widgets (mirror .audit-page definitions) ---- */
        .admin-dashboard .pill.p-primary {
            border-color: color-mix(in oklch, var(--brand-navy) 25%, var(--border));
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            color: var(--brand-navy);
        }

        .admin-dashboard .pill.p-neutral {
            border-color: var(--border);
            background: var(--bg-2);
            color: var(--fg-2);
        }

        .admin-dashboard .pill.p-gold {
            border-color: var(--status-warning-border);
            background: var(--status-warning-bg);
            color: var(--status-warning-fg);
        }

        .admin-dashboard .pill.p-teal {
            border-color: var(--status-info-border);
            background: var(--status-info-bg);
            color: var(--status-info-fg);
        }

        .admin-dashboard .pill.p-purple {
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            border-color: color-mix(in oklch, var(--brand-navy) 22%, var(--border));
            color: var(--brand-navy);
        }

        /* ดูทั้งหมด — สีดำ/เทา แทนสี brand */
        .admin-dashboard .btn {
            border: 1px solid color-mix(in oklch, var(--brand-navy) 28%, var(--border));
            box-shadow: 0 1px 2px rgba(0, 36, 84, 0.08);
            transition:
                background 160ms ease,
                border-color 160ms ease,
                color 160ms ease,
                box-shadow 160ms ease,
                transform 160ms ease;
        }

        .admin-dashboard .btn:hover,
        .admin-dashboard .btn:focus-visible {
            transform: translateY(-1px);
            outline: none;
        }

        .admin-dashboard .btn-primary {
            border-color: color-mix(in oklch, var(--brand-navy) 88%, var(--border));
            background:
                linear-gradient(180deg,
                    color-mix(in oklch, var(--brand-navy) 92%, var(--surface)),
                    var(--brand-navy));
            color: var(--fg-on-brand);
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.16),
                0 10px 20px -16px rgba(0, 36, 84, 0.64);
        }

        .admin-dashboard .btn-primary:hover,
        .admin-dashboard .btn-primary:focus-visible {
            border-color: var(--brand-navy);
            background:
                linear-gradient(180deg,
                    color-mix(in oklch, var(--brand-navy-700) 86%, var(--surface)),
                    var(--brand-navy-700));
            box-shadow:
                0 2px 4px rgba(0, 36, 84, 0.18),
                0 14px 24px -16px rgba(0, 36, 84, 0.7);
        }

        .admin-dashboard .ra-view-all {
            color: var(--fg-on-brand);
            background: var(--brand-navy);
            border-color: var(--brand-navy);
            box-shadow:
                0 1px 2px rgba(0, 36, 84, 0.16),
                0 10px 18px -16px rgba(0, 36, 84, 0.58);
        }

        .admin-dashboard .ra-view-all:hover,
        .admin-dashboard .ra-view-all:focus-visible {
            border-color: var(--brand-navy-700);
            background: var(--brand-navy-700);
            color: var(--fg-on-brand);
            box-shadow:
                0 2px 4px rgba(0, 36, 84, 0.16),
                0 12px 22px -16px rgba(0, 36, 84, 0.58);
        }

        .admin-dashboard [data-testid="admin-stats-strip"] {
            margin-bottom: 0 !important;
        }

        .admin-dashboard {
            --dash-accent-blue: oklch(43% 0.118 255);
            --dash-accent-gold: oklch(61% 0.112 82);
            --dash-accent-teal: oklch(52% 0.095 184);
            --dash-accent-indigo: oklch(45% 0.105 282);
        }

        .admin-action-grid > .card:nth-child(1),
        .admin-secondary-grid > .card:nth-child(1) {
            --dash-card-accent: var(--dash-accent-gold);
        }

        .admin-action-grid > .card:nth-child(2),
        .admin-secondary-grid > .card:nth-child(2) {
            --dash-card-accent: var(--dash-accent-teal);
        }

        .admin-dashboard .dash-chart-card:nth-child(1) {
            --dash-card-accent: var(--dash-accent-indigo);
        }

        .admin-dashboard .dash-chart-card:nth-child(2) {
            --dash-card-accent: var(--dash-accent-blue);
        }

        .admin-dashboard .admin-action-grid > .card.admin-alert-card,
        .admin-dashboard .admin-action-grid > .card[data-testid="offering-pipeline"],
        .admin-dashboard .admin-secondary-grid > .card[data-testid="recent-activity-widget"] {
            --dash-card-accent: var(--brand-navy) !important;
        }

        .admin-dashboard .card,
        .admin-dashboard .dash-chart-card {
            border-color: color-mix(in oklch, var(--dash-card-accent, var(--brand-navy)) 22%, var(--border));
            background:
                radial-gradient(circle at 12% 0%, color-mix(in oklch, var(--dash-card-accent, var(--brand-navy)) 9%, transparent), transparent 34%),
                linear-gradient(180deg, color-mix(in oklch, var(--dash-card-accent, var(--brand-navy)) 4.5%, var(--surface)), var(--surface) 44%),
                var(--surface);
        }

        .admin-dashboard .card-hdr {
            border-bottom-color: color-mix(in oklch, var(--dash-card-accent, var(--brand-navy)) 20%, var(--border));
            background:
                linear-gradient(180deg,
                    color-mix(in oklch, var(--dash-card-accent, var(--brand-navy)) 10%, var(--surface)),
                    color-mix(in oklch, var(--brand-navy) 3%, var(--surface)));
        }

        .admin-dashboard .card:hover,
        .admin-dashboard .card:focus-within,
        .admin-dashboard .dash-chart-card:hover,
        .admin-dashboard .dash-chart-card:focus-within {
            border-color: color-mix(in oklch, var(--dash-card-accent, var(--brand-navy)) 36%, var(--border));
            background:
                radial-gradient(circle at 12% 0%, color-mix(in oklch, var(--dash-card-accent, var(--brand-navy)) 12%, transparent), transparent 34%),
                linear-gradient(180deg, color-mix(in oklch, var(--dash-card-accent, var(--brand-navy)) 7%, var(--surface)), var(--surface) 44%),
                var(--surface);
        }

        .admin-dashboard .card:hover .card-hdr,
        .admin-dashboard .card:focus-within .card-hdr {
            border-bottom-color: color-mix(in oklch, var(--dash-card-accent, var(--brand-navy)) 30%, var(--border));
            background:
                linear-gradient(180deg,
                    color-mix(in oklch, var(--dash-card-accent, var(--brand-navy)) 14%, var(--surface)),
                    color-mix(in oklch, var(--brand-navy) 5%, var(--surface)));
        }

        .admin-dashboard .admin-stats-cell:nth-child(1) {
            --dash-stat-accent: var(--dash-accent-blue);
        }

        .admin-dashboard .admin-stats-cell:nth-child(2) {
            --dash-stat-accent: var(--dash-accent-teal);
        }

        .admin-dashboard .admin-stats-cell:nth-child(3) {
            --dash-stat-accent: var(--dash-accent-gold);
        }

        .admin-dashboard .admin-stats-cell:nth-child(4) {
            --dash-stat-accent: var(--dash-accent-indigo);
        }

        .admin-dashboard .admin-stats-cell {
            border-color: color-mix(in oklch, var(--dash-stat-accent, var(--brand-navy)) 18%, var(--border));
            background:
                radial-gradient(circle at 10% 0%, color-mix(in oklch, var(--dash-stat-accent, var(--brand-navy)) 8%, transparent), transparent 32%),
                linear-gradient(180deg, color-mix(in oklch, var(--dash-stat-accent, var(--brand-navy)) 5%, var(--surface)), var(--surface) 66%);
        }

        .admin-dashboard .admin-stats-cell::before,
        .admin-dashboard .admin-stats-bar-fill {
            background:
                linear-gradient(180deg,
                    color-mix(in oklch, var(--dash-stat-accent, var(--brand-navy)) 82%, var(--surface)),
                    color-mix(in oklch, var(--dash-stat-accent, var(--brand-navy)) 86%, var(--brand-navy)));
        }

        .admin-dashboard .admin-stats-cell:hover,
        .admin-dashboard .admin-stats-cell:focus-visible {
            background:
                radial-gradient(circle at 10% 0%, color-mix(in oklch, var(--dash-stat-accent, var(--brand-navy)) 12%, transparent), transparent 32%),
                linear-gradient(180deg, color-mix(in oklch, var(--dash-stat-accent, var(--brand-navy)) 8%, var(--surface)), var(--surface) 66%);
        }

        .admin-dashboard .pill:not([style*="background"]):not([style*="--"]) {
            background: color-mix(in oklch, var(--brand-navy) 6%, var(--surface));
            border-color: color-mix(in oklch, var(--brand-navy) 16%, var(--border));
            color: var(--fg-2);
        }

        .admin-dashboard .pill.p-conflict:not([style*="background"]):not([style*="--"]) {
            background: var(--status-conflict-bg);
            border-color: var(--status-conflict-border);
            color: var(--status-conflict-fg);
        }

        .admin-dashboard .pill.p-warning:not([style*="background"]):not([style*="--"]) {
            background: color-mix(in oklch, var(--dash-accent-gold) 12%, var(--surface));
            border-color: color-mix(in oklch, var(--dash-accent-gold) 36%, var(--border));
            color: color-mix(in oklch, var(--dash-accent-gold) 82%, var(--brand-navy));
        }

        .admin-dashboard .pill.p-success:not([style*="background"]):not([style*="--"]) {
            background: var(--status-success-bg);
            border-color: var(--status-success-border);
            color: var(--status-success-fg);
        }

        .admin-dashboard .pill.p-info:not([style*="background"]):not([style*="--"]) {
            background: color-mix(in oklch, var(--dash-accent-teal) 11%, var(--surface));
            border-color: color-mix(in oklch, var(--dash-accent-teal) 30%, var(--border));
            color: color-mix(in oklch, var(--dash-accent-teal) 78%, var(--brand-navy));
        }

        @media (max-width: 1280px) {
            .admin-dashboard {
                padding-inline: 18px;
            }

        }

        @media (max-width: 1100px) {
            .dash-skel-grid {
                grid-template-columns: 1fr;
            }

            .admin-action-grid,
            .admin-secondary-grid {
                grid-template-columns: 1fr;
            }

            .admin-secondary-grid > .card {
                height: auto;
            }

            .admin-secondary-grid > .card > .card-hdr {
                flex-basis: auto;
                min-height: 54px;
            }
        }

        @media (max-width: 900px) {
            .admin-dashboard {
                padding-inline: 16px;
            }

        }

        @media (max-width: 720px) {
            .admin-dashboard {
                padding: 16px;
                gap: 22px;
            }

            .admin-dashboard-content,
            .admin-dashboard-skeleton {
                gap: 22px;
            }

            .dash-skel-hero {
                min-height: 220px;
                padding: 18px;
            }

            .dash-skel-hero span:first-child,
            .dash-skel-hero span:nth-child(2),
            .dash-skel-hero span:nth-child(3) {
                width: 100%;
            }

            .dash-skel-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dash-skel-strip span:nth-child(2) {
                border-right: 0;
            }

            .admin-dashboard .card-hdr {
                align-items: flex-start;
            }

            .admin-dashboard .card-actions,
            .admin-dashboard .search-box {
                width: 100%;
            }
        }

        @media (max-width: 540px) {
            .admin-dashboard {
                padding: 12px;
            }
        }
    </style>
</x-app-layout>
