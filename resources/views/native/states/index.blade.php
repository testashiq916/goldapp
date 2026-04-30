<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>State/Emirates Master</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #f0f0f0; padding: 20px; }
        .container { max-width: 1545px; margin: 0 auto; background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,.1); padding: 20px; }
        h1 { text-align: center; color: #333; margin-bottom: 20px; font-size: 24px; }
        .table-container { overflow-y: auto; max-height: 600px; border: 1px solid #ddd; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        thead { position: sticky; top: 0; background-color: #4CAF50; color: white; z-index: 10; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        tbody tr:hover { background-color: #f5f5f5; }
        tbody tr.selected { background-color: #e3f2fd; }
        input[type="text"] { width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-family: Arial, sans-serif; }
        input[type="text"]:focus { outline: none; border-color: #4CAF50; box-shadow: 0 0 5px rgba(76,175,80,.3); }
        .button-container { background-color: #c0c0c0; padding: 15px; border-radius: 21px; text-align: center; border: 1px solid #0000ff; }
        button { padding: 10px 20px; margin: 0 5px; font-size: 14px; font-weight: bold; font-family: Arial, sans-serif; border: 1px solid #666; border-radius: 4px; cursor: pointer; transition: all .2s; }
        button:hover { opacity: .9; transform: translateY(-1px); }
        .btn-add { background-color: #2196F3; color: white; }
        .btn-delete { background-color: #f44336; color: white; }
        .btn-cancel { background-color: #ff9800; color: white; }
        .btn-save { background-color: #4CAF50; color: white; }
        .btn-exit { background-color: #607D8B; color: white; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; display: none; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .empty-row td { background-color: #fffacd; }
        .keyboard-hint { font-size: 12px; color: #666; text-align: center; margin-top: 10px; }
        .loading { text-align: center; padding: 20px; color: #666; }
    </style>
</head>
<body>
<div class="container">
    <h1>State/Emirates Master</h1>
    <div id="message" class="message"></div>
    <div class="table-container">
        <table id="statesTable">
            <thead><tr><th style="width: 30%">Code</th><th style="width: 70%">Name</th></tr></thead>
            <tbody id="tableBody"><tr><td colspan="2" class="loading">Loading data...</td></tr></tbody>
        </table>
    </div>
    <div class="button-container">
        <button class="btn-add" onclick="addRow()"><u>A</u>dd</button>
        <button class="btn-delete" onclick="deleteRow()"><u>D</u>elete</button>
        <button class="btn-cancel" onclick="cancelChanges()"><u>C</u>ancel</button>
        <button class="btn-save" onclick="saveChanges()"><u>S</u>ave</button>
        <button class="btn-exit" onclick="exitWindow()">E<u>x</u>it</button>
    </div>
    <div class="keyboard-hint">Alt+A Add | Alt+D Delete | Alt+C Cancel | Alt+S Save | Alt+X Exit | F9 Save | Enter Next field</div>
</div>

<script>
let statesData = [];
let selectedRow = null;
const API_URL = @json(url('/api/states-adding'));
const DASHBOARD_URL = @json(url('/dashboard'));

document.addEventListener('DOMContentLoaded', function () {
    loadStates();
    setupKeyboardShortcuts();
});

function loadStates() {
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: 'action=retrieve'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            statesData = data.data || [];
            renderTable();
        } else {
            showMessage('Error loading data: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(err => showMessage('Error: ' + err.message, 'error'));
}

function renderTable() {
    const tbody = document.getElementById('tableBody');
    tbody.innerHTML = '';
    if (statesData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;color:#999;">No data available. Click Add to create new record.</td></tr>';
        return;
    }
    statesData.forEach((state, index) => tbody.appendChild(createTableRow(state, index)));
}

function createTableRow(state, index) {
    const row = document.createElement('tr');
    row.dataset.index = index;
    if (!state.code && !state.name) row.classList.add('empty-row');
    row.innerHTML = `
        <td><input type="text" value="${escapeHtml(state.code || '')}" data-index="${index}" onchange="updateData(${index}, 'code', this.value)" onkeydown="handleKeyNavigation(event, ${index}, 0)" maxlength="10"></td>
        <td><input type="text" value="${escapeHtml(state.name || '')}" data-index="${index}" onchange="updateData(${index}, 'name', this.value)" onkeydown="handleKeyNavigation(event, ${index}, 1)" maxlength="100"></td>
    `;
    row.addEventListener('click', function () { selectRow(row); });
    return row;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

function updateData(index, field, value) {
    if (!statesData[index]) statesData[index] = {};
    statesData[index][field] = (value || '').trim();
}

function selectRow(row) {
    document.querySelectorAll('tr.selected').forEach(r => r.classList.remove('selected'));
    row.classList.add('selected');
    selectedRow = parseInt(row.dataset.index, 10);
}

function addRow() {
    const lastIndex = statesData.length - 1;
    if (lastIndex >= 0) {
        const last = statesData[lastIndex] || {};
        if (!last.code || !last.code.trim()) {
            focusOnRow(lastIndex, 0);
            showMessage('Please complete the current row before adding a new one', 'warning');
            return;
        }
    }
    statesData.push({ code: '', name: '' });
    renderTable();
    setTimeout(() => focusOnRow(statesData.length - 1, 0), 80);
}

function deleteRow() {
    if (selectedRow === null) {
        showMessage('Please select a row to delete', 'warning');
        return;
    }
    const row = statesData[selectedRow] || {};
    const code = (row.code || '').trim();
    if (!code) {
        statesData.splice(selectedRow, 1);
        selectedRow = null;
        renderTable();
        return;
    }
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: 'action=check_usage&code=' + encodeURIComponent(code)
    })
    .then(r => r.json())
    .then(data => {
        const inUse = !!(data.in_use || (data.data && data.data.in_use));
        const count = Number(data.count || (data.data && data.data.count) || 0);
        if (inUse) {
            showMessage("You can't delete this entry. It is being used in " + count + " sales record(s).", 'warning');
            return;
        }
        if (!confirm('Are you sure you want to delete this state?')) return;
        return fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: 'action=delete&code=' + encodeURIComponent(code)
        })
        .then(r => r.json())
        .then(out => {
            if (!out || !out.success) {
                showMessage('Error deleting state: ' + ((out && out.message) || 'Unknown error'), 'error');
                return;
            }
            statesData.splice(selectedRow, 1);
            selectedRow = null;
            renderTable();
            showMessage(out.message || 'State deleted successfully', 'success');
        });
    })
    .catch(err => showMessage('Error checking state usage: ' + err.message, 'error'));
}

function saveChanges() {
    const validData = statesData.filter(s => (s.code || '').trim() !== '' && (s.name || '').trim() !== '');
    if (validData.length === 0) {
        showMessage('No data to save', 'warning');
        return;
    }
    showMessage('Saving...', 'success');
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: 'action=save&data=' + encodeURIComponent(JSON.stringify(validData))
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message || 'Saved', 'success');
            loadStates();
        } else {
            showMessage('Error: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(err => showMessage('Error: ' + err.message, 'error'));
}

function cancelChanges() {
    if (!confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) return;
    selectedRow = null;
    loadStates();
    showMessage('Changes cancelled', 'success');
}

function exitWindow() {
    if (!confirm('Are you sure you want to exit?')) return;
    window.close();
    setTimeout(() => { if (!window.closed) window.location.href = DASHBOARD_URL; }, 80);
}

function showMessage(message, type) {
    const box = document.getElementById('message');
    box.textContent = message;
    box.className = 'message ' + type;
    box.style.display = 'block';
    setTimeout(() => { box.style.display = 'none'; }, 5000);
}

function focusOnRow(rowIndex, colIndex) {
    const inputs = document.querySelectorAll('input[data-index="' + rowIndex + '"]');
    if (inputs[colIndex]) { inputs[colIndex].focus(); inputs[colIndex].select(); }
}

function handleKeyNavigation(event, rowIndex, colIndex) {
    if (event.key === 'Enter') {
        event.preventDefault();
        if (colIndex === 0) focusOnRow(rowIndex, 1);
        else if (rowIndex === statesData.length - 1) addRow();
        else focusOnRow(rowIndex + 1, 0);
    }
    if (event.altKey && event.key === 'ArrowLeft') { event.preventDefault(); if (colIndex > 0) focusOnRow(rowIndex, colIndex - 1); }
    if (event.altKey && event.key === 'ArrowRight') { event.preventDefault(); if (colIndex < 1) focusOnRow(rowIndex, colIndex + 1); }
    if (event.key === 'F9') { event.preventDefault(); saveChanges(); }
}

function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function (event) {
        if (event.altKey && event.key.toLowerCase() === 'a') { event.preventDefault(); addRow(); }
        if (event.altKey && event.key.toLowerCase() === 'd') { event.preventDefault(); deleteRow(); }
        if (event.altKey && event.key.toLowerCase() === 'c') { event.preventDefault(); cancelChanges(); }
        if (event.altKey && event.key.toLowerCase() === 's') { event.preventDefault(); saveChanges(); }
        if (event.altKey && event.key.toLowerCase() === 'x') { event.preventDefault(); exitWindow(); }
        if (event.key === 'F9') { event.preventDefault(); saveChanges(); }
    });
}
</script>
</body>
</html>
