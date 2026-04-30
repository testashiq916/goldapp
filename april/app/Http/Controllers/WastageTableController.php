<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WastageTableController extends Controller
{
    use LogsDelpartAudit;

    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $types = collect();
        if ($this->hasTable('wstgtable')) {
            $types = DB::table('wstgtable')
                ->select('iqtype')
                ->whereNotNull('iqtype')
                ->where('iqtype', '!=', '')
                ->distinct()
                ->orderBy('iqtype')
                ->pluck('iqtype');
        }

        return view('wastage-table.index', [
            'types' => $types,
            'wstgTableMissing' => !$this->hasTable('wstgtable'),
            'itemsTableMissing' => !$this->hasTable('items'),
        ]);
    }

    public function validateItem(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('items')) {
            return response()->json(['success' => false, 'message' => '`items` table not found.']);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Item code is required.']);
        }

        $item = DB::table('items')
            ->select('name', 'wastage', 'mcharge')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->first();

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'This code does not exist']);
        }

        return response()->json([
            'success' => true,
            'name' => (string) ($item->name ?? ''),
        ]);
    }

    public function showRows(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('wstgtable')) {
            return response()->json(['success' => false, 'message' => '`wstgtable` table not found.']);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        $type = trim((string) $request->input('type', ''));

        $query = DB::table('wstgtable')
            ->select('weight1', 'weight2', 'wastage', 'perc');

        if ($code !== '') {
            $query->whereRaw('UPPER(TRIM(code)) = ?', [$code]);
        }
        if ($type !== '') {
            $query->where('iqtype', $type);
        }

        $rows = $query
            ->orderBy('weight1')
            ->orderBy('weight2')
            ->get();

        return response()->json([
            'success' => true,
            'rows' => $rows,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('wstgtable')) {
            return response()->json(['success' => false, 'message' => '`wstgtable` table not found.']);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        $type = trim((string) $request->input('type', ''));
        $rows = $request->input('rows', []);

        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Item code is required.']);
        }
        if (!is_array($rows)) {
            return response()->json(['success' => false, 'message' => 'Invalid rows payload.']);
        }

        DB::transaction(function () use ($code, $type, $rows): void {
            $deleteQuery = DB::table('wstgtable')
                ->whereRaw('UPPER(TRIM(code)) = ?', [$code]);
            if ($type !== '') {
                $deleteQuery->where('iqtype', $type);
            }
            $deleteQuery->delete();

            foreach ($rows as $row) {
                $w1 = (float) ($row['weight1'] ?? 0);
                $w2 = (float) ($row['weight2'] ?? 0);
                $wastage = (float) ($row['wastage'] ?? 0);
                $perc = (float) ($row['perc'] ?? 0);

                if ($w1 == 0.0 && $w2 == 0.0) {
                    continue;
                }

                DB::table('wstgtable')->insert([
                    'code' => $code,
                    'iqtype' => $type,
                    'weight1' => $w1,
                    'weight2' => $w2,
                    'wastage' => $wastage,
                    'perc' => $perc,
                ]);
            }
        });

        $this->logDelpart($request, 'Wastage(' . $code . ($type !== '' ? '/' . $type : '') . ') Saved', ['utype' => 'E', 'ttype' => 'R']);
        return response()->json(['success' => true, 'message' => 'Updated...']);
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
