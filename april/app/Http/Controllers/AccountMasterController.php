<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AccountMasterController extends Controller
{
    private function requireLogin(Request $request): void
    {
        if (!$request->session()->has('user_code')) {
            abort(redirect('/login'));
        }
    }

    private function json(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    private function isBlocked(Request $request, string $action): bool
    {
        $userCode = (string) $request->session()->get('user_code', '');
        if ($userCode === 'admin') return false;
        $blocked = $request->session()->get('blocked_items', []);
        return in_array($action, $blocked, true);
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . ':' . strtolower($column);
        if (!isset($cache[$key])) {
            $cache[$key] = Schema::hasColumn($table, $column);
        }
        return $cache[$key];
    }

    private function trimUpper($v): string
    {
        return strtoupper(trim((string) $v));
    }

    private function normalizeAmount($raw, string $type): float
    {
        $amount = abs((float) $raw);
        return ($type === 'debit') ? -$amount : $amount;
    }

    // ── Page methods ──

    public function account(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('account.master-account');
    }

    public function groups(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('account.master-groups');
    }

    public function bsHeads(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('account.master-bs-heads');
    }

    public function positionSettings(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('account.position-settings');
    }

    public function bookStock(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);

        $message = '';
        $messageType = '';
        $stockValue = '';
        $cashValue = '';

        if ($request->isMethod('post') && $request->has('submit')) {
            $stockValue = trim((string) $request->input('stock', ''));
            $cashValue = trim((string) $request->input('cash', ''));

            try {
                $this->upsertGeneralValue('BOOKSTK', $stockValue, 'Book Stock Value');
                $this->upsertGeneralValue('BOOKCASH', $cashValue, 'Book Cash Value');
                $message = 'Updated...';
                $messageType = 'success';
            } catch (\Throwable $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }

        try {
            $stockValue = $this->getOrCreateGeneralValue('BOOKSTK', $stockValue, 'Book Stock Value');
            $cashValue = $this->getOrCreateGeneralValue('BOOKCASH', $cashValue, 'Book Cash Value');
        } catch (\Throwable $e) {
            if ($message === '') {
                $message = 'Error loading data: ' . $e->getMessage();
                $messageType = 'error';
            }
        }

        return view('account.book-stock', compact('stockValue', 'cashValue', 'message', 'messageType'));
    }

    public function opStockValueSet(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);

        $gilevel = (int) ($request->session()->get('gilevel', 1));
        if ($gilevel <= 0) $gilevel = 1;
        $gdtstartdate = trim((string) ($request->session()->get('gdtstartdate', '')));
        if ($gdtstartdate === '') $gdtstartdate = date('Y-m-d');

        $dval = 0.0;
        $dtdateDb = $gdtstartdate;
        $message = '';
        $messageType = '';

        if ($request->isMethod('post') && $request->input('action') === 'save') {
            $dval = (float) $request->input('stock_value', 0);
            $dtdateDb = $this->normalizeUiDate($request->input('date', '')) ?: $gdtstartdate;

            try {
                DB::beginTransaction();

                DB::table('stkandprofit')->where('slno', 0)->delete();
                DB::table('stkandprofit')->insert([
                    'slno' => 0,
                    'tdate' => $dtdateDb,
                    'stkvalue' => $dval,
                    'profit' => 0,
                    'note' => 'OP.VALUE',
                    'control' => $gilevel,
                ]);

                $negVal = -$dval;
                if ($gilevel === 1 && $this->hasColumn('accountm', 'opbal')) {
                    DB::table('accountm')->whereRaw("TRIM(accode) = 'OPSTOCK'")->update(['opbal' => $negVal]);
                } elseif ($this->hasColumn('accountm', 'opbalb')) {
                    DB::table('accountm')->whereRaw("TRIM(accode) = 'OPSTOCK'")->update(['opbalb' => $negVal]);
                }

                DB::commit();
                $message = 'Updated...';
                $messageType = 'success';
            } catch (\Throwable $e) {
                DB::rollBack();
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }

        try {
            $row = DB::table('stkandprofit')->where('slno', 0)->first();
            if ($row) {
                $dval = (float) ($row->stkvalue ?? 0);
                $dtdateDb = trim((string) ($row->tdate ?? $gdtstartdate));
                $year = (int) date('Y', strtotime($dtdateDb ?: $gdtstartdate));
                if ($year <= 1990) {
                    $dtdateDb = $gdtstartdate;
                    $ob = DB::table('accountm')->selectRaw('opbal, opbalb')->whereRaw("TRIM(accode) = 'OPSTOCK'")->first();
                    if ($ob) $dval = ($gilevel === 1) ? -(float) ($ob->opbal ?? 0) : -(float) ($ob->opbalb ?? 0);
                }
            } else {
                $dtdateDb = $gdtstartdate;
                $ob = DB::table('accountm')->selectRaw('opbal, opbalb')->whereRaw("TRIM(accode) = 'OPSTOCK'")->first();
                if ($ob) $dval = ($gilevel === 1) ? -(float) ($ob->opbal ?? 0) : -(float) ($ob->opbalb ?? 0);
            }
        } catch (\Throwable $e) {
            if ($message === '') {
                $message = 'Error loading data: ' . $e->getMessage();
                $messageType = 'error';
            }
        }

        $dtdate = $this->formatUiDate($dtdateDb);
        return view('account.op-stock-value-set', compact('dval', 'dtdate', 'message', 'messageType'));
    }

    public function coPartyLimit(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('account.co-party-limit');
    }

    public function salesMan(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('account.sales-man');
    }

    // ── API methods ──

    public function apiAccount(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action = strtolower(trim((string) $request->input('action', '')));

        switch ($action) {
            case 'list':
                try {
                    $type = strtolower(trim((string) $request->input('type', '')));
                    if ($type === 'groups') {
                        $search = trim((string) $request->input('search', ''));
                        $query = DB::table('accountg')->selectRaw('TRIM(grcode) AS grcode, TRIM(name) AS name');
                        if ($this->hasColumn('accountg', 'actype1')) $query->addSelect('actype1 AS actype');
                        if ($search !== '') {
                            $like = '%' . $search . '%';
                            $query->where(function ($q) use ($like) {
                                $q->whereRaw('TRIM(grcode) LIKE ?', [$like])
                                  ->orWhereRaw('TRIM(name) LIKE ?', [$like]);
                            });
                        }
                        return $this->json(['success' => true, 'data' => $query->orderBy('name')->get()->all()]);
                    }
                    if ($type === 'bs_heads') {
                        $rows = DB::table('accountgbs')->selectRaw('TRIM(hcode) AS hcode, TRIM(hname) AS hname')->orderBy('hname')->get()->all();
                        return $this->json(['success' => true, 'data' => $rows]);
                    }
                    $query = DB::table('accountm')->selectRaw('TRIM(accode) AS accode, TRIM(name) AS name');
                    if ($this->hasColumn('accountm', 'removed')) {
                        $query->whereRaw('(removed <> 1 OR removed IS NULL)');
                    }
                    return $this->json(['success' => true, 'data' => $query->orderBy('name')->get()->all()]);
                } catch (\Throwable $e) {
                    return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
                }

            case 'list_all':
                try {
                    $search = trim((string) $request->input('search', ''));
                    $limit = (int) $request->input('limit', $search === '' ? 150 : 300);
                    if ($limit <= 0) $limit = 150;
                    if ($limit > 500) $limit = 500;
                    $selects = ['TRIM(accountm.accode) AS accode', 'TRIM(accountm.name) AS name'];
                    if ($this->hasColumn('accountm', 'grcode')) $selects[] = 'TRIM(accountm.grcode) AS grcode';
                    if ($this->hasColumn('accountm', 'actype1')) $selects[] = 'accountm.actype1';
                    if ($this->hasColumn('accountm', 'actype2')) $selects[] = 'accountm.actype2';
                    $query = DB::table('accountm')->selectRaw(implode(', ', $selects));
                    if ($this->hasColumn('accountm', 'removed')) {
                        $query->whereRaw('(accountm.removed <> 1 OR accountm.removed IS NULL)');
                    }
                    if ($this->hasColumn('accountm', 'actype2')) {
                        $query->whereNotIn('accountm.actype2', ['C', 'S', 'G', 'R', 'J']);
                    }
                    if ($search !== '') {
                        $like = '%' . $search . '%';
                        $query->where(function ($q) use ($like) {
                            $q->whereRaw('TRIM(accountm.accode) LIKE ?', [$like])
                              ->orWhereRaw('TRIM(accountm.name) LIKE ?', [$like]);
                        });
                    }
                    $rows = $query->orderBy('accountm.accode')->limit($limit)->get()->all();
                    return $this->json(['success' => true, 'data' => $rows]);
                } catch (\Throwable $e) {
                    return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
                }

            case 'list_codes':
                try {
                    $codes = DB::table('accountm')
                        ->selectRaw('TRIM(accode) AS accode')
                        ->whereNotNull('accode')
                        ->whereRaw("TRIM(accode) <> ''")
                        ->orderBy('accode')
                        ->pluck('accode')
                        ->all();
                    return $this->json(['success' => true, 'data' => $codes]);
                } catch (\Throwable $e) {
                    return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
                }

            case 'validate':
                return $this->json($this->validateAccount($request->input('accode', ''), $request->input('mode', 'A')));

            case 'load':
                $row = $this->loadAccountRow($request->input('accode', ''));
                if (!$row) return $this->json(['success' => false, 'error' => 'Account not found'], 404);
                $accode = $this->trimUpper($request->input('accode', ''));
                $payload = [
                    'accode' => $accode,
                    'desc' => (string) ($row->name ?? ''),
                    'grcode' => (string) ($row->grcode ?? ''),
                    'bshead' => (string) ($row->bshead ?? ''),
                    'balance' => abs((float) ($row->opbal ?? 0)),
                    'pos' => (string) ($row->tplpos ?? ''),
                    'shedpos' => (string) ($row->shepos ?? ''),
                    'shedgrp' => (string) ($row->shedgrp ?? $accode),
                    'note' => (string) ($row->note ?? ''),
                    'debit_checked' => ((float) ($row->opbal ?? 0) <= 0),
                    'cash_checked' => strtoupper((string) ($row->actype2 ?? '')) === 'H',
                    'bank_checked' => strtoupper((string) ($row->actype2 ?? '')) === 'B',
                    'revenue_checked' => strtoupper((string) ($row->actype1 ?? '')) === 'R',
                    'expense_checked' => strtoupper((string) ($row->actype1 ?? '')) === 'E',
                    'asset_checked' => strtoupper((string) ($row->actype1 ?? 'A')) === 'A',
                    'liability_checked' => strtoupper((string) ($row->actype1 ?? '')) === 'L',
                    'display_checked' => (int) ($row->control ?? 1) !== 2,
                    'hlp_checked' => (int) ($row->hlp ?? 1) === 0,
                    'sp_checked' => (int) ($row->sp ?? 0) === 1,
                    'reserve_checked' => strtoupper((string) ($row->reserve ?? 'N')) === 'Y',
                    'removed_checked' => (int) ($row->removed ?? 0) === 1,
                    'blocked_checked' => strtoupper((string) ($row->blocked ?? 'N')) === 'Y',
                ];
                return $this->json(['success' => true, 'data' => $payload]);

            case 'save':
                return $this->json($this->saveAccountInternal($request));

            case 'delete':
                return $this->json($this->deleteAccountInternal($request->input('accode', '')));

            default:
                return $this->json(['success' => false, 'error' => 'Invalid action'], 400);
        }
    }

    public function apiGroups(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action = strtolower(trim((string) $request->input('action', 'list')));

        switch ($action) {
            case 'list':
                $rows = DB::table('accountg')->selectRaw('TRIM(grcode) AS grcode, TRIM(name) AS name')->orderBy('name')->get()->all();
                return $this->json(['success' => true, 'data' => $rows]);

            case 'validate':
                return $this->json($this->validateGroup($request->input('grcode', ''), $request->input('mode', 'A')));

            case 'load':
                $grcode = $this->trimUpper($request->input('grcode', ''));
                $row = DB::table('accountg')->whereRaw('TRIM(grcode) = ?', [$grcode])->first();
                if (!$row) return $this->json(['success' => false, 'error' => 'Group not found'], 404);
                return $this->json(['success' => true, 'data' => [
                    'grcode' => $grcode,
                    'name' => (string) ($row->name ?? ''),
                    'pos' => (string) ($row->pos ?? ''),
                    'grp' => (string) ($row->grp ?? ''),
                    'mgrp' => (string) ($row->mgrp ?? 'MGRP'),
                    'revenue_checked' => strtoupper((string) ($row->actype1 ?? '')) === 'R',
                    'expense_checked' => strtoupper((string) ($row->actype1 ?? '')) === 'E',
                    'asset_checked' => strtoupper((string) ($row->actype1 ?? 'A')) === 'A',
                    'liability_checked' => strtoupper((string) ($row->actype1 ?? '')) === 'L',
                    'reserve_checked' => strtoupper((string) ($row->reserve ?? 'N')) === 'Y',
                ]]);

            case 'save':
                $mode = strtoupper(trim((string) $request->input('mode', 'A')));
                $grcode = $this->trimUpper($request->input('grcode', ''));
                $name = trim((string) $request->input('name', ''));
                $actype1 = $this->trimUpper($request->input('actype1', 'A'));
                if ($grcode === '') return $this->json(['success' => false, 'error' => 'Group code is required']);
                if ($name === '') return $this->json(['success' => false, 'error' => 'Group name is required']);
                $v = $this->validateGroup($grcode, $mode);
                if (!empty($v['error'])) return $this->json(['success' => false, 'error' => $v['error']]);
                try {
                    $data = [
                        'grcode' => $grcode, 'name' => $name, 'actype1' => $actype1,
                        'reserve' => $request->input('reserve') ? 'Y' : 'N',
                        'pos' => trim((string) $request->input('pos', '')),
                        'grp' => trim((string) $request->input('grp', '')),
                        'mgrp' => $this->trimUpper($request->input('mgrp', 'MGRP')),
                    ];
                    if ($mode === 'A') {
                        DB::table('accountg')->insert($data);
                    } else {
                        DB::table('accountg')->whereRaw('TRIM(grcode) = ?', [$grcode])->update($data);
                    }
                    return $this->json(['success' => true, 'message' => 'Group saved successfully']);
                } catch (\Throwable $e) {
                    return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
                }

            case 'delete':
                $grcode = $this->trimUpper($request->input('grcode', ''));
                $v = $this->validateGroup($grcode, 'D');
                if (!empty($v['error'])) return $this->json(['success' => false, 'error' => $v['error']]);
                try {
                    DB::table('accountg')->whereRaw('TRIM(grcode) = ?', [$grcode])->delete();
                    return $this->json(['success' => true, 'message' => 'Group deleted successfully']);
                } catch (\Throwable $e) {
                    return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
                }

            default:
                return $this->json(['success' => false, 'error' => 'Invalid action'], 400);
        }
    }

    public function apiBSHeads(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action = strtolower(trim((string) $request->input('action', 'list')));

        switch ($action) {
            case 'list':
                $rows = DB::table('accountgbs')->selectRaw('TRIM(hcode) AS hcode, TRIM(hname) AS hname')->orderBy('hname')->get()->all();
                return $this->json(['success' => true, 'data' => $rows]);

            case 'validate':
                return $this->json($this->validateBSHead($request->input('hcode', ''), $request->input('mode', 'A')));

            case 'load':
                $hcode = $this->trimUpper($request->input('hcode', ''));
                $row = DB::table('accountgbs')->whereRaw('TRIM(hcode) = ?', [$hcode])->first();
                if (!$row) return $this->json(['success' => false, 'error' => 'BS Head not found'], 404);
                return $this->json(['success' => true, 'data' => [
                    'hcode' => $hcode,
                    'hname' => (string) ($row->hname ?? ''),
                    'pos' => (string) ($row->pos ?? ''),
                    'expand_checked' => strtoupper((string) ($row->expand ?? 'Y')) === 'Y',
                    'revenue_checked' => strtoupper((string) ($row->actype1 ?? '')) === 'R',
                    'expense_checked' => strtoupper((string) ($row->actype1 ?? '')) === 'E',
                    'asset_checked' => strtoupper((string) ($row->actype1 ?? 'A')) === 'A',
                    'liability_checked' => strtoupper((string) ($row->actype1 ?? '')) === 'L',
                    'reserve_checked' => strtoupper((string) ($row->reserve ?? 'N')) === 'Y',
                ]]);

            case 'save':
                $mode = strtoupper(trim((string) $request->input('mode', 'A')));
                $hcode = $this->trimUpper($request->input('hcode', ''));
                $hname = trim((string) $request->input('hname', ''));
                $actype1 = $this->trimUpper($request->input('actype1', 'A'));
                if ($hcode === '') return $this->json(['success' => false, 'error' => 'Head code is required']);
                if ($hname === '') return $this->json(['success' => false, 'error' => 'Head name is required']);
                $v = $this->validateBSHead($hcode, $mode);
                if (!empty($v['error'])) return $this->json(['success' => false, 'error' => $v['error']]);
                try {
                    $data = [
                        'hcode' => $hcode, 'hname' => $hname, 'actype1' => $actype1,
                        'reserve' => $request->input('reserve') ? 'Y' : 'N',
                        'pos' => trim((string) $request->input('pos', '')),
                        'expand' => $request->input('expand') ? 'Y' : 'N',
                    ];
                    if ($mode === 'A') {
                        DB::table('accountgbs')->insert($data);
                    } else {
                        DB::table('accountgbs')->whereRaw('TRIM(hcode) = ?', [$hcode])->update($data);
                    }
                    return $this->json(['success' => true, 'message' => 'BS Head saved successfully']);
                } catch (\Throwable $e) {
                    return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
                }

            case 'delete':
                $hcode = $this->trimUpper($request->input('hcode', ''));
                $v = $this->validateBSHead($hcode, 'D');
                if (!empty($v['error'])) return $this->json(['success' => false, 'error' => $v['error']]);
                try {
                    DB::table('accountgbs')->whereRaw('TRIM(hcode) = ?', [$hcode])->delete();
                    return $this->json(['success' => true, 'message' => 'BS Head deleted successfully']);
                } catch (\Throwable $e) {
                    return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
                }

            default:
                return $this->json(['success' => false, 'error' => 'Invalid action'], 400);
        }
    }

    public function apiPositions(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action = strtolower(trim((string) $request->input('action', '')));

        if ($action === 'get_data') {
            $type = trim((string) $request->input('type', 'Accounts Position'));
            try {
                if ($type === 'Accounts Position') {
                    $nameExpr = $this->hasColumn('accountm', 'acname') ? 'TRIM(acname)' : 'TRIM(name)';
                    $tplExpr = $this->hasColumn('accountm', 'tplpos') ? 'tplpos' : '0';
                    $sheExpr = $this->hasColumn('accountm', 'shepos') ? 'shepos' : '0';
                    $data = DB::select("SELECT TRIM(accode) AS accode, $nameExpr AS acname, $tplExpr AS tplpos, $sheExpr AS shepos FROM accountm ORDER BY accode");
                } elseif ($type === 'Group Position') {
                    $data = DB::select("SELECT TRIM(grcode) AS grcode, TRIM(name) AS name, COALESCE(pos,0) AS pos FROM accountg ORDER BY grcode");
                } elseif ($type === 'BS Head Position') {
                    $data = DB::select("SELECT TRIM(hcode) AS hcode, TRIM(hname) AS hname, COALESCE(pos,0) AS pos FROM accountgbs ORDER BY hcode");
                } else {
                    return $this->json(['success' => false, 'message' => 'Invalid type'], 400);
                }
                return $this->json(['success' => true, 'data' => $data]);
            } catch (\Throwable $e) {
                return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        if ($action === 'update_positions') {
            if ($this->isBlocked($request, 'MASTEREDIT')) {
                return $this->json(['success' => false, 'message' => 'You are not permitted to edit entries'], 403);
            }

            $type = trim((string) $request->input('type', ''));
            $rawData = $request->input('data', '[]');
            $rows = is_string($rawData) ? (json_decode($rawData, true) ?: []) : (is_array($rawData) ? $rawData : []);

            try {
                DB::beginTransaction();
                foreach ($rows as $row) {
                    if (!is_array($row)) continue;
                    if ($type === 'Accounts Position') {
                        $code = $this->trimUpper($row['accode'] ?? '');
                        if ($code === '') continue;
                        DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$code])->update([
                            'tplpos' => (int) ($row['tplpos'] ?? 0),
                            'shepos' => (int) ($row['shepos'] ?? 0),
                        ]);
                    } elseif ($type === 'Group Position') {
                        $code = $this->trimUpper($row['grcode'] ?? '');
                        if ($code === '') continue;
                        DB::table('accountg')->whereRaw('TRIM(grcode) = ?', [$code])->update(['pos' => (int) ($row['pos'] ?? 0)]);
                    } elseif ($type === 'BS Head Position') {
                        $code = $this->trimUpper($row['hcode'] ?? '');
                        if ($code === '') continue;
                        DB::table('accountgbs')->whereRaw('TRIM(hcode) = ?', [$code])->update(['pos' => (int) ($row['pos'] ?? 0)]);
                    } else {
                        DB::rollBack();
                        return $this->json(['success' => false, 'message' => 'Invalid type'], 400);
                    }
                }
                DB::commit();
                return $this->json(['success' => true, 'message' => 'Positions updated successfully']);
            } catch (\Throwable $e) {
                DB::rollBack();
                return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

        return $this->json(['success' => false, 'message' => 'Invalid action'], 400);
    }

    public function apiCoPartyLimit(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action = strtolower(trim((string) $request->input('action', '')));

        if (!$this->hasColumn('copartylimit', 'code') || !$this->hasColumn('copartylimit', 'maxamt')) {
            return $this->json(['success' => false, 'error' => 'Table copartylimit must contain code and maxamt columns'], 500);
        }

        $hasId = $this->hasColumn('copartylimit', 'id');

        if ($action === 'retrieve' || $action === 'list') {
            try {
                $idSelect = $hasId ? 'c.id AS id' : 'NULL AS id';
                $nameExpr = $this->hasColumn('accountm', 'acname') ? 'TRIM(a.acname)' : 'TRIM(a.name)';
                $rows = DB::select("SELECT $idSelect, TRIM(c.code) AS code, COALESCE(c.maxamt,0) AS maxamt, COALESCE($nameExpr,'') AS account_name FROM copartylimit c LEFT JOIN accountm a ON TRIM(a.accode) = TRIM(c.code) ORDER BY c.code");
                return $this->json(['success' => true, 'data' => $rows]);
            } catch (\Throwable $e) {
                return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }

        if ($action === 'account_list') {
            try {
                $nameExpr = $this->hasColumn('accountm', 'acname') ? 'TRIM(acname)' : 'TRIM(name)';
                $removedWhere = $this->hasColumn('accountm', 'removed') ? " AND (removed <> 1 OR removed IS NULL)" : "";
                $search = trim((string) $request->input('search', ''));
                $sql = "SELECT TRIM(accode) AS code, COALESCE($nameExpr,'') AS name
                        FROM accountm
                        WHERE TRIM(accode) <> '' $removedWhere";
                $bindings = [];
                if ($search !== '') {
                    $like = '%' . $search . '%';
                    $sql .= " AND (TRIM(accode) LIKE ? OR $nameExpr LIKE ?)";
                    $bindings[] = $like;
                    $bindings[] = $like;
                }
                $sql .= " ORDER BY $nameExpr ASC";
                $rows = DB::select($sql, $bindings);
                return $this->json(['success' => true, 'data' => $rows]);
            } catch (\Throwable $e) {
                return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }

        if ($action === 'save') {
            if ($this->isBlocked($request, 'MASTEREDIT')) {
                return $this->json(['success' => false, 'error' => 'You are not permitted to edit entries'], 403);
            }

            $raw = $request->input('data', '[]');
            $rows = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);

            try {
                DB::beginTransaction();
                foreach ($rows as $row) {
                    if (!is_array($row)) continue;
                    $code = $this->trimUpper($row['code'] ?? '');
                    if ($code === '') continue;
                    $maxAmt = (float) ($row['maxamt'] ?? 0);

                    $accountExists = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$code])->exists();
                    if (!$accountExists) {
                        DB::rollBack();
                        return $this->json(['success' => false, 'error' => 'Invalid account code: ' . $code], 400);
                    }

                    $rowId = $hasId ? (int) ($row['id'] ?? 0) : 0;
                    if ($hasId && $rowId > 0) {
                        DB::table('copartylimit')->where('id', $rowId)->update(['code' => $code, 'maxamt' => $maxAmt]);
                        continue;
                    }

                    $existing = DB::table('copartylimit')->whereRaw('TRIM(code) = ?', [$code])->first();
                    if ($existing) {
                        if ($hasId) {
                            DB::table('copartylimit')->where('id', $existing->id)->update(['code' => $code, 'maxamt' => $maxAmt]);
                        } else {
                            DB::table('copartylimit')->whereRaw('TRIM(code) = ?', [$code])->update(['maxamt' => $maxAmt]);
                        }
                    } else {
                        DB::table('copartylimit')->insert(['code' => $code, 'maxamt' => $maxAmt]);
                    }
                }
                DB::commit();
                return $this->json(['success' => true, 'message' => 'Data saved successfully']);
            } catch (\Throwable $e) {
                DB::rollBack();
                return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }

        if ($action === 'delete') {
            if ($this->isBlocked($request, 'MASTERDELETE')) {
                return $this->json(['success' => false, 'error' => 'You are not permitted to delete entries'], 403);
            }
            $id = (int) $request->input('id', 0);
            $code = $this->trimUpper($request->input('code', ''));
            if ($hasId && $id > 0) {
                DB::table('copartylimit')->where('id', $id)->delete();
                return $this->json(['success' => true, 'message' => 'Record deleted successfully']);
            }
            if ($code !== '') {
                DB::table('copartylimit')->whereRaw('TRIM(code) = ?', [$code])->delete();
                return $this->json(['success' => true, 'message' => 'Record deleted successfully']);
            }
            return $this->json(['success' => false, 'error' => 'Id or code is required'], 400);
        }

        return $this->json(['success' => false, 'error' => 'Invalid action'], 400);
    }

    public function apiSalesMan(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action = strtolower(trim((string) $request->input('action', '')));

        if (!$this->hasColumn('sman', 'code') || !$this->hasColumn('sman', 'name') || !$this->hasColumn('sman', 'accode') || !$this->hasColumn('sman', 'active')) {
            return $this->json(['success' => false, 'error' => 'Table sman must contain code, name, accode, active columns'], 500);
        }

        if ($action === 'retrieve' || $action === 'list') {
            try {
                $rows = DB::table('sman')->selectRaw("TRIM(code) AS code, TRIM(name) AS name, TRIM(accode) AS accode, COALESCE(active,'Y') AS active")->orderBy('code')->get()->all();
                return $this->json(['success' => true, 'data' => $rows]);
            } catch (\Throwable $e) {
                return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }

        if ($action === 'account_list') {
            try {
                $nameExpr = $this->hasColumn('accountm', 'acname') ? 'TRIM(acname)' : 'TRIM(name)';
                $removedWhere = $this->hasColumn('accountm', 'removed') ? " AND (removed <> 1 OR removed IS NULL)" : "";
                $rows = DB::select("SELECT TRIM(accode) AS code, COALESCE($nameExpr,'') AS name FROM accountm WHERE TRIM(accode) <> '' $removedWhere ORDER BY $nameExpr ASC");
                return $this->json(['success' => true, 'data' => $rows]);
            } catch (\Throwable $e) {
                return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }

        if ($action === 'check_usage' || $action === 'delete') {
            $code = $this->trimUpper($request->input('code', ''));
            if ($code === '') return $this->json(['success' => false, 'error' => 'Code is required'], 400);
            try {
                $inUseCount = 0;
                if ($this->hasColumn('orderm', 'smcode')) {
                    $inUseCount = (int) DB::table('orderm')->whereRaw('TRIM(smcode) = ?', [$code])->count();
                }
                if ($action === 'check_usage') {
                    return $this->json(['success' => true, 'count' => $inUseCount, 'in_use' => $inUseCount > 0]);
                }
                if ($inUseCount > 0) {
                    return $this->json(['success' => false, 'error' => "You can't delete this entry - it is being used in orders", 'in_use' => true]);
                }
                return $this->json(['success' => true, 'can_delete' => true]);
            } catch (\Throwable $e) {
                return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }

        if ($action === 'save') {
            $raw = $request->input('entries', $request->input('data', '[]'));
            $rows = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);

            try {
                DB::beginTransaction();
                DB::table('sman')->delete();
                foreach ($rows as $row) {
                    if (!is_array($row)) continue;
                    $code = $this->trimUpper($row['code'] ?? '');
                    if ($code === '') continue;
                    $name = strtoupper(trim((string) ($row['name'] ?? '')));
                    $accode = $this->trimUpper($row['accode'] ?? '');
                    $active = strtoupper(trim((string) ($row['active'] ?? 'Y')));
                    if ($active !== 'N') $active = 'Y';

                    if ($accode !== '') {
                        if (!DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$accode])->exists()) {
                            DB::rollBack();
                            return $this->json(['success' => false, 'error' => 'Invalid account code: ' . $accode], 400);
                        }
                    }

                    DB::table('sman')->insert(['code' => $code, 'name' => $name, 'accode' => $accode, 'active' => $active]);
                }
                DB::commit();
                return $this->json(['success' => true, 'message' => 'Data saved successfully']);
            } catch (\Throwable $e) {
                DB::rollBack();
                return $this->json(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }

        return $this->json(['success' => false, 'error' => 'Invalid action'], 400);
    }

    // ── Internal helpers ──

    private function loadAccountRow(string $accode): ?object
    {
        $accode = trim($accode);
        if ($accode === '') return null;

        $columns = ['name', 'opbal', 'opbalb', 'actype1', 'actype2', 'control', 'reserve', 'grcode', 'hlp', 'tplpos', 'bshead', 'shepos', 'shedgrp', 'sp', 'removed', 'blocked', 'note'];
        $available = array_filter($columns, fn ($c) => $this->hasColumn('accountm', $c));
        if (empty($available)) return null;

        $sql = "SELECT " . implode(', ', $available) . " FROM accountm WHERE TRIM(accode) = ? LIMIT 1";
        return DB::selectOne($sql, [$accode]);
    }

    private function validateAccount(string $accode, string $mode): array
    {
        $accode = $this->trimUpper($accode);
        $mode = strtoupper(trim($mode));
        if ($accode === '') return ['error' => 'Account code is required'];

        $exists = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$accode])->exists();
        if ($mode === 'A' && $exists) return ['error' => 'This account code already exists'];
        if (($mode === 'E' || $mode === 'D') && !$exists) return ['error' => 'This account code does not exist'];

        if ($mode === 'D') {
            $meta = DB::table('accountm')->selectRaw('reserve, actype2')->whereRaw('TRIM(accode) = ?', [$accode])->first();
            if ($meta && strtoupper((string) ($meta->reserve ?? 'N')) === 'Y') {
                return ['error' => 'Reserved code. Cannot delete.'];
            }
            $actype2 = strtoupper(trim((string) ($meta->actype2 ?? '')));
            if (in_array($actype2, ['C', 'S', 'G', 'R', 'J'], true)) {
                return ['error' => 'Linked account. Cannot delete through Account Master.'];
            }
            try {
                $amountExpr = $this->hasColumn('daybook', 'amount') ? 'SUM(ABS(amount))' : 'COUNT(*)';
                $hasTx = DB::selectOne("SELECT COALESCE($amountExpr,0) AS total FROM daybook WHERE TRIM(accode) = ?", [$accode]);
                if (((float) ($hasTx->total ?? 0)) > 0) {
                    return ['error' => 'Transactions exist. Cannot delete.'];
                }
            } catch (\Throwable $e) {
                return ['error' => 'Unable to verify transactions: ' . $e->getMessage()];
            }
        }

        return ['success' => true];
    }

    private function saveAccountInternal(Request $request): array
    {
        $mode = strtoupper(trim((string) $request->input('mode', 'A')));
        $accode = $this->trimUpper($request->input('accode', ''));
        $originalCode = $this->trimUpper($request->input('original_accode', $accode));
        $desc = trim((string) $request->input('desc', ''));
        $grcode = $this->trimUpper($request->input('grcode', ''));
        $bshead = $this->trimUpper($request->input('bshead', ''));
        $actype1 = $this->trimUpper($request->input('actype1', 'A'));
        $actype2 = strtoupper(trim((string) $request->input('actype2', '')));
        if ($actype2 === '') $actype2 = ' ';

        if ($accode === '') return ['error' => 'Account code is required'];
        if ($desc === '') return ['error' => 'Description is required'];
        if ($grcode === '') return ['error' => 'Group is compulsory for all accounts'];
        if ($bshead === '' && in_array($actype1, ['A', 'L'], true)) return ['error' => 'BS Head is compulsory for Asset/Liability type accounts'];
        if (!in_array($actype1, ['R', 'E', 'A', 'L'], true)) return ['error' => 'Invalid account type'];

        if ($mode === 'E' && $this->isBlocked($request, 'MASTEREDIT')) return ['error' => 'You are not permitted to edit entries'];

        if ($mode === 'E') {
            if ($originalCode === '') return ['error' => 'Original account code is required for edit'];
            if (!DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$originalCode])->exists()) return ['error' => 'This account code does not exist'];
            if ($accode !== $originalCode) {
                if (DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$accode])->exists()) return ['error' => 'This account code already exists'];
                if ($this->hasColumn('daybook', 'accode')) {
                    $txCount = (int) DB::table('daybook')->whereRaw('TRIM(accode) = ?', [$originalCode])->count();
                    if ($txCount > 0) return ['error' => 'Cannot change code. Transactions exist for this account.'];
                }
            }
        } else {
            $validation = $this->validateAccount($accode, $mode);
            if (!empty($validation['error'])) return $validation;
        }

        $opbal = $this->normalizeAmount($request->input('balance', 0), strtolower((string) $request->input('balance_type', 'debit')));
        $opbalb = $opbal;
        $shedgrp = $this->trimUpper($request->input('shedgrp', ''));
        if ($shedgrp === '') $shedgrp = $accode;

        $row = [
            'accode' => $accode, 'name' => $desc, 'opbal' => $opbal, 'opbalb' => $opbalb,
            'actype1' => $actype1, 'actype2' => $actype2,
            'control' => $request->input('display') ? 1 : 2,
            'grcode' => $grcode, 'hlp' => $request->input('hlp') ? 0 : 1,
            'tplpos' => trim((string) $request->input('pos', '')),
            'bshead' => $bshead,
            'shepos' => trim((string) $request->input('shedpos', '')),
            'shedgrp' => $shedgrp,
            'reserve' => $request->input('reserve') ? 'Y' : 'N',
            'sp' => $request->input('sp') ? 1 : 0,
            'removed' => $request->input('removed') ? 1 : 0,
            'blocked' => $request->input('blocked') ? 'Y' : 'N',
            'note' => trim((string) $request->input('note', '')),
        ];

        $filtered = [];
        foreach ($row as $k => $v) {
            if ($this->hasColumn('accountm', $k)) $filtered[$k] = $v;
        }

        try {
            if ($mode === 'A') {
                DB::table('accountm')->insert($filtered);
            } else {
                if (empty($filtered)) return ['error' => 'No writable columns found in accountm'];
                DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$mode === 'E' ? $originalCode : $accode])->update($filtered);
                if ($mode === 'E' && $accode !== $originalCode && $this->hasColumn('accountm', 'shedgrp')) {
                    DB::table('accountm')->whereRaw('TRIM(shedgrp) = ?', [$originalCode])->update(['shedgrp' => $accode]);
                }
            }
            return ['success' => true, 'message' => 'Account saved successfully'];
        } catch (\Throwable $e) {
            return ['error' => 'Database error: ' . $e->getMessage()];
        }
    }

    private function deleteAccountInternal(string $accode): array
    {
        // Note: permission check handled by request context — caller should verify
        $validation = $this->validateAccount($accode, 'D');
        if (!empty($validation['error'])) return $validation;
        try {
            $deleted = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$this->trimUpper($accode)])->delete();
            if ((int) $deleted === 0) {
                return ['error' => 'Delete did not remove any row.'];
            }
            return ['success' => true, 'message' => 'Account deleted successfully'];
        } catch (\Throwable $e) {
            return ['error' => 'Database error: ' . $e->getMessage()];
        }
    }

    private function validateGroup(string $grcode, string $mode): array
    {
        $grcode = $this->trimUpper($grcode);
        $mode = strtoupper(trim($mode));
        if ($grcode === '') return ['error' => 'Group code is required'];
        $exists = DB::table('accountg')->whereRaw('TRIM(grcode) = ?', [$grcode])->exists();
        if ($mode === 'A' && $exists) return ['error' => 'This code already exists'];
        if (($mode === 'E' || $mode === 'D') && !$exists) return ['error' => 'This code does not exist'];
        if ($mode === 'D') {
            $row = DB::table('accountg')->selectRaw('reserve')->whereRaw('TRIM(grcode) = ?', [$grcode])->first();
            if ($row && strtoupper((string) ($row->reserve ?? 'N')) === 'Y') return ['error' => 'It is a reserved code. You cannot delete it.'];
            $count = (int) DB::table('accountm')->whereRaw('TRIM(grcode) = ?', [$grcode])->count();
            if ($count > 0) return ['error' => 'You cannot delete this code. Entries exist.'];
        }
        return ['success' => true];
    }

    private function validateBSHead(string $hcode, string $mode): array
    {
        $hcode = $this->trimUpper($hcode);
        $mode = strtoupper(trim($mode));
        if ($hcode === '') return ['error' => 'Head code is required'];
        $exists = DB::table('accountgbs')->whereRaw('TRIM(hcode) = ?', [$hcode])->exists();
        if ($mode === 'A' && $exists) return ['error' => 'This code already exists'];
        if (($mode === 'E' || $mode === 'D') && !$exists) return ['error' => 'This code does not exist'];
        if ($mode === 'D') {
            $row = DB::table('accountgbs')->selectRaw('reserve')->whereRaw('TRIM(hcode) = ?', [$hcode])->first();
            if ($row && strtoupper((string) ($row->reserve ?? 'N')) === 'Y') return ['error' => 'It is a reserved code. You cannot delete it.'];
            $count = (int) DB::table('accountm')->whereRaw('TRIM(bshead) = ?', [$hcode])->count();
            if ($count > 0) return ['error' => 'You cannot delete this code. Entries exist.'];
        }
        return ['success' => true];
    }

    private function upsertGeneralValue(string $code, string $value, string $description = ''): void
    {
        $code = $this->trimUpper($code);
        $row = DB::table('generals')->where('code', $code)->first();
        if ($row) {
            DB::table('generals')->where('code', $code)->update(['cvalue' => $value]);
            return;
        }
        $data = ['code' => $code, 'cvalue' => $value];
        if ($this->hasColumn('generals', 'description')) $data['description'] = $description;
        DB::table('generals')->insert($data);
    }

    private function getOrCreateGeneralValue(string $code, string $fallback = '', string $description = ''): string
    {
        $code = $this->trimUpper($code);
        $row = DB::table('generals')->where('code', $code)->first();
        if ($row && isset($row->cvalue)) return (string) $row->cvalue;
        $this->upsertGeneralValue($code, $fallback, $description);
        return $fallback;
    }

    private function normalizeUiDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        $formats = ['d/m/Y', 'Y-m-d', 'd-m-Y'];
        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt && $dt->format($format) === $value) return $dt->format('Y-m-d');
        }
        return null;
    }

    private function formatUiDate(string $dbDate): string
    {
        $dbDate = trim($dbDate);
        if ($dbDate === '') return '';
        $ts = strtotime($dbDate);
        return $ts === false ? $dbDate : date('d/m/Y', $ts);
    }
}
