<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CustomerPopupController extends Controller
{
    public function routesPage(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        return view('native.customer.popups.routes', [
            'missing' => !$this->hasTable('route'),
        ]);
    }

    public function routesRetrieve(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('route')) {
            return response()->json(['success' => false, 'message' => '`route` table not found.']);
        }

        $rows = DB::table('route')
            ->select('code', 'name')
            ->orderBy('code')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function routesSave(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('route')) {
            return response()->json(['success' => false, 'message' => '`route` table not found.']);
        }

        $rows = $request->input('rows', []);
        $deleted = $request->input('deleted', []);
        if (!is_array($rows) || !is_array($deleted)) {
            return response()->json(['success' => false, 'message' => 'Invalid payload.'], 422);
        }

        DB::transaction(function () use ($rows, $deleted): void {
            foreach ($deleted as $code) {
                $code = strtoupper(trim((string) $code));
                if ($code === '') {
                    continue;
                }

                if ($this->hasTable('clients')) {
                    $used = (int) DB::table('clients')
                        ->whereRaw('UPPER(TRIM(route)) = ?', [$code])
                        ->count('code');
                    if ($used > 0) {
                        continue;
                    }
                }

                DB::table('route')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->delete();
            }

            $upserts = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                if ($code === '') {
                    continue;
                }
                $upserts[] = [
                    'code' => $code,
                    'name' => trim((string) ($row['name'] ?? '')),
                ];
            }

            if ($upserts !== []) {
                DB::table('route')->upsert($upserts, ['code'], ['name']);
            }
        });

        return response()->json(['success' => true, 'message' => 'Saved successfully.']);
    }

    public function areaPage(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        return view('native.customer.popups.area', [
            'missing' => !$this->hasTable('area'),
        ]);
    }

    public function areaRetrieve(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('area')) {
            return response()->json(['success' => false, 'message' => '`area` table not found.']);
        }

        $rows = DB::table('area')
            ->select('code', 'name')
            ->orderBy('code')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function areaSave(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('area')) {
            return response()->json(['success' => false, 'message' => '`area` table not found.']);
        }

        $rows = $request->input('rows', []);
        $deleted = $request->input('deleted', []);
        if (!is_array($rows) || !is_array($deleted)) {
            return response()->json(['success' => false, 'message' => 'Invalid payload.'], 422);
        }

        DB::transaction(function () use ($rows, $deleted): void {
            foreach ($deleted as $code) {
                $code = strtoupper(trim((string) $code));
                if ($code === '') {
                    continue;
                }

                if ($this->hasTable('clients_advanced')) {
                    $used = (int) DB::table('clients_advanced')
                        ->whereRaw('UPPER(TRIM(area)) = ?', [$code])
                        ->count('code');
                    if ($used > 0) {
                        continue;
                    }
                }

                DB::table('area')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->delete();
            }

            $upserts = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                if ($code === '') {
                    continue;
                }
                $upserts[] = [
                    'code' => $code,
                    'name' => trim((string) ($row['name'] ?? '')),
                ];
            }

            if ($upserts !== []) {
                DB::table('area')->upsert($upserts, ['code'], ['name']);
            }
        });

        return response()->json(['success' => true, 'message' => 'Saved successfully.']);
    }

    public function pcardPage(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        return view('native.customer.popups.pcard', [
            'missing' => !$this->hasTable('pcard'),
        ]);
    }

    public function pcardRetrieve(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('pcard')) {
            return response()->json(['success' => false, 'message' => '`pcard` table not found.']);
        }

        $rows = DB::table('pcard')
            ->select('code', 'name', 'vadisc', 'totdisc')
            ->orderBy('code')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function pcardSave(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('pcard')) {
            return response()->json(['success' => false, 'message' => '`pcard` table not found.']);
        }

        $rows = $request->input('rows', []);
        $deleted = $request->input('deleted', []);
        if (!is_array($rows) || !is_array($deleted)) {
            return response()->json(['success' => false, 'message' => 'Invalid payload.'], 422);
        }

        DB::transaction(function () use ($rows, $deleted): void {
            foreach ($deleted as $code) {
                $code = strtoupper(trim((string) $code));
                if ($code === '') {
                    continue;
                }

                if ($this->hasTable('clients')) {
                    $used = (int) DB::table('clients')
                        ->whereRaw('UPPER(TRIM(pcard)) = ?', [$code])
                        ->count('code');
                    if ($used > 0) {
                        continue;
                    }
                }

                DB::table('pcard')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->delete();
            }

            $upserts = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                if ($code === '') {
                    continue;
                }
                $upserts[] = [
                    'code' => $code,
                    'name' => strtoupper(trim((string) ($row['name'] ?? ''))),
                    'vadisc' => (float) ($row['vadisc'] ?? 0),
                    'totdisc' => (float) ($row['totdisc'] ?? 0),
                ];
            }

            if ($upserts !== []) {
                DB::table('pcard')->upsert($upserts, ['code'], ['name', 'vadisc', 'totdisc']);
            }
        });

        return response()->json(['success' => true, 'message' => 'Saved successfully.']);
    }

    public function advancedPage(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }
        $mode = strtoupper(trim((string) $request->query('mode', 'C')));
        if (!in_array($mode, ['C', 'S'], true)) {
            $mode = 'C';
        }

        return view('native.customer.popups.advanced', [
            'mode' => $mode,
        ]);
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

