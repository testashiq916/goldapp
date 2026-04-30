<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gold Rate Update</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #f2f4f8;
            --bg2: #e8ecf3;
            --panel: #ffffff;
            --panel2: #f8fafc;
            --line: #d9e1ec;
            --ink: #152033;
            --muted: #52627a;
            --accent: #0f5ea8;
            --accent2: #1c78cc;
            --ok: #1f9a58;
            --danger: #c43b3b;
            --shadow: 0 14px 34px rgba(18, 34, 57, 0.12);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 10% 15%, #eef3fb 0, transparent 44%),
                radial-gradient(circle at 85% 0%, #e9f2ff 0, transparent 34%),
                linear-gradient(180deg, var(--bg), var(--bg2));
            min-height: 100vh;
            padding: 22px;
        }

        .container {
            max-width: 980px;
            margin: 0 auto;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--panel);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .header {
            padding: 22px 26px;
            background: linear-gradient(120deg, #13365d, #275f94);
            border-bottom: 1px solid #315e8b;
            color: #eef5ff;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .header h1 {
            font-size: 25px;
            font-weight: 700;
            letter-spacing: 0.2px;
            margin-bottom: 4px;
        }

        .header h1 i { margin-right: 8px; color: #ffd477; }
        .header p { color: #d6e7ff; font-size: 13px; }

        .stamp {
            font-size: 11px;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 999px;
            padding: 6px 10px;
            color: #f3f8ff;
            background: rgba(7, 30, 56, 0.28);
        }

        .body {
            padding: 24px;
            background: linear-gradient(180deg, #fbfcff 0%, #f4f7fc 100%);
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--panel2);
            padding: 18px;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 700;
            color: #1d3a5b;
            margin-bottom: 14px;
        }

        .card-title i { color: var(--accent); }

        .alert {
            padding: 12px 14px;
            margin-bottom: 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            display: none;
        }
        .alert.show { display: block; }
        .alert.ok { background: #e8fbef; color: #125736; border: 1px solid #8be0b3; }
        .alert.err { background: #fff0f0; color: #7f1d1d; border: 1px solid #f4a3a3; }

        .no-perm {
            background: #fff8e9;
            border: 1px solid #f0cb73;
            border-radius: 10px;
            padding: 18px;
            text-align: center;
            margin-bottom: 16px;
        }
        .no-perm i { font-size: 40px; color: #c08d25; display: block; margin-bottom: 8px; }
        .no-perm h3 { margin-bottom: 6px; color: #764b00; font-size: 17px; }
        .no-perm p { font-size: 13px; color: #6f4e14; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 14px;
        }

        .form-row {
            margin-bottom: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #ffffff;
            padding: 10px 12px;
        }

        .form-row.hidden-row { display: none; }

        .form-row label {
            width: auto;
            padding-right: 0;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .form-row label i {
            margin-right: 6px;
            width: 14px;
            text-align: center;
            color: var(--accent);
        }

        .form-row input,
        .form-row select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #cfd9e6;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 700;
            color: #11243a;
            text-align: right;
            background: #fdfefe;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .form-row input:focus,
        .form-row select:focus {
            outline: none;
            border-color: var(--accent2);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(28,120,204,0.14);
        }

        .form-row input:disabled,
        .form-row select:disabled {
            background: #edf1f7;
            color: #75859a;
            cursor: not-allowed;
        }

        .btn-bar {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
            border-top: 1px dashed #c8d3e0;
            padding-top: 14px;
        }

        .btn {
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 700;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.16s, box-shadow 0.16s, opacity 0.16s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #fff;
        }
        .btn:hover:not(:disabled) { transform: translateY(-1px); }
        .btn:disabled { opacity: 0.55; cursor: not-allowed; }
        .btn-save { background: linear-gradient(120deg, #21915b, var(--ok)); box-shadow: 0 6px 16px rgba(31,154,88,0.25); }
        .btn-history { background: linear-gradient(120deg, #1861a9, #2c82d4); box-shadow: 0 6px 16px rgba(24,97,169,0.24); }
        .btn-exit { background: linear-gradient(120deg, #af3838, var(--danger)); box-shadow: 0 6px 16px rgba(196,59,59,0.22); }

        .shortcuts {
            margin-top: 12px;
            font-size: 12px;
            color: var(--muted);
            padding: 10px;
            border: 1px solid #d7e1ee;
            border-radius: 8px;
            background: #ffffff;
        }

        .shortcuts kbd {
            display: inline-block;
            border: 1px solid #c8d2df;
            border-bottom-width: 2px;
            border-radius: 5px;
            padding: 1px 7px;
            background: #f8fbff;
            color: #2b4868;
            font-family: Consolas, monospace;
            font-size: 11px;
            font-weight: 700;
            margin: 0 2px;
        }

        @media (max-width: 900px) {
            .form-grid { grid-template-columns: 1fr; }
            .btn-bar { justify-content: stretch; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-top">
            <div>
                <h1><i class="fas fa-coins"></i> Gold Rate Update</h1>
                <p>Classic modern panel for daily precious metal rate maintenance</p>
            </div>
            <div class="stamp"><i class="fas fa-calendar-day"></i> {{ now()->format('d M Y, h:i A') }}</div>
        </div>
    </div>

    <div class="body">
        @if(!$hasPermission)
        <div class="no-perm">
            <i class="fas fa-lock"></i>
            <h3>Access Denied</h3>
            <p>You do not have permission to update rates.</p>
            <p style="margin-top:10px;">Please contact your administrator to grant RATESETUP permission.</p>
        </div>
        @endif

        <div id="status" class="alert"></div>

        <div class="card">
            <div class="card-title"><i class="fas fa-chart-line"></i> Current Rates</div>

            <div class="form-grid">
                @foreach($rateCodes as $code => $info)
                    <div class="form-row {{ !empty($info['hidden']) ? 'hidden-row' : '' }}">
                        <label>
                            @if(!empty($info['icon']))<i class="fas {{ $info['icon'] }}"></i>@endif
                            {{ $info['label'] }}
                        </label>
                        @if(!empty($info['type']) && $info['type'] === 'select')
                            <select id="{{ strtolower($code) }}" {{ !$hasPermission ? 'disabled' : '' }}>
                                @foreach($info['options'] as $opt)
                                    <option value="{{ $opt }}" {{ $rates[$code] == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="number" id="{{ strtolower($code) }}"
                                   step="{{ $info['decimals'] == 3 ? '0.001' : '0.01' }}"
                                   value="{{ number_format($rates[$code], $info['decimals'], '.', '') }}"
                                   {{ !$hasPermission ? 'disabled' : '' }}>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="btn-bar">
                <button type="button" class="btn btn-save" id="btnSave" {{ !$hasPermission ? 'disabled' : '' }}>
                    <i class="fas fa-save"></i> Save (F9)
                </button>
                <button type="button" class="btn btn-history" id="btnHistory">
                    <i class="fas fa-history"></i> History
                </button>
                <button type="button" class="btn btn-exit" id="btnExit">
                    <i class="fas fa-times"></i> Exit (Esc)
                </button>
            </div>

            <div class="shortcuts">
                <i class="fas fa-keyboard"></i> Shortcuts:
                <kbd>F9</kbd> Save
                <kbd>Enter</kbd> Next Field
                <kbd>Esc</kbd> Exit
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const statusEl = document.getElementById('status');
    const inputs = Array.from(document.querySelectorAll('.form-row:not(.hidden-row) input, .form-row:not(.hidden-row) select'));
    const allInputs = document.querySelectorAll('.form-row input, .form-row select');

    function setStatus(msg, ok) {
        statusEl.textContent = msg;
        statusEl.className = 'alert show ' + (ok ? 'ok' : 'err');
        if (ok) {
            setTimeout(() => {
                statusEl.style.opacity = '0';
                statusEl.style.transition = 'opacity 0.5s ease';
                setTimeout(() => { statusEl.className = 'alert'; statusEl.style.opacity = ''; }, 500);
            }, 5000);
        }
    }

    // Auto-select on focus
    inputs.forEach(inp => inp.addEventListener('focus', () => {
        if (inp.type === 'number') inp.select();
    }));

    // Enter moves to next visible field
    inputs.forEach((inp, i) => {
        inp.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (i < inputs.length - 1) {
                    inputs[i + 1].focus();
                    if (inputs[i + 1].type === 'number') inputs[i + 1].select();
                } else {
                    save();
                }
            }
        });
    });

    // Focus first visible input
    if (inputs.length > 0) inputs[0].focus();

    document.getElementById('btnSave').addEventListener('click', save);
    document.getElementById('btnHistory').addEventListener('click', () => {
        window.location.href = @json(url('/rate/history'));
    });
    document.getElementById('btnExit').addEventListener('click', exitPage);

    document.addEventListener('keydown', e => {
        if (e.key === 'F9') { e.preventDefault(); save(); }
        if (e.key === 'Escape') { exitPage(); }
    });

    function exitPage() {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:rate-exit-refresh' }, '*');
        } else {
            window.location.href = @json(url('/dashboard')) + '?refresh=' + Date.now();
        }
    }

    async function save() {
        const body = new URLSearchParams();
        allInputs.forEach(inp => body.append(inp.id, inp.value));

        try {
            const res = await fetch(@json(url('/api/rate/save')), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf },
                body
            });
            const data = await res.json();
            setStatus(data.message || data.error, !!data.success);
            if (data.success && window.parent && window.parent !== window) {
                try {
                    if (typeof window.parent.goldappRefreshRates === 'function') {
                        window.parent.goldappRefreshRates();
                    }
                } catch (e) {}
                window.parent.postMessage({ type: 'goldapp:rate-updated' }, '*');
            }
        } catch (err) {
            setStatus('Network error: ' + err.message, false);
        }
    }
})();
</script>
</body>
</html>
