<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock Ledger</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 1300px; margin: 18px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .filters { display: grid; grid-template-columns: repeat(8, minmax(120px, 1fr)); gap: 10px; margin-bottom: 12px; }
        .field label { display: block; font-size: 12px; font-weight: 700; color: #3b5d86; margin-bottom: 4px; }
        .field input, .field select { width: 100%; height: 34px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 10px; font-size: 12px; }
        .actions { display: flex; gap: 8px; align-items: end; flex-wrap: wrap; }
        button, .btn {
            height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e;
            padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center;
        }
        .btn-green { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
        .opts { display: flex; gap: 16px; margin: 10px 0 12px; font-size: 12px; color: #395980; }
        .msg { margin: 8px 0 12px; padding: 9px 11px; border-radius: 7px; font-size: 12px; border: 1px solid #ead59b; background: #fff8e1; color: #6b5120; }
        .table-wrap { max-height: 68vh; overflow: auto; border: 1px solid #d8e2ef; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e3eaf4; padding: 6px; font-size: 12px; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2d4f74; z-index: 2; text-align: left; }
        td.num, th.num { text-align: right; }
        tr.parent { background: #f0f7ff; font-weight: 700; }
        tr.child td.name-cell { padding-left: 20px; color: #3b556f; }
        tr.item { background: #fff; }
        tfoot td { background: #f3f7fd; font-weight: 700; color: #24486d; position: sticky; bottom: 0; }
        .muted { color: #627389; font-size: 11px; }
        @media (max-width: 1200px) { .filters { grid-template-columns: repeat(2, minmax(120px, 1fr)); } }
    </style>
<link rel="stylesheet" href="{{ asset('css/report-readable.css') }}?v={{ @filemtime(public_path('css/report-readable.css')) }}">
<script src="{{ asset('js/report-row-navigation.js') }}?v={{ @filemtime(public_path('js/report-row-navigation.js')) }}" defer></script>
</head>
<body>
<div class="wrap">
    <h1>Stock Ledger</h1>

    @if($message !== '')
        <div class="msg">{{ $message }}</div>
    @endif

    <form method="get" action="{{ url('/stock/ledger') }}">
        <div class="filters">
            <div class="field">
                <label for="basis">Report Basis</label>
                <select id="basis" name="basis">
                    <option value="item" {{ $filters['basis'] === 'item' ? 'selected' : '' }}>Item Based</option>
                    <option value="group" {{ $filters['basis'] === 'group' ? 'selected' : '' }}>Group Based</option>
                    <option value="subgroup" {{ $filters['basis'] === 'subgroup' ? 'selected' : '' }}>Subgroup Based</option>
                </select>
            </div>
            <div class="field">
                <label for="stktype">Stock Type</label>
                <select id="stktype" name="stktype">
                    <option value="">All</option>
                    @foreach($stockTypes as $code)
                        <option value="{{ $code }}" {{ $filters['stktype'] === $code ? 'selected' : '' }}>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="grpcode">Group Code</label>
                <select id="grpcode" name="grpcode">
                    <option value="">All</option>
                    @foreach($groupCodes as $code)
                        <option value="{{ $code }}" {{ $filters['grpcode'] === $code ? 'selected' : '' }}>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="itype">Item Type</label>
                <select id="itype" name="itype">
                    <option value="All" {{ $filters['itype'] === 'All' ? 'selected' : '' }}>All</option>
                    <option value="Gold Only" {{ $filters['itype'] === 'Gold Only' ? 'selected' : '' }}>Gold Only</option>
                    <option value="Silver Only" {{ $filters['itype'] === 'Silver Only' ? 'selected' : '' }}>Silver Only</option>
                    <option value="Other Only" {{ $filters['itype'] === 'Other Only' ? 'selected' : '' }}>Other Only</option>
                </select>
            </div>
            <div class="field">
                <label for="ornament_filter">Ornament</label>
                <select id="ornament_filter" name="ornament_filter">
                    @foreach(['All','Ornaments Only','Not Ornaments'] as $opt)
                        <option value="{{ $opt }}" {{ ($filters['ornament_filter'] ?? 'All') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="stock_view">View</label>
                <select id="stock_view" name="stock_view">
                    <option value="opening" {{ $filters['stock_view'] === 'opening' ? 'selected' : '' }}>Op.Stock</option>
                    <option value="closing" {{ $filters['stock_view'] === 'closing' ? 'selected' : '' }}>Cl.Stock</option>
                </select>
            </div>
            <div class="field">
                <label for="level">Level</label>
                <select id="level" name="level">
                    <option value="1" {{ (int)$filters['level'] === 1 ? 'selected' : '' }}>1</option>
                    <option value="2" {{ (int)$filters['level'] !== 1 ? 'selected' : '' }}>B</option>
                </select>
            </div>
            <div class="field">
                <label for="search">Search</label>
                <input id="search" name="search" maxlength="40" value="{{ $filters['search'] }}">
            </div>
            <div class="actions">
                <button type="submit">Show</button>
                <a class="btn btn-green"
                   href="{{ url('/stock/ledger/export?' . http_build_query(request()->query())) }}">Export CSV</a>
            </div>
        </div>

        <div class="opts">
            <input type="hidden" name="include_child" value="0">
            <label><input type="checkbox" name="include_child" value="1" {{ $filters['include_child'] ? 'checked' : '' }}> Include Child Rows</label>
            <span class="muted">Rows: {{ count($rows) }}</span>
        </div>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th style="width: 90px;">Code</th>
                <th>Name</th>
                <th style="width: 90px;">Group</th>
                <th style="width: 95px;">SubGroup</th>
                <th class="num" style="width: 90px;">Qty</th>
                <th class="num" style="width: 90px;">Weight</th>
                <th class="num" style="width: 90px;">Stone Wgt</th>
                <th class="num" style="width: 90px;">Dmd Wgt</th>
                <th class="num" style="width: 90px;">Net Wgt</th>
            </tr>
            </thead>
            <tbody>
            @php $tQty=0; $tW=0; $tS=0; $tD=0; $tN=0; @endphp
            @forelse($rows as $row)
                @php
                    if (($row['row_type'] ?? '') !== 'child') {
                        $tQty += (float) $row['qty'];
                        $tW += (float) $row['weight'];
                        $tS += (float) $row['stonewgt'];
                        $tD += (float) $row['dmdwgt'];
                        $tN += (float) $row['netwgt'];
                    }
                    $cls = $row['row_type'] === 'parent' ? 'parent' : ($row['row_type'] === 'child' ? 'child' : 'item');
                @endphp
                <tr class="{{ $cls }}">
                    <td>{{ $row['code'] }}</td>
                    <td class="name-cell">{{ $row['name'] }}</td>
                    <td>{{ $row['grpcode'] }}</td>
                    <td>{{ $row['subgrpcode'] }}</td>
                    <td class="num">{{ number_format((float)$row['qty'], 3) }}</td>
                    <td class="num">{{ number_format((float)$row['weight'], 3) }}</td>
                    <td class="num">{{ number_format((float)$row['stonewgt'], 3) }}</td>
                    <td class="num">{{ number_format((float)$row['dmdwgt'], 3) }}</td>
                    <td class="num">{{ number_format((float)$row['netwgt'], 3) }}</td>
                </tr>
            @empty
                <tr><td colspan="9">No records found.</td></tr>
            @endforelse
            </tbody>
            <tfoot>
            <tr>
                <td colspan="4">Totals</td>
                <td class="num">{{ number_format($tQty, 3) }}</td>
                <td class="num">{{ number_format($tW, 3) }}</td>
                <td class="num">{{ number_format($tS, 3) }}</td>
                <td class="num">{{ number_format($tD, 3) }}</td>
                <td class="num">{{ number_format($tN, 3) }}</td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>
</body>
</html>
