<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Master Print</title>
    @include('partials.print-layout-head')
    <style>
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            color: #1f2937;
            background: #eef3f8;
        }
        .page {
            max-width: 900px;
            margin: 20px auto;
            background: #fff;
            border: 1px solid #d7dfeb;
            border-radius: 12px;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .head {
            padding: 16px 20px;
            border-bottom: 1px solid #dbe4ef;
            background: linear-gradient(135deg, #f8fbff, #eef5fd);
        }
        .head h1 {
            margin: 0;
            font-size: 24px;
            color: #1f3f66;
        }
        .head p {
            margin: 6px 0 0;
            font-size: 12px;
            color: #60758d;
        }
        .content {
            padding: 20px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(220px, 1fr));
            gap: 12px;
        }
        .card {
            border: 1px solid #d8e2ef;
            border-radius: 10px;
            padding: 12px 14px;
            background: #fbfdff;
        }
        .card h3 {
            margin: 0 0 10px;
            font-size: 14px;
            color: #31577f;
        }
        .row {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 13px;
        }
        .row:last-child {
            margin-bottom: 0;
        }
        .label {
            font-weight: 700;
            color: #4a6786;
        }
        .value {
            color: #1f2937;
            word-break: break-word;
        }
        .notes {
            margin-top: 12px;
            border: 1px solid #d8e2ef;
            border-radius: 10px;
            padding: 12px 14px;
            background: #fbfdff;
        }
        .notes h3 {
            margin: 0 0 8px;
            font-size: 14px;
            color: #31577f;
        }
        .notes .text {
            min-height: 64px;
            white-space: pre-wrap;
            font-size: 13px;
        }
        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 14px 20px 20px;
        }
        button {
            height: 34px;
            border: 1px solid #2a6398;
            border-radius: 7px;
            background: #e8f2ff;
            color: #17456e;
            padding: 0 14px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        @media print {
            body {
                background: #fff;
            }
            .page {
                margin: 0;
                max-width: none;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }
            .actions {
                display: none;
            }
        }
    </style>
</head>
<body>
@php
    $opening = (float) ($row->opbal ?? 0);
    $openingType = $opening <= 0 ? 'Debit' : 'Credit';
    $cashBank = strtoupper(trim((string) ($row->actype2 ?? '')));
    $accountType = strtoupper(trim((string) ($row->actype1 ?? 'A')));
    $accountTypeLabel = match ($accountType) {
        'R' => 'Revenue',
        'E' => 'Expense',
        'L' => 'Liability',
        default => 'Asset',
    };
    $cashBankLabel = $cashBank === 'H' ? 'Cash Account' : ($cashBank === 'B' ? 'Bank Account' : '-');
@endphp

<div class="page">
    <div class="head">
        <h1>Account Master</h1>
        <p>Printable summary for account code {{ $accode }}</p>
    </div>

    <div class="content">
        <div class="grid">
            <div class="card">
                <h3>Account Details</h3>
                <div class="row"><div class="label">Account Code</div><div class="value">{{ $accode }}</div></div>
                <div class="row"><div class="label">Description</div><div class="value">{{ trim((string) ($row->name ?? '')) }}</div></div>
                <div class="row"><div class="label">Group</div><div class="value">{{ trim((string) ($row->grcode ?? '')) }}</div></div>
                <div class="row"><div class="label">BS Head</div><div class="value">{{ trim((string) ($row->bshead ?? '')) }}</div></div>
                <div class="row"><div class="label">Account Type</div><div class="value">{{ $accountTypeLabel }}</div></div>
                <div class="row"><div class="label">Cash/Bank</div><div class="value">{{ $cashBankLabel }}</div></div>
            </div>

            <div class="card">
                <h3>Configuration</h3>
                <div class="row"><div class="label">Opening Balance</div><div class="value">{{ number_format(abs($opening), 2) }} ({{ $openingType }})</div></div>
                <div class="row"><div class="label">Trading P&amp;L Pos</div><div class="value">{{ trim((string) ($row->tplpos ?? '0')) }}</div></div>
                <div class="row"><div class="label">BS Schedule Pos</div><div class="value">{{ trim((string) ($row->shepos ?? '0')) }}</div></div>
                <div class="row"><div class="label">Schedule Group</div><div class="value">{{ trim((string) ($row->shedgrp ?? '')) }}</div></div>
                <div class="row"><div class="label">Display</div><div class="value">{{ (int) ($row->control ?? 1) !== 2 ? 'Yes' : 'No' }}</div></div>
                <div class="row"><div class="label">Blocked</div><div class="value">{{ strtoupper((string) ($row->blocked ?? 'N')) === 'Y' ? 'Yes' : 'No' }}</div></div>
            </div>
        </div>

        <div class="notes">
            <h3>Notes</h3>
            <div class="text">{{ trim((string) ($row->note ?? '')) !== '' ? trim((string) ($row->note ?? '')) : '-' }}</div>
        </div>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
        <button type="button" onclick="window.close()">Close</button>
    </div>
</div>

<script>
    setTimeout(() => {
        try {
            window.focus();
            window.print();
        } catch (_) {}
    }, 250);
</script>
</body>
</html>
