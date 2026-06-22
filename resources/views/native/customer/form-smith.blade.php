<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $isEdit ? 'Edit' : 'Add' }} {{ $typeInfo['title'] }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            background: #f5f7fa;
            padding: 22px;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            margin: 0;
        }
        .container {
            max-width: 1360px;
            margin: 0 auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(90deg, #b68507, #d4af37);
            color: #fff;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-title {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .type-chip {
            border: 1px solid #c89a13;
            background: #fff7dc;
            color: #6a4c00;
            border-radius: 20px;
            padding: 3px 14px;
            font-size: 13px;
            font-weight: 600;
        }
        .header-links {
            display: flex;
            gap: 8px;
        }
        .header-links a {
            border: 1px solid #c4c4c4;
            background: #fff;
            border-radius: 6px;
            padding: 6px 14px;
            font-size: 13px;
            color: #333;
            text-decoration: none;
            cursor: pointer;
        }
        .header-links a:hover {
            background: #f0f0f0;
        }
        .body-wrap {
            padding: 24px;
        }
        .outer-grid {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 24px;
        }
        .section-title {
            color: #b68507;
            font-size: 17px;
            font-weight: 700;
            border-bottom: 2px solid #f0e1b0;
            padding-bottom: 8px;
            margin: 0 0 18px 0;
        }
        .fields-grid {
            display: grid;
            grid-template-columns: 170px 1fr 170px 1fr;
            gap: 14px 16px;
            align-items: center;
        }
        .lbl {
            text-align: right;
            font-size: 13px;
            font-weight: 600;
            color: #505050;
        }
        .fields-grid input,
        .fields-grid select,
        .fields-grid textarea {
            min-height: 38px;
            border: 1px solid #d7d7d7;
            border-radius: 6px;
            padding: 8px 11px;
            font-size: 14px;
            font-family: inherit;
            width: 100%;
            background: #fff;
            outline: none;
        }
        .fields-grid input:focus,
        .fields-grid select:focus,
        .fields-grid textarea:focus {
            border-color: #d4af37;
            box-shadow: 0 0 0 2px rgba(212,175,55,0.15);
        }
        .fields-grid input[readonly] {
            background: #f3f3f3;
            color: #888;
        }
        .fields-grid textarea {
            min-height: 96px;
            resize: vertical;
        }
        .inline-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .inline-group input {
            flex: 1;
        }
        .inline-group select {
            width: 140px;
            flex-shrink: 0;
        }
        .note-full {
            grid-column: 2 / -1;
        }

        /* Photo column */
        .photo-col {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .photo-box {
            border: 2px dashed #d4af37;
            border-radius: 10px;
            min-height: 260px;
            background: #fffdf5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        .photo-box img {
            max-width: 100%;
            max-height: 300px;
            object-fit: contain;
        }
        .photo-box .placeholder {
            color: #c4a84d;
            font-size: 14px;
        }
        .photo-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .photo-actions .btn-select-photo {
            padding: 7px 16px;
            border: 1px solid #d4af37;
            background: #fff7dc;
            border-radius: 6px;
            color: #6a4c00;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .photo-actions .btn-select-photo:hover {
            background: #f5ecce;
        }
        .checkboxes {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            align-items: center;
            padding: 4px 0;
        }
        .checkboxes label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #505050;
            cursor: pointer;
        }

        /* Message */
        .msg {
            margin: 0 24px 16px;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 14px;
            display: none;
        }
        .msg-ok { background: #ecfdf5; border: 1px solid #86efac; color: #166534; }
        .msg-err { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }

        /* Button bar */
        .actions {
            border-top: 1px solid #ececec;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 16px 24px;
            margin-top: 18px;
        }
        .btn {
            padding: 9px 22px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            border: 1px solid #c4c4c4;
            background: #fff;
            color: #333;
        }
        .btn:hover { background: #f0f0f0; }
        .btn-primary {
            background: #d4af37;
            border: 1px solid #be980f;
            color: #fff;
        }
        .btn-primary:hover { background: #c9a22e; }
        .btn-danger {
            background: #dc3545;
            border: 1px solid #b92130;
            color: #fff;
        }
        .btn-danger:hover { background: #c82333; }
        .btn-dark {
            background: #333;
            border: 1px solid #333;
            color: #fff;
        }
        .btn-dark:hover { background: #222; }

        @media (max-width: 1000px) {
            .outer-grid { grid-template-columns: 1fr; }
            .fields-grid { grid-template-columns: 1fr; }
            .lbl { text-align: left; }
        }
    </style>
</head>
<body>

<div class="container">
    {{-- Header --}}
    <div class="header">
        <div class="header-title">
            {{ $isEdit ? 'Edit' : 'Add' }} {{ $typeInfo['title'] }}
            <span class="type-chip">{{ $type }} &mdash; {{ $typeInfo['title'] }}</span>
        </div>
        <div class="header-links">
            <a href="{{ url('/customer') }}?type={{ urlencode($type) }}&list=1">List View</a>
            <a href="{{ url('/dashboard') }}">Dashboard</a>
        </div>
    </div>

    {{-- Status message --}}
    <div id="msg" class="msg"></div>

    <form id="smithForm" onsubmit="return false;">
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="smcode" value="{{ $data['smcode'] ?? '' }}">

        <div class="body-wrap">
            <div class="outer-grid">
                {{-- Left: Fields --}}
                <div class="fields-col">
                    <div class="section-title">Basic Information</div>

                    @php
                        // Default group: G/R/J → SUNCR (Sundry Creditors)
                        $defaultGrp = 'SUNCR';
                        $currentGrp = strtoupper(trim((string)($data['grp'] ?? $data['acgrp'] ?? '')));
                        if ($currentGrp === '') $currentGrp = $defaultGrp;

                        // Default BS Head matches group default
                        $defaultBsHead = $defaultGrp;
                        $currentBsHead = trim((string)($data['bshead'] ?? ''));
                        if ($currentBsHead === '') $currentBsHead = $defaultBsHead;

                        // Balance type from sign of opbalance
                        $opBal = isset($data['opbalance']) ? (float)$data['opbalance'] : 0;
                        $balType = $opBal >= 0 ? 'credit' : 'debit';

                        // Weight type from sign of opweight
                        $opWgt = isset($data['opweight']) ? (float)$data['opweight'] : 0;
                        $wgtType = $opWgt >= 0 ? 'credit' : 'debit';
                    @endphp

                    <div class="fields-grid">
                        {{-- Row 1: Code, Name --}}
                        <label class="lbl">Code</label>
                        <input type="text" name="code" maxlength="8" value="{{ $data['code'] ?? '' }}" {{ $isEdit ? 'readonly' : '' }} required>

                        <label class="lbl">Name</label>
                        <input type="text" name="name" maxlength="40" value="{{ $data['name'] ?? '' }}" required>

                        {{-- Row 2: Address, City/Loc --}}
                        <label class="lbl">Address</label>
                        <input type="text" name="addr1" maxlength="90" value="{{ $data['addr1'] ?? '' }}">

                        <label class="lbl">City / Loc</label>
                        <input type="text" name="city" maxlength="20" value="{{ $data['city'] ?? '' }}">

                        {{-- Row 3: PIN Code, State --}}
                        <label class="lbl">PIN Code</label>
                        <input type="text" name="pin" maxlength="10" value="{{ $data['pin'] ?? '' }}">

                        <label class="lbl">State</label>
                        <select name="state">
                            <option value="">-- Select State --</option>
                            @foreach(($lookups['states'] ?? []) as $s)
                                @php $sCode = (string)($s['code'] ?? ''); @endphp
                                <option value="{{ $sCode }}" {{ (string)($data['state'] ?? '') === $sCode ? 'selected' : '' }}>{{ (string)($s['name'] ?? $sCode) }}</option>
                            @endforeach
                        </select>

                        {{-- Row 4: Phone, Mobile --}}
                        <label class="lbl">Phone</label>
                        <input type="text" name="telephone" maxlength="25" value="{{ $data['telephone'] ?? '' }}">

                        <label class="lbl">Mobile</label>
                        <input type="text" name="mobile" maxlength="25" value="{{ $data['mobile'] ?? '' }}">

                        {{-- Row 5: Email, GST No --}}
                        <label class="lbl">Email</label>
                        <input type="email" name="email" maxlength="40" value="{{ $data['email'] ?? '' }}">

                        <label class="lbl">GST No</label>
                        <input type="text" name="tin" maxlength="20" value="{{ $data['tin'] ?? '' }}">

                        {{-- Row 6: PAN/Aadhaar, A/c Group --}}
                        <label class="lbl">PAN / Aadhaar</label>
                        <input type="text" name="panadhar" maxlength="20" value="{{ $data['panadhar'] ?? '' }}">

                        <label class="lbl">A/c Group</label>
                        <select name="grp">
                            <option value="">-- Select Group --</option>
                            @foreach(($lookups['accountGroups'] ?? []) as $g)
                                @php $gCode = strtoupper(trim((string)($g['code'] ?? ''))); @endphp
                                <option value="{{ $gCode }}" {{ $currentGrp === $gCode ? 'selected' : '' }}>{{ ($g['name'] ?? '') }} ({{ $gCode }})</option>
                            @endforeach
                        </select>

                        {{-- Row 7: BS Head, Op. Amt Bal --}}
                        <label class="lbl">BS Head</label>
                        <select name="bshead">
                            <option value="">-- Select BS Head --</option>
                            @foreach(($lookups['bsHeads'] ?? []) as $b)
                                @php $bCode = (string)($b['code'] ?? ''); @endphp
                                <option value="{{ $bCode }}" {{ $currentBsHead === $bCode ? 'selected' : '' }}>{{ ($b['name'] ?? '') }} ({{ $bCode }})</option>
                            @endforeach
                        </select>

                        <label class="lbl">Op. Amt Bal</label>
                        <div class="inline-group">
                            <input type="number" step="0.01" name="opbalance" value="{{ number_format(abs($opBal), 2, '.', '') }}">
                            <select name="balance_type">
                                <option value="debit" {{ $balType === 'debit' ? 'selected' : '' }}>To Receive</option>
                                <option value="credit" {{ $balType === 'credit' ? 'selected' : '' }}>To Give</option>
                            </select>
                        </div>

                        {{-- Row 8: Op. Weight Bal, Op. Dep Wgt Bal --}}
                        <label class="lbl">Op. Weight Bal</label>
                        <div class="inline-group">
                            <input type="number" step="0.001" name="opweight" value="{{ number_format(abs($opWgt), 3, '.', '') }}">
                            <select name="weight_type">
                                <option value="debit" {{ $wgtType === 'debit' ? 'selected' : '' }}>To Receive</option>
                                <option value="credit" {{ $wgtType === 'credit' ? 'selected' : '' }}>To Give</option>
                            </select>
                        </div>

                        <label class="lbl">Op. Dep Wgt Bal</label>
                        <input type="number" step="0.001" name="opdepwgt" value="{{ isset($data['opdepwgt']) ? number_format(abs((float)$data['opdepwgt']), 3, '.', '') : '0.000' }}">

                        {{-- Row 9: Conv Touch, Default Touch --}}
                        <label class="lbl">Conv. Touch</label>
                        <input type="number" step="0.01" name="convtouch" value="{{ isset($data['convtouch']) ? number_format((float)$data['convtouch'], 2, '.', '') : '100.00' }}">

                        <label class="lbl">Default Touch</label>
                        <input type="number" step="0.01" name="deftouch" value="{{ isset($data['deftouch']) ? number_format((float)$data['deftouch'], 2, '.', '') : '0.00' }}">

                        {{-- Row 10: Stock Touch, MC Rate --}}
                        <label class="lbl">Stock Touch</label>
                        <input type="number" step="0.01" name="stocktouch" value="{{ isset($data['stocktouch']) ? number_format((float)$data['stocktouch'], 2, '.', '') : '0.00' }}">

                        <label class="lbl">MC Rate / Gm</label>
                        <input type="number" step="0.01" name="mcrate" value="{{ isset($data['mcrate']) ? number_format((float)$data['mcrate'], 2, '.', '') : '0.00' }}">

                        {{-- Row 11: Distance, Religion --}}
                        <label class="lbl">Distance</label>
                        <input type="number" name="distance" value="{{ $data['distance'] ?? 0 }}">

                        <label class="lbl">Religion</label>
                        <input type="text" name="religion" maxlength="10" value="{{ $data['religion'] ?? '' }}">

                        {{-- Row 12: Wastage, Dec Round --}}
                        <label class="lbl">Wastage Gm</label>
                        <input type="number" step="0.001" name="wastage" value="{{ isset($data['wastage']) ? number_format((float)$data['wastage'], 3, '.', '') : '0.000' }}">

                        <label class="lbl">Dec. Round</label>
                        <input type="number" name="decround" value="{{ $data['decround'] ?? 2 }}">

                        {{-- Row 13: MC Dec Round, Round Up --}}
                        <label class="lbl">MC Dec. Round</label>
                        <input type="number" name="mcdecround" value="{{ $data['mcdecround'] ?? 0 }}">

                        <label class="lbl">Round Up</label>
                        @php
                            $roundupValue = (string) ($data['roundup'] ?? '');
                            if (strcasecmp($roundupValue, 'Y') === 0) $roundupValue = '1';
                            elseif (strcasecmp($roundupValue, 'N') === 0) $roundupValue = '0';
                            elseif ($roundupValue === '') $roundupValue = '0';
                        @endphp
                        <select name="roundup">
                            <option value="0" {{ $roundupValue === '0' ? 'selected' : '' }}>No</option>
                            <option value="1" {{ $roundupValue === '1' ? 'selected' : '' }}>Yes</option>
                        </select>

                        {{-- Row 14: Route, Area --}}
                        <label class="lbl">Route</label>
                        <input type="text" name="route" maxlength="10" value="{{ $data['route'] ?? '' }}">

                        <label class="lbl">Area</label>
                        <input type="text" name="carea" maxlength="10" value="{{ $data['carea'] ?? '' }}">

                        {{-- Note: full width --}}
                        <label class="lbl">Note</label>
                        <textarea name="note" class="note-full">{{ $data['note'] ?? '' }}</textarea>
                    </div>
                </div>

                {{-- Right: Photo --}}
                <div class="photo-col">
                    <div class="section-title">Photo</div>

                    <div class="photo-box" id="photoBox">
                        @if($isEdit && !empty($data['code']))
                            <img id="photoPreview" src="{{ url('/customer/picture') }}?code={{ urlencode($data['code']) }}&_={{ time() }}" alt="Customer Photo"
                                 onerror="this.style.display='none'; document.getElementById('photoPlaceholder').style.display='block';">
                            <span id="photoPlaceholder" class="placeholder" style="display:none;">No photo available</span>
                        @else
                            <img id="photoPreview" src="" alt="" style="display:none;">
                            <span id="photoPlaceholder" class="placeholder">No photo selected</span>
                        @endif
                    </div>

                    <div class="photo-actions">
                        <input type="file" name="photo" id="photoInput" accept="image/*" style="display:none;">
                        <button type="button" class="btn-select-photo" onclick="document.getElementById('photoInput').click();">Select Photo</button>
                    </div>

                    <div class="checkboxes">
                        <label>
                            <input type="checkbox" name="removed" value="Y" {{ strtoupper((string)($data['removed'] ?? 'N')) === 'Y' || (!empty($data['removed']) && $data['removed'] == 1) ? 'checked' : '' }}>
                            Removed
                        </label>
                        <label>
                            <input type="checkbox" name="blocked" value="Y" {{ strtoupper((string)($data['blocked'] ?? 'N')) === 'Y' ? 'checked' : '' }}>
                            Blocked
                        </label>
                        <label>
                            <input type="checkbox" name="agent" value="Y" {{ strtoupper((string)($data['agent'] ?? 'N')) === 'Y' ? 'checked' : '' }}>
                            Agent
                        </label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Button bar --}}
        <div class="actions">
            @if($isEdit)
                <a href="{{ url('/api/customer/delete') }}?type={{ urlencode($type) }}&code={{ urlencode($data['code'] ?? '') }}"
                   class="btn btn-danger"
                   onclick="return confirm('Are you sure you want to delete this {{ strtolower($typeInfo['title']) }}?');">
                    Delete
                </a>
            @endif
            <button type="submit" class="btn btn-primary">Save</button>
            <button type="button" class="btn" onclick="cancelForm();">Cancel</button>
            <a href="{{ url('/dashboard') }}" class="btn btn-dark">Exit</a>
        </div>
    </form>
</div>

<script>
    const siteUrl = @json(request()->root());
    const type = @json($type);
    const isEdit = @json($isEdit);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    // Photo preview on file input change
    document.getElementById('photoInput').addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('photoPreview');
            const placeholder = document.getElementById('photoPlaceholder');
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    });

    // Cancel form
    function cancelForm() {
        window.close();
        setTimeout(function() {
            if (!window.closed) {
                location.href = siteUrl + '/dashboard';
            }
        }, 50);
    }

    // Show status message
    function showMsg(text, ok) {
        const msg = document.getElementById('msg');
        msg.className = 'msg ' + (ok ? 'msg-ok' : 'msg-err');
        msg.textContent = text;
        msg.style.display = 'block';
        if (ok) {
            setTimeout(function() { msg.style.display = 'none'; }, 4000);
        }
    }

    // Save handler
    document.querySelector('form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);

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
                showMsg(out.message || 'Save failed', false);
                return;
            }

            showMsg(out.message || 'Saved successfully', true);
            const code = (out.data && out.data.code) ? out.data.code : (document.querySelector('[name="code"]').value || '');
            setTimeout(function() {
                location.href = siteUrl + '/customer/edit?type=' + encodeURIComponent(type) + '&code=' + encodeURIComponent(code);
            }, 450);
        } catch (err) {
            showMsg('Network error: ' + err.message, false);
        }
    });
</script>
</body>
</html>
