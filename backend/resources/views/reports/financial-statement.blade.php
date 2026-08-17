<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $reportTitle }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 9.5pt;
        color: #1f2937;
        background: #ffffff;
    }

    /* ── Page shell ─────────────────────────────────────────── */
    .page {
        width: 100%;
        padding: 0;
    }

    /* ── Header ─────────────────────────────────────────────── */
    .header {
        background: #1e3a5f;
        color: #ffffff;
        padding: 22px 32px 18px;
    }
    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .business-name {
        font-size: 14pt;
        font-weight: bold;
        letter-spacing: 0.3px;
        color: #ffffff;
    }
    .report-label {
        font-size: 8pt;
        color: #93c5fd;
        margin-top: 2px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .header-meta {
        text-align: right;
        font-size: 8pt;
        color: #bfdbfe;
        line-height: 1.6;
    }

    /* Accent rule below header */
    .header-accent {
        height: 4px;
        background: linear-gradient(to right, #3b82f6, #6366f1);
    }

    /* ── Report title band ───────────────────────────────────── */
    .title-band {
        background: #f0f7ff;
        border-bottom: 1px solid #bfdbfe;
        padding: 12px 32px;
    }
    .report-title {
        font-size: 13pt;
        font-weight: bold;
        color: #1e3a5f;
    }
    .report-period {
        font-size: 8.5pt;
        color: #4b5563;
        margin-top: 3px;
    }

    /* ── Body ────────────────────────────────────────────────── */
    .body {
        padding: 20px 32px 24px;
    }

    /* ── Section headings ────────────────────────────────────── */
    .section-heading {
        font-size: 7.5pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #6b7280;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 4px;
        margin-top: 18px;
        margin-bottom: 6px;
    }
    .section-heading:first-child {
        margin-top: 0;
    }

    /* ── Tables ──────────────────────────────────────────────── */
    table {
        width: 100%;
        border-collapse: collapse;
    }
    table th {
        font-size: 8pt;
        font-weight: bold;
        color: #374151;
        background: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
        padding: 7px 10px;
        text-align: left;
    }
    table th.num { text-align: right; }
    table td {
        padding: 6px 10px;
        border-bottom: 1px solid #f3f4f6;
        font-size: 9pt;
        color: #374151;
    }
    table td.num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    table td.code {
        font-family: "Courier New", monospace;
        font-size: 8pt;
        color: #6b7280;
        width: 60px;
    }
    table tr:last-child td { border-bottom: none; }
    table tr.subtotal td {
        font-weight: bold;
        background: #f9fafb;
        border-top: 1px solid #d1d5db;
        border-bottom: 1px solid #d1d5db;
    }
    table tr.grand-total td {
        font-weight: bold;
        font-size: 10pt;
        background: #1e3a5f;
        color: #ffffff;
        border: none;
    }
    table tr.grand-total td.positive { color: #86efac; }
    table tr.grand-total td.negative { color: #fca5a5; }

    /* ── Row styles for P&L / Cash Flow ─────────────────────── */
    .row {
        display: table;
        width: 100%;
        border-bottom: 1px solid #f3f4f6;
    }
    .row-label {
        display: table-cell;
        padding: 5px 0;
        color: #374151;
    }
    .row-label.indent { padding-left: 16px; }
    .row-value {
        display: table-cell;
        text-align: right;
        padding: 5px 0;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        color: #374151;
    }
    .row-value.negative { color: #dc2626; }
    .row-bold .row-label,
    .row-bold .row-value { font-weight: bold; color: #111827; }

    /* ── Balance Sheet two-column ────────────────────────────── */
    .bs-grid {
        display: table;
        width: 100%;
    }
    .bs-col {
        display: table-cell;
        width: 49%;
        vertical-align: top;
        padding-right: 16px;
    }
    .bs-col:last-child { padding-right: 0; padding-left: 16px; }

    /* ── Summary box ─────────────────────────────────────────── */
    .summary-box {
        background: #1e3a5f;
        color: #ffffff;
        border-radius: 4px;
        padding: 12px 16px;
        margin-top: 20px;
        display: table;
        width: 100%;
    }
    .summary-box .sb-label {
        display: table-cell;
        font-size: 10pt;
        font-weight: bold;
    }
    .summary-box .sb-value {
        display: table-cell;
        font-size: 11pt;
        font-weight: bold;
        text-align: right;
        white-space: nowrap;
    }
    .summary-box.positive .sb-value { color: #86efac; }
    .summary-box.negative .sb-value { color: #fca5a5; }

    /* ── Alert banner ────────────────────────────────────────── */
    .alert {
        background: #fef9c3;
        border: 1px solid #fde047;
        border-radius: 3px;
        padding: 8px 12px;
        font-size: 8.5pt;
        color: #713f12;
        margin-bottom: 14px;
    }

    /* ── Footer ──────────────────────────────────────────────── */
    .footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #f9fafb;
        border-top: 1px solid #e5e7eb;
        padding: 6px 32px;
        font-size: 7.5pt;
        color: #9ca3af;
        display: table;
        width: 100%;
    }
    .footer-left { display: table-cell; }
    .footer-right { display: table-cell; text-align: right; }
</style>
</head>
<body>
<div class="page">

    {{-- ── Header ── --}}
    <div class="header">
        <div class="header-top">
            <div>
                <div class="business-name">{{ $businessName }}</div>
                <div class="report-label">Financial Report</div>
            </div>
            <div class="header-meta">
                Generated: {{ $generatedAt }}<br>
                {{ $period }}
            </div>
        </div>
    </div>
    <div class="header-accent"></div>

    {{-- ── Title band ── --}}
    <div class="title-band">
        <div class="report-title">{{ $reportTitle }}</div>
        <div class="report-period">{{ $period }}</div>
    </div>

    {{-- ── Body ── --}}
    <div class="body">

        @if ($type === 'trial_balance')
            @php $r = $report; @endphp

            @if (!$r['is_balanced'])
                <div class="alert">
                    Warning: Total debits do not equal total credits. The ledger may have incomplete entries.
                </div>
            @endif

            <table>
                <thead>
                    <tr>
                        <th style="width:60px">Code</th>
                        <th>Account</th>
                        <th class="num">Debit</th>
                        <th class="num">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($r['lines'] as $line)
                        @php
                            $account = is_array($line['account']) ? $line['account'] : $line['account']->toArray();
                            $debit  = (float) $line['debit'];
                            $credit = (float) $line['credit'];
                        @endphp
                        <tr>
                            <td class="code">{{ $account['code'] ?? '' }}</td>
                            <td>{{ $account['name'] }}</td>
                            <td class="num">{{ $debit  > 0 ? number_format($debit,  2) : '' }}</td>
                            <td class="num">{{ $credit > 0 ? number_format($credit, 2) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center;color:#6b7280;padding:16px">No activity to report.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="grand-total">
                        <td colspan="2">Totals</td>
                        <td class="num">{{ number_format((float) $r['total_debit'], 2) }}</td>
                        <td class="num">{{ number_format((float) $r['total_credit'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>

            @php
                $balanced = $r['is_balanced'];
            @endphp
            <div class="summary-box {{ $balanced ? 'positive' : 'negative' }}" style="margin-top:14px">
                <div class="sb-label">Balance Check</div>
                <div class="sb-value">{{ $balanced ? 'BALANCED' : 'OUT OF BALANCE' }}</div>
            </div>
        @endif


        @if ($type === 'profit_and_loss')
            @php
                $r = $report;
                $netProfit = (float) $r['net_profit'];
            @endphp

            <div class="section-heading">Revenue</div>
            <table>
                <tbody>
                    @forelse ($r['revenue_accounts'] as $row)
                        @php $acc = is_array($row['account']) ? $row['account'] : $row['account']->toArray(); @endphp
                        <tr>
                            <td class="code" style="width:60px">{{ $acc['code'] ?? '' }}</td>
                            <td>{{ $acc['name'] }}</td>
                            <td class="num">{{ number_format((float)$row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="color:#6b7280;padding:8px 10px">No revenue posted in this period.</td></tr>
                    @endforelse
                    <tr class="subtotal">
                        <td colspan="2">Total Revenue</td>
                        <td class="num">{{ number_format((float)$r['total_revenue'], 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="section-heading" style="margin-top:16px">Expenses</div>
            <table>
                <tbody>
                    @forelse ($r['expense_accounts'] as $row)
                        @php $acc = is_array($row['account']) ? $row['account'] : $row['account']->toArray(); @endphp
                        <tr>
                            <td class="code" style="width:60px">{{ $acc['code'] ?? '' }}</td>
                            <td>{{ $acc['name'] }}</td>
                            <td class="num">{{ number_format((float)$row['amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" style="color:#6b7280;padding:8px 10px">No expenses posted in this period.</td></tr>
                    @endforelse
                    <tr class="subtotal">
                        <td colspan="2">Total Expenses</td>
                        <td class="num">{{ number_format((float)$r['total_expenses'], 2) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="summary-box {{ $netProfit >= 0 ? 'positive' : 'negative' }}">
                <div class="sb-label">Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }}</div>
                <div class="sb-value">
                    @if ($netProfit < 0)({{ number_format(abs($netProfit), 2) }})@else{{ number_format($netProfit, 2) }}@endif
                </div>
            </div>
        @endif


        @if ($type === 'balance_sheet')
            @php
                $r = $report;
                $totalLiabEquity = (float)$r['total_liabilities'] + (float)$r['total_equity'];
            @endphp

            @if (!$r['is_balanced'])
                <div class="alert">
                    Warning: Assets do not equal Liabilities + Equity. The balance sheet is out of balance.
                </div>
            @endif

            <div class="bs-grid">
                {{-- Left: Assets --}}
                <div class="bs-col">
                    <div class="section-heading">Assets</div>
                    <table>
                        <tbody>
                            @forelse ($r['assets'] as $row)
                                @php $acc = is_array($row['account']) ? $row['account'] : $row['account']->toArray(); @endphp
                                <tr>
                                    <td>{{ $acc['name'] }}</td>
                                    <td class="num">{{ number_format((float)$row['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" style="color:#6b7280;padding:6px">No asset balances.</td></tr>
                            @endforelse
                            <tr class="subtotal">
                                <td>Total Assets</td>
                                <td class="num">{{ number_format((float)$r['total_assets'], 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Right: Liabilities + Equity --}}
                <div class="bs-col">
                    <div class="section-heading">Liabilities</div>
                    <table>
                        <tbody>
                            @forelse ($r['liabilities'] as $row)
                                @php $acc = is_array($row['account']) ? $row['account'] : $row['account']->toArray(); @endphp
                                <tr>
                                    <td>{{ $acc['name'] }}</td>
                                    <td class="num">{{ number_format((float)$row['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" style="color:#6b7280;padding:6px">No liability balances.</td></tr>
                            @endforelse
                            <tr class="subtotal">
                                <td>Total Liabilities</td>
                                <td class="num">{{ number_format((float)$r['total_liabilities'], 2) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="section-heading">Equity</div>
                    <table>
                        <tbody>
                            @forelse ($r['equity'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td class="num">{{ number_format((float)$row['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" style="color:#6b7280;padding:6px">No equity entries.</td></tr>
                            @endforelse
                            <tr class="subtotal">
                                <td>Total Equity</td>
                                <td class="num">{{ number_format((float)$r['total_equity'], 2) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <table style="margin-top:6px">
                        <tbody>
                            <tr class="grand-total">
                                <td>Total Liabilities + Equity</td>
                                <td class="num">{{ number_format($totalLiabEquity, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="summary-box {{ $r['is_balanced'] ? 'positive' : 'negative' }}" style="margin-top:16px">
                <div class="sb-label">Balance Check — Total Assets vs Liabilities + Equity</div>
                <div class="sb-value">{{ $r['is_balanced'] ? 'BALANCED' : 'OUT OF BALANCE' }}</div>
            </div>
        @endif


        @if ($type === 'cash_flow')
            @php
                $r = $report;
                $format = function(float $n): string {
                    if ($n < 0) return '(' . number_format(abs($n), 2) . ')';
                    return number_format($n, 2);
                };
            @endphp

            <div class="section-heading">Operating Activities</div>
            <table>
                <tbody>
                    <tr>
                        <td style="padding-left:16px">Net Income</td>
                        <td class="num">{{ $format((float)$r['net_income']) }}</td>
                    </tr>
                    <tr>
                        <td style="padding-left:16px;color:#6b7280;font-size:8.5pt">Changes in Working Capital</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td style="padding-left:28px">Accounts Receivable</td>
                        <td class="num">{{ $format((float)$r['changes_in_working_capital']['accounts_receivable']) }}</td>
                    </tr>
                    <tr>
                        <td style="padding-left:28px">Inventory</td>
                        <td class="num">{{ $format((float)$r['changes_in_working_capital']['inventory']) }}</td>
                    </tr>
                    <tr>
                        <td style="padding-left:28px">Accounts Payable</td>
                        <td class="num">{{ $format((float)$r['changes_in_working_capital']['accounts_payable']) }}</td>
                    </tr>
                    <tr class="subtotal">
                        <td>Net Cash from Operating Activities</td>
                        <td class="num">{{ $format((float)$r['net_cash_from_operating_activities']) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="section-heading">Investing &amp; Financing Activities</div>
            <table>
                <tbody>
                    <tr>
                        <td>Net Cash from Investing Activities</td>
                        <td class="num">{{ $format((float)$r['net_cash_from_investing_activities']) }}</td>
                    </tr>
                    <tr>
                        <td>Net Cash from Financing Activities</td>
                        <td class="num">{{ $format((float)$r['net_cash_from_financing_activities']) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="section-heading">Cash Position</div>
            <table>
                <tbody>
                    <tr>
                        <td>Cash &amp; Bank at Start of Period</td>
                        <td class="num">{{ $format((float)$r['cash_at_start']) }}</td>
                    </tr>
                    <tr>
                        <td>Net Change in Cash</td>
                        <td class="num">{{ $format((float)$r['net_change_in_cash']) }}</td>
                    </tr>
                    <tr class="subtotal">
                        <td>Cash &amp; Bank at End of Period</td>
                        <td class="num">{{ $format((float)$r['cash_at_end']) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="summary-box {{ (float)$r['net_change_in_cash'] >= 0 ? 'positive' : 'negative' }}">
                <div class="sb-label">Net Change in Cash</div>
                <div class="sb-value">{{ $format((float)$r['net_change_in_cash']) }}</div>
            </div>
        @endif

    </div>{{-- /body --}}

    {{-- ── Footer ── --}}
    <div class="footer">
        <div class="footer-left">{{ $businessName }} &mdash; {{ $reportTitle }} &mdash; Confidential</div>
        <div class="footer-right">Generated {{ $generatedAt }}</div>
    </div>

</div>
</body>
</html>
