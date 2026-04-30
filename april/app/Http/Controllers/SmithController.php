<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SmithController extends Controller
{
    // ── Auth check ──

    private function requireAuth(Request $request): void
    {
        if (!$request->session()->has('user_code')) {
            abort(response()->json(['success' => false, 'message' => 'Unauthorized'], 401));
        }
    }

    // ── Column / table helpers (static cache, same pattern as AccountMasterController) ──

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . ':' . strtolower($column);
        if (!isset($cache[$key])) {
            $cache[$key] = Schema::hasColumn($table, $column);
        }
        return $cache[$key];
    }

    private function getColumns(string $table): array
    {
        static $colCache = [];
        if (!isset($colCache[$table])) {
            try {
                $colCache[$table] = array_map('strtolower', $this->columnList($table));
            } catch (\Throwable $e) {
                $colCache[$table] = [];
            }
        }
        return $colCache[$table];
    }

    private function filterColumns(string $table, array $data): array
    {
        $columns = array_flip($this->getColumns($table));
        $out = [];
        foreach ($data as $k => $v) {
            if (isset($columns[strtolower($k)])) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private function tableExists(string $table): bool
    {
        static $tableCache = [];
        if (!isset($tableCache[$table])) {
            $tableCache[$table] = $this->hasTable($table);
        }
        return $tableCache[$table];
    }

    // ── API endpoints ──

    /**
     * GET /api/smith?type=G
     * List all smith/goldsmith/refiner/jewellery records by type.
     */
    public function index(Request $request): JsonResponse
    {
        $this->requireAuth($request);

        $type = strtoupper(trim((string) $request->query('type', 'G')));

        try {
            $blockedExpr = $this->hasColumn('accountm', 'blocked') ? ', a.blocked' : ', NULL AS blocked';

            $sql = "SELECT c.*,
                           cg.wastage, cg.mcrate, cg.opweight, cg.opweightb, cg.opwgtamt, cg.acin24ct, cg.silver,
                           a.grcode, a.bshead{$blockedExpr}
                    FROM clients c
                    LEFT JOIN clientsgs cg ON c.code = cg.code
                    LEFT JOIN accountm a ON c.code = a.accode
                    WHERE c.ctype = ?
                    ORDER BY c.code DESC";

            $rows = DB::select($sql, [$type]);

            return response()->json([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/smith/{code}
     * Get a single record by code (joined from clients + clientsgs + accountm).
     */
    public function show(string $code): JsonResponse
    {
        $code = trim((string) $code);
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Code is required'], 400);
        }

        try {
            $blockedExpr = $this->hasColumn('accountm', 'blocked') ? ', a.blocked' : ', NULL AS blocked';

            $sql = "SELECT c.*,
                           cg.wastage, cg.opweight, cg.opweightb, cg.mcrate,
                           cg.opwgtamt, cg.opwgtamtb, cg.convtouch, cg.deftouch,
                           cg.stocktouch, cg.decround, cg.roundup, cg.mcdecround,
                           cg.intamt, cg.intwgt, cg.acin24ct, cg.silver,
                           a.grcode, a.bshead{$blockedExpr}
                    FROM clients c
                    LEFT JOIN clientsgs cg ON c.code = cg.code
                    LEFT JOIN accountm a ON c.code = a.accode
                    WHERE c.code = ?
                    LIMIT 1";

            $row = DB::selectOne($sql, [$code]);

            if (!$row) {
                return response()->json(['success' => false, 'message' => 'Record not found'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $row,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/smith
     * Create a new smith/goldsmith/refiner/jewellery record.
     */
    public function store(Request $request): JsonResponse
    {
        $this->requireAuth($request);

        $data = $this->readPayload($request);
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));

        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Code is required'], 400);
        }
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Name is required'], 400);
        }

        // Check if code already exists
        $exists = DB::table('clients')->where('code', $code)->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'This code already exists'], 400);
        }

        try {
            $this->saveInternal($data, false);

            return response()->json([
                'success' => true,
                'message' => 'Record created successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/smith/{code}
     * Update an existing smith/goldsmith/refiner/jewellery record.
     */
    public function update(Request $request, string $code): JsonResponse
    {
        $this->requireAuth($request);

        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Code is required'], 400);
        }

        $exists = DB::table('clients')->where('code', $code)->exists();
        if (!$exists) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        $data = $this->readPayload($request);
        $data['code'] = $code;

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return response()->json(['success' => false, 'message' => 'Name is required'], 400);
        }

        try {
            $this->saveInternal($data, true);

            return response()->json([
                'success' => true,
                'message' => 'Record updated successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/smith/{code}
     * Delete a smith/goldsmith/refiner/jewellery record if not used in transactions.
     */
    public function destroy(string $code): JsonResponse
    {
        $code = trim((string) $code);
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Code is required'], 400);
        }

        // Check for usage in daybook transactions
        try {
            $used = DB::selectOne("SELECT COUNT(*) AS c FROM daybook WHERE accode = ?", [$code]);
            if ((int) ($used->c ?? 0) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete. Code is used in transactions.',
                ], 400);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking transactions: ' . $e->getMessage(),
            ], 500);
        }

        DB::beginTransaction();
        try {
            DB::table('clientsgs')->where('code', $code)->delete();

            if ($this->tableExists('clientspict')) {
                DB::table('clientspict')->where('code', $code)->delete();
            }

            DB::table('accountm')->where('accode', $code)->delete();
            DB::table('clients')->where('code', $code)->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Record deleted successfully',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/smith/photo?code=X
     * Return the photo binary for a given code as image/jpeg.
     */
    public function photo(Request $request): Response
    {
        $code = trim((string) $request->query('code', ''));
        if ($code === '' || !$this->tableExists('clientspict')) {
            return response('', 404);
        }

        $row = DB::table('clientspict')->select(['pict'])->where('code', $code)->first();
        $blob = (string) ($row->pict ?? '');
        if ($blob === '') {
            return response('', 404);
        }

        return response($blob, 200, ['Content-Type' => 'image/jpeg']);
    }

    /**
     * GET /api/smith/groups
     * Return client groups + account groups for dropdowns.
     */
    public function groups(): JsonResponse
    {
        try {
            // Client groups from clientsgrp
            $clientGroups = [];
            if ($this->tableExists('clientsgrp')) {
                $clientGroups = DB::select("SELECT code, name FROM clientsgrp ORDER BY name");
            }

            // Account groups (accountg or achdgrp fallback)
            $accountGroups = [];
            if ($this->tableExists('accountg')) {
                $accountGroups = DB::select("SELECT grcode AS code, name FROM accountg ORDER BY name");
            } elseif ($this->tableExists('achdgrp')) {
                $accountGroups = DB::select("SELECT accode AS code, name FROM achdgrp ORDER BY name");
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'clientGroups' => $clientGroups,
                    'accountGroups' => $accountGroups,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/smith/states
     * Return state list for dropdowns (statestate or states fallback).
     */
    public function states(): JsonResponse
    {
        try {
            $states = [];

            if ($this->tableExists('statestate')) {
                $states = DB::select("SELECT code, name FROM statestate ORDER BY name");
            } elseif ($this->tableExists('states')) {
                $states = DB::select("SELECT code, name FROM states ORDER BY name");
            }

            return response()->json([
                'success' => true,
                'data' => $states,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/smith/search?q=X&type=G
     * Search clients by code, name, city, or mobile.
     */
    public function search(Request $request): JsonResponse
    {
        $this->requireAuth($request);

        $q = trim((string) $request->query('q', ''));
        $type = strtoupper(trim((string) $request->query('type', 'G')));

        if ($q === '') {
            return response()->json(['success' => true, 'data' => []]);
        }

        $search = '%' . $q . '%';

        try {
            $rows = DB::select(
                "SELECT code, name, city, mobile
                 FROM clients
                 WHERE (code LIKE ? OR name LIKE ? OR city LIKE ? OR mobile LIKE ?) AND ctype = ?
                 LIMIT 20",
                [$search, $search, $search, $search, $type]
            );

            return response()->json([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ── Internal helpers ──

    /**
     * Read the request payload from JSON body or form data.
     */
    private function readPayload(Request $request): array
    {
        $json = $request->json()->all();
        if (is_array($json) && !empty($json)) {
            return $json;
        }
        return $request->all();
    }

    /**
     * Transaction: write to clients + clientsgs + accountm + photo (clientspict).
     * Uses filterColumns() for schema-safe writes.
     */
    private function saveInternal(array $data, bool $isEdit): void
    {
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $name = trim((string) ($data['name'] ?? ''));
        $ctype = strtoupper(trim((string) ($data['ctype'] ?? 'G')));

        if ($code === '' || $name === '') {
            throw new \RuntimeException('Code and Name are required');
        }

        DB::beginTransaction();
        try {
            // ── 1. clients table ──
            $clientRow = $this->filterColumns('clients', [
                'code' => $code,
                'name' => $name,
                'addr1' => (string) ($data['addr1'] ?? ''),
                'addr2' => (string) ($data['addr2'] ?? ''),
                'addr3' => (string) ($data['addr3'] ?? ''),
                'city' => (string) ($data['city'] ?? ''),
                'pin' => (string) ($data['pin'] ?? ''),
                'state' => (string) ($data['state'] ?? ''),
                'telephone' => (string) ($data['telephone'] ?? ''),
                'mobile' => (string) ($data['mobile'] ?? ''),
                'tin' => (string) ($data['tin'] ?? ''),
                'panadhar' => (string) ($data['panadhar'] ?? ''),
                'email' => (string) ($data['email'] ?? ''),
                'opbalance' => (float) ($data['opbalance'] ?? 0),
                'opbalanceb' => (float) ($data['opbalanceb'] ?? 0),
                'ctype' => $ctype,
                'grp' => (string) ($data['grp'] ?? ''),
                'distance' => (int) ($data['distance'] ?? 0),
                'removed' => (int) ($data['removed'] ?? 0),
                'control' => 1,
            ]);
            $this->upsertByCode('clients', 'code', $clientRow);

            // ── 2. clientsgs table ──
            $gsRow = $this->filterColumns('clientsgs', [
                'code' => $code,
                'wastage' => (float) ($data['wastage'] ?? 0),
                'opweight' => (float) ($data['opweight'] ?? 0),
                'opweightb' => (float) ($data['opweightb'] ?? 0),
                'mcrate' => (float) ($data['mcrate'] ?? 0),
                'opwgtamt' => (float) ($data['opwgtamt'] ?? 0),
                'opwgtamtb' => (float) ($data['opwgtamtb'] ?? 0),
                'convtouch' => (float) ($data['convtouch'] ?? 0),
                'deftouch' => (float) ($data['deftouch'] ?? 0),
                'stocktouch' => (float) ($data['stocktouch'] ?? 0),
                'decround' => (int) ($data['decround'] ?? 2),
                'roundup' => (int) ($data['roundup'] ?? 0),
                'mcdecround' => (int) ($data['mcdecround'] ?? 0),
                'intamt' => (float) ($data['intamt'] ?? 0),
                'intwgt' => (float) ($data['intwgt'] ?? 0),
                'acin24ct' => (float) ($data['acin24ct'] ?? 0),
                'silver' => (string) ($data['silver'] ?? 'N'),
                'ctype' => $ctype,
            ]);
            $this->upsertByCode('clientsgs', 'code', $gsRow);

            // ── 3. accountm table ──
            $actype1 = ($ctype === 'C') ? 'A' : 'L';
            $acName = substr($name . '(' . $code . ')', 0, 30);
            $accountRow = $this->filterColumns('accountm', [
                'accode' => $code,
                'name' => $acName,
                'opbal' => (float) ($data['opbalance'] ?? 0),
                'opbalb' => (float) ($data['opbalanceb'] ?? 0),
                'actype1' => $actype1,
                'actype2' => $ctype,
                'grcode' => (string) ($data['grcode'] ?? 'SUNCR'),
                'bshead' => (string) ($data['bshead'] ?? 'SUNCR'),
                'shedgrp' => (string) ($data['bshead'] ?? 'SUNCR'),
                'blocked' => (string) ($data['blocked'] ?? 'N'),
                'removed' => (int) ($data['removed'] ?? 0),
                'control' => 1,
                'hlp' => 1,
                'sp' => 0,
            ]);
            $this->upsertByCode('accountm', 'accode', $accountRow);

            // ── 4. Photo (clientspict) ──
            if (!empty($data['photo']) && $this->tableExists('clientspict')) {
                $photoBinary = $data['photo'];

                // Decode base64 data URI if present
                if (is_string($photoBinary) && preg_match('/^data:image\\/[^;]+;base64,/', $photoBinary)) {
                    $photoBinary = base64_decode(substr($photoBinary, strpos($photoBinary, ',') + 1));
                }

                if ($photoBinary !== false && $photoBinary !== '' && $photoBinary !== null) {
                    $this->upsertPhoto($code, $photoBinary);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Upsert a row by primary key column.
     * If a row with the PK value exists, update it; otherwise insert.
     */
    private function upsertByCode(string $table, string $pk, array $data): void
    {
        if (empty($data) || !isset($data[$pk])) {
            return;
        }

        $pkValue = $data[$pk];
        $exists = DB::table($table)->where($pk, $pkValue)->exists();

        if ($exists) {
            $updateData = $data;
            unset($updateData[$pk]);
            if (!empty($updateData)) {
                DB::table($table)->where($pk, $pkValue)->update($updateData);
            }
        } else {
            DB::table($table)->insert($data);
        }
    }

    /**
     * Upsert photo binary into clientspict.
     */
    private function upsertPhoto(string $code, $photoBinary): void
    {
        $exists = DB::table('clientspict')->where('code', $code)->exists();

        if ($exists) {
            DB::table('clientspict')->where('code', $code)->update(['pict' => $photoBinary]);
        } else {
            DB::table('clientspict')->insert(['code' => $code, 'pict' => $photoBinary]);
        }
    }
}
