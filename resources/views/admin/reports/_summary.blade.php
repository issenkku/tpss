@php
    $overQuota = $summary['over_quota_count'];
    $cards = [
        ['label' => 'อาจารย์ที่มีภาระงาน', 'value' => number_format($summary['instructor_count']), 'unit' => 'คน', 'tone' => 'navy'],
        ['label' => 'ชั่วโมงรวมทั้งคณะ', 'value' => number_format($summary['total_hours'], 1), 'unit' => 'ชม.', 'tone' => 'navy'],
        ['label' => 'ชั่วโมงฝึกปฏิบัติ', 'value' => number_format($summary['practicum_hours'], 1), 'unit' => 'ชม.', 'tone' => 'navy'],
        ['label' => 'เกินเกณฑ์ภาระงาน', 'value' => number_format($overQuota), 'unit' => 'คน', 'tone' => $overQuota > 0 ? 'warn' : 'navy'],
    ];
@endphp

@php
    $levels = [
        ['key' => 'bachelor', 'label' => 'ปริญญาตรี'],
        ['key' => 'master', 'label' => 'ปริญญาโท'],
        ['key' => 'doctorate', 'label' => 'ปริญญาเอก'],
    ];
    $levelTotal = array_sum($byLevel ?? []);
@endphp

<div class="wl-summary" data-testid="workload-summary">
    @foreach($cards as $card)
        <div class="wl-summary-card {{ $card['tone'] === 'warn' ? 'is-warn' : '' }}">
            <div class="wl-summary-label">{{ $card['label'] }}</div>
            <div class="wl-summary-metric">
                <span class="wl-summary-value">{{ $card['value'] }}</span>
                <span class="wl-summary-unit">{{ $card['unit'] }}</span>
            </div>
        </div>
    @endforeach
</div>

<div class="wl-level" data-testid="workload-by-level">
    <div class="wl-level-label">ภาระงานแยกตามระดับหลักสูตร</div>
    <div class="wl-level-bars">
        @foreach($levels as $level)
            @php $value = $byLevel[$level['key']] ?? 0; @endphp
            <div class="wl-level-item">
                <div class="wl-level-head">
                    <span class="wl-level-name">{{ $level['label'] }}</span>
                    <span class="wl-level-value">{{ number_format($value, 1) }} ชม.</span>
                </div>
                <div class="wl-level-track">
                    <div class="wl-level-fill" style="width: {{ $levelTotal > 0 ? round($value / $levelTotal * 100) : 0 }}%;"></div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<style>
    .wl-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .wl-summary-card {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 18px 20px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
        border-radius: var(--r-lg);
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface) 64%);
        min-width: 0;
    }

    .wl-summary-card.is-warn {
        border-color: var(--status-warning-border);
        background: var(--status-warning-bg);
    }

    .wl-summary-label {
        font-size: 12px;
        font-weight: 800;
        color: color-mix(in oklch, var(--brand-navy) 76%, var(--fg-2));
        overflow-wrap: anywhere;
    }

    .wl-summary-card.is-warn .wl-summary-label {
        color: var(--status-warning-fg);
    }

    .wl-summary-metric {
        display: flex;
        align-items: baseline;
        gap: 6px;
        margin-top: auto;
    }

    .wl-summary-value {
        font-family: var(--font-display);
        font-size: 30px;
        font-weight: 800;
        color: var(--brand-navy);
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }

    .wl-summary-card.is-warn .wl-summary-value {
        color: var(--status-warning-fg);
    }

    .wl-summary-unit {
        font-size: 12.5px;
        font-weight: 700;
        color: var(--fg-3);
    }

    @media (max-width: 1100px) {
        .wl-summary { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 540px) {
        .wl-summary { grid-template-columns: 1fr; }
    }

    /* แยกตามระดับหลักสูตร */
    .wl-level {
        padding: 18px 20px;
        border: 1px solid color-mix(in oklch, var(--brand-navy) 22%, var(--border));
        border-radius: var(--r-lg);
        background:
            linear-gradient(180deg, color-mix(in oklch, var(--brand-navy) 4%, var(--surface)), var(--surface) 64%);
        margin-bottom: 18px;
    }

    .wl-level-label {
        font-size: 12px;
        font-weight: 800;
        color: color-mix(in oklch, var(--brand-navy) 76%, var(--fg-2));
        margin-bottom: 14px;
    }

    .wl-level-bars {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }

    .wl-level-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 7px;
    }

    .wl-level-name {
        font-size: 13px;
        font-weight: 700;
        color: var(--fg-2);
    }

    .wl-level-value {
        font-size: 13px;
        font-weight: 800;
        color: var(--brand-navy);
        font-variant-numeric: tabular-nums;
    }

    .wl-level-track {
        height: 8px;
        border-radius: 999px;
        background: color-mix(in oklch, var(--brand-navy) 12%, var(--surface));
        overflow: hidden;
    }

    .wl-level-fill {
        height: 100%;
        border-radius: 999px;
        background: var(--brand-navy);
        transition: width 240ms ease;
    }

    @media (max-width: 700px) {
        .wl-level-bars { grid-template-columns: 1fr; gap: 12px; }
    }
</style>
