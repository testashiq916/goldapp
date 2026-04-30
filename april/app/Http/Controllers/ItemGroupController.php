<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ItemGroupController extends Controller
{
    use LogsDelpartAudit;

    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $mode = strtoupper((string) $request->query('mode', 'A'));
        if (!in_array($mode, ['A', 'E', 'D'], true)) {
            $mode = 'A';
        }

        $search = trim((string) $request->query('search', ''));
        $selectedCode = strtoupper(trim((string) $request->query('code', '')));
        $tableMissing = !$this->hasTable('itemgrp');

        $groups = collect();
        $groupData = null;

        if (!$tableMissing) {
            $groupsQuery = DB::table('itemgrp')
                ->select('code', 'name', 'mname', 'itype', 'orn', 'pos', 'showinstkrep')
                ->orderBy('name')
                ->orderBy('code');

            if ($search !== '') {
                $like = '%' . $search . '%';
                $groupsQuery->where(function ($q) use ($like): void {
                    $q->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('mname', 'like', $like);
                });
            }

            $groups = $groupsQuery->limit(500)->get();

            if ($selectedCode !== '') {
                $groupData = DB::table('itemgrp')
                    ->whereRaw('UPPER(TRIM(code)) = ?', [$selectedCode])
                    ->first();
            }
        }

        return view('groups.index', [
            'mode' => $mode,
            'search' => $search,
            'selectedCode' => $selectedCode,
            'groups' => $groups,
            'groupData' => $groupData,
            'tableMissing' => $tableMissing,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        if (!$this->hasTable('itemgrp')) {
            return redirect('/groups')->with('error', '`itemgrp` table not found.');
        }

        $mode = strtoupper((string) $request->input('mode', 'A'));
        if (!in_array($mode, ['A', 'E', 'D'], true)) {
            return redirect('/groups')->with('error', 'Invalid operation mode.');
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return redirect('/groups')->with('error', 'Group code is required.');
        }

        try {
            if ($mode === 'D') {
                if ($this->hasTable('items') && Schema::hasColumn('items', 'grpcode')) {
                    $count = (int) DB::table('items')
                        ->whereRaw('UPPER(TRIM(grpcode)) = ?', [$code])
                        ->count();
                    if ($count > 0) {
                        return redirect('/groups?mode=D&code=' . urlencode($code))
                            ->with('error', 'Cannot delete: items exist in this group.');
                    }
                }

                $deleted = DB::table('itemgrp')
                    ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                    ->delete();

                if ($deleted < 1) {
                    return redirect('/groups?mode=D&code=' . urlencode($code))
                        ->with('error', 'Group not found.');
                }

                $this->logDelpart($request, 'Item Group(' . $code . ') Deleted', ['utype' => 'D', 'ttype' => 'R']);
                return redirect('/groups?mode=A')->with('success', 'Group deleted successfully.');
            }

            $name = trim((string) $request->input('name', ''));
            if ($name === '') {
                return redirect('/groups?mode=' . $mode . '&code=' . urlencode($code))
                    ->with('error', 'Group name is required.');
            }

            $payload = [
                'name' => $name,
                'mname' => trim((string) $request->input('mname', '')),
                'itype' => strtoupper((string) $request->input('type', 'G')),
                'orn' => $request->boolean('ornament') ? 'Y' : 'N',
                'pos' => (int) $request->input('position', 0),
                'showinstkrep' => $request->boolean('show_in_stock') ? 'Y' : 'N',
            ];

            if (!in_array($payload['itype'], ['G', 'S', 'P', 'O'], true)) {
                $payload['itype'] = 'G';
            }

            if ($mode === 'A') {
                $exists = DB::table('itemgrp')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->exists();
                if ($exists) {
                    return redirect('/groups?mode=A&code=' . urlencode($code))
                        ->with('error', 'Code already exists.');
                }

                $payload['code'] = $code;
                DB::table('itemgrp')->insert($payload);
                $this->logDelpart($request, 'Item Group(' . $code . ') Added', ['utype' => 'A', 'ttype' => 'R']);
                return redirect('/groups?mode=A')->with('success', 'Group added successfully.');
            }

            $updated = DB::table('itemgrp')
                ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                ->update($payload);

            if ($updated < 1) {
                return redirect('/groups?mode=E&code=' . urlencode($code))
                    ->with('error', 'Group not found.');
            }

            $this->logDelpart($request, 'Item Group(' . $code . ') Updated', ['utype' => 'E', 'ttype' => 'R']);
            return redirect('/groups?mode=E&code=' . urlencode($code))
                ->with('success', 'Group updated successfully.');
        } catch (\Throwable $e) {
            return redirect('/groups?mode=' . $mode . '&code=' . urlencode($code))
                ->with('error', 'Error: ' . $e->getMessage());
        }
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
