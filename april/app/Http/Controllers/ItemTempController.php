<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ItemTempController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $tableMissing = !$this->hasTable('itemstmp');
        $records = collect();
        if (!$tableMissing) {
            $records = DB::table('itemstmp')
                ->select('code', 'name', 'mname', 'iqtype')
                ->orderBy('code')
                ->orderBy('name')
                ->limit(1000)
                ->get();
        }

        return view('item-temp.index', [
            'tableMissing' => $tableMissing,
            'records' => $records,
            'qtypes' => $this->qualityTypeOptions(),
        ]);
    }

    public function itemDetails(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['name' => '', 'mname' => ''], 401);
        }
        if (!$this->hasTable('items')) {
            return response()->json(['name' => '', 'mname' => '']);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['name' => '', 'mname' => '']);
        }

        $row = DB::table('items')
            ->select('name', DB::raw('regionalname as mname'))
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->first();

        if (!$row) {
            return response()->json(['name' => '', 'mname' => '']);
        }

        return response()->json([
            'name' => (string) ($row->name ?? ''),
            'mname' => (string) ($row->mname ?? ''),
        ]);
    }

    public function checkDuplicate(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['is_duplicate' => false], 401);
        }
        if (!$this->hasTable('itemstmp')) {
            return response()->json(['is_duplicate' => false]);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));

        if ($code === '' || $name === '') {
            return response()->json(['is_duplicate' => false]);
        }

        $exists = DB::table('itemstmp')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->whereRaw('TRIM(name) = ?', [$name])
            ->exists();

        return response()->json(['is_duplicate' => $exists]);
    }

    public function checkDelete(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['in_use' => false, 'count' => 0], 401);
        }
        if (!$this->hasTable('orderm') || !Schema::hasColumn('orderm', 'smcode')) {
            return response()->json(['in_use' => false, 'count' => 0]);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['in_use' => false, 'count' => 0]);
        }

        $count = (int) DB::table('orderm')
            ->whereRaw('UPPER(TRIM(smcode)) = ?', [$code])
            ->count();

        return response()->json(['in_use' => $count > 0, 'count' => $count]);
    }

    public function save(Request $request): RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }
        if (!$this->hasTable('itemstmp')) {
            return redirect('/item-temp')->with('error', '`itemstmp` table not found.');
        }

        $codes = $request->input('code', []);
        $names = $request->input('name', []);
        $mnames = $request->input('mname', []);
        $iqtypes = $request->input('iqtype', []);
        $originalCodes = $request->input('original_code', []);
        $originalNames = $request->input('original_name', []);

        if (!is_array($codes) || !is_array($names) || !is_array($mnames) || !is_array($iqtypes)) {
            return redirect('/item-temp')->with('error', 'Invalid form payload.');
        }

        try {
            DB::transaction(function () use ($codes, $names, $mnames, $iqtypes, $originalCodes, $originalNames): void {
                $seen = [];
                for ($i = 0; $i < count($codes); $i++) {
                    $code = strtoupper(trim((string) ($codes[$i] ?? '')));
                    $name = trim((string) ($names[$i] ?? ''));
                    if ($code === '' || $name === '') {
                        continue;
                    }

                    $key = $code . '|' . mb_strtoupper($name);
                    if (isset($seen[$key])) {
                        throw new \RuntimeException('Duplicate entry in form: ' . $code . ' / ' . $name);
                    }
                    $seen[$key] = true;

                    $mname = trim((string) ($mnames[$i] ?? ''));
                    $iqtype = trim((string) ($iqtypes[$i] ?? ''));
                    $origCode = strtoupper(trim((string) ($originalCodes[$i] ?? '')));
                    $origName = trim((string) ($originalNames[$i] ?? ''));

                    if ($origCode !== '' && $origName !== '') {
                        // Updating an existing row.
                        $rowExists = DB::table('itemstmp')
                            ->whereRaw('UPPER(TRIM(code)) = ?', [$origCode])
                            ->whereRaw('TRIM(name) = ?', [$origName])
                            ->exists();
                        if ($rowExists) {
                            if (($code !== $origCode || $name !== $origName) && $this->usageCount($origCode) > 0) {
                                throw new \RuntimeException('Cannot modify code "' . $origCode . '" because it is in use.');
                            }
                            DB::table('itemstmp')
                                ->whereRaw('UPPER(TRIM(code)) = ?', [$origCode])
                                ->whereRaw('TRIM(name) = ?', [$origName])
                                ->update([
                                    'code' => $code,
                                    'name' => $name,
                                    'mname' => $mname,
                                    'iqtype' => $iqtype,
                                ]);
                            continue;
                        }
                    }

                    $dup = DB::table('itemstmp')
                        ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                        ->whereRaw('TRIM(name) = ?', [$name])
                        ->exists();
                    if ($dup) {
                        DB::table('itemstmp')
                            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                            ->whereRaw('TRIM(name) = ?', [$name])
                            ->update([
                                'mname' => $mname,
                                'iqtype' => $iqtype,
                            ]);
                    } else {
                        DB::table('itemstmp')->insert([
                            'code' => $code,
                            'name' => $name,
                            'mname' => $mname,
                            'iqtype' => $iqtype,
                        ]);
                    }
                }
            });
        } catch (\Throwable $e) {
            return redirect('/item-temp')->with('error', 'Error: ' . $e->getMessage());
        }

        return redirect('/item-temp')->with('success', 'Records saved successfully.');
    }

    public function delete(Request $request): RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }
        if (!$this->hasTable('itemstmp')) {
            return redirect('/item-temp')->with('error', '`itemstmp` table not found.');
        }

        $code = strtoupper(trim((string) $request->input('delete_code', '')));
        $name = trim((string) $request->input('delete_name', ''));
        if ($code === '' || $name === '') {
            return redirect('/item-temp')->with('error', 'Code and Name are required.');
        }

        $count = $this->usageCount($code);
        if ($count > 0) {
            return redirect('/item-temp')->with('error', 'Cannot delete - this item is used in ' . $count . ' order(s).');
        }

        DB::table('itemstmp')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->whereRaw('TRIM(name) = ?', [$name])
            ->delete();

        return redirect('/item-temp')->with('success', 'Record deleted successfully.');
    }

    private function usageCount(string $code): int
    {
        if (!$this->hasTable('orderm') || !Schema::hasColumn('orderm', 'smcode')) {
            return 0;
        }

        return (int) DB::table('orderm')
            ->whereRaw('UPPER(TRIM(smcode)) = ?', [$code])
            ->count();
    }

    private function qualityTypeOptions(): array
    {
        foreach (['itemsqtype', 'qtype'] as $table) {
            if (!$this->hasTable($table) || !Schema::hasColumn($table, 'code')) {
                continue;
            }
            $rows = DB::table($table)
                ->select('code')
                ->orderBy('code')
                ->limit(500)
                ->get()
                ->map(fn ($r) => (string) $r->code)
                ->values()
                ->toArray();
            if (!empty($rows)) {
                return $rows;
            }
        }

        return [];
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

