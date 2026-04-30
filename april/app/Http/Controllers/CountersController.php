<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CountersController extends Controller
{
    use LogsDelpartAudit;

    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $tableMissing = !$this->hasTable('counter');
        $counters = collect();

        if (!$tableMissing) {
            $counters = DB::table('counter')
                ->select('code', 'name', 'startbillno')
                ->orderBy('code')
                ->get();
        }

        return view('counters.index', [
            'counters' => $counters,
            'tableMissing' => $tableMissing,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('counter')) {
            return response()->json(['success' => false, 'message' => '`counter` table not found.']);
        }

        $rows = $request->input('rows', []);
        if (!is_array($rows)) {
            return response()->json(['success' => false, 'message' => 'Invalid rows payload.']);
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                $name = strtoupper(trim((string) ($row['name'] ?? '')));
                $startBillNo = (int) ($row['startbillno'] ?? 0);

                if ($code === '') {
                    continue;
                }

                DB::table('counter')->upsert(
                    [[
                        'code' => $code,
                        'name' => $name,
                        'startbillno' => $startBillNo,
                    ]],
                    ['code'],
                    ['name', 'startbillno']
                );

                if (
                    $startBillNo > 0
                    && $this->hasTable('salesm')
                    && $this->hasTable('generali')
                ) {
                    $salesCount = (int) DB::table('salesm')
                        ->where('counter', $code)
                        ->count('slno');

                    if ($salesCount === 0) {
                        DB::table('generali')->upsert(
                            [[
                                'code' => $code,
                                'cvalue' => $startBillNo,
                            ]],
                            ['code'],
                            ['cvalue']
                        );
                    }
                }
            }
        });

        $this->logDelpart($request, 'Counters Saved', ['utype' => 'E', 'ttype' => 'R']);
        return response()->json(['success' => true, 'message' => 'Saved successfully.']);
    }

    public function delete(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('counter')) {
            return response()->json(['success' => false, 'message' => '`counter` table not found.']);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'No row selected.']);
        }

        if ($this->hasTable('barcode')) {
            $used = DB::table('barcode')
                ->where('counter', $code)
                ->exists();

            if ($used) {
                return response()->json(['success' => false, 'message' => "You can't delete this entry!"]);
            }
        }

        DB::table('counter')->where('code', $code)->delete();
        $this->logDelpart($request, 'Counter(' . $code . ') Deleted', ['utype' => 'D', 'ttype' => 'R']);
        return response()->json(['success' => true, 'message' => 'Deleted successfully.']);
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
