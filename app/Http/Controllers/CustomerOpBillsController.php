<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CustomerOpBillsController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        return view('op-bill-creation.customers', [
            'clientsMissing' => !$this->hasTable('clients'),
            'salesmMissing' => !$this->hasTable('salesm'),
            'accountmMissing' => !$this->hasTable('accountm'),
            'generaliMissing' => !$this->hasTable('generali'),
        ]);
    }

    public function lookupCustomer(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('clients')) {
            return response()->json(['success' => false, 'message' => '`clients` table not found.']);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Code is required.']);
        }

        $row = DB::table('clients')
            ->select('code', 'name')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->first();

        if (!$row) {
            return response()->json(['success' => false, 'message' => 'This code does not exist...']);
        }

        return response()->json([
            'success' => true,
            'code' => (string) ($row->code ?? ''),
            'name' => (string) ($row->name ?? ''),
        ]);
    }

    public function retrieveBills(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('salesm')) {
            return response()->json(['success' => false, 'message' => '`salesm` table not found.']);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        $level = (int) $request->input('level', 2);
        if ($code === '') {
            return response()->json(['success' => true, 'data' => [], 'lvalue' => 0]);
        }

        $rows = DB::table('salesm')
            ->select([
                'billno',
                'tdate',
                'grate',
                'billamt',
                'ramt',
                'ramtafter',
                'dstatus',
                'secwgt',
                'secnote',
                'custname',
            ])
            ->selectRaw('(COALESCE(billamt,0) - COALESCE(ramt,0) - COALESCE(ramtafter,0)) AS balance')
            ->whereRaw('UPPER(TRIM(custcode)) = ?', [$code])
            ->where('control', '<=', $level)
            ->where(function ($q): void {
                $q->whereRaw("LEFT(billno,2) = 'OP'")
                  ->orWhere('opbill', 1);
            })
            ->orderBy('tdate')
            ->orderBy('slno')
            ->get();

        $lvalue = 0;
        if ($this->hasTable('generali')) {
            $lvalue = (int) (DB::table('generali')->where('code', 'SOPBILLNO')->value('cvalue') ?? 0);
        }

        return response()->json(['success' => true, 'data' => $rows, 'lvalue' => $lvalue]);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('clients')) {
            return response()->json(['success' => false, 'message' => '`clients` table not found.']);
        }

        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json(['success' => true, 'data' => []]);
        }

        $rows = DB::table('clients')
            ->select('code', 'name')
            ->where(function ($query) use ($q): void {
                $query->where('code', 'like', $q . '%')
                      ->orWhere('name', 'like', '%' . $q . '%');
            })
            ->orderBy('code')
            ->limit(20)
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        foreach (['salesm', 'clients', 'accountm', 'generali'] as $table) {
            if (!$this->hasTable($table)) {
                return response()->json(['success' => false, 'message' => "`{$table}` table not found."]);
            }
        }

        $custcode = strtoupper(trim((string) $request->input('custcode', '')));
        $custname = trim((string) $request->input('custname', ''));
        $lvalue = (int) $request->input('lvalue', 0);
        $gisemi = (int) $request->input('gisemi', 1);
        $rows = $request->input('rows', []);

        if ($custcode === '') {
            return response()->json(['success' => false, 'message' => 'No customer selected.']);
        }
        if (!is_array($rows)) {
            return response()->json(['success' => false, 'message' => 'Invalid rows payload.']);
        }

        DB::transaction(function () use ($custcode, $custname, $lvalue, $gisemi, $rows): void {
            DB::table('salesm')
                ->whereRaw('UPPER(TRIM(custcode)) = ?', [$custcode])
                ->where(function ($q): void {
                    $q->whereRaw("LEFT(billno,2) = 'OP'")
                        ->orWhere('opbill', 1);
                })
                ->delete();

            $currentSerial = (int) (DB::table('generali')
                ->lockForUpdate()
                ->where('code', 'SERIALNO')
                ->value('cvalue') ?? 0);

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $billno = trim((string) ($row['billno'] ?? ''));
                $billamt = (float) ($row['billamt'] ?? 0);
                if ($billno === '' || $billamt <= 0) {
                    continue;
                }

                $currentSerial++;

                DB::table('salesm')->insert([
                    'slno' => $currentSerial,
                    'billno' => $billno,
                    'custcode' => $custcode,
                    'custname' => trim((string) ($row['custname'] ?? $custname)),
                    'billamt' => $billamt,
                    'ramt' => (float) ($row['ramt'] ?? 0),
                    'discount' => 0,
                    'status' => 2,
                    'sr' => 'S',
                    'eamt' => 0,
                    'grate' => (float) ($row['grate'] ?? 0),
                    'control' => $gisemi,
                    'tdate' => (string) ($row['tdate'] ?? now()->toDateString()),
                    'staxperc' => 0,
                    'staxamt' => 0,
                    'srate' => 0,
                    'ramtafter' => (float) ($row['ramtafter'] ?? 0),
                    'sretamt' => 0,
                    'jrate' => 0,
                    'round' => 0,
                    'opbill' => 1,
                    'netamt' => $billamt,
                    'dstatus' => trim((string) ($row['dstatus'] ?? 'Delivered')),
                    'secwgt' => (float) ($row['secwgt'] ?? 0),
                    'secnote' => trim((string) ($row['secnote'] ?? '')),
                ]);
            }

            DB::table('generali')->upsert(
                [['code' => 'SERIALNO', 'cvalue' => $currentSerial]],
                ['code'],
                ['cvalue']
            );
            DB::table('generali')->upsert(
                [['code' => 'SOPBILLNO', 'cvalue' => $lvalue]],
                ['code'],
                ['cvalue']
            );

            $bal1 = (float) (DB::table('salesm')
                ->whereRaw('UPPER(TRIM(custcode)) = ?', [$custcode])
                ->where(function ($q): void {
                    $q->whereRaw("LEFT(billno,2) = 'OP'")
                        ->orWhere('opbill', 1);
                })
                ->where('control', 1)
                ->selectRaw('COALESCE(SUM(billamt - ramt),0) AS bal')
                ->value('bal') ?? 0);
            $dbalance = -$bal1;

            $bal2 = (float) (DB::table('salesm')
                ->whereRaw('UPPER(TRIM(custcode)) = ?', [$custcode])
                ->where(function ($q): void {
                    $q->whereRaw("LEFT(billno,2) = 'OP'")
                        ->orWhere('opbill', 1);
                })
                ->where('control', '<=', 2)
                ->selectRaw('COALESCE(SUM(billamt - ramt),0) AS bal')
                ->value('bal') ?? 0);
            $dbalanceb = -$bal2;

            $clientUpdate = [];
            if (Schema::hasColumn('clients', 'opbalance')) {
                $clientUpdate['opbalance'] = $dbalance;
            }
            if (Schema::hasColumn('clients', 'opbalanceb')) {
                $clientUpdate['opbalanceb'] = $dbalanceb;
            }
            if ($clientUpdate !== []) {
                DB::table('clients')
                    ->whereRaw('TRIM(code) = ?', [$custcode])
                    ->update($clientUpdate);
            }

            $accountUpdate = [];
            if (Schema::hasColumn('accountm', 'opbal')) {
                $accountUpdate['opbal'] = $dbalance;
            }
            if (Schema::hasColumn('accountm', 'opbalb')) {
                $accountUpdate['opbalb'] = $dbalanceb;
            }
            if ($accountUpdate !== []) {
                DB::table('accountm')
                    ->whereRaw('TRIM(accode) = ?', [$custcode])
                    ->update($accountUpdate);
            }
        });

        return response()->json(['success' => true, 'message' => 'Saved successfully.']);
    }

    private function isAuthorized(Request $request): bool
    {
        if ((string) ($request->session()->get('user_code') ?? '') !== '') {
            return true;
        }

        $legacySessionId = preg_replace('/[^a-zA-Z0-9,-]/', '', (string) ($_COOKIE['PHPSESSID'] ?? ''));
        if ($legacySessionId === '') {
            return false;
        }

        $savePath = (string) ini_get('session.save_path');
        if ($savePath === '') {
            return false;
        }
        if (str_contains($savePath, ';')) {
            $parts = explode(';', $savePath);
            $savePath = (string) end($parts);
        }
        $savePath = trim($savePath);
        if ($savePath === '') {
            return false;
        }

        $sessionFile = rtrim($savePath, "/\\") . DIRECTORY_SEPARATOR . 'sess_' . $legacySessionId;
        if (!is_file($sessionFile) || !is_readable($sessionFile)) {
            return false;
        }

        $raw = (string) @file_get_contents($sessionFile);
        return $raw !== '' && str_contains($raw, 'user_code|');
    }
}

