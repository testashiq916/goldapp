<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\MCTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MCTableController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }
        return view('mctable.index');
    }

    public function getItem(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Code is required'], 400);
        }

        if (!$this->hasTable('items')) {
            return response()->json(['success' => false, 'message' => 'items table not found'], 500);
        }

        $item = DB::table('items')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->first();

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'This code does not exist'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'code' => (string) ($item->code ?? ''),
                'name' => (string) ($item->name ?? ''),
                'wastage' => (float) ($item->wastage ?? 0),
                'mcharge' => (float) ($item->mcharge ?? 0),
            ],
        ]);
    }

    public function getMCTable(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!$this->hasTable('mctable')) {
            return response()->json(['success' => false, 'message' => 'mctable not found'], 500);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        $iqtype = trim((string) $request->input('iqtype', ''));

        return response()->json([
            'success' => true,
            'data' => MCTable::getByItemAndType($code, $iqtype),
        ]);
    }

    public function getItemTypes(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => true, 'data' => []]);
        }

        if (!$this->hasTable('itemqtype')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $cols = array_map('strtolower', $this->columnList('itemqtype'));
        $codeCol = in_array('code', $cols, true) ? 'code' : (in_array('icode', $cols, true) ? 'icode' : 'code');
        $qcodeCol = in_array('qcode', $cols, true) ? 'qcode' : (in_array('iqtype', $cols, true) ? 'iqtype' : null);
        $qdescCol = in_array('qdesc', $cols, true) ? 'qdesc' : (in_array('name', $cols, true) ? 'name' : null);

        if ($qcodeCol === null) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $query = DB::table('itemqtype')->whereRaw('UPPER(TRIM(' . $codeCol . ')) = ?', [$code]);
        $rows = $query->orderBy($qcodeCol)->get();

        $data = $rows->map(function ($row) use ($qcodeCol, $qdescCol) {
            $qcode = (string) ($row->{$qcodeCol} ?? '');
            $qdesc = $qdescCol ? (string) ($row->{$qdescCol} ?? '') : $qcode;
            return ['qcode' => $qcode, 'qdesc' => $qdesc];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getItemsList(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!$this->hasTable('items')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $search = trim((string) $request->query('search', ''));
        $query = Item::query()->select('code', 'name');

        if ($search !== '') {
            $s = '%' . $search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('code', 'like', $s)->orWhere('name', 'like', $s);
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->limit(300)->get(),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'code' => 'required|string|max:10',
            'iqtype' => 'nullable|string|max:10',
            'entries' => 'required|array',
            'entries.*.weight1' => 'nullable|numeric',
            'entries.*.weight2' => 'nullable|numeric',
            'entries.*.mc' => 'nullable|numeric',
            'entries.*.mcpergm' => 'nullable|numeric',
            'entries.*.mcperqty' => 'nullable|numeric',
            'entries.*.vaperc' => 'nullable|numeric',
        ]);

        try {
            MCTable::bulkUpdateForItem(
                strtoupper(trim((string) $request->input('code'))),
                trim((string) $request->input('iqtype', '')),
                $request->input('entries', [])
            );

            return response()->json(['success' => true, 'message' => 'MC Table updated successfully']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error saving MC table: ' . $e->getMessage()], 500);
        }
    }

    public function deleteAll(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate(['code' => 'required|string|max:10']);
        try {
            MCTable::deleteAllForItem((string) $request->input('code'));
            return response()->json(['success' => true, 'message' => 'All MC table entries deleted for this item']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error deleting entries: ' . $e->getMessage()], 500);
        }
    }

    public function findMCForWeight(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'code' => 'required|string|max:10',
            'iqtype' => 'nullable|string|max:10',
            'weight' => 'required|numeric|min:0',
        ]);

        $data = MCTable::findMCForWeight(
            (string) $request->input('code'),
            (string) $request->input('iqtype', ''),
            (float) $request->input('weight')
        );

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'No MC rate found for this weight range'], 404);
        }

        return response()->json(['success' => true, 'data' => $data]);
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

