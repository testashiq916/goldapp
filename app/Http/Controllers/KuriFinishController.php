<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class KuriFinishController extends Controller
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
        $from = (string) ($request->session()->get('semi')
                ?? $request->session()->get('control') ?? '2');
        return is_numeric($from) ? max(1, (int) $from) : 2;
    }

    private function nextSerial(): int
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

    private function nextVoucher(int $control): string
    {
        if (!$this->hasTable('generali')) return 'JLB/00001';
        $code   = ($control === 1) ? 'VCHNOJB' : 'VCHNOJE';
        $prefix = ($control === 1) ? 'JLB/' : 'JLE/';
        $cur    = (int) (DB::table('generali')->where('code', $code)->value('cvalue') ?? 0);
        $next   = $cur + 1;
        DB::table('generali')->where('code', $code)->update(['cvalue' => $next]);
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    // ── Page routes ──────────────────────────────────────────────────────────

    public function closeScheme(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        $mode  = $request->query('mode', 'kuri');
        $title = $mode === 'scheme' ? 'Close Scheme' : 'Close Kuri';
        return view('kuri-finish.close-scheme', compact('mode', 'title'));
    }

    public function draw(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        $mode  = $request->query('mode', 'kuri');
        $title = 'Draw Process';
        return view('kuri-finish.draw', compact('mode', 'title'));
    }

    public function refund(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        $mode  = $request->query('mode', 'kuri');
        $title = $mode === 'scheme' ? 'Scheme Refund' : 'Kuri Refund';
        return view('kuri-finish.refund', compact('mode', 'title'));
    }

    // ── API ───────────────────────────────────────────────────────────────────

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action  = strtolower(trim((string) $request->input('action', '')));
        $control = $this->resolveControl($request);

        switch ($action) {
            case 'init':         return $this->actionInit();
            case 'types':        return $this->actionTypes();
            case 'show':         return $this->actionShow($request, $control);
            case 'party-info':   return $this->actionPartyInfo($request, $control);
            case 'party-search': return $this->actionPartySearch($request);
            case 'save-finish':  return $this->actionSaveFinish($request, $control);
            case 'save-draw':    return $this->actionSaveDraw($request, $control);
            case 'save-refund':  return $this->actionSaveRefund($request, $control);
            default:
                return $this->json(['success' => false, 'error' => 'Invalid action'], 400);
        }
    }

    private function actionInit(): JsonResponse
    {
        return $this->json(['success' => true, 'today' => date('Y-m-d')]);
    }

    private function actionTypes(): JsonResponse
    {
        $types = [];
        if ($this->hasTable('kuritype')) {
            $types = DB::table('kuritype')
                ->selectRaw('TRIM(code) AS code, TRIM(name) AS name, instamt, totamt, bonus')
                ->orderBy('code')
                ->get()
                ->all();
        }
        return $this->json(['success' => true, 'types' => $types]);
    }

    // Load active (finished='N') scheme members of a given type
    private function actionShow(Request $request, int $control): JsonResponse
    {
        $tdate  = trim((string) $request->input('tdate', date('Y-m-d')));
        $stype  = trim((string) $request->input('stype', ''));
        $grate  = (float) $request->input('grate', 0);

        if (!$this->hasTable('clients') || !$this->hasTable('clients_kuridet')) {
            return $this->json(['success' => true, 'data' => []]);
        }

        $query = DB::table('clients_kuridet as kd')
            ->join('clients as c', 'c.code', '=', 'kd.code')
            ->selectRaw(
                'TRIM(kd.code) AS code,
                 TRIM(c.name) AS name,
                 COALESCE(kd.totamt, 0)  AS totamt,
                 COALESCE(kd.bonus, 0)   AS bonus,
                 COALESCE(kd.kuritype, \'\') AS kuritype'
            )
            ->where('c.grp', 'KC')
            ->where('kd.finished', 'N')
            ->orderBy('c.name');

        if ($stype !== '') {
            $query->where('kd.kuritype', $stype);
        }

        $rows = $query->limit(500)->get();

        $data = [];
        foreach ($rows as $r) {
            $code   = trim((string) ($r->code ?? ''));
            if ($code === '') continue;

            $tcolln = 0.0;
            $opbal  = 0.0;
            if ($this->hasTable('kuricolln')) {
                $tcolln = (float) (DB::table('kuricolln')
                    ->where('code', $code)
                    ->where('tdate', '<=', $tdate)
                    ->where('control', '<=', $control)
                    ->sum('amount') ?? 0);
            }
            if ($this->hasTable('clients_kuridet')) {
                $opbal = (float) (DB::table('clients_kuridet')
                    ->where('code', $code)
                    ->value('collnopbal') ?? 0);
            }
            $totalColln = $tcolln + $opbal;
            $totamt     = (float) ($r->totamt ?? 0);
            $balance    = $totamt - $totalColln;
            $bonus      = (float) ($r->bonus  ?? 0);
            $estwgt     = ($grate > 0) ? round($totamt / $grate, 3) : 0.0;

            $data[] = [
                'code'    => $code,
                'name'    => trim((string) ($r->name ?? '')),
                'totamt'  => round($totamt, 2),
                'tcolln'  => round($totalColln, 2),
                'balance' => round($balance, 2),
                'bonus'   => round($bonus, 2),
                'estwgt'  => $estwgt,
                'sel'     => 1,
            ];
        }

        return $this->json(['success' => true, 'data' => $data]);
    }

    // Load single party details for Draw/Refund
    private function actionPartyInfo(Request $request, int $control): JsonResponse
    {
        $code  = strtoupper(trim((string) $request->input('code', '')));
        $tdate = trim((string) $request->input('tdate', date('Y-m-d')));
        $grate = (float) $request->input('grate', 0);

        if ($code === '') {
            return $this->json(['success' => false, 'error' => 'Code required'], 422);
        }

        if (!$this->hasTable('clients')) {
            return $this->json(['success' => false, 'error' => 'Clients table missing'], 500);
        }

        $client = DB::table('clients')->where('code', $code)->where('grp', 'KC')->first();
        if (!$client) {
            return $this->json(['success' => false, 'error' => 'Party not found in KC group'], 404);
        }

        $name    = trim((string) ($client->name ?? ''));
        $addr    = trim(implode(' ', array_filter([
            trim((string) ($client->addr1 ?? '')),
            trim((string) ($client->addr2 ?? '')),
            trim((string) ($client->addr3 ?? '')),
        ])));

        $totamt  = 0.0;
        $bonus   = 0.0;
        $opbal   = 0.0;
        $finished = 'N';
        if ($this->hasTable('clients_kuridet')) {
            $kd = DB::table('clients_kuridet')->where('code', $code)->first();
            if ($kd) {
                $totamt   = (float) ($kd->totamt ?? 0);
                $bonus    = (float) ($kd->bonus  ?? 0);
                $opbal    = (float) ($kd->collnopbal ?? 0);
                $finished = trim((string) ($kd->finished ?? 'N'));
            }
        }

        $tcolln = 0.0;
        if ($this->hasTable('kuricolln')) {
            $tcolln = (float) (DB::table('kuricolln')
                ->where('code', $code)
                ->where('tdate', '<=', $tdate)
                ->where('control', '<=', $control)
                ->sum('amount') ?? 0);
        }
        $tcolln  += $opbal;
        $balance  = $totamt - $tcolln;
        $estwgt   = ($grate > 0) ? round($totamt / $grate, 3) : 0.0;

        return $this->json([
            'success'  => true,
            'code'     => $code,
            'name'     => $name,
            'addr'     => $addr,
            'totamt'   => round($totamt, 2),
            'tcolln'   => round($tcolln, 2),
            'balance'  => round($balance, 2),
            'bonus'    => round($bonus, 2),
            'estwgt'   => $estwgt,
            'finished' => $finished,
        ]);
    }

    // Search parties for autocomplete
    private function actionPartySearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (!$this->hasTable('clients')) {
            return $this->json(['success' => true, 'results' => []]);
        }
        $query = DB::table('clients as c')
            ->join('clients_kuridet as kd', 'kd.code', '=', 'c.code')
            ->selectRaw('TRIM(c.code) AS code, TRIM(c.name) AS name')
            ->where('c.grp', 'KC');

        if ($q !== '') {
            $query->where(function ($sq) use ($q) {
                $sq->where('c.code',  'like', '%' . $q . '%')
                   ->orWhere('c.name', 'like', '%' . $q . '%');
            });
        }
        $results = $query->orderBy('c.name')->limit(30)->get()->all();
        return $this->json(['success' => true, 'results' => $results]);
    }

    // Save Close Scheme (ftype='F') for multiple parties
    private function actionSaveFinish(Request $request, int $control): JsonResponse
    {
        $tdate  = trim((string) $request->input('tdate', date('Y-m-d')));
        $grate  = round((float) $request->input('grate', 0), 2);
        $rows   = $request->input('rows', []);
        $userId = (string) $request->session()->get('user_id',
                       $request->session()->get('user_code', ''));

        if (!is_array($rows) || count($rows) === 0) {
            return $this->json(['success' => false, 'error' => 'No rows to save'], 422);
        }

        $valid = array_filter($rows, fn($r) => !empty($r['code']) && !empty($r['sel']));
        if (count($valid) === 0) {
            return $this->json(['success' => false, 'error' => 'No rows selected'], 422);
        }

        $ttime = date('H:i:s');
        DB::beginTransaction();
        try {
            $saved = 0;
            foreach ($valid as $row) {
                $code  = strtoupper(trim((string) ($row['code'] ?? '')));
                $bonus = round((float) ($row['bonus'] ?? 0), 2);

                // Check not already finished
                if ($this->hasTable('clients_kuridet')) {
                    $fin = DB::table('clients_kuridet')->where('code', $code)->value('finished');
                    if ($fin !== null && $fin !== 'N') continue;
                }

                $slno  = $this->nextSerial();
                $vchno = $this->nextVoucher($control);

                if ($this->hasTable('kurifinishdet')) {
                    DB::table('kurifinishdet')->insert($this->f('kurifinishdet', [
                        'slno'    => $slno,
                        'tdate'   => $tdate,
                        'code'    => $code,
                        'ftype'   => 'F',
                        'bonus'   => $bonus,
                        'allocamt'=> 0,
                        'control' => $control,
                        'grate'   => $grate,
                    ]));
                }

                DB::table('clients_kuridet')
                    ->where('code', $code)
                    ->update(['finished' => 'Y', 'finisheddate' => $tdate]);

                if ($bonus != 0) {
                    $particular = 'Kuri Finish(Bonus)-' . $code;
                    if ($this->hasTable('daybookpart')) {
                        DB::table('daybookpart')->insert($this->f('daybookpart', [
                            'slno'       => $slno,
                            'vchno'      => $vchno,
                            'particular' => mb_substr($particular, 0, 60),
                            'ic'         => $userId,
                            'uid'        => $userId,
                            'ttime'      => $ttime,
                            'rate'       => 0,
                        ]));
                    }

                    if ($this->hasTable('daybook')) {
                        DB::table('daybook')->insert($this->f('daybook', [
                            'slno'     => $slno,
                            'tdate'    => $tdate,
                            'accode'   => $code,
                            'amount'   => $bonus,
                            'control'  => $control,
                            'opaccode' => 'KBONUS',
                        ]));
                        DB::table('daybook')->insert($this->f('daybook', [
                            'slno'     => $slno,
                            'tdate'    => $tdate,
                            'accode'   => 'KBONUS',
                            'amount'   => -$bonus,
                            'control'  => $control,
                            'opaccode' => $code,
                        ]));
                    }
                }

                $saved++;
            }

            if ($saved === 0) {
                DB::rollBack();
                return $this->json(['success' => false, 'error' => 'No valid (un-finished) rows to save'], 422);
            }

            DB::commit();
            return $this->json(['success' => true, 'message' => $saved . ' scheme(s) closed successfully']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    // Save Draw Process (ftype='D') for a single party
    private function actionSaveDraw(Request $request, int $control): JsonResponse
    {
        $tdate  = trim((string) $request->input('tdate', date('Y-m-d')));
        $code   = strtoupper(trim((string) $request->input('code', '')));
        $bonus  = round((float) $request->input('bonus', 0), 2);
        $damt   = round((float) $request->input('damt', 0), 2);   // balance + bonus
        $grate  = round((float) $request->input('grate', 0), 2);
        $userId = (string) $request->session()->get('user_id',
                       $request->session()->get('user_code', ''));

        if ($code === '') {
            return $this->json(['success' => false, 'error' => 'Party code required'], 422);
        }

        $ttime = date('H:i:s');
        DB::beginTransaction();
        try {
            // Delete existing kurifinishdet for this code+date if any (re-do)
            if ($this->hasTable('kurifinishdet')) {
                $existSlno = DB::table('kurifinishdet')
                    ->where('code', $code)->where('tdate', $tdate)->value('slno');
                if ($existSlno !== null) {
                    DB::table('daybook')->where('slno', $existSlno)->delete();
                    DB::table('daybookpart')->where('slno', $existSlno)->delete();
                    DB::table('kurifinishdet')->where('slno', $existSlno)->delete();
                }
            }

            $slno  = $this->nextSerial();
            $vchno = $this->nextVoucher($control);

            if ($this->hasTable('kurifinishdet')) {
                DB::table('kurifinishdet')->insert($this->f('kurifinishdet', [
                    'slno'    => $slno,
                    'tdate'   => $tdate,
                    'code'    => $code,
                    'ftype'   => 'D',
                    'bonus'   => $bonus,
                    'allocamt'=> $damt,
                    'control' => $control,
                    'grate'   => $grate,
                ]));
            }

            DB::table('clients_kuridet')
                ->where('code', $code)
                ->update(['finished' => 'D', 'finisheddate' => $tdate]);

            if ($this->hasTable('daybookpart')) {
                DB::table('daybookpart')->insert($this->f('daybookpart', [
                    'slno'       => $slno,
                    'vchno'      => $vchno,
                    'particular' => mb_substr('By Kuri Draw-' . $code, 0, 60),
                    'ic'         => $userId,
                    'uid'        => $userId,
                    'ttime'      => $ttime,
                    'rate'       => 0,
                ]));
            }

            if ($damt != 0 && $this->hasTable('daybook')) {
                DB::table('daybook')->insert($this->f('daybook', [
                    'slno'     => $slno,
                    'tdate'    => $tdate,
                    'accode'   => $code,
                    'amount'   => $damt,
                    'control'  => $control,
                    'opaccode' => 'KDRAW',
                ]));
                DB::table('daybook')->insert($this->f('daybook', [
                    'slno'     => $slno,
                    'tdate'    => $tdate,
                    'accode'   => 'KDRAW',
                    'amount'   => -$damt,
                    'control'  => $control,
                    'opaccode' => $code,
                ]));
            }

            DB::commit();
            return $this->json([
                'success' => true,
                'message' => 'Draw saved for ' . $code . '. Voucher: ' . $vchno,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    // Save Refund (ftype='R') for a single party
    private function actionSaveRefund(Request $request, int $control): JsonResponse
    {
        $tdate     = trim((string) $request->input('tdate', date('Y-m-d')));
        $code      = strtoupper(trim((string) $request->input('code', '')));
        $refundamt = round((float) $request->input('refundamt', 0), 2);
        $grate     = round((float) $request->input('grate', 0), 2);
        $userId    = (string) $request->session()->get('user_id',
                          $request->session()->get('user_code', ''));

        if ($code === '') {
            return $this->json(['success' => false, 'error' => 'Party code required'], 422);
        }
        if ($refundamt <= 0) {
            return $this->json(['success' => false, 'error' => 'Refund amount must be > 0'], 422);
        }

        $ttime = date('H:i:s');
        DB::beginTransaction();
        try {
            $slno  = $this->nextSerial();
            $vchno = $this->nextVoucher($control);

            if ($this->hasTable('kurifinishdet')) {
                DB::table('kurifinishdet')->insert($this->f('kurifinishdet', [
                    'slno'    => $slno,
                    'tdate'   => $tdate,
                    'code'    => $code,
                    'ftype'   => 'R',
                    'bonus'   => 0,
                    'allocamt'=> $refundamt,
                    'control' => $control,
                    'grate'   => $grate,
                ]));
            }

            DB::table('clients_kuridet')
                ->where('code', $code)
                ->update(['finished' => 'R', 'finisheddate' => $tdate]);

            if ($this->hasTable('daybookpart')) {
                DB::table('daybookpart')->insert($this->f('daybookpart', [
                    'slno'       => $slno,
                    'vchno'      => $vchno,
                    'particular' => mb_substr('Kuri Refund-' . $code, 0, 60),
                    'ic'         => $userId,
                    'uid'        => $userId,
                    'ttime'      => $ttime,
                    'rate'       => 0,
                ]));
            }

            if ($this->hasTable('daybook')) {
                // Debit customer (refund going out to customer)
                DB::table('daybook')->insert($this->f('daybook', [
                    'slno'     => $slno,
                    'tdate'    => $tdate,
                    'accode'   => $code,
                    'amount'   => $refundamt,
                    'control'  => $control,
                    'opaccode' => 'KREFUND',
                ]));
                DB::table('daybook')->insert($this->f('daybook', [
                    'slno'     => $slno,
                    'tdate'    => $tdate,
                    'accode'   => 'KREFUND',
                    'amount'   => -$refundamt,
                    'control'  => $control,
                    'opaccode' => $code,
                ]));
            }

            DB::commit();
            return $this->json([
                'success' => true,
                'message' => 'Refund saved for ' . $code . '. Voucher: ' . $vchno,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}
