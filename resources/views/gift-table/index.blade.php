<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Party Gift Table</title>
<style>
  :root {
    --bg:        #f4f1eb;
    --surface:   #ffffff;
    --accent:    #b8336a;
    --accent-lt: #f2d4e0;
    --text:      #2b2b2b;
    --text-dim:  #7a7a7a;
    --border:    #e0dcd4;
    --danger:    #d94444;
    --success:   #3a9e6e;
    --radius:    10px;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: "Segoe UI", Tahoma, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    padding: 40px 20px;
  }

  .container {
    width: 100%;
    max-width: 720px;
  }

  h1 {
    font-size: 2rem;
    margin-bottom: 6px;
    color: var(--accent);
    letter-spacing: -0.5px;
  }

  .subtitle {
    color: var(--text-dim);
    font-size: 0.9rem;
    margin-bottom: 28px;
  }

  .msg {
    padding: 12px 16px;
    border-radius: var(--radius);
    margin-bottom: 20px;
    font-size: 0.9rem;
    font-weight: 500;
  }
  .msg.error   { background: #fde8e8; color: var(--danger); border: 1px solid #f5c6c6; }
  .msg.success { background: #e4f5ec; color: var(--success); border: 1px solid #b8e6cb; }

  .table-wrap {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    overflow: hidden;
  }

  table {
    width: 100%;
    border-collapse: collapse;
  }

  thead th {
    background: var(--accent);
    color: #fff;
    text-align: left;
    padding: 14px 16px;
    font-weight: 700;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  thead th:first-child { width: 130px; text-align: right; padding-right: 20px; }
  thead th:last-child  { width: 52px; text-align: center; }

  tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
  tbody tr:hover { background: #faf8f5; }

  tbody td { padding: 4px 6px; vertical-align: middle; }
  tbody td:last-child { text-align: center; }

  input[type="number"],
  input[type="text"] {
    width: 100%;
    border: 1.5px solid transparent;
    border-radius: 6px;
    padding: 10px 12px;
    font-family: inherit;
    font-size: 0.95rem;
    background: transparent;
    transition: border-color .2s, background .2s;
  }
  input[type="number"] { text-align: right; }
  input:focus {
    outline: none;
    border-color: var(--accent);
    background: var(--accent-lt);
  }

  .btn-rm {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-dim);
    font-size: 1.2rem;
    line-height: 1;
    padding: 6px;
    border-radius: 6px;
    transition: color .2s, background .2s;
  }
  .btn-rm:hover { color: var(--danger); background: #fde8e8; }

  .empty-row td {
    padding: 32px;
    text-align: center;
    color: var(--text-dim);
    font-style: italic;
  }

  .actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    flex-wrap: wrap;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 11px 22px;
    border: none;
    border-radius: var(--radius);
    font-family: inherit;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform .12s, box-shadow .2s, background .2s;
  }
  .btn:active { transform: scale(.97); }

  .btn-primary {
    background: var(--accent);
    color: #fff;
    box-shadow: 0 3px 10px rgba(184,51,106,.25);
  }
  .btn-primary:hover { background: #a02d5d; }

  .btn-secondary {
    background: var(--surface);
    color: var(--text);
    border: 1.5px solid var(--border);
  }
  .btn-secondary:hover { background: #f5f3ef; }

  .btn-add {
    background: var(--accent-lt);
    color: var(--accent);
  }
  .btn-add:hover { background: #ebbecd; }

  .spacer { flex: 1; }

  .hint {
    margin-top: 16px;
    font-size: 0.78rem;
    color: var(--text-dim);
    text-align: center;
  }
  kbd {
    display: inline-block;
    padding: 2px 6px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--surface);
    font-family: inherit;
    font-size: 0.76rem;
  }
</style>
</head>
<body>
<div class="container">
  <h1>Party Gift Table</h1>
  <p class="subtitle">Manage gift point tiers and their descriptions.</p>

  @if ($errors->any())
    <div class="msg error">{{ $errors->first() }}</div>
  @endif
  @if (session('success'))
    <div class="msg success">{{ session('success') }}</div>
  @endif

  <form method="post" action="{{ route('gift-table.save') }}" id="giftForm">
    @csrf

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Points</th>
            <th>Particulars</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="tableBody">
          @if ($rows->isEmpty())
            <tr class="empty-row" id="emptyMsg">
              <td colspan="3">No entries yet - click <strong>Add</strong> to get started.</td>
            </tr>
          @else
            @foreach ($rows as $row)
              <tr class="data-row">
                <td>
                  <input type="number" name="points[]" value="{{ $row->points }}" placeholder="0" required>
                  <input type="hidden" name="orig_points[]" value="{{ $row->points }}">
                </td>
                <td>
                  <input type="text" name="particulars[]" maxlength="60" value="{{ $row->particulars ?? '' }}" placeholder="Description...">
                </td>
                <td>
                  <button type="button" class="btn-rm" title="Remove row" onclick="removeRow(this)">x</button>
                </td>
              </tr>
            @endforeach
          @endif
        </tbody>
      </table>
    </div>

    <div class="actions">
      <button type="button" class="btn btn-add" onclick="addRow()">+ Add</button>
      <button type="submit" class="btn btn-primary">Save</button>
      <button type="button" class="btn btn-secondary" onclick="location.reload()">Cancel</button>
      <span class="spacer"></span>
      <button type="button" class="btn btn-secondary" onclick="closeModule()">Exit</button>
    </div>
  </form>

  <p class="hint">
    <kbd>Enter</kbd> next field / add row
    <span>&nbsp;.&nbsp;</span>
    <kbd>Shift + Left/Right</kbd> move columns
    <span>&nbsp;.&nbsp;</span>
    <kbd>F9</kbd> save
  </p>
</div>

<script>
function addRow() {
  const tbody = document.getElementById('tableBody');
  const empty = document.getElementById('emptyMsg');
  if (empty) empty.remove();

  const tr = document.createElement('tr');
  tr.className = 'data-row';
  tr.innerHTML = `
    <td>
      <input type="number" name="points[]" placeholder="0" required>
      <input type="hidden" name="orig_points[]" value="">
    </td>
    <td>
      <input type="text" name="particulars[]" maxlength="60" placeholder="Description...">
    </td>
    <td>
      <button type="button" class="btn-rm" title="Remove row" onclick="removeRow(this)">x</button>
    </td>`;
  tbody.appendChild(tr);
  tr.querySelector('input[name="points[]"]').focus();
}

function removeRow(btn) {
  const row = btn.closest('tr');
  const origPts = row.querySelector('input[name="orig_points[]"]').value;

  if (origPts !== '' && !confirm('Delete this entry?')) return;
  row.remove();

  if (document.querySelectorAll('.data-row').length === 0) {
    const tbody = document.getElementById('tableBody');
    tbody.innerHTML = `<tr class="empty-row" id="emptyMsg">
      <td colspan="3">No entries yet - click <strong>Add</strong> to get started.</td>
    </tr>`;
  }
}

function closeModule() {
  if (window.parent && window.parent !== window) {
    window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
    return;
  }
  window.close();
  location.href = '/';
}

document.addEventListener('keydown', function(e) {
  const el = document.activeElement;
  if (!el || !el.closest('.data-row')) return;

  const row = el.closest('tr');
  const rows = [...document.querySelectorAll('.data-row')];
  const inputs = [...row.querySelectorAll('input:not([type=hidden])')];
  const colIdx = inputs.indexOf(el);

  if (e.key === 'Enter') {
    e.preventDefault();
    if (colIdx < inputs.length - 1) {
      inputs[colIdx + 1].focus();
    } else {
      const rowIdx = rows.indexOf(row);
      if (rowIdx < rows.length - 1) {
        rows[rowIdx + 1].querySelector('input').focus();
      } else {
        addRow();
      }
    }
  }

  if (e.shiftKey && e.key === 'ArrowLeft' && colIdx > 0) {
    e.preventDefault();
    inputs[colIdx - 1].focus();
  }
  if (e.shiftKey && e.key === 'ArrowRight' && colIdx < inputs.length - 1) {
    e.preventDefault();
    inputs[colIdx + 1].focus();
  }

  if (e.key === 'F9') {
    e.preventDefault();
    document.getElementById('giftForm').submit();
  }
});
</script>
</body>
</html>

