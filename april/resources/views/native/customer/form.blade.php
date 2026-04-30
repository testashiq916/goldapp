@php
    $typeUpper = strtoupper((string)$type);
    $isCustomerType = in_array($typeUpper, ['C', 'D'], true);
    $isSupplierType = $typeUpper === 'S';
    $isStaffType = $typeUpper === 'F';
    $codeValue = $data['code'] ?? '';

    // Default group logic: C→SUNDB, S→SUNCR, F→SUNCR
    $defaultGroup = in_array($typeUpper, ['C', 'D'], true) ? 'SUNDB' : 'SUNCR';
    $currentGroup = strtoupper(trim((string)($data['grp'] ?? $data['acgrp'] ?? $defaultGroup)));
    if ($currentGroup === '') $currentGroup = $defaultGroup;

    $currentBsHead = trim((string)($data['bshead'] ?? $currentGroup));
    if ($currentBsHead === '') $currentBsHead = $defaultGroup;

    // Customer balances use business labels opposite to generic debit/credit wording:
    // positive => shop has to give customer, negative => customer has to give shop.
    $opBalRaw = (float)($data['opbalance'] ?? 0);
    $balanceType = $isCustomerType
        ? ($opBalRaw >= 0 ? 'debit' : 'credit')
        : ($opBalRaw >= 0 ? 'credit' : 'debit');
    $opBalAbs = number_format(abs($opBalRaw), 2, '.', '');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $isEdit ? 'Edit' : 'Add' }} {{ $typeInfo['title'] }}</title>
    <style>
        /* ── Reset & Base ── */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            background: #f4ecd8;
            color: #2d1d0f;
            padding: 5px;
            font-size: 13px;
        }

        /* ── Card ── */
        .card {
            max-width: 1100px;
            margin: 0 auto;
            background: #fffdf7;
            border: 1px solid #c7b086;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(45,29,15,0.10);
        }

        /* ── Header Bar ── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            background: linear-gradient(to right, #2d1d0f, #5a3a17);
            color: #f4e1c5;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-title {
            font-size: 16px;
            font-weight: 600;
            color: #ffefd0;
        }
        .type-badge {
            display: inline-block;
            padding: 3px 12px;
            background: linear-gradient(135deg, #d4a847, #b8860b);
            color: #2d1d0f;
            font-size: 11px;
            font-weight: 700;
            border-radius: 12px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .header-links {
            display: flex;
            gap: 8px;
        }
        .header-links a {
            padding: 5px 12px;
            background: linear-gradient(to bottom, #fff8ef, #f4e1c5);
            border: 1px solid #8a6a3a;
            border-radius: 4px;
            color: #2d1d0f;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
        }
        .header-links a:hover {
            background: linear-gradient(to bottom, #f4e1c5, #e8cfa0);
        }

        /* ── Message ── */
        .msg {
            margin: 8px 12px;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            display: none;
        }
        .msg-ok  { background: #ecfdf5; border: 1px solid #86efac; color: #166534; }
        .msg-err { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }

        /* ── Outer Layout: fields + photo column ── */
        .form-outer {
            display: grid;
            grid-template-columns: 1fr 250px;
            gap: 0;
        }

        /* ── Fields Grid ── */
        .fields-grid {
            display: grid;
            grid-template-columns: 100px 1fr 100px 1fr;
            gap: 6px 8px;
            padding: 14px 14px 14px 14px;
            align-items: center;
        }

        .fields-grid label {
            font-size: 12px;
            font-weight: 600;
            color: #5a3a17;
            text-align: right;
            padding-right: 6px;
            white-space: nowrap;
        }

        .fields-grid input[type="text"],
        .fields-grid input[type="email"],
        .fields-grid input[type="number"],
        .fields-grid input[type="date"],
        .fields-grid input[type="password"],
        .fields-grid select,
        .fields-grid textarea {
            width: 100%;
            padding: 5px 7px;
            border: 1px solid #c7b086;
            border-radius: 3px;
            background: #fff;
            color: #2d1d0f;
            font-size: 12px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }
        .fields-grid input:focus,
        .fields-grid select:focus,
        .fields-grid textarea:focus {
            border-color: #9a6e2f;
            box-shadow: 0 0 0 2px rgba(154,110,47,0.15);
        }
        .fields-grid input[readonly] {
            background: #f0e8d5;
            color: #7a6a50;
        }
        .fields-grid textarea {
            min-height: 60px;
            resize: vertical;
        }

        .col-span-2 { grid-column: span 2; }
        .col-span-3 { grid-column: span 3; }
        .col-span-full { grid-column: 1 / -1; }

        .inline-group {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .inline-group input[type="number"] { flex: 1; }
        .inline-group select { max-width: 130px; }

        /* ── Checkboxes section ── */
        .checkbox-row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
            padding: 2px 0;
        }
        .checkbox-row label {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            font-weight: 500;
            color: #5a3a17;
            text-align: left;
            padding-right: 0;
            white-space: nowrap;
            cursor: pointer;
        }
        .checkbox-row input[type="checkbox"] {
            accent-color: #9a6e2f;
        }

        /* ── Section headers inside fields ── */
        .section-label {
            grid-column: 1 / -1;
            font-size: 11px;
            font-weight: 700;
            color: #8a6a3a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e0d5c0;
            padding: 6px 0 3px 0;
            margin-top: 4px;
        }

        /* ── Photo Column ── */
        .photo-col {
            padding: 14px 14px 14px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            border-left: 1px solid #e0d5c0;
            padding-left: 14px;
        }
        .photo-label {
            font-size: 12px;
            font-weight: 700;
            color: #5a3a17;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .photo-panel {
            width: 100%;
            height: 160px;
            border: 2px dashed #c7b086;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #faf6ed;
            overflow: hidden;
        }
        .photo-panel img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .photo-panel .no-photo {
            color: #b0a080;
            font-size: 11px;
        }
        .photo-btn {
            padding: 5px 14px;
            background: linear-gradient(to bottom, #fff8ef, #f4e1c5);
            border: 1px solid #8a6a3a;
            border-radius: 4px;
            color: #2d1d0f;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
        }
        .photo-btn:hover {
            background: linear-gradient(to bottom, #f4e1c5, #e8cfa0);
        }
        .photo-reload {
            font-size: 11px;
            color: #8a6a3a;
            cursor: pointer;
            text-decoration: underline;
        }

        /* ── Button Bar ── */
        .btnbar {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            align-items: center;
            padding: 10px 16px;
            background: linear-gradient(to right, #3a2a14, #5a3a17);
            border-top: 1px solid #c7b086;
        }
        .btnbar .btn {
            padding: 7px 18px;
            background: linear-gradient(to bottom, #fff8ef, #f4e1c5);
            border: 1px solid #8a6a3a;
            border-radius: 4px;
            color: #2d1d0f;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .btnbar .btn:hover {
            background: linear-gradient(to bottom, #f4e1c5, #e8cfa0);
        }
        .btnbar .btn-save {
            background: linear-gradient(to bottom, #d4a847, #b8860b);
            color: #fff;
            border-color: #8a6a3a;
        }
        .btnbar .btn-save:hover {
            background: linear-gradient(to bottom, #c49b3f, #a07808);
        }
        .btnbar .btn-delete {
            background: linear-gradient(to bottom, #fee2e2, #fecaca);
            color: #991b1b;
            border-color: #f87171;
            margin-right: auto;
        }
        .btnbar .btn-delete:hover {
            background: linear-gradient(to bottom, #fecaca, #fca5a5);
        }

        /* ── Phone duplicate warning ── */
        .dup-warn {
            color: #b91c1c;
            font-size: 11px;
            font-weight: 500;
            margin-top: 2px;
        }

        /* ── Responsive ── */
        @media (max-width: 800px) {
            .form-outer {
                grid-template-columns: 1fr;
            }
            .photo-col {
                border-left: none;
                border-top: 1px solid #e0d5c0;
                padding: 14px;
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
            }
            .fields-grid {
                grid-template-columns: 90px 1fr;
            }
        }
    </style>
</head>
<body>

<div class="card">
    {{-- ══ Header Bar ══ --}}
    <div class="header">
        <div class="header-left">
            <span class="header-title">{{ $isEdit ? 'Edit' : 'Add' }} {{ $typeInfo['title'] }}</span>
            <span class="type-badge">{{ $typeInfo['title'] }}</span>
        </div>
        <div class="header-links">
            <a href="{{ url('/customer') }}?type={{ urlencode($type) }}&list=1">List View</a>
            <a href="{{ url('/dashboard') }}">Dashboard</a>
        </div>
    </div>

    {{-- ══ Message Area ══ --}}
    <div id="msg" class="msg"></div>

    {{-- ══ Form ══ --}}
    <form id="customerForm" onsubmit="return false;" enctype="multipart/form-data">
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="original_code" value="{{ $codeValue }}">
        <input type="hidden" name="advanced_payload" id="advancedPayload" value="">

        <div class="form-outer">
            {{-- ──── Fields Column ──── --}}
            <div class="fields-grid">

                {{-- Row 1: Code, Name --}}
                <label for="fCode">Code</label>
                <input type="text" id="fCode" name="code" maxlength="8" value="{{ $codeValue }}" required>
                <label for="fName">Name</label>
                <input type="text" id="fName" name="name" maxlength="40" value="{{ $data['name'] ?? '' }}" required>

                {{-- Row 2: Address, City --}}
                <label for="fAddr1">Address</label>
                <input type="text" id="fAddr1" name="addr1" maxlength="90" value="{{ $data['addr1'] ?? '' }}">
                <label for="fCity">City/Loc</label>
                <input type="text" id="fCity" name="city" maxlength="20" value="{{ $data['city'] ?? '' }}">

                {{-- Row 3: Phone, Mobile --}}
                <label for="fPhone">Phone</label>
                <div>
                    <input type="text" id="fPhone" name="telephone" maxlength="25" value="{{ $data['telephone'] ?? '' }}">
                    <div id="phoneDup" class="dup-warn" style="display:none;"></div>
                </div>
                <label for="fMobile">Mobile</label>
                <div>
                    <input type="text" id="fMobile" name="mobile" maxlength="25" value="{{ $data['mobile'] ?? '' }}">
                    <div id="mobileDup" class="dup-warn" style="display:none;"></div>
                </div>

                {{-- Row 4: Email, PIN Code --}}
                <label for="fEmail">Email</label>
                <input type="email" id="fEmail" name="email" maxlength="40" value="{{ $data['email'] ?? '' }}">
                <label for="fPin">PIN Code</label>
                <input type="text" id="fPin" name="pin" maxlength="10" value="{{ $data['pin'] ?? '' }}">

                {{-- Row 5: GST No, PAN/Adh --}}
                <label for="fTin">GST No</label>
                <input type="text" id="fTin" name="tin" maxlength="20" value="{{ $data['tin'] ?? '' }}">
                <label for="fPanadhar">PAN/Adh</label>
                <input type="text" id="fPanadhar" name="panadhar" maxlength="20" value="{{ $data['panadhar'] ?? '' }}">

                {{-- Row 6: Religion, SMAN --}}
                <label for="fReligion">Religion</label>
                <input type="text" id="fReligion" name="religion" maxlength="10" value="{{ $data['religion'] ?? '' }}">
                <label for="fSmcode">SMAN</label>
                <select id="fSmcode" name="smcode">
                    <option value="">Select SMan</option>
                    @foreach(($lookups['salesmen'] ?? []) as $sm)
                        @php $smCode = (string)($sm['code'] ?? ''); @endphp
                        <option value="{{ $smCode }}" {{ (string)($data['smcode'] ?? '') === $smCode ? 'selected' : '' }}>{{ ($sm['name'] ?? '') }} ({{ $smCode }})</option>
                    @endforeach
                </select>

                {{-- Row 7: A/c Group, State --}}
                <label for="fGrp">A/c Group</label>
                <select id="fGrp" name="grp">
                    <option value="">Select Group</option>
                    @foreach(($lookups['accountGroups'] ?? []) as $g)
                        @php $gCode = strtoupper(trim((string)($g['code'] ?? ''))); @endphp
                        <option value="{{ $gCode }}" {{ $currentGroup === $gCode ? 'selected' : '' }}>{{ ($g['name'] ?? '') }} ({{ $gCode }})</option>
                    @endforeach
                </select>
                <label for="fState">State</label>
                <select id="fState" name="state">
                    <option value="">Select State</option>
                    @foreach(($lookups['states'] ?? []) as $s)
                        @php $sCode = (string)($s['code'] ?? ''); @endphp
                        <option value="{{ $sCode }}" {{ (string)($data['state'] ?? '') === $sCode ? 'selected' : '' }}>{{ (string)($s['name'] ?? $sCode) }}</option>
                    @endforeach
                </select>

                {{-- Row 8: BS Head, Op.Balance --}}
                <label for="fBshead">BS Head</label>
                <select id="fBshead" name="bshead">
                    <option value="">Select BS Head</option>
                    @foreach(($lookups['bsHeads'] ?? []) as $b)
                        @php $bCode = (string)($b['code'] ?? ''); @endphp
                        <option value="{{ $bCode }}" {{ $currentBsHead === $bCode ? 'selected' : '' }}>{{ ($b['name'] ?? '') }} ({{ $bCode }})</option>
                    @endforeach
                </select>
                <label for="fOpbal">Op.Balance</label>
                <div class="inline-group">
                    <input type="number" step="0.01" id="fOpbal" name="opbalance" value="{{ $opBalAbs }}">
                    <select name="balance_type">
                        <option value="credit" {{ $balanceType === 'credit' ? 'selected' : '' }}>To Receive</option>
                        <option value="debit" {{ $balanceType === 'debit' ? 'selected' : '' }}>To Give</option>
                    </select>
                </div>

                {{-- Row 9: Route, Area --}}
                <label for="fRoute">Route</label>
                <div class="inline-group">
                    <select id="fRoute" name="route">
                        <option value="">Select Route</option>
                        @foreach(($lookups['routes'] ?? []) as $r)
                            @php $rCode = (string)($r['code'] ?? ''); @endphp
                            <option value="{{ $rCode }}" {{ (string)($data['route'] ?? '') === $rCode ? 'selected' : '' }}>
                                {{ (string)($r['name'] ?? $rCode) }} ({{ $rCode }})
                            </option>
                        @endforeach
                    </select>
                    <button type="button" class="photo-btn" onclick="openLookupPopup('route')">Add</button>
                </div>
                <label for="fArea">Area</label>
                <div class="inline-group">
                    <select id="fArea" name="carea">
                        <option value="">Select Area</option>
                        @foreach(($lookups['areas'] ?? []) as $a)
                            @php $aCode = (string)($a['code'] ?? ''); @endphp
                            <option value="{{ $aCode }}" {{ (string)($data['carea'] ?? '') === $aCode ? 'selected' : '' }}>
                                {{ (string)($a['name'] ?? $aCode) }} ({{ $aCode }})
                            </option>
                        @endforeach
                    </select>
                    <button type="button" class="photo-btn" onclick="openLookupPopup('area')">Add</button>
                </div>

                {{-- Row 10: Date, Due Date --}}
                <label for="fAdate">Date</label>
                <input type="date" id="fAdate" name="adate" value="{{ $data['adate'] ?? '' }}">
                <label for="fDuedate">Due Date</label>
                <input type="date" id="fDuedate" name="duedate" value="{{ $data['duedate'] ?? '' }}">

                {{-- Row 11: Op.Weight Bal, Op.Dep.Wgt Bal --}}
                <label for="fOpweight">Op.Weight Bal</label>
                <input type="number" step="0.001" id="fOpweight" name="opweight" value="{{ $data['opweight'] ?? '0.000' }}">
                <label for="fOpdepwgt">Op.Dep.Wgt Bal</label>
                <input type="number" step="0.001" id="fOpdepwgt" name="opdepwgtbal" value="{{ $data['opdepwgtbal'] ?? '0.000' }}">

                {{-- Row 12: Customer-only fields --}}
                @if($isCustomerType)
                    <label for="fPcard">Prev.Card Type</label>
                    <div class="inline-group">
                        <input type="text" id="fPcard" name="pcard" value="{{ $data['pcard'] ?? '' }}">
                        <button type="button" class="photo-btn" onclick="openLookupPopup('pcard')">Add</button>
                    </div>
                    <label for="fPcardpts">Op.PCard Points</label>
                    <input type="number" step="0.01" id="fPcardpts" name="oppcardpoints" value="{{ $data['oppcardpoints'] ?? '0' }}">
                @endif

                {{-- Row 13: Distance --}}
                <label for="fDistance">Distance</label>
                <input type="number" id="fDistance" name="distance" value="{{ $data['distance'] ?? 0 }}">
                <label></label>
                <div></div>

                {{-- Staff-only fields --}}
                @if($isStaffType)
                    <div class="section-label">Staff Details</div>

                    <label for="fIdno">ID No</label>
                    <div>
                        <input type="text" id="fIdno" name="idno" value="{{ $data['idno'] ?? '' }}">
                        <div id="idnoDup" class="dup-warn" style="display:none;"></div>
                    </div>
                    <label for="fSalary">Salary</label>
                    <input type="number" step="0.01" id="fSalary" name="salary" value="{{ $data['salary'] ?? '0' }}">

                    <label for="fCutrate">Cut Rate</label>
                    <input type="number" step="0.01" id="fCutrate" name="cutrate" value="{{ $data['cutrate'] ?? '0' }}">
                    <label for="fColncomn">Coln Comn</label>
                    <input type="number" step="0.01" id="fColncomn" name="colncomn" value="{{ $data['colncomn'] ?? '0' }}">

                    <label for="fHomemobile">Home Mobile</label>
                    <input type="text" id="fHomemobile" name="homemobile" value="{{ $data['homemobile'] ?? '' }}">
                    <label for="fPospwd">POS Password</label>
                    <input type="password" id="fPospwd" name="pospwd" value="{{ $data['pospwd'] ?? '' }}">
                @endif

                {{-- Advanced checkboxes --}}
                <div class="section-label">Advanced Options</div>
                <label></label>
                <div class="checkbox-row col-span-3">
                    <label>
                        <input type="checkbox" name="coparty" value="Y" {{ strtoupper((string)($data['coparty'] ?? 'N')) === 'Y' ? 'checked' : '' }}>
                        C/o Party
                    </label>
                    <label>
                        <input type="checkbox" name="removed" value="Y" {{ !empty($data['removed']) && (string)$data['removed'] !== '0' && strtoupper((string)$data['removed']) !== 'N' ? 'checked' : '' }}>
                        Removed
                    </label>
                    <label>
                        <input type="checkbox" name="agent" value="Y" {{ strtoupper((string)($data['agent'] ?? 'N')) === 'Y' ? 'checked' : '' }}>
                        Agent
                    </label>
                    <label>
                        <input type="checkbox" name="blocked" value="Y" {{ strtoupper((string)($data['blocked'] ?? 'N')) === 'Y' ? 'checked' : '' }}>
                        Blocked
                    </label>
                    @if($isStaffType)
                        <label>
                            <input type="checkbox" name="approval" value="Y" {{ strtoupper((string)($data['approval'] ?? 'N')) === 'Y' ? 'checked' : '' }}>
                            Approval Authority
                        </label>
                    @endif
                </div>

                <label></label>
                <div class="checkbox-row col-span-3">
                    <button type="button" class="photo-btn" onclick="openAdvancedPopup();">Advanced...</button>
                    <button type="button" class="photo-btn" onclick="document.getElementById('photoFile').click();">Image Upload</button>
                </div>

                {{-- Note --}}
                <label for="fNote">Note</label>
                <textarea id="fNote" name="note" class="col-span-3">{{ $data['note'] ?? '' }}</textarea>

            </div>

            {{-- ──── Photo Column ──── --}}
            <div class="photo-col">
                <span class="photo-label">Photo</span>
                <div class="photo-panel" id="photoPanel">
                    @if($isEdit && $codeValue !== '')
                        <img id="photoImg" src="{{ url('/customer/picture') }}?code={{ urlencode($codeValue) }}&_={{ time() }}" alt="Photo"
                             onerror="this.style.display='none'; document.getElementById('noPhotoText').style.display='block';">
                        <span id="noPhotoText" class="no-photo" style="display:none;">No photo</span>
                    @else
                        <span class="no-photo">No photo</span>
                    @endif
                </div>
                <input type="file" id="photoFile" name="photo" accept="image/*" style="display:none;">
                <button type="button" class="photo-btn" onclick="document.getElementById('photoFile').click();">Select Photo</button>
                @if($isEdit && $codeValue !== '')
                    <a class="photo-reload" onclick="reloadPhoto();">Reload</a>
                @endif
            </div>

        </div>
    </form>

    {{-- ══ Button Bar ══ --}}
    <div class="btnbar">
        @if($isEdit)
            <a class="btn btn-delete" href="#" onclick="confirmDelete(); return false;">Delete</a>
        @endif
        <button type="button" class="btn btn-save" id="btnSave">Save</button>
        <button type="button" class="btn" onclick="cancelForm();">Cancel</button>
    </div>
</div>

<script>
    const siteUrl = @json(request()->root());
    const type = @json($type);
    const isEdit = @json($isEdit);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    let advancedData = @json($data['advanced'] ?? []);

    // ── Auto-fetch next code for add mode (C/S only, not F) ──
    if (!isEdit && (type === 'C' || type === 'S')) {
        fetch(siteUrl + '/api/customer/next-code?type=' + encodeURIComponent(type))
            .then(r => r.json())
            .then(out => {
                if (out.success && out.code) {
                    document.getElementById('fCode').value = out.code;
                }
            })
            .catch(() => {});
    }

    // ── Message helper ──
    function showMsg(text, ok) {
        const el = document.getElementById('msg');
        el.className = 'msg ' + (ok ? 'msg-ok' : 'msg-err');
        el.textContent = text;
        el.style.display = 'block';
        if (ok) {
            setTimeout(() => { el.style.display = 'none'; }, 4000);
        }
    }

    // ── Photo preview on file select ──
    document.getElementById('photoFile').addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const panel = document.getElementById('photoPanel');
            panel.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
        };
        reader.readAsDataURL(file);
    });

    // ── Reload photo ──
    function reloadPhoto() {
        const code = document.querySelector('[name="code"]').value;
        if (!code) return;
        const panel = document.getElementById('photoPanel');
        panel.innerHTML = '<img src="' + siteUrl + '/customer/picture?code=' + encodeURIComponent(code) + '&_=' + Date.now() + '" alt="Photo" onerror="this.outerHTML=\'<span class=no-photo>No photo</span>\'">';
    }

    // ── Cancel ──
    function cancelForm() {
        window.close();
        setTimeout(function() {
            if (!window.closed) {
                location.href = siteUrl + '/dashboard';
            }
        }, 100);
    }

    // ── Code field blur: try fetch existing (add mode only) ──
    if (!isEdit) {
        document.getElementById('fCode').addEventListener('blur', function() {
            tryFetchByCode(this.value.trim());
        });
    }

    function tryFetchByCode(code) {
        if (!code || isEdit) return;
        fetch(siteUrl + '/api/customer/get?code=' + encodeURIComponent(code))
            .then(r => r.json())
            .then(out => {
                if (out.success && out.data) {
                    if (confirm('Code "' + code + '" already exists (' + (out.data.name || '') + '). Open for editing?')) {
                        location.href = siteUrl + '/customer/edit?type=' + encodeURIComponent(type) + '&code=' + encodeURIComponent(code);
                    }
                }
            })
            .catch(() => {});
    }

    // ── Phone/mobile duplicate check on blur ──
    function checkPhoneDup(phone, codeVal, warnEl) {
        warnEl.style.display = 'none';
        warnEl.textContent = '';
        if (!phone) return;
        const body = new URLSearchParams({ phone: phone, code: codeVal });
        fetch(siteUrl + '/api/customer/check-phone', {
            method: 'POST',
            body: body,
            headers: { 'X-CSRF-TOKEN': csrfToken }
        })
        .then(r => r.json())
        .then(out => {
            if (out.exists) {
                warnEl.textContent = 'Duplicate: ' + (out.details || 'Already in use');
                warnEl.style.display = 'block';
            }
        })
        .catch(() => {});
    }

    document.getElementById('fPhone').addEventListener('blur', function() {
        const code = document.querySelector('[name="code"]').value;
        checkPhoneDup(this.value.trim(), code, document.getElementById('phoneDup'));
    });

    document.getElementById('fMobile').addEventListener('blur', function() {
        const code = document.querySelector('[name="code"]').value;
        checkPhoneDup(this.value.trim(), code, document.getElementById('mobileDup'));
    });

    // ── ID No duplicate check on blur (staff only) ──
    @if($isStaffType)
    document.getElementById('fIdno').addEventListener('blur', function() {
        const idno = this.value.trim();
        const codeVal = document.querySelector('[name="code"]').value;
        const warnEl = document.getElementById('idnoDup');
        warnEl.style.display = 'none';
        warnEl.textContent = '';
        if (!idno) return;
        const body = new URLSearchParams({ idno: idno, code: codeVal });
        fetch(siteUrl + '/api/customer/check-idno', {
            method: 'POST',
            body: body,
            headers: { 'X-CSRF-TOKEN': csrfToken }
        })
        .then(r => r.json())
        .then(out => {
            if (out.exists) {
                warnEl.textContent = 'Duplicate ID No: already used by ' + (out.name || 'another record');
                warnEl.style.display = 'block';
            }
        })
        .catch(() => {});
    });
    @endif

    // ── Save ──
    document.getElementById('btnSave').addEventListener('click', async () => {
        const formData = new FormData(document.getElementById('customerForm'));
        formData.set('advanced_payload', JSON.stringify(advancedData || {}));
        const res = await fetch(siteUrl + '/api/customer/save', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-TOKEN': csrfToken }
        });
        const out = await res.json();
        if (!out.success) {
            showMsg(out.message || 'Save failed', false);
            return;
        }
        showMsg(out.message || 'Saved', true);
        const code = (out.data && out.data.code) ? out.data.code : formData.get('code');
        const name = (out.data && out.data.name) ? out.data.name : (formData.get('name') || '');
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage({ type: 'goldapp:customer-created', code: code, name: name }, '*');
        }
        setTimeout(() => {
            location.href = siteUrl + '/customer/edit?type=' + encodeURIComponent(type) + '&code=' + encodeURIComponent(code);
        }, 450);
    });

    // ── Delete ──
    @if($isEdit)
    function confirmDelete() {
        const code = document.querySelector('[name="code"]').value;
        if (!code) return;
        if (!confirm('Are you sure you want to DELETE "' + code + '"? This cannot be undone.')) return;
        fetch(siteUrl + '/api/customer/delete', {
            method: 'POST',
            body: new URLSearchParams({ code: code }),
            headers: { 'X-CSRF-TOKEN': csrfToken }
        })
        .then(r => r.json())
        .then(out => {
            if (!out.success) {
                showMsg(out.message || 'Delete failed', false);
                return;
            }
            showMsg(out.message || 'Deleted', true);
            setTimeout(() => {
                location.href = siteUrl + '/customer?type=' + encodeURIComponent(type) + '&list=1';
            }, 600);
        })
        .catch(err => {
            showMsg('Error: ' + err.message, false);
        });
    }
    @endif

    function openLookupPopup(kind) {
        const map = {
            route: siteUrl + '/customer/popup/routes',
            area: siteUrl + '/customer/popup/area',
            pcard: siteUrl + '/customer/popup/pcard'
        };
        const url = map[kind];
        if (!url) return;
        window.open(url, 'popup_' + kind, 'width=780,height=600,resizable=yes,scrollbars=yes');
    }

    function openAdvancedPopup() {
        const mode = (type === 'S') ? 'S' : 'C';
        const w = window.open(
            siteUrl + '/customer/popup/advanced?mode=' + encodeURIComponent(mode),
            'popup_advanced',
            'width=1200,height=760,resizable=yes,scrollbars=yes'
        );
        if (w) w.focus();
    }

    window.addEventListener('message', function(event) {
        const data = event && event.data ? event.data : null;
        if (!data || typeof data !== 'object') return;

        if (data.type === 'customer-advanced-request') {
            if (event.source && typeof event.source.postMessage === 'function') {
                event.source.postMessage({ type: 'customer-advanced-data', data: advancedData || {} }, '*');
            }
            return;
        }

        if (data.type === 'customer-advanced-save') {
            if (data.data && typeof data.data === 'object') {
                advancedData = data.data;
                showMsg('Advanced details updated.', true);
            }
        }
    });
</script>

</body>
</html>
