<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('js/goldapp-common.js') }}"></script>
<title>{{ $title ?? 'Enter Sales Bill Details' }}</title>
<script>
  @php
    $__curDb = trim((string) config('database.connections.mysql.database', ''));
    $__envDb = trim((string) env('DB_DATABASE', ''));
    $__isSecondaryDb = $__curDb !== '' && $__envDb !== '' && strcasecmp($__curDb, $__envDb) !== 0;
  @endphp
  window.SB_CONFIG = {
    siteUrl:   @json(request()->root()),
    currentDatabase: @json((string) config('database.connections.mysql.database', '')),
    mode:      @json($mode ?? 'bill'),
    rates: {
      GRATE:  {{ $rates['GRATE']  ?? 0 }},
      SRATE:  {{ $rates['SRATE']  ?? 0 }},
      OGRATE: {{ $rates['OGRATE'] ?? 0 }},
      OSRATE: {{ $rates['OSRATE'] ?? 0 }},
      BULRATE: {{ $rates['BULRATE'] ?? 0 }},
      BULTOUCH: {{ $rates['BULTOUCH'] ?? 99.5 }},
      THRATE: {{ $rates['THRATE'] ?? 0 }},
    },
    exchItems:  @json($exchItems ?? []),
    billTypes:  @json($billTypes ?? []),
    counters:   @json($counters ?? []),
    salesmen:   @json($salesmen ?? []),
    agents:     @json($agents ?? []),
    cashBanks:  @json($cashBanks ?? []),
    approvedBy: @json($approvedBy ?? []),
    states:     @json($states ?? []),
    mcRows:     @json($mcRows ?? []),
    wstgRows:   @json($wstgRows ?? []),
    software:   @json($software ?? []),
    access:     @json($access ?? []),
    quotationMode: @json(!empty($quotationMode)),
    isSecondaryDb: @json($__isSecondaryDb),
  };
</script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    font-size: 12px;
    background: #dfe3e8;
    color: #1a202c;
    overflow: hidden;
    height: 100vh;
  }

  /* Smooth transitions */
  input, select, button { transition: all 0.15s ease; }

  /* Modern scrollbar */
  ::-webkit-scrollbar { width: 6px; }
  ::-webkit-scrollbar-track { background: #f7fafc; }
  ::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 3px; }
  ::-webkit-scrollbar-thumb:hover { background: #a0aec0; }

  /* ===== MAIN WINDOW ===== */
  .main-window {
    background: #ffffff;
    border: 1px solid #aeb7c2;
    margin: 6px;
    border-radius: 4px;
    box-shadow: none;
    overflow: hidden;
    position: relative;
  }

  .title-bar {
    background: linear-gradient(135deg, #1e3a5f, #2c5282);
    color: white;
    padding: 6px 12px;
    font-weight: 700;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
    letter-spacing: 0.3px;
  }
  .title-bar .icon { width: 16px; height: 16px; background: #f6ad55; border-radius: 4px; }

  /* PB-style warning/message popup */
  .msg-modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.35);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 12000;
  }
  .msg-modal.active { display: flex; }
  .msg-box {
    width: min(420px, calc(100vw - 24px));
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-shadow: 0 18px 48px rgba(0,0,0,0.25);
    overflow: hidden;
  }
  .msg-box .head {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 700;
    font-size: 12px;
    color: #1e293b;
    padding: 8px 10px;
  }
  .msg-box .body {
    padding: 14px 12px;
    font-size: 12px;
    color: #334155;
    line-height: 1.45;
    white-space: pre-wrap;
  }
  .msg-box .foot {
    padding: 8px 10px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    background: #f8fafc;
  }
  .msg-box .ok-btn {
    min-width: 74px;
    height: 28px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
    color: #0f172a;
    font-weight: 600;
    cursor: pointer;
  }
  .msg-box .ok-btn:hover { background: #f1f5f9; }
  .msg-box .secondary-btn,
  .msg-box .primary-btn {
    min-width: 90px;
    height: 30px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
  }
  .msg-box .secondary-btn {
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #0f172a;
  }
  .msg-box .secondary-btn:hover { background: #f1f5f9; }
  .msg-box .primary-btn {
    border: 1px solid #0f766e;
    background: #0f766e;
    color: #fff;
  }
  .msg-box .primary-btn:hover { background: #115e59; }

  /* ===== TOP FIELDS ===== */
  .top-section {
    background: #e9edf2;
    padding: 6px 8px;
    display: grid;
    grid-template-columns: 1.25fr 1fr 1.2fr;
    gap: 2px 14px;
    border-bottom: 1px solid #c4ccd7;
  }
  .top-section .row {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 1px 0;
    min-height: 24px;
  }
  .top-section label {
    font-weight: 600;
    font-size: 11px;
    min-width: 74px;
    color: #4a5568;
  }
  .top-section input, .top-section select {
    font-size: 11px;
    padding: 2px 6px;
    border: 1px solid #9ea7b3;
    background: #fff;
    height: 22px;
    border-radius: 2px;
    color: #2d3748;
  }
  .top-section input:focus, .top-section select:focus {
    border-color: #4299e1;
    box-shadow: none;
    outline: none;
  }
  .top-section input.sm { width: 60px; }
  .top-section input.md { width: 100px; }
  .top-section input.lg { width: 140px; }
  .top-section .row.billno-row input.lg { flex: 1; min-width: 0; width: auto; }
  .top-section .row.billno-row .inline-check { margin-left: 6px; white-space: nowrap; }
  .top-section .row.customer-row { position: relative; }
  .top-section .row.customer-row #fCustomerCodeInput { width: 108px !important; text-transform: uppercase; }
  .top-section .row.customer-row #fCustomerName { flex: 1; min-width: 0; width: auto; }
  /* Make Address, C/o, GST, PAN fields stretch to fill column width */
  .top-section .row #fAddress { flex: 1; min-width: 0; width: auto; }
  .top-section .row #fCo,
  .top-section .row #fGstNo,
  .top-section .row #fPanNo { flex: 1; min-width: 0; width: auto; }
  .top-section .row #fCo {
    background: #111827;
    color: #f9fafb;
    border-color: #374151;
  }
  .top-section .row.customer-row #btnCustSearch,
  .top-section .row.customer-row #btnCustCreate {
    font-size: 10px;
    padding: 1px 6px;
    border: 1px solid #cbd5e0;
    border-radius: 3px;
    cursor: pointer;
    background: #fff;
    min-width: 24px;
    height: 22px;
  }
  .top-section .row.customer-row #btnCustCreate {
    color: #2f855a;
    font-weight: 700;
  }
  .top-section .row.customer-row #custSearchResults { position: absolute; top: 24px; left: 184px; width: 420px; max-height: 220px; overflow-y: auto; background: #111827; border: 1px solid #374151; border-radius: 6px; box-shadow: 0 6px 16px rgba(0,0,0,0.35); z-index: 500; display: none; font-size: 11px; color: #f9fafb; }
  .top-section .row.billtype-row { justify-content: flex-start !important; gap: 6px; }
  .top-section .row.billtype-row .nav-btns { display: inline-flex; gap: 3px; margin-right: 8px; }
  .top-section .row.billtype-row .nav-btns button { font-size: 10px; padding: 1px 6px; border: 1px solid #cbd5e0; border-radius: 3px; background: #fff; cursor: pointer; }
  .top-section .row.billtype-row #fBillType { flex: 1; min-width: 0; width: auto !important; }
  .top-section .tiny-label { min-width: auto; }
  .top-section .row #fMobNo { flex: 1; min-width: 0; width: auto; }
  .top-section .row.ob-row label.ob-label { min-width: 24px; }
  .top-section .row.ob-row #fOB {
    width: 88px;
    min-width: 88px;
    text-align: right;
    padding-left: 4px;
    padding-right: 6px;
  }
  .top-section .row.ob-row label.sm-label {
    min-width: 54px;
    margin-left: 4px;
  }
  .top-section .row.ob-row #fSmName {
    flex: 1;
    min-width: 0;
    width: auto !important;
  }
  .top-section .row.ob-row .tiny-label {
    margin-left: 4px;
  }
  .top-section .row.ob-row #fWs {
    width: 16px;
    height: 16px;
    flex: 0 0 auto;
  }
  .inline-check { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; color: #4a5568; }

  /* ===== ITEM TABLE ===== */
  .table-container {
    margin: 0 4px;
    border: 1px solid #c4ccd7;
    border-radius: 2px;
    background: #fff;
    overflow: hidden;
  }
  table.items {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
  }
  table.items thead th {
    background: #2d3748;
    color: #e2e8f0;
    padding: 5px 6px;
    border: 1px solid #4a5568;
    font-weight: 600;
    font-size: 10px;
    text-align: center;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  table.items tbody td {
    border: 1px solid #edf2f7;
    padding: 2px 4px;
    text-align: center;
    height: 22px;
  }
  table.items tbody tr:nth-child(odd) { background: #ffffff; }
  table.items tbody tr:nth-child(even) { background: #f7fafc; }
  table.items tbody tr:hover { background: #ebf8ff; }
  table.items tbody tr.empty { height: 20px; }
  table.items tbody tr.empty td { border-color: #edf2f7; }
  table.items tbody input {
    font-size: 11px; border: none; background: transparent; text-align: center; width: 100%; height: 100%;
  }
  table.items tbody input:focus { background: #fffff0; outline: 2px solid #4299e1; border-radius: 2px; }
  table.items tbody input.ic-code:focus { background: #fff7cc; outline: 2px solid #2563eb; }

  .table-footer {
    display: flex;
    align-items: center;
    background: #e9edf2;
    padding: 4px 8px;
    gap: 8px;
    font-size: 11px;
    font-weight: 600;
    color: #2d3748;
    border-top: 1px solid #c4ccd7;
  }
  .table-footer button {
    background: #fff;
    border: 1px solid #9ea7b3;
    border-radius: 2px;
    padding: 2px 12px;
    font-size: 11px;
    cursor: pointer;
    font-weight: 600;
    color: #2d3748;
  }
  .table-footer .tf-label { color:#744210; font-weight:700; }
  .table-footer .tfv { text-align: right; font-variant-numeric: tabular-nums; }
  .table-footer .tf-actions {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex: 0 0 34.0136%; /* first 6 item columns: 500 / 1470 */
  }
  .table-footer .tf-totals {
    display: grid;
    grid-template-columns: 45fr 110fr 110fr 110fr 95fr 120fr 50fr 130fr 110fr 90fr;
    align-items: center;
    flex: 1 1 auto;
  }
  .table-footer button:hover { background: #edf2f7; border-color: #a0aec0; }
  .table-footer button:active { transform: translateY(1px); }

  .info-bar {
    background: #e9edf2;
    padding: 3px 8px;
    font-size: 11px;
    font-weight: 600;
    display: grid;
    grid-template-columns: 220px 1fr 360px;
    align-items: center;
    color: #c53030;
    border-top: 1px solid #c4ccd7;
  }
  .info-bar .left { color: #1d4ed8; text-align: left; }
  .info-bar .center { text-align: center; display: inline-flex; gap: 10px; justify-content: center; align-items: center; }
  .info-bar .right { text-align: right; display: inline-flex; gap: 10px; justify-content: flex-end; align-items: center; }

  /* ===== BOTTOM SECTION ===== */
  .bottom-section {
    background: #e9edf2;
    padding: 5px 8px;
    display: grid;
    grid-template-columns: 220px 300px 340px 360px;
    gap: 0 14px;
    padding-right: 250px; /* reserve space for right action panel */
    font-size: 11px;
    border-top: 1px solid #c4ccd7;
    align-items: start;
  }
  .bottom-section > div {
    display: grid;
    grid-auto-rows: 24px;
    align-content: start;
  }
  .bottom-section .row {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 0;
    min-height: 24px;
    white-space: nowrap;
  }
  .bottom-section label {
    font-weight: 600;
    font-size: 11px;
    min-width: 76px;
    color: #4a5568;
    line-height: 1;
  }
  .bottom-section input, .bottom-section select {
    font-size: 11px;
    padding: 1px 4px;
    border: 1px solid #9ea7b3;
    background: #fff;
    height: 21px;
    width: 70px;
    border-radius: 2px;
    color: #2d3748;
  }
  .bottom-section input:focus, .bottom-section select:focus {
    border-color: #4299e1;
    box-shadow: none;
    outline: none;
  }
  .bottom-section input.w50 { width: 50px; }
  .bottom-section input.w80 { width: 82px; }
  .bottom-section select { width: 118px; }
  .bottom-section .row input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin: 0 6px 0 0;
  }
  .bottom-section .row .chk-text {
    display: inline-block;
    min-width: 92px;
    color: #1f2937;
    font-weight: 500;
  }

  /* ===== RIGHT BUTTONS ===== */
  .action-buttons {
    position: absolute;
    right: 10px;
    bottom: 12px;
    top: auto;
    display: flex;
    flex-direction: column;
    gap: 4px;
    border: none;
    padding: 0;
    background: transparent;
    border-radius: 0;
    box-shadow: none;
    z-index: 20;
  }
  .action-buttons button {
    background: #fff;
    border: 1px solid #9ea7b3;
    border-radius: 4px;
    padding: 4px 16px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    min-width: 110px;
    font-family: inherit;
    color: #2d3748;
  }
  .secondary-sync-box {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 110px;
    padding: 6px 8px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 11px;
    color: #334155;
  }
  .secondary-sync-box input[type="checkbox"] {
    width: 14px;
    height: 14px;
    margin: 0;
  }
  .quote-mode-box {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    min-width: 110px;
    padding: 6px 8px;
    background: #fff7ed;
    border: 1px solid #fdba74;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    color: #9a3412;
  }
  .quote-mode-box input[type="checkbox"] {
    width: 14px;
    height: 14px;
    margin: 0;
  }
  .qtn-select-btn {
    min-width: 110px;
    padding: 6px 8px;
    border: 1px solid #fdba74;
    border-radius: 6px;
    background: #fff7ed;
    color: #9a3412;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
  }
  .qtn-select-btn:hover { background: #ffedd5; }
  .qtn-modal {
    width: min(760px, calc(100vw - 40px));
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    box-shadow: 0 18px 48px rgba(0,0,0,0.25);
    overflow: hidden;
  }
  .qtn-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 10px 12px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
  }
  .qtn-modal-head strong { font-size: 13px; color: #0f172a; }
  .qtn-modal-head input {
    width: 240px;
    height: 30px;
    padding: 4px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
  }
  .qtn-modal-table-wrap {
    max-height: 380px;
    overflow: auto;
  }
  table.qtn-list {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
  }
  table.qtn-list th,
  table.qtn-list td {
    border-bottom: 1px solid #e2e8f0;
    padding: 8px 10px;
    text-align: left;
  }
  table.qtn-list th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    text-transform: uppercase;
  }
  table.qtn-list tbody tr:hover { background: #fff7ed; }
  .qtn-pick-btn {
    height: 28px;
    padding: 0 12px;
    border: 1px solid #0f766e;
    border-radius: 6px;
    background: #0f766e;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
  }
  .qtn-modal-foot {
    padding: 10px 12px;
    display: flex;
    justify-content: flex-end;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
  }
  .qtn-empty {
    padding: 24px;
    text-align: center;
    color: #64748b;
  }
  .action-buttons button:hover { background: #edf2f7; border-color: #a0aec0; }
  .action-buttons button:active { transform: translateY(1px); }
  .action-buttons button.highlight {
    background: #ebf8ff;
    border-color: #4299e1;
    color: #2b6cb0;
  }
  .action-buttons button.highlight:hover {
    background: #bee3f8;
  }

  /* ===== MODAL OVERLAY ===== */
  .modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.35);
    backdrop-filter: blur(2px);
    z-index: 1000;
    justify-content: center;
    align-items: center;
  }
  .modal-overlay.active { display: flex; }

  /* ===== EXCHANGE WINDOW ===== */
  .exchange-window {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    width: 820px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    animation: popIn 0.2s ease-out;
    overflow: hidden;
  }
  @keyframes popIn {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
  }
  .exchange-title {
    background: linear-gradient(135deg, #1e3a5f, #2c5282);
    color: white;
    padding: 6px 12px;
    font-weight: 700;
    font-size: 13px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    letter-spacing: 0.3px;
  }
  .exchange-title .close-btn {
    background: rgba(255,255,255,0.15);
    color: white;
    border: none;
    width: 22px;
    height: 20px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    border-radius: 4px;
  }
  .exchange-title .close-btn:hover { background: #fc8181; }

  .exchange-body {
    padding: 6px;
  }

  table.exchange-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    border-radius: 4px;
    overflow: hidden;
  }
  table.exchange-table thead th {
    background: linear-gradient(to bottom, #975a16, #744210);
    color: #fefcbf;
    padding: 5px 5px;
    border: 1px solid #8b6914;
    font-weight: 600;
    font-size: 10px;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.3px;
  }
  table.exchange-table tbody td {
    border: 1px solid #e2e8f0;
    padding: 2px 4px;
    text-align: center;
    height: 22px;
    background: #fffff0;
  }
  table.exchange-table tbody tr.data-row td {
    background: #fefcbf;
  }
  table.exchange-table tbody tr.data-row td.code { background: #fef9c3; text-align: left; font-weight: 500; }
  table.exchange-table tbody tr.data-row td.name { text-align: left; font-weight: 600; color: #744210; }
  table.exchange-table tbody input {
    font-size: 11px; border: none; background: transparent; text-align: center; width: 100%;
  }
  table.exchange-table tbody input:focus { background: #fffff0; outline: 2px solid #d69e2e; border-radius: 2px; }
  table.exchange-table tbody select { font-size: 10px; border: none; background: transparent; }

  .exchange-footer-bar {
    display: flex;
    align-items: center;
    background: #fef9c3;
    padding: 4px 8px;
    gap: 8px;
    font-size: 11px;
    font-weight: 600;
    color: #744210;
    border-top: 1px solid #d69e2e;
  }
  .exchange-footer-bar button {
    background: #fff;
    border: 1px solid #d69e2e;
    border-radius: 3px;
    padding: 2px 12px;
    font-size: 11px;
    cursor: pointer;
    font-weight: 600;
    color: #744210;
  }
  .exchange-footer-bar button:hover { background: #fefcbf; }
  .exchange-footer-bar .count {
    background: #fefcbf;
    border: 1px solid #d69e2e;
    border-radius: 10px;
    padding: 1px 10px;
    font-weight: 700;
    color: #744210;
  }
  .exchange-footer-bar .s-badge {
    color: #c53030;
    font-weight: 700;
    font-size: 13px;
  }

  .exchange-totals {
    background: #faf5e4;
    padding: 6px 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    font-weight: 700;
    border-top: 2px solid #d69e2e;
    color: #744210;
  }
  .exchange-totals .group { display: flex; gap: 20px; }
  .exchange-totals .val { color: #1a202c; }

  .exchange-bottom {
    padding: 8px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    font-weight: 600;
    background: #fff;
    border-top: 1px solid #e2e8f0;
  }
  .exchange-bottom .info-group { display: flex; gap: 30px; color: #4a5568; }
  .exchange-btn-group {
    display: flex;
    gap: 8px;
  }
  .exchange-btn-group button {
    padding: 6px 24px;
    font-size: 12px;
    font-weight: 700;
    border: none;
    border-radius: 4px;
    cursor: pointer;
  }
  .exchange-btn-group button.save {
    background: #48bb78;
    color: #fff;
  }
  .exchange-btn-group button.save:hover { background: #38a169; }
  .exchange-btn-group button.cancel {
    background: #fc8181;
    color: #fff;
  }
  .exchange-btn-group button.cancel:hover { background: #f56565; }
  .exchange-btn-group button:active { transform: translateY(1px); }

  /* ===== SALES RETURN WINDOW ===== */
  .salesreturn-window {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    width: 900px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    animation: popIn 0.2s ease-out;
    overflow: hidden;
  }
  .sr-title {
    background: linear-gradient(135deg, #1e3a5f, #2c5282);
    color: white;
    padding: 6px 12px;
    font-weight: 700;
    font-size: 13px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    letter-spacing: 0.3px;
  }

  .sr-body { padding: 6px; }

  table.sr-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    border-radius: 4px;
    overflow: hidden;
  }
  table.sr-table thead th {
    background: linear-gradient(to bottom, #975a16, #744210);
    color: #fefcbf;
    padding: 5px 5px;
    border: 1px solid #8b6914;
    font-weight: 600;
    font-size: 10px;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.3px;
  }
  table.sr-table tbody td {
    border: 1px solid #e2e8f0;
    padding: 2px 4px;
    text-align: center;
    height: 22px;
    background: #fefcbf;
  }
  table.sr-table tbody input {
    font-size: 11px; border: none; background: transparent; text-align: center; width: 100%;
  }
  table.sr-table tbody input:focus { background: #fffff0; outline: 2px solid #d69e2e; border-radius: 2px; }
  table.sr-table tbody select { font-size: 10px; border: none; background: transparent; }

  .sr-footer-bar {
    display: flex;
    align-items: center;
    background: #fef9c3;
    padding: 4px 8px;
    gap: 8px;
    font-size: 11px;
    font-weight: 600;
    color: #744210;
    border-top: 1px solid #d69e2e;
  }
  .sr-footer-bar button {
    background: #fff;
    border: 1px solid #d69e2e;
    border-radius: 3px;
    padding: 2px 12px;
    font-size: 11px;
    cursor: pointer;
    font-weight: 600;
    color: #744210;
  }
  .sr-footer-bar button:hover { background: #fefcbf; }
  .sr-footer-bar .count {
    background: #fefcbf;
    border: 1px solid #d69e2e;
    border-radius: 10px;
    padding: 1px 10px;
    font-weight: 700;
    color: #744210;
  }

  .sr-eva-bar {
    background: #276749;
    color: #c6f6d5;
    padding: 3px 8px;
    font-size: 11px;
    font-weight: 600;
    text-align: right;
    display: flex;
    justify-content: flex-end;
    gap: 20px;
  }

  .sr-bottom {
    background: #faf5e4;
    padding: 6px 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    font-weight: 700;
    flex-wrap: wrap;
    gap: 6px;
    border-top: 1px solid #d69e2e;
    color: #744210;
  }
  .sr-bottom .field-group {
    display: flex;
    align-items: center;
    gap: 4px;
  }
  .sr-bottom input {
    font-size: 11px;
    padding: 2px 4px;
    border: 1px solid #d69e2e;
    border-radius: 3px;
    height: 20px;
    width: 50px;
    background: #fff;
  }
  .sr-bottom input:focus {
    border-color: #b7791f;
    box-shadow: 0 0 0 2px rgba(214,158,46,0.2);
    outline: none;
  }
  .sr-bottom input.wide { width: 80px; }
  .sr-bottom label { font-weight: 700; font-size: 11px; color: #744210; }

  .sr-btn-group {
    display: flex;
    gap: 8px;
  }
  .sr-btn-group button {
    padding: 6px 24px;
    font-size: 12px;
    font-weight: 700;
    border: none;
    border-radius: 4px;
    cursor: pointer;
  }
  .sr-btn-group button.save {
    background: #48bb78;
    color: #fff;
  }
  .sr-btn-group button.save:hover { background: #38a169; }
  .sr-btn-group button.cancel {
    background: #fc8181;
    color: #fff;
  }
  .sr-btn-group button.cancel:hover { background: #f56565; }
  .sr-btn-group button:active { transform: translateY(1px); }

  /* Checkbox style */
  input[type="checkbox"] { margin: 0 2px; accent-color: #4299e1; }

  /* Customer search dropdown */
  #custSearchResults .cust-row {
    padding: 6px 8px;
    cursor: pointer;
    border-bottom: 1px solid #374151;
  }
  #custSearchResults .cust-row:hover {
    background: #1f2937;
  }
  #custSearchResults .cust-row .code {
    font-weight: 600;
    color: #93c5fd;
    margin-right: 6px;
  }
  #custSearchResults .cust-row .mob {
    color: #718096;
    font-size: 10px;
    margin-left: 6px;
  }

  /* ===== GULL STYLE OVERRIDES ===== */
  :root {
    --g-bg: #f4f7fb;
    --g-surface: #ffffff;
    --g-border: #e3e8ef;
    --g-text: #384250;
    --g-muted: #7b8794;
    --g-primary: #5b6dee;
    --g-primary-2: #6f7ff3;
    --g-header: #2f3d8f;
    --g-header-2: #4457cb;
  }

  body {
    background: radial-gradient(circle at 10% -10%, #eef3ff 0%, #f4f7fb 40%, #edf2f8 100%);
    color: var(--g-text);
  }

  .main-window {
    background: var(--g-surface);
    border: 1px solid var(--g-border);
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(33, 52, 89, 0.10);
    margin: 10px;
  }

  .title-bar {
    background: linear-gradient(135deg, var(--g-header), var(--g-header-2));
    padding: 10px 14px;
    font-size: 14px;
  }
  .title-bar .icon {
    background: #ffb548;
    border-radius: 5px;
  }

  .top-section,
  .table-footer,
  .info-bar,
  .bottom-section {
    background: #f9fbff;
    border-color: var(--g-border);
  }

  .top-section {
    padding: 10px;
    gap: 6px 14px;
  }

  .top-section label,
  .bottom-section label {
    color: var(--g-muted);
    font-weight: 600;
  }

  .top-section input,
  .top-section select,
  .bottom-section input,
  .bottom-section select {
    border: 1px solid #d6deea;
    border-radius: 7px;
    height: 26px;
    background: #fff;
    box-shadow: inset 0 1px 2px rgba(17, 24, 39, 0.03);
  }

  .top-section input:focus,
  .top-section select:focus,
  .bottom-section input:focus,
  .bottom-section select:focus {
    border-color: #a9b7ff;
    box-shadow: 0 0 0 3px rgba(91, 109, 238, 0.14);
  }

  .table-container {
    margin: 0 8px;
    border: 1px solid var(--g-border);
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(32, 55, 92, 0.05);
  }

  table.items thead th {
    background: linear-gradient(180deg, #364891, #2d3d7b);
    border-color: #42529e;
    color: #f7f9ff;
    font-size: 10px;
  }

  table.items tbody tr:nth-child(odd) { background: #ffffff; }
  table.items tbody tr:nth-child(even) { background: #f7faff; }
  table.items tbody tr:hover { background: #edf3ff; }
  table.items tbody td { border-color: #edf1f7; }

  .table-footer {
    padding: 6px 10px;
  }

  .table-footer button,
  .top-section .row.billtype-row .nav-btns button,
  .top-section .row.customer-row #btnCustSearch,
  .top-section .row.customer-row #btnCustCreate {
    border-radius: 8px;
    border: 1px solid #d0d9ea;
    background: #fff;
    color: #3f4a5b;
  }

  .table-footer button:hover,
  .top-section .row.billtype-row .nav-btns button:hover,
  .top-section .row.customer-row #btnCustSearch:hover,
  .top-section .row.customer-row #btnCustCreate:hover {
    background: #eef3ff;
    border-color: #bfcaf0;
  }

  .info-bar {
    padding: 6px 10px;
    font-weight: 700;
  }

  .bottom-section {
    border-top: 1px solid var(--g-border);
  }

  .action-buttons button {
    border-radius: 10px;
    border: 1px solid #cfd8ec;
    min-width: 136px;
    height: 34px;
    background: #fff;
    color: #2f3a4f;
  }

  .action-buttons button.highlight {
    background: linear-gradient(180deg, #f5f7ff, #eaf0ff);
    border-color: #9eb0ff;
    color: #35459f;
  }

  .action-buttons button:hover {
    background: #eff4ff;
    border-color: #9fb3f7;
  }

  /* ===== RESPONSIVE ===== */
  @media (max-width: 1100px) {
    .top-section { grid-template-columns: 1fr 1fr; }
    .bottom-section { grid-template-columns: 1fr 1fr; padding-right: 8px; }
    .info-bar { grid-template-columns: 180px 1fr 1fr; gap: 2px 10px; }
    .action-buttons { position: static; flex-direction: row; flex-wrap: wrap; justify-content: flex-end; border-top: 1px solid #c4ccd7; padding: 6px 8px; background: #e9edf2; gap: 4px; }
    .action-buttons button { min-width: 90px; }
    .bottom-section { padding-right: 8px; }
  }

  @media (max-width: 768px) {
    body { overflow: auto; height: auto; }
    .main-window { margin: 4px; }
    .top-section { grid-template-columns: 1fr; }
    .info-bar { grid-template-columns: 1fr 1fr; }
    .bottom-section { grid-template-columns: 1fr 1fr; padding-right: 8px; }
    .action-buttons { flex-direction: row; flex-wrap: wrap; justify-content: center; }
    .action-buttons button { flex: 1 1 auto; min-width: 80px; }
  }

  @media (max-width: 540px) {
    .top-section, .bottom-section, .info-bar { grid-template-columns: 1fr; }
    .info-bar { grid-template-columns: 1fr; }
    .top-section input.sm, .top-section input.md, .top-section input.lg { width: 100%; }
    .title-bar { font-size: 11px; padding: 5px 8px; }
    .table-footer { overflow-x: auto; }
  }
</style>
@include('partials.print-layout-head')
</head>
<body>

<!-- ===== MAIN BILLING WINDOW ===== -->
<div class="main-window">
  <div class="title-bar">
    <div class="icon"></div>
    <span id="titleText">{{ $title ?? 'Enter Sales Bill Details' }} - {{ max(1, (int) request()->query('n', 1)) }}</span>
  </div>

  <!-- Top Fields -->
  <div class="top-section">
    <div>
      <div class="row billno-row"><label id="lblBillNo">Bill No</label><input id="fBillNo" class="lg" value="" readonly><span class="inline-check"><input id="fManualBillNo" type="checkbox" style="width:16px;height:16px;"><span>Manual BNo</span></span></div>
      <div class="row"><label>Rate/gm</label><input id="fRatePerGm" class="sm" value="{{ number_format((float)($rates['GRATE'] ?? 0),2,'.','') }}"><input id="fRatePurity" class="sm" value="99.5"><label>Counter</label><select id="fCounter" style="width:80px;font-size:11px;height:20px;"><option value="">--</option></select></div>
      <div class="row customer-row"><label>Customer</label><input id="fCustomerCodeInput" class="sm" value="" autocomplete="off"><input id="fCustomerName" class="lg" value="" autocomplete="off"><input type="hidden" id="fCustomerCode" value=""><button id="btnCustSearch" title="Search Customer">^</button><button id="btnCustCreate" title="Create Customer">+</button>
        <div id="custSearchResults"></div>
      </div>
      <div class="row"><label>Mob No</label><input id="fMobNo" class="md" value=""></div>
      <div class="row"><label>Address</label><input id="fAddress" class="lg" value=""></div>
    </div>
    <div>
      <div class="row billtype-row">
        <span class="nav-btns">
          <button type="button" id="btnPrev">&lt;</button>
          <button type="button" id="btnNext">&gt;</button>
        </span>
        <label>Bill Type</label>
        <select id="fBillType" style="width:120px;"></select>
      </div>
      <div class="row"><label>C/o</label><select id="fCo" class="md"><option value="">--</option></select></div>
      <div class="row"><label id="lblTaxNo">GST No</label><input id="fGstNo" class="md" value=""></div>
      <div class="row"><label id="lblPan">PAN/Adh</label><input id="fPanNo" class="md" value=""></div>
    </div>
    <div>
      <div class="row"><label>Date</label><input id="fBillDate" type="date" class="md" value="" style="background:#fefce8;"><span id="fBillTime" style="font-size:10px;margin-left:6px;"></span></div>
      <div class="row"><label>Op.Wgt</label><input id="fOpWgt" class="sm" value=".000"><label>Rate/8 gm</label><input id="fRatePer8gm" class="sm" value="{{ number_format(((float)($rates['GRATE'] ?? 0))*8,2,'.','') }}"></div>
      <div class="row ob-row"><label class="ob-label">OB</label><input id="fOB" class="sm" value=".00"><label class="sm-label">SM Name</label><select id="fSmName" style="font-size:11px;height:20px;"><option value="">--</option></select><label class="tiny-label">WS</label><input id="fWs" type="checkbox"></div>
      <div class="row" id="rowPrevAdv"><label>Prev.Adv</label><input id="fPrevAdv" class="sm" value=".00"><span style="flex:1 1 auto;"></span><button type="button" id="btnRateAll" style="font-size:10px;padding:2px 8px;border:1px solid #cbd5e0;border-radius:3px;background:#fff;cursor:pointer;">Rate All</button></div>
    </div>
  </div>

  <!-- Items Table -->
  <div class="table-container">
    <datalist id="itemCodeList">
      @foreach(($exchItems ?? []) as $it)
        <option value="{{ strtoupper(trim((string)($it['code'] ?? ''))) }}" label="{{ strtoupper(trim((string)($it['name'] ?? ''))) }}"></option>
      @endforeach
    </datalist>
    <table class="items">
      <thead>
        <tr>
          <th style="width:60px;">Item code</th>
          <th style="width:150px;">Item Name</th>
          <th style="width:70px;">Purity</th>
          <th style="width:70px;">Model</th>
          <th style="width:70px;">HSN</th>
          <th style="width:80px;">HUID</th>
          <th style="width:45px;">Qty</th>
          <th style="width:110px;">Weight in Gm.</th>
          <th style="width:110px;">Stone Weight</th>
          <th style="width:110px;">Net Wgt in Gm.</th>
          <th style="width:95px;">Stone Price</th>
          <th style="width:120px;">Wastage in Gm.</th>
          <th style="width:50px;">MC %</th>
          <th style="width:130px;">Making Charge</th>
          <th style="width:110px;">Amount</th>
          <th style="width:90px;">Rate</th>
        </tr>
      </thead>
      <tbody id="itemsTbody">
        <!-- rows rendered by JS -->
      </tbody>
    </table>
  </div>

  <!-- Table Footer -->
  <div class="table-footer">
    <div class="tf-actions">
      <button type="button" id="btnAddItem">Add</button>
      <button type="button" id="btnDeleteItem">Delete</button>
      <span class="tf-label">Total :</span>
    </div>
    <div class="tf-totals">
      <span id="ftQty" class="tfv">0</span>
      <span id="ftWeight" class="tfv">0.000</span>
      <span id="ftStoneWgt" class="tfv">0.000</span>
      <span id="ftNetWgt" class="tfv">.000</span>
      <span id="ftStonePrice" class="tfv">.00</span>
      <span id="ftWastage" class="tfv">.00</span>
      <span class="tfv">0.00</span>
      <span id="ftMcAmt" class="tfv">0.00</span>
      <span id="ftAmount" class="tfv">0.00</span>
      <span id="ftRate" class="tfv">0.00</span>
    </div>
  </div>

  <!-- Info Bar -->
  <div class="info-bar">
    <div class="left">Total Items <span id="infoItemCount">0</span></div>
    <div class="center">
      <span id="infoQtyWgt">0 / 0.000</span>
      <span>Stock : EVA :</span>
      <span style="color:#38a169;" id="infoEva">0.00</span>
      <span>%</span>
    </div>
    <div class="right">
      <span>VA:</span>
      <span>Total VA :</span>
      <span id="infoTotalVa">0.00</span>
    </div>
  </div>

  <!-- Bottom Section -->
  <div class="bottom-section">
    <!-- Column 1 -->
    <div>
      <div class="row"><label>Bill Total</label><input id="fBillTotal" class="w80" value=".00" readonly></div>
      <div class="row"><label>Tax Amt</label><input id="fTaxPerc" class="w50" value="{{ $__isSecondaryDb ? '0' : '3' }}"{{ $__isSecondaryDb ? '' : ' readonly' }}><input id="fTaxAmt" class="w50" value=".00" readonly></div>
      <div class="row"><label>Discount</label><input id="fDiscount" value="" autocomplete="off"><input id="fDiscountPerc" class="w50" value="0.00" autocomplete="off"></div>
      <div class="row"><label>Grand Amt</label><input id="fGrandAmt" class="w80" value=".00" readonly></div>
      <div class="row"><label>Purch.Tax</label><input id="fPurchTaxPerc" class="w50" value="."><input id="fPurchTaxAmt" class="w50" value=".00"></div>
      <div class="row"><label>Cash/Bank</label><select id="fCashBank"><option>CASH IN HAND</option></select></div>
      <div class="row"><label>Chq Bank</label><select id="fChqBank" style="width:120px;"><option></option></select></div>
      <div class="row"><label>Agent</label><select id="fAgent" style="width:120px;"><option></option></select></div>
      <div class="row"><label>Approved by</label><select id="fApprovedBy" style="width:120px;"><option></option></select></div>
    </div>

    <!-- Column 2 -->
    <div>
      <div class="row"><label>Repair</label><input id="fRepair" class="w80" value=".00"></div>
      <div class="row"><label>Cess</label><input id="fCessPerc" class="w50" value="0."><input id="fCessAmt" class="w50" value=".00"></div>
      <div class="row"><label>Fancy Amt</label><input id="fFancyAmt" class="w80" value=".00"></div>
      <div class="row"><label>Schm Less</label><input id="fSchemeAmt" class="w80" value=".00"><select id="fSchemeLedger" class="w80"><option value="APP">APP</option><option value="SCHMAMT">GP</option></select></div>
      <div class="row"><label>TCS</label><input id="fTcsPerc" class="w50" value="0.00"><input id="fTcsAmt" class="w50" value=".00"></div>
      <div class="row"><label></label><input id="fInterstate" type="checkbox"><span class="chk-text">Interstate</span></div>
      <div class="row" style="display:none"><label></label><input id="fLoan" type="checkbox"><span class="chk-text">Loan</span></div>
      <div class="row" style="display:none"><label></label><input id="fAddBc" type="checkbox"><span class="chk-text">Add BC</span></div>
      <div class="row" style="display:none"><label></label><input id="fCcardPdc" type="checkbox"><span class="chk-text">CCard PDC ?</span></div>
    </div>

    <!-- Column 3 -->
    <div>
      <div class="row"><label>Exchange</label><input id="fExchangeAmt" class="w80" value="0.00" readonly></div>
      <div class="row"><label>HMC</label><input id="fHmc" class="w80" value=".00"></div>
      <div class="row"><label id="lblTotalRcvd">Total Rcvd</label><input id="fTotalRcvd" class="w80" value=".00" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" name="fTotalRcvd_nohistory"></div>
      <div class="row"><label>Net Amt</label><input id="fNetAmt" class="w80" value=".00" readonly> CB: <input id="fCb" class="w50" value=".00"></div>
      <div class="row"><label>Note</label><input id="fNote" style="width:150px;" value=""></div>
      <div class="row"><label>CCardAmt</label><input id="fCcardAmt" value=""> BC: <input id="fBcPerc" class="w50" value=".00"><input id="fBcAmt" class="w50" value=".00"></div>
      <div class="row"><label>Chq.Amt/Dt</label><input id="fChqAmt" class="w50" value=".00"><input id="fChqDate" type="date" class="w80" value=""></div>
      <div class="row"><label>Chq. No</label><input id="fChqNo" class="w80" value=""><input id="fPdc" type="checkbox"><span>PDC</span></div>
      <div class="row"><label>Veh. No.</label><input id="fVehNo" class="w80" value=""></div>
      <div class="row"><label></label><label>Scheme Bal</label><input id="fSchemeBal" class="w80" value=""></div>
    </div>

    <!-- Column 4 -->
    <div>
      <div class="row"><label style="min-width:auto;">Redeem Points</label><input id="fRedeemPoints" type="checkbox"><label>Sales Ret.</label><input id="fSalesRetAmt" class="w80" value="0.00" readonly></div>
      <div class="row"><label></label><label>Net Total</label><input id="fNetTotal" class="w80" value=".00" readonly></div>
      <div class="row"><label></label><input id="fCredit" type="checkbox"><span>Credit</span><label style="margin-left:10px;">Balance</label><input id="fBalance" class="w80" value=".00" readonly></div>
      <div class="row"><label>Due Date</label><input id="fDueDate" type="date" class="w80" value=""></div>
      <div class="row"><label>PC Points</label><input id="fPcPoints" class="w80" value=""></div>
      <div class="row"><label>PC Amt</label><input id="fPcAmt" class="w80" value=""></div>
      <div class="row"><label>Supply Place</label><input id="fSupplyPlace" class="w80" value=""></div>
      <div class="row"><label id="lblState">State</label><select id="fState" style="width:100px;"><option></option></select></div>
      <div class="row"><label>Distance</label><input id="fDistance" class="w80" value=""></div>
      <div class="row"><label></label><input id="fSendSms" type="checkbox"><span>Send Sms</span></div>
    </div>
  </div>
  <!-- Action Buttons (right side) -->
  <div class="action-buttons">
    <button type="button" class="highlight" id="btnExchange">Exchange</button>
    <button type="button" class="highlight" id="btnSalesReturn">Sales Return</button>
    <button type="button" id="btnQuotationSelect" class="qtn-select-btn">Qtn Select</button>
    <label class="quote-mode-box" for="fQuotationMode" style="display:none;">
      <input id="fQuotationMode" type="checkbox">
      <span>Quotation</span>
    </label>
    <button type="button" id="btnSave">Save</button>
    <button type="button" id="btnShortPrint">Short Print</button>
    <button type="button" id="btnExit">Exit</button>
    <button type="button" id="btnQtnPrint">Qtn Print</button>
  </div>
</div>

<div class="modal-overlay" id="quotationSelectModal">
  <div class="qtn-modal">
    <div class="qtn-modal-head">
      <strong>Select Quotation</strong>
      <input id="quotationSearchInput" type="text" placeholder="Search quotation / customer / mobile">
    </div>
    <div class="qtn-modal-table-wrap">
      <table class="qtn-list">
        <thead>
          <tr>
            <th style="width:16%;">Qtn No</th>
            <th style="width:16%;">Date</th>
            <th>Customer</th>
            <th style="width:18%;">Mobile</th>
            <th style="width:14%; text-align:right;">Amount</th>
            <th style="width:12%;">Action</th>
          </tr>
        </thead>
        <tbody id="quotationListBody"></tbody>
      </table>
    </div>
    <div class="qtn-modal-foot">
      <button type="button" id="btnQuotationClose">Close</button>
    </div>
  </div>
</div>


<!-- ===== EXCHANGE MODAL ===== -->
<div class="modal-overlay" id="exchangeModal">
  <div class="exchange-window">
    <div class="exchange-title">
      <span>Enter Exchange Details</span>
      <button class="close-btn" id="btnExchClose">&#10005;</button>
    </div>
    <div class="exchange-body">
      <table class="exchange-table">
        <thead>
          <tr>
            <th style="width:50px;">Item Code</th>
            <th style="width:140px;">Item Name</th>
            <th>Rate</th>
            <th>Qty</th>
            <th>Weight in Gm.</th>
            <th>Mud Less</th>
            <th>Stone Weight</th>
            <th>Stone Price</th>
            <th>Touch %</th>
            <th>Less %</th>
            <th>Less Weight</th>
            <th>Extra wgt Rate</th>
            <th>Net Weight</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody id="exchTbody">
          <!-- rows rendered by JS -->
        </tbody>
      </table>
    </div>
    <div class="exchange-footer-bar">
      <button id="btnExchAdd">Add</button>
      <button id="btnExchDelete">Delete</button>
      <span class="count" id="exCount">0</span>
      <span class="s-badge">S</span>
      <span style="flex:1;"></span>
      <span style="color:#744210;">Total :</span>
      <span id="exFtQty">0</span>
      <span id="exFtWeight">0.000</span>
      <span id="exFtMudLess">0.000</span>
      <span id="exFtStoneWgt">0.000</span>
      <span id="exFtStonePrice">0.00</span>
      <span style="flex:0.3;"></span>
      <span id="exFtLessWgt">0.000</span>
      <span style="flex:0.3;"></span>
      <span id="exFtNetWgt">0.000</span>
      <span id="exFtAmt">0.00</span>
    </div>
    <div class="exchange-totals">
      <div class="group">
        <span>Bill Amt : <span class="val" id="exBillAmt">0.00</span></span>
        <span>Ex.Amt : <span class="val" id="exExAmt">0.00</span></span>
        <span>Net : <span class="val" id="exNet">0.00</span></span>
      </div>
      <button id="btnExchRecalc" style="padding:3px 16px;font-size:11px;border:1px solid #d69e2e;border-radius:3px;cursor:pointer;background:#fff;color:#744210;font-weight:600;">Recalc</button>
    </div>
    <div class="exchange-bottom">
      <div class="info-group">
        <span>Sale Wgt : <span id="exSaleWgt">0.000</span></span>
      </div>
      <div class="exchange-btn-group">
        <button class="save" id="btnExchSave">Save</button>
        <button class="cancel" id="btnExchCancel">Cancel</button>
      </div>
    </div>
  </div>
</div>


<!-- ===== SALES RETURN MODAL ===== -->
<div class="modal-overlay" id="salesReturnModal">
  <div class="salesreturn-window">
    <div class="sr-title">
      <span>Enter Sales Returns</span>
      <button class="close-btn" id="btnSrClose" style="background:rgba(255,255,255,0.15);color:white;border:none;width:22px;height:20px;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;border-radius:4px;">&#10005;</button>
    </div>
    <div class="sr-body">
      <table class="sr-table">
        <thead>
          <tr>
            <th style="width:55px;">Item code</th>
            <th style="width:140px;">Item Name</th>
            <th>Model</th>
            <th>Qty</th>
            <th>Weight in Gm.</th>
            <th>Stone Weight</th>
            <th>Net Wgt in Gm.</th>
            <th>Stone Price</th>
            <th>Wastage</th>
            <th>MC%</th>
            <th>Making Charge</th>
            <th>Rate Rs.</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody id="srTbody">
          <!-- rows rendered by JS -->
        </tbody>
      </table>
    </div>

    <div style="background:#fef9c3;padding:2px 6px;font-size:11px;border-top:1px solid #d69e2e;">
      <div class="sr-footer-bar" style="background:transparent;padding:0;">
        <button id="btnSrAdd">Add</button>
        <button id="btnSrDelete">Delete</button>
        <button id="btnSrSelectBill" style="background:#2b6cb0;color:#fff;border:none;padding:2px 7px;border-radius:3px;cursor:pointer;font-size:11px;">Select from Bill</button>
        <span class="count" id="srCount">0</span>
        <span style="flex:1;"></span>
        <span style="color:#744210;">Total :</span>
        <span id="srFtQty">0</span>
        <span id="srFtWeight">0.000</span>
        <span id="srFtStoneWgt">0.000</span>
        <span id="srFtNetWgt">.000</span>
        <span id="srFtStonePrice">0.00</span>
        <span id="srFtWastage">0.000</span>
        <span>%</span>
        <span id="srFtMc">0.00</span>
        <span style="flex:0.5;"></span>
        <span id="srFtAmt">0.00</span>
      </div>
    </div>

    <div class="sr-eva-bar">
      <span>EVA :</span>
      <span style="flex:1;"></span>
      <span>TVA :</span>
      <span id="srTotalVa">0.00</span>
    </div>

    <div class="sr-bottom">
      <div class="field-group">
        <label>Discount :</label>
        <input id="srDiscount" value=".00">
      </div>
      <div class="field-group">
        <label>Tax:</label>
        <input id="srTaxPerc" value="3." style="width:30px;">
        <input id="srTaxAmt" value=".00">
      </div>
      <div class="field-group">
        <label>Cess:</label>
        <input id="srCessPerc" value="." style="width:30px;">
        <input id="srCessAmt" value=".00">
      </div>
      <div class="field-group">
        <label>Bill Amt :</label>
        <input id="srBillAmt" class="wide" value="0.00" style="background:#fefce8;">
      </div>
      <div class="field-group">
        <label>Ret.Amt</label>
        <input id="srRetAmt" class="wide" value="0.00" style="background:#2c5282;color:#fff;font-weight:700;border-radius:3px;">
      </div>
      <div class="field-group">
        <label>Net :</label>
        <input id="srNetAmt" class="wide" value="0.00" style="background:#fefce8;">
      </div>
      <div class="sr-btn-group">
        <button class="save" id="btnSrSave">Save</button>
        <button class="cancel" id="btnSrCancel">Cancel</button>
      </div>
    </div>
  </div>
</div>

<!-- Bill Item Selection Popup -->
<div id="srBillSelectOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:10000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:6px;box-shadow:0 8px 32px rgba(0,0,0,0.35);min-width:560px;max-width:92vw;max-height:80vh;display:flex;flex-direction:column;">
    <div style="background:#2d3748;color:#fff;padding:8px 12px;border-radius:6px 6px 0 0;display:flex;align-items:center;justify-content:space-between;">
      <span style="font-weight:600;font-size:13px;">Select Items to Return</span>
      <button id="btnSrBillSelClose" style="background:rgba(255,255,255,0.15);color:#fff;border:none;width:22px;height:20px;font-size:14px;font-weight:700;cursor:pointer;border-radius:4px;">&#10005;</button>
    </div>
    <div style="overflow-y:auto;flex:1;padding:8px;">
      <table style="width:100%;border-collapse:collapse;font-size:11px;">
        <thead>
          <tr style="background:#edf2f7;">
            <th style="padding:4px 6px;text-align:center;width:28px;"><input type="checkbox" id="chkSrBillSelAll" title="Select All"></th>
            <th style="padding:4px 6px;text-align:left;">Code</th>
            <th style="padding:4px 6px;text-align:left;">Item Name</th>
            <th style="padding:4px 6px;text-align:right;">Qty</th>
            <th style="padding:4px 6px;text-align:right;">Weight</th>
            <th style="padding:4px 6px;text-align:right;">St.Wgt</th>
            <th style="padding:4px 6px;text-align:right;">Net Wgt</th>
            <th style="padding:4px 6px;text-align:right;">Amount</th>
            <th style="padding:4px 6px;text-align:center;">Barcode</th>
          </tr>
        </thead>
        <tbody id="srBillSelTbody"></tbody>
      </table>
    </div>
    <div style="padding:8px 12px;border-top:1px solid #e2e8f0;display:flex;gap:8px;justify-content:flex-end;">
      <button id="btnSrBillSelAdd" style="background:#276749;color:#fff;border:none;padding:4px 16px;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;">Add Selected</button>
      <button id="btnSrBillSelCancel" style="background:#718096;color:#fff;border:none;padding:4px 12px;border-radius:4px;cursor:pointer;font-size:12px;">Cancel</button>
    </div>
  </div>
</div>

<div id="msgModal" class="msg-modal" aria-hidden="true">
  <div class="msg-box" role="dialog" aria-modal="true" aria-labelledby="msgModalTitle">
    <div id="msgModalTitle" class="head">Warning</div>
    <div id="msgModalText" class="body"></div>
    <div class="foot">
      <button id="msgModalOk" class="ok-btn" type="button">OK</button>
    </div>
  </div>
</div>

<div id="confirmModal" class="msg-modal" aria-hidden="true">
  <div class="msg-box" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
    <div id="confirmModalTitle" class="head">Confirm</div>
    <div id="confirmModalText" class="body"></div>
    <div class="foot" style="gap:8px;">
      <button id="confirmModalNo" class="secondary-btn" type="button">No</button>
      <button id="confirmModalYes" class="primary-btn" type="button">Yes</button>
    </div>
  </div>
</div>

<div id="cancelReasonModal" class="msg-modal" aria-hidden="true">
  <div class="msg-box" role="dialog" aria-modal="true" aria-labelledby="cancelReasonTitle" style="width:min(460px,calc(100vw - 24px))">
    <div id="cancelReasonTitle" class="head" style="background:#fee2e2;color:#b91c1c;">Cancel Bill</div>
    <div class="body">
      <div style="font-size:12px;color:#64748b;margin-bottom:10px;">Are you sure you want to cancel this bill? This action cannot be undone.</div>
      <label style="font-size:11px;font-weight:700;color:#64748b;display:block;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px;">Cancellation Reason</label>
      <textarea id="cancelReasonInput" rows="3" style="width:100%;border:1px solid #cbd5e1;border-radius:6px;padding:8px 10px;font-size:13px;font-family:inherit;resize:vertical;" placeholder="Enter reason (optional)"></textarea>
    </div>
    <div class="foot" style="gap:8px;">
      <button id="cancelReasonNo" class="secondary-btn" type="button">No, Keep Bill</button>
      <button id="cancelReasonYes" type="button" style="height:32px;padding:0 18px;border:none;border-radius:6px;background:#dc2626;color:#fff;font-size:12px;font-weight:700;cursor:pointer;">Yes, Cancel Bill</button>
    </div>
  </div>
</div>

<div id="passwordModal" class="msg-modal" aria-hidden="true">
  <div class="msg-box" role="dialog" aria-modal="true" aria-labelledby="passwordModalTitle">
    <div id="passwordModalTitle" class="head">E-Invoice Password</div>
    <div id="passwordModalText" class="body">Enter API password to generate e-invoice.</div>
    <div class="body" style="padding-top:0;">
      <input id="passwordModalInput" type="password" autocomplete="current-password" style="width:100%;height:36px;border:1px solid #cbd5e1;border-radius:6px;padding:0 10px;font-size:14px;" placeholder="Enter password">
    </div>
    <div class="foot" style="gap:8px;">
      <button id="passwordModalCancel" class="secondary-btn" type="button">Cancel</button>
      <button id="passwordModalOk" class="primary-btn" type="button">Generate</button>
    </div>
  </div>
</div>

<script>
(() => {
  'use strict';

  // ══════════════════════════════════════════════════════════════════
  // 1. CONFIG & CONSTANTS
  // ══════════════════════════════════════════════════════════════════
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const { siteUrl, currentDatabase, mode, rates, exchItems, billTypes, counters, salesmen, agents, cashBanks, approvedBy, states, mcRows, wstgRows, software, access = {} } = window.SB_CONFIG;
  const ALLOW_PREV_NEXT_BUTTONS = !!access.allowPrevNextButtons;
  const GOLD_RATE     = rates.GRATE;
  const SILVER_RATE   = rates.SRATE;
  const OLD_GOLD_RATE = rates.OGRATE;
  const OLD_SIL_RATE  = rates.OSRATE;
  const BULLION_RATE  = rates.BULRATE || rates.GRATE || 0;
  const BULLION_TOUCH = Number(rates.BULTOUCH ?? software.BulTouch ?? 99.5) || 99.5;
  const sw = software || {};
  const flagY = (v, def = 'N') => String(v ?? def).trim().toUpperCase() === 'Y';
  const STOP_ON_EXCH_RATE = flagY(sw.StopOnExchRate, 'N');
  const USE_TOUCH_TO_LESS_WGT = flagY(sw.UseTouchToLessWgtCalculationInPurchase, 'Y');
  const ROUND_OFF_ALL_AMT = flagY(sw.RoundOffAllAmt, 'N');
  const CONV_TOUCH = Number(sw.PurchConvTouch ?? 0) || 0;
  const ROUND_DEC = Math.max(0, Math.min(4, Math.round(Number(sw.RoundDec ?? 2) || 2)));
  const DEF_EXCH_ITEM = String(sw.DefExchItem ?? 'OG').trim().toUpperCase();
  const DEF_EXCH_STKTYPE = String(sw.ExchangeDefStkType ?? '').trim();
  const DEF_SRET_STKTYPE = String(sw.SReturnDefStkType ?? sw.SReturnDefStkType2 ?? '').trim();
  const USE_VA_MODEL = flagY(sw.UseVa, 'N');
  const DEVIDE_NETWGT_BY_QTY = flagY(sw.DevideNetwgtByQty, 'N');
  const ALLOW_SALES_BILL_MANUAL = flagY(sw.AllowSalesBillManual, 'Y');
  const MANUAL_BNO_DEFAULT = flagY(sw.BNO, 'N');
  const FOCUS_ON_PARTY_CODE = flagY(sw.FocusOnPartyCode, 'N');
  const DEF_RATE_MODE = String(sw.DefRate ?? 'RTR').trim().toUpperCase();
  const RATE_BASE_BULLION = flagY(sw.SRateBasedOnBullionRate, 'N');
  const SALES_DEF_CREDIT = flagY(sw.SalesDefCredit, 'N');
  const DEF_BILL_TYPE_RAW = String(sw.DefBillType ?? '').trim();
  const DEF_COUNTER = String(sw.DefCounter ?? '').trim();
  const DEF_STATE_CODE = String(sw.DefStateCode ?? '').trim();
  const SHOW_ADVANCE_IN_ESTIMATE = flagY(sw.ShowAdvanceInSalesEstimate, 'N');
  const PAN_LABEL = String(sw.PanLabel ?? 'PAN/Adh').trim();
  const STATE_LABEL = String(sw.StateLabel ?? 'State').trim();
  const TAXNO_LABEL = String(sw.TaxNoLabel ?? 'GST No').trim();
  const PRINTER_IS_LASER = String(sw.PrinterType ?? 'DotMatrix').trim().toUpperCase() === 'LASER';
  const ENABLE_QTN = flagY(sw.QtnV, 'N');
  const FORCE_QTN_MODE = !!(window.SB_CONFIG?.quotationMode);
  const MOBILE_NO_BASED_SEARCH = flagY(sw.MobileNoBasedSearch, 'N');
  const BILL_TYPEWISE_BILLNO = flagY(sw.BillTypewiseBillNo, 'N');
  const TAX_BILL_TYPE_WISE = flagY(sw.TaxBillTypeWise, 'N');
  const TAX_AFTER_DISC = flagY(sw.TaxAfterDisc, 'N');
  const BAL_RCVD_TO_DISCOUNT = flagY(sw.BalRcvdToDiscount, 'N');
  const IS_SECONDARY_DB = !!(window.SB_CONFIG?.isSecondaryDb);
  const DEFAULT_SALES_TAX_PERC = IS_SECONDARY_DB ? 0 : 3;
  const ASK_EINVOICE_ABOVE_AMOUNT = flagY(sw.AskEInvoiceAboveAmount, 'Y');
  const EINVOICE_THRESHOLD_AMOUNT = Number(sw.EInvoiceThresholdAmount ?? 1000000) || 1000000;

  // ══════════════════════════════════════════════════════════════════
  // 2. STATE
  // ══════════════════════════════════════════════════════════════════
  let items    = [];   // main item rows
  let exchRows = [];   // exchange modal rows
  let srRows   = [];   // sales return modal rows
  let currentBillNo = '';
  let currentLegacySlno = 0;
  let isSaving = false;
  let forceItemCodeFocusIdx = -1;
  let pendingItemCodeFocusIdx = -1;
  let pendingItemCodePickerIdx = -1;
  let skipNextCodeBlurLookupIdx = -1;
  let skipNextItemBlurCommitKey = '';
  let itemCodeFocusTimer = null;
  let warningCloseHandler = null;
  let confirmCloseHandler = null;
  let passwordCloseHandler = null;
  let pendingResetAfterPrint = false;
  let billNoRefreshToken = 0;
  let totalRcvdDirty = false;
  let discountDirty = false;
  let _nextRowId = 1;
  function nextRowId() { return _nextRowId++; }

  // ══════════════════════════════════════════════════════════════════
  // 3. UTILITIES
  // ══════════════════════════════════════════════════════════════════
  async function api(url, method, payload) {
    const opts = { method, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } };
    if (method === 'POST') {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(payload || {});
    }
    const res = await fetch(url, opts);
    return await res.json();
  }

  function toNum(v) {
    const n = parseFloat(String(v ?? '').replace(/,/g, ''));
    return isNaN(n) ? 0 : n;
  }

  function deriveNextBillNo(rawBillNo) {
    const cfg = window.SB_CONFIG || {};
    const sw = cfg.software || {};
    const secondaryMode = !!cfg.isSecondaryDb
      && String(sw.SecondaryTransactionSeriesSeperate || 'N').toUpperCase() === 'Y';
    if (secondaryMode) {
      return '';
    }
    const billNo = String(rawBillNo || '').trim();
    if (!billNo) return '';
    const m = billNo.match(/^(.*?)(\d+)$/);
    if (!m) return '';
    const prefix = m[1];
    const digits = m[2];
    const nextNo = String((parseInt(digits, 10) || 0) + 1).padStart(digits.length, '0');
    return prefix + nextNo;
  }
  function roundByMode(n) {
    const num = toNum(n);
    if (ROUND_OFF_ALL_AMT) return Math.round(num);
    const p = Math.pow(10, ROUND_DEC);
    return Math.round(num * p) / p;
  }
  function billTypeTaxPerc(rawCode = val('fBillType')) {
    const code = String(rawCode || '').trim().toUpperCase();
    const row = (billTypes || []).find(bt =>
      String(bt.code || '').trim().toUpperCase() === code ||
      String(bt.name || '').trim().toUpperCase() === code
    );
    const pct = Number(row?.taxperc ?? 0);
    return Number.isFinite(pct) && pct > 0 ? pct : DEFAULT_SALES_TAX_PERC;
  }
  function normalizeTaxPerc(rawPerc, fallbackCode = val('fBillType')) {
    const pct = Number(rawPerc ?? 0);
    if (IS_SECONDARY_DB) return Number.isFinite(pct) ? pct : 0;
    return Number.isFinite(pct) && pct > 0 ? pct : billTypeTaxPerc(fallbackCode);
  }
  function fmt2(n) { return toNum(n).toFixed(2); }
  function fmt3(n) { return toNum(n).toFixed(3); }
  function esc(v)  { return String(v ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
  function fmtMoney(n) { return new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(toNum(n)); }

  function todayDmY() {
    const d = new Date();
    return d.getFullYear() + '-' +
           String(d.getMonth()+1).padStart(2,'0') + '-' +
           String(d.getDate()).padStart(2,'0');
  }

  function inQuotationMode() {
    return !!(FORCE_QTN_MODE || chk('fQuotationMode'));
  }

  function normalizeDateInputValue(v) {
    const raw = String(v ?? '').trim();
    if (!raw || raw === '00/00/00' || raw === '00/00/0000') return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw;
    const m = raw.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/);
    if (!m) return raw;
    let yy = m[3];
    if (yy.length === 2) yy = '20' + yy;
    return yy + '-' + String(m[2]).padStart(2, '0') + '-' + String(m[1]).padStart(2, '0');
  }
  function nowTime() {
    const d = new Date();
    let h = d.getHours(), m = d.getMinutes(), ap = 'AM';
    if (h >= 12) { ap = 'PM'; if (h > 12) h -= 12; }
    if (h === 0) h = 12;
    return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ' ' + ap;
  }

  function showAlert(msg, ok, onClose = null) {
    const modal = $('msgModal');
    const title = $('msgModalTitle');
    const text = $('msgModalText');
    const btn = $('msgModalOk');
    if (modal && title && text && btn) {
      warningCloseHandler = (typeof onClose === 'function') ? onClose : null;
      title.textContent = ok ? 'Saved' : 'Warning';
      text.textContent = String(msg || '');
      modal.classList.add('active');
      modal.setAttribute('aria-hidden', 'false');
      btn.focus();
      return;
    }

    let el = document.getElementById('statusBar');
    if (!el) {
      el = document.createElement('div');
      el.id = 'statusBar';
      el.style.cssText = 'position:fixed;bottom:4px;left:50%;transform:translateX(-50%);padding:4px 16px;font-size:12px;font-weight:700;border-radius:3px;z-index:9999;transition:opacity 0.3s;';
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.style.background = ok ? '#f0fff4' : '#fff5f5';
    el.style.border = ok ? '1px solid #48bb78' : '1px solid #fc8181';
    el.style.color  = ok ? '#276749' : '#c53030';
    el.style.borderRadius = '6px';
    el.style.opacity = '1';
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.style.opacity = '0'; }, ok ? 3000 : 6000);
  }

  function openBillPrintPreview(kind = 'full') {
    const billNo = val('fBillNo') || '';
    const billDate = val('fBillDate') || '';
    const customer = val('fCustomerName') || '';
    const mobile = val('fMobNo') || '';
    const netAmt = val('fNetAmt') || val('fNetTotal') || '0.00';
    const disc = val('fDiscount') || '0.00';
    const tax = val('fTaxAmt') || '0.00';
    const grand = val('fGrandAmt') || '0.00';

    const rows = (items || []).filter(r => String(r.item_code || '').trim() !== '');
    if (kind === 'short') {
      const thermalRows = rows.map((r, i) => `
        <tr>
          <td>${i + 1}</td>
          <td>
            <div>${esc(r.item_name || r.item_code || '')}</div>
            <div style="font-size:10px;">${esc(r.item_code || '')}</div>
            <div style="font-size:10px;">VA %: ${fmt2(toNum(r.mc_perc ?? r.vaperc ?? 0))}</div>
            <div style="font-size:10px;">VA Amt: ${fmt2(toNum(r.making_charge || 0))}</div>
            <div style="font-size:10px;">Rate/gm: ${fmt2(toNum(r.rate || 0))}</div>
          </td>
          <td style="text-align:right;">${fmt3(r.weight)}</td>
          <td style="text-align:right;">${fmt2(r.amount)}</td>
        </tr>`).join('');

      const title = 'Estimate';
      const win = window.open('', '_blank', 'width=420,height=760,scrollbars=yes');
      if (!win) return false;

      win.document.write(`
        <!doctype html>
        <html><head><title>${title} - ${esc(billNo)}</title>
        <style>
          @@page { size: 80mm auto; margin: 4mm; }
          * { box-sizing: border-box; }
          body { font-family: "Courier New", monospace; width: 72mm; margin: 0 auto; color:#000; font-size: 11px; }
          .actions { margin: 0 0 10px 0; display: flex; justify-content: center; gap: 8px; }
          .actions button { border: 1px solid #555; background: #fff; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; }
          .slip { padding: 4px 0; }
          .center { text-align: center; }
          .title { font-size: 18px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
          .shop { font-size: 15px; font-weight: 700; text-transform: uppercase; }
          .rule { border-top: 1px dashed #000; margin: 6px 0; }
          .meta-row { display: flex; justify-content: space-between; gap: 8px; margin: 2px 0; }
          .meta-row div { white-space: nowrap; }
          table { width: 100%; border-collapse: collapse; }
          th, td { padding: 4px 2px; vertical-align: top; }
          thead th { border-top: 1px dashed #000; border-bottom: 1px dashed #000; font-size: 10px; text-transform: uppercase; }
          tbody tr:last-child td { border-bottom: 1px dashed #000; }
          .totals { margin-top: 6px; }
          .totals .meta-row { font-weight: 700; }
          .totals .meta-row.grand { font-size: 13px; }
          .foot { margin-top: 10px; text-align: center; font-size: 10px; }
          @media print { .actions { display:none; } body { width: auto; } }
        </style>
        </head><body>
          <div class="actions">
            <button type="button" onclick="window.print()">Print</button>
            <button type="button" onclick="window.__goldappExit()">Close</button>
          </div>
          <div class="slip">
            <div class="center shop">Saleena Gold And Diamonds</div>
            <div class="center title">${title}</div>
            <div class="rule"></div>
            <div class="meta-row"><div>Bill: ${esc(billNo)}</div><div>Date: ${esc(billDate)}</div></div>
            <div class="meta-row"><div>Customer: ${esc(customer || '-')}</div></div>
            <div class="meta-row"><div>Mobile: ${esc(mobile || '-')}</div></div>
            <div class="meta-row"><div>Rate/8gm: ${esc(val('fRatePer8gm') || '0.00')}</div></div>
            <table>
              <thead>
                <tr><th style="width:8%">#</th><th style="width:47%">Item</th><th style="width:20%; text-align:right;">Wgt</th><th style="width:25%; text-align:right;">Amt</th></tr>
              </thead>
              <tbody>${thermalRows || '<tr><td colspan="4" style="text-align:center;">No items</td></tr>'}</tbody>
            </table>
            <div class="totals">
              <div class="meta-row"><div>Bill Total</div><div>${esc(val('fBillTotal') || '0.00')}</div></div>
              <div class="meta-row"><div>Tax</div><div>${esc(tax)}</div></div>
              <div class="meta-row"><div>Discount</div><div>${esc(disc)}</div></div>
              <div class="meta-row grand"><div>Net Amount</div><div>${esc(netAmt)}</div></div>
              <div class="meta-row"><div>Grand Amount</div><div>${esc(grand)}</div></div>
            </div>
            <div class="rule"></div>
            <div class="foot">Estimate Only</div>
          </div>
          <script>
            (function() {
              var notified = false;
              function notifyExit() {
                if (notified) return;
                notified = true;
                try {
                  if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({ type: 'goldapp:salesbill-print-exit' }, '*');
                  }
                } catch (_) {}
              }
              window.__goldappExit = function() {
                notifyExit();
                window.close();
              };
              window.addEventListener('beforeunload', notifyExit);
            })();
          <\/script>
        </body></html>
      `);
      win.document.close();
      setTimeout(() => { try { win.focus(); } catch(_) {} }, 250);
      return true;
    }

    const bodyRows = rows.map((r, i) => {
      return `<tr>
        <td>${i + 1}</td>
        <td>${esc(r.item_code)}</td>
        <td>${esc(r.item_name || '')}</td>
        <td style="text-align:right;">${toNum(r.qty)}</td>
        <td style="text-align:right;">${fmt3(r.weight)}</td>
        <td style="text-align:right;">${fmt3(r.stone_wgt)}</td>
        <td style="text-align:right;">${fmt3(r.net_wgt)}</td>
        <td style="text-align:right;">${fmt2(r.making_charge)}</td>
        <td style="text-align:right;">${fmt2(r.rate)}</td>
        <td style="text-align:right;">${fmt2(r.amount)}</td>
      </tr>`;
    }).join('');

    const tableHead = `<tr><th>#</th><th>Item Code</th><th>Item Name</th><th>Qty</th><th>Weight</th><th>Stone Wgt</th><th>Net Wgt</th><th>MC</th><th>Rate</th><th>Amount</th></tr>`;

    const title = kind === 'qtn' ? 'Quotation Print' : 'Sales Bill Print';
    const win = window.open('', '_blank', 'width=1100,height=760');
    if (!win) return false;

    win.document.write(`
      <!doctype html>
      <html><head><title>${title} - ${esc(billNo)}</title>
      <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 16px; color:#111; }
        h2 { margin: 0 0 8px 0; font-size: 18px; }
        .meta { margin-bottom: 12px; line-height: 1.5; }
        .actions { margin: 0 0 12px 0; display: flex; justify-content: flex-end; gap: 8px; }
        .actions button { border: 1px solid #9ca3af; background: #fff; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .actions button:hover { background: #f3f4f6; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #777; padding: 6px 8px; }
        th { background: #f1f5f9; text-align: left; }
        .totals { margin-top: 12px; width: 320px; margin-left: auto; }
        .totals td { border: 1px solid #777; }
        @media print { .actions { display: none; } }
      </style>
      
</head><body>
        <div class="actions">
          <button type="button" onclick="window.print()">Print</button>
          <button type="button" onclick="window.__goldappExit()">Exit</button>
        </div>
        <h2>${title}</h2>
        <div class="meta">
          <div><b>Bill No:</b> ${esc(billNo)} &nbsp;&nbsp; <b>Date:</b> ${esc(billDate)}</div>
          <div><b>Customer:</b> ${esc(customer)} &nbsp;&nbsp; <b>Mobile:</b> ${esc(mobile)}</div>
        </div>
        <table>
          <thead>${tableHead}</thead>
          <tbody>${bodyRows || '<tr><td colspan="10" style="text-align:center;">No items</td></tr>'}</tbody>
        </table>
        <table class="totals">
          <tr><td><b>Tax</b></td><td style="text-align:right;">${esc(tax)}</td></tr>
          <tr><td><b>Discount</b></td><td style="text-align:right;">${esc(disc)}</td></tr>
          <tr><td><b>Net Amt</b></td><td style="text-align:right;">${esc(netAmt)}</td></tr>
          <tr><td><b>Grand Amt</b></td><td style="text-align:right;">${esc(grand)}</td></tr>
        </table>
        <script>
          (function() {
            var notified = false;
            function notifyExit() {
              if (notified) return;
              notified = true;
              try {
                if (window.opener && !window.opener.closed) {
                  window.opener.postMessage({ type: 'goldapp:salesbill-print-exit' }, '*');
                }
              } catch (_) {}
            }
            window.__goldappExit = function() {
              notifyExit();
              window.close();
            };
            window.addEventListener('beforeunload', notifyExit);
          })();
        <\/script>
      </body></html>
    `);
    win.document.close();
    setTimeout(() => { try { win.focus(); } catch(_) {} }, 250);
    return true;
  }

  function renderQuotationList(rows) {
    quotationRowsCache = Array.isArray(rows) ? rows : [];
    const tbody = $('quotationListBody');
    if (!tbody) return;
    if (!quotationRowsCache.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="qtn-empty">No quotation found.</td></tr>';
      return;
    }
    tbody.innerHTML = quotationRowsCache.map((row, idx) => `
      <tr>
        <td>${esc(row.bill_no || '')}</td>
        <td>${esc(row.bill_date || '')}</td>
        <td>${esc(row.customer_name || '')}</td>
        <td>${esc(row.mobile || '')}</td>
        <td style="text-align:right;">${fmt2(row.net_total || 0)}</td>
        <td><button type="button" class="qtn-pick-btn" data-qtn-index="${idx}">Select</button></td>
      </tr>
    `).join('');
  }

  async function fetchQuotationList(search = '') {
    try {
      const params = new URLSearchParams();
      if (search) params.set('q', search);
      const res = await api(siteUrl + '/api/sales-bill/quotation-list?' + params.toString(), 'GET');
      renderQuotationList(res.ok ? (res.rows || []) : []);
    } catch (_) {
      renderQuotationList([]);
    }
  }

  function openQuotationSelect() {
    $('quotationSelectModal')?.classList.add('active');
    $('quotationSearchInput').value = '';
    fetchQuotationList('');
    setTimeout(() => $('quotationSearchInput')?.focus(), 50);
  }

  function closeQuotationSelect() {
    $('quotationSelectModal')?.classList.remove('active');
  }

  async function applyQuotationToSale(billNo) {
    const keepBillNo = String(val('fBillNo') || '').trim();
    const keepBillDate = String(val('fBillDate') || '').trim();
    const keepBillTime = String($('fBillTime')?.textContent || '').trim();
    try {
      const res = await api(siteUrl + '/api/sales-bill/get?bill_no=' + encodeURIComponent(billNo), 'GET');
      if (!res.ok || !res.data) {
        showAlert(res.message || 'Quotation not found.');
        return;
      }
      const d = res.data;
      selectedQuotationBillNo = String(d.bill_no || '').trim();
      setVal('fQuotationMode', false);
      if ($('lblBillNo')) $('lblBillNo').textContent = 'Bill No';
      setVal('fBillNo', keepBillNo);
      setVal('fBillDate', keepBillDate);
      if ($('fBillTime')) $('fBillTime').textContent = keepBillTime || nowTime();
      setBillTypeByAny(d.bill_type || 'G');
      setVal('fCustomerName', d.customer_name || '');
      setVal('fCustomerCode', d.customer_code || '');
      setVal('fCustomerCodeInput', d.customer_code || '');
      setVal('fAddress', d.address || '');
      setVal('fMobNo', d.mobile || '');
      setVal('fGstNo', d.gst_no || '');
      setVal('fPanNo', d.pan_no || '');
      setVal('fRatePerGm', fmt2(d.rate_per_gm || 0));
      setVal('fCounter', d.counter_code || '');
      setSalesmanByAny(d.salesman_code || '', d.salesman_name || '');
      setVal('fAgent', d.agent_code || '');
      setVal('fApprovedBy', d.approved_by || '');
      setVal('fState', d.state_code || '');
      setVal('fDueDate', d.due_date || '');
      setVal('fVehNo', d.vehicle_no || '');
      setVal('fSupplyPlace', d.supply_place || '');
      setVal('fDistance', d.distance || '');
      items = (d.items || []).map(r => ({ ...newItemRow(), ...r, hsn: r.hsn || r.note || '', _rowId: nextRowId() }));
      exchRows = (d.exchange || []).map(r => ({ ...newExchRow(), ...r, _rowId: nextRowId() }));
      srRows = (d.sales_return || []).map(r => ({ ...newSrRow(), ...r, _rowId: nextRowId() }));
      const ex = d.extra || {};
      setVal('fDiscount', toNum(ex.discount) > 0 ? fmt2(ex.discount) : '');
      setVal('fDiscountPerc', ex.discount_perc ?? 0);
      setVal('fTaxAmt', fmt2(ex.tax));
      setVal('fTaxPerc', String(normalizeTaxPerc(ex.tax_perc, d.bill_type || val('fBillType'))));
      setVal('fCessAmt', fmt2(ex.ast));
      setVal('fCessPerc', ex.ast_perc ?? 0);
      setVal('fRepair', fmt2(ex.repair_charge));
      setVal('fHmc', fmt2(ex.hallmark_charge));
      setVal('fFancyAmt', fmt2(ex.fancy_amt));
      setVal('fSchemeAmt', fmt2(ex.scheme_amt));
      setVal('fSchemeLedger', ex.scheme_ledger || 'APP');
      setVal('fPurchTaxPerc', ex.ptax_perc ?? 0);
      setVal('fPurchTaxAmt', fmt2(ex.ptax_amt));
      setVal('fExchangeAmt', fmt2(d.exchange_amount || 0));
      setVal('fSalesRetAmt', fmt2(d.return_amount || 0));
      setVal('fCashBank', 'CASH IN HAND');
      setVal('fChqBank', '');
      setVal('fChqAmt', '0.00');
      setVal('fCcardAmt', '0.00');
      setVal('fBcPerc', '0.00');
      setVal('fBcAmt', '0.00');
      setVal('fPrevAdv', '0.00');
      setVal('fTotalRcvd', '0.00');
      setVal('fCredit', false);
      setVal('fBalance', '0.00');
      totalRcvdDirty = false;
      discountDirty = false;
      renderItemsTable();
      renderExchTable();
      renderSrTable();
      updateTableFooter();
      triggerRecalc();
      closeQuotationSelect();
      showAlert('Quotation loaded: ' + selectedQuotationBillNo, true);
    } catch (e) {
      showAlert('Quotation load error: ' + e.message);
    }
  }

  function closeWarningModal() {
    const modal = $('msgModal');
    if (!modal) return;
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    if (typeof warningCloseHandler === 'function') {
      const fn = warningCloseHandler;
      warningCloseHandler = null;
      try { fn(); } catch (_) {}
    } else {
      warningCloseHandler = null;
    }
  }

  function closeConfirmModal(answer = false) {
    const modal = $('confirmModal');
    if (!modal) return;
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    if (typeof confirmCloseHandler === 'function') {
      const fn = confirmCloseHandler;
      confirmCloseHandler = null;
      try { fn(!!answer); } catch (_) {}
    } else {
      confirmCloseHandler = null;
    }
  }

  function closePasswordModal(password = null) {
    const modal = $('passwordModal');
    const input = $('passwordModalInput');
    if (!modal) return;
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    if (input) input.value = '';
    if (typeof passwordCloseHandler === 'function') {
      const fn = passwordCloseHandler;
      passwordCloseHandler = null;
      try { fn(password); } catch (_) {}
    } else {
      passwordCloseHandler = null;
    }
  }

  function showConfirmModal(message, title = 'Confirm') {
    const modal = $('confirmModal');
    const titleEl = $('confirmModalTitle');
    const textEl = $('confirmModalText');
    const yesBtn = $('confirmModalYes');
    if (!modal || !titleEl || !textEl || !yesBtn) {
      return Promise.resolve(window.confirm(String(message || '')));
    }

    titleEl.textContent = String(title || 'Confirm');
    textEl.textContent = String(message || '');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    yesBtn.focus();

    return new Promise((resolve) => {
      confirmCloseHandler = resolve;
    });
  }

  function showPasswordModal(message, title = 'E-Invoice Password') {
    const modal = $('passwordModal');
    const titleEl = $('passwordModalTitle');
    const textEl = $('passwordModalText');
    const input = $('passwordModalInput');
    if (!modal || !titleEl || !textEl || !input) {
      const raw = window.prompt(String(message || 'Enter password'), '');
      if (raw === null) return Promise.resolve(null);
      return Promise.resolve(String(raw));
    }

    titleEl.textContent = String(title || 'E-Invoice Password');
    textEl.textContent = String(message || 'Enter password');
    input.value = '';
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    setTimeout(() => input.focus(), 0);

    return new Promise((resolve) => {
      passwordCloseHandler = resolve;
    });
  }

  async function maybePromptEInvoice(billNo, amount) {
    if (mode !== 'bill' || !ASK_EINVOICE_ABOVE_AMOUNT) return;

    const netAmount = toNum(amount);
    if (netAmount <= EINVOICE_THRESHOLD_AMOUNT) return;

    const wantsGenerate = await showConfirmModal(
      'Sales amount is Rs. ' + fmtMoney(netAmount) + '.\nDo you want to generate e-invoice now?',
      'Generate E-Invoice'
    );
    if (!wantsGenerate) return;

    const password = await showPasswordModal(
      'Enter e-invoice API password for bill ' + billNo + '.',
      'E-Invoice Password'
    );
    if (password === null) return;
    if (!String(password).trim()) {
      showAlert('E-invoice password is required.');
      return;
    }

    try {
      const res = await api(siteUrl + '/api/sales-bill/einvoice', 'POST', {
        bill_no: billNo,
        password: String(password),
      });
      if (res.ok) {
        showAlert(res.message || 'E-invoice generated successfully.', true);
      } else {
        showAlert(res.message || 'E-invoice generation failed.');
      }
    } catch (e) {
      showAlert('E-invoice error: ' + e.message);
    }
  }

  function promptStkQType(currentStk = '', currentQ = '', currentTouch = 0) {
    const seed = [currentStk || '', currentQ || ''].filter(Boolean).join('|');
    const withTouch = toNum(currentTouch) > 0 ? (seed ? seed + '\\' + currentTouch : '\\' + currentTouch) : seed;
    const raw = prompt('StockType|QType\\Touch', withTouch || '');
    if (raw === null) return null;
    const txt = String(raw).trim();
    if (!txt) return { stktype: '', qtype: '', stktouch: 0 };

    let stktype = '';
    let qtype = '';
    let stktouch = 0;
    let body = txt;
    if (body.includes('|')) {
      stktype = body.split('|')[0].trim();
      body = body.substring(body.indexOf('|') + 1);
      qtype = body.trim();
    } else {
      stktype = body.trim();
      body = '';
    }
    if (qtype.includes('\\')) {
      stktouch = toNum(qtype.split('\\')[1]);
      qtype = qtype.split('\\')[0].trim();
    } else if (stktype.includes('\\')) {
      stktouch = toNum(stktype.split('\\')[1]);
      stktype = stktype.split('\\')[0].trim();
    }
    return { stktype, qtype, stktouch };
  }

  // ══════════════════════════════════════════════════════════════════
  // 4. DOM HELPERS
  // ══════════════════════════════════════════════════════════════════
  function $(id)       { return document.getElementById(id); }
  function val(id)     {
    const e = $(id);
    if (!e) return '';
    if (e.type === 'checkbox') return e.checked;
    if (e.type === 'date') return normalizeDateInputValue(e.value);
    return e.value;
  }
  function setVal(id, v) {
    const e = $(id);
    if (!e) return;
    if (e.type === 'checkbox') e.checked = !!v;
    else if (e.tagName === 'SPAN') e.textContent = (v === null || v === undefined) ? '' : v;
    else if (e.type === 'date') e.value = normalizeDateInputValue(v);
    else e.value = (v === null || v === undefined) ? '' : v;
  }
  function numVal(id)  { return toNum(val(id)); }
  function chk(id)     { const e = $(id); return e ? e.checked : false; }

  function normalizeBillType(v) {
    const t = String(v ?? '').trim().toUpperCase();
    if (!t) return '';
    if (t === 'GOLD') return 'G';
    if (t === 'SILVER') return 'S';
    return t;
  }

  function isSilverBillType(v) {
    const t = normalizeBillType(v);
    return t === 'S' || t === 'SILVER';
  }

  function setBillTypeByAny(rawVal) {
    const sel = $('fBillType');
    if (!sel) return;
    const input = String(rawVal ?? '').trim();
    if (!input) return;
    const norm = normalizeBillType(input);
    for (const opt of Array.from(sel.options)) {
      const ov = normalizeBillType(opt.value);
      const ot = normalizeBillType(opt.textContent || '');
      if (ov === norm || ot === norm) {
        sel.value = opt.value;
        return;
      }
    }
  }

  async function refreshNextBillNo() {
    const requestToken = ++billNoRefreshToken;
    const billTypeVal = String(val('fBillType') || '').trim();
    const params = new URLSearchParams({
      bill_type: billTypeVal || 'G',
      seb: inQuotationMode() ? 'E' : 'B',
      quotation: inQuotationMode() ? '1' : '0',
    });
    try {
      const res = await api(siteUrl + '/api/sales-bill/next-number?' + params.toString(), 'GET');
      if (res.ok) {
        if (requestToken !== billNoRefreshToken) return;
        if (chk('fManualBillNo')) return;
        setVal('fBillNo', res.bill_no || '');
        currentBillNo = res.bill_no || '';
        if (res.tax_perc !== undefined) {
          setVal('fTaxPerc', String(normalizeTaxPerc(res.tax_perc)));
          triggerRecalc();
        }
      }
    } catch (e) {
      // keep existing value on failure
    }
  }

  function applyHeaderRate() {
    const wsOn = chk('fWs');
    const billType = String(val('fBillType') || '');
    let rate = GOLD_RATE;
    if (isSilverBillType(billType)) {
      rate = SILVER_RATE;
    } else if (RATE_BASE_BULLION || wsOn || DEF_RATE_MODE === 'WSR') {
      rate = BULLION_RATE > 0 ? BULLION_RATE : GOLD_RATE;
    }
    setVal('fRatePerGm', fmt2(rate));
    setVal('fRatePer8gm', fmt2(rate * 8));
  }

  async function validateManualBillNo() {
    const manual = chk('fManualBillNo');
    const billNo = String(val('fBillNo') || '').trim();
    if (!manual || !billNo) return true;
    const params = new URLSearchParams({
      bill_no: billNo,
      current_bill_no: currentBillNo || '',
    });
    try {
      const res = await api(siteUrl + '/api/sales-bill/check-bill-no?' + params.toString(), 'GET');
      if (res.ok && res.duplicate) {
        showAlert('Duplicate: This Bill Number already exist...', false);
        setVal('fBillNo', '');
        $('fBillNo')?.focus();
        return false;
      }
    } catch (e) {
      // ignore and allow user to continue editing
    }
    return true;
  }

  function applyBillNoMode() {
    const manual = chk('fManualBillNo');
    const bill = $('fBillNo');
    if (!bill) return;
    billNoRefreshToken++;
    if (mode !== 'bill') {
      setVal('fManualBillNo', false);
      $('fManualBillNo').disabled = true;
      bill.readOnly = true;
      bill.style.background = '#edf2f7';
      return;
    }
    bill.readOnly = !manual;
    bill.style.background = manual ? '#fff' : '#edf2f7';
    if (manual) {
      bill.focus();
      bill.select();
      return;
    }
    if (mode === 'bill') {
      refreshNextBillNo();
    }
  }

  // ══════════════════════════════════════════════════════════════════
  // 4b. POPULATE DROPDOWNS
  // ══════════════════════════════════════════════════════════════════
  function populateSelect(id, list, labelFn) {
    const el = $(id);
    if (!el) return;
    const curVal = el.value;
    // keep first option (--) then add from list
    while (el.options.length > 1) el.remove(1);
    (list || []).forEach(item => {
      const opt = document.createElement('option');
      opt.value = item.code;
      opt.textContent = labelFn ? labelFn(item) : (item.code + ' - ' + item.name);
      el.appendChild(opt);
    });
    if (curVal) el.value = curVal;
  }

  function syncSalesmanTitle() {
    const sm = $('fSmName');
    if (!sm) return;
    const sel = sm.options[sm.selectedIndex];
    sm.title = sel ? (sel.textContent || '') : '';
  }

  function ensureSalesmanOption(code, name = '') {
    const sm = $('fSmName');
    const cleanCode = String(code ?? '').trim();
    const cleanName = String(name ?? '').trim();
    if (!sm || !cleanCode) return;

    const norm = v => String(v ?? '').trim().toUpperCase();
    const exists = Array.from(sm.options).some(opt => norm(opt.value) === norm(cleanCode));
    if (exists) return;

    const opt = document.createElement('option');
    opt.value = cleanCode;
    opt.textContent = cleanName ? (cleanCode + ' - ' + cleanName) : cleanCode;
    sm.appendChild(opt);
  }

  function setSalesmanByAny(rawCode, rawName = '') {
    const sm = $('fSmName');
    if (!sm) return;
    const norm = v => String(v ?? '').trim().toUpperCase();
    const code = norm(rawCode);
    const name = norm(rawName);
    const row = (salesmen || []).find(s => {
      const sc = norm(s.code);
      const sn = norm(s.name);
      return (code && (sc === code || sn === code)) || (name && (sc === name || sn === name));
    });
    const selectedCode = row ? row.code : (String(rawCode || rawName || '').trim());
    ensureSalesmanOption(selectedCode, row ? row.name : rawName);
    sm.value = selectedCode;
    syncSalesmanTitle();
  }

  function initDropdowns() {
    populateSelect('fBillType', billTypes, item => (item.name || item.code || '').toUpperCase());
    if (!$('fBillType').value && $('fBillType').options.length > 0) {
      $('fBillType').selectedIndex = 0;
    }
    populateSelect('fCounter', counters);
    populateSelect('fSmName', salesmen);
    syncSalesmanTitle();
    populateSelect('fAgent', agents);
    populateSelect('fCashBank', cashBanks, item =>
      item.type === 'H' ? item.name : item.code + ' - ' + item.name
    );
    populateSelect('fApprovedBy', approvedBy);
    populateSelect('fState', states, item => item.code + ' - ' + item.name);
  }

  function setCoPartyOption(code, name = '') {
    const sel = $('fCo');
    if (!sel) return;
    const c = String(code || '').trim();
    if (!c) return;
    const exists = Array.from(sel.options).some(opt => String(opt.value || '').trim() === c);
    if (!exists) {
      const opt = document.createElement('option');
      opt.value = c;
      opt.textContent = name ? (c + ' - ' + name) : c;
      sel.appendChild(opt);
    }
    sel.value = c;
  }

  async function initCoPartyDropdown() {
    const sel = $('fCo');
    if (!sel) return;
    try {
      const res = await api(siteUrl + '/api/sales-bill/customer-search?preload=1&type=C&coparty_only=1', 'GET');
      if (!res.ok || !Array.isArray(res.rows)) return;
      while (sel.options.length > 1) sel.remove(1);
      res.rows.forEach(r => {
        const code = String(r.code || '').trim();
        if (!code) return;
        const opt = document.createElement('option');
        opt.value = code;
        opt.textContent = code + ' - ' + String(r.name || '').trim();
        sel.appendChild(opt);
      });
    } catch (e) {
      // Keep form usable even if co-party list fails.
    }
  }

  // ══════════════════════════════════════════════════════════════════
  // 4c. CUSTOMER SEARCH AUTOCOMPLETE
  // ══════════════════════════════════════════════════════════════════
  let _custSearchTimer = null;
  let _mobileFetchTimer = null;
  let _lastMobileFetchValue = '';
  let _custSearchSelecting = false;
  let selectedQuotationBillNo = '';
  let quotationRowsCache = [];
  const custSearchResults = $('custSearchResults');

  function hideCustSearch() {
    custSearchResults.style.display = 'none';
    custSearchResults.innerHTML = '';
  }

  async function fetchCustomerByCode(rawCode, focusNext = true) {
    const code = String(rawCode || '').trim().toUpperCase();
    setVal('fCustomerCodeInput', code);
    setVal('fCustomerCode', code);
    if (!code) return false;
    try {
      const res = await api(siteUrl + '/api/sales-bill/customer-details?code=' + encodeURIComponent(code), 'GET');
      if (!res.ok || res.blocked || res.invalid || !res.data) {
        showAlert(res.message || (res.blocked ? 'Customer is blocked...Sorry....' : 'Invalid Customer'), false);
        setVal('fCustomerCodeInput', '');
        setVal('fCustomerCode', '');
        setVal('fCustomerName', '');
        $('fCustomerCodeInput')?.focus();
        return false;
      }

      const cust = res.data;
      const a1 = String(cust.addr1 || '').trim();
      const a2 = String(cust.addr2 || '').trim();
      const mergedAddr = (a1 && a2) ? (a1 + ', ' + a2) : (a1 || a2);

      setVal('fCustomerCodeInput', cust.code || code);
      setVal('fCustomerCode', cust.code || code);
      setVal('fCustomerName', cust.name || '');
      setVal('fAddress', mergedAddr);
      setVal('fMobNo', cust.mobile || '');
      setVal('fGstNo', cust.tin || '');
      setVal('fPanNo', cust.panadhar || '');
      setVal('fOB', fmt2(cust.opbalance || 0));
      if (cust.cocode) {
        setCoPartyOption(cust.cocode, '');
      } else {
        setVal('fCo', '');
      }

      if (cust.state) {
        const stSel = $('fState');
        if (stSel) {
          for (let i = 0; i < stSel.options.length; i++) {
            if (stSel.options[i].value === cust.state) {
              stSel.value = cust.state;
              break;
            }
          }
        }
      }

      hideCustSearch();
      triggerRecalc();
      if (focusNext) $('fSmName')?.focus();
      return true;
    } catch (e) {
      showAlert('Customer fetch error: ' + e.message, false);
      return false;
    }
  }

  async function selectCustomer(cust) {
    const code = String(cust?.code || '').trim();
    if (!code) return;
    await fetchCustomerByCode(code, true);
    hideCustSearch();
  }

  async function fetchCustomerByMobile(rawMobile) {
    const mobile = String(rawMobile || '').trim();
    const digits = mobile.replace(/\D+/g, '');
    if (!mobile || digits.length < 10) return false;
    if (_lastMobileFetchValue === digits) return false;
    try {
      const res = await api(siteUrl + '/api/sales-bill/customer-by-mobile?mobile=' + encodeURIComponent(mobile), 'GET');
      _lastMobileFetchValue = digits;
      if (!res.ok || !res.found) return false;

      const code = String(res.code || '').trim();
      if (!code) return false;

      await fetchCustomerByCode(code, false);
      return true;
    } catch (e) {
      return false;
    }
  }

  function scheduleMobileCustomerFetch(rawMobile) {
    const mobile = String(rawMobile || '').trim();
    const digits = mobile.replace(/\D+/g, '');
    clearTimeout(_mobileFetchTimer);
    if (digits.length < 10) {
      _lastMobileFetchValue = '';
      return;
    }
    _mobileFetchTimer = setTimeout(() => {
      fetchCustomerByMobile(mobile);
    }, 250);
  }

  async function doCustomerSearch(q, preload = false) {
    if ((!q || q.length < 1) && !preload) { hideCustSearch(); return; }
    try {
      const params = new URLSearchParams();
      if (q) params.set('q', q);
      params.set('type', 'C');
      if (preload) params.set('preload', '1');
      const res = await api(siteUrl + '/api/sales-bill/customer-search?' + params.toString(), 'GET');
      if (!res.ok || !res.rows || !res.rows.length) { hideCustSearch(); return; }
      custSearchResults.innerHTML = '';
      res.rows.forEach(cust => {
        const div = document.createElement('div');
        div.className = 'cust-row';
        div.innerHTML = '<span class="code">' + esc(cust.code) + '</span>' + esc(cust.name);
        div.addEventListener('mousedown', () => { _custSearchSelecting = true; });
        div.addEventListener('click', () => { selectCustomer(cust); });
        custSearchResults.appendChild(div);
      });
      custSearchResults.style.display = 'block';
    } catch (e) { hideCustSearch(); }
  }

  $('fCustomerName').addEventListener('input', function() {
    clearTimeout(_custSearchTimer);
    const q = this.value.trim();
    _custSearchTimer = setTimeout(() => doCustomerSearch(q), 300);
  });
  $('fCustomerName').addEventListener('focus', function() {
    clearTimeout(_custSearchTimer);
    _custSearchTimer = setTimeout(() => doCustomerSearch('', true), 120);
  });
  $('fCustomerCodeInput').addEventListener('input', function() {
    this.value = String(this.value || '').toUpperCase();
    clearTimeout(_custSearchTimer);
    const q = this.value.trim();
    if (!q) { hideCustSearch(); return; }
    _custSearchTimer = setTimeout(() => doCustomerSearch(q), 200);
  });
  $('fCustomerCodeInput').addEventListener('focus', function() {
    const q = String(this.value || '').trim();
    clearTimeout(_custSearchTimer);
    _custSearchTimer = setTimeout(() => doCustomerSearch(q, q === ''), 120);
  });

  $('fCustomerCodeInput').addEventListener('blur', function() {
    if (_custSearchSelecting) {
      setTimeout(() => { _custSearchSelecting = false; }, 150);
      return;
    }
    const code = String(this.value || '').trim();
    if (code) fetchCustomerByCode(code, false);
  });
  $('fCustomerCodeInput').addEventListener('change', function() {
    const code = String(this.value || '').trim();
    if (code) fetchCustomerByCode(code, false);
  });
  $('fCustomerCodeInput').addEventListener('keydown', async function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const code = String(this.value || '').trim();
    if (!code) {
      $('fCustomerName')?.focus();
      return;
    }
    await fetchCustomerByCode(code, true);
  });

  $('fMobNo').addEventListener('input', function() {
    scheduleMobileCustomerFetch(this.value);
  });
  $('fMobNo').addEventListener('blur', async function() {
    await fetchCustomerByMobile(this.value);
  });
  $('fMobNo').addEventListener('change', async function() {
    await fetchCustomerByMobile(this.value);
  });
  $('fMobNo').addEventListener('keydown', async function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const done = await fetchCustomerByMobile(this.value);
    if (done) {
      $('fSmName')?.focus();
    }
  });

  $('btnCustSearch').addEventListener('click', () => {
    const code = String(val('fCustomerCodeInput') || '').trim();
    if (code) {
      fetchCustomerByCode(code, true);
      return;
    }
    const q = val('fCustomerName').trim();
    doCustomerSearch(q || '', !q);
  });

  function popupFeatures(width, height) {
    const dualLeft = window.screenLeft ?? window.screenX ?? 0;
    const dualTop = window.screenTop ?? window.screenY ?? 0;
    const outerWidth = window.outerWidth || document.documentElement.clientWidth || screen.width || width;
    const outerHeight = window.outerHeight || document.documentElement.clientHeight || screen.height || height;
    const left = Math.round(dualLeft + Math.max((outerWidth - width) / 2, 0));
    const top = Math.round(dualTop + Math.max((outerHeight - height) / 2, 0));
    return `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`;
  }

  $('btnCustCreate').addEventListener('click', () => {
    const params = new URLSearchParams({
      type: 'C',
      popup: '1',
      name: String(val('fCustomerName') || '').trim(),
      address: String(val('fAddress') || '').trim(),
      mobile: String(val('fMobNo') || '').trim()
    });
    const popupUrl = siteUrl + '/customer/add?' + params.toString();
    const popup = window.open(
      popupUrl,
      'goldapp_customer_create',
      popupFeatures(1200, 760)
    );
    if (popup) popup.focus();
  });

  // Hide customer search when clicking outside
  document.addEventListener('click', function(e) {
    if (!e.target.closest('#custSearchResults') && e.target.id !== 'fCustomerName' && e.target.id !== 'fCustomerCodeInput' && e.target.id !== 'btnCustSearch' && e.target.id !== 'btnCustCreate') {
      hideCustSearch();
    }
  });

  // Auto-fill from customer popup (+) after save.
  window.addEventListener('message', async function(event) {
    const data = event && event.data ? event.data : null;
    if (!data || typeof data !== 'object') return;
    if (data.type === 'goldapp:customer-created') {
      const code = String(data.code || '').trim();
      if (!code) return;
      await fetchCustomerByCode(code, true);
      return;
    }
    if (data.type === 'goldapp:salesbill-print-exit') {
      if (pendingResetAfterPrint && mode === 'bill') {
        pendingResetAfterPrint = false;
        resetToAddMode();
      }
    }
  });

  window.__goldappResetSalesBillAfterPrint = async function() {
    if (pendingResetAfterPrint && mode === 'bill') {
      pendingResetAfterPrint = false;
      await resetToAddMode();
    }
  };

  // ══════════════════════════════════════════════════════════════════
  // 5. ITEMS TABLE
  // ══════════════════════════════════════════════════════════════════
  function newItemRow() {
    return {
      _rowId: nextRowId(), item_code: '', item_name: '', item_type: 'G',
      purity: '', model: '', hsn: '', huid: '',
      qty: 0, weight: 0, stone_wgt: 0, net_wgt: 0,
      stone_price: 0, wastage: 0, wstg_rate: 0, mcrate: 0, mc_perc: 0, vaperc: 0, making_charge: 0,
      manual_mcharge: false, manual_amount: false,
      vaperqty: 0, dmdamt: 0, stkinnos: 'N',
      amount: 0, rate: 0, bcode: 0,
      stktype: '', stktouch: 0
    };
  }

  function renderItemsTable() {
    const tbody = $('itemsTbody');
    tbody.innerHTML = '';
    items.forEach((row, idx) => {
      const tr = document.createElement('tr');
      tr.dataset.rowid = row._rowId;
      tr.innerHTML =
        '<td><input class="ic-code" list="itemCodeList" data-idx="'+idx+'" value="'+esc(row.item_code)+'" style="width:55px;text-align:left;"></td>' +
        '<td style="text-align:left;font-size:10px;">'+esc(row.item_name)+'</td>' +
        '<td>'+esc(row.purity)+'</td>' +
        '<td><input class="ic-model" data-idx="'+idx+'" value="'+esc(row.model)+'" style="width:50px;"></td>' +
        '<td><input class="ic-hsn" data-idx="'+idx+'" value="'+esc(row.hsn || '')+'" style="width:70px;"></td>' +
        '<td><input class="ic-huid" data-idx="'+idx+'" value="'+esc(row.huid)+'" style="width:60px;"></td>' +
        '<td><input class="ic-qty" data-idx="'+idx+'" value="'+row.qty+'" style="width:30px;"></td>' +
        '<td><input class="ic-weight" data-idx="'+idx+'" value="'+fmt3(row.weight)+'" style="width:55px;"></td>' +
        '<td><input class="ic-stwgt" data-idx="'+idx+'" value="'+fmt3(row.stone_wgt)+'" style="width:55px;"></td>' +
        '<td>'+fmt3(row.net_wgt)+'</td>' +
        '<td><input class="ic-stprice" data-idx="'+idx+'" value="'+fmt2(row.stone_price)+'" style="width:55px;"></td>' +
        '<td><input class="ic-wastage" data-idx="'+idx+'" value="'+fmt3(row.wastage || 0)+'" style="width:55px;"></td>' +
        '<td><input class="ic-vaperc" data-idx="'+idx+'" value="'+fmt2(row.mc_perc ?? row.vaperc ?? row.mcrate ?? 0)+'" style="width:48px;"></td>' +
        '<td><input class="ic-mc" data-idx="'+idx+'" value="'+fmt2(row.making_charge)+'" style="width:60px;"></td>' +
        '<td><input class="ic-amount" data-idx="'+idx+'" value="'+fmt2(row.amount)+'" style="width:70px;"></td>' +
        '<td><input class="ic-rate" data-idx="'+idx+'" value="'+fmt2(row.rate)+'" style="width:60px;"></td>';
      tbody.appendChild(tr);
    });
    // filler empty rows
    for (let i = 0, n = Math.max(0, 6 - items.length); i < n; i++) {
      const tr = document.createElement('tr');
      tr.className = 'empty';
      tr.innerHTML = '<td></td>'.repeat(16);
      tbody.appendChild(tr);
    }
    updateTableFooter();

    if (pendingItemCodeFocusIdx >= 0) {
      const idx = pendingItemCodeFocusIdx;
      pendingItemCodeFocusIdx = -1;
      const openPicker = (pendingItemCodePickerIdx === idx);
      if (openPicker) pendingItemCodePickerIdx = -1;
      scheduleItemCodeFocus(idx, openPicker);
    } else if (forceItemCodeFocusIdx >= 0) {
      const idx = forceItemCodeFocusIdx;
      forceItemCodeFocusIdx = -1;
      scheduleItemCodeFocus(idx, false);
    }
  }

  function updateTableFooter() {
    let tQ=0, tW=0, tSW=0, tNW=0, tSP=0, tWst=0, tMC=0, tA=0, tR=0;
    items.forEach(r => {
      tQ  += toNum(r.qty);
      tW  += toNum(r.weight);
      tSW += toNum(r.stone_wgt);
      tNW += toNum(r.net_wgt);
      tSP += toNum(r.stone_price);
      tWst+= toNum(r.wastage);
      tMC += toNum(r.making_charge);
      tA  += toNum(r.amount);
      tR  += toNum(r.rate);
    });
    setVal('ftQty', tQ);
    setVal('ftWeight', fmt3(tW));
    setVal('ftStoneWgt', fmt3(tSW));
    setVal('ftNetWgt', fmt3(tNW));
    setVal('ftStonePrice', fmt2(tSP));
    setVal('ftWastage', fmt2(tWst));
    setVal('ftMcAmt', fmt2(tMC));
    setVal('ftAmount', fmt2(tA));
    setVal('ftRate', items.length ? fmt2(tR / items.length) : '0.00');
    setVal('infoItemCount', items.length);
    setVal('infoQtyWgt', tQ + ' / ' + fmt3(tW));
    recomputeVaInfo();
  }

  function syncDiscountPair(fromPerc = false) {
    const billTotal = toNum(val('fBillTotal')) || roundByMode(items.reduce((s, r) => s + toNum(r.amount || 0), 0));
    if (fromPerc) {
      const discPerc = toNum(val('fDiscountPerc'));
      const disc = billTotal > 0 ? roundByMode((billTotal * discPerc) / 100) : 0;
      setVal('fDiscount', fmt2(disc));
    } else {
      const disc = toNum(val('fDiscount'));
      const perc = billTotal > 0 ? (disc * 100) / billTotal : 0;
      setVal('fDiscountPerc', Number.isFinite(perc) ? toNum(perc).toFixed(2) : '0.00');
    }
  }

  function recomputeVaInfo() {
    let dtAmt = 0;
    let dtVa = 0;
    for (const r of items) {
      const mc = toNum(r.making_charge);
      if (mc > 0) {
        dtAmt += (toNum(r.amount) - mc - toNum(r.stone_price));
        dtVa += mc;
      }
    }
    const disc = toNum(val('fDiscount'));
    const evaAmt = dtVa - disc;
    const evaPerc = dtAmt > 0 ? (evaAmt * 100) / dtAmt : 0;
    setVal('infoTotalVa', fmt2(evaAmt));
    setVal('infoEva', toNum(evaPerc).toFixed(2));
  }

  function recomputeTaxAmtFromPerc() {
    const billTotal = roundByMode(items.reduce((s, r) => s + toNum(r.amount || 0), 0));
    let disc = toNum(val('fDiscount'));
    const discPerc = toNum(val('fDiscountPerc'));
    if (disc <= 0 && discPerc > 0 && billTotal > 0) {
      disc = roundByMode((billTotal * discPerc) / 100);
      setVal('fDiscount', fmt2(disc));
    }
    let taxBase = billTotal;
    if (TAX_AFTER_DISC) taxBase = Math.max(billTotal - disc, 0);
    const taxPerc = toNum(val('fTaxPerc'));
    const taxAmt = roundByMode((taxBase * taxPerc) / 100);
    setVal('fTaxAmt', fmt2(taxAmt));
  }

  function updateRcvdCaptionByNet() {
    const net = toNum(val('fNetTotal'));
    const lbl = $('lblTotalRcvd');
    if (!lbl) return;
    lbl.textContent = net < 0 ? 'Paid' : 'Total Rcvd';
  }

  // PB logic (Total Rcvd changed):
  // if TaxAfterDisc=Y and Credit=N and BalRcvdToDiscount=Y,
  // derive Discount/Tax/AST from received amount.
  function applyReceivedBasedDiscountRule() {
    if (!(TAX_AFTER_DISC && !chk('fCredit') && BAL_RCVD_TO_DISCOUNT)) return;

    const dbamt = toNum(val('fBillTotal'));
    const drcvd = toNum(val('fTotalRcvd'));
    const dnet = toNum(val('fNetTotal'));
    let dtaxamt = toNum(val('fTaxAmt'));
    let dast = toNum(val('fCessAmt'));
    const dnbamt = dbamt - toNum(val('fExchangeAmt')) - toNum(val('fSalesRetAmt'));
    const dtaxperc = toNum(val('fTaxPerc'));
    const dastperc = toNum(val('fCessPerc'));
    const isCst = chk('fInterstate');

    let ddisc = 0;
    if (dtaxperc > 0) {
      let dttaxperc = dtaxperc;
      if (!isCst) dttaxperc += dastperc;

      ddisc = (dnbamt + (dttaxperc * dbamt) / 100 - drcvd) / (1 + dttaxperc / 100);
      dtaxamt = ((dbamt - ddisc) * dtaxperc) / 100;
      dast = !isCst ? ((dbamt - ddisc) * dastperc) / 100 : 0;
    } else {
      ddisc = dnet - drcvd;
    }

    ddisc = Math.round(ddisc);
    setVal('fDiscount', fmt2(ddisc));

    if (dbamt > 0) {
      const dperc = (ddisc * 100) / dbamt;
      setVal('fDiscountPerc', Number.isFinite(dperc) ? dperc.toFixed(2) : '0.00');
    }

    setVal('fTaxAmt', fmt2(dtaxamt));
    setVal('fCessAmt', fmt2(dast));
    recomputeVaInfo();
  }

  function addItemRow() {
    items.push(newItemRow());
    renderItemsTable();
    const inputs = document.querySelectorAll('.ic-code');
    if (inputs.length) inputs[inputs.length - 1].focus();
  }

  function focusItemCodeRow(rowIdx, openPicker = false) {
    const tryFocus = () => {
      const input = $('itemsTbody').querySelector(`.ic-code[data-idx="${rowIdx}"]`);
      if (!input) return false;
      input.focus();
      input.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      // Put caret visibly inside field.
      const len = String(input.value || '').length;
      try { input.setSelectionRange(0, len); } catch (_) {}
      input.click();
      if (openPicker && typeof input.showPicker === 'function') {
        try { input.showPicker(); } catch (_) {}
      }
      return document.activeElement === input;
    };
    if (tryFocus()) return;
    // Re-try after async redraws/recalc timers.
    requestAnimationFrame(() => { tryFocus(); });
    setTimeout(() => { tryFocus(); }, 40);
    setTimeout(() => { tryFocus(); }, 120);
    setTimeout(() => { tryFocus(); }, 260);
  }

  function scheduleItemCodeFocus(rowIdx, openPicker = false) {
    if (itemCodeFocusTimer) {
      clearInterval(itemCodeFocusTimer);
      itemCodeFocusTimer = null;
    }
    let tries = 0;
    const runner = () => {
      tries++;
      focusItemCodeRow(rowIdx, openPicker && tries === 1);
      const input = $('itemsTbody').querySelector(`.ic-code[data-idx="${rowIdx}"]`);
      if ((input && document.activeElement === input) || tries > 30) {
        if (itemCodeFocusTimer) {
          clearInterval(itemCodeFocusTimer);
          itemCodeFocusTimer = null;
        }
      }
    };
    runner();
    itemCodeFocusTimer = setInterval(runner, 50);
  }

  function deleteItemRow() {
    const focused = document.activeElement;
    const idx = focused ? parseInt(focused.dataset?.idx) : -1;
    if (idx >= 0 && idx < items.length) items.splice(idx, 1);
    else if (items.length > 0) items.splice(items.length - 1, 1);
    renderItemsTable();
    triggerRecalc();
  }

  function findDuplicateBarcodeRow(currentIdx, bcode) {
    const bc = Math.trunc(toNum(bcode));
    if (bc <= 0) return -1;
    for (let i = 0; i < items.length; i++) {
      if (i === currentIdx) continue;
      const rowBc = Math.trunc(toNum(items[i]?.bcode || 0));
      const rowCode = String(items[i]?.item_code || '').trim();
      if (rowBc === bc || rowCode === String(bc)) return i;
    }
    return -1;
  }

  function recalcMainByPb(idx, changedCol = '') {
    const r = items[idx];
    if (!r) return;

    const qty = Math.max(0, Math.round(toNum(r.qty)));
    const rate = toNum(r.rate);
    const weight = toNum(r.weight);
    const stoneWgt = toNum(r.stone_wgt);
    const stonePrice = toNum(r.stone_price);
    const dmdamt = toNum(r.dmdamt);
    const code = String(r.item_code || '').trim().toUpperCase();
    const qtype = String(r.purity || '').trim().toUpperCase();
    const itemType = String(r.item_type || '').trim().toUpperCase();
    const stkinnos = String(r.stkinnos || 'N').trim().toUpperCase();
    const vaperc = toNum(r.mc_perc ?? r.vaperc ?? r.mcrate ?? 0);
    const vaperqty = toNum(r.vaperqty);
    const net = Math.max(weight - stoneWgt, 0);
    let wastage = toNum(r.wastage);
    let mcharge = toNum(r.making_charge);
    let amount = toNum(r.amount);

    if (changedCol === 'amount') {
      if (amount > 0) {
        wastage = 0;
        mcharge = 0;
        r.wastage = 0;
        r.making_charge = 0;
      }
      if (stkinnos !== 'Y') {
        const den = (weight - stoneWgt + wastage);
        if (den > 0) r.rate = (amount - mcharge - stonePrice - dmdamt) / den;
      } else if (qty > 0) {
        r.rate = (amount - mcharge - stonePrice - dmdamt) / qty;
      }
    } else if (['weight', 'stonewgt', 'itemcode', 'rate', 'vaperc', 'qty'].includes(changedCol)) {
      // Manual MC% override: percent-based MC should always work.
      if (changedCol === 'vaperc' && vaperc > 0) {
        mcharge = (net * rate * vaperc) / 100;
        if (vaperc === 0 && vaperqty > 0) mcharge = qty * vaperqty;
        r.manual_mcharge = false;
        r.making_charge = mcharge;
      }

      if (USE_VA_MODEL) {
        if (r.manual_mcharge && !['itemcode', 'vaperc'].includes(changedCol)) {
          mcharge = toNum(r.making_charge);
        } else if (vaperc > 0) {
          mcharge = (net * rate * vaperc) / 100;
        } else {
          mcharge = srMCCalc(code, net, qty, rate, qtype);
        }
        if (mcharge === 0 && vaperc > 0) mcharge = (net * rate * vaperc) / 100;
        if (vaperc === 0 && vaperqty > 0) mcharge = qty * vaperqty;
        r.making_charge = mcharge;
      } else {
        wastage = srWastageCalc(code, qtype, net);
        if (wastage === 0) {
          const wstgRate = toNum(r.wstg_rate || 0);
          wastage = (net / 8) * wstgRate;
        }
        if (itemType !== 'G') wastage = 0;
        r.wastage = wastage;

        if (r.manual_mcharge && !['itemcode', 'vaperc'].includes(changedCol)) {
          mcharge = toNum(r.making_charge);
        } else if (vaperc > 0) {
          mcharge = (net * rate * vaperc) / 100;
        } else {
          mcharge = srMCCalc(code, net, qty, rate, qtype);
          if (mcharge === 0) {
            const mcrate = toNum(r.mcrate || 0);
            mcharge = net * mcrate;
          }
        }
        if (vaperqty > 0 && vaperc === 0) mcharge = qty * vaperqty;
        r.making_charge = mcharge;
      }
    }

    r.net_wgt = net;
    if (stkinnos === 'Y') {
      amount = qty * toNum(r.rate) + stonePrice + toNum(r.making_charge) + dmdamt;
    } else {
      amount = (weight - stoneWgt + toNum(r.wastage)) * toNum(r.rate) + stonePrice + toNum(r.making_charge) + dmdamt;
    }
    r.amount = roundByMode(amount);
  }

  function recalcItemAmount(idx) {
    recalcMainByPb(idx, 'rate');
  }

  async function itemLookup(idx) {
    const row = items[idx];
    if (!row) return false;
    const code = row.item_code.trim();
    if (!code) return false;

    const params = new URLSearchParams({
      code,
      tax_perc:      numVal('fTaxPerc'),
      ast_perc:      numVal('fCessPerc'),
      is_cst:        chk('fInterstate') ? '1' : '0',
      gold_rate:     numVal('fRatePerGm') || GOLD_RATE,
      silver_rate:   SILVER_RATE,
      platinum_rate: 0,
    });

    try {
      const res = await api(siteUrl + '/api/sales-bill/item-lookup?' + params.toString(), 'GET');
      if (!res.ok) { showAlert(res.message || 'Item not found'); return false; }
      const d = res.data;
      const dupRow = findDuplicateBarcodeRow(idx, d.bcode || 0);
      if (dupRow >= 0) {
        showAlert('This barcode already entered in row ' + (dupRow + 1) + '.', false, () => {
          row.item_code = '';
          row.bcode = 0;
          row.item_name = '';
          row.weight = 0;
          row.stone_wgt = 0;
          row.net_wgt = 0;
          row.amount = 0;
          renderItemsTable();
          setTimeout(() => {
            const c = $('itemsTbody').querySelector(`.ic-code[data-idx="${idx}"]`);
            if (c) { c.focus(); c.select(); }
          }, 20);
        });
        return false;
      }
      Object.assign(row, {
        item_code: d.item_code, item_name: d.item_name, item_type: d.item_type,
        purity: d.purity || '', model: d.model || '', hsn: d.hsn || '', huid: d.huid || '',
        qty: toNum(d.qty), weight: toNum(d.weight), stone_wgt: toNum(d.stone_wgt),
        net_wgt: toNum(d.net_wgt), stone_price: toNum(d.stone_price),
        wastage: toNum(d.wastage || 0), wstg_rate: toNum(d.wstg_rate), mcrate: toNum(d.mcrate),
        mc_perc: toNum((d.mc_perc ?? d.vaperc ?? d.mcrate) || 0),
        making_charge: toNum(d.making_charge), amount: toNum(d.amount),
        manual_mcharge: false, manual_amount: false,
        rate: toNum(d.rate), bcode: d.bcode || 0,
        stktype: d.stktype || '', stktouch: d.stktouch || 0,
        stkinnos: String(d.stkinnos || 'N').toUpperCase(),
        vaperc: toNum((d.vaperc ?? d.mc_perc ?? d.mcrate) || 0),
        vaperqty: toNum(d.vaperqty || 0),
        dmdamt: toNum(d.dmdamt || 0),
        dmdwgt: toNum(d.dmdwgt || 0),
        dmdnos: Math.max(0, Math.round(toNum(d.dmdnos || 0))),
        dmdunit: d.dmdunit || '',
        bcitem: d.item_code || '',
        cost: toNum(d.cost || 0),
        ttouch: toNum(d.ttouch || 0),
        scode: d.scode || '',
        mccost: toNum(d.mccost || 0),
        pcostperc: toNum(d.pcostperc || 0),
        bcmcamt: toNum(d.bcmcamt || 0),
      });
      recalcMainByPb(idx, 'itemcode');
      if (d.warnings && d.warnings.length) showAlert(d.warnings.join('; '));
      renderItemsTable();
      triggerRecalc();
      // After lookup, continue with qty entry instead of forcing weight focus.
      const qi = document.querySelectorAll('.ic-qty');
      if (qi[idx]) {
        qi[idx].focus();
        qi[idx].select();
      }
      return true;
    } catch (e) {
      showAlert('Lookup error: ' + e.message);
      return false;
    }
  }

  // Item table event delegation (blur captures since blur doesn't bubble)
  $('itemsTbody').addEventListener('blur', function(e) {
    const el = e.target;
    const idx = parseInt(el.dataset?.idx);
    if (isNaN(idx) || !items[idx]) return;
    let blurClass = '';
    for (const c of ['ic-code', 'ic-weight', 'ic-stwgt', 'ic-stprice', 'ic-wastage', 'ic-vaperc', 'ic-mc', 'ic-rate', 'ic-amount', 'ic-qty', 'ic-model', 'ic-hsn', 'ic-huid']) {
      if (el.classList.contains(c)) {
        blurClass = c;
        break;
      }
    }
    const blurKey = blurClass ? (idx + ':' + blurClass) : '';
    if (blurKey && skipNextItemBlurCommitKey === blurKey) {
      skipNextItemBlurCommitKey = '';
      return;
    }
    const r = items[idx];

    if (el.classList.contains('ic-code')) {
      r.item_code = el.value.trim().toUpperCase();
      el.value = r.item_code;
      if (skipNextCodeBlurLookupIdx === idx) {
        skipNextCodeBlurLookupIdx = -1;
      } else if (r.item_code) {
        itemLookup(idx);
      }
    } else if (el.classList.contains('ic-weight')) {
      r.weight = toNum(el.value); recalcMainByPb(idx, 'weight'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-stwgt')) {
      r.stone_wgt = toNum(el.value); recalcMainByPb(idx, 'stonewgt'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-stprice')) {
      r.stone_price = toNum(el.value); recalcMainByPb(idx, 'stoneprice'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-wastage')) {
      r.wastage = toNum(el.value); recalcMainByPb(idx, 'wastage'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-vaperc')) {
      r.mc_perc = toNum(el.value); r.vaperc = r.mc_perc; r.manual_mcharge = false; recalcMainByPb(idx, 'vaperc'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-mc')) {
      r.making_charge = toNum(el.value); r.manual_mcharge = true; recalcMainByPb(idx, 'mcharge'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-rate')) {
      r.rate = toNum(el.value); recalcMainByPb(idx, 'rate'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-amount')) {
      const newAmount = toNum(el.value);
      if (Math.abs(newAmount - toNum(r.amount)) > 0.0001) {
        r.amount = newAmount;
        r.manual_amount = true;
        recalcMainByPb(idx, 'amount'); renderItemsTable(); triggerRecalc();
      } else {
        r.manual_amount = false;
      }
    } else if (el.classList.contains('ic-qty')) {
      r.qty = Math.max(0, Math.round(toNum(el.value))); recalcMainByPb(idx, 'qty'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-model')) {
      r.model = el.value;
    } else if (el.classList.contains('ic-hsn')) {
      r.hsn = el.value;
    } else if (el.classList.contains('ic-huid')) {
      r.huid = el.value;
    }
  }, true);

  function commitItemCell(el, idx) {
    if (!el || isNaN(idx) || !items[idx]) return;
    const r = items[idx];

    if (el.classList.contains('ic-code')) {
      r.item_code = el.value.trim().toUpperCase();
      el.value = r.item_code;
      if (skipNextCodeBlurLookupIdx === idx) {
        skipNextCodeBlurLookupIdx = -1;
      } else if (r.item_code) {
        itemLookup(idx);
      }
    } else if (el.classList.contains('ic-weight')) {
      r.weight = toNum(el.value); recalcMainByPb(idx, 'weight'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-stwgt')) {
      r.stone_wgt = toNum(el.value); recalcMainByPb(idx, 'stonewgt'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-stprice')) {
      r.stone_price = toNum(el.value); recalcMainByPb(idx, 'stoneprice'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-wastage')) {
      r.wastage = toNum(el.value); recalcMainByPb(idx, 'wastage'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-vaperc')) {
      r.mc_perc = toNum(el.value); r.vaperc = r.mc_perc; r.manual_mcharge = false; recalcMainByPb(idx, 'vaperc'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-mc')) {
      r.making_charge = toNum(el.value); r.manual_mcharge = true; recalcMainByPb(idx, 'mcharge'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-rate')) {
      r.rate = toNum(el.value); recalcMainByPb(idx, 'rate'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-amount')) {
      const newAmount = toNum(el.value);
      if (Math.abs(newAmount - toNum(r.amount)) > 0.0001) {
        r.amount = newAmount;
        r.manual_amount = true;
        recalcMainByPb(idx, 'amount'); renderItemsTable(); triggerRecalc();
      } else {
        r.manual_amount = false;
      }
    } else if (el.classList.contains('ic-qty')) {
      r.qty = Math.max(0, Math.round(toNum(el.value))); recalcMainByPb(idx, 'qty'); renderItemsTable(); triggerRecalc();
    } else if (el.classList.contains('ic-model')) {
      r.model = el.value;
    } else if (el.classList.contains('ic-hsn')) {
      r.hsn = el.value;
    } else if (el.classList.contains('ic-huid')) {
      r.huid = el.value;
    }
  }

  // PB-style TAB navigation for main billing grid
  $('itemsTbody').addEventListener('keydown', async function(e) {
    if (e.key !== 'Tab') return;
    const el = e.target;
    const idx = parseInt(el?.dataset?.idx);
    if (isNaN(idx) || !items[idx]) return;

    // Row-based PB-like tab flow for entry columns.
    const seq = ['ic-code', 'ic-model', 'ic-hsn', 'ic-huid', 'ic-qty', 'ic-weight', 'ic-stwgt', 'ic-stprice', 'ic-wastage', 'ic-vaperc', 'ic-mc', 'ic-amount', 'ic-rate'];
    let curClass = '';
    for (const c of seq) {
      if (el.classList.contains(c)) {
        curClass = c;
        break;
      }
    }
    if (!curClass) return;

    e.preventDefault();

    if (curClass === 'ic-code') {
      items[idx].item_code = el.value.trim().toUpperCase();
      el.value = items[idx].item_code;
      if (!items[idx].item_code) return;

      const ok = await itemLookup(idx);
      const cur = items[idx];
      if (ok && cur && cur.item_code && cur.item_name) {
        skipNextCodeBlurLookupIdx = idx;
        focusNextItemEntryAfterLookup(idx, 'ic-model');
      }
      return;
    }

    const curPos = seq.indexOf(curClass);
    let targetRow = idx;
    let targetClass = curClass;

    if (e.shiftKey) {
      if (curPos > 0) {
        targetClass = seq[curPos - 1];
      } else if (idx > 0) {
        targetRow = idx - 1;
        targetClass = seq[seq.length - 1];
      } else {
        return;
      }
    } else {
      if (curPos < seq.length - 1) {
        targetClass = seq[curPos + 1];
      } else {
        targetRow = idx + 1;
        targetClass = seq[0];
        if (!items[targetRow]) {
          items.push(newItemRow());
          renderItemsTable();
        }
      }
    }

    // Commit current cell before any row add/re-render so last-column tab keeps values.
    commitItemCell(el, idx);
    for (const c of seq) {
      if (el.classList.contains(c)) {
        skipNextItemBlurCommitKey = idx + ':' + c;
        break;
      }
    }
    el.blur();
    setTimeout(() => {
      const target = $('itemsTbody').querySelector(`.${targetClass}[data-idx="${targetRow}"]`);
      if (target) target.focus();
    }, 40);
  });

  // PB-like Enter key navigation for ALL item table fields
  // Sequence of active (editable) columns for Enter-key navigation
  const enterSeq = ['ic-code', 'ic-model', 'ic-hsn', 'ic-huid', 'ic-qty', 'ic-weight', 'ic-stwgt', 'ic-stprice', 'ic-wastage', 'ic-vaperc', 'ic-mc', 'ic-amount', 'ic-rate'];

  function focusNextItemEntryAfterLookup(idx, nextClass = 'ic-model') {
    renderItemsTable();
    setTimeout(() => {
      const next = $('itemsTbody').querySelector(`.${nextClass}[data-idx="${idx}"]`);
      if (next) { next.focus(); next.select(); }
    }, 50);
  }

  // Helper: focus the next active column in the Enter sequence, or add a new row
  function enterMoveToNext(idx, curClass) {
    const curPos = enterSeq.indexOf(curClass);
    let targetRow = idx;
    let targetClass;

    if (curPos >= 0 && curPos < enterSeq.length - 1) {
      // Move to next column in same row
      targetClass = enterSeq[curPos + 1];
    } else {
      // At last column or unknown -> add new row, focus ic-code
      targetRow = idx + 1;
      targetClass = enterSeq[0];
      if (!items[targetRow]) {
        items.push(newItemRow());
        renderItemsTable();
      }
    }

    const curEl = document.activeElement;
    // Commit current cell before any row add/re-render so last-column enter keeps values.
    commitItemCell(curEl, idx);
    for (const c of enterSeq) {
      if (curEl?.classList?.contains(c)) {
        skipNextItemBlurCommitKey = idx + ':' + c;
        break;
      }
    }
    if (curEl) curEl.blur();
    setTimeout(() => {
      const target = $('itemsTbody').querySelector(`.${targetClass}[data-idx="${targetRow}"]`);
      if (target) { target.focus(); target.select(); }
    }, 40);
  }

  $('itemsTbody').addEventListener('keydown', async function(e) {
    if (e.key !== 'Enter') return;
    const el = e.target;
    const idx = parseInt(el.dataset?.idx);
    if (isNaN(idx) || !items[idx]) return;

    e.preventDefault();

    if (el.classList.contains('ic-code')) {
      // --- Item code / Barcode entry ---
      items[idx].item_code = el.value.trim().toUpperCase();
      el.value = items[idx].item_code;

      if (!items[idx].item_code) return;

      const ok = await itemLookup(idx);
      const cur = items[idx];

      if (ok && cur && cur.item_code && cur.item_name) {
        skipNextCodeBlurLookupIdx = idx;

        focusNextItemEntryAfterLookup(idx, 'ic-model');
      }
    } else {
      // --- All other fields: PB-like Enter navigation ---
      let curClass = '';
      for (const c of enterSeq) {
        if (el.classList.contains(c)) { curClass = c; break; }
      }
      if (!curClass) return;
      enterMoveToNext(idx, curClass);
    }
  });


  // ══════════════════════════════════════════════════════════════════
  // 6. EXCHANGE MODAL  (PB: w_exchange)
  // ══════════════════════════════════════════════════════════════════
  function newExchRow() {
    return {
      _rowId: nextRowId(), item_code: '', item_name: '', item_type: 'G',
      rate: OLD_GOLD_RATE, qty: 0, weight: 0, mud_less: 0, stone_wgt: 0,
      stone_price: 0, touch: 0, less_perc: 0, less_wgt: 0, extra_rate: 0,
      net_wgt: 0, amount: 0, cost: 0, stktype: DEF_EXCH_STKTYPE, qtype: '',
      stktouch: 0, stkinnos: 'N', rate2: 0, batch: '', stkfd: ''
    };
  }

  // PB w_exchange.dw_exchange.itemchanged: lookup item from items table by code
  function exchItemLookup(idx) {
    const r = exchRows[idx];
    if (!r) return;
    const code = (r.item_code || '').trim().toUpperCase();
    r.item_code = code;
    if (!code) return;
    const it = exchItems.find(i => i.code === code);
    if (!it) {
      showAlert('Invalid Item');
      r.item_code = '';
      r.item_name = '';
      renderExchTable();
      return;
    }
    if (toNum(it.disabled) === 1) {
      showAlert('This Item is Disabled');
      r.item_code = '';
      r.item_name = '';
      renderExchTable();
      return;
    }
    r.item_name = it.name;
    r.item_type = it.itype || 'G';
    r.cost = toNum(it.cost);
    r.stkinnos = String(it.stkinnos || 'N').trim().toUpperCase();
    r.stktype = DEF_EXCH_STKTYPE || (it.defstktype || '');
    r.qtype = it.defquality || '';
    // PB rate logic: OG->OGRATE, other Gold->GRATE, OS->OSRATE, other Silver->SRATE
    if (it.itype === 'S') {
      r.rate = (code === 'OS' && OLD_SIL_RATE > 0) ? OLD_SIL_RATE : SILVER_RATE;
    } else {
      r.rate = (code === 'OG' && OLD_GOLD_RATE > 0) ? OLD_GOLD_RATE : GOLD_RATE;
    }
    recalcExchRow(idx);
    renderExchTable();
  }

  // PB w_exchange.dw_exchange.itemchanged: touch/less/amount calculation
  function recalcExchRow(idx, changedCol) {
    const r = exchRows[idx];
    if (!r) return;
    const wgt = toNum(r.weight), mud = toNum(r.mud_less), stWgt = toNum(r.stone_wgt);
    const stPrice = toNum(r.stone_price), touch = toNum(r.touch);
    const rate = toNum(r.rate), qty = toNum(r.qty);
    const rate2 = toNum(r.rate2 || r.extra_rate);
    let lessWgt = toNum(r.less_wgt);
    const rawNet = Math.max(wgt - mud - stWgt, 0);
    const saleWgt = toNum(($('exSaleWgt')?.textContent ?? 0));

    if (changedCol === 'lesswgt') {
      // PB: manual lesswgt entry clears lessperc
      r.less_perc = 0;
    } else if (changedCol === 'amount') {
      // PB: reverse-calculate rate from amount
      const amt = toNum(r.amount);
      const finalNet = rawNet - lessWgt;
      if (r.stkinnos === 'Y' && qty > 0) {
        r.rate = (amt - stPrice) / qty;
      } else if (finalNet > 0) {
        r.rate = (amt - stPrice) / finalNet;
      }
      r.net_wgt = Math.max(finalNet, 0);
      return;
    } else {
      if (['weight', 'stwgt', 'touch', 'mud', 'lessperc'].includes(changedCol || '')) {
        // PB: touch-based lesswgt (UseTouchToLessWgtCalculationInPurchase)
        if (Math.abs(touch) > 0 && USE_TOUCH_TO_LESS_WGT) {
          const div = CONV_TOUCH > 0 ? CONV_TOUCH : 100;
          lessWgt = rawNet - ((rawNet * touch) / div);
          r.less_wgt = Math.round(lessWgt * 1000) / 1000;
        } else if (Math.abs(toNum(r.less_perc)) > 0) {
          lessWgt = (rawNet * toNum(r.less_perc) / 100);
          r.less_wgt = Math.round(lessWgt * 1000) / 1000;
        }
      }
      lessWgt = toNum(r.less_wgt);
    }

    const netWgt = Math.max(wgt - mud - lessWgt - stWgt, 0);
    r.net_wgt = netWgt;

    // PB: stkinnos=Y -> qty*rate+stprice, else netwgt*rate+stprice
    if (r.stkinnos === 'Y') {
      r.amount = roundByMode(qty * rate + stPrice);
    } else {
      if (netWgt > saleWgt && rate2 > 0) {
        r.amount = roundByMode((saleWgt * rate) + ((netWgt - saleWgt) * rate2));
      } else {
        r.amount = roundByMode((netWgt * rate) + stPrice);
      }
    }
  }

  function renderExchTable() {
    const tbody = $('exchTbody');
    tbody.innerHTML = '';
    exchRows.forEach((row, idx) => {
      const tr = document.createElement('tr');
      tr.className = 'data-row';
      const show2 = (v) => toNum(v) === 0 ? '' : fmt2(v);
      const show3 = (v) => toNum(v) === 0 ? '' : fmt3(v);
      const showN = (v) => toNum(v) === 0 ? '' : String(v);
      tr.innerHTML =
        '<td class="code"><input class="ex-code" data-idx="'+idx+'" value="'+esc(row.item_code)+'" style="width:50px;text-align:left;"></td>' +
        '<td class="name">'+esc(row.item_name)+'</td>' +
        '<td><input class="ex-rate" data-idx="'+idx+'" value="'+fmt2(row.rate)+'" style="width:55px;"></td>' +
        '<td><input class="ex-qty" data-idx="'+idx+'" value="'+showN(Math.round(toNum(row.qty)))+'" style="width:30px;"></td>' +
        '<td><input class="ex-weight" data-idx="'+idx+'" value="'+show3(row.weight)+'" style="width:55px;"></td>' +
        '<td><input class="ex-mud" data-idx="'+idx+'" value="'+show3(row.mud_less)+'" style="width:45px;"></td>' +
        '<td><input class="ex-stwgt" data-idx="'+idx+'" value="'+show3(row.stone_wgt)+'" style="width:45px;"></td>' +
        '<td><input class="ex-stprice" data-idx="'+idx+'" value="'+show2(row.stone_price)+'" style="width:50px;"></td>' +
        '<td><input class="ex-touch" data-idx="'+idx+'" value="'+showN(row.touch)+'" style="width:40px;"></td>' +
        '<td><input class="ex-less" data-idx="'+idx+'" value="'+showN(row.less_perc)+'" style="width:40px;"></td>' +
        '<td><input class="ex-lesswgt" data-idx="'+idx+'" value="'+show3(row.less_wgt)+'" style="width:50px;"></td>' +
        '<td><input class="ex-extra" data-idx="'+idx+'" value="'+show2(row.rate2 || row.extra_rate)+'" style="width:50px;"></td>' +
        '<td>'+show3(row.net_wgt)+'</td>' +
        '<td><input class="ex-amount" data-idx="'+idx+'" value="'+show2(row.amount)+'" style="width:60px;"></td>';
      tbody.appendChild(tr);
    });
    for (let i = 0, n = Math.max(0, 4 - exchRows.length); i < n; i++) {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td></td>'.repeat(14);
      tbody.appendChild(tr);
    }
    updateExchFooter();
  }

  function updateExchFooter() {
    let tQ=0, tW=0, tM=0, tSW=0, tSP=0, tLW=0, tNW=0, tA=0;
    exchRows.forEach(r => {
      tQ  += toNum(r.qty); tW += toNum(r.weight); tM += toNum(r.mud_less);
      tSW += toNum(r.stone_wgt); tSP += toNum(r.stone_price);
      tLW += toNum(r.less_wgt); tNW += toNum(r.net_wgt); tA += toNum(r.amount);
    });
    setVal('exCount', exchRows.length);
    setVal('exFtQty', tQ); setVal('exFtWeight', fmt3(tW));
    setVal('exFtMudLess', fmt3(tM)); setVal('exFtStoneWgt', fmt3(tSW));
    setVal('exFtStonePrice', fmt2(tSP)); setVal('exFtLessWgt', fmt3(tLW));
    setVal('exFtNetWgt', fmt3(tNW)); setVal('exFtAmt', fmt2(tA));
    // totals bar
    const billAmt = items.reduce((s,r) => s + toNum(r.amount), 0);
    setVal('exBillAmt', fmt2(billAmt));
    setVal('exExAmt', fmt2(tA));
    setVal('exNet', fmt2(billAmt - tA));
    setVal('exSaleWgt', fmt3(items.reduce((s,r) => s + toNum(r.net_wgt), 0)));
  }

  // Exchange blur event delegation (PB: itemchanged per column)
  $('exchTbody').addEventListener('blur', function(e) {
    const el = e.target, idx = parseInt(el.dataset?.idx);
    if (isNaN(idx) || !exchRows[idx]) return;
    const r = exchRows[idx];
    if (el.classList.contains('ex-code')) {
      const newCode = el.value.trim().toUpperCase();
      if (newCode && newCode !== r.item_code) {
        r.item_code = newCode;
        exchItemLookup(idx);
      }
    } else {
      let changedCol = '';
      if (el.classList.contains('ex-rate')) {
        r.rate = toNum(el.value);
        changedCol = 'rate';
      } else if (el.classList.contains('ex-qty')) {
        r.qty = Math.max(0, Math.round(toNum(el.value)));
        changedCol = 'qty';
      } else if (el.classList.contains('ex-weight')) {
        r.weight = toNum(el.value);
        changedCol = 'weight';
      } else if (el.classList.contains('ex-mud')) {
        r.mud_less = toNum(el.value);
        changedCol = 'mud';
      } else if (el.classList.contains('ex-stwgt')) {
        r.stone_wgt = toNum(el.value);
        changedCol = 'stwgt';
      } else if (el.classList.contains('ex-stprice')) {
        r.stone_price = toNum(el.value);
        changedCol = 'stprice';
      } else if (el.classList.contains('ex-touch')) {
        const stmp = String(el.value || '').trim();
        if (stmp.includes('*')) {
          const w = toNum(r.weight), sw = toNum(r.stone_wgt);
          const net = w - sw;
          if (net !== 0) {
            let touch1 = toNum(stmp.split('*')[0]);
            let touch2 = toNum(stmp.split('*')[1]);
            if (!touch1) touch1 = 100;
            if (!touch2) touch2 = 100;
            const adjWgt = (net * touch2) / touch1;
            r.touch = (adjWgt * 100) / net;
          } else {
            r.touch = 0;
          }
        } else {
          r.touch = toNum(el.value);
        }
        changedCol = 'touch';
      } else if (el.classList.contains('ex-less')) {
        r.less_perc = toNum(el.value);
        changedCol = 'lessperc';
      }
      else if (el.classList.contains('ex-lesswgt')) { r.less_wgt = toNum(el.value); recalcExchRow(idx, 'lesswgt'); renderExchTable(); return; }
      else if (el.classList.contains('ex-extra')) {
        r.rate2 = toNum(el.value);
        r.extra_rate = r.rate2;
        changedCol = 'rate2';
      }
      else if (el.classList.contains('ex-amount'))  { r.amount = toNum(el.value); recalcExchRow(idx, 'amount'); renderExchTable(); return; }
      recalcExchRow(idx, changedCol); renderExchTable();
    }
  }, true);

  // Exchange Enter key navigation (PB: uekey event)
  $('exchTbody').addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const el = e.target, idx = parseInt(el.dataset?.idx);
    if (isNaN(idx) || !exchRows[idx]) return;
    const moveToNextFieldOrRow = () => {
      const rowEl = el.closest('tr');
      const inputs = rowEl ? Array.from(rowEl.querySelectorAll('input')) : [];
      const cur = inputs.indexOf(el);
      if (cur >= 0 && cur < inputs.length - 1) {
        el.blur();
        setTimeout(() => inputs[cur + 1].focus(), 40);
        return;
      }
      // End of row -> move to next row code; create row if needed
      el.blur();
      const nextIdx = idx + 1;
      if (!exchRows[nextIdx]) {
        exchRows.push(newExchRow());
        renderExchTable();
      }
      setTimeout(() => {
        const codes = document.querySelectorAll('.ex-code');
        if (codes[nextIdx]) codes[nextIdx].focus();
      }, 40);
    };

    if (el.classList.contains('ex-code')) {
      const code = el.value.trim().toUpperCase();
      exchRows[idx].item_code = code;
      if (code) exchItemLookup(idx);
      // PB: after item code, move to rate or weight
      setTimeout(() => {
        if (STOP_ON_EXCH_RATE) {
          const r = document.querySelectorAll('.ex-rate');
          if (r[idx]) r[idx].focus();
          return;
        }
        const w = document.querySelectorAll('.ex-weight');
        if (w[idx]) w[idx].focus();
      }, 50);
    } else if (el.classList.contains('ex-rate')) {
      // PB: ornament items move to qty, others to weight
      el.blur();
      setTimeout(() => {
        const itemCode = String(exchRows[idx]?.item_code || '').trim().toUpperCase();
        const item = exchItems.find(i => i.code === itemCode);
        if ((item?.ornament || 'N') === 'Y') {
          const q = document.querySelectorAll('.ex-qty');
          if (q[idx]) q[idx].focus();
          return;
        }
        const w = document.querySelectorAll('.ex-weight');
        if (w[idx]) w[idx].focus();
      }, 50);
    } else if (el.classList.contains('ex-weight')) {
      const curRow = exchRows[idx];
      const sstkinnos = String(curRow?.stkinnos || 'N').toUpperCase();
      if (toNum(el.value) === 0 && sstkinnos !== 'Y') {
        const wantCorrect = confirm('Weight is not entered.\nDo you want to correct?');
        if (wantCorrect) {
          setTimeout(() => el.focus(), 30);
          return;
        }
      }
      el.blur();
      // PB: after weight, move to touch or lessperc
      setTimeout(() => { const t = document.querySelectorAll('.ex-touch'); if (t[idx]) t[idx].focus(); }, 50);
    } else if (el.classList.contains('ex-touch') || el.classList.contains('ex-less') || el.classList.contains('ex-lesswgt')) {
      el.blur();
      // PB: after less entry, add new row
      setTimeout(() => $('btnExchAdd').click(), 50);
    } else if (el.classList.contains('ex-amount')) {
      // Enter on amount should continue to next row
      moveToNextFieldOrRow();
    } else {
      // Default: move to next field, then next row when row ends
      moveToNextFieldOrRow();
    }
  });

  // PB-style TAB navigation: enforce inside Exchange popup (row/column wrap)
  $('exchangeModal').addEventListener('keydown', function(e) {
    if (e.key !== 'Tab') return;
    if (!$('exchangeModal').classList.contains('active')) return;
    const el = e.target;
    if (!(el instanceof HTMLInputElement)) return;
    if (!el.closest('#exchTbody')) return;

    const idx = parseInt(el.dataset?.idx);
    if (isNaN(idx) || !exchRows[idx]) return;

    const seq = ['ex-code', 'ex-rate', 'ex-qty', 'ex-weight', 'ex-mud', 'ex-stwgt', 'ex-stprice', 'ex-touch', 'ex-less', 'ex-lesswgt', 'ex-extra', 'ex-amount'];
    let curClass = '';
    for (const c of seq) {
      if (el.classList.contains(c)) {
        curClass = c;
        break;
      }
    }
    if (!curClass) return;

    e.preventDefault();
    e.stopPropagation();

    const curPos = seq.indexOf(curClass);
    let targetRow = idx;
    let targetClass = curClass;

    if (e.shiftKey) {
      if (curPos > 0) {
        targetClass = seq[curPos - 1];
      } else if (idx > 0) {
        targetRow = idx - 1;
        targetClass = seq[seq.length - 1];
      } else {
        return;
      }
    } else {
      if (curPos < seq.length - 1) {
        targetClass = seq[curPos + 1];
      } else {
        targetRow = idx + 1;
        targetClass = seq[0];
        if (!exchRows[targetRow]) {
          exchRows.push(newExchRow());
          renderExchTable();
        }
      }
    }

    // Commit current editor first, then move focus to target cell
    el.blur();
    setTimeout(() => {
      const target = $('exchTbody').querySelector(`.${targetClass}[data-idx="${targetRow}"]`);
      if (target) target.focus();
    }, 40);
  }, true);

  $('exchTbody').addEventListener('focusin', function(e) {
    const el = e.target;
    const idx = parseInt(el?.dataset?.idx);
    if (isNaN(idx) || !exchRows[idx]) return;
    setVal('exCount', exchRows.length);
    const b = document.querySelector('.exchange-footer-bar .s-badge');
    if (b) b.textContent = (exchRows[idx].stktype || 'S');
  });

  function openExchange()  {
    if (!exchRows.length) {
      const r = newExchRow();
      r.item_code = DEF_EXCH_ITEM || 'OG';
      exchRows.push(r);
      if (r.item_code) exchItemLookup(0);
    }
    renderExchTable();
    $('exchangeModal').classList.add('active');
  }
  function closeExchange()  { $('exchangeModal').classList.remove('active'); }

  $('btnExchAdd').addEventListener('click', () => {
    exchRows.push(newExchRow());
    renderExchTable();
    const codes = document.querySelectorAll('.ex-code');
    if (codes.length) codes[codes.length - 1].focus();
  });
  $('btnExchDelete').addEventListener('click', () => { if (exchRows.length) { exchRows.pop(); renderExchTable(); } });
  $('btnExchClose').addEventListener('click', closeExchange);
  $('btnExchCancel').addEventListener('click', closeExchange);
  $('btnExchRecalc').addEventListener('click', () => {
    // PB cb_recalc: trigger itemchanged on weight column for all rows
    exchRows.forEach((_, i) => recalcExchRow(i));
    renderExchTable();
  });
  $('btnExchSave').addEventListener('click', () => {
    // PB cb_ok: validate rows (remove empty ones, check weight>0)
    let valid = true;
    for (let i = exchRows.length - 1; i >= 0; i--) {
      const r = exchRows[i];
      if (!r.item_code.trim() || (toNum(r.weight) === 0 && toNum(r.qty) === 0)) {
        exchRows.splice(i, 1); // PB: delete rows with no code or no weight/qty
        continue;
      }
      if (toNum(r.weight) <= 0 && r.stkinnos !== 'Y') {
        showAlert('Check Weight for ' + r.item_code + '. Cannot save.');
        valid = false; break;
      }
    }
    if (!valid) { renderExchTable(); return; }
    const totalAmt = exchRows.reduce((s, r) => s + toNum(r.amount), 0);
    setVal('fExchangeAmt', fmt2(totalAmt));
    closeExchange();
    triggerRecalc();
  });
  $('exchangeModal').addEventListener('click', function(e) { if (e.target === this) closeExchange(); });

  // ══════════════════════════════════════════════════════════════════
  // 7. SALES RETURN MODAL  (PB: w_sreturn)
  // ══════════════════════════════════════════════════════════════════
  function newSrRow() {
    return {
      _rowId: nextRowId(), item_code: '', item_name: '', item_type: 'G', model: '',
      qty: 0, weight: 0, stone_wgt: 0, net_wgt: 0,
      stone_price: 0, wastage: 0, mc_perc: 0, making_charge: 0,
      rate: 0, amount: 0, bcode: 0, stktype: DEF_SRET_STKTYPE, qtype: '',
      stktouch: 0, stkinnos: 'N', cost: 0, dmdamt: 0
    };
  }

  // PB w_sreturn.bcodechk + itemchanged: lookup via item-lookup API (handles barcode + items)
  async function srItemLookup(idx) {
    const r = srRows[idx];
    if (!r) return;
    const code = (r.item_code || '').trim().toUpperCase();
    r.item_code = code;
    if (!code) return;

    const params = new URLSearchParams({
      code,
      tax_perc: numVal('fTaxPerc'),
      ast_perc: numVal('fCessPerc'),
      is_cst: chk('fInterstate') ? '1' : '0',
      gold_rate: GOLD_RATE,
      silver_rate: SILVER_RATE,
      platinum_rate: 0,
    });

    try {
      const res = await api(siteUrl + '/api/sales-bill/item-lookup?' + params.toString(), 'GET');
      if (!res.ok) {
        // Fallback: try exchItems list
        const it = exchItems.find(i => i.code === code);
        if (!it) { showAlert(res.message || 'Item not found: ' + code); return; }
        r.item_name = it.name;
        r.item_type = it.itype;
        if (it.itype === 'G') r.rate = GOLD_RATE;
        else if (it.itype === 'S') r.rate = SILVER_RATE;
      } else {
        const d = res.data;
        Object.assign(r, {
          item_code: d.item_code, item_name: d.item_name, item_type: d.item_type || 'G',
          qty: d.qty || 0, weight: toNum(d.weight), stone_wgt: toNum(d.stone_wgt),
          net_wgt: toNum(d.net_wgt), stone_price: toNum(d.stone_price),
          wastage: toNum(d.wstg_rate), wstg_rate: toNum(d.wstg_rate),
          mc_perc: toNum(d.mcrate), mc_rate: toNum(d.mcrate),
          making_charge: toNum(d.making_charge), rate: toNum(d.rate),
          amount: toNum(d.amount), model: d.model || r.model,
          bcode: d.bcode || 0, stktype: d.stktype || '', stktouch: d.stktouch || 0,
          qtype: (d.purity || r.qtype || ''),
          vaperc: toNum(d.vaperc || 0),
          cost: toNum(d.cost || 0),
          dmdamt: toNum(d.dmdamt), dmdwgt: toNum(d.dmdwgt || 0),
          stkinnos: (d.stkinnos || 'N'),
        });
      }
      recalcSrByPb(idx, 'itemcode');
      renderSrTable();
      // PB: focus weight if zero
      if (r.weight === 0) {
        const w = document.querySelectorAll('.sr-weight');
        if (w[idx]) w[idx].focus();
      }
    } catch (e) {
      showAlert('Lookup error: ' + e.message);
    }
  }

  // PB w_sreturn.dw_exchange.itemchanged: calculate amount
  function recalcSrRow(idx) {
    const r = srRows[idx];
    if (!r) return;
    r.net_wgt = Math.max(toNum(r.weight) - toNum(r.stone_wgt), 0);
    const dmdamt = toNum(r.dmdamt || 0);
    const qty = toNum(r.qty);
    if (String(r.stkinnos || 'N').toUpperCase() === 'Y') {
      r.amount = roundByMode((qty * toNum(r.rate)) + toNum(r.stone_price) + toNum(r.making_charge) + dmdamt);
    } else {
      r.amount = roundByMode((r.net_wgt * toNum(r.rate)) + toNum(r.stone_price) + toNum(r.making_charge) + dmdamt);
    }
  }

  function findSlabRow(rows, code, qtype, netWgt) {
    const n = toNum(netWgt);
    const c = String(code || '').trim().toUpperCase();
    const q = String(qtype || '').trim().toUpperCase();
    const pick = (cc, qq) => (rows || []).find(r =>
      String(r.code || '').toUpperCase() === cc &&
      String(r.iqtype || '').toUpperCase() === qq &&
      n > toNum(r.weight1) &&
      n <= toNum(r.weight2)
    );
    return pick(c, q) || pick(c, '') || pick('', q) || pick('', '') || null;
  }

  // PB global mccalc()
  function srMCCalc(code, netWgt, qty, rate, qtype) {
    let dnetwgt = toNum(netWgt);
    const rqty = Math.max(0, Math.round(toNum(qty)));
    if (DEVIDE_NETWGT_BY_QTY && rqty > 0) dnetwgt /= rqty;

    const row = findSlabRow(mcRows, code, qtype, dnetwgt);
    const dmc = toNum(row?.mc);
    const dmcpergm = toNum(row?.mcpergm);
    const dmcperqty = toNum(row?.mcperqty);
    const dvaperc2 = toNum(row?.vaperc);

    if (dmc > 0) return dmc;
    if (dmcpergm > 0) return toNum(netWgt) * dmcpergm;
    if (dmcperqty > 0) return rqty * dmcperqty;
    if (dvaperc2 > 0) return (toNum(netWgt) * toNum(rate) * dvaperc2) / 100;
    return 0;
  }

  // PB global wastagecalc()
  function srWastageCalc(code, qtype, netWgt) {
    const row = findSlabRow(wstgRows, code, qtype, netWgt);
    const dwstg = toNum(row?.wastage);
    const dperc = toNum(row?.perc);
    if (dwstg > 0) return dwstg;
    if (dperc > 0) return (toNum(netWgt) * dperc) / 100;
    return 0;
  }

  function recalcSrByPb(idx, changedCol = '') {
    const r = srRows[idx];
    if (!r) return;

    const qty = Math.max(0, Math.round(toNum(r.qty)));
    const rate = toNum(r.rate);
    const weight = toNum(r.weight);
    const stoneWgt = toNum(r.stone_wgt);
    const stonePrice = toNum(r.stone_price);
    const dmdamt = toNum(r.dmdamt);
    const code = String(r.item_code || '').trim().toUpperCase();
    const qtype = String(r.qtype || '').trim().toUpperCase();
    const itemType = String(r.item_type || '').trim().toUpperCase();
    const stkinnos = String(r.stkinnos || 'N').trim().toUpperCase();
    const vaperc = toNum(r.vaperc);
    const net = Math.max(weight - stoneWgt, 0);
    let wastage = toNum(r.wastage);
    let mcharge = toNum(r.making_charge);
    let amount = toNum(r.amount);

    if (changedCol === 'mcperc') {
      // User-entered MC%: percent over net value (net_wgt * rate)
      const mcperc = toNum(r.mc_perc || r.mc_rate || 0);
      r.making_charge = (net * rate * mcperc) / 100;
    } else if (changedCol === 'amount') {
      if (amount > 0) {
        wastage = 0;
        mcharge = 0;
      }
      if (stkinnos !== 'Y') {
        const den = (weight - stoneWgt + wastage);
        if (den > 0) r.rate = (amount - mcharge - stonePrice) / den;
      } else if (qty > 0) {
        r.rate = (amount - mcharge - stonePrice) / qty;
      }
    } else if (['weight', 'stonewgt', 'itemcode', 'vaperc', 'qty', 'rate'].includes(changedCol)) {
      if (USE_VA_MODEL) {
        mcharge = srMCCalc(code, net, qty, rate, qtype);
        if (mcharge === 0) mcharge = (net * rate * vaperc) / 100;
        if (itemType !== 'G') mcharge = 0;
        r.making_charge = mcharge;
      } else {
        wastage = srWastageCalc(code, qtype, net);
        if (wastage === 0) {
          const wstgRate = toNum(r.wstg_rate || r.wastage_rate || 0);
          wastage = (net / 8) * wstgRate;
        }
        if (itemType !== 'G') wastage = 0;
        r.wastage = wastage;

        mcharge = srMCCalc(code, net, qty, rate, qtype);
        if (mcharge === 0) {
          const mcrate = toNum(r.mc_rate || r.mc_perc || 0);
          mcharge = net * mcrate;
        }
        r.making_charge = mcharge;
      }
    }

    if (stkinnos === 'Y') amount = qty * toNum(r.rate);
    else amount = (weight - stoneWgt) * toNum(r.rate);

    amount = amount + dmdamt + toNum(r.making_charge) + toNum(r.wastage) * toNum(r.rate) + stonePrice;
    r.amount = roundByMode(amount);
    r.net_wgt = net;
  }

  // PB itemerror-like parsing for mcharge/wastage expressions
  function applySrExpressionInput(idx, colName, rawText) {
    const r = srRows[idx];
    if (!r) return false;
    const stmp = String(rawText || '').trim().toUpperCase();
    if (!stmp) return false;

    const dwgt = toNum(r.weight);
    const dstwgt = toNum(r.stone_wgt);
    const dwastage = toNum(r.wastage);
    const drate = toNum(r.rate);
    const iqty = Math.max(0, Math.round(toNum(r.qty)));
    const sstkinnos = String(r.stkinnos || 'N').toUpperCase();
    const dnetwgt = dwgt - dstwgt;

    if (colName === 'mcharge' && stmp.includes('*')) {
      const dmcrate = toNum(stmp.split('*')[0]);
      let dmcharge = 0;
      if (USE_VA_MODEL) {
        dmcharge = (sstkinnos === 'Y') ? ((iqty * drate * dmcrate) / 100) : ((dnetwgt * drate * dmcrate) / 100);
      } else {
        dmcharge = dnetwgt * dmcrate;
      }
      r.making_charge = dmcharge;
      r.mc_rate = dmcrate;
      r.mc_perc = dmcrate;
      return true;
    }

    if (stmp.includes('%') || stmp.includes('/') || stmp.includes('P') || stmp.includes('G')) {
      if (colName === 'wastage') {
        const dmcrate = toNum(stmp.split('%')[0]);
        if (dnetwgt > 0) {
          const newWastage = (dnetwgt * dmcrate) / 100;
          r.wastage = newWastage;
          r.wstg_rate = (newWastage / dnetwgt) * 8;
          return true;
        }
      }
      if (colName === 'mcharge') {
        let dmcrate = 0;
        if (stmp.includes('%')) dmcrate = toNum(stmp.split('%')[0]);
        else if (stmp.includes('P')) dmcrate = toNum(stmp.split('P')[0]);
        else if (stmp.includes('G')) dmcrate = toNum(stmp.split('G')[0]);
        let dmcharge = 0;
        if (stmp.includes('G')) dmcharge = dnetwgt * dmcrate;
        else dmcharge = (dnetwgt * drate * dmcrate) / 100;
        r.making_charge = dmcharge;
        if (dnetwgt > 0) {
          r.mc_rate = dmcharge / dnetwgt;
          r.mc_perc = r.mc_rate;
        }
        return true;
      }
    }

    if (colName === 'mcharge' && stmp.includes('+')) {
      const dmcrate = toNum(stmp.split('+')[0]);
      const dmcharge = dmcrate - (dwastage * drate);
      r.making_charge = dmcharge;
      if (dnetwgt > 0) {
        r.mc_rate = dmcharge / dnetwgt;
        r.mc_perc = r.mc_rate;
      }
      return true;
    }

    return false;
  }

  function renderSrTable() {
    const tbody = $('srTbody');
    tbody.innerHTML = '';
    srRows.forEach((row, idx) => {
      const tr = document.createElement('tr');
      const show2 = (v) => toNum(v) === 0 ? '' : fmt2(v);
      const show3 = (v) => toNum(v) === 0 ? '' : fmt3(v);
      const showN = (v) => toNum(v) === 0 ? '' : String(v);
      tr.innerHTML =
        '<td><input class="sr-code" data-idx="'+idx+'" value="'+esc(row.item_code)+'" style="width:50px;text-align:left;"></td>' +
        '<td style="text-align:left;font-size:10px;">'+esc(row.item_name)+'</td>' +
        '<td><input class="sr-model" data-idx="'+idx+'" value="'+esc(row.model)+'" style="width:50px;"></td>' +
        '<td><input class="sr-qty" data-idx="'+idx+'" value="'+showN(Math.round(toNum(row.qty)))+'" style="width:30px;"></td>' +
        '<td><input class="sr-weight" data-idx="'+idx+'" value="'+show3(row.weight)+'" style="width:55px;"></td>' +
        '<td><input class="sr-stwgt" data-idx="'+idx+'" value="'+show3(row.stone_wgt)+'" style="width:45px;"></td>' +
        '<td>'+show3(row.net_wgt)+'</td>' +
        '<td><input class="sr-stprice" data-idx="'+idx+'" value="'+show2(row.stone_price)+'" style="width:50px;"></td>' +
        '<td><input class="sr-wastage" data-idx="'+idx+'" value="'+show3(row.wastage)+'" style="width:45px;"></td>' +
        '<td><input class="sr-mcperc" data-idx="'+idx+'" value="'+show2(row.mc_perc)+'" style="width:40px;"></td>' +
        '<td><input class="sr-mc" data-idx="'+idx+'" value="'+show2(row.making_charge)+'" style="width:55px;"></td>' +
        '<td><input class="sr-rate" data-idx="'+idx+'" value="'+show2(row.rate)+'" style="width:55px;"></td>' +
        '<td>'+show2(row.amount)+'</td>';
      tbody.appendChild(tr);
    });
    for (let i = 0, n = Math.max(0, 4 - srRows.length); i < n; i++) {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td></td>'.repeat(13);
      tbody.appendChild(tr);
    }
    updateSrFooter();
  }

  function updateSrFooter() {
    let tQ=0, tW=0, tSW=0, tNW=0, tSP=0, tWst=0, tMC=0, tA=0;
    srRows.forEach(r => {
      tQ  += toNum(r.qty); tW += toNum(r.weight); tSW += toNum(r.stone_wgt);
      tNW += toNum(r.net_wgt); tSP += toNum(r.stone_price);
      tWst += toNum(r.wastage); tMC += toNum(r.making_charge); tA += toNum(r.amount);
    });
    setVal('srCount', srRows.length);
    setVal('srFtQty', tQ); setVal('srFtWeight', fmt3(tW));
    setVal('srFtStoneWgt', fmt3(tSW)); setVal('srFtNetWgt', fmt3(tNW));
    setVal('srFtStonePrice', fmt2(tSP)); setVal('srFtWastage', fmt3(tWst));
    setVal('srFtMc', fmt2(tMC)); setVal('srFtAmt', fmt2(tA));
    // PB bottom totals: discount, tax, cess, retamt, netamt
    const discount = toNum($('srDiscount')?.value);
    const taxPerc  = toNum($('srTaxPerc')?.value);
    const cessPerc = toNum($('srCessPerc')?.value);
    const billAmt = toNum($('srBillAmt')?.value);
    const retAmt = tA - discount;
    const tax  = roundByMode(retAmt * taxPerc / 100);
    const cess = roundByMode(retAmt * cessPerc / 100);
    const exAmt = retAmt + tax + cess;
    const net = billAmt - tA - discount + tax + cess;
    setVal('srTaxAmt', fmt2(tax));
    setVal('srCessAmt', fmt2(cess));
    setVal('srRetAmt', fmt2(exAmt));
    setVal('srNetAmt', fmt2(net));
  }

  // SR blur event delegation (PB: itemchanged per column)
  $('srTbody').addEventListener('blur', function(e) {
    const el = e.target, idx = parseInt(el.dataset?.idx);
    if (isNaN(idx) || !srRows[idx]) return;
    const r = srRows[idx];
    if (el.classList.contains('sr-code')) {
      const newCode = el.value.trim().toUpperCase();
      if (newCode && newCode !== r.item_code) {
        r.item_code = newCode;
        srItemLookup(idx);
      }
    } else {
      let changedCol = '';
      if (el.classList.contains('sr-model'))         { r.model = el.value; changedCol = 'model'; }
      else if (el.classList.contains('sr-qty'))      { r.qty = Math.max(0, Math.round(toNum(el.value))); changedCol = 'qty'; }
      else if (el.classList.contains('sr-weight'))   { r.weight = toNum(el.value); changedCol = 'weight'; }
      else if (el.classList.contains('sr-stwgt'))    { r.stone_wgt = toNum(el.value); changedCol = 'stonewgt'; }
      else if (el.classList.contains('sr-stprice'))  { r.stone_price = toNum(el.value); changedCol = 'stoneprice'; }
      else if (el.classList.contains('sr-wastage'))  {
        if (!applySrExpressionInput(idx, 'wastage', el.value)) r.wastage = toNum(el.value);
        changedCol = 'wastage';
      }
      else if (el.classList.contains('sr-mcperc'))   { r.mc_perc = toNum(el.value); r.mc_rate = r.mc_perc; changedCol = 'mcperc'; }
      else if (el.classList.contains('sr-mc'))       {
        if (!applySrExpressionInput(idx, 'mcharge', el.value)) r.making_charge = toNum(el.value);
        changedCol = 'mcharge';
      }
      else if (el.classList.contains('sr-rate'))     { r.rate = toNum(el.value); changedCol = 'rate'; }
      recalcSrByPb(idx, changedCol); renderSrTable();
    }
  }, true);

  // SR Enter key navigation (PB: uekey event)
  $('srTbody').addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const el = e.target, idx = parseInt(el.dataset?.idx);
    if (isNaN(idx) || !srRows[idx]) return;
    if (el.classList.contains('sr-code')) {
      const code = el.value.trim().toUpperCase();
      srRows[idx].item_code = code;
      if (code) srItemLookup(idx);
      if (flagY(sw.AutoPopupStktype, 'N') && code) {
        const parsed = promptStkQType(srRows[idx].stktype || '', srRows[idx].qtype || '', srRows[idx].stktouch || 0);
        if (parsed && (parsed.stktype || parsed.qtype || parsed.stktouch > 0)) {
          srRows[idx].stktype = parsed.stktype;
          srRows[idx].qtype = parsed.qtype;
          if (parsed.stktouch > 0) srRows[idx].stktouch = parsed.stktouch;
        }
      }
      // PB: after item code, move to qty
      setTimeout(() => { const q = document.querySelectorAll('.sr-qty'); if (q[idx]) q[idx].focus(); }, 100);
    } else if (el.classList.contains('sr-weight')) {
      if (toNum(el.value) === 0) {
        const wantCorrect = confirm('Weight is not entered.\nDo you want to correct?');
        if (wantCorrect) {
          setTimeout(() => el.focus(), 30);
          return;
        }
      }
      el.blur();
      setTimeout(() => { const mc = document.querySelectorAll('.sr-mc'); if (mc[idx]) mc[idx].focus(); }, 50);
    } else if (el.classList.contains('sr-rate') || el.classList.contains('sr-mc')) {
      el.blur();
      // PB: after rate or mc, add new row
      setTimeout(() => $('btnSrAdd').click(), 50);
    } else {
      el.blur();
      const inputs = el.closest('tr').querySelectorAll('input');
      const cur = Array.from(inputs).indexOf(el);
      if (cur < inputs.length - 1) setTimeout(() => inputs[cur + 1].focus(), 50);
    }
  });

  // SR helper keys: F1/F7/F12 (web equivalents for PB popups)
  $('srTbody').addEventListener('keydown', function(e) {
    const el = e.target, idx = parseInt(el.dataset?.idx);
    if (isNaN(idx) || !srRows[idx]) return;
    const r = srRows[idx];

    if (e.key === 'F1') {
      e.preventDefault();
      if (el.classList.contains('sr-code')) {
        const pick = prompt('Item Code', r.item_code || '');
        if (pick !== null && String(pick).trim() !== '') {
          r.item_code = String(pick).trim().toUpperCase();
          srItemLookup(idx);
          renderSrTable();
        }
        return;
      }
      if (el.classList.contains('sr-wastage') || el.classList.contains('sr-mc')) {
        if (USE_VA_MODEL && el.classList.contains('sr-mc')) {
          const va = prompt('VA %', String(toNum(r.vaperc || 0)));
          if (va !== null) {
            r.vaperc = toNum(va);
            recalcSrByPb(idx, 'vaperc');
            renderSrTable();
          }
        } else {
          const w = prompt('Wastage', String(toNum(r.wastage || 0)));
          const m = prompt('Making Charge', String(toNum(r.making_charge || 0)));
          if (w !== null) r.wastage = toNum(w);
          if (m !== null) r.making_charge = toNum(m);
          recalcSrByPb(idx, 'weight');
          renderSrTable();
        }
      }
      return;
    }

    if (e.key === 'F7') {
      e.preventDefault();
      const cur = String(r.stkfd || 'N').toUpperCase();
      const v = prompt('STKFD (Y/N)', cur);
      if (v !== null) {
        const yn = String(v).trim().toUpperCase();
        r.stkfd = (yn === 'Y') ? 'Y' : 'N';
      }
      return;
    }

    if (e.key === 'F12') {
      e.preventDefault();
      if (el.classList.contains('sr-weight')) {
        const w = prompt('Weight', String(toNum(r.weight || 0)));
        if (w !== null && toNum(w) > 0) {
          r.weight = toNum(w);
          recalcSrByPb(idx, 'weight');
          renderSrTable();
        }
      } else {
        const parsed = promptStkQType(r.stktype || '', r.qtype || '', r.stktouch || 0);
        if (parsed) {
          r.stktype = parsed.stktype;
          r.qtype = parsed.qtype;
          if (parsed.stktouch > 0) r.stktouch = parsed.stktouch;
          renderSrTable();
        }
      }
      return;
    }
  });

  // PB-style TAB navigation inside Sales Return popup
  $('salesReturnModal').addEventListener('keydown', function(e) {
    if (e.key === 'F9') {
      e.preventDefault();
      $('btnSrSave').click();
      return;
    }
    if (e.key !== 'Tab') return;
    if (!$('salesReturnModal').classList.contains('active')) return;
    const el = e.target;
    if (!(el instanceof HTMLInputElement)) return;
    if (!el.closest('#srTbody')) return;
    const idx = parseInt(el.dataset?.idx);
    if (isNaN(idx) || !srRows[idx]) return;

    const seq = ['sr-code', 'sr-model', 'sr-qty', 'sr-weight', 'sr-stwgt', 'sr-stprice', 'sr-wastage', 'sr-mcperc', 'sr-mc', 'sr-rate'];
    let curClass = '';
    for (const c of seq) {
      if (el.classList.contains(c)) { curClass = c; break; }
    }
    if (!curClass) return;

    e.preventDefault();
    e.stopPropagation();
    const curPos = seq.indexOf(curClass);
    let targetRow = idx;
    let targetClass = curClass;

    if (e.shiftKey) {
      if (curPos > 0) targetClass = seq[curPos - 1];
      else if (idx > 0) { targetRow = idx - 1; targetClass = seq[seq.length - 1]; }
      else return;
    } else {
      if (curPos < seq.length - 1) targetClass = seq[curPos + 1];
      else {
        targetRow = idx + 1;
        targetClass = seq[0];
        if (!srRows[targetRow]) {
          srRows.push(newSrRow());
          renderSrTable();
        }
      }
    }

    el.blur();
    setTimeout(() => {
      const target = $('srTbody').querySelector(`.${targetClass}[data-idx="${targetRow}"]`);
      if (target) target.focus();
    }, 40);
  }, true);

  // SR bottom field changes trigger footer recalc
  ['srDiscount','srTaxPerc','srCessPerc'].forEach(id => {
    const el = $(id);
    if (el) el.addEventListener('change', () => updateSrFooter());
  });

  function openSalesReturn()  {
    const billAmt = toNum(val('fBillTotal'));
    setVal('srBillAmt', fmt2(billAmt));
    if (!toNum(val('srTaxPerc'))) setVal('srTaxPerc', String(numVal('fTaxPerc') || 0));
    if (!toNum(val('srCessPerc'))) setVal('srCessPerc', String(numVal('fCessPerc') || 0));
    if (!srRows.length) {
      srRows.push(newSrRow());
    }
    renderSrTable();
    $('salesReturnModal').classList.add('active');
    const code = document.querySelector('#srTbody .sr-code[data-idx="0"]');
    if (code) code.focus();
  }
  function closeSalesReturn()  { $('salesReturnModal').classList.remove('active'); }

  // ── Bill Item Selection Popup ──────────────────────────────────────
  function openBillSelectPopup() {
    const billItems = items.filter(r => r.item_code && r.item_code.trim());
    if (!billItems.length) { showAlert('No items in the bill to select.'); return; }
    const tbody = $('srBillSelTbody');
    tbody.innerHTML = '';
    billItems.forEach((r, i) => {
      const tr = document.createElement('tr');
      tr.style.cssText = 'border-bottom:1px solid #e2e8f0;' + (i % 2 === 0 ? 'background:#f7fafc;' : '');
      tr.innerHTML =
        '<td style="padding:4px 6px;text-align:center;"><input type="checkbox" class="srBillSelChk" data-idx="'+i+'"></td>' +
        '<td style="padding:4px 6px;">'+esc(r.item_code)+'</td>' +
        '<td style="padding:4px 6px;">'+esc(r.item_name)+'</td>' +
        '<td style="padding:4px 6px;text-align:right;">'+Math.round(toNum(r.qty))+'</td>' +
        '<td style="padding:4px 6px;text-align:right;">'+fmt3(r.weight)+'</td>' +
        '<td style="padding:4px 6px;text-align:right;">'+fmt3(r.stone_wgt)+'</td>' +
        '<td style="padding:4px 6px;text-align:right;">'+fmt3(r.net_wgt)+'</td>' +
        '<td style="padding:4px 6px;text-align:right;">'+fmt2(r.amount)+'</td>' +
        '<td style="padding:4px 6px;text-align:center;">'+(r.bcode ? String(r.bcode) : '-')+'</td>';
      tbody.appendChild(tr);
    });
    $('chkSrBillSelAll').checked = false;
    const overlay = $('srBillSelectOverlay');
    overlay.style.display = 'flex';
    overlay._billItems = billItems;
  }
  function closeBillSelectPopup() {
    $('srBillSelectOverlay').style.display = 'none';
  }
  $('chkSrBillSelAll').addEventListener('change', function() {
    document.querySelectorAll('.srBillSelChk').forEach(c => { c.checked = this.checked; });
  });
  $('btnSrBillSelClose').addEventListener('click', closeBillSelectPopup);
  $('btnSrBillSelCancel').addEventListener('click', closeBillSelectPopup);
  $('btnSrSelectBill').addEventListener('click', openBillSelectPopup);
  $('btnSrBillSelAdd').addEventListener('click', () => {
    const overlay = $('srBillSelectOverlay');
    const billItems = overlay._billItems || [];
    const checked = document.querySelectorAll('.srBillSelChk:checked');
    if (!checked.length) { showAlert('Please select at least one item.'); return; }
    // Remove empty placeholder rows before adding
    for (let i = srRows.length - 1; i >= 0; i--) {
      if (!srRows[i].item_code.trim()) srRows.splice(i, 1);
    }
    checked.forEach(chk => {
      const r = billItems[parseInt(chk.dataset.idx)];
      if (!r) return;
      srRows.push({
        ...newSrRow(),
        item_code:    r.item_code,
        item_name:    r.item_name,
        item_type:    r.item_type || 'G',
        model:        r.model || '',
        hsn:          r.hsn || '',
        qty:          toNum(r.qty),
        weight:       toNum(r.weight),
        stone_wgt:    toNum(r.stone_wgt),
        net_wgt:      toNum(r.net_wgt),
        stone_price:  toNum(r.stone_price),
        wastage:      toNum(r.wastage),
        mc_perc:      toNum(r.mc_perc ?? r.making_charge),
        making_charge:toNum(r.making_charge),
        rate:         toNum(r.rate),
        amount:       toNum(r.amount),
        bcode:        r.bcode || 0,
        stktype:      r.stktype || DEF_SRET_STKTYPE,
        stktouch:     toNum(r.stktouch),
        stkinnos:     r.stkinnos || 'N',
        cost:         toNum(r.cost),
      });
    });
    closeBillSelectPopup();
    renderSrTable();
    updateSrFooter();
  });

  $('btnSrAdd').addEventListener('click', () => {
    // PB: only add if last row has code+amount, or no rows
    const last = srRows.length ? srRows[srRows.length - 1] : null;
    if (!last || (last.item_code.trim() && toNum(last.amount) > 0) || srRows.length === 0) {
      srRows.push(newSrRow());
      renderSrTable();
      const codes = document.querySelectorAll('.sr-code');
      if (codes.length) codes[codes.length - 1].focus();
    } else {
      const codes = document.querySelectorAll('.sr-code');
      if (codes.length) codes[codes.length - 1].focus();
    }
  });
  $('btnSrDelete').addEventListener('click', () => { if (srRows.length) { srRows.pop(); renderSrTable(); } });
  $('btnSrClose').addEventListener('click', closeSalesReturn);
  $('btnSrCancel').addEventListener('click', closeSalesReturn);
  $('btnSrSave').addEventListener('click', () => {
    // PB cb_ok: validate and save, remove empty rows
    for (let i = srRows.length - 1; i >= 0; i--) {
      const r = srRows[i];
      if (!r.item_code.trim() || (toNum(r.weight) === 0 && toNum(r.qty) === 0)) {
        srRows.splice(i, 1);
      }
    }
    renderSrTable();
    const retAmt = toNum($('srRetAmt')?.value);
    setVal('fSalesRetAmt', fmt2(retAmt));
    closeSalesReturn();
    triggerRecalc();
  });
  $('salesReturnModal').addEventListener('click', function(e) { if (e.target === this) closeSalesReturn(); });

  // ══════════════════════════════════════════════════════════════════
  // 8. RECALCULATION (server-side via /api/sales-bill/recalc)
  // ══════════════════════════════════════════════════════════════════
  let _recalcTimer = null;
  function triggerRecalc() {
    clearTimeout(_recalcTimer);
    _recalcTimer = setTimeout(doRecalc, 300);
  }
  function triggerRecalcNow() {
    clearTimeout(_recalcTimer);
    doRecalc();
  }

  async function doRecalc() {
    recomputeTaxAmtFromPerc();
    const payload = {
      items: items.map(r => ({
        qty: r.qty, weight: r.weight, stone_wgt: r.stone_wgt, net_wgt: r.net_wgt,
        stone_price: r.stone_price, making_charge: r.making_charge, amount: r.amount, rate: r.rate,
      })),
      exchange: exchRows.map(r => ({ amount: r.amount })),
      sales_return: srRows.map(r => ({ amount: r.amount })),
      sr_tax_amt:  toNum($('srTaxAmt')?.value || 0),
      sr_cess_amt: toNum($('srCessAmt')?.value || 0),
      extra: {
        discount:        numVal('fDiscount'),
        discount_perc:   numVal('fDiscountPerc'),
        tax_perc:        numVal('fTaxPerc'),
        tax:             numVal('fTaxAmt'),
        ast_perc:        numVal('fCessPerc'),
        ast:             numVal('fCessAmt'),
        tcs_perc:        numVal('fTcsPerc'),
        repair_charge:   numVal('fRepair'),
        hallmark_charge: numVal('fHmc'),
        advance:         numVal('fPrevAdv'),
        fancy_amt:       numVal('fFancyAmt'),
        scheme_amt:      numVal('fSchemeAmt'),
        scheme_ledger:   val('fSchemeLedger') || 'APP',
        bank_charge:     numVal('fBcAmt'),
        add_bank_charge: chk('fAddBc'),
        opening_balance: numVal('fOB'),
        round_to:        0,
        credit:          chk('fCredit'),
        received:        numVal('fTotalRcvd'),
        auto_rcvd:       !totalRcvdDirty,
        grand_to_rcvd:   false,
      }
    };
    try {
      const res = await api(siteUrl + '/api/sales-bill/recalc', 'POST', payload);
      if (!res.ok) return;
      applyRecalcResult(res.data);
    } catch (e) { /* silent fail on recalc */ }
  }

  function applyRecalcResult(data) {
    setVal('fBillTotal', fmt2(data.bill_total));
    setVal('fExchangeAmt', fmt2(data.exchange_amount));
    setVal('fSalesRetAmt', fmt2(data.return_amount));
    setVal('fNetTotal', fmt2(data.net_total));
    const ex = data.extra || {};
    if (document.activeElement?.id !== 'fDiscount' && !discountDirty) {
      setVal('fDiscount', toNum(ex.discount) > 0 ? fmt2(ex.discount) : '');
      setVal('fDiscountPerc', ex.discount_perc ?? 0);
    }
    setVal('fTaxAmt', fmt2(ex.tax));
    setVal('fCessAmt', fmt2(ex.ast));
    setVal('fTcsAmt', fmt2(ex.tcs_amt));
    setVal('fGrandAmt', fmt2(ex.grand_amt));
    setVal('fNetAmt', fmt2(ex.net_amt));
    if (document.activeElement?.id !== 'fTotalRcvd' && !totalRcvdDirty) {
      setVal('fTotalRcvd', fmt2(ex.received));
    }
    setVal('fBalance', fmt2(ex.balance));
    setVal('fCb', fmt2(ex.net_balance));
    updateRcvdCaptionByNet();
    updateTableFooter();
  }

  // ══════════════════════════════════════════════════════════════════
  // 9. FINANCIAL FIELD CHANGE EVENTS
  // ══════════════════════════════════════════════════════════════════
  const RECALC_FIELDS = [
    'fTaxPerc','fTaxAmt',
    'fRepair','fCessPerc','fCessAmt','fFancyAmt','fSchemeAmt','fSchemeLedger',
    'fTcsPerc','fHmc','fOB','fPrevAdv','fBcAmt','fBcPerc'
  ];
  const RECALC_CHECKS = ['fAddBc','fCredit','fInterstate'];

  RECALC_FIELDS.forEach(id => {
    const el = $(id);
    if (el) el.addEventListener('change', triggerRecalc);
  });
  RECALC_CHECKS.forEach(id => {
    const el = $(id);
    if (el) el.addEventListener('change', triggerRecalc);
  });

  // PB-like discount/discount% sync.
  $('fDiscount')?.addEventListener('change', function() {
    syncDiscountPair(false);
    recomputeVaInfo();
    triggerRecalc();
  });
  $('fDiscountPerc')?.addEventListener('change', function() {
    syncDiscountPair(true);
    recomputeVaInfo();
    triggerRecalc();
  });
  $('fDiscount')?.addEventListener('input', function() {
    discountDirty = true;
    syncDiscountPair(false);
    recomputeVaInfo();
    triggerRecalc();
  });
  $('fDiscount')?.addEventListener('change', function() { discountDirty = false; });
  $('fDiscount')?.addEventListener('blur', function() { discountDirty = false; });
  $('fDiscountPerc')?.addEventListener('input', function() {
    syncDiscountPair(true);
    recomputeVaInfo();
    triggerRecalc();
  });
  $('fTaxPerc')?.addEventListener('input', function() {
    recomputeTaxAmtFromPerc();
    triggerRecalc();
  });
  $('fTaxPerc')?.addEventListener('change', function() {
    recomputeTaxAmtFromPerc();
    triggerRecalc();
  });
  $('fCredit')?.addEventListener('change', function() {
    // PB cbx_credit clicked: if checked then clear received and recalc
    if (chk('fCredit')) {
      setVal('fTotalRcvd', '');
    }
    triggerRecalc();
  });
  $('fTotalRcvd')?.addEventListener('input', function() {
    totalRcvdDirty = true;
    triggerRecalc();
  });
  $('fTotalRcvd')?.addEventListener('change', function() {
    this.value = fmt2(this.value);
    triggerRecalcNow();
  });
  $('fTotalRcvd')?.addEventListener('blur', function() {
    this.value = fmt2(this.value);
    triggerRecalcNow();
  });

  // Bill type change -> update rate + PB bill-type-wise bill no/tax
  $('fBillType').addEventListener('change', async function() {
    applyHeaderRate();
    if (mode === 'bill' && !chk('fManualBillNo') && BILL_TYPEWISE_BILLNO) {
      await refreshNextBillNo();
    } else if (mode === 'bill' && !chk('fManualBillNo') && TAX_BILL_TYPE_WISE) {
      await refreshNextBillNo();
    }
  });
  $('fWs').addEventListener('change', function() { applyHeaderRate(); });

  // ══════════════════════════════════════════════════════════════════
  // 10. SAVE / LOAD / NAVIGATE
  // ══════════════════════════════════════════════════════════════════
  async function saveBill() {
    if (isSaving) return;
    const billNo = val('fBillNo').trim();
    if (!billNo) { showAlert('Bill number is required.'); return; }

    const salesmanCode = val('fSmName') || '';
    if (!salesmanCode) {
      showAlert('Please select salesman.', false, () => {
        $('fSmName')?.focus();
      });
      return;
    }

    isSaving = true;
    $('btnSave').textContent = 'Saving...';

    // Resolve display names from select options
    const counterSel = $('fCounter');
    const counterName = counterSel && counterSel.selectedIndex > 0 ? counterSel.options[counterSel.selectedIndex].textContent : '';
    const smSel = $('fSmName');
    const smName = smSel && smSel.selectedIndex > 0 ? smSel.options[smSel.selectedIndex].textContent : '';

    const payload = {
      bill_no:       billNo,
      slno:          currentLegacySlno || null,
      form_mode:     mode,
      manual_bill_no: chk('fManualBillNo'),
      bill_date:     val('fBillDate'),
      bill_time:     $('fBillTime').textContent || nowTime(),
      bill_type:     val('fBillType') || 'G',
      is_quotation:  chk('fQuotationMode'),
      source_quotation_bill_no: selectedQuotationBillNo || null,
      customer_name: val('fCustomerName'),
      customer_code: val('fCustomerCode') || null,
      address:       val('fAddress') || null,
      mobile:        val('fMobNo') || null,
      gst_no:        val('fGstNo') || null,
      pan_no:        val('fPanNo') || null,
      co_party_code: val('fCo') || null,
      state_code:    val('fState') || null,
      due_date:      val('fDueDate') || null,
      vehicle_no:    val('fVehNo') || null,
      supply_place:  val('fSupplyPlace') || null,
      rate_per_gm:   numVal('fRatePerGm'),
      counter_name:  counterName,
      counter_code:  val('fCounter') || null,
      salesman_name: smName,
      salesman_code: salesmanCode || null,
      agent_code:    val('fAgent') || null,
      approved_by:   val('fApprovedBy') || null,
      cashbank_code: val('fCashBank') || null,
      distance:      numVal('fDistance'),
      items:         items.map(r => ({
        item_code: r.item_code, item_name: r.item_name, item_type: r.item_type,
        purity: r.purity, model: r.model, hsn: r.hsn, huid: r.huid,
        qty: r.qty, weight: r.weight, stone_wgt: r.stone_wgt, net_wgt: r.net_wgt,
        stone_price: r.stone_price, making_charge: r.making_charge,
        rate: r.rate, amount: r.amount,
        bcode: r.bcode || 0,
        stkinnos: r.stkinnos || 'N',
        stktype: r.stktype || '',
        stktouch: r.stktouch || 0,
        cost: r.cost || 0,
        dmdamt: r.dmdamt || 0,
        dmdwgt: r.dmdwgt || 0,
        dmdnos: r.dmdnos || 0,
        dmdunit: r.dmdunit || '',
        vaperc: r.vaperc || 0,
      })),
      exchange:      exchRows.map(r => ({
        item_code: r.item_code, item_name: r.item_name, item_type: r.item_type, rate: r.rate,
        qty: r.qty, weight: r.weight, mud_less: r.mud_less,
        stone_wgt: r.stone_wgt, stone_price: r.stone_price,
        touch: r.touch, less_perc: r.less_perc, less_wgt: r.less_wgt,
        extra_rate: r.rate2 || r.extra_rate, rate2: r.rate2 || r.extra_rate,
        net_wgt: r.net_wgt, amount: r.amount, cost: r.cost,
        stkfd: r.stkfd, stktype: r.stktype, qtype: r.qtype,
        stktouch: r.stktouch, batch: r.batch, stkinnos: r.stkinnos,
      })),
      sales_return:  srRows.map(r => ({
        item_code: r.item_code, item_name: r.item_name, model: r.model,
        qty: r.qty, weight: r.weight, stone_wgt: r.stone_wgt, net_wgt: r.net_wgt,
        stone_price: r.stone_price, wastage: r.wastage,
        mc_perc: r.mc_perc, making_charge: r.making_charge,
        rate: r.rate, amount: r.amount,
        bcode: r.bcode || 0, stktype: r.stktype || '', qtype: r.qtype || '',
        stkinnos: r.stkinnos || 'N', stktouch: r.stktouch || 0, cost: r.cost || 0,
        dmdamt: r.dmdamt || 0, dmdwgt: r.dmdwgt || 0, vaperc: r.vaperc || 0, stkfd: r.stkfd || 'N',
      })),
      sr_tax_amt:  toNum($('srTaxAmt')?.value || 0),
      sr_cess_amt: toNum($('srCessAmt')?.value || 0),
      extra: {
        discount:        numVal('fDiscount'),
        discount_perc:   numVal('fDiscountPerc'),
        tax_perc:        numVal('fTaxPerc'),
        tax:             numVal('fTaxAmt'),
        ast_perc:        numVal('fCessPerc'),
        ast:             numVal('fCessAmt'),
        tcs_perc:        numVal('fTcsPerc'),
        tcs_amt:         numVal('fTcsAmt'),
        repair_charge:   numVal('fRepair'),
        hallmark_charge: numVal('fHmc'),
        advance:         numVal('fPrevAdv'),
        fancy_amt:       numVal('fFancyAmt'),
        scheme_amt:      numVal('fSchemeAmt'),
        scheme_ledger:   val('fSchemeLedger') || 'APP',
        bank_charge:     numVal('fBcAmt'),
        bc_perc:         numVal('fBcPerc'),
        cc_amt:          numVal('fCcardAmt'),
        chq_amt:         numVal('fChqAmt'),
        chq_bank:        val('fChqBank') || '',
        chq_no:          val('fChqNo') || '',
        chq_date:        val('fChqDate') || '',
        chq_pdc:         chk('fPdc'),
        cc_pdc:          chk('fCcardPdc'),
        redeem_points:   chk('fRedeemPoints') ? 1 : 0,
        ptax_perc:       numVal('fPurchTaxPerc'),
        ptax_amt:        numVal('fPurchTaxAmt'),
        is_cst:          chk('fInterstate'),
        add_bank_charge: chk('fAddBc'),
        opening_balance: numVal('fOB'),
        received:        numVal('fTotalRcvd'),
        balance:         numVal('fBalance'),
        credit:          chk('fCredit'),
        note:            val('fNote'),
      }
    };

    try {
      const res = await api(siteUrl + '/api/sales-bill/save', 'POST', payload);
      if (res.ok) {
        const savedBillNo = String(res.bill_no || res.data?.bill_no || billNo || '').trim();
        currentBillNo = savedBillNo;
        if (savedBillNo) {
          setVal('fBillNo', savedBillNo);
        }
        if (res.data) applyRecalcResult(res.data);
        const savedNetTotal = toNum(res.data?.net_total ?? val('fGrandAmt') ?? 0);
        const printSlno = res.legacy_slno;
        const isQuotation = !!(res.data?.is_quotation ?? payload.is_quotation);
        if (mode === 'bill') {
          showAlert((isQuotation ? 'Quotation saved: ' : 'Bill saved: ') + savedBillNo, true, async () => {
            if (isQuotation) {
              openBillPrintPreview('short');
            } else if (printSlno) {
              const printUrl = '{{ url("/sales-bill-print") }}?slno=' + printSlno + (isQuotation ? '&control=2' : '');
              window.open(printUrl, '_blank', 'width=950,height=820,scrollbars=yes');
            }
            if (!isQuotation) {
              try {
                await maybePromptEInvoice(savedBillNo, savedNetTotal);
              } catch (_) {}
            }
            pendingResetAfterPrint = false;
            setTimeout(() => {
              const nextN = (parseInt(new URLSearchParams(window.location.search).get('n') || '1', 10) || 1) + 1;
              window.location.href = '{{ url("/sales-bill") }}?n=' + nextN;
            }, 100);
          });
        } else {
          showAlert('Bill saved: ' + billNo, true);
        }
      } else {
        showAlert(res.message || 'Save failed.');
      }
    } catch (e) {
      showAlert('Save error: ' + e.message);
    } finally {
      isSaving = false;
      $('btnSave').textContent = 'Save';
    }
  }

  async function loadBill(billNo) {
    try {
      const res = await api(siteUrl + '/api/sales-bill/get?bill_no=' + encodeURIComponent(billNo), 'GET');
      if (!res.ok) { showAlert(res.message || 'Bill not found.'); return; }
      const d = res.data;

      currentBillNo = d.bill_no;
      currentLegacySlno = Number(d.slno || 0);
      setVal('fBillNo', d.bill_no);
      setVal('fBillDate', d.bill_date || '');
      $('fBillTime').textContent = d.bill_time || '';
      setBillTypeByAny(d.bill_type || 'G');
      setVal('fCustomerName', d.customer_name || '');
      setVal('fCustomerCode', d.customer_code || '');
      setVal('fCustomerCodeInput', d.customer_code || '');
      setVal('fAddress', d.address || '');
      setVal('fMobNo', d.mobile || '');
      setVal('fGstNo', d.gst_no || '');
      setVal('fPanNo', d.pan_no || '');
      setVal('fRatePerGm', fmt2(d.rate_per_gm));
      setVal('fCounter', d.counter_code || '');
      setSalesmanByAny(d.salesman_code || '', d.salesman_name || '');
      setVal('fAgent', d.agent_code || '');
      setVal('fApprovedBy', d.approved_by || '');
      setVal('fCashBank', d.cashbank_code || '');
      setVal('fState', d.state_code || '');
      setVal('fDueDate', d.due_date || '');
      setVal('fVehNo', d.vehicle_no || '');
      setVal('fSupplyPlace', d.supply_place || '');
      setVal('fDistance', d.distance || '');
      setVal('fQuotationMode', !!d.is_quotation);
      selectedQuotationBillNo = String(d.source_quotation_bill_no || '').trim();
      if ($('lblBillNo') && d.is_quotation) $('lblBillNo').textContent = 'Qtn No';
      setVal('fCo', '');
      if (d.co_party_code) setCoPartyOption(d.co_party_code, '');

      items    = (d.items || []).map(r => ({ ...newItemRow(), ...r, hsn: r.hsn || r.note || '', _rowId: nextRowId() }));
      exchRows = (d.exchange || []).map(r => ({ ...newExchRow(), ...r, _rowId: nextRowId() }));
      srRows   = (d.sales_return || []).map(r => ({ ...newSrRow(), ...r, _rowId: nextRowId() }));
      if (srRows.length) {
        const srTotal = srRows.reduce((s, r) => s + toNum(r.amount || 0), 0);
        const extraRet = Number(d.extra?.tax_perc ?? 0);
        setVal('srTaxPerc', String(extraRet || 0));
        setVal('srTaxAmt', fmt2(roundByMode(srTotal * (extraRet || 0) / 100)));
      } else {
        setVal('srTaxAmt', '0.00');
        setVal('srCessAmt', '0.00');
      }

      const ex = d.extra || {};
      const loadedTaxPerc = normalizeTaxPerc(ex.tax_perc, d.bill_type || val('fBillType'));
      setVal('fDiscount', toNum(ex.discount) > 0 ? fmt2(ex.discount) : '');
      setVal('fDiscountPerc', ex.discount_perc ?? 0);
      setVal('fTaxAmt', fmt2(ex.tax));
      setVal('fTaxPerc', String(Number(loadedTaxPerc.toFixed(3))));
      setVal('fCessAmt', fmt2(ex.ast));
      setVal('fCessPerc', ex.ast_perc ?? 0);
      setVal('fTcsPerc', ex.tcs_perc ?? 0);
      setVal('fRepair', fmt2(ex.repair_charge));
      setVal('fHmc', fmt2(ex.hallmark_charge));
      setVal('fPrevAdv', fmt2(ex.advance));
      setVal('fFancyAmt', fmt2(ex.fancy_amt));
      setVal('fSchemeAmt', fmt2(ex.scheme_amt));
      setVal('fSchemeLedger', ex.scheme_ledger || 'APP');
      setVal('fBcPerc', ex.bc_perc ?? 0);
      setVal('fBcAmt', fmt2(ex.bank_charge));
      setVal('fCcardAmt', fmt2(ex.cc_amt));
      setVal('fChqAmt', fmt2(ex.chq_amt));
      setVal('fChqBank', ex.chq_bank || '');
      setVal('fChqNo', ex.chq_no || '');
      setVal('fChqDate', ex.chq_date || '');
      setVal('fPdc', !!ex.chq_pdc);
      setVal('fCcardPdc', !!ex.cc_pdc);
      setVal('fRedeemPoints', (Number(ex.redeem_points || 0) > 0));
      setVal('fPurchTaxPerc', ex.ptax_perc ?? 0);
      setVal('fPurchTaxAmt', fmt2(ex.ptax_amt));
      setVal('fAddBc', ex.add_bank_charge);
      setVal('fOB', fmt2(ex.opening_balance));
      setVal('fTotalRcvd', fmt2(ex.received));
      totalRcvdDirty = false;
      discountDirty = false;
      setVal('fCredit', ex.credit);
      setVal('fNote', ex.note || '');

      renderItemsTable();
      applyRecalcResult(d);
      showAlert('Bill loaded: ' + billNo, true);
      if (d.customer_code) {
        const keepOB = fmt2(ex.opening_balance);
        const keepDiscount = toNum(ex.discount) > 0 ? fmt2(ex.discount) : '';
        const keepDiscountPerc = ex.discount_perc ?? 0;
        const keepReceived = fmt2(ex.received);
        await fetchCustomerByCode(d.customer_code, false);
        setVal('fOB', keepOB);
        setVal('fDiscount', keepDiscount);
        setVal('fDiscountPerc', keepDiscountPerc);
        setVal('fTotalRcvd', keepReceived);
        totalRcvdDirty = false;
        discountDirty = false;
        triggerRecalc();
      }
    } catch (e) {
      showAlert('Load error: ' + e.message);
    }
  }

  // Navigation (PB-style prev/next by slno/control/orderno)
  async function resetToAddMode(prefillBillNo = '', prefillTaxPerc = null, savedBillNo = '') {
    currentBillNo = '';
    currentLegacySlno = 0;
    hideCustSearch();
    setVal('fBillNo', '');
    setVal('fCustomerName', '');
    setVal('fCustomerCode', '');
    setVal('fCustomerCodeInput', '');
    setVal('fAddress', '');
    setVal('fMobNo', '');
    setVal('fGstNo', '');
    setVal('fPanNo', '');
    setVal('fDueDate', '');
    setVal('fVehNo', '');
    setVal('fSupplyPlace', '');
    setVal('fDistance', '');
    setVal('fCo', '');
    setVal('fState', DEF_STATE_CODE || '');
    setVal('fCashBank', '');
    setVal('fSmName', '');
    setVal('fAgent', '');
    setVal('fApprovedBy', '');
    setVal('fOB', '0.00');
    setVal('fPrevAdv', '0.00');
    setVal('fTotalRcvd', '0.00');
    totalRcvdDirty = false;
    discountDirty = false;
    setVal('fDiscount', '');
    setVal('fDiscountPerc', '0');
    setVal('fTaxAmt', '0.00');
    setVal('fTaxPerc', String(normalizeTaxPerc(prefillTaxPerc)));
    setVal('fCessAmt', '0.00');
    setVal('fCessPerc', '0');
    setVal('fPurchTaxPerc', '0');
    setVal('fPurchTaxAmt', '0.00');
    setVal('fRepair', '0.00');
    setVal('fFancyAmt', '0.00');
    setVal('fSchemeAmt', '0.00');
    setVal('fSchemeLedger', 'APP');
    setVal('fHmc', '0.00');
    setVal('fTcsPerc', '0');
    setVal('fTcsAmt', '0.00');
    setVal('fBcPerc', '0.00');
    setVal('fBcAmt', '0.00');
    setVal('fCcardAmt', '0.00');
    setVal('fChqAmt', '0.00');
    setVal('fChqBank', '');
    setVal('fChqNo', '');
    setVal('fChqDate', '');
    setVal('fPdc', false);
    setVal('fCcardPdc', false);
    setVal('fQuotationMode', false);
    selectedQuotationBillNo = '';
    if ($('lblBillNo')) $('lblBillNo').textContent = FORCE_QTN_MODE ? 'Qtn No' : 'Bill No';
    setVal('fRedeemPoints', false);
    setVal('fBillDate', todayDmY());
    $('fBillTime').textContent = nowTime();
    setVal('fBillTotal', '0.00');
    setVal('fNetTotal', '0.00');
    setVal('fGrandAmt', '0.00');
    setVal('fNetAmt', '0.00');
    setVal('fBalance', '0.00');
    setVal('fExchangeAmt', '0.00');
    setVal('fSalesRetAmt', '0.00');

    items = [];
    exchRows = [];
    srRows = [];
    renderItemsTable();
    renderExchTable();
    renderSrTable();
    updateTableFooter();
    addItemRow();
    applyHeaderRate();
    setVal('fTaxPerc', String(normalizeTaxPerc(prefillTaxPerc)));
    triggerRecalc();
    syncSalesmanTitle();

    // Ensure fresh bill number is ready before user starts next entry.
    if (mode === 'bill') {
      if (!chk('fManualBillNo')) {
        const seededBillNo = String(prefillBillNo || deriveNextBillNo(savedBillNo) || '').trim();
        if (seededBillNo) {
          setVal('fBillNo', seededBillNo);
          currentBillNo = seededBillNo;
        } else {
          await refreshNextBillNo();
        }
      } else {
        $('fBillNo')?.focus();
      }
    }

    if (mode === 'bill' && !chk('fManualBillNo') && !String(val('fBillNo') || '').trim()) {
      try {
        await refreshNextBillNo();
      } catch (e) {
        // leave final fallback below
      }
      if (!String(val('fBillNo') || '').trim()) {
        const fallbackBillNo = String(prefillBillNo || deriveNextBillNo(savedBillNo) || currentBillNo || '').trim();
        if (fallbackBillNo) {
          setVal('fBillNo', fallbackBillNo);
          currentBillNo = fallbackBillNo;
        }
      }
    }

    if (FOCUS_ON_PARTY_CODE) {
      $('fCustomerCodeInput')?.focus();
    } else {
      $('fSmName')?.focus();
    }
  }

  $('btnPrev').addEventListener('click', async (e) => {
    e.preventDefault();
    if (!ALLOW_PREV_NEXT_BUTTONS) {
      showAlert('You do not have permission to use Previous / Next navigation.');
      return;
    }
    const billNo = String(val('fBillNo') || currentBillNo || '').trim();
    try {
      const res = await api(siteUrl + '/api/sales-bill/prev?bill_no=' + encodeURIComponent(billNo), 'GET');
      if (!res.ok) {
        showAlert(res.message || 'Previous bill not found.');
        return;
      }
      if (res.bill_no) {
        await loadBill(res.bill_no);
      }
    } catch (e) {
      showAlert('Previous navigation error: ' + e.message);
    }
  });
  $('btnNext').addEventListener('click', async (e) => {
    e.preventDefault();
    if (!ALLOW_PREV_NEXT_BUTTONS) {
      showAlert('You do not have permission to use Previous / Next navigation.');
      return;
    }
    const billNo = String(val('fBillNo') || currentBillNo || '').trim();
    try {
      const res = await api(siteUrl + '/api/sales-bill/next?bill_no=' + encodeURIComponent(billNo), 'GET');
      if (!res.ok) {
        showAlert(res.message || 'Next bill not found.');
        return;
      }
      if (res.mode === 'add' || !res.bill_no) {
        resetToAddMode();
        return;
      }
      await loadBill(res.bill_no);
    } catch (e) {
      showAlert('Next navigation error: ' + e.message);
    }
  });

  // ══════════════════════════════════════════════════════════════════
  // 11. ACTION BUTTON EVENTS
  // ══════════════════════════════════════════════════════════════════
  $('btnAddItem').addEventListener('click', addItemRow);
  $('btnDeleteItem').addEventListener('click', deleteItemRow);
  $('btnExchange').addEventListener('click', openExchange);
  $('btnSalesReturn').addEventListener('click', openSalesReturn);
  $('btnSave').addEventListener('click', async () => {
    if (!(await validateManualBillNo())) return;
    saveBill();
  });
  $('fManualBillNo').addEventListener('change', () => applyBillNoMode());
  $('fBillNo').addEventListener('input', () => {
    if (!chk('fManualBillNo')) return;
    const bill = $('fBillNo');
    if (!bill) return;
    const normalized = String(bill.value || '').trim().toUpperCase();
    if (bill.value !== normalized) {
      bill.value = normalized;
    }
  });
  $('fBillNo').addEventListener('blur', () => { validateManualBillNo(); });
  $('btnExit').addEventListener('click', () => {
    // In bill mode, Exit should refresh/clear current bill form.
    if (mode === 'bill') {
      resetToAddMode();
      return;
    }
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
      return;
    }
    history.back();
  });
  $('btnShortPrint')?.addEventListener('click', () => openBillPrintPreview('short'));
  $('btnQtnPrint').addEventListener('click', () => openBillPrintPreview('qtn'));
  $('btnQuotationSelect')?.addEventListener('click', openQuotationSelect);
  $('btnQuotationClose')?.addEventListener('click', closeQuotationSelect);
  $('quotationSelectModal')?.addEventListener('click', (e) => {
    if (e.target && e.target.id === 'quotationSelectModal') closeQuotationSelect();
  });
  $('quotationSearchInput')?.addEventListener('input', (e) => {
    clearTimeout(window.__qtnSearchTimer);
    const term = String(e.target?.value || '').trim();
    window.__qtnSearchTimer = setTimeout(() => fetchQuotationList(term), 180);
  });
  $('quotationListBody')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-qtn-index]');
    if (!btn) return;
    const idx = Number(btn.getAttribute('data-qtn-index'));
    const row = quotationRowsCache[idx];
    if (row?.bill_no) {
      applyQuotationToSale(row.bill_no);
    }
  });
  $('fQuotationMode')?.addEventListener('change', () => {
    if ($('lblBillNo')) $('lblBillNo').textContent = inQuotationMode() ? 'Qtn No' : 'Bill No';
    if (mode === 'bill' && !chk('fManualBillNo')) {
      refreshNextBillNo();
    }
  });

  const MAIN_TAB_ORDER = [
    'fManualBillNo', 'fRatePerGm', 'fRatePurity', 'fCounter',
    'fCustomerCodeInput', 'fCustomerName', 'btnCustSearch', 'btnCustCreate', 'fMobNo', 'fAddress',
    'btnPrev', 'btnNext', 'fBillType', 'fCo', 'fGstNo', 'fPanNo',
    'fBillDate', 'fOpWgt', 'fRatePer8gm', 'fOB', 'fSmName', 'fWs', 'fPrevAdv', 'btnRateAll',
    'fBillTotal', 'fTaxPerc', 'fTaxAmt', 'fDiscount', 'fDiscountPerc', 'fGrandAmt', 'fPurchTaxPerc', 'fPurchTaxAmt',
    'fCashBank', 'fChqBank', 'fAgent', 'fApprovedBy',
    'fRepair', 'fCessPerc', 'fCessAmt', 'fFancyAmt', 'fSchemeAmt', 'fSchemeLedger', 'fTcsPerc', 'fTcsAmt', 'fInterstate',
    'fExchangeAmt', 'fHmc', 'fTotalRcvd', 'fNetAmt', 'fCb', 'fNote', 'fCcardAmt', 'fBcPerc', 'fBcAmt', 'fChqAmt', 'fChqDate', 'fChqNo', 'fPdc', 'fVehNo', 'fSchemeBal',
    'fRedeemPoints', 'fSalesRetAmt', 'fNetTotal', 'fCredit', 'fBalance', 'fDueDate', 'fPcPoints', 'fPcAmt', 'fSupplyPlace', 'fState', 'fDistance', 'fSendSms',
    'btnExchange', 'btnSalesReturn', 'btnQuotationSelect', 'fQuotationMode', 'btnSave', 'btnShortPrint', 'btnExit', 'btnQtnPrint'
  ];

  function isElementTabbable(el) {
    if (!el) return false;
    if (el.disabled) return false;
    if (el.type === 'hidden') return false;
    if (el.closest('[style*="display:none"]')) return false;
    const style = window.getComputedStyle(el);
    if (style.display === 'none' || style.visibility === 'hidden') return false;
    return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  }

  function getMainTabElements() {
    return MAIN_TAB_ORDER
      .map(id => $(id))
      .filter(isElementTabbable);
  }

  function isInsideManagedTable(el) {
    return !!(el && el.closest('#itemsTbody, #exchTbody, #srTbody, #custSearchResults'));
  }

  function isModalOpen() {
    return !!(
      $('exchangeModal')?.classList.contains('active') ||
      $('salesReturnModal')?.classList.contains('active') ||
      $('msgModal')?.classList.contains('active') ||
      $('confirmModal')?.classList.contains('active') ||
      $('passwordModal')?.classList.contains('active')
    );
  }

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Tab') return;
    if (isModalOpen()) return;
    const active = document.activeElement;
    if (!active || isInsideManagedTable(active)) return;
    const fields = getMainTabElements();
    const idx = fields.indexOf(active);
    if (idx === -1) return;
    e.preventDefault();
    const nextIdx = e.shiftKey ? (idx - 1 + fields.length) % fields.length : (idx + 1) % fields.length;
    const target = fields[nextIdx];
    if (target) {
      target.focus();
      if (typeof target.select === 'function' && ['text', 'search', 'tel', 'url', 'password', 'number'].includes(target.type || 'text')) {
        target.select();
      }
    }
  });

  // Rate All button
  $('btnRateAll').addEventListener('click', () => {
    const rate = numVal('fRatePerGm');
    if (!rate) return;
    items.forEach((r, idx) => { r.rate = rate; recalcItemAmount(idx); });
    renderItemsTable();
    triggerRecalc();
  });
  $('fSmName')?.addEventListener('change', syncSalesmanTitle);

  // Keyboard shortcuts
  document.addEventListener('keydown', (e) => {
    if ($('msgModal')?.classList.contains('active')) {
      if (e.key === 'Escape' || e.key === 'Enter') {
        e.preventDefault();
        closeWarningModal();
      }
      return;
    }
    if ($('confirmModal')?.classList.contains('active')) {
      if (e.key === 'Escape') {
        e.preventDefault();
        closeConfirmModal(false);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        closeConfirmModal(true);
      }
      return;
    }
    if ($('passwordModal')?.classList.contains('active')) {
      if (e.key === 'Escape') {
        e.preventDefault();
        closePasswordModal(null);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        closePasswordModal($('passwordModalInput')?.value ?? '');
      }
      return;
    }
    if (e.key === 'F5') { e.preventDefault(); $('btnSave')?.click(); }
    if (e.key === 'Escape') {
      if ($('exchangeModal').classList.contains('active')) closeExchange();
      else if ($('salesReturnModal').classList.contains('active')) closeSalesReturn();
    }
  });

  $('msgModalOk')?.addEventListener('click', closeWarningModal);
  $('msgModal')?.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'msgModal') closeWarningModal();
  });
  $('confirmModalYes')?.addEventListener('click', () => closeConfirmModal(true));
  $('confirmModalNo')?.addEventListener('click', () => closeConfirmModal(false));
  $('confirmModal')?.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'confirmModal') closeConfirmModal(false);
  });
  $('passwordModalOk')?.addEventListener('click', () => closePasswordModal($('passwordModalInput')?.value ?? ''));
  $('passwordModalCancel')?.addEventListener('click', () => closePasswordModal(null));
  $('passwordModal')?.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'passwordModal') closePasswordModal(null);
  });

  // ══════════════════════════════════════════════════════════════════
  // 12. INITIALISATION
  // ══════════════════════════════════════════════════════════════════
  async function init() {
    if (window.goldappDateUi) {
      window.goldappDateUi.attachCalendarInputs(['fBillDate', 'fChqDate', 'fDueDate']);
    }

    // Populate dropdown selects from server data
    initDropdowns();
    await initCoPartyDropdown();

    if (DEF_COUNTER) setVal('fCounter', DEF_COUNTER);
    if (DEF_STATE_CODE) setVal('fState', DEF_STATE_CODE);
    if ($('lblPan')) $('lblPan').textContent = PAN_LABEL || 'PAN/Adh';
    if ($('lblState')) $('lblState').textContent = STATE_LABEL || 'State';
    if ($('lblTaxNo')) $('lblTaxNo').textContent = TAXNO_LABEL || 'GST No';
    if ($('fLaserPrint')) $('fLaserPrint').checked = PRINTER_IS_LASER;
    if (!SHOW_ADVANCE_IN_ESTIMATE) {
      const rowPrevAdv = $('rowPrevAdv');
      if (rowPrevAdv) rowPrevAdv.style.display = 'none';
    }
    if (FORCE_QTN_MODE) {
      setVal('fQuotationMode', true);
      if ($('lblBillNo')) $('lblBillNo').textContent = 'Qtn No';
      const navBtns = document.querySelector('.nav-btns');
      if (navBtns) navBtns.style.display = 'none';
      if ($('btnShortPrint')) $('btnShortPrint').style.display = 'none';
      if ($('btnQtnPrint')) $('btnQtnPrint').style.display = 'none';
      if ($('btnQuotationSelect')) $('btnQuotationSelect').style.display = 'none';
    }
    if (!ALLOW_PREV_NEXT_BUTTONS) {
      if ($('btnPrev')) $('btnPrev').disabled = true;
      if ($('btnNext')) $('btnNext').disabled = true;
    }

    // PB-like default bill type from settings
    if (DEF_BILL_TYPE_RAW) {
      setBillTypeByAny(DEF_BILL_TYPE_RAW);
    }

    // PB-like defaults for entry flags
    setVal('fRatePurity', String(BULLION_TOUCH));
    setVal('fWs', DEF_RATE_MODE === 'WSR');
    setVal('fCredit', SALES_DEF_CREDIT);
    setVal('fManualBillNo', ALLOW_SALES_BILL_MANUAL && MANUAL_BNO_DEFAULT);
    $('fManualBillNo').disabled = !ALLOW_SALES_BILL_MANUAL;
    if (!ALLOW_SALES_BILL_MANUAL) setVal('fManualBillNo', false);

    // Set date and time
    setVal('fBillDate', todayDmY());
    $('fBillTime').textContent = nowTime();

    // Set rates
    applyHeaderRate();
    applyBillNoMode();

    // Mode-aware setup
    const qs = new URLSearchParams(window.location.search);
    const urlBillNo = qs.get('bill_no');
    const autoPrint = qs.get('autoprint') === '1';

    if (mode === 'bill') {
      // New bill mode - generate next bill number
      if (!chk('fManualBillNo')) {
        try {
          await refreshNextBillNo();
          if (!val('fBillNo')) {
            throw new Error('bill no unavailable');
          }
        } catch (e) {
          setVal('fBillNo', 'J/' + String(new Date().getMonth()+1).padStart(2,'0') +
                 String(new Date().getDate()).padStart(2,'0') + '/001');
        }
      }
      addItemRow();
    } else if (urlBillNo) {
      // edit / cancel / reprint / confirmation - load existing bill
      await loadBill(urlBillNo);
      if (autoPrint) {
        setTimeout(() => { openBillPrintPreview('full'); }, 200);
      }
      if (mode === 'cancel') {
        $('btnSave').textContent = 'Cancel Bill';
        $('btnSave').style.background = '#fee2e2';
        $('btnSave').style.color = '#c53030';
        $('btnSave').removeEventListener('click', saveBill);
        $('btnSave').addEventListener('click', async () => {
          if (!currentBillNo) { showAlert('No bill loaded.'); return; }
          // Show cancel reason modal
          $('cancelReasonInput').value = '';
          $('cancelReasonModal').classList.add('active');
          $('cancelReasonInput').focus();
        });
        $('cancelReasonNo').addEventListener('click', () => {
          $('cancelReasonModal').classList.remove('active');
        });
        $('cancelReasonYes').addEventListener('click', async () => {
          const reason = $('cancelReasonInput').value.trim();
          $('cancelReasonModal').classList.remove('active');
          const res = await api(siteUrl + '/api/sales-bill/cancel', 'POST', { bill_no: currentBillNo, reason });
          if (!res.ok) { showAlert('Error: ' + (res.message || 'Cancel failed')); return; }
          showAlert('Bill ' + currentBillNo + ' cancelled successfully.', true, () => {
            if (window.parent && window.parent !== window) {
              window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
            } else {
              history.back();
            }
          });
        });
      } else if (mode === 'confirmation') {
        $('btnSave').textContent = 'Confirm Bill';
        $('btnSave').removeEventListener('click', saveBill);
        $('btnSave').addEventListener('click', async () => {
          const res = await api(siteUrl + '/api/sales-bill/confirm', 'POST', { bill_no: currentBillNo });
          showAlert(res.message || (res.ok ? 'Confirmed' : 'Error'), res.ok);
        });
      }
    } else {
      // No bill_no provided in non-bill mode - start fresh
      addItemRow();
    }

    // PB-like opening focus
    if (FOCUS_ON_PARTY_CODE) {
      $('fCustomerName')?.focus();
    } else {
      $('fSmName')?.focus();
    }
  }

  init();
})();
</script>

</body>
</html>
