<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Item Group Details</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #eef2f7; color: #1b2c3d; }
        .wrap { max-width: 1180px; margin: 16px auto; background: #fff; border: 1px solid #d3ddeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 12px; font-size: 24px; color: #173e67; }
        .status { padding: 9px 11px; border-radius: 8px; margin-bottom: 10px; font-size: 13px; }
        .status.ok { border: 1px solid #9fd3af; background: #e8f8ed; color: #12532a; }
        .status.err { border: 1px solid #f0b2b2; background: #fdeaea; color: #8e2020; }
        .modes { display: flex; gap: 8px; margin-bottom: 12px; }
        .modes a { text-decoration: none; padding: 7px 12px; border: 1px solid #c2d1e4; border-radius: 7px; color: #1b4c79; font-size: 12px; font-weight: 700; background: #f5f9ff; }
        .modes a.active { border-color: #2470b0; background: #dfefff; color: #0f3e67; }
        .layout { display: grid; grid-template-columns: 420px 1fr; gap: 14px; }
        .panel { border: 1px solid #d8e2ef; border-radius: 9px; overflow: hidden; }
        .panel-head { padding: 10px 12px; border-bottom: 1px solid #e0e8f3; background: #f3f8ff; font-size: 13px; font-weight: 700; color: #274a6d; }
        .panel-body { padding: 12px; }
        .search-row { display: flex; gap: 8px; margin-bottom: 10px; }
        input[type=text], select { border: 1px solid #bfd0e4; border-radius: 6px; height: 34px; padding: 0 10px; font-size: 13px; width: 100%; }
        input:focus, select:focus { outline: none; border-color: #3d7fb9; box-shadow: 0 0 0 2px rgba(61, 127, 185, 0.12); }
        button { border: 1px solid #2a679f; border-radius: 7px; background: #eaf3ff; color: #1b4f7d; font-weight: 700; font-size: 12px; height: 34px; padding: 0 12px; cursor: pointer; }
        button.primary { border-color: #2f7d47; background: #e6f8ec; color: #18552f; }
        button.warn { border-color: #ad3b3b; background: #fdeeee; color: #902424; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border-bottom: 1px solid #e4ebf5; padding: 7px; text-align: left; }
        th { position: sticky; top: 0; background: #edf4fc; color: #2b4f73; }
        tbody tr { cursor: pointer; }
        tbody tr:hover { background: #f5faff; }
        tbody tr.active { background: #e8f2ff; }
        .list-wrap { max-height: 56vh; overflow: auto; border: 1px solid #d8e2ef; border-radius: 7px; }
        .grid { display: grid; grid-template-columns: 130px 1fr; gap: 10px 12px; align-items: center; max-width: 640px; }
        .grid label { font-size: 12px; font-weight: 700; color: #35597d; }
        .checkline { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #35597d; }
        .toolbar { margin-top: 14px; display: flex; gap: 8px; flex-wrap: wrap; }
        @media (max-width: 980px) {
            .layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Item Group Details</h1>

    @if($tableMissing)
        <div class="status err">`itemgrp` table not found. Please create this table before using Groups module.</div>
    @endif
    @if(session('success'))
        <div class="status ok">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="status err">{{ session('error') }}</div>
    @endif

    <div class="modes">
        <a href="{{ url('/groups?mode=A') }}" class="{{ $mode === 'A' ? 'active' : '' }}">Add</a>
        <a href="{{ url('/groups?mode=E') }}" class="{{ $mode === 'E' ? 'active' : '' }}">Edit</a>
        <a href="{{ url('/groups?mode=D') }}" class="{{ $mode === 'D' ? 'active' : '' }}">Delete</a>
    </div>

    <div class="layout">
        <section class="panel">
            <div class="panel-head">Select Group</div>
            <div class="panel-body">
                <form class="search-row" method="GET" action="{{ url('/groups') }}">
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search by code or name">
                    <button type="submit">Search</button>
                </form>

                <div class="list-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th style="width: 110px;">Code</th>
                            <th>Name</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($groups as $g)
                            @php $isActive = strtoupper((string) $g->code) === $selectedCode; @endphp
                            <tr class="{{ $isActive ? 'active' : '' }}"
                                onclick="window.location='{{ url('/groups?mode=' . urlencode($mode) . '&code=' . urlencode((string) $g->code) . ($search !== '' ? '&search=' . urlencode($search) : '')) }}'">
                                <td>{{ $g->code }}</td>
                                <td>{{ $g->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2">No groups found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">Group Form ({{ $mode === 'A' ? 'Add' : ($mode === 'E' ? 'Edit' : 'Delete') }})</div>
            <div class="panel-body">
                <form method="POST" action="{{ url('/groups/save') }}" id="groupForm">
                    @csrf
                    <input type="hidden" name="mode" value="{{ $mode }}">

                    <div class="grid">
                        <label for="code">Group Code</label>
                        <input id="code" type="text" name="code" maxlength="10"
                               value="{{ old('code', $groupData->code ?? '') }}"
                               {{ $mode !== 'A' ? 'readonly' : '' }} required>

                        <label for="name">Name</label>
                        <input id="name" type="text" name="name" maxlength="50"
                               value="{{ old('name', $groupData->name ?? '') }}"
                               {{ $mode === 'D' ? 'readonly' : '' }} required>

                        <label for="mname">Malayalam</label>
                        <input id="mname" type="text" name="mname" maxlength="100"
                               value="{{ old('mname', $groupData->mname ?? '') }}"
                               {{ $mode === 'D' ? 'readonly' : '' }}>

                        <label for="position">Position</label>
                        <input id="position" type="text" name="position"
                               value="{{ old('position', $groupData->pos ?? 1) }}"
                               {{ $mode === 'D' ? 'readonly' : '' }}>

                        <label for="type">Item Type</label>
                        <select id="type" name="type" {{ $mode === 'D' ? 'disabled' : '' }}>
                            @php $itype = old('type', $groupData->itype ?? 'G'); @endphp
                            <option value="G" {{ $itype === 'G' ? 'selected' : '' }}>Gold</option>
                            <option value="S" {{ $itype === 'S' ? 'selected' : '' }}>Silver</option>
                            <option value="P" {{ $itype === 'P' ? 'selected' : '' }}>Platinum</option>
                            <option value="O" {{ $itype === 'O' ? 'selected' : '' }}>Others</option>
                        </select>
                    </div>

                    <div style="margin-top: 10px; display: flex; gap: 20px; flex-wrap: wrap;">
                        @php $orn = old('ornament', (($groupData->orn ?? 'N') === 'Y') ? '1' : ''); @endphp
                        @php $show = old('show_in_stock', (($groupData->showinstkrep ?? 'Y') === 'Y') ? '1' : ''); @endphp
                        <label class="checkline">
                            <input type="checkbox" name="ornament" value="1" {{ $orn ? 'checked' : '' }} {{ $mode === 'D' ? 'disabled' : '' }}>
                            Ornament?
                        </label>
                        <label class="checkline">
                            <input type="checkbox" name="show_in_stock" value="1" {{ $show ? 'checked' : '' }} {{ $mode === 'D' ? 'disabled' : '' }}>
                            Show in Stock Reports
                        </label>
                    </div>

                    <div class="toolbar">
                        @if($mode !== 'D')
                            <button type="submit" class="primary">Save</button>
                        @else
                            <button type="submit" class="warn" onclick="return confirm('Delete this group?')">Delete</button>
                        @endif
                        <button type="button" onclick="window.location='{{ url('/groups?mode=' . urlencode($mode)) }}'">Cancel</button>
                        <button type="button" onclick="closeModule()">Exit</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<script>
function closeModule() {
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
    } else {
        window.close();
    }
}
</script>
</body>
</html>

