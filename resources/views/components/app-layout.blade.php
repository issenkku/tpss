@props(['title' => 'TPSS'])

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TPSS — {{ $title }}</title>

    <!-- Google Fonts: IBM Plex Sans Thai (UI) + Kanit (Headings) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Kanit:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Design System CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- SweetAlert2 -->
    <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Choices.js for improved select dropdown placement -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <style>
        /* Make Choices look like our native form-control */
        .choices__inner {
            height: 44px !important;
            display: flex !important;
            align-items: center !important;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 34%, var(--border)) !important;
            border-radius: var(--r-md) !important;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--surface) 92%, white), color-mix(in oklch, var(--brand-navy) 5%, var(--surface))) !important;
            color: var(--brand-navy) !important;
            padding: 0 42px 0 12px !important;
            box-sizing: border-box !important;
            font: inherit !important;
            font-size: 14px !important;
            font-weight: 650 !important;
            box-shadow: 0 1px 2px rgba(0, 36, 84, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.72) !important;
            line-height: 1 !important;
            transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease !important;
        }
        .choices::after {
            content: "" !important;
            position: absolute !important;
            right: 13px !important;
            top: 50% !important;
            width: 18px !important;
            height: 18px !important;
            margin: 0 !important;
            border: 0 !important;
            transform: translateY(-50%) !important;
            pointer-events: none !important;
            background: center / 18px 18px no-repeat url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23002454' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") !important;
            transition: transform 160ms ease !important;
        }
        .choices.is-open::after {
            transform: translateY(-50%) rotate(180deg) !important;
        }
        .choices__list--dropdown {
            border: 1px solid color-mix(in oklch, var(--brand-navy) 38%, var(--border)) !important;
            box-shadow: 0 18px 42px -24px rgba(0, 36, 84, 0.46), 0 1px 2px rgba(0, 36, 84, 0.08) !important;
            border-radius: var(--r-md) !important;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface) 28%) !important;
            margin-top: 6px !important;
            max-height: 44vh !important;
            overflow: auto !important;
            padding: 5px !important;
        }
        .choices.tpss-choices-bottom .choices__list--dropdown,
        .choices.tpss-choices-bottom.is-flipped .choices__list--dropdown {
            top: calc(100% + 6px) !important;
            bottom: auto !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            transform-origin: top !important;
        }
        .choices.tpss-choices-bottom.is-flipped {
            overflow: visible !important;
        }
        .choices__list--dropdown .choices__item {
            padding: 11px 12px !important;
            border-radius: 7px !important;
            font-size: 14px !important;
            color: var(--fg-1) !important;
            font-weight: 600 !important;
        }
        .choices__list--dropdown .choices__item--highlighted {
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface)) !important;
            color: var(--brand-navy) !important;
        }
        .choices__list--dropdown .choices__item.is-selected,
        .choices__list--dropdown .choices__item[aria-selected="true"] {
            background: var(--brand-navy) !important;
            color: var(--fg-on-brand) !important;
        }
        .choices__inner:focus-within {
            outline: none !important;
            border-color: var(--brand-navy) !important;
            box-shadow: 0 0 0 3px color-mix(in oklch, var(--brand-navy) 16%, transparent), 0 10px 22px -18px rgba(0, 36, 84, 0.42) !important;
        }
        .choices__placeholder { color: var(--fg-3) !important; }
        .choices__list--single .choices__item { display: block !important; width: 100% !important; font-weight: 400 !important; color: var(--fg-1) !important; line-height: 1.4 !important; padding: 0 !important; text-align: left !important; }
        .choices__single-choice { display: block !important; text-align: left !important; font-weight: 400 !important; line-height: 1.4 !important; }
        .choices__item--choice { line-height: 1.4 !important; padding: 12px 14px !important; font-weight: 400 !important; }
        /* Hide the default Choices dropdown arrow and rely on our caret via background if needed */
        .choices[data-type*="select-one"] .choices__inner::after,
        .choices__inner .choices__button {
            display: none !important;
        }
        .tpss-native-select {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
        .tpss-select {
            position: relative;
            width: 100%;
        }
        .tpss-select-trigger {
            width: 100%;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 34%, var(--border));
            border-radius: var(--r-md);
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--surface) 92%, white), color-mix(in oklch, var(--brand-navy) 5%, var(--surface)));
            color: var(--brand-navy);
            padding: 9px 13px;
            font: inherit;
            font-size: 14px;
            font-weight: 650;
            text-align: left;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 36, 84, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.72);
            transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease, background 160ms ease;
        }
        .tpss-select-trigger:hover {
            border-color: color-mix(in oklch, var(--brand-navy) 50%, var(--border));
            box-shadow: 0 10px 24px -18px rgba(0, 36, 84, 0.38), inset 0 1px 0 rgba(255, 255, 255, 0.78);
        }
        .tpss-select-trigger:focus {
            outline: none;
            border-color: var(--brand-navy);
            box-shadow: 0 0 0 3px color-mix(in oklch, var(--brand-navy) 16%, transparent), 0 10px 22px -18px rgba(0, 36, 84, 0.42);
        }
        .tpss-select-trigger[disabled] {
            cursor: not-allowed;
            opacity: .65;
        }
        .tpss-select-label {
            min-width: 0;
            flex: 1 1 auto;
            text-align: left;
            line-height: 1.35;
            white-space: normal;
            word-break: break-word;
        }
        .tpss-select-caret {
            flex: 0 0 auto;
            width: 18px;
            height: 18px;
            border: 0;
            background: center / 18px 18px no-repeat url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23002454' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
        }
        .tpss-select-menu {
            z-index: 100000;
            border: 1px solid color-mix(in oklch, var(--brand-navy) 38%, var(--border));
            border-radius: var(--r-md);
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface) 28%);
            box-shadow: 0 18px 42px -24px rgba(0, 36, 84, 0.46), 0 1px 2px rgba(0, 36, 84, 0.08);
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 5px;
        }
        .tpss-select-option {
            width: 100%;
            display: block;
            border: 0;
            background: transparent;
            color: var(--fg-1);
            padding: 11px 12px;
            border-radius: 7px;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.4;
            text-align: left;
            cursor: pointer;
        }
        .tpss-select-option:hover,
        .tpss-select-option.is-selected {
            background: color-mix(in oklch, var(--brand-navy) 8%, var(--surface));
            color: var(--brand-navy);
        }
        .tpss-select-option.is-selected {
            background: var(--brand-navy);
            color: var(--fg-on-brand);
        }
        .tpss-select-option.is-placeholder {
            color: var(--fg-3);
        }
    </style>

    {{-- ปฏิทินเลือกวันที่ พ.ศ. ของ <x-thai-date-input> — ลงทะเบียนที่ layout เพื่อให้ทำงานแม้ component อยู่ใน <template> --}}
    <style>
        .tdi-wrap { width: 100%; position: relative; }
        .tdi-control {
            position: relative;
            width: 100%;
        }
        .tdi-input-cal { padding-right: 38px !important; }
        .tdi-cal-btn {
            position: absolute;
            top: 50%;
            right: 6px;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: var(--fg-3, #6b7280);
            cursor: pointer;
        }
        .tdi-cal-btn:hover { background: var(--bg-2, #f1f5f9); color: var(--brand-navy, #1e3a5f); }
        .tdi-cal-btn svg { width: 18px; height: 18px; }
        .tdi-pop {
            position: absolute;
            z-index: 10000;
            top: calc(100% + 8px);
            left: 0;
            width: 316px;
            max-width: calc(100vw - 32px);
            max-height: calc(100vh - 24px);
            box-sizing: border-box;
            padding: 12px;
            background: #fff;
            border: 1px solid var(--border, #d8dee9);
            border-radius: 10px;
            box-shadow: 0 14px 38px rgba(0, 0, 0, 0.18);
            overflow: visible;
        }
        .tdi-pop-head {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
            margin-bottom: 9px;
        }
        .tdi-pop-nav {
            flex-shrink: 0;
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border, #d8dee9);
            border-radius: 6px;
            background: #fff;
            color: var(--fg-2, #475569);
            cursor: pointer;
        }
        .tdi-pop-nav:hover { background: var(--bg-2, #f1f5f9); }
        .tdi-pop-nav svg { width: 15px; height: 15px; }
        .tdi-pop-select {
            position: relative;
            min-width: 0;
        }
        .tdi-pop-select.tdi-pop-month { flex: 1; }
        .tdi-pop-select.tdi-pop-year { width: 82px; flex-shrink: 0; }
        .tdi-pop-sel {
            min-width: 0;
            width: 100%;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border: 1px solid var(--border, #d8dee9);
            border-radius: 6px;
            background: #fff;
            font: inherit;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--fg-1, #1e293b);
            padding: 0 10px;
            cursor: pointer;
        }
        .tdi-pop-sel:hover { background: var(--bg-2, #f1f5f9); }
        .tdi-pop-sel:focus {
            outline: none;
            border-color: var(--brand-navy, #1e3a5f);
            box-shadow: 0 0 0 3px rgba(30, 58, 95, 0.12);
        }
        .tdi-pop-sel-label {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .tdi-pop-sel-caret {
            width: 0;
            height: 0;
            flex-shrink: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-top: 5px solid var(--fg-2, #475569);
        }
        .tdi-pop-menu {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            z-index: 1002;
            width: 100%;
            max-height: 184px;
            overflow-y: auto;
            overscroll-behavior: contain;
            border: 1px solid var(--border, #d8dee9);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.16);
        }
        .tdi-pop-year .tdi-pop-menu {
            width: 100%;
            max-height: 184px;
            display: block;
            scrollbar-gutter: stable;
        }
        .tdi-pop-menu button {
            width: 100%;
            min-height: 34px;
            display: block;
            border: 0;
            background: #fff;
            color: var(--fg-1, #1e293b);
            padding: 8px 10px;
            font: inherit;
            font-size: 12.5px;
            font-weight: 600;
            line-height: 1.3;
            text-align: left;
            cursor: pointer;
        }
        .tdi-pop-year .tdi-pop-menu button {
            min-height: 34px;
            padding: 8px 10px;
            text-align: left;
        }
        .tdi-pop-menu button:hover,
        .tdi-pop-menu button.is-selected {
            background: var(--bg-2, #f1f5f9);
        }
        .tdi-pop-menu button.is-selected {
            color: var(--brand-navy, #1e3a5f);
            font-weight: 800;
        }
        .tdi-pop-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }
        .tdi-pop-dow {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 24px;
            font-size: 10.5px;
            font-weight: 800;
            color: var(--fg-3, #6b7280);
        }
        .tdi-pop-day {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 30px;
            border: 1px solid transparent;
            border-radius: 6px;
            background: transparent;
            font: inherit;
            font-size: 12px;
            font-weight: 600;
            color: var(--fg-1, #1e293b);
            cursor: pointer;
        }
        .tdi-pop-day:hover:not(.is-blank) { background: var(--bg-2, #f1f5f9); }
        .tdi-pop-day.is-blank { visibility: hidden; cursor: default; }
        .tdi-pop-day.is-blocked {
            color: var(--fg-3, #6b7280);
            background: var(--bg-1, #f8fafc);
            cursor: not-allowed;
            opacity: 0.62;
        }
        .tdi-pop-day.is-blocked:hover { background: var(--bg-1, #f8fafc); }
        .tdi-pop-day.is-selected {
            background: var(--brand-navy, #1e3a5f);
            color: #fff;
            border-color: var(--brand-navy, #1e3a5f);
        }
    </style>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('thaiDateInput', (options = {}) => ({
                blockWeekends: !!options.blockWeekends,
                calOpen: false,
                calYear: new Date().getFullYear(),
                calMonth: new Date().getMonth(),
                tdiMonthOpen: false,
                tdiYearOpen: false,
                tdiSelectedIso: '',
                tdiPopStyle: '',
                tdiPositionHandler: null,
                tdiGlobalCloseHandler: null,

                // มาส์กข้อความให้เป็นรูปแบบ วว/ดด/พ.ศ.
                init() {
                    this.tdiGlobalCloseHandler = () => this.tdiClose();
                    document.addEventListener('tpss:close-date-popovers', this.tdiGlobalCloseHandler);
                    this.$nextTick(() => this.tdiValidateInput());
                },
                destroy() {
                    if (this.tdiGlobalCloseHandler) {
                        document.removeEventListener('tpss:close-date-popovers', this.tdiGlobalCloseHandler);
                    }
                    this.tdiDetachPositionListeners();
                },
                maskThaiDate(value) {
                    return window.tpssThaiDate.maskInput(value);
                },
                tdiHandleDateInput(event) {
                    if (!event.tpssPreserveThaiDateMask) {
                        event.target.value = this.maskThaiDate(event.target.value);
                    }
                    this.tdiValidateInput();
                },
                tdiParseIso(value) {
                    return window.tpssThaiDate.parseToIso(value);
                },
                tdiHandleDateKeydown(event) {
                    if (event.key !== 'Backspace') return;

                    const el = event.target;
                    const result = window.tpssThaiDate.backwardDelete(
                        el.value,
                        el.selectionStart,
                        el.selectionEnd
                    );
                    if (!result) return;

                    event.preventDefault();
                    el.value = result.value;
                    el.setSelectionRange(result.selectionStart, result.selectionEnd);
                    const inputEvent = new Event('input', { bubbles: true });
                    inputEvent.tpssPreserveThaiDateMask = true;
                    el.dispatchEvent(inputEvent);
                },
                tdiValidateInput() {
                    const el = this.$refs.thaiInput;
                    if (!el) return true;

                    const value = String(el.value || '').trim();
                    let message = '';

                    if (value === '') {
                        message = el.required ? 'กรุณากรอกวันที่' : '';
                    } else if (!this.tdiParseIso(value)) {
                        message = 'วันที่ไม่ถูกต้อง กรุณากรอกในรูปแบบ วว/ดด/พ.ศ. เช่น 21/05/2569';
                    }

                    el.setCustomValidity(message);
                    el.setAttribute('aria-invalid', message ? 'true' : 'false');

                    return message === '';
                },
                get tdiTodayIso() {
                    const t = new Date();
                    return t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0');
                },
                get tdiGrid() {
                    const firstDow = (new Date(this.calYear, this.calMonth, 1).getDay() + 6) % 7;
                    const days = new Date(this.calYear, this.calMonth + 1, 0).getDate();
                    const cells = [];
                    for (let i = 0; i < firstDow; i++) cells.push({ day: null });
                    for (let d = 1; d <= days; d++) cells.push({ day: d });
                    return cells;
                },
                tdiDayIso(day) {
                    return this.calYear + '-' + String(this.calMonth + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                },
                tdiIsBlockedDay(day) {
                    if (!this.blockWeekends || !day) return false;
                    const dayOfWeek = new Date(this.calYear, this.calMonth, day).getDay();
                    return dayOfWeek === 0 || dayOfWeek === 6;
                },
                // sync เดือน/ปีในปฏิทินจากค่าที่พิมพ์ไว้ในช่อง (วว/ดด/พ.ศ.)
                tdiSync() {
                    const parts = String(this.$refs.thaiInput.value || '').match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                    const iso = this.tdiParseIso(this.$refs.thaiInput.value);
                    if (parts && iso) {
                        const [year, month, day] = iso.split('-').map(Number);
                        this.calYear = year;
                        this.calMonth = month - 1;
                        this.tdiSelectedIso = this.tdiDayIso(day);
                    } else {
                        const t = new Date();
                        this.calYear = t.getFullYear();
                        this.calMonth = t.getMonth();
                        this.tdiSelectedIso = '';
                    }
                },
                tdiToggle() {
                    if (!this.calOpen) {
                        this.tdiSync();
                        this.calOpen = true;
                        this.tdiCloseMenus();
                        this.tdiPositionPop();
                        this.$nextTick(() => this.tdiPositionPop());
                        this.tdiAttachPositionListeners();
                        return;
                    }

                    this.tdiClose();
                },
                tdiClose() {
                    this.calOpen = false;
                    this.tdiCloseMenus();
                    this.tdiDetachPositionListeners();
                },
                tdiCloseMenus() {
                    this.tdiMonthOpen = false;
                    this.tdiYearOpen = false;
                },
                tdiPositionPop() {
                    const control = this.$refs.tdiControl;
                    const pop = this.$refs.tdiPop;

                    if (!control) {
                        this.tdiPopStyle = 'top: calc(100% + 8px); bottom: auto; left: 0; right: auto;';
                        return;
                    }

                    const margin = 14;
                    const controlRect = control.getBoundingClientRect();
                    const popWidth = pop && pop.offsetWidth ? pop.offsetWidth : 316;
                    const popHeight = pop && pop.offsetHeight ? pop.offsetHeight : 320;
                    const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
                    const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
                    const inCopyScheduleModal = !!control.closest('.schedule-copy-week-modal');

                    if (inCopyScheduleModal) {
                        const preferredLeft = controlRect.right - popWidth;
                        const left = Math.round(Math.max(margin, Math.min(preferredLeft, viewportWidth - margin - popWidth)));
                        let top = Math.round(controlRect.bottom + 8);

                        if (top + popHeight > viewportHeight - margin && controlRect.top - popHeight - 8 >= margin) {
                            top = Math.round(controlRect.top - popHeight - 8);
                        } else {
                            top = Math.round(Math.max(margin, Math.min(top, viewportHeight - margin - popHeight)));
                        }

                        this.tdiPopStyle = `position: fixed; top: ${top}px; bottom: auto; left: ${left}px; right: auto;`;
                        return;
                    }

                    let clipLeft = 0;
                    let clipRight = viewportWidth;
                    let node = control.parentElement;

                    while (node && node !== document.body && node !== document.documentElement) {
                        const style = window.getComputedStyle(node);
                        const overflow = `${style.overflow} ${style.overflowX} ${style.overflowY}`;

                        if (/(auto|scroll|hidden|clip)/.test(overflow)) {
                            const clip = node.getBoundingClientRect();
                            clipLeft = Math.max(clipLeft, clip.left);
                            clipRight = Math.min(clipRight, clip.right);
                        }

                        node = node.parentElement;
                    }

                    const minLeft = clipLeft + margin - controlRect.left;
                    const maxLeft = clipRight - margin - controlRect.left - popWidth;
                    const alignToControlEnd = !!control.closest('.users-modal-body, .schedule-copy-week-modal');
                    const preferredLeft = alignToControlEnd ? (controlRect.width - popWidth) : 0;
                    const left = Math.round(Math.max(minLeft, Math.min(preferredLeft, maxLeft)));

                    this.tdiPopStyle = `top: calc(100% + 8px); bottom: auto; left: ${left}px; right: auto;`;
                },
                tdiIsControlVisible(rect, viewportWidth, viewportHeight) {
                    if (rect.bottom <= 0 || rect.top >= viewportHeight || rect.right <= 0 || rect.left >= viewportWidth) {
                        return false;
                    }

                    let clipTop = 0;
                    let clipRight = viewportWidth;
                    let clipBottom = viewportHeight;
                    let clipLeft = 0;
                    let node = this.$refs.tdiControl ? this.$refs.tdiControl.parentElement : null;

                    while (node && node !== document.body && node !== document.documentElement) {
                        const style = window.getComputedStyle(node);
                        const overflow = `${style.overflow} ${style.overflowX} ${style.overflowY}`;

                        if (/(auto|scroll|hidden|clip)/.test(overflow)) {
                            const clip = node.getBoundingClientRect();
                            clipTop = Math.max(clipTop, clip.top);
                            clipRight = Math.min(clipRight, clip.right);
                            clipBottom = Math.min(clipBottom, clip.bottom);
                            clipLeft = Math.max(clipLeft, clip.left);
                        }

                        node = node.parentElement;
                    }

                    return rect.bottom > clipTop
                        && rect.top < clipBottom
                        && rect.right > clipLeft
                        && rect.left < clipRight;
                },
                tdiAttachPositionListeners() {
                    if (this.tdiPositionHandler) return;

                    this.tdiPositionHandler = () => {
                        if (this.calOpen) this.tdiPositionPop();
                    };
                    window.addEventListener('resize', this.tdiPositionHandler, { passive: true });
                    window.addEventListener('scroll', this.tdiPositionHandler, true);
                },
                tdiDetachPositionListeners() {
                    if (!this.tdiPositionHandler) return;

                    window.removeEventListener('resize', this.tdiPositionHandler);
                    window.removeEventListener('scroll', this.tdiPositionHandler, true);
                    this.tdiPositionHandler = null;
                },
                tdiToggleMonth() {
                    this.tdiYearOpen = false;
                    this.tdiMonthOpen = !this.tdiMonthOpen;
                    if (this.tdiMonthOpen) this.tdiScrollMenu('month');
                },
                tdiToggleYear() {
                    this.tdiMonthOpen = false;
                    this.tdiYearOpen = !this.tdiYearOpen;
                    if (this.tdiYearOpen) this.tdiScrollMenu('year');
                },
                tdiScrollMenu(kind) {
                    this.$nextTick(() => {
                        const menu = this.$root.querySelector('.tdi-pop-' + kind + ' .tdi-pop-menu');
                        const selected = menu ? menu.querySelector('.is-selected') : null;
                        if (!menu || !selected) return;

                        const targetTop = selected.offsetTop - ((menu.clientHeight - selected.offsetHeight) / 2);
                        menu.scrollTop = Math.max(0, targetTop);
                    });
                },
                tdiPickMonth(month) {
                    this.calMonth = month;
                    this.tdiMonthOpen = false;
                },
                tdiPickYear(year) {
                    this.calYear = year;
                    this.tdiYearOpen = false;
                },
                tdiShiftMonth(delta) {
                    let month = this.calMonth + delta;
                    let year = this.calYear;
                    if (month < 0) { month = 11; year -= 1; }
                    if (month > 11) { month = 0; year += 1; }
                    this.calMonth = month;
                    this.calYear = year;
                    this.tdiCloseMenus();
                    this.tdiPositionPop();
                },
                tdiPick(day) {
                    if (!day || this.tdiIsBlockedDay(day)) return;
                    const thai = String(day).padStart(2, '0')
                        + '/' + String(this.calMonth + 1).padStart(2, '0')
                        + '/' + (this.calYear + 543);
                    const input = this.$refs.thaiInput;
                    input.value = thai;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    this.tdiSelectedIso = this.tdiDayIso(day);
                    this.tdiClose();
                },
            }));
        });
    </script>

    <!-- Alpine.js (Collapse plugin must load before core) -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.13.3/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }

        /* ─── TPSS Delete Confirm Dialog ─────────────────────────────── */
        .swal2-container.tpss-delete-container {
            inset: 0 0 0 var(--sidebar-w, 0px) !important;
            width: auto !important;
            padding: 24px !important;
        }
        @media (max-width: 1024px) {
            .swal2-container.tpss-delete-container {
                inset: 0 !important;
                padding: 16px !important;
            }
        }
        .tpss-delete-popup {
            border-radius: 20px !important;
            padding: 0 !important;
            max-width: 400px !important;
            width: 90vw !important;
            max-height: min(640px, calc(100dvh - 32px)) !important;
            display: flex !important;
            flex-direction: column !important;
            box-shadow: 0 32px 64px rgba(15,23,42,0.20), 0 0 0 1px rgba(15,23,42,0.06) !important;
            font-family: 'IBM Plex Sans Thai', sans-serif !important;
            overflow: hidden !important;
        }
        .tpss-delete-popup .swal2-html-container {
            margin: 0 !important;
            padding: 32px 28px 0 !important;
            display: block !important;
            flex: 1 1 auto !important;
            min-height: 0 !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }
        .tpss-delete-popup .swal2-html-container::-webkit-scrollbar {
            width: 0 !important;
            height: 0 !important;
        }
        .tpss-delete-popup > :where(
            .swal2-image,
            .swal2-input,
            .swal2-file,
            .swal2-range,
            .swal2-select,
            .swal2-radio,
            .swal2-checkbox,
            .swal2-textarea,
            .swal2-validation-message,
            .swal2-footer,
            .swal2-timer-progress-bar,
            .swal2-loader
        ),
        .tpss-delete-popup > .swal2-icon:not(.swal2-icon-show),
        .tpss-delete-popup > .swal2-title:empty {
            display: none !important;
        }
        .tpss-delete-actions {
            padding: 20px 28px 24px !important;
            margin: 0 !important;
            gap: 8px !important;
            display: flex !important;
            flex: 0 0 auto !important;
            justify-content: flex-end !important;
            border-top: 1px solid #f1f5f9 !important;
            margin-top: 20px !important;
            background: #fafafa !important;
        }
        .tpss-delete-confirm {
            background: #dc2626 !important;
            color: #fff !important;
            border: none !important;
            border-radius: 9px !important;
            padding: 10px 24px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            font-family: inherit !important;
            letter-spacing: 0.01em !important;
            transition: all 0.15s !important;
            box-shadow: 0 2px 8px rgba(220,38,38,0.30) !important;
        }
        .tpss-delete-confirm:hover { background: #b91c1c !important; box-shadow: 0 4px 12px rgba(220,38,38,0.40) !important; transform: translateY(-1px) !important; }
        .tpss-delete-confirm:focus { outline: none !important; box-shadow: 0 0 0 3px rgba(220,38,38,0.25) !important; }
        .tpss-delete-cancel {
            background: #fff !important;
            color: #475569 !important;
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 9px !important;
            padding: 10px 24px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            font-family: inherit !important;
            transition: all 0.15s !important;
        }
        .tpss-delete-cancel:hover { background: #f8fafc !important; border-color: #cbd5e1 !important; }
        .tpss-delete-cancel:focus { outline: none !important; box-shadow: 0 0 0 3px rgba(148,163,184,0.25) !important; }
        .tpss-warn-confirm {
            background: #d97706 !important;
            color: #fff !important;
            border: none !important;
            border-radius: 9px !important;
            padding: 10px 24px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            font-family: inherit !important;
            letter-spacing: 0.01em !important;
            transition: all 0.15s !important;
            box-shadow: 0 2px 8px rgba(217,119,6,0.30) !important;
        }
        .tpss-warn-confirm:hover { background: #b45309 !important; box-shadow: 0 4px 12px rgba(217,119,6,0.40) !important; transform: translateY(-1px) !important; }
        .tpss-warn-confirm:focus { outline: none !important; box-shadow: 0 0 0 3px rgba(217,119,6,0.25) !important; }
        .tpss-item-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 14px;
            margin: 14px 0 0;
            text-align: left;
            max-width: 100%;
            min-width: 0;
        }
        .tpss-item-badge-icon {
            width: 30px;
            height: 30px;
            border-radius: 7px;
            background: #fff;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .tpss-item-badge-text {
            font-size: 13.5px;
            font-weight: 600;
            color: #1e293b;
            word-break: break-word;
            overflow-wrap: anywhere;
            line-height: 1.4;
            min-width: 0;
            max-height: 10.5rem;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .tpss-item-badge-text::-webkit-scrollbar {
            width: 0;
            height: 0;
        }
        @media (max-width: 480px) {
            .tpss-delete-popup {
                width: calc(100vw - 24px) !important;
                max-height: calc(100dvh - 24px) !important;
            }
            .tpss-delete-popup .swal2-html-container {
                padding: 28px 20px 0 !important;
            }
            .tpss-delete-actions {
                padding: 16px 20px 20px !important;
                flex-direction: column-reverse !important;
            }
            .tpss-delete-actions :where(button) {
                width: 100% !important;
                margin: 0 !important;
            }
        }

        :where(.modal-center, .profile-modal-shell, .users-modal, .instructor-modal, .room-modal, .course-modal, .tpss-delete-popup) {
            border-radius: 20px !important;
            border: 1px solid color-mix(in oklch, var(--brand-navy, #002b5c) 22%, var(--border, #d8dee9)) !important;
            background:
                linear-gradient(180deg, color-mix(in oklch, var(--brand-navy, #002b5c) 5%, var(--surface, #fff)), var(--surface, #fff) 42%),
                var(--surface, #fff) !important;
            box-shadow:
                0 24px 54px -28px rgba(0, 36, 84, 0.52),
                0 4px 18px rgba(15, 23, 42, 0.12) !important;
            overflow: hidden !important;
        }

        :where(.modal-center, .profile-modal-shell, .users-modal, .instructor-modal, .room-modal, .course-modal) :where(.modal-hdr) {
            border-bottom: 1px solid color-mix(in oklch, var(--brand-navy, #002b5c) 18%, var(--border, #d8dee9)) !important;
            background:
                linear-gradient(180deg,
                    color-mix(in oklch, var(--brand-navy, #002b5c) 10%, var(--surface, #fff)),
                    color-mix(in oklch, var(--brand-navy, #002b5c) 4%, var(--surface, #fff))) !important;
        }

        :where(.modal-center, .profile-modal-shell, .users-modal, .instructor-modal, .room-modal, .course-modal) :where(.modal-foot) {
            border-top: 1px solid color-mix(in oklch, var(--brand-navy, #002b5c) 16%, var(--border, #d8dee9)) !important;
            background: color-mix(in oklch, var(--brand-navy, #002b5c) 6%, var(--surface, #fff)) !important;
        }

        :where(.modal-body, .profile-modal-body, .users-modal-body, .instructor-modal-body, .room-modal-body, .course-modal-body) {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        :where(.modal-body, .profile-modal-body, .users-modal-body, .instructor-modal-body, .room-modal-body, .course-modal-body)::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none;
        }

        :where(.tpss-scroll-hidden, .tabs, [role="tablist"]) {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        :where(.tpss-scroll-hidden, .tabs, [role="tablist"])::-webkit-scrollbar {
            width: 0;
            height: 0;
            display: none;
        }
    </style>
</head>
<body x-data="{ sidebarOpen: window.innerWidth > 1024 }">
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" :class="{ 'is-open': sidebarOpen }" @click="sidebarOpen = false"></div>

    <div class="app-layout">
        <!-- Sidebar -->
        @include('components.sidebar')

        <!-- Main -->
        <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <!-- Hamburger Menu -->
                <button @click="sidebarOpen = !sidebarOpen" class="action-btn" data-testid="sidebar-toggle" style="display: none; border: none; background: transparent;" :style="{ display: window.innerWidth <= 1024 ? 'flex' : 'none' }">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <span class="tb-title">{{ $title }}</span>

                {{-- M11 — กระดิ่งแจ้งเตือน --}}
                @php $navUnread = $navUnreadCount ?? 0; $navItems = $navNotifications ?? collect(); @endphp
                <div class="tb-notif" style="margin-left:auto;position:relative;" x-data="{ open: false }">
                    <button type="button" class="action-btn tb-notif-btn" @click="open = !open" :aria-expanded="open"
                            data-testid="notif-bell" aria-label="การแจ้งเตือน" style="border:none;background:transparent;cursor:pointer;position:relative;color:var(--fg-2);">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        @if($navUnread > 0)
                            <span class="tb-notif-badge" data-testid="notif-unread-count">{{ $navUnread > 9 ? '9+' : $navUnread }}</span>
                        @endif
                    </button>

                    <div x-show="open" x-cloak @click.outside="open = false" @keydown.escape.window="open = false"
                         class="tb-notif-pop" data-testid="notif-dropdown">
                        <div class="tb-notif-head">
                            <span style="font-weight:700;color:var(--fg-1);">การแจ้งเตือน</span>
                            @if($navUnread > 0)
                                <form method="POST" action="{{ route('notifications.read_all') }}" style="margin:0;">
                                    @csrf
                                    <button type="submit" class="tb-notif-readall" data-testid="notif-mark-all">อ่านทั้งหมด</button>
                                </form>
                            @endif
                        </div>
                        <div class="tb-notif-list">
                            @forelse($navItems as $n)
                                <a href="{{ route('notifications.open', $n) }}" class="tb-notif-item {{ $n->is_read ? '' : 'is-unread' }}" data-testid="notif-item">
                                    <span class="tb-notif-dot" aria-hidden="true"></span>
                                    <span class="tb-notif-body">
                                        <span class="tb-notif-msg">{{ $n->message }}</span>
                                        <span class="tb-notif-time">{{ $n->created_at?->diffForHumans() }}</span>
                                    </span>
                                </a>
                            @empty
                                <div class="tb-notif-empty" data-testid="notif-empty">ยังไม่มีการแจ้งเตือน</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <style>
                .tb-notif-btn:hover { color: var(--brand-navy); }
                .tb-notif-badge {
                    position: absolute; top: 2px; right: 2px;
                    min-width: 16px; height: 16px; padding: 0 4px;
                    display: inline-flex; align-items: center; justify-content: center;
                    background: var(--status-conflict, #b82a1e); color: #fff;
                    font-size: 10px; font-weight: 800; line-height: 1;
                    border-radius: 999px; font-variant-numeric: tabular-nums;
                }
                .tb-notif-pop {
                    position: absolute; top: calc(100% + 8px); right: 0;
                    width: min(360px, 90vw);
                    background: var(--surface); border: 1px solid var(--border);
                    border-radius: 10px; box-shadow: 0 18px 48px -22px rgba(0,36,84,0.5);
                    z-index: var(--z-modal, 200); overflow: hidden;
                }
                .tb-notif-head {
                    display: flex; align-items: center; justify-content: space-between;
                    padding: 12px 14px; border-bottom: 1px solid var(--border);
                    background: color-mix(in oklch, var(--brand-navy) 4%, var(--surface));
                }
                .tb-notif-readall {
                    border: none; background: transparent; cursor: pointer;
                    color: var(--brand-navy); font-size: 12px; font-weight: 700;
                }
                .tb-notif-readall:hover { text-decoration: underline; }
                .tb-notif-list { max-height: 360px; overflow-y: auto; }
                .tb-notif-item {
                    display: flex; gap: 10px; align-items: flex-start;
                    padding: 12px 14px; border-bottom: 1px solid var(--border);
                    text-decoration: none; color: var(--fg-1);
                }
                .tb-notif-item:last-child { border-bottom: none; }
                .tb-notif-item:hover { background: color-mix(in oklch, var(--brand-navy) 5%, var(--surface)); }
                .tb-notif-item.is-unread { background: color-mix(in oklch, var(--status-info, #2b6cb0) 6%, var(--surface)); }
                .tb-notif-dot {
                    width: 8px; height: 8px; border-radius: 50%; margin-top: 6px; flex-shrink: 0;
                    background: transparent;
                }
                .tb-notif-item.is-unread .tb-notif-dot { background: var(--status-info, #2b6cb0); }
                .tb-notif-body { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
                .tb-notif-msg { font-size: 13px; line-height: 1.5; color: var(--fg-1); }
                .tb-notif-time { font-size: 11px; color: var(--fg-3); }
                .tb-notif-empty { padding: 24px 14px; text-align: center; color: var(--fg-3); font-size: 13px; }
            </style>

            <!-- Content -->
            <div class="content-area">
                {{ $slot }}
            </div>
        </div>
    </div>

    <x-profile-modal />

    <script>
        /* ─── tpssDelete(btn) — call via onclick="tpssDelete(this)"
           btn must have data-form="<form-id>" and data-label="<item name>"
           Optionally data-warn="<warning text>" ─────────────────────── */
        function tpssDelete(btn) {
            var formId = btn.getAttribute('data-form');
            var label  = btn.getAttribute('data-label') || '';
            var warn   = btn.getAttribute('data-warn')  || 'การดำเนินการนี้ไม่สามารถย้อนกลับได้';

            function doSubmit() {
                try {
                    sessionStorage.setItem('tpss-schedule-scroll-y', window.scrollY);
                    sessionStorage.setItem('tpss-schedule-scroll-height', document.documentElement.scrollHeight);
                } catch (e) {}

                document.getElementById(formId).submit();
            }

            if (typeof Swal === 'undefined') {
                if (confirm('ยืนยันการลบ?\n\n' + label + '\n' + warn)) doSubmit();
                return;
            }

            function escapeHtml(value) {
                return String(value).replace(/[&<>"']/g, function (char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    }[char];
                });
            }

            var safeLabel = escapeHtml(label);
            var safeWarn = escapeHtml(warn);
            var itemHtml = label
                ? '<div class="tpss-item-badge">'
                  + '<div class="tpss-item-badge-icon">'
                  + '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#64748b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'
                  + '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>'
                  + '<polyline points="14 2 14 8 20 8"/></svg></div>'
                  + '<span class="tpss-item-badge-text">' + safeLabel + '</span></div>'
                : '';

            var warnHtml = '<div style="display:flex;align-items:flex-start;gap:7px;margin-top:14px;'
                + 'padding:10px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;text-align:left;">'
                + '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#d97706" stroke-width="2.5" '
                + 'stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;">'
                + '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>'
                + '<line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
                + '<span style="font-size:12.5px;color:#92400e;line-height:1.65;">' + safeWarn + '</span></div>';

            var innerHtml = '<div style="text-align:center;">'
                + '<div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#fef2f2,#fee2e2);'
                + 'border:2px solid #fca5a5;display:flex;align-items:center;justify-content:center;'
                + 'margin:0 auto 16px;box-shadow:0 4px 16px rgba(220,38,38,0.15);">'
                + '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="#dc2626" stroke-width="2" '
                + 'stroke-linecap="round" stroke-linejoin="round">'
                + '<path d="M3 6h18"/>'
                + '<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>'
                + '<line x1="10" y1="11" x2="10" y2="17"/>'
                + '<line x1="14" y1="11" x2="14" y2="17"/></svg></div>'
                + '<div style="font-family:Kanit,sans-serif;font-size:19px;font-weight:700;color:#0f172a;line-height:1.2;">'
                + 'ยืนยันการลบข้อมูล</div>'
                + '<div style="font-size:13px;color:#94a3b8;margin-top:4px;">กรุณาตรวจสอบข้อมูลก่อนดำเนินการ</div>'
                + itemHtml + warnHtml + '</div>';

            Swal.fire({
                html: innerHtml,
                showCancelButton: true,
                confirmButtonText: 'ลบข้อมูล',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true,
                focusCancel: true,
                buttonsStyling: false,
                heightAuto: false,
                scrollbarPadding: false,
                customClass: {
                    container:     'tpss-delete-container',
                    popup:         'tpss-delete-popup',
                    confirmButton: 'tpss-delete-confirm',
                    cancelButton:  'tpss-delete-cancel',
                    actions:       'tpss-delete-actions',
                }
            }).then(function(result) {
                if (result.isConfirmed) doSubmit();
            });
        }

        /* ─── Back-compat: Alpine methods call this ──────────────────── */
        window.tpssConfirmDelete = function(formId, label, warn) {
            var fakeBtn = { getAttribute: function(k) {
                return k === 'data-form' ? formId : k === 'data-label' ? (label || '') : (warn || '');
            }};
            tpssDelete(fakeBtn);
        };

        /* ─── Cascade delete confirm for curriculum (with course count) ── */
        window.tpssConfirmCascadeCurriculum = function(curriculumName, courseCount, simpleConfirmFallback) {
            if (courseCount === 0) {
                simpleConfirmFallback && simpleConfirmFallback();
                return;
            }
            var form = document.getElementById('deleteCurriculumForm');
            var body = document.createElement('div');
            body.style.textAlign = 'left';
            body.style.fontSize = '14px';
            body.style.lineHeight = '1.6';
            var warn = document.createElement('div');
            warn.style.color = '#b91c1c';
            warn.style.fontWeight = '700';
            warn.style.marginBottom = '8px';
            warn.textContent = '⚠️ การกระทำนี้ไม่สามารถกู้คืนได้';
            var info = document.createElement('div');
            info.innerHTML = 'หลักสูตร <strong></strong> มี <strong></strong> รายวิชา';
            info.querySelectorAll('strong')[0].textContent = curriculumName;
            info.querySelectorAll('strong')[1].textContent = courseCount;
            var hint = document.createElement('div');
            hint.style.color = '#6b7280';
            hint.style.marginTop = '8px';
            hint.textContent = 'ระบบจะลบรายวิชาทั้งหมดในหลักสูตรนี้พร้อมกัน (เฉพาะวิชาที่ยังไม่ถูกนำไปใช้ในข้อมูลการสอน)';
            body.appendChild(warn);
            body.appendChild(info);
            body.appendChild(hint);

            Swal.fire({
                title: 'ยืนยันการลบหลักสูตรแบบ Cascade',
                html: body,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ลบหลักสูตรและรายวิชา',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true,
                focusCancel: true,
                buttonsStyling: false,
                customClass: {
                    container:     'tpss-delete-container',
                    popup:         'tpss-delete-popup',
                    confirmButton: 'tpss-delete-confirm',
                    cancelButton:  'tpss-delete-cancel',
                    actions:       'tpss-delete-actions',
                }
            }).then(function(result) {
                if (result.isConfirmed && form) {
                    var cascadeInput = form.querySelector('input[name="confirm_cascade"]');
                    if (!cascadeInput) {
                        cascadeInput = document.createElement('input');
                        cascadeInput.type = 'hidden';
                        cascadeInput.name = 'confirm_cascade';
                        form.appendChild(cascadeInput);
                    }
                    cascadeInput.value = '1';
                    form.submit();
                }
            });
        };

        /* ─── tpssToast(message, type) — slide-down notification bar ── */
        function tpssToast(message, type) {
            var existing = document.getElementById('tpss-toast');
            if (existing) existing.remove();

            var isError = type === 'error';
            var isWarning = type === 'warning';
            var bg      = 'linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 6%, var(--surface)), var(--surface))';
            var border  = isError
                ? 'var(--status-conflict-border)'
                : isWarning
                    ? 'var(--status-warning-border)'
                    : 'color-mix(in oklch, var(--brand-navy) 22%, var(--border))';
            var iconClr = isError ? 'var(--status-conflict-fg)' : isWarning ? 'var(--status-warning-fg)' : 'var(--status-success-fg)';
            var iconBg  = isError
                ? 'color-mix(in oklch, var(--status-conflict-fg) 12%, transparent)'
                : isWarning
                    ? 'color-mix(in oklch, var(--status-warning-fg) 12%, transparent)'
                    : 'color-mix(in oklch, var(--status-success-fg) 12%, transparent)';
            var textClr = 'var(--fg-2)';
            var labelClr= isError ? 'var(--status-conflict-fg)' : isWarning ? 'var(--status-warning-fg)' : 'var(--fg-1)';
            var label   = isError ? 'เกิดข้อผิดพลาด' : isWarning ? 'ดำเนินการแล้ว' : 'สำเร็จ';

            var iconSvg = !isError
                ? '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="' + iconClr + '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                : '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="' + iconClr + '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';

            var toast = document.createElement('div');
            toast.id = 'tpss-toast';
            toast.innerHTML =
                '<div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;">'
                + '<div style="flex-shrink:0;width:38px;height:38px;border-radius:50%;background:' + iconBg + ';display:flex;align-items:center;justify-content:center;">'
                + iconSvg + '</div>'
                + '<div style="min-width:0;">'
                + '<div style="font-size:13px;font-weight:800;color:' + labelClr + ';line-height:1.2;">' + label + '</div>'
                + '<div style="font-size:13px;color:' + textClr + ';line-height:1.55;margin-top:3px;word-break:break-word;">' + message + '</div>'
                + '</div></div>'
                + '<button onclick="document.getElementById(\'tpss-toast\').remove()" style="flex-shrink:0;background:transparent;border:none;cursor:pointer;padding:4px;border-radius:6px;color:' + iconClr + ';opacity:0.6;margin-left:8px;" title="ปิด">'
                + '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
                + '</button>'
                + '<div id="tpss-toast-bar" style="position:absolute;bottom:0;left:0;height:3px;border-radius:0 0 14px 14px;background:' + iconClr + ';width:100%;transition:width linear;"></div>';

            Object.assign(toast.style, {
                position: 'fixed',
                top: '20px',
                right: '20px',
                zIndex: '99999',
                background: bg,
                border: '1px solid ' + border,
                borderRadius: '16px',
                padding: '14px 16px',
                display: 'flex',
                alignItems: 'center',
                gap: '0',
                maxWidth: '420px',
                width: 'calc(100vw - 40px)',
                boxShadow: '0 1px 2px rgba(0,36,84,0.08), 0 24px 54px -28px rgba(0,36,84,0.52), 0 8px 24px rgba(15,23,42,0.10)',
                fontFamily: 'IBM Plex Sans Thai, sans-serif',
                transform: 'translateY(-80px)',
                opacity: '0',
                transition: 'transform 0.35s cubic-bezier(0.34,1.56,0.64,1), opacity 0.25s ease',
                overflow: 'hidden',
            });

            document.body.appendChild(toast);

            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    toast.style.transform = 'translateY(0)';
                    toast.style.opacity = '1';
                });
            });

            if (!isError) {
                var duration = 4000;
                var bar = document.getElementById('tpss-toast-bar');
                if (bar) {
                    bar.style.transitionDuration = duration + 'ms';
                    requestAnimationFrame(function() {
                        requestAnimationFrame(function() { bar.style.width = '0%'; });
                    });
                }
                setTimeout(function() {
                    if (!toast.parentNode) return;
                    toast.style.transform = 'translateY(-80px)';
                    toast.style.opacity = '0';
                    setTimeout(function() { if (toast.parentNode) toast.remove(); }, 350);
                }, duration);
            }
        }

        function tpssShowFlashToasts() {
            @if(session('success'))
                tpssToast(@json(session('success')), 'success');
            @endif

            @if(session('error'))
                tpssToast(@json(session('error')), 'error');
            @endif

            @if(session('warning'))
                tpssToast(@json(session('warning')), 'warning');
            @endif
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', tpssShowFlashToasts, { once: true });
        } else {
            tpssShowFlashToasts();
        }
        // Expose function to initialize TPSS select dropdowns on-demand (used when modals render dynamically)
        window.tpssInitChoices = function(root) {
            try {
                var scope = root || document;
                var selects = [];
                var selector = 'select.tpss-choices, select.tpss-custom-select, select.offering-select-control';

                if (scope.matches && scope.matches(selector)) {
                    selects.push(scope);
                }

                scope.querySelectorAll(selector).forEach(function(el) {
                    selects.push(el);
                });

                selects.forEach(function(el) {
                    if (el._tpssSelect) return;
                    if (el.multiple || Number(el.getAttribute('size') || 0) > 1) return;
                    if (el.dataset.nativeSelect === 'true' || el.classList.contains('tpss-no-custom-select')) return;
                    if (el.closest('.choices')) return;

                    var wrapper = document.createElement('div');
                    var trigger = document.createElement('button');
                    var label = document.createElement('span');
                    var caret = document.createElement('span');
                    var menu = document.createElement('div');

                    wrapper.className = 'tpss-select';
                    trigger.type = 'button';
                    trigger.className = 'tpss-select-trigger';
                    trigger.setAttribute('aria-haspopup', 'listbox');
                    trigger.setAttribute('aria-expanded', 'false');
                    label.className = 'tpss-select-label';
                    caret.className = 'tpss-select-caret';
                    menu.className = 'tpss-select-menu';
                    menu.setAttribute('role', 'listbox');
                    menu.hidden = true;

                    el.classList.add('tpss-native-select');
                    el.setAttribute('tabindex', '-1');
                    el.parentNode.insertBefore(wrapper, el);
                    wrapper.appendChild(el);
                    wrapper.appendChild(trigger);
                    trigger.appendChild(label);
                    trigger.appendChild(caret);
                    wrapper.appendChild(menu);

                    var close = function() {
                        menu.hidden = true;
                        trigger.setAttribute('aria-expanded', 'false');
                    };

                    var selectedText = function() {
                        var option = el.options[el.selectedIndex] || el.options[0];
                        return option ? option.text.trim() : '';
                    };

                    var positionAnchor = function() {
                        if (!el.dataset.menuAnchor) return trigger;

                        try {
                            return el.closest(el.dataset.menuAnchor) || trigger;
                        } catch (e) {
                            return trigger;
                        }
                    };

                    var positionMenu = function() {
                        if (menu.hidden) return;

                        var rect = positionAnchor().getBoundingClientRect();
                        var modalBody = trigger.closest('.modal-form-body, .modal-body, .users-modal-body');
                        var boundary = modalBody ? modalBody.getBoundingClientRect() : {
                            top: 12,
                            bottom: window.innerHeight - 12
                        };
                        var gutter = 6;
                        var minHeight = 96;
                        var desiredHeight = Math.min(menu.scrollHeight || 280, 280);
                        var belowSpace = Math.max(0, boundary.bottom - rect.bottom - gutter);
                        var aboveSpace = Math.max(0, rect.top - boundary.top - gutter);
                        var forceBottom = el.dataset.menuPlacement === 'bottom' || !!trigger.closest('.schedule-modal');
                        var openUp = !forceBottom && belowSpace < desiredHeight && aboveSpace > belowSpace;
                        var available = openUp ? aboveSpace : belowSpace;
                        var menuHeight = Math.min(desiredHeight, Math.max(minHeight, available));

                        if (modalBody) {
                            menu.style.position = 'absolute';
                            menu.style.left = '0';
                            menu.style.top = openUp ? 'auto' : (rect.height + gutter) + 'px';
                            menu.style.bottom = openUp ? (rect.height + gutter) + 'px' : 'auto';
                            menu.style.width = '100%';
                            menu.style.maxHeight = menuHeight + 'px';
                            return;
                        }

                        menu.style.position = 'fixed';
                        menu.style.left = rect.left + 'px';
                        menu.style.top = openUp
                            ? Math.max(boundary.top + gutter, rect.top - menuHeight - gutter) + 'px'
                            : (rect.bottom + gutter) + 'px';
                        menu.style.bottom = 'auto';
                        menu.style.width = rect.width + 'px';
                        menu.style.maxHeight = menuHeight + 'px';
                    };

                    var sync = function() {
                        label.textContent = selectedText();
                        trigger.disabled = el.disabled;

                        Array.prototype.forEach.call(menu.querySelectorAll('.tpss-select-option'), function(optionButton) {
                            optionButton.classList.toggle('is-selected', optionButton.dataset.value === el.value);
                        });
                    };

                    var rebuild = function() {
                        menu.innerHTML = '';
                        Array.prototype.forEach.call(el.options, function(option) {
                            var item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'tpss-select-option' + (option.value === '' ? ' is-placeholder' : '');
                            item.setAttribute('role', 'option');
                            item.dataset.value = option.value;
                            item.textContent = option.text.trim();
                            item.disabled = option.disabled;
                            item.addEventListener('click', function() {
                                el.value = option.value;
                                el.dispatchEvent(new Event('change', { bubbles: true }));
                                el.dispatchEvent(new Event('input', { bubbles: true }));
                                close();
                                sync();
                            });
                            menu.appendChild(item);
                        });
                        sync();
                    };

                    trigger.addEventListener('click', function() {
                        if (el.disabled) return;

                        document.querySelectorAll('.tpss-select-menu').forEach(function(otherMenu) {
                            if (otherMenu !== menu) otherMenu.hidden = true;
                        });

                        menu.hidden = !menu.hidden;
                        trigger.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
                        positionMenu();
                    });

                    document.addEventListener('click', function(event) {
                        if (!wrapper.contains(event.target)) close();
                    });
                    window.addEventListener('resize', positionMenu);
                    window.addEventListener('scroll', positionMenu, true);
                    el.addEventListener('change', sync);

                    var observer = new MutationObserver(function() {
                        rebuild();
                    });
                    observer.observe(el, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'label', 'value', 'selected'] });

                    el._tpssSelect = { rebuild: rebuild, sync: sync, close: close, observer: observer };
                    rebuild();
                });
            } catch (e) {
                console.warn('TPSS select init failed', e);
            }
        };

        // Initial run on DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            window.tpssInitChoices();
        });
    </script>
    <script>
        (function () {
            var markReady = function () {
                requestAnimationFrame(function () {
                    document.documentElement.classList.add('tpss-page-ready');
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', markReady, { once: true });
            } else {
                markReady();
            }
        })();
    </script>
</body>
</html>
