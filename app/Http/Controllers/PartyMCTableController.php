<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PartyMCTable;
use App\Models\ProductModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PartyMCTableController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $pcode = strtoupper(trim((string) $request->query('pcode', '')));
        return view('partymctable.index', compact('pcode'));
    }

    public function getParty(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!$this->hasTable('clients')) {
            return response()->json(['success' => false, 'message' => 'clients table not found'], 500);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Code is required'], 400);
        }

        $q = DB::table('clients')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code]);
        $cols = array_map('strtolower', $this->columnList('clients'));
        if (in_array('removed', $cols, true)) {
            $q->where(function ($qq) {
                $qq->where('removed', '<>', 1)->orWhereNull('removed');
            });
        }
        $client = $q->first(['code', 'name']);

        // Fallback for legacy/messy codes if exact match fails.
        if (!$client) {
            $q2 = DB::table('clients')
                ->whereRaw('UPPER(TRIM(code)) LIKE ?', [$code . '%']);

            if (in_array('removed', $cols, true)) {
                $q2->where(function ($qq) {
                    $qq->where('removed', '<>', 1)->orWhereNull('removed');
                });
            }

            $client = $q2->orderBy('code')->first(['code', 'name']);
        }

        if (!$client) {
            return response()->json(['success' => false, 'message' => 'This code does not exist'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => ['code' => (string) $client->code, 'name' => (string) $client->name],
        ]);
    }

    public function getMCTable(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!$this->hasTable('pmctable')) {
            return response()->json(['success' => false, 'message' => 'pmctable not found'], 500);
        }

        $pcode = strtoupper(trim((string) $request->input('pcode', '')));
        return response()->json(['success' => true, 'data' => PartyMCTable::getByParty($pcode)]);
    }

    public function getItems(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!$this->hasTable('items')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $items = Item::query()->orderBy('name')->get(['code', 'name']);
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function getModels(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!$this->hasTable('models')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $type = strtoupper(trim((string) $request->input('type', 'M')));
        $models = ProductModel::where('mtype', $type)->orderBy('name')->get(['name']);
        return response()->json(['success' => true, 'data' => $models]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!$this->hasTable('pmctable')) {
            return response()->json(['success' => false, 'message' => 'pmctable not found'], 500);
        }

        $request->validate([
            'pcode' => 'required|string|max:10',
            'entries' => 'required|array',
            'entries.*.icode' => 'nullable|string|max:10',
            'entries.*.model' => 'nullable|string|max:15',
            'entries.*.wastage' => 'nullable|numeric',
            'entries.*.mc' => 'nullable|numeric',
            'entries.*.mcperc' => 'nullable|numeric',
            'entries.*.mcperqty' => 'nullable|numeric',
            'entries.*.touch' => 'nullable|numeric',
            'entries.*.formula' => 'nullable|string|max:15',
        ]);

        try {
            PartyMCTable::bulkUpdateForParty((string) $request->input('pcode'), $request->input('entries', []));
            return response()->json(['success' => true, 'message' => 'MC Table updated successfully']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error saving MC table: ' . $e->getMessage()], 500);
        }
    }

    public function deleteEntry(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate(['id' => 'required|integer']);
        try {
            PartyMCTable::findOrFail((int) $request->input('id'))->delete();
            return response()->json(['success' => true, 'message' => 'Entry deleted successfully']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error deleting entry: ' . $e->getMessage()], 500);
        }
    }

    public function getParties(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('clients')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $q = trim((string) $request->query('search', ''));
        $cols = array_map('strtolower', $this->columnList('clients'));

        $query = DB::table('clients')
            ->select('code', 'name')
            ->when($q !== '', function ($builder) use ($q) {
                $s = '%' . $q . '%';
                $builder->where(function ($x) use ($s) {
                    $x->whereRaw('TRIM(code) LIKE ?', [$s])->orWhereRaw('TRIM(name) LIKE ?', [$s]);
                });
            });

        if (in_array('removed', $cols, true)) {
            $query->where(function ($qq) {
                $qq->where('removed', '<>', 1)->orWhereNull('removed');
            });
        }

        $parties = $query->orderBy('name')->limit(200)->get();
        return response()->json(['success' => true, 'data' => $parties]);
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
