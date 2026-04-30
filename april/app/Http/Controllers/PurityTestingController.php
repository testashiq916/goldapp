<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Purity Testing Entry — New / Edit / Cancel / Reprint.
 * Legacy PB window: w_purity_entry
 * Table: testdet (slno, docno, tdate, customer, purityinperc, purityinct,
 *                  rcvdon, testedon, otherinfo, rcvdwgt, control, typeofsample)
 */
class PurityTestingController extends Controller
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

    private function normDate(?string $d): ?string
    {
        $d = trim((string)$d);
        if ($d === '') return null;
        $t = strtotime($d);
        return ($t === false) ? null : date('Y-m-d', $t);
    }

    // ── Pages ──

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('purity-testing.index');
    }

    public function picker(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('purity-testing.picker');
    }

    // ── API ──

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code'))
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);

        $action = strtolower(trim((string)$request->input('action', '')));
        $control = $this->resolveControl($request);

        return match ($action) {
            'init'   => $this->actionInit(),
            'save'   => $this->actionSave($request, $control),
            'load'   => $this->actionLoad($request),
            'list'   => $this->actionList($request),
            'delete' => $this->actionDelete($request, $control),
            default  => $this->json(['success' => false, 'error' => 'Invalid action'], 400),
        };
    }

    // ── init ──

    private function actionInit(): JsonResponse
    {
        $nextDocno = 1;
        if ($this->hasTable('generali')) {
            $cur = (int)(DB::table('generali')->where('code', 'BILLNO')->value('cvalue') ?? 0);
            $nextDocno = $cur + 1;
        }

        return $this->json([
            'success' => true,
            'nextDocno' => $nextDocno,
            'today' => date('Y-m-d'),
        ]);
    }

    // ── save ──

    private function actionSave(Request $request, int $control): JsonResponse
    {
        $editSlno = (int)$request->input('edit_slno', 0);
        $docno = trim((string)$request->input('docno', ''));
        $tdate = $this->normDate((string)$request->input('tdate', '')) ?? date('Y-m-d');
        $customer = trim((string)$request->input('customer', ''));
        $purityinperc = round((float)$request->input('purityinperc', 0), 3);
        $purityinct = round((float)$request->input('purityinct', 0), 3);
        $otherinfo = mb_substr(trim((string)$request->input('otherinfo', '')), 0, 40);
        $rcvdon = $this->normDate((string)$request->input('rcvdon', ''));
        $testedon = $this->normDate((string)$request->input('testedon', ''));
        $rcvdwgt = round((float)$request->input('rcvdwgt', 0), 3);
        $typeofsample = mb_substr(trim((string)$request->input('typeofsample', '')), 0, 40);

        if ($customer === '')
            return $this->json(['success' => false, 'error' => 'Customer is required'], 422);

        DB::beginTransaction();
        try {
            if ($editSlno > 0) {
                // Edit mode — update existing
                DB::table('testdet')->where('slno', $editSlno)->update($this->f('testdet', [
                    'docno' => $docno,
                    'tdate' => $tdate,
                    'customer' => $customer,
                    'purityinperc' => $purityinperc,
                    'purityinct' => $purityinct,
                    'otherinfo' => $otherinfo,
                    'rcvdon' => $rcvdon,
                    'testedon' => $testedon,
                    'rcvdwgt' => $rcvdwgt,
                    'typeofsample' => $typeofsample,
                ]));

                DB::commit();
                return $this->json([
                    'success' => true,
                    'message' => 'Purity record updated. Slno: ' . $editSlno,
                    'slno' => $editSlno,
                ]);
            }

            // New mode
            // Get next SLNO
            if (!DB::table('generali')->where('code', 'SLNO')->exists())
                DB::table('generali')->insert(['code' => 'SLNO', 'cvalue' => 0]);
            DB::table('generali')->where('code', 'SLNO')->update(['cvalue' => DB::raw('cvalue + 1')]);
            $slno = (int)(DB::table('generali')->where('code', 'SLNO')->value('cvalue') ?? 0);

            // Update BILLNO counter
            $ldocno = (int)$docno;
            if (!DB::table('generali')->where('code', 'BILLNO')->exists())
                DB::table('generali')->insert(['code' => 'BILLNO', 'cvalue' => 0]);
            DB::table('generali')->where('code', 'BILLNO')->update(['cvalue' => $ldocno]);

            // Insert testdet
            DB::table('testdet')->insert($this->f('testdet', [
                'slno' => $slno,
                'docno' => $docno,
                'tdate' => $tdate,
                'customer' => $customer,
                'purityinperc' => $purityinperc,
                'purityinct' => $purityinct,
                'rcvdon' => $rcvdon,
                'testedon' => $testedon,
                'otherinfo' => $otherinfo,
                'rcvdwgt' => $rcvdwgt,
                'control' => $control,
                'typeofsample' => $typeofsample,
            ]));

            DB::commit();
            return $this->json([
                'success' => true,
                'message' => 'Purity record saved. No: ' . $docno,
                'slno' => $slno,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    // ── load ──

    private function actionLoad(Request $request): JsonResponse
    {
        $slno = (int)$request->input('slno', 0);
        if ($slno <= 0 || !$this->hasTable('testdet'))
            return $this->json(['success' => false, 'error' => 'Record not found'], 404);

        $r = DB::table('testdet')->where('slno', $slno)->first();
        if (!$r) return $this->json(['success' => false, 'error' => 'Record not found'], 404);

        return $this->json([
            'success' => true,
            'record' => [
                'slno' => (int)$r->slno,
                'docno' => trim((string)($r->docno ?? '')),
                'tdate' => (string)($r->tdate ?? ''),
                'customer' => trim((string)($r->customer ?? '')),
                'purityinperc' => round((float)($r->purityinperc ?? 0), 3),
                'purityinct' => round((float)($r->purityinct ?? 0), 3),
                'otherinfo' => trim((string)($r->otherinfo ?? '')),
                'rcvdon' => (string)($r->rcvdon ?? ''),
                'testedon' => (string)($r->testedon ?? ''),
                'rcvdwgt' => round((float)($r->rcvdwgt ?? 0), 3),
                'typeofsample' => trim((string)($r->typeofsample ?? '')),
                'control' => (int)($r->control ?? 0),
            ],
        ]);
    }

    // ── list ──

    private function actionList(Request $request): JsonResponse
    {
        if (!$this->hasTable('testdet')) return $this->json(['success' => true, 'data' => []]);

        $search = trim((string)$request->input('search', ''));
        $query = DB::table('testdet')->orderByDesc('slno');

        if ($search !== '') {
            $like = '%' . strtoupper($search) . '%';
            $query->where(fn($q) => $q
                ->where('docno', 'like', $like)
                ->orWhere('customer', 'like', $like)
                ->orWhere('typeofsample', 'like', $like)
                ->orWhereRaw('CAST(slno AS CHAR) LIKE ?', [$like])
            );
        }

        $rows = $query->limit(200)->get(['slno', 'docno', 'tdate', 'customer', 'purityinperc', 'purityinct', 'rcvdwgt', 'typeofsample']);

        $data = $rows->map(fn($r) => [
            'slno' => (int)$r->slno,
            'docno' => trim((string)($r->docno ?? '')),
            'tdate' => (string)($r->tdate ?? ''),
            'customer' => trim((string)($r->customer ?? '')),
            'purityinperc' => round((float)($r->purityinperc ?? 0), 3),
            'purityinct' => round((float)($r->purityinct ?? 0), 3),
            'rcvdwgt' => round((float)($r->rcvdwgt ?? 0), 3),
            'typeofsample' => trim((string)($r->typeofsample ?? '')),
        ])->values();

        return $this->json(['success' => true, 'data' => $data]);
    }

    // ── delete (cancel) ──

    private function actionDelete(Request $request, int $control): JsonResponse
    {
        $slno = (int)$request->input('slno', 0);
        if ($slno <= 0 || !$this->hasTable('testdet'))
            return $this->json(['success' => false, 'error' => 'Record not found'], 422);

        $r = DB::table('testdet')->where('slno', $slno)->first();
        if (!$r) return $this->json(['success' => false, 'error' => 'Record not found'], 404);

        $userId = (string)$request->session()->get('user_id', $request->session()->get('user_code', ''));

        DB::beginTransaction();
        try {
            DB::table('testdet')->where('slno', $slno)->delete();

            if ($this->hasTable('delpart')) {
                DB::table('delpart')->insert($this->f('delpart', [
                    'tdate' => date('Y-m-d'),
                    'part' => mb_substr('Purity Test Entry(No:' . trim((string)($r->docno ?? '')) . ') Canceled', 0, 60),
                    'control' => $control,
                    'slno' => $slno,
                    'utype' => 'D',
                    'ttype' => 'T',
                    'updtdate' => date('Y-m-d'),
                    'updttime' => date('H:i:s'),
                    'uid' => $userId,
                ]));
            }

            DB::commit();
            return $this->json(['success' => true, 'message' => 'Purity record #' . $slno . ' cancelled']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
