<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $isEdit ? 'Edit' : 'Add' }} {{ $typeInfo['title'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: #e0e0e0;
            padding: 18px;
            font-family: Tahoma, "Segoe UI", sans-serif;
            font-size: 13px;
            color: #000;
        }

        .container {
            max-width: 980px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #a0a0a0;
            box-shadow: 2px 2px 5px rgba(0,0,0,0.25);
        }

        .header {
            background: linear-gradient(to bottom, #e8e8e8, #d0d0d0);
            color: #000;
            padding: 10px 15px;
            border-bottom: 1px solid #a0a0a0;
        }

        .header h1 {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }

        .header h2 {
            font-size: 14px;
            font-weight: normal;
            margin: 2px 0 0 0;
        }

        .form-body {
            padding: 16px 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 30px;
        }

        .form-group {
            display: flex;
            align-items: center;
        }

        .form-group label.field-label {
            width: 120px;
            min-width: 120px;
            font-weight: bold;
            font-size: 12px;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="email"] {
            flex: 1;
            padding: 4px 8px;
            border: 1px solid #a0a0a0;
            height: 24px;
            font-size: 12px;
            font-family: Tahoma, "Segoe UI", sans-serif;
        }

        .form-group select {
            flex: 1;
            padding: 3px 8px;
            height: 24px;
            font-size: 12px;
            border: 1px solid #a0a0a0;
            font-family: Tahoma, "Segoe UI", sans-serif;
        }

        .small-input {
            width: 86px !important;
            min-width: 86px !important;
            flex: 0 0 86px !important;
        }

        .radio-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 8px;
            flex: 1;
        }

        .radio-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .radio-item input[type="radio"],
        .checkbox-item input[type="checkbox"] {
            width: 14px;
            height: 14px;
            margin: 0;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .amount-display {
            width: 80px;
            background: #f0f0f0;
            border: 1px solid #a0a0a0;
            height: 24px;
            padding: 4px 8px;
            font-size: 12px;
            display: flex;
            align-items: center;
        }

        .inline-combo {
            display: flex;
            align-items: center;
            flex: 1;
            gap: 8px;
        }

        .inline-combo .small-input {
            flex: 0 0 86px !important;
        }

        .inline-combo select {
            flex: 1;
        }

        .multi-field {
            display: flex;
            align-items: center;
            flex: 1;
            gap: 6px;
        }

        .multi-field label {
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .multi-field input[type="number"],
        .multi-field input[type="text"] {
            width: 70px;
            flex: 0 0 auto;
        }

        .multi-field select {
            flex: 0 0 auto;
            width: 70px;
        }

        .status-box {
            margin: 0 20px 12px;
            padding: 10px;
            display: none;
            font-size: 12px;
        }

        .status-box.success {
            border: 1px solid #9bb89d;
            background: #edf9ed;
            color: #215a21;
        }

        .status-box.error {
            border: 1px solid #c89c9c;
            background: #fff1f1;
            color: #851f1f;
        }

        .btn-bar {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            padding: 12px 16px;
            background: #f0f0f0;
            border-top: 1px solid #a0a0a0;
        }

        .btn {
            background: linear-gradient(to bottom, #f8f8f8, #e0e0e0);
            border: 1px solid #808080;
            padding: 6px 20px;
            font-size: 12px;
            font-weight: bold;
            font-family: Tahoma, "Segoe UI", sans-serif;
            cursor: pointer;
            text-decoration: none;
            color: #000;
        }

        .btn:hover {
            background: linear-gradient(to bottom, #ffffff, #e8e8e8);
        }

        .btn-photo {
            flex: 0 0 auto;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: bold;
            background: linear-gradient(to bottom, #f8f8f8, #e0e0e0);
            border: 1px solid #808080;
            cursor: pointer;
        }

        a.btn {
            display: inline-block;
            text-align: center;
            line-height: normal;
        }

        .delete-link {
            color: #851f1f;
            font-size: 12px;
            font-weight: bold;
            text-decoration: underline;
            cursor: pointer;
            margin-right: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>{{ $isEdit ? 'Edit' : 'Add' }} {{ $typeInfo['title'] }}</h1>
        <h2>Depositor Account (Type D)</h2>
    </div>

    <div id="statusBox" class="status-box"></div>

    <form id="depositorForm" class="form-body" autocomplete="off">
        {{-- Hidden Fields --}}
        <input type="hidden" name="type" value="D">
        <input type="hidden" name="route" value="{{ $data['route'] ?? '' }}">
        <input type="hidden" name="carea" value="{{ $data['carea'] ?? '' }}">
        <input type="hidden" name="religion" value="{{ $data['religion'] ?? '' }}">
        <input type="hidden" name="smcode" value="{{ $data['smcode'] ?? '' }}">
        <input type="file" name="photo" id="photoInput" accept="image/*" style="display:none;">

        <div class="form-grid">
            {{-- ==================== LEFT COLUMN ==================== --}}

            {{-- Code --}}
            <div class="form-group">
                <label class="field-label">Code</label>
                <input type="text" name="code" value="{{ $data['code'] ?? '' }}" {{ $isEdit ? 'readonly' : '' }} required>
            </div>

            {{-- PAN/Adhaar (RIGHT) --}}
            <div class="form-group">
                <label class="field-label">PAN/Adhaar</label>
                <input type="text" name="panadhar" value="{{ $data['panadhar'] ?? '' }}">
            </div>

            {{-- Name --}}
            <div class="form-group">
                <label class="field-label">Name</label>
                <input type="text" name="name" value="{{ $data['name'] ?? '' }}" required>
            </div>

            {{-- Select Photo (RIGHT) --}}
            <div class="form-group">
                <label class="field-label">Photo</label>
                <button type="button" class="btn-photo" id="btnSelectPhoto">Select Photo</button>
            </div>

            {{-- City/Loc --}}
            <div class="form-group">
                <label class="field-label">City/Loc</label>
                <input type="text" name="city" value="{{ $data['city'] ?? '' }}">
            </div>

            {{-- MC Rate / Wastage / Weight Amt (RIGHT) --}}
            <div class="form-group">
                <label class="field-label">MC Rate/Gm</label>
                <div class="multi-field">
                    <input type="number" id="mcRate" name="mcrate" step="0.01" value="{{ $data['mcrate'] ?? '' }}" style="width:65px;">
                    <label>Wastage %</label>
                    <input type="number" id="wastagePercent" name="wastage" step="0.001" value="{{ $data['wastage'] ?? '' }}" style="width:65px;">
                    <label>Weight Amt</label>
                    <div class="amount-display" id="weightAmt">0.00</div>
                </div>
            </div>

            {{-- Op. Amt Bal --}}
            @php
                $opbalance = isset($data['opbalance']) ? (float)$data['opbalance'] : 0;
                $balType = $opbalance >= 0 ? 'credit' : 'debit';
            @endphp
            <div class="form-group">
                <label class="field-label">Op. Amt Bal</label>
                <input type="number" step="0.01" name="opbalance" class="small-input" value="{{ number_format(abs($opbalance), 2, '.', '') }}">
                <div class="radio-group">
                    <div class="radio-item">
                        <input type="radio" name="balance_type" id="bal_credit" value="credit" {{ $balType === 'credit' ? 'checked' : '' }}>
                        <label for="bal_credit">To receive</label>
                    </div>
                    <div class="radio-item">
                        <input type="radio" name="balance_type" id="bal_debit" value="debit" {{ $balType === 'debit' ? 'checked' : '' }}>
                        <label for="bal_debit">To give</label>
                    </div>
                </div>
            </div>

            {{-- A/c Group (RIGHT) --}}
            <div class="form-group">
                <label class="field-label">A/c Group</label>
                <select name="grp">
                    @foreach(($lookups['accountGroups'] ?? []) as $g)
                        @php $gCode = strtoupper(trim((string)($g['code'] ?? ''))); @endphp
                        <option value="{{ $gCode }}" {{ strtoupper((string)($data['grp'] ?? 'DEP')) === $gCode ? 'selected' : '' }}>{{ ($g['name'] ?? '') }} ({{ $gCode }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Op. Weight Bal (radios REVERSED from amount) --}}
            @php
                $opweight = isset($data['opweight']) ? (float)$data['opweight'] : 0;
                $wgtType = $opweight >= 0 ? 'credit' : 'debit';
            @endphp
            <div class="form-group">
                <label class="field-label">Op. Weight Bal</label>
                <input type="number" step="0.001" name="opweight" class="small-input" value="{{ number_format(abs($opweight), 3, '.', '') }}">
                <div class="radio-group">
                    <div class="radio-item">
                        <input type="radio" name="weight_type" id="wgt_debit" value="debit" {{ $wgtType === 'debit' ? 'checked' : '' }}>
                        <label for="wgt_debit">To receive</label>
                    </div>
                    <div class="radio-item">
                        <input type="radio" name="weight_type" id="wgt_credit" value="credit" {{ $wgtType === 'credit' ? 'checked' : '' }}>
                        <label for="wgt_credit">To give</label>
                    </div>
                </div>
            </div>

            {{-- New BS Head (RIGHT) --}}
            <div class="form-group">
                <label class="field-label">New BS Head</label>
                <select name="bshead">
                    @foreach(($lookups['bsHeads'] ?? []) as $b)
                        @php $bCode = (string)($b['code'] ?? ''); @endphp
                        <option value="{{ $bCode }}" {{ (string)($data['bshead'] ?? 'SUNDB') === $bCode ? 'selected' : '' }}>{{ ($b['name'] ?? '') }} ({{ $bCode }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Distance --}}
            <div class="form-group">
                <label class="field-label">Distance</label>
                <input type="number" name="distance" class="small-input" value="{{ $data['distance'] ?? 0 }}">
            </div>

            {{-- Group (RIGHT) --}}
            <div class="form-group">
                <label class="field-label">Group</label>
                <div class="checkbox-item">
                    <input type="checkbox" name="newgroup" id="newgroup" value="1" checked disabled>
                    <label for="newgroup">New</label>
                </div>
            </div>

            {{-- PIN / State --}}
            <div class="form-group">
                <label class="field-label">PIN / State</label>
                <div class="inline-combo">
                    <input type="text" name="pin" class="small-input" value="{{ $data['pin'] ?? '' }}" placeholder="PIN">
                    <select name="state">
                        <option value="">Select State</option>
                        @foreach(($lookups['states'] ?? []) as $s)
                            @php $sCode = (string)($s['code'] ?? ''); @endphp
                            <option value="{{ $sCode }}" {{ (string)($data['state'] ?? '') === $sCode ? 'selected' : '' }}>{{ (string)($s['name'] ?? $sCode) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Conv. Touch / Def. Touch (RIGHT) --}}
            <div class="form-group">
                <label class="field-label">Conv. Touch</label>
                <div class="multi-field">
                    <input type="number" id="convTouch" name="convtouch" step="0.01" value="{{ $data['convtouch'] ?? '' }}" style="width:70px;">
                    <label>Def. Touch</label>
                    <input type="number" id="defTouch" name="deftouch" step="0.01" value="{{ $data['deftouch'] ?? '' }}" style="width:70px;">
                </div>
            </div>

            {{-- GST No --}}
            <div class="form-group">
                <label class="field-label">GST No</label>
                <input type="text" name="tin" value="{{ $data['tin'] ?? '' }}">
            </div>

            {{-- Stock Touch (RIGHT) --}}
            <div class="form-group">
                <label class="field-label">Stock Touch</label>
                <input type="number" id="stockTouch" name="stocktouch" step="0.01" value="{{ $data['stocktouch'] ?? '' }}">
            </div>

            {{-- Empty spacer for left column alignment --}}
            <div class="form-group"></div>

            {{-- E-mail (RIGHT) --}}
            <div class="form-group">
                <label class="field-label">E-mail</label>
                <input type="email" name="email" value="{{ $data['email'] ?? '' }}">
            </div>

            {{-- Empty spacer for left column alignment --}}
            <div class="form-group"></div>

            {{-- MC Dec.Round / Dec. Round / Round Up (RIGHT) --}}
            <div class="form-group">
                <label class="field-label">MC Dec.Round</label>
                <div class="multi-field">
                    <input type="number" id="mcDecRound" name="mcdecround" value="{{ $data['mcdecround'] ?? '' }}" style="width:50px;">
                    <label>Dec. Round</label>
                    <input type="number" id="decRound" name="decround" value="{{ $data['decround'] ?? 2 }}" style="width:50px;">
                    <label>Round Up</label>
                    <select name="roundup" style="width:60px;">
                        <option value="" {{ ($data['roundup'] ?? '') === '' ? 'selected' : '' }}>-</option>
                        <option value="Y" {{ ($data['roundup'] ?? '') === 'Y' ? 'selected' : '' }}>Yes</option>
                        <option value="N" {{ ($data['roundup'] ?? '') === 'N' ? 'selected' : '' }}>No</option>
                    </select>
                </div>
            </div>
        </div>
    </form>

    <div class="btn-bar">
        @if($isEdit)
            <a class="delete-link" href="{{ url('') }}/api/customer/delete?type=D&code={{ urlencode($data['code'] ?? '') }}" onclick="return confirm('Are you sure you want to delete this depositor?');">Delete</a>
        @endif
        <button type="button" class="btn" id="btnSave">Save</button>
        <button type="button" class="btn" id="btnCancel">Cancel</button>
        <a class="btn" href="{{ url('') }}/dashboard">Exit</a>
    </div>
</div>

<script>
(function() {
    const siteUrl = @json(request()->root());
    const type = @json($type);
    const isEdit = @json($isEdit);

    // ===================== Weight Amount Calculator =====================
    function calcWeightAmt() {
        const mcRate = parseFloat(document.getElementById('mcRate').value) || 0;
        const wastage = parseFloat(document.getElementById('wastagePercent').value) || 0;
        const weightAmt = mcRate * (1 + wastage / 100);
        document.getElementById('weightAmt').textContent = weightAmt.toFixed(2);
    }

    document.getElementById('mcRate').addEventListener('input', calcWeightAmt);
    document.getElementById('wastagePercent').addEventListener('input', calcWeightAmt);

    // Calculate on page load
    calcWeightAmt();

    // ===================== Photo Upload =====================
    document.getElementById('btnSelectPhoto').addEventListener('click', function() {
        document.getElementById('photoInput').click();
    });

    // ===================== Status Message =====================
    function showStatus(message, isError) {
        const box = document.getElementById('statusBox');
        box.textContent = message;
        box.className = 'status-box ' + (isError ? 'error' : 'success');
        box.style.display = 'block';
    }

    // ===================== Cancel =====================
    document.getElementById('btnCancel').addEventListener('click', function() {
        window.close();
        setTimeout(function() {
            if (!window.closed) {
                location.href = siteUrl + '/dashboard';
            }
        }, 50);
    });

    // ===================== Save via Fetch =====================
    document.getElementById('depositorForm').addEventListener('submit', async function(e) {
        e.preventDefault();
    });

    document.getElementById('btnSave').addEventListener('click', async function() {
        const form = document.getElementById('depositorForm');
        const formData = new FormData(form);

        try {
            const res = await fetch(siteUrl + '/api/customer/save', {
                method: 'POST',
                body: new URLSearchParams(formData),
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const out = await res.json();

            if (!out.success) {
                showStatus(out.message || 'Save failed.', true);
                return;
            }

            showStatus(out.message || 'Saved successfully.', false);

            const code = (out.data && out.data.code) ? out.data.code : (form.querySelector('[name="code"]').value || '');
            setTimeout(function() {
                location.href = siteUrl + '/customer/edit?type=D&code=' + encodeURIComponent(code);
            }, 450);

        } catch (err) {
            showStatus('Network error: ' + err.message, true);
        }
    });
})();
</script>
</body>
</html>
