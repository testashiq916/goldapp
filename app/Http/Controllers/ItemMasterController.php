<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ItemMasterController extends Controller
{
    use LogsDelpartAudit;

    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        return view('items.master');
    }

    public function options(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'groups' => $this->safeLookup('itemgrp'),
                'subgroups' => $this->safeLookup('itemsubgrp'),
                'stocktypes' => $this->safeLookup('stktype'),
                'qualitytypes' => $this->qualityTypeLookup(),
                'billtypes' => $this->billTypeLookup(),
                'smiths' => $this->smithLookup(),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('items')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $search = trim((string) $request->query('search', ''));
        $q = DB::table('items')->select('code', 'name', 'itype');
        if ($search !== '') {
            $s = '%' . $search . '%';
            $q->where(function ($w) use ($s): void {
                $w->where('code', 'like', $s)
                    ->orWhere('name', 'like', $s)
                    ->orWhere('regionalname', 'like', $s);
            });
        }

        return response()->json(['success' => true, 'data' => $q->orderBy('code')->limit(100)->get()]);
    }

    public function getItem(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('items')) {
            return response()->json(['success' => false, 'message' => 'items table not found'], 500);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Code is required'], 400);
        }

        $item = DB::table('items')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->first();
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'This item does not exist'], 404);
        }

        return response()->json(['success' => true, 'data' => $item]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('items')) {
            return response()->json(['success' => false, 'message' => 'items table not found'], 500);
        }

        $request->validate([
            'mode' => 'required|string|in:add,edit',
            'code' => 'required|string|max:10',
            'desc' => 'required|string|max:50',
        ]);

        $mode = (string) $request->input('mode');
        $code = strtoupper(trim((string) $request->input('code')));
        $codeLocked = $request->boolean('code_locked');

        try {
            $exists = DB::table('items')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->exists();
            if ($mode === 'add' && $exists && $codeLocked) {
                $mode = 'edit';
            }
            if ($mode === 'add' && $exists) {
                return response()->json(['success' => false, 'message' => 'This item already exists'], 422);
            }
            if ($mode === 'edit' && !$exists) {
                return response()->json(['success' => false, 'message' => 'This item does not exist'], 404);
            }

            $allCols = array_map('strtolower', $this->columnList('items'));
            $payload = $this->payloadFromRequest($request, $allCols, $mode === 'add');

            DB::transaction(function () use ($mode, $code, $payload): void {
                if ($mode === 'add') {
                    DB::table('items')->insert($payload);
                } else {
                    DB::table('items')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->update($payload);
                }
            });

            $this->logDelpart($request, 'Item(' . $code . ') ' . ($mode === 'add' ? 'Added' : 'Updated'), ['utype' => $mode === 'add' ? 'A' : 'E', 'ttype' => 'R']);
            return response()->json(['success' => true, 'message' => $mode === 'add' ? 'Item added successfully' : 'Item updated successfully']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error saving item: ' . $e->getMessage()], 500);
        }
    }

    public function delete(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('items')) {
            return response()->json(['success' => false, 'message' => 'items table not found'], 500);
        }

        $request->validate(['code' => 'required|string|max:10']);
        $code = strtoupper(trim((string) $request->input('code')));

        try {
            $item = DB::table('items')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->first();
            if (!$item) {
                return response()->json(['success' => false, 'message' => 'This item does not exist'], 404);
            }

            if (isset($item->reserve) && strtoupper((string) $item->reserve) === 'Y') {
                return response()->json(['success' => false, 'message' => 'This item is reserved. You cannot delete it'], 422);
            }

            if (!$this->canDeleteItem($code)) {
                return response()->json(['success' => false, 'message' => 'Some entries exist with this item. You cannot delete this item'], 422);
            }

            DB::table('items')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->delete();
            $this->logDelpart($request, 'Item(' . $code . ') Deleted', ['utype' => 'D', 'ttype' => 'R']);
            return response()->json(['success' => true, 'message' => 'Item deleted successfully']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error deleting item: ' . $e->getMessage()], 500);
        }
    }

    public function codeExists(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('items')) {
            return response()->json(['success' => false, 'message' => 'items table not found'], 500);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => true, 'exists' => false]);
        }

        $item = DB::table('items')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->first(['code', 'name']);
        return response()->json([
            'success' => true,
            'exists' => (bool) $item,
            'code' => $item ? trim((string) $item->code) : null,
            'name' => $item ? trim((string) $item->name) : null,
            'item' => $item ? ['code' => trim((string) $item->code), 'name' => trim((string) $item->name)] : null,
        ]);
    }

    public function renameCode(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('items')) {
            return response()->json(['success' => false, 'message' => 'items table not found'], 500);
        }

        $oldCode = strtoupper(trim((string) $request->input('old_code', '')));
        $newCode = strtoupper(trim((string) $request->input('new_code', '')));
        $mergeExisting = $request->boolean('merge_existing');

        if ($oldCode === '' || $newCode === '') {
            return response()->json(['success' => false, 'message' => 'Old code and new code are required'], 422);
        }
        if (mb_strlen($oldCode) > 10 || mb_strlen($newCode) > 10) {
            return response()->json(['success' => false, 'message' => 'Item code maximum length is 10'], 422);
        }
        if ($oldCode === $newCode) {
            return response()->json(['success' => false, 'message' => 'Old code and new code are same'], 422);
        }

        try {
            $result = DB::transaction(function () use ($request, $oldCode, $newCode, $mergeExisting): array {
                $oldExists = DB::table('items')->whereRaw('UPPER(TRIM(code)) = ?', [$oldCode])->exists();
                if (!$oldExists) {
                    return ['success' => false, 'message' => 'Invalid old item code'];
                }

                $newExists = DB::table('items')->whereRaw('UPPER(TRIM(code)) = ?', [$newCode])->exists();
                if ($newExists && !$mergeExisting) {
                    return ['success' => false, 'message' => 'New item code already exists', 'exists' => true];
                }

                $counts = [];
                if ($newExists) {
                    $counts['items_deleted'] = DB::table('items')->whereRaw('UPPER(TRIM(code)) = ?', [$oldCode])->delete();
                } else {
                    $counts['items'] = DB::table('items')->whereRaw('UPPER(TRIM(code)) = ?', [$oldCode])->update(['code' => $newCode]);
                }

                foreach ($this->itemRenameReferences() as $table => $columns) {
                    if (!$this->hasTable($table)) {
                        continue;
                    }
                    $tableCols = array_map('strtolower', $this->columnList($table));
                    foreach ($columns as $column) {
                        if (!in_array(strtolower($column), $tableCols, true)) {
                            continue;
                        }
                        $affected = DB::table($table)
                            ->whereRaw('UPPER(TRIM(' . $column . ')) = ?', [$oldCode])
                            ->update([$column => $newCode]);
                        if ($affected > 0) {
                            $counts[$table . '.' . $column] = $affected;
                        }
                    }
                }

                $this->logDelpart($request, 'Item Code Renamed ' . $oldCode . ' to ' . $newCode, ['utype' => 'E', 'ttype' => 'R']);

                return [
                    'success' => true,
                    'message' => 'Item renamed successfully',
                    'old_code' => $oldCode,
                    'new_code' => $newCode,
                    'merged' => $newExists,
                    'counts' => $counts,
                ];
            });

            return response()->json($result, ($result['success'] ?? false) ? 200 : (($result['exists'] ?? false) ? 409 : 422));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error renaming item: ' . $e->getMessage()], 500);
        }
    }

    private function payloadFromRequest(Request $request, array $columns, bool $forAdd): array
    {
        $has = fn (string $c): bool => in_array(strtolower($c), $columns, true);
        $set = [];

        $map = [
            'code' => strtoupper(trim((string) $request->input('code', ''))),
            'name' => strtoupper(trim((string) $request->input('desc', ''))),
            'regionalname' => trim((string) $request->input('regional', '')),
            'grpcode' => trim((string) $request->input('grpcode', '')),
            'subgrpcode' => trim((string) $request->input('subgrpcode', '')),
            'itype' => trim((string) $request->input('itype', 'G')),
            'wastage' => (float) $request->input('wastage', 0),
            'mcharge' => (float) $request->input('mcrate', 0),
            'opcost' => (float) $request->input('opcost', 0),
            'cost' => (float) $request->input('cost', 0),
            'vaperc' => (float) $request->input('vaperc', 0),
            'vaperqty' => (float) $request->input('vaperqty', 0),
            'ornament' => $request->boolean('ornament') ? 'Y' : 'N',
            'touch' => (float) $request->input('touch', 0),
            'rollower' => (float) $request->input('rollower', 0),
            'rolupper' => (float) $request->input('rolupper', 0),
            'rollowerqty' => (int) $request->input('rollowerqty', 0),
            'rolupperqty' => (int) $request->input('rolupperqty', 0),
            'defqty' => (int) $request->input('defqty', 0),
            'code2' => strtoupper(trim((string) $request->input('frcode', ''))),
            'defstktype' => trim((string) $request->input('stktype', '')),
            'defquality' => trim((string) $request->input('qtype', '')),
            'qtype' => trim((string) $request->input('qtype', '')),
            'defsmith' => trim((string) $request->input('smith', '')),
            'footer_e' => trim((string) $request->input('footer_e', '')),
            'footer_m' => trim((string) $request->input('footer_m', '')),
            'stonemarg' => (float) $request->input('stonemarg', 0),
            'rate' => (float) $request->input('rate', 0),
            'wsrate' => (float) $request->input('wsrate', 0),
            'smithmc' => (float) $request->input('smithmc', 0),
            'jewlmc' => (float) $request->input('jewlmc', 0),
            'shedule' => strtoupper(trim((string) $request->input('shedule', ''))),
            'vatcode' => strtoupper(trim((string) $request->input('vatcode', ''))),
            'stktouch' => (float) $request->input('stktouch', 100),
            'dmdplt' => trim((string) $request->input('dmdplt', '')),
            'jewltouch' => (float) $request->input('jewltouch', 0),
            'prate' => (float) $request->input('prate', 0),
            'saccode' => strtoupper(trim((string) $request->input('saccode', ''))),
            'billtype' => trim((string) $request->input('billtype', '')),
            'stickerwgt' => (float) $request->input('stickerwgt', 0),
            'minvap' => (float) $request->input('minvap', 0),
            'stkinnos' => $request->boolean('stkinnos') ? 'Y' : 'N',
            'reserve' => $request->boolean('reserve') ? 'Y' : ' ',
            'disabled' => $request->boolean('disable') ? 1 : 0,
            'showinstkrep' => $request->boolean('dontshowinstkrep') ? 'N' : 'Y',
            'taxable' => $request->boolean('taxable') ? 'Y' : 'N',
            'taxinternal' => $request->boolean('taxinternal') ? 'Y' : 'N',
            'cessinternal' => $request->boolean('cessinternal') ? 'Y' : 'N',
            'printvaamt' => $request->boolean('printvaamt') ? 'Y' : 'N',
            'bccompulsory' => $request->boolean('bccompulsory') ? 'Y' : 'N',
            'nodisc' => $request->boolean('nodisc') ? 'N' : '',
            'stonemust' => $request->boolean('stonemust') ? 'Y' : 'N',
            'vaoffer' => $request->boolean('vaoffer') ? 'Y' : 'N',
        ];

        foreach ($map as $col => $val) {
            if ($has($col)) {
                $set[$col] = $val;
            }
        }

        if ($forAdd) {
            foreach ([
                'qty', 'weight', 'stonewgt', 'qtyb', 'weightb', 'stonewgtb',
                'opqty', 'opweight', 'opstonewgt', 'opqtyb', 'opweightb', 'opstonewgtb',
            ] as $zeroCol) {
                if ($has($zeroCol) && !array_key_exists($zeroCol, $set)) {
                    $set[$zeroCol] = 0;
                }
            }
        }

        return $set;
    }

    private function itemRenameReferences(): array
    {
        return [
            'itemadj' => ['fromcode', 'tocode'],
            'itemsstk' => ['code'],
            'orderd' => ['code'],
            'orderdmodel' => ['code'],
            'orderdga' => ['code'],
            'salesd' => ['code'],
            'salesrd' => ['code'],
            'purchased' => ['code'],
            'purchaserd' => ['code'],
            'refineryd' => ['code'],
            'repaird' => ['code'],
            'smithd' => ['code'],
            'smithnewwrk' => ['code'],
            'smithsusp' => ['code'],
            'wstgtable' => ['code'],
            'mctable' => ['code'],
            'barcode' => ['icode'],
            'itemadjverify' => ['code'],
            'itemstmp' => ['code'],
            'modelm' => ['icode'],
        ];
    }

    private function canDeleteItem(string $code): bool
    {
        $total = 0.0;
        $tables = [
            ['salesd', 'weight'],
            ['salesrd', 'weight'],
            ['purchased', 'weight'],
            ['purchaserd', 'weight'],
            ['orderd', 'weight'],
            ['repaird', 'weight'],
            ['smithd', 'weight'],
            ['refineryd', 'issuedwgt'],
            ['refineryd', 'rcvdwgt'],
        ];

        foreach ($tables as [$table, $column]) {
            if ($this->hasTable($table) && Schema::hasColumn($table, $column) && Schema::hasColumn($table, 'code')) {
                $sum = (float) (DB::table($table)->whereRaw('UPPER(TRIM(code)) = ?', [$code])->sum($column) ?? 0);
                $total += $sum;
            }
        }

        if ($this->hasTable('itemadj')) {
            if (Schema::hasColumn('itemadj', 'fromcode') && Schema::hasColumn('itemadj', 'fromwgt')) {
                $total += (float) (DB::table('itemadj')->whereRaw('UPPER(TRIM(fromcode)) = ?', [$code])->sum('fromwgt') ?? 0);
            }
            if (Schema::hasColumn('itemadj', 'tocode') && Schema::hasColumn('itemadj', 'towgt')) {
                $total += (float) (DB::table('itemadj')->whereRaw('UPPER(TRIM(tocode)) = ?', [$code])->sum('towgt') ?? 0);
            }
        }

        return abs($total) < 0.0001;
    }

    private function safeLookup(string $table): array
    {
        if (!$this->hasTable($table)) {
            return [];
        }

        $cols = array_map('strtolower', $this->columnList($table));
        if (!in_array('code', $cols, true)) {
            return [];
        }
        $nameCol = in_array('name', $cols, true) ? 'name' : 'code';

        return DB::table($table)
            ->select(['code', DB::raw($nameCol . ' as name')])
            ->orderBy($nameCol)
            ->limit(500)
            ->get()
            ->map(fn ($r) => ['code' => (string) $r->code, 'name' => (string) $r->name])
            ->values()
            ->toArray();
    }

    private function qualityTypeLookup(): array
    {
        // Prefer legacy purity master used by current migration work.
        foreach (['itemsqtype', 'qtype'] as $table) {
            if (!$this->hasTable($table)) {
                continue;
            }
            $cols = array_map('strtolower', $this->columnList($table));
            if (!in_array('code', $cols, true)) {
                continue;
            }
            $nameCol = in_array('name', $cols, true) ? 'name' : 'code';
            $hasTouch = in_array('touch', $cols, true);

            $select = ['code', DB::raw($nameCol . ' as name')];
            if ($hasTouch) {
                $select[] = 'touch';
            }

            $rows = DB::table($table)
                ->select($select)
                ->orderBy($nameCol)
                ->limit(500)
                ->get()
                ->map(fn ($r) => [
                    'code' => (string) $r->code,
                    'name' => (string) $r->name,
                    'touch' => $hasTouch ? (float) ($r->touch ?? 0) : 0,
                ])
                ->values()
                ->toArray();

            if (!empty($rows)) {
                return $rows;
            }
        }

        // Fallback for schemas that keep quality under itemqtype/qcode.
        if (!$this->hasTable('itemqtype')) {
            return [];
        }

        $cols = array_map('strtolower', $this->columnList('itemqtype'));
        $codeCol = in_array('qcode', $cols, true) ? 'qcode' : (in_array('iqtype', $cols, true) ? 'iqtype' : null);
        if ($codeCol === null) {
            return [];
        }

        return DB::table('itemqtype')
            ->selectRaw('DISTINCT TRIM(' . $codeCol . ') as code')
            ->whereRaw('TRIM(' . $codeCol . ') <> ?', [''])
            ->orderBy('code')
            ->limit(500)
            ->get()
            ->map(fn ($r) => ['code' => (string) $r->code, 'name' => (string) $r->code, 'touch' => 0])
            ->values()
            ->toArray();
    }

    private function smithLookup(): array
    {
        if (!$this->hasTable('clients') || !Schema::hasColumn('clients', 'code') || !Schema::hasColumn('clients', 'name')) {
            return [];
        }
        $q = DB::table('clients')->select('code', 'name');
        if (Schema::hasColumn('clients', 'ctype')) {
            $q->where('ctype', 'G');
        }
        return $q->orderBy('name')->limit(500)->get()
            ->map(fn ($r) => ['code' => (string) $r->code, 'name' => (string) $r->name])
            ->values()
            ->toArray();
    }

    private function billTypeLookup(): array
    {
        foreach (['salestype', 'billtype'] as $table) {
            $rows = $this->safeLookup($table);
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
