<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Ruff Work — spreadsheet-style scratch-pad grid.
 * Legacy PB window: w_ruffwork
 * Table: ruffwrk (slno, party, tdate, item, qty, weight, amount, part, inexp, sman, person, pend, number, control)
 */
class RuffWorkController extends Controller
{
    private function json(array $p, int $s = 200): JsonResponse { return response()->json($p, $s); }

    private function f(string $table, array $data): array
    {
        if (!$this->hasTable($table)) return $data;
        $cols = array_flip(array_map('strtolower', $this->columnList($table)));
        return array_filter($data, static fn($v, $k) => isset($cols[strtolower((string)$k)]), ARRAY_FILTER_USE_BOTH);
    }

    private function resolveControl(Request $request): int
    {
        $q = trim((string)$request->query('gisemi', ''));
        if ($q !== '' && is_numeric($q)) return max(1, (int)$q);
        $s = (string)($request->session()->get('semi') ?? $request->session()->get('control') ?? '2');
        return is_numeric($s) ? max(1, (int)$s) : 2;
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('ruff-work.index');
    }

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code'))
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);

        $action = strtolower(trim((string)$request->input('action', '')));
        $control = $this->resolveControl($request);

        return match ($action) {
            'list'           => $this->actionList($request, $control),
            'save'           => $this->actionSave($request, $control),
            'delete'         => $this->actionDelete($request),
            'account_search' => $this->actionAccountSearch($request),
            'item_search'    => $this->actionItemSearch($request),
            'salesmen'       => $this->actionSalesmen(),
            default          => $this->json(['success' => false, 'error' => 'Invalid action'], 400),
        };
    }

    // ── list ──

    private function actionList(Request $request, int $control): JsonResponse
    {
        if (!$this->hasTable('ruffwrk')) return $this->json(['success' => true, 'data' => []]);

        $filter = strtolower(trim((string)$request->input('filter', 'all')));
        $query = DB::table('ruffwrk')->where('control', $control)->orderBy('slno');

        if ($filter === 'pending') {
            $query->where('pend', 1);
        }

        $rows = $query->limit(500)->get();

        $data = $rows->map(fn($r) => [
            'slno'   => (int)$r->slno,
            'party'  => trim((string)($r->party ?? '')),
            'tdate'  => (string)($r->tdate ?? ''),
            'item'   => trim((string)($r->item ?? '')),
            'qty'    => trim((string)($r->qty ?? '')),
            'weight' => trim((string)($r->weight ?? '')),
            'amount' => trim((string)($r->amount ?? '')),
            'part'   => trim((string)($r->part ?? '')),
            'inexp'  => trim((string)($r->inexp ?? '')),
            'sman'   => trim((string)($r->sman ?? '')),
            'person' => trim((string)($r->person ?? '')),
            'pend'   => (int)($r->pend ?? 0),
            'number' => (int)($r->number ?? 0),
        ])->values();

        return $this->json(['success' => true, 'data' => $data]);
    }

    // ── save (bulk upsert) ──

    private function actionSave(Request $request, int $control): JsonResponse
    {
        if (!$this->hasTable('ruffwrk'))
            return $this->json(['success' => false, 'error' => 'ruffwrk table not found'], 500);

        $rows = $request->input('rows', []);
        if (!is_array($rows))
            return $this->json(['success' => false, 'error' => 'Invalid rows data'], 422);

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $slno = (int)($row['slno'] ?? 0);
                $party = trim((string)($row['party'] ?? ''));

                // Delete rows with empty party (matches PB behavior)
                if ($party === '' && $slno > 0) {
                    DB::table('ruffwrk')->where('slno', $slno)->delete();
                    continue;
                }
                if ($party === '') continue;

                $data = $this->f('ruffwrk', [
                    'party'   => $party,
                    'tdate'   => $row['tdate'] ?? date('Y-m-d'),
                    'item'    => mb_substr(trim((string)($row['item'] ?? '')), 0, 30),
                    'qty'     => mb_substr(trim((string)($row['qty'] ?? '')), 0, 20),
                    'weight'  => mb_substr(trim((string)($row['weight'] ?? '')), 0, 20),
                    'amount'  => mb_substr(trim((string)($row['amount'] ?? '')), 0, 30),
                    'part'    => mb_substr(trim((string)($row['part'] ?? '')), 0, 30),
                    'inexp'   => mb_substr(trim((string)($row['inexp'] ?? '')), 0, 3),
                    'sman'    => mb_substr(trim((string)($row['sman'] ?? '')), 0, 30),
                    'person'  => mb_substr(trim((string)($row['person'] ?? '')), 0, 30),
                    'pend'    => (int)($row['pend'] ?? 1),
                    'number'  => (int)($row['number'] ?? 0),
                    'control' => $control,
                ]);

                if ($slno > 0 && DB::table('ruffwrk')->where('slno', $slno)->exists()) {
                    DB::table('ruffwrk')->where('slno', $slno)->update($data);
                } else {
                    DB::table('ruffwrk')->insert($data);
                }
            }

            DB::commit();
            return $this->json(['success' => true, 'message' => 'Saved successfully']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    // ── delete single row ──

    private function actionDelete(Request $request): JsonResponse
    {
        $slno = (int)$request->input('slno', 0);
        if ($slno <= 0 || !$this->hasTable('ruffwrk'))
            return $this->json(['success' => false, 'error' => 'Record not found'], 422);

        DB::table('ruffwrk')->where('slno', $slno)->delete();
        return $this->json(['success' => true, 'message' => 'Row deleted']);
    }

    // ── account search (F1 on party) ──

    private function actionAccountSearch(Request $request): JsonResponse
    {
        if (!$this->hasTable('accountm')) return $this->json(['success' => true, 'data' => []]);
        $s = strtoupper(trim((string)$request->input('search', '')));
        if ($s === '') return $this->json(['success' => true, 'data' => []]);
        $like = '%' . $s . '%';
        $rows = DB::table('accountm')
            ->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
            ->where(fn($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like))
            ->orderBy('name')->limit(50)->get()->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    // ── item search (F1 on item) ──

    private function actionItemSearch(Request $request): JsonResponse
    {
        if (!$this->hasTable('items')) return $this->json(['success' => true, 'data' => []]);
        $s = strtoupper(trim((string)$request->input('search', '')));
        if ($s === '') return $this->json(['success' => true, 'data' => []]);
        $like = '%' . $s . '%';
        $rows = DB::table('items')
            ->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
            ->where(fn($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like))
            ->orderBy('code')->limit(50)->get()->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    // ── salesmen list ──

    private function actionSalesmen(): JsonResponse
    {
        if (!$this->hasTable('sman')) return $this->json(['success' => true, 'data' => []]);
        $rows = DB::table('sman')
            ->selectRaw('TRIM(name) AS name')
            ->orderBy('name')->get()->pluck('name')->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }
}
