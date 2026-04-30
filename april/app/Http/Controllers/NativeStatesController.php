<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class NativeStatesController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        return view('native.states.index');
    }

    public function api(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $action = trim((string) $request->input('action', $request->query('action', '')));
        if ($action === '') {
            return response()->json(['success' => false, 'message' => 'No action specified'], 400);
        }

        try {
            return match ($action) {
                'retrieve' => $this->retrieveStates(),
                'save' => $this->saveStates($request),
                'delete' => $this->deleteState($request),
                'check_usage' => $this->checkUsage($request),
                default => response()->json(['success' => false, 'message' => 'Invalid action'], 400),
            };
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function retrieveStates(): JsonResponse
    {
        $meta = $this->resolveStateMeta();
        if (!$meta) {
            return response()->json(['success' => false, 'message' => 'State table not found']);
        }

        $rows = DB::table($meta['table'])
            ->selectRaw("{$meta['keyCol']} as code, {$meta['nameCol']} as name")
            ->orderBy($meta['keyCol'])
            ->get()
            ->map(fn ($r) => ['code' => (string) ($r->code ?? ''), 'name' => (string) ($r->name ?? '')])
            ->all();

        return response()->json(['success' => true, 'message' => 'States retrieved successfully', 'data' => $rows]);
    }

    private function saveStates(Request $request): JsonResponse
    {
        $meta = $this->resolveStateMeta();
        if (!$meta) {
            return response()->json(['success' => false, 'message' => 'State table not found']);
        }

        $raw = (string) $request->input('data', '');
        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            return response()->json(['success' => false, 'message' => 'Invalid data format'], 400);
        }

        $inserted = 0;
        $updated = 0;
        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                $name = trim((string) ($row['name'] ?? ''));
                if ($code === '' || $name === '') {
                    continue;
                }

                $exists = DB::table($meta['table'])->where($meta['keyCol'], $code)->exists();
                if ($exists) {
                    DB::table($meta['table'])
                        ->where($meta['keyCol'], $code)
                        ->update([$meta['nameCol'] => $name]);
                    $updated++;
                } else {
                    $insert = [$meta['keyCol'] => $code];
                    if ($meta['nameCol'] !== $meta['keyCol']) {
                        $insert[$meta['nameCol']] = $name;
                    }
                    DB::table($meta['table'])->insert($insert);
                    $inserted++;
                }
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error saving states: ' . $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => "Data saved successfully. {$inserted} inserted, {$updated} updated.",
            'data' => ['inserted' => $inserted, 'updated' => $updated],
        ]);
    }

    private function deleteState(Request $request): JsonResponse
    {
        $meta = $this->resolveStateMeta();
        if (!$meta) {
            return response()->json(['success' => false, 'message' => 'State table not found']);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'State code is required'], 400);
        }

        $usage = $this->usageInfo($code);
        if ($usage['in_use']) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete this state. It is referenced in {$usage['count']} sales record(s).",
                'in_use' => true,
                'data' => $usage,
            ]);
        }

        $deleted = DB::table($meta['table'])->where($meta['keyCol'], $code)->delete();
        if ($deleted < 1) {
            return response()->json(['success' => false, 'message' => 'State not found']);
        }

        return response()->json(['success' => true, 'message' => 'State deleted successfully', 'data' => ['deleted_count' => $deleted]]);
    }

    private function checkUsage(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'State code is required'], 400);
        }

        $usage = $this->usageInfo($code);
        return response()->json([
            'success' => true,
            'message' => 'Usage check completed',
            'in_use' => $usage['in_use'],
            'count' => $usage['count'],
            'data' => $usage,
        ]);
    }

    private function usageInfo(string $code): array
    {
        $count = 0;
        if ($this->hasTable('salesm')) {
            $columns = array_map('strtolower', $this->columnList('salesm'));
            foreach (['statecode', 'state', 'scode'] as $candidate) {
                if (in_array($candidate, $columns, true)) {
                    $count = (int) DB::table('salesm')->where($candidate, $code)->count();
                    break;
                }
            }
        }

        return ['in_use' => $count > 0, 'count' => $count];
    }

    private function resolveStateMeta(): ?array
    {
        if ($this->hasTable('state')) {
            $cols = array_map('strtolower', $this->columnList('state'));
            $keyCol = in_array('code', $cols, true) ? 'code' : (in_array('state', $cols, true) ? 'state' : null);
            $nameCol = in_array('name', $cols, true) ? 'name' : (in_array('state', $cols, true) ? 'state' : null);
            if ($keyCol && $nameCol) {
                return ['table' => 'state', 'keyCol' => $keyCol, 'nameCol' => $nameCol];
            }
        }

        if ($this->hasTable('state_master')) {
            $cols = array_map('strtolower', $this->columnList('state_master'));
            if (in_array('code', $cols, true) && in_array('name', $cols, true)) {
                return ['table' => 'state_master', 'keyCol' => 'code', 'nameCol' => 'name'];
            }
        }

        if ($this->hasTable('statestate')) {
            $cols = array_map('strtolower', $this->columnList('statestate'));
            $keyCol = in_array('code', $cols, true) ? 'code' : (in_array('state', $cols, true) ? 'state' : null);
            $nameCol = in_array('name', $cols, true) ? 'name' : (in_array('state', $cols, true) ? 'state' : null);
            if ($keyCol && $nameCol) {
                return ['table' => 'statestate', 'keyCol' => $keyCol, 'nameCol' => $nameCol];
            }
        }

        return null;
    }

    private function isAuthorized(Request $request): bool
    {
        if ((string) ($request->session()->get('user_code') ?? '') !== '') {
            return true;
        }

        return $this->hasLegacyUserSession();
    }

    private function hasLegacyUserSession(): bool
    {
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
        if ($raw === '') {
            return false;
        }

        return str_contains($raw, 'user_code|');
    }
}
