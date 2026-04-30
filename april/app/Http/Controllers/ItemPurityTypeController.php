<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ItemPurityTypeController extends Controller
{
    use LogsDelpartAudit;

    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $tableMissing = !$this->hasTable('itemsqtype');
        $records = collect();

        if (!$tableMissing) {
            $records = DB::table('itemsqtype')
                ->select('code', 'touch')
                ->orderBy('code')
                ->get();
        }

        return view('purity-type.index', [
            'tableMissing' => $tableMissing,
            'records' => $records,
        ]);
    }

    public function checkDelete(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['can_delete' => false, 'usage_count' => 0], 401);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['can_delete' => false, 'usage_count' => 0], 400);
        }

        $usageCount = $this->checkCodeUsage($code);
        return response()->json([
            'can_delete' => $usageCount === 0,
            'usage_count' => $usageCount,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        if (!$this->hasTable('itemsqtype')) {
            return redirect('/purity-type')->with('error', '`itemsqtype` table not found.');
        }

        $codes = $request->input('code', []);
        $touches = $request->input('touch', []);
        $originalCodes = $request->input('original_code', []);
        $deletedRowsJson = (string) $request->input('deleted_rows', '[]');

        if (!is_array($codes) || !is_array($touches) || !is_array($originalCodes)) {
            return redirect('/purity-type')->with('error', 'Invalid form payload.');
        }

        try {
            DB::transaction(function () use ($codes, $touches, $originalCodes, $deletedRowsJson): void {
                for ($i = 0; $i < count($codes); $i++) {
                    $code = strtoupper(trim((string) ($codes[$i] ?? '')));
                    $touch = $this->normalizeTouch($touches[$i] ?? 0);
                    $originalCode = strtoupper(trim((string) ($originalCodes[$i] ?? $code)));

                    if ($code === '') {
                        continue;
                    }

                    $existing = DB::table('itemsqtype')
                        ->whereRaw('UPPER(TRIM(code)) = ?', [$originalCode])
                        ->first();

                    if ($existing) {
                        if ($code !== $originalCode) {
                            $dup = DB::table('itemsqtype')
                                ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                                ->exists();
                            if ($dup) {
                                throw new \RuntimeException('Purity code "' . $code . '" already exists.');
                            }
                        }

                        DB::table('itemsqtype')
                            ->whereRaw('UPPER(TRIM(code)) = ?', [$originalCode])
                            ->update([
                                'code' => $code,
                                'touch' => $touch,
                            ]);

                        if ($code !== $originalCode) {
                            $this->updateRelatedTables($originalCode, $code);
                        }
                    } else {
                        $dup = DB::table('itemsqtype')
                            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                            ->exists();
                        if ($dup) {
                            DB::table('itemsqtype')
                                ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                                ->update(['touch' => $touch]);
                        } else {
                            DB::table('itemsqtype')->insert([
                                'code' => $code,
                                'touch' => $touch,
                            ]);
                        }
                    }
                }

                $deletedCodes = json_decode($deletedRowsJson, true);
                if (is_array($deletedCodes)) {
                    foreach ($deletedCodes as $rawCode) {
                        $code = strtoupper(trim((string) $rawCode));
                        if ($code === '') {
                            continue;
                        }
                        $this->deleteRecord($code);
                    }
                }
            });
        } catch (\Throwable $e) {
            return redirect('/purity-type')->with('error', 'Error: ' . $e->getMessage());
        }

        $this->logDelpart($request, 'Purity Types Saved', ['utype' => 'E', 'ttype' => 'R']);
        return redirect('/purity-type')->with('success', 'Records saved successfully.');
    }

    private function deleteRecord(string $code): void
    {
        $usageCount = $this->checkCodeUsage($code);
        if ($usageCount > 0) {
            throw new \RuntimeException('Cannot delete "' . $code . '" - code is in use in ' . $usageCount . ' record(s).');
        }

        DB::table('itemsqtype')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->delete();
    }

    private function updateRelatedTables(string $oldCode, string $newCode): void
    {
        $tables = [
            'barcode' => 'qtype',
            'items' => 'defquality',
            'mctable' => 'iqtype',
            'orderd' => 'iqtype',
            'orderdga' => 'iqtype',
            'purchased' => 'iqtype',
            'salesd' => 'iqtype',
            'salesrd' => 'iqtype',
            'wstgtable' => 'iqtype',
        ];

        foreach ($tables as $table => $field) {
            if (!$this->hasTable($table) || !Schema::hasColumn($table, $field)) {
                continue;
            }
            DB::table($table)
                ->whereRaw('UPPER(TRIM(' . $field . ')) = ?', [$oldCode])
                ->update([$field => $newCode]);
        }
    }

    private function checkCodeUsage(string $code): int
    {
        $total = 0;
        $tables = [
            'items' => 'defquality',
            'salesd' => 'iqtype',
            'purchased' => 'iqtype',
            'salesrd' => 'iqtype',
            'mctable' => 'iqtype',
            'barcode' => 'qtype',
            'orderd' => 'iqtype',
            'orderdga' => 'iqtype',
            'wstgtable' => 'iqtype',
        ];

        foreach ($tables as $table => $field) {
            if (!$this->hasTable($table) || !Schema::hasColumn($table, $field)) {
                continue;
            }
            $total += (int) DB::table($table)
                ->whereRaw('UPPER(TRIM(' . $field . ')) = ?', [$code])
                ->count();
        }

        return $total;
    }

    private function normalizeTouch(mixed $value): float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        // Allow only one decimal point and digits.
        $raw = str_replace(',', '.', $raw);
        $raw = preg_replace('/[^0-9.]/', '', $raw) ?? '0';
        $parts = explode('.', $raw);
        if (count($parts) > 2) {
            $raw = $parts[0] . '.' . implode('', array_slice($parts, 1));
        }

        return (float) $raw;
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
