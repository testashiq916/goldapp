<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PointCardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        return view('point-card.index', [
            'pcardTableMissing' => !$this->hasTable('pcardtable'),
        ]);
    }

    public function retrieve(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('pcardtable')) {
            return response()->json(['success' => false, 'message' => '`pcardtable` table not found.']);
        }

        $rows = DB::table('pcardtable')
            ->select(
                'pcard',
                'isubgrp',
                'pointbasedon',
                'valuefor1point',
                'valueperpoint',
                'minsalesamt',
                'rounddown'
            )
            ->orderBy('pcard')
            ->orderBy('isubgrp')
            ->get()
            ->map(fn ($row) => [
                'pcard' => trim((string) ($row->pcard ?? '')),
                'isubgrp' => trim((string) ($row->isubgrp ?? '')),
                'pointbasedon' => (string) ($row->pointbasedon ?? 'Wgt'),
                'valuefor1point' => (float) ($row->valuefor1point ?? 0),
                'valueperpoint' => (float) ($row->valueperpoint ?? 0),
                'minsalesamt' => (float) ($row->minsalesamt ?? 0),
                'rounddown' => (float) ($row->rounddown ?? 0),
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('pcardtable')) {
            return response()->json(['success' => false, 'message' => '`pcardtable` table not found.']);
        }

        $rows = $request->input('rows', []);
        $deleted = $request->input('deleted', []);
        if (!is_array($rows) || !is_array($deleted)) {
            return response()->json(['success' => false, 'message' => 'Invalid payload.'], 422);
        }

        DB::transaction(function () use ($rows, $deleted): void {
            foreach ($deleted as $keyPair) {
                if (!is_array($keyPair) || count($keyPair) < 2) {
                    continue;
                }
                $pcard = trim((string) ($keyPair[0] ?? ''));
                $isubgrp = trim((string) ($keyPair[1] ?? ''));
                if ($pcard === '' || $isubgrp === '') {
                    continue;
                }

                DB::table('pcardtable')
                    ->where('pcard', $pcard)
                    ->where('isubgrp', $isubgrp)
                    ->delete();
            }

            $upsertRows = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $pcard = trim((string) ($row['pcard'] ?? ''));
                $isubgrp = trim((string) ($row['isubgrp'] ?? ''));
                if ($pcard === '' || $isubgrp === '') {
                    continue;
                }

                $pointBasedOn = trim((string) ($row['pointbasedon'] ?? 'Wgt'));
                if (!in_array($pointBasedOn, ['Wgt', 'Wgt/Amt', 'Amt'], true)) {
                    $pointBasedOn = 'Wgt';
                }

                $upsertRows[] = [
                    'pcard' => $pcard,
                    'isubgrp' => $isubgrp,
                    'pointbasedon' => $pointBasedOn,
                    'valuefor1point' => (float) ($row['valuefor1point'] ?? 0),
                    'valueperpoint' => (float) ($row['valueperpoint'] ?? 0),
                    'minsalesamt' => (float) ($row['minsalesamt'] ?? 0),
                    'rounddown' => (float) ($row['rounddown'] ?? 0),
                ];
            }

            if ($upsertRows !== []) {
                DB::table('pcardtable')->upsert(
                    $upsertRows,
                    ['pcard', 'isubgrp'],
                    ['pointbasedon', 'valuefor1point', 'valueperpoint', 'minsalesamt', 'rounddown']
                );
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

