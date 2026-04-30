<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DenominationMasterController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        return view('denomination-master.index', [
            'denomTableMissing' => !$this->hasTable('denom_master'),
            'salesmMissing' => !$this->hasTable('salesm'),
        ]);
    }

    public function retrieve(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('denom_master')) {
            return response()->json(['success' => false, 'message' => '`denom_master` table not found.']);
        }

        $rows = DB::table('denom_master')
            ->select('code', 'name', 'cvalue')
            ->orderBy('code')
            ->get()
            ->map(fn ($row) => [
                'code' => trim((string) ($row->code ?? '')),
                'name' => trim((string) ($row->name ?? '')),
                'cvalue' => (float) ($row->cvalue ?? 0),
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('denom_master')) {
            return response()->json(['success' => false, 'message' => '`denom_master` table not found.']);
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
                DB::table('denom_master')->where('code', $code)->delete();
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
                    'cvalue' => (float) ($row['cvalue'] ?? 0),
                ];
            }

            if ($upserts !== []) {
                DB::table('denom_master')->upsert(
                    $upserts,
                    ['code'],
                    ['name', 'cvalue']
                );
            }
        });

        return response()->json(['success' => true, 'message' => 'Saved successfully.']);
    }

    public function checkRef(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('salesm')) {
            return response()->json(['success' => true, 'count' => 0]);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => true, 'count' => 0]);
        }

        $salesTypeCol = $this->resolveSalesTypeColumn();
        if ($salesTypeCol === null) {
            return response()->json(['success' => true, 'count' => 0]);
        }

        $count = (int) DB::table('salesm')
            ->whereRaw('UPPER(TRIM(' . $salesTypeCol . ')) = ?', [$code])
            ->count('slno');

        return response()->json(['success' => true, 'count' => $count]);
    }

    private function resolveSalesTypeColumn(): ?string
    {
        if (!$this->hasTable('salesm')) {
            return null;
        }
        $cols = array_map('strtolower', $this->columnList('salesm'));
        foreach (['saletype', 'salestype', 'billtype'] as $candidate) {
            if (in_array($candidate, $cols, true)) {
                return $candidate;
            }
        }
        return null;
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
