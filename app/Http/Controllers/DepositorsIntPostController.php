<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DepositorsIntPostController extends Controller
{
    private function json(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    private function f(string $table, array $data): array
    {
        if (!$this->hasTable($table)) return $data;
        $cols = array_flip(array_map('strtolower', $this->columnList($table)));
        return array_filter(
            $data,
            static fn($v, $k) => isset($cols[strtolower((string) $k)]),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function genInt(string $code): int
    {
        if (!$this->hasTable('generali')) return 0;
        return (int) (DB::table('generali')->where('code', $code)->value('cvalue') ?? 0);
    }

    private function incrementGenInt(string $code): int
    {
        if (!$this->hasTable('generali')) return 1;
        $current = $this->genInt($code);
        $next = $current + 1;
        $updated = DB::table('generali')->where('code', $code)->update(['cvalue' => $next]);
        if ($updated === 0) {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => $next]);
        }
        return $next;
    }

    private function nextSerialNo(): int
    {
        if (!$this->hasTable('generali')) return 1;
        $current = (int) (DB::table('generali')->where('code', 'SERIALNO')->value('cvalue') ?? 0);
        $maxUsed = 0;
        foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
            if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
            }
        }
        $next = max($current, $maxUsed) + 1;
        DB::table('generali')->updateOrInsert(['code' => 'SERIALNO'], ['cvalue' => $next]);
        return $next;
    }

    private function resolveGisemi(Request $request): int
    {
        $q = trim((string) $request->query('gisemi', ''));
        if ($q !== '' && is_numeric($q)) return max(1, (int) $q);
        $fromSession = (string) ($request->session()->get('semi')
            ?? $request->session()->get('control') ?? '2');
        return is_numeric($fromSession) ? max(1, (int) $fromSession) : 2;
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }
        return view('wgt-rcpt-pmnt.int-posting');
    }

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action = strtolower(trim((string) $request->input('action', '')));
        $control = $this->resolveGisemi($request);

        switch ($action) {
            case 'init':
                return $this->actionInit($request, $control);
            case 'show':
                return $this->actionShow($request, $control);
            case 'save':
                return $this->actionSave($request, $control);
            default:
                return $this->json(['success' => false, 'error' => 'Invalid action'], 400);
        }
    }

    private function actionInit(Request $request, int $control): JsonResponse
    {
        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')
                ->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
                ->orderBy('code')
                ->get()
                ->all();
        }

        return $this->json([
            'success' => true,
            'salesmen' => $salesmen,
            'today' => date('Y-m-d'),
            'control' => $control,
        ]);
    }

    private function actionShow(Request $request, int $control): JsonResponse
    {
        // Load depositor clients with interest weight/amount from clientsgs
        if (!$this->hasTable('clients')) {
            return $this->json(['success' => true, 'data' => []]);
        }

        $hasClientsgs = $this->hasTable('clientsgs');

        $query = DB::table('clients as c')
            ->select([
                DB::raw('TRIM(c.code) AS code'),
                DB::raw('TRIM(c.name) AS name'),
            ])
            ->where('c.grp', 'KC');

        if ($hasClientsgs) {
            $query->leftJoin('clientsgs as g', 'g.code', '=', 'c.code')
                ->addSelect([
                    DB::raw('COALESCE(g.intwgt, 0) AS intwgt'),
                    DB::raw('COALESCE(g.intamt, 0) AS intamt'),
                ]);
            // Only show those with interest > 0
            $query->where(function ($q) {
                $q->where('g.intwgt', '>', 0)
                  ->orWhere('g.intamt', '>', 0);
            });
        }

        $rows = $query->orderBy('c.name')->limit(500)->get();

        $data = $rows->map(function ($r) {
            return [
                'code' => trim((string) ($r->code ?? '')),
                'name' => trim((string) ($r->name ?? '')),
                'intwgt' => round((float) ($r->intwgt ?? 0), 3),
                'intamt' => round((float) ($r->intamt ?? 0), 2),
                'sel' => 1,
            ];
        })->values();

        return $this->json(['success' => true, 'data' => $data]);
    }

    private function actionSave(Request $request, int $control): JsonResponse
    {
        $tdate = trim((string) $request->input('tdate', ''));
        if ($tdate === '') $tdate = date('Y-m-d');
        $t = strtotime($tdate);
        if ($t === false) $tdate = date('Y-m-d');
        else $tdate = date('Y-m-d', $t);

        $smcode = strtoupper(trim((string) $request->input('smcode', '')));
        $rows = $request->input('rows', []);
        $userCode = (string) $request->session()->get('user_code', '');
        $userId = (string) $request->session()->get('user_id', $userCode);
        $note = 'Interest upto ' . date('d/m/Y', strtotime($tdate));

        if (!is_array($rows) || count($rows) === 0) {
            return $this->json(['success' => false, 'error' => 'No rows selected'], 422);
        }

        $eb = ($control === 1) ? 'E' : 'B';
        $counterCode = 'DINT' . $eb;
        $ttime = date('H:i:s');
        $savedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                $name = trim((string) ($row['name'] ?? ''));
                $intwgt = round((float) ($row['intwgt'] ?? 0), 3);
                $intamt = round((float) ($row['intamt'] ?? 0), 2);
                $sel = (int) ($row['sel'] ?? 0);

                if ($code === '' || $sel !== 1 || ($intwgt == 0 && $intamt == 0)) {
                    continue;
                }

                $slno = $this->nextSerialNo();
                $docNum = $this->incrementGenInt($counterCode);
                $docno = 'DIN' . $eb . str_pad($docNum, 5, '0', STR_PAD_LEFT);

                // Insert into smithm
                if ($this->hasTable('smithm')) {
                    DB::table('smithm')->insert($this->f('smithm', [
                        'slno' => $slno,
                        'docno' => $docno,
                        'tdate' => $tdate,
                        'ttime' => $ttime,
                        'smithcode' => $code,
                        'tmcharge' => $intamt,
                        'pamt' => 0,
                        'status' => 1,
                        'control' => $control,
                        'rate' => 0,
                        'rmno' => '',
                        'smcode' => $smcode,
                        'person' => '',
                        'jewlcode' => '',
                        'ic' => $userCode,
                        'opwgt' => 0,
                        'opamt' => 0,
                        'tdsperc' => 0,
                        'tdsamt' => 0,
                        'acidcharge' => 0,
                        'discount' => 0,
                        'lotno' => '',
                        'taxperc' => 0,
                        'taxamt' => 0,
                        'interstate' => 'N',
                        'taxreverse' => 'N',
                        'statecode' => '',
                        'placeos' => '',
                        'refno' => '',
                        'doctype' => 'N',
                        'duedate' => $tdate,
                        'transportmode' => '',
                        'vehno' => '',
                        'purpose' => '',
                        'note' => $note,
                    ]));
                }

                // Insert into smithd
                if ($this->hasTable('smithd')) {
                    DB::table('smithd')->insert($this->f('smithd', [
                        'slno' => $slno,
                        'code' => 'INT',
                        'qty' => 0,
                        'weight' => $intwgt,
                        'stonewgt' => 0,
                        'stoneprice' => 0,
                        'mcharge' => $intamt,
                        'wastage' => 0,
                        'cost' => 0,
                        'givrec' => 'R',
                        'wgtamt' => 0,
                        'touch' => 100,
                        'touchwgt' => 0,
                        'sno' => 1,
                        'name' => '',
                        'ordno' => '',
                        'orditem' => '',
                        'stktype' => '',
                        'touchnote' => '',
                        'bcode' => 0,
                        'smithmc' => 0,
                        'netwgt' => $intwgt,
                        'stktouch' => 0,
                        'hmc' => 0,
                        'mud' => 0,
                        'tp' => 0,
                        'sva' => 0,
                        'sstprice' => 0,
                        'model' => '',
                        'remark' => 'Interest',
                    ]));
                }

                // Insert into daybookpart
                $particular = 'By Interest Post -' . trim($docno);
                if ($name !== '') {
                    $particular .= '-' . trim($name);
                }
                $particular = mb_substr($particular, 0, 40);

                if ($this->hasTable('daybookpart')) {
                    DB::table('daybookpart')->insert($this->f('daybookpart', [
                        'slno' => $slno,
                        'particular' => $particular,
                        'vchno' => '',
                        'ic' => $userCode,
                        'uid' => $userId,
                        'ttime' => $ttime,
                        'rate' => 0,
                    ]));
                }

                // Insert daybook entries (double entry) if amount > 0
                if ($intamt > 0 && $this->hasTable('daybook')) {
                    // Debit party account
                    DB::table('daybook')->insert($this->f('daybook', [
                        'slno' => $slno,
                        'tdate' => $tdate,
                        'accode' => $code,
                        'amount' => round($intamt, 2),
                        'control' => $control,
                        'opaccode' => 'INTEXP',
                    ]));

                    // Credit INTEXP
                    DB::table('daybook')->insert($this->f('daybook', [
                        'slno' => $slno,
                        'tdate' => $tdate,
                        'accode' => 'INTEXP',
                        'amount' => round(-$intamt, 2),
                        'control' => $control,
                        'opaccode' => $code,
                    ]));
                }

                $savedCount++;
            }

            if ($savedCount === 0) {
                DB::rollBack();
                return $this->json(['success' => false, 'error' => 'No valid rows to save'], 422);
            }

            DB::commit();
            return $this->json([
                'success' => true,
                'message' => $savedCount . ' interest posting(s) saved successfully',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}
