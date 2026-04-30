<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Other Item Help</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            background: #c0c0c0;
            padding: 10px;
        }
        h2 { text-align: center; margin-bottom: 10px; }
        #searchBox {
            width: 100%;
            padding: 6px 8px;
            margin-bottom: 8px;
            font-size: 10pt;
            border: 2px inset #ccc;
        }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th {
            background: #808080;
            color: #fff;
            padding: 4px 8px;
            text-align: left;
            position: sticky;
            top: 0;
        }
        td {
            padding: 4px 8px;
            border-bottom: 1px solid #ddd;
            cursor: pointer;
        }
        tr.selected td { background: #0a246a; color: #fff; }
        tr:hover td { background: #316ac5; color: #fff; }
        .table-wrapper {
            max-height: 450px;
            overflow-y: auto;
            border: 2px inset #ccc;
        }
        #searchDisplay {
            margin-top: 6px;
            padding: 4px;
            min-height: 20px;
            color: #333;
            font-weight: bold;
        }
        .btn-row { text-align: center; margin-top: 10px; }
        .btn-row button {
            padding: 6px 20px;
            margin: 0 5px;
            font-size: 10pt;
            cursor: pointer;
        }
    </style>
</head>
<body>
<h2>Other Item Help</h2>
<input type="text" id="searchBox" placeholder="Type to search by code or name..."
       value="{{ $initialSearch }}" autofocus>

<div class="table-wrapper">
    <table id="itemTable">
        <thead>
        <tr>
            <th>Code</th>
            <th>Name</th>
        </tr>
        </thead>
        <tbody>
        @foreach($items as $row)
            <tr data-code="{{ $row->code }}" data-name="{{ $row->name }}">
                <td>{{ $row->code }}</td>
                <td>{{ $row->name }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div id="searchDisplay">{{ $initialSearch }}</div>

<div class="btn-row">
    <button id="btnSelect">Select (Enter)</button>
    <button id="btnCancel">Cancel (Esc)</button>
</div>

<script>
(function () {
    const rows = Array.from(document.querySelectorAll('#itemTable tbody tr'));
    const searchBox = document.getElementById('searchBox');
    let selectedIdx = -1;

    function filterRows() {
        const term = searchBox.value.trim().toUpperCase();
        let firstMatch = -1;
        rows.forEach((tr, i) => {
            const code = String(tr.dataset.code || '').toUpperCase();
            const name = String(tr.dataset.name || '').toUpperCase();
            const show = !term || code.startsWith(term) || name.startsWith(term);
            tr.style.display = show ? '' : 'none';
            if (show && firstMatch === -1) firstMatch = i;
        });
        if (firstMatch >= 0) selectRow(firstMatch);
        document.getElementById('searchDisplay').textContent = searchBox.value;
    }

    function selectRow(idx) {
        rows.forEach(r => r.classList.remove('selected'));
        if (idx >= 0 && idx < rows.length && rows[idx].style.display !== 'none') {
            rows[idx].classList.add('selected');
            rows[idx].scrollIntoView({ block: 'nearest' });
            selectedIdx = idx;
        }
    }

    function returnCode(code) {
        if (window.opener && typeof window.opener.receiveHelpCode === 'function') {
            window.opener.receiveHelpCode(code);
        }
        window.close();
    }

    searchBox.addEventListener('input', filterRows);
    searchBox.addEventListener('keydown', function (e) {
        const visible = rows.filter(r => r.style.display !== 'none');
        if (!visible.length) {
            if (e.key === 'Escape') returnCode('');
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const cur = visible.indexOf(rows[selectedIdx]);
            const next = (cur + 1) % visible.length;
            selectRow(rows.indexOf(visible[next]));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const cur = visible.indexOf(rows[selectedIdx]);
            const prev = (cur - 1 + visible.length) % visible.length;
            selectRow(rows.indexOf(visible[prev]));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIdx >= 0) returnCode(rows[selectedIdx].dataset.code.trim());
        } else if (e.key === 'Escape') {
            returnCode('');
        }
    });

    rows.forEach((tr, i) => {
        tr.addEventListener('click', () => selectRow(i));
        tr.addEventListener('dblclick', () => returnCode(tr.dataset.code.trim()));
    });

    document.getElementById('btnSelect').addEventListener('click', () => {
        if (selectedIdx >= 0) returnCode(rows[selectedIdx].dataset.code.trim());
    });
    document.getElementById('btnCancel').addEventListener('click', () => returnCode(''));

    filterRows();
})();
</script>
</body>
</html>

