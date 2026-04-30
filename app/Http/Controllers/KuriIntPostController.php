<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class KuriIntPostController extends Controller
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

    private function resolveControl(Request $request): int
    {
        $q = trim((string) $request->query('control', ''));
        if ($q !== '' && is_numeric($q)) return max(1, (int) $q);
        $fromSession = (string) ($request->session()->get('semi')
            ?? $request->session()->get('control') ?? '2');
        return is_numeric($fromSession) ? max(1, (int) $fromSession) : 2;
    }

    private function nextSerialAndIncrement(): int
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

    private function nextVoucherNo(int $control): string
    {
        if (!$this->hasTable('generali')) return 'JLB/00001';
        $counterCode = ($control === 1) ? 'VCHNOJB' : 'VCHNOJE';
        $prefix      = ($control === 1) ? 'JLB/' : 'JLE/';
        $current = (int) (DB::table('generali')->where('code', $counterCode)->value('cvalue') ?? 0);
        $next    = $current + 1;
        DB::table('generali')->where('code', $counterCode)->update(['cvalue' => $next]);
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }
        $mode  = $request->query('mode', 'kuri');   // 'kuri' or 'scheme'
        $title = $mode === 'scheme' ? 'Scheme Interest Posting' : 'Kuri Interest Posting';
        return view('kuri-int-post.index', compact('mode', 'title'));
    }

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action  = strtolower(trim((string) $request->input('action', '')));
        $control = $this->resolveControl($request);

        switch ($action) {
            case 'init':     return $this->actionInit($control);
            case 'show':     return $this->actionShow($request, $control);
            case 'save':     return $this->actionSave($request, $control);
            default:
                return $this->json(['success' => false, 'error' => 'Invalid action'], 400);
        }
    }

    private function actionInit(int $control): JsonResponse
    {
        return $this->json([
            'success' => true,
            'today'   => date('Y-m-d'),
            'control' => $control,
        ]);
    }

    private function actionShow(Request $request, int $control): JsonResponse
    {
        $tdate    = trim((string) $request->input('tdate', date('Y-m-d')));
        $withZero = (bool) $request->input('with_zero', false);

        if (!$this->hasTable('clients')) {
            return $this->json(['success' => true, 'data' => []]);
        }

        // Build query: clients (KC group) + total collection from kuricolln
        $query = DB::table('clients as c')
            ->selectRaw('TRIM(c.code) AS code, TRIM(c.name) AS name')
            ->where('c.grp', 'KC')
            ->orderBy('c.name');

        if ($this->hasTable('kuricolln')) {
            $query->selectRaw(
                'COALESCE((SELECT SUM(kc.amount) FROM kuricolln kc
                    WHERE kc.code = c.code AND kc.tdate <= ? AND kc.control <= ?), 0) AS tcolln',
                [$tdate, $control]
            );
        } else {
            $query->selectRaw('0 AS tcolln');
        }

        // Interest rate from clients_kuridet
        if ($this->hasTable('clients_kuridet')) {
            $query->selectRaw(
                'COALESCE((SELECT ckd.intrate FROM clients_kuridet ckd WHERE ckd.code = c.code LIMIT 1), 0) AS intrate'
            );
        } else {
            $query->selectRaw('0 AS intrate');
        }

        $rows = $query->limit(1000)->get();

        if ($withZero) {
            $data = $rows->map(fn($r) => [
                'code'    => trim((string) ($r->code ?? '')),
                'name'    => trim((string) ($r->name ?? '')),
                'tcolln'  => round((float) ($r->tcolln ?? 0), 2),
                'intrate' => round((float) ($r->intrate ?? 0), 3),
                'intamt'  => 0.0,
                'sel'     => 1,
            ])->filter(fn($r) => $r['code'] !== '')->values();

            return $this->json(['success' => true, 'data' => $data]);
        }

        // Calculate interest per party using kuricolln entries
        $data = [];
        foreach ($rows as $r) {
            $code    = trim((string) ($r->code ?? ''));
            if ($code === '') continue;
            $name    = trim((string) ($r->name ?? ''));
            $tcolln  = round((float) ($r->tcolln ?? 0), 2);
            $intrate = round((float) ($r->intrate ?? 0), 3);
            $intamt  = 0.0;

            if ($intrate > 0 && $this->hasTable('kuricolln')) {
                // Sum: amount * intrate/100 * days / 365 for each collection entry
                $result = DB::table('kuricolln')
                    ->selectRaw(
                        'COALESCE(SUM(amount * ? / 100 * DATEDIFF(?, tdate) / 365), 0) AS intamt',
                        [$intrate, $tdate]
                    )
                    ->where('code', $code)
                    ->where('tdate', '<=', $tdate)
                    ->where('control', '<=', $control)
                    ->value('intamt');
                $intamt = round((float) ($result ?? 0), 2);
            }

            $data[] = [
                'code'    => $code,
                'name'    => $name,
                'tcolln'  => $tcolln,
                'intrate' => $intrate,
                'intamt'  => $intamt,
                'sel'     => ($intamt > 0) ? 1 : 0,
            ];
        }

        return $this->json(['success' => true, 'data' => $data]);
    }

    private function actionSave(Request $request, int $control): JsonResponse
    {
        $tdate   = trim((string) $request->input('tdate', date('Y-m-d')));
        $t = strtotime($tdate);
        if ($t === false) $tdate = date('Y-m-d');
        else $tdate = date('Y-m-d', $t);

        $rows    = $request->input('rows', []);
        $userId  = (string) $request->session()->get('user_id',
                        $request->session()->get('user_code', ''));

        if (!is_array($rows) || count($rows) === 0) {
            return $this->json(['success' => false, 'error' => 'No rows to save'], 422);
        }

        // Filter valid rows
        $valid = array_filter($rows, fn($r) =>
            !empty($r['code']) && (float) ($r['intamt'] ?? 0) > 0
        );
        if (count($valid) === 0) {
            return $this->json(['success' => false, 'error' => 'No rows with interest amount > 0'], 422);
        }

        DB::beginTransaction();
        try {
            $slno    = $this->nextSerialAndIncrement();
            $vchno   = $this->nextVoucherNo($control);
            $particular = 'By Kuri Interest - ' . $vchno;
            $ttime   = date('H:i:s');

            if ($this->hasTable('daybookpart')) {
                DB::table('daybookpart')->insert($this->f('daybookpart', [
                    'slno'       => $slno,
                    'vchno'      => $vchno,
                    'particular' => mb_substr($particular, 0, 60),
                    'uid'        => $userId,
                    'ic'         => $userId,
                    'ttime'      => $ttime,
                    'rate'       => 0,
                ]));
            }

            $netamt   = 0.0;
            $lastCode = '';
            $sno      = 0;

            foreach ($valid as $row) {
                $code   = strtoupper(trim((string) ($row['code'] ?? '')));
                $intamt = round((float) ($row['intamt'] ?? 0), 2);
                if ($code === '' || $intamt <= 0) continue;

                $sno++;

                if ($this->hasTable('kuriint')) {
                    DB::table('kuriint')->insert($this->f('kuriint', [
                        'slno'    => $slno,
                        'tdate'   => $tdate,
                        'code'    => $code,
                        'amount'  => $intamt,
                        'sno'     => $sno,
                        'control' => $control,
                    ]));
                }

                if ($this->hasTable('daybook')) {
                    DB::table('daybook')->insert($this->f('daybook', [
                        'slno'     => $slno,
                        'tdate'    => $tdate,
                        'accode'   => $code,
                        'amount'   => $intamt,
                        'control'  => $control,
                        'opaccode' => 'KINT',
                    ]));
                }

                $netamt  += $intamt;
                $lastCode = $code;
            }

            if ($netamt > 0 && $this->hasTable('daybook')) {
                // Contra entry: credit KINT account
                $opaccode = DB::table('daybook')
                    ->where('slno', $slno)
                    ->max('accode') ?? $lastCode;

                DB::table('daybook')->insert($this->f('daybook', [
                    'slno'     => $slno,
                    'tdate'    => $tdate,
                    'accode'   => 'KINT',
                    'amount'   => round(-$netamt, 2),
                    'control'  => $control,
                    'opaccode' => $opaccode,
                ]));
            }

            DB::commit();
            return $this->json([
                'success' => true,
                'message' => $sno . ' interest posting(s) saved. Voucher: ' . $vchno,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}
