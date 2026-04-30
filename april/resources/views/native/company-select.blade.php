<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Company Select</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root{--bg:#f5f7fb;--surface:#fff;--surface2:#f8fafc;--line:#dbe4ee;--line2:#c9d6e4;--ink:#152235;--ink2:#41576d;--ink3:#6e8194;--teal:#0f8b8d;--teal2:#15b9a6;--teal-pale:#e8fffb;--gold:#d89d1b;--gold-pale:#fff5d8;--red:#c2410c;--red-pale:#fff2ec;--shadow:0 18px 46px rgba(15,23,42,.08)}
        *{box-sizing:border-box}
        body{margin:0;background:linear-gradient(180deg,#f8fbff 0%,var(--bg) 100%);font-family:'Segoe UI',Tahoma,Verdana,sans-serif;color:var(--ink);padding:18px}
        .wrap{max-width:980px;margin:0 auto}
        .panel{background:var(--surface);border:1px solid var(--line);border-radius:22px;box-shadow:var(--shadow);overflow:hidden}
        .hidden{display:none !important}
        .panel-head{padding:20px 22px;border-bottom:1px solid var(--line);display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
        .eyebrow{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:var(--teal-pale);color:var(--teal);font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
        h1{margin:12px 0 6px;font-size:30px;line-height:1.08}
        .sub{margin:0;color:var(--ink3);font-size:14px;max-width:620px}
        .head-actions{display:flex;align-items:center;gap:10px}
        .icon-btn,.action-btn,.switch-btn{border:none;cursor:pointer;font-weight:700}
        .icon-btn{width:42px;height:42px;border-radius:14px;background:var(--surface2);border:1px solid var(--line);color:var(--ink2);display:flex;align-items:center;justify-content:center;font-size:15px}
        .icon-btn:hover{background:var(--teal-pale);color:var(--teal);border-color:#afe7df}
        .icon-btn.danger:hover{background:var(--red-pale);color:var(--red);border-color:#fdba74}
        .body{padding:20px 22px 24px}
        .notice{padding:12px 14px;border-radius:14px;font-size:13px;margin-bottom:14px}
        .notice.error{background:#fff0f3;border:1px solid #ffc5d2;color:#9f1239}
        .notice.success{background:#ecfdf5;border:1px solid #bbf7d0;color:#166534}
        .active-row{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:18px}
        .chip{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;border:1px solid var(--line);background:var(--surface2);font-size:13px;color:var(--ink2)}
        .chip strong{color:var(--ink)}
        .chip.active{background:var(--gold-pale);border-color:#f2d485;color:#8a5a00}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:14px}
        .card{border:1px solid var(--line);background:var(--surface);border-radius:18px;padding:16px;display:flex;flex-direction:column;gap:12px;min-height:190px}
        .card.current{border-color:#a8e4dd;box-shadow:0 12px 28px rgba(21,185,166,.12)}
        .card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
        .company-name{font-size:22px;font-weight:800;line-height:1.12}
        .db-pill{display:inline-flex;align-items:center;padding:5px 10px;border-radius:999px;background:var(--surface2);border:1px solid var(--line);font-family:Consolas,'Courier New',monospace;font-size:12px;color:var(--ink3);margin-top:8px}
        .tag{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:var(--teal-pale);color:var(--teal);font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
        .desc{font-size:13px;color:var(--ink2);line-height:1.55}
        .card-actions{margin-top:auto;display:flex;align-items:center;justify-content:space-between;gap:10px}
        .meta{font-size:12px;color:var(--ink3)}
        .action-btn{height:38px;padding:0 14px;border-radius:12px;background:var(--surface2);border:1px solid var(--line);color:var(--ink2)}
        .switch-btn{height:40px;padding:0 16px;border-radius:12px;background:linear-gradient(135deg,var(--teal),var(--teal2));color:#fff;box-shadow:0 10px 24px rgba(15,139,141,.22)}
        .switch-btn[disabled],.action-btn[disabled]{cursor:default;opacity:.65;box-shadow:none}
        .modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.42);display:none;align-items:center;justify-content:center;padding:16px;z-index:4000}
        .modal-backdrop.open{display:flex}
        .modal{width:min(460px,100%);background:#fff;border-radius:22px;border:1px solid var(--line);box-shadow:0 28px 70px rgba(15,23,42,.20);overflow:hidden}
        .modal-head{padding:18px 20px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:10px}
        .modal-title{font-size:20px;font-weight:800}
        .modal-close{width:36px;height:36px;border-radius:12px;border:1px solid var(--line);background:var(--surface2);color:var(--ink2);cursor:pointer}
        .modal-body{padding:20px}
        .field{margin-bottom:14px}
        .field label{display:block;font-size:12px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:var(--ink3);margin-bottom:7px}
        .field input{width:100%;height:44px;border-radius:12px;border:1px solid var(--line2);padding:0 14px;font-size:14px;color:var(--ink);background:#fff}
        .field input:focus{outline:none;border-color:#87d9cf;box-shadow:0 0 0 4px rgba(21,185,166,.10)}
        .modal-note{font-size:12px;color:var(--ink3);line-height:1.5;margin-bottom:12px}
        .modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px}
        .switch-login-shell{width:min(430px,100%)}
        .switch-login-card{background:#fff;border:1px solid #d9e3ec;border-radius:20px;box-shadow:0 28px 72px rgba(15,23,42,.22);overflow:hidden}
        .switch-login-top{height:4px;background:linear-gradient(90deg,var(--teal),var(--teal2))}
        .switch-login-body{padding:22px}
        .switch-login-title{font-size:24px;line-height:1.1;font-weight:800;color:var(--ink);margin:0 0 8px}
        .switch-login-sub{margin:0 0 16px;color:var(--ink3);font-size:14px;line-height:1.55}
        .switch-login-error{display:none;padding:10px 12px;border-radius:12px;background:#fff0f3;border:1px solid #ffc5d2;color:#9f1239;font-size:13px;margin-bottom:14px}
        .switch-login-error.show{display:block}
        .switch-field{margin-bottom:14px}
        .switch-field label{display:block;font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--ink2);margin-bottom:8px}
        .switch-company-chip{height:44px;display:flex;align-items:center;padding:0 14px;border-radius:14px;border:1px solid var(--line);background:var(--surface2);font-weight:700;color:var(--ink)}
        @media (max-width:640px){body{padding:10px}.panel-head,.body{padding:16px}h1{font-size:26px}.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap" id="companySelectContent">
    <section class="panel">
        <div class="panel-head">
            <div>
                <div class="eyebrow"><i class="fas fa-building"></i> Company Select</div>
                <h1>Company List</h1>
                <p class="sub">Use this list to switch company database. Click the pen icon to edit or create company entries. Edit password is <strong>PRO</strong>. Switching will take you back to login and the same username/password logic will be checked in the selected company database.</p>
            </div>
            <div class="head-actions">
                <button type="button" class="icon-btn" title="Add company" id="addCompanyBtn"><i class="fas fa-pen"></i></button>
            </div>
        </div>

        <div class="body">
            @if(session('company_select_error'))
                <div class="notice error">{{ session('company_select_error') }}</div>
            @endif
            @if(session('company_select_success'))
                <div class="notice success">{{ session('company_select_success') }}</div>
            @endif

            <div class="active-row">
                <div class="chip active"><i class="fas fa-star"></i> Active company</div>
                <div class="chip"><strong>{{ $currentCompanyName !== '' ? $currentCompanyName : 'Current Company' }}</strong></div>
                <div class="chip">Database: <strong>{{ $currentDatabase }}</strong></div>
                <div class="chip">Default Secondary DB: <strong>{{ $dbstrf !== '' ? $dbstrf : 'Not set' }}</strong></div>
            </div>

            <div class="grid">
                @forelse($companies as $company)
                    <article class="card{{ $company['is_current'] ? ' current' : '' }}">
                        <div class="card-top">
                            <div>
                                <div class="company-name">{{ $company['company_name'] !== '' ? $company['company_name'] : $company['database'] }}</div>
                                <div class="db-pill">{{ $company['database'] }}</div>
                            </div>
                            <div style="display:flex;gap:8px;align-items:center">
                                <button
                                    type="button"
                                    class="icon-btn"
                                    title="Edit company"
                                    data-company-edit='@json(['company_name' => $company['company_name'], 'database' => $company['database']])'
                                >
                                    <i class="fas fa-pen"></i>
                                </button>
                                @if(!$company['is_current'])
                                <button
                                    type="button"
                                    class="icon-btn danger"
                                    title="Delete company"
                                    data-company-delete='@json(['company_name' => $company['company_name'], 'database' => $company['database']])'
                                >
                                    <i class="fas fa-trash"></i>
                                </button>
                                @endif
                            </div>
                        </div>

                        @if(!$company['is_current'])
                        <div class="desc">Switch this entry to make it the active company in the app.</div>
                        <div class="card-actions">
                            <div class="meta">{{ $dbstrf === $company['database'] ? 'Default secondary DB selected' : 'Click to set as default secondary DB' }}</div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                                <form method="POST" action="{{ url('/company-select/set-default') }}">
                                    @csrf
                                    <input type="hidden" name="database" value="{{ $company['database'] }}">
                                    <button type="submit" class="action-btn">{{ $dbstrf === $company['database'] ? 'Default Secondary DB' : 'Set Default Secondary DB' }}</button>
                                </form>
                                <form method="POST" action="{{ url('/company-select/switch') }}">
                                    @csrf
                                    <input type="hidden" name="database" value="{{ $company['database'] }}">
                                    <button type="submit" class="switch-btn">Switch Company</button>
                                </form>
                            </div>
                        </div>
                        @endif
                    </article>
                @empty
                    <article class="card">
                        <div class="company-name">No company entries</div>
                        <div class="desc">Click the pen icon above and create the first company using only company name and database name.</div>
                    </article>
                @endforelse
            </div>
        </div>
    </section>
</div>

<div class="modal-backdrop" id="switchBackdrop" onclick="closeSwitchModal(event)">
    <div class="switch-login-shell" onclick="event.stopPropagation()">
        <div class="switch-login-card">
            <div class="switch-login-top"></div>
            <div class="switch-login-body">
                <h2 class="switch-login-title">Company Login</h2>
                <p class="switch-login-sub">Enter the same login password. If it is valid in the selected company database, dashboard will switch to that database.</p>
                <div class="switch-login-error" id="switchLoginError"></div>
                <form method="POST" action="{{ url('/company-select/switch') }}">
                    @csrf
                    <input type="hidden" name="database" id="switchDatabaseInput" value="">
                    <div class="switch-field">
                        <label>Company</label>
                        <div class="switch-company-chip" id="switchCompanyName"></div>
                    </div>
                    <div class="switch-field">
                        <label for="switchPasswordInput">Password</label>
                        <input type="password" name="current_password" id="switchPasswordInput" autocomplete="off" placeholder="Enter password" required>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="action-btn" onclick="closeSwitchModal()">Cancel</button>
                        <button type="submit" class="switch-btn">Continue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="editorBackdrop" onclick="closeEditor(event)">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-head">
            <div class="modal-title" id="editorTitle">Edit Company</div>
            <button type="button" class="modal-close" onclick="closeEditor()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ url('/company-select/save') }}" id="companyEditorForm">
                @csrf
                <input type="hidden" name="original_database" id="originalDatabase" value="">

                <div id="passwordStep">
                    <div class="modal-note">Enter edit password to continue.</div>
                    <div class="field">
                        <label for="editPasswordInput">Password</label>
                        <input type="password" id="editPasswordInput" autocomplete="off" placeholder="Enter password">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="action-btn" onclick="closeEditor()">Cancel</button>
                        <button type="button" class="switch-btn" onclick="unlockEditor()">Continue</button>
                    </div>
                </div>

                <div id="formStep" class="hidden">
                    <div class="modal-note">Only these two values are needed: company name and database name.</div>
                    <input type="hidden" name="edit_password" id="editPasswordValue" value="">
                    <div class="field">
                        <label for="companyNameInput">Company Name</label>
                        <input type="text" name="company_name" id="companyNameInput" required placeholder="Enter company name" autocomplete="off">
                    </div>
                    <div class="field">
                        <label for="databaseInput">Database Name</label>
                        <input type="text" name="database" id="databaseInput" required placeholder="Enter database name" autocomplete="off">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="action-btn" onclick="closeEditor()">Cancel</button>
                        <button type="submit" class="switch-btn">Save Company</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="deleteBackdrop" onclick="closeDeleteModal(event)">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-head">
            <div class="modal-title">Delete Company</div>
            <button type="button" class="modal-close" onclick="closeDeleteModal()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ url('/company-select/delete') }}">
                @csrf
                <input type="hidden" name="database" id="deleteDatabaseInput" value="">
                <div class="modal-note">This removes the company entry from the selector list only. It does not delete the actual database.</div>
                <div class="field">
                    <label>Company</label>
                    <input type="text" id="deleteCompanyName" value="" readonly>
                </div>
                <div class="field">
                    <label for="deletePasswordInput">Edit Password</label>
                    <input type="password" name="edit_password" id="deletePasswordInput" autocomplete="off" placeholder="Enter password" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="action-btn" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="switch-btn" style="background:linear-gradient(135deg,#c2410c,#ea580c)">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '';
let editorSeed = { company_name: '', database: '' };
let deleteSeed = { company_name: '', database: '' };

function openSwitchModal(database, companyName) {
    document.getElementById('switchCompanyName').textContent = companyName || database || '';
    document.getElementById('switchDatabaseInput').value = database || '';
    document.getElementById('switchPasswordInput').value = '';
    document.getElementById('switchLoginError').classList.remove('show');
    document.getElementById('switchLoginError').textContent = '';
    document.getElementById('switchBackdrop').classList.add('open');
    setTimeout(function() {
        document.getElementById('switchPasswordInput').focus();
    }, 30);
}

function closeSwitchModal(evt) {
    if (evt && evt.target && evt.target.id !== 'switchBackdrop') return;
    document.getElementById('switchBackdrop').classList.remove('open');
}

function openEditor(seed) {
    editorSeed = seed || { company_name: '', database: '' };
    document.getElementById('editorTitle').textContent = editorSeed.database ? 'Edit Company' : 'Create Company';
    document.getElementById('editorBackdrop').classList.add('open');
    document.getElementById('passwordStep').classList.remove('hidden');
    document.getElementById('formStep').classList.add('hidden');
    document.getElementById('editPasswordInput').value = '';
    document.getElementById('editPasswordValue').value = '';
    document.getElementById('companyNameInput').value = editorSeed.company_name || '';
    document.getElementById('databaseInput').value = editorSeed.database || '';
    document.getElementById('originalDatabase').value = editorSeed.database || '';
    setTimeout(function() {
        document.getElementById('editPasswordInput').focus();
    }, 30);
}

function closeEditor(evt) {
    if (evt && evt.target && evt.target.id !== 'editorBackdrop') return;
    document.getElementById('editorBackdrop').classList.remove('open');
}

function openDeleteModal(seed) {
    deleteSeed = seed || { company_name: '', database: '' };
    document.getElementById('deleteCompanyName').value = deleteSeed.company_name || deleteSeed.database || '';
    document.getElementById('deleteDatabaseInput').value = deleteSeed.database || '';
    document.getElementById('deletePasswordInput').value = '';
    document.getElementById('deleteBackdrop').classList.add('open');
    setTimeout(function() {
        document.getElementById('deletePasswordInput').focus();
    }, 30);
}

function closeDeleteModal(evt) {
    if (evt && evt.target && evt.target.id !== 'deleteBackdrop') return;
    document.getElementById('deleteBackdrop').classList.remove('open');
}

function unlockEditor() {
    var password = document.getElementById('editPasswordInput').value || '';
    if (password !== 'PRO') {
        alert('Wrong password');
        document.getElementById('editPasswordInput').focus();
        return;
    }
    document.getElementById('editPasswordValue').value = password;
    document.getElementById('passwordStep').classList.add('hidden');
    document.getElementById('formStep').classList.remove('hidden');
    // Re-apply seed so browser autofill cannot override with stale values
    document.getElementById('companyNameInput').value = editorSeed.company_name || '';
    document.getElementById('databaseInput').value = editorSeed.database || '';
    setTimeout(function() {
        document.getElementById('companyNameInput').focus();
    }, 30);
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditor();
        closeSwitchModal();
        closeDeleteModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    var addBtn = document.getElementById('addCompanyBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function() {
            openEditor();
        });
    }

    document.querySelectorAll('[data-company-edit]').forEach(function(button) {
        button.addEventListener('click', function() {
            try {
                openEditor(JSON.parse(button.getAttribute('data-company-edit') || '{}'));
            } catch (_) {
                openEditor();
            }
        });
    });

    document.querySelectorAll('[data-company-delete]').forEach(function(button) {
        button.addEventListener('click', function() {
            try {
                openDeleteModal(JSON.parse(button.getAttribute('data-company-delete') || '{}'));
            } catch (_) {}
        });
    });
});

@if(session('company_select_error') && !empty($switchTargetDatabase))
window.addEventListener('load', function() {
    openSwitchModal(@json($switchTargetDatabase), @json($switchTargetCompany !== '' ? $switchTargetCompany : $switchTargetDatabase));
    var errorBox = document.getElementById('switchLoginError');
    if (errorBox) {
        errorBox.textContent = @json(session('company_select_error'));
        errorBox.classList.add('show');
    }
});
@endif
</script>
</body>
</html>
