<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SuspenseEntryController extends Controller
{
    private function requireLogin(Request $request): void
    {
        if (!$request->session()->has('user_code')) {
            abort(redirect('/login'));
        }
    }

    private function json(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    private function trimUpper($v): string
    {
        return strtoupper(trim((string) $v));
    }

    private function parseDate(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '00/00/0000') return null;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw)) return $raw;
        return null;
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

    private function getColumns(string $table): array
    {
        if (!$this->hasTable($table)) return [];
        return array_map('strtolower', $this->columnList($table));
    }

    private function getAccountBalance(string $accode, int $control): float
    {
        if ($accode === '' || !$this->hasTable('daybook')) return 0;
        $sum = (float) DB::table('daybook')
            ->whereRaw('TRIM(accode) = ?', [$accode])
            ->where('control', $control)
            ->sum('amount');
        if ($this->hasTable('accountm')) {
            $ob = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$accode])->first();
            if ($ob) {
                $opbal = ($control === 1) ? (float) ($ob->opbal ?? 0) : (float) ($ob->opbalb ?? 0);
                $sum += $opbal;
            }
        }
        return $sum;
    }

    private function nextVoucher(string $rp, int $control): string
    {
        $isPayment = strtoupper($rp) === 'P';
        if ($isPayment) {
            if ($control === 1) return 'VPB/' . str_pad((string) ($this->genInt('VCHNOPB') + 1), 5, '0', STR_PAD_LEFT);
            return 'VPE/' . str_pad((string) ($this->genInt('VCHNOPE') + 1), 5, '0', STR_PAD_LEFT);
        }
        if ($control === 1) return 'VRB/' . str_pad((string) ($this->genInt('VCHNORB') + 1), 5, '0', STR_PAD_LEFT);
        return 'VRE/' . str_pad((string) ($this->genInt('VCHNORE') + 1), 5, '0', STR_PAD_LEFT);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('account.suspense-entry');
    }

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        $action = strtolower(trim((string) $request->input('action', '')));
        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control <= 0) $control = 1;

        return match ($action) {
            'init' => $this->actionInit($request, $control),
            'account_list' => $this->actionAccountList($request),
            'suspense_list' => $this->actionSuspenseList($request),
            'against_list' => $this->actionAgainstList($request),
            'load_account' => $this->actionLoadAccount($request, $control),
            'load' => $this->actionLoad($request, $control),
            'save' => $this->actionSave($request, $control),
            default => $this->json(['success' => false, 'error' => 'Invalid action'], 400),
        };
    }

    private function actionInit(Request $request, int $control): JsonResponse
    {
        $rp = strtoupper(substr(trim((string) $request->input('rp', 'P')), 0, 1));
        if (!in_array($rp, ['P', 'R'], true)) $rp = 'P';

        $cbAccounts = [];
        if ($this->hasTable('accountm')) {
            $cbAccounts = DB::table('accountm')
                ->selectRaw('TRIM(accode) AS accode, TRIM(name) AS name')
                ->whereRaw("TRIM(actype2) IN ('H','B')")
                ->when(Schema::hasColumn('accountm', 'removed'), function ($q) {
                    $q->whereRaw('(removed <> 1 OR removed IS NULL)');
                })
                ->orderBy('accode')
                ->get()
                ->all();
        }

        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')
                ->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
                ->orderBy('code')
                ->get()
                ->all();
        }

        $defaultSusp = trim((string) ($request->session()->get('susp_accode') ?? ''));
        if ($defaultSusp === '' && $this->hasTable('generald')) {
            $defaultSusp = trim((string) (DB::table('generald')->where('code', 'SUSPACCODE')->value('cvalue') ?? ''));
        }

        return $this->json([
            'success' => true,
            'today' => date('d/m/Y'),
            'rp' => $rp,
            'nextVchno' => $this->nextVoucher($rp, $control),
            'cbAccounts' => $cbAccounts,
            'salesmen' => $salesmen,
            'defaultSuspAccode' => $defaultSusp,
        ]);
    }

    private function actionAccountList(Request $request): JsonResponse
    {
        if (!$this->hasTable('accountm')) return $this->json(['success' => true, 'data' => []]);
        $search = trim((string) $request->input('search', ''));
        $q = DB::table('accountm')->selectRaw('TRIM(accode) AS accode, TRIM(name) AS name, TRIM(actype2) AS actype2');
        if (Schema::hasColumn('accountm', 'removed')) {
            $q->whereRaw('(removed <> 1 OR removed IS NULL)');
        }
        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(function ($x) use ($like) {
                $x->whereRaw('TRIM(accode) LIKE ?', [$like])
                  ->orWhereRaw('TRIM(name) LIKE ?', [$like]);
            });
        }
        return $this->json(['success' => true, 'data' => $q->orderBy('name')->limit(300)->get()->all()]);
    }

    private function actionSuspenseList(Request $request): JsonResponse
    {
        if (!$this->hasTable('suspentry')) return $this->json(['success' => true, 'data' => []]);
        $search = trim((string) $request->input('search', ''));
        $q = DB::table('suspentry')->selectRaw('TRIM(scode) AS scode')->whereRaw("TRIM(scode) <> ''");
        if ($search !== '') {
            $q->whereRaw('TRIM(scode) LIKE ?', ['%' . $search . '%']);
        }
        $rows = $q->groupBy('scode')->orderBy('scode')->limit(200)->get()->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    private function actionLoadAccount(Request $request, int $control): JsonResponse
    {
        $accode = $this->trimUpper($request->input('accode', ''));
        if ($accode === '' || !$this->hasTable('accountm')) {
            return $this->json(['success' => false, 'error' => 'Account not found'], 404);
        }
        $row = DB::table('accountm')->whereRaw('TRIM(accode)=?', [$accode])->first();
        if (!$row) return $this->json(['success' => false, 'error' => 'Account not found'], 404);
        $bal = $this->getAccountBalance($accode, $control);
        return $this->json([
            'success' => true,
            'accode' => $accode,
            'name' => trim((string) ($row->name ?? '')),
            'actype1' => strtoupper(trim((string) ($row->actype1 ?? ''))),
            'actype2' => strtoupper(trim((string) ($row->actype2 ?? ''))),
            'balance' => round($bal, 2),
            'balanceLabel' => $bal > 0 ? 'Credit' : ($bal < 0 ? 'Debit' : ''),
        ]);
    }

    private function actionAgainstList(Request $request): JsonResponse
    {
        if (!$this->hasTable('suspentry')) return $this->json(['success' => true, 'data' => []]);

        $accode = $this->trimUpper($request->input('accode', ''));
        $scode = $this->trimUpper($request->input('scode', ''));
        $ttype = strtoupper(substr(trim((string) $request->input('ttype', '')), 0, 1));
        $search = trim((string) $request->input('search', ''));

        if ($accode === '' || $scode === '' || !in_array($ttype, ['P', 'R'], true)) {
            return $this->json(['success' => true, 'data' => []]);
        }

        $q = DB::table('suspentry')
            ->selectRaw('slno, tdate, TRIM(vchno) AS vchno, TRIM(note) AS note, amount, TRIM(ttype) AS ttype, TRIM(pend) AS pend')
            ->whereRaw('TRIM(accode)=?', [$accode])
            ->whereRaw('TRIM(scode)=?', [$scode])
            ->whereRaw('TRIM(ttype)=?', [$ttype])
            ->whereRaw("(pend IS NULL OR TRIM(pend)='Y')");

        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(function ($x) use ($like) {
                $x->whereRaw('TRIM(vchno) LIKE ?', [$like])
                  ->orWhereRaw('TRIM(note) LIKE ?', [$like]);
            });
        }

        $rows = $q->orderByDesc('slno')->limit(200)->get()->map(function ($r) {
            $dt = '';
            if (!empty($r->tdate)) {
                $ts = strtotime((string) $r->tdate);
                $dt = $ts ? date('d/m/Y', $ts) : (string) $r->tdate;
            }
            return [
                'slno' => (int) ($r->slno ?? 0),
                'tdate' => $dt,
                'vchno' => trim((string) ($r->vchno ?? '')),
                'note' => trim((string) ($r->note ?? '')),
                'amount' => (float) ($r->amount ?? 0),
                'ttype' => trim((string) ($r->ttype ?? '')),
                'pend' => trim((string) ($r->pend ?? 'Y')),
            ];
        })->all();

        return $this->json(['success' => true, 'data' => $rows]);
    }

    private function actionSave(Request $request, int $control): JsonResponse
    {
        $mode = strtoupper(substr(trim((string) $request->input('mode', 'A')), 0, 1));
        $rp = strtoupper(substr(trim((string) $request->input('rp', 'P')), 0, 1));
        if (!in_array($rp, ['P', 'R'], true)) $rp = 'P';
        $tdate = $this->parseDate($request->input('tdate', ''));
        $accode = $this->trimUpper($request->input('accode', ''));
        $cbcode = $this->trimUpper($request->input('cbcode', ''));
        $scode = $this->trimUpper($request->input('scode', ''));
        $staff = trim((string) $request->input('staff', ''));
        $part = trim((string) $request->input('particular', ''));
        $againstVch = $this->trimUpper($request->input('against_vchno', ''));
        $amount = abs((float) $request->input('amount', 0));
        $printSlip = $request->boolean('print_slip');
        $printObCb = $request->boolean('print_obcb');

        if (!$tdate) return $this->json(['success' => false, 'error' => 'Valid date required']);
        if ($accode === '' || $cbcode === '' || $scode === '') return $this->json(['success' => false, 'error' => 'A/C, Cash/Bank and Suspense A/c are required']);
        if ($amount <= 0) return $this->json(['success' => false, 'error' => 'Amount must be > 0']);
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart') || !$this->hasTable('suspentry')) {
            return $this->json(['success' => false, 'error' => 'Required tables missing']);
        }

        try {
            DB::beginTransaction();

            if ($mode === 'E' && (int) $request->input('slno', 0) > 0) {
                $slno = (int) $request->input('slno', 0);
                $vchno = $this->trimUpper($request->input('vchno', ''));
                if ($vchno === '') {
                    $vchno = (string) (DB::table('daybookpart')->where('slno', $slno)->value('vchno') ?? '');
                }
                DB::table('daybook')->where('slno', $slno)->delete();
                DB::table('daybookpart')->where('slno', $slno)->delete();
                DB::table('suspentry')->where('slno', $slno)->delete();
            } else {
                $slno = $this->nextSerialNo();
                if ($rp === 'P') {
                    $vn = $control === 1 ? $this->incrementGenInt('VCHNOPB') : $this->incrementGenInt('VCHNOPE');
                    $vchno = ($control === 1 ? 'VPB/' : 'VPE/') . str_pad((string) $vn, 5, '0', STR_PAD_LEFT);
                } else {
                    $vn = $control === 1 ? $this->incrementGenInt('VCHNORB') : $this->incrementGenInt('VCHNORE');
                    $vchno = ($control === 1 ? 'VRB/' : 'VRE/') . str_pad((string) $vn, 5, '0', STR_PAD_LEFT);
                }
            }

            if ($part === '') {
                $part = ($rp === 'P' ? 'Paid to ' : 'Receipt from ') . $accode . ' - ' . $vchno;
            }
            $part = mb_substr($part, 0, 70);

            $pslno = 0;
            if ($againstVch !== '') {
                $pslno = (int) (DB::table('suspentry')->whereRaw('TRIM(vchno)=?', [$againstVch])->value('slno') ?? 0);
            }
            $pend = $pslno > 0 ? 'N' : 'Y';

            $dbCols = $this->getColumns('daybook');
            $dpCols = $this->getColumns('daybookpart');
            $spCols = $this->getColumns('suspentry');

            $dp = [
                'slno' => $slno,
                'vchno' => $vchno,
                'particular' => $part,
                'staff' => $staff,
                'chequeno' => '',
                'chequedate' => null,
                'ic' => (string) ($request->session()->get('incharge') ?? ''),
                'duedate' => null,
                'uid' => (string) ($request->session()->get('user_code') ?? ''),
                'slno2' => $pslno,
                'ttime' => date('H:i:s'),
                'tdate' => $tdate,
                'control' => $control,
            ];
            DB::table('daybookpart')->insert(array_filter($dp, fn ($k) => in_array($k, $dpCols, true), ARRAY_FILTER_USE_KEY));

            $acAmount = $rp === 'P' ? -round($amount, 2) : round($amount, 2);
            $cbAmount = $rp === 'P' ? round($amount, 2) : -round($amount, 2);

            $rowA = ['slno' => $slno, 'tdate' => $tdate, 'accode' => $accode, 'amount' => $acAmount, 'control' => $control, 'opaccode' => $cbcode];
            DB::table('daybook')->insert(array_filter($rowA, fn ($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY));
            $rowB = ['slno' => $slno, 'tdate' => $tdate, 'accode' => $cbcode, 'amount' => $cbAmount, 'control' => $control, 'opaccode' => $accode];
            DB::table('daybook')->insert(array_filter($rowB, fn ($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY));

            $sp = [
                'slno' => $slno,
                'tdate' => $tdate,
                'accode' => $accode,
                'scode' => $scode,
                'vchno' => $vchno,
                'pslno' => $pslno,
                'note' => $part,
                'amount' => $acAmount,
                'pend' => $pend,
                'control' => $control,
                'ttype' => $rp,
            ];
            DB::table('suspentry')->insert(array_filter($sp, fn ($k) => in_array($k, $spCols, true), ARRAY_FILTER_USE_KEY));

            if ($this->hasTable('software')) {
                DB::table('software')->where('code', 'SuspAccode')->update(['cvalue' => $accode]);
            }

            DB::commit();

            return $this->json([
                'success' => true,
                'message' => 'Saved',
                'slno' => $slno,
                'vchno' => $vchno,
                'print_slip' => $printSlip,
                'print_obcb' => $printObCb,
                'nextVchno' => $this->nextVoucher($rp, $control),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Save failed: ' . $e->getMessage()], 500);
        }
    }

    private function actionLoad(Request $request, int $control): JsonResponse
    {
        $slno = (int) $request->input('slno', 0);
        if ($slno <= 0) return $this->json(['success' => false, 'error' => 'Invalid slno'], 422);
        if (!$this->hasTable('suspentry')) return $this->json(['success' => false, 'error' => 'suspentry table missing'], 500);

        $sp = DB::table('suspentry')->where('slno', $slno)->first();
        if (!$sp) return $this->json(['success' => false, 'error' => 'Record not found'], 404);

        $dp = $this->hasTable('daybookpart') ? DB::table('daybookpart')->where('slno', $slno)->first() : null;
        $dbRows = $this->hasTable('daybook') ? DB::table('daybook')->where('slno', $slno)->get() : collect();

        $cbcode = '';
        foreach ($dbRows as $r) {
            if (trim((string) ($r->accode ?? '')) !== trim((string) ($sp->accode ?? ''))) {
                $cbcode = trim((string) ($r->accode ?? ''));
                break;
            }
        }

        $tdate = '';
        if (!empty($sp->tdate)) {
            $ts = strtotime((string) $sp->tdate);
            $tdate = $ts ? date('d/m/Y', $ts) : (string) $sp->tdate;
        }

        return $this->json([
            'success' => true,
            'data' => [
                'slno' => $slno,
                'vchno' => trim((string) ($sp->vchno ?? '')),
                'tdate' => $tdate,
                'rp' => strtoupper(trim((string) ($sp->ttype ?? 'P'))),
                'accode' => trim((string) ($sp->accode ?? '')),
                'scode' => trim((string) ($sp->scode ?? '')),
                'amount' => abs((float) ($sp->amount ?? 0)),
                'particular' => trim((string) ($sp->note ?? '')),
                'against_slno' => (int) ($sp->pslno ?? 0),
                'cbcode' => $cbcode,
                'staff' => trim((string) ($dp->staff ?? '')),
            ],
        ]);
    }
}
