<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Change Doc. No.</title>
<style>
*,*::before,*::after{box-sizing:border-box}html,body{margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#f3f5f8;color:#172033;font-size:13px}
.page-header{background:#fff;border-bottom:1px solid #dbe2ea;padding:14px 20px;display:flex;align-items:center;gap:12px}
.badge{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#c9a84c,#8d6a22);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
.title{font-size:20px;font-weight:700}.subtitle{color:#667085;font-size:12px;margin-top:3px}
.page{max-width:1240px;padding:24px 20px 32px}
.panel{background:#fff;border:1px solid #dbe2ea;border-radius:18px;padding:24px;box-shadow:0 8px 22px rgba(15,23,42,.04)}
.toolbar{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px}
.note{color:#667085;line-height:1.5;max-width:760px}
.toggle{display:flex;align-items:center;gap:8px;font-weight:700;color:#173a63}
.sections{display:grid;gap:16px}
.section{border:1px solid #dbe2ea;border-radius:16px;background:#fbfcfd;padding:18px}
.section.highlight{border-color:#c9a84c;background:#fffdf5}
.section h3{margin:0 0 6px;font-size:15px}
.section.highlight h3{color:#7a5c0a;display:flex;align-items:center;gap:8px}
.section.highlight h3::before{content:'✏️';font-size:16px}
.section p{margin:0 0 14px;color:#667085;line-height:1.45}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px}
.field label{display:block;font-weight:700;margin-bottom:6px}
.field input{width:100%;height:40px;border:1px solid #cfd8e3;border-radius:10px;padding:0 12px;background:#fff}
.field input[readonly]{background:#f7f8fa;color:#667085}
/* prefix table */
.pfx-table{width:100%;border-collapse:collapse}
.pfx-table th{text-align:left;font-size:11px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:#8d6a22;padding:6px 10px;border-bottom:2px solid #e8d9a0;background:#fdf8ec}
.pfx-table td{padding:5px 8px;border-bottom:1px solid #f0e8cc;vertical-align:middle}
.pfx-table tr:last-child td{border-bottom:none}
.pfx-table tr:hover td{background:#fffbef}
.pfx-grp{display:inline-block;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;background:#e8d9a0;color:#7a5c0a;white-space:nowrap}
.pfx-input{height:34px;border:1px solid #cfd8e3;border-radius:8px;padding:0 10px;background:#fff;width:100%;font-size:13px}
.pfx-input:focus{outline:none;border-color:#c9a84c;box-shadow:0 0 0 2px rgba(201,168,76,.2)}
.len-input{width:60px;height:34px;border:1px solid #cfd8e3;border-radius:8px;padding:0 8px;text-align:center;font-size:13px}
.len-input:focus{outline:none;border-color:#c9a84c;box-shadow:0 0 0 2px rgba(201,168,76,.2)}
.actions{display:flex;justify-content:flex-end;gap:12px;margin-top:18px;flex-wrap:wrap}
.btn{appearance:none;border:none;border-radius:12px;padding:12px 16px;font:inherit;font-weight:700;cursor:pointer}
.btn.primary{background:#173a63;color:#fff}.btn.secondary{background:#fff;color:#173a63;border:1px solid #c8d4e0}
.status{margin-top:16px;display:none;padding:12px 14px;border-radius:10px}
.status.show{display:block}.status.ok{background:#ecfdf3;border:1px solid #abefc6;color:#067647}.status.err{background:#fef3f2;border:1px solid #fecdca;color:#b42318}
@media (max-width:720px){.page{padding:16px 14px 24px}}
</style>
</head>
<body>
<div class="page-header">
    <div class="badge">C</div>
    <div>
        <div class="title">Change Doc. No.</div>
    </div>
</div>

<main class="page">
    <form class="panel" id="changeDocNoForm">
        @csrf
        <div class="toolbar">
            <label class="toggle">
                <input type="checkbox" id="unlockSerial">
                Enable Serial No. edit
            </label>
        </div>

        <div class="sections">
            @foreach($sections as $section)
                <section class="section{{ !empty($section['highlight']) ? ' highlight' : '' }}">
                    <h3>{{ $section['title'] }}</h3>

                    @if(!empty($section['highlight']))
                        {{-- Prefix section: grouped table layout --}}
                        @php
                            $groups = [];
                            foreach ($section['fields'] as $f) {
                                $g = $f['group'] ?? 'Other';
                                $groups[$g][] = $f;
                            }
                        @endphp
                        <table class="pfx-table">
                            <thead>
                                <tr>
                                    <th style="width:120px">Group</th>
                                    <th>Document Type</th>
                                    <th style="width:180px">Prefix</th>
                                    <th style="width:90px;text-align:center">Length</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($groups as $grpName => $pairs)
                                    @for($pi = 0; $pi < count($pairs); $pi += 2)
                                        @php $pf = $pairs[$pi]; $lf = $pairs[$pi+1] ?? null; @endphp
                                        <tr>
                                            @if($pi === 0)
                                                <td rowspan="{{ intdiv(count($pairs)+1,2) }}">
                                                    <span class="pfx-grp">{{ $grpName }}</span>
                                                </td>
                                            @endif
                                            <td style="font-weight:600">{{ preg_replace('/ Prefix$| Length$/', '', $pf['label']) }}</td>
                                            <td>
                                                <input class="pfx-input" id="{{ $pf['input'] }}" name="{{ $pf['input'] }}"
                                                    type="text" value="{{ $pf['value'] }}" placeholder="{{ $pf['default'] ?? '' }}">
                                            </td>
                                            <td style="text-align:center">
                                                @if($lf)
                                                    <input class="len-input" id="{{ $lf['input'] }}" name="{{ $lf['input'] }}"
                                                        type="number" min="1" max="10" value="{{ $lf['value'] }}">
                                                @endif
                                            </td>
                                        </tr>
                                    @endfor
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        {{-- Regular sections: grid layout --}}
                        <div class="grid">
                            @foreach($section['fields'] as $field)
                                <div class="field">
                                    <label for="{{ $field['input'] }}">{{ $field['label'] }}</label>
                                    <input
                                        id="{{ $field['input'] }}"
                                        name="{{ $field['input'] }}"
                                        type="{{ ($field['type'] ?? 'int') === 'int' ? 'number' : 'text' }}"
                                        value="{{ $field['value'] }}"
                                        @if(($field['type'] ?? 'int') === 'int') min="0" @endif
                                        @if(!empty($field['readonly'])) data-locked="1" readonly @endif
                                    >
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        <div class="actions">
            <button class="btn primary" type="submit" id="doneBtn">Done</button>
            <button class="btn secondary" type="button" id="exitBtn">Exit</button>
        </div>

        <div class="status" id="statusBox"></div>
    </form>
</main>

<script>
const form = document.getElementById('changeDocNoForm');
const statusBox = document.getElementById('statusBox');
const unlockSerial = document.getElementById('unlockSerial');
const lockedInputs = Array.from(document.querySelectorAll('[data-locked="1"]'));

function showStatus(message, isError) {
    statusBox.textContent = message;
    statusBox.className = 'status show ' + (isError ? 'err' : 'ok');
}

unlockSerial.addEventListener('change', function () {
    lockedInputs.forEach(function (input) {
        input.readOnly = !unlockSerial.checked;
    });
});

form.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!confirm('You are going to change document numbers. It may affect your data. Please confirm.')) return;

    const btn = document.getElementById('doneBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    statusBox.className = 'status';

    try {
        const payload = new FormData(form);
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const res = await fetch('{{ url('/api/administration/change-docno') }}', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: payload
        });
        const data = await res.json();
        showStatus(data.message || (data.ok ? 'Done' : 'Failed'), !data.ok);
    } catch (err) {
        showStatus('Change Doc. No. request failed.', true);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Done';
    }
});

document.getElementById('exitBtn').addEventListener('click', function () {
    if (window.parent && window.parent !== window) {
        window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        return;
    }
    window.location.href = '{{ url('/administration') }}';
});
</script>
</body>
</html>
