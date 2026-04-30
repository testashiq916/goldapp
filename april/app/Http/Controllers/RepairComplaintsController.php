<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RepairComplaintsController extends Controller
{
    use LogsDelpartAudit;

    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        return view('repair-complaints.index', [
            'repcomplMissing' => !$this->hasTable('repcompl'),
            'itemsMissing' => !$this->hasTable('items'),
        ]);
    }

    public function retrieve(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('repcompl')) {
            return response()->json(['success' => false, 'message' => '`repcompl` table not found.']);
        }

        $rows = DB::table('repcompl')
            ->select('part')
            ->orderBy('part')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('repcompl')) {
            return response()->json(['success' => false, 'message' => '`repcompl` table not found.']);
        }

        $rows = $request->input('rows', []);
        $deleted = $request->input('deleted', []);
        if (!is_array($rows) || !is_array($deleted)) {
            return response()->json(['success' => false, 'message' => 'Invalid data']);
        }

        DB::transaction(function () use ($rows, $deleted): void {
            foreach ($deleted as $part) {
                $part = trim((string) $part);
                if ($part === '') {
                    continue;
                }
                DB::table('repcompl')->where('part', $part)->delete();
            }

            foreach ($rows as $row) {
                $part = strtoupper(trim((string) ($row['part'] ?? '')));
                if ($part === '') {
                    continue;
                }

                DB::table('repcompl')->upsert(
                    [[
                        'part' => $part,
                    ]],
                    ['part'],
                    ['part']
                );
            }
        });

        $this->logDelpart($request, 'Repair Complaints Saved', ['utype' => 'E', 'ttype' => 'T']);
        return response()->json(['success' => true, 'message' => 'Saved successfully']);
    }

    public function lookupItem(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('items')) {
            return response()->json(['success' => true, 'data' => ['name' => '', 'mname' => '']]);
        }

        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            return response()->json(['success' => true, 'data' => ['name' => '', 'mname' => '']]);
        }

        $item = DB::table('items')
            ->selectRaw('name, regionalname AS mname')
            ->where('code', $code)
            ->first();

        if (!$item) {
            return response()->json(['success' => true, 'data' => ['name' => '', 'mname' => '']]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'name' => (string) ($item->name ?? ''),
                'mname' => (string) ($item->mname ?? ''),
            ],
        ]);
    }

    public function helpList(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('repcompl')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $rows = DB::table('repcompl')
            ->select('part')
            ->orderBy('part')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
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
