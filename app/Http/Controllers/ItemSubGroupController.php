<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ItemSubGroupController extends Controller
{
    use LogsDelpartAudit;

    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $tableMissing = !$this->hasTable('itemsubgrp');
        $subgroups = collect();

        if (!$tableMissing) {
            $subgroups = DB::table('itemsubgrp')
                ->select('code', 'name')
                ->orderBy('code')
                ->limit(1000)
                ->get();
        }

        return view('subgroups.index', [
            'tableMissing' => $tableMissing,
            'subgroups' => $subgroups,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        if (!$this->hasTable('itemsubgrp')) {
            return redirect('/sub-groups')->with('error', '`itemsubgrp` table not found.');
        }

        $codes = $request->input('code', []);
        $names = $request->input('name', []);

        if (!is_array($codes) || !is_array($names)) {
            return redirect('/sub-groups')->with('error', 'Invalid form payload.');
        }

        $processed = 0;

        try {
            DB::transaction(function () use ($codes, $names, &$processed): void {
                foreach ($codes as $idx => $rawCode) {
                    $code = strtoupper(trim((string) $rawCode));
                    $name = trim((string) ($names[$idx] ?? ''));

                    if ($code === '') {
                        continue;
                    }

                    $exists = DB::table('itemsubgrp')
                        ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                        ->exists();

                    if ($exists) {
                        DB::table('itemsubgrp')
                            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                            ->update(['name' => $name]);
                    } else {
                        DB::table('itemsubgrp')->insert([
                            'code' => $code,
                            'name' => $name,
                        ]);
                    }

                    $processed++;
                }
            });
        } catch (\Throwable $e) {
            return redirect('/sub-groups')->with('error', 'Error saving records: ' . $e->getMessage());
        }

        $this->logDelpart($request, 'Item Sub Groups Saved', ['utype' => 'E', 'ttype' => 'R']);
        return redirect('/sub-groups')->with('success', 'Saved ' . $processed . ' record(s).');
    }

    public function delete(Request $request): RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        if (!$this->hasTable('itemsubgrp')) {
            return redirect('/sub-groups')->with('error', '`itemsubgrp` table not found.');
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return redirect('/sub-groups')->with('error', 'Code is required for delete.');
        }

        try {
            if ($this->hasTable('barcode') && Schema::hasColumn('barcode', 'counter')) {
                $inUse = (int) DB::table('barcode')
                    ->whereRaw('UPPER(TRIM(counter)) = ?', [$code])
                    ->count();
                if ($inUse > 0) {
                    return redirect('/sub-groups')->with('error', 'Cannot delete: used in barcode table.');
                }
            }

            $deleted = DB::table('itemsubgrp')
                ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                ->delete();

            if ($deleted < 1) {
                return redirect('/sub-groups')->with('error', 'Sub group not found.');
            }
        } catch (\Throwable $e) {
            return redirect('/sub-groups')->with('error', 'Delete failed: ' . $e->getMessage());
        }

        $this->logDelpart($request, 'Item Sub Group(' . $code . ') Deleted', ['utype' => 'D', 'ttype' => 'R']);
        return redirect('/sub-groups')->with('success', 'Sub group deleted successfully.');
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
