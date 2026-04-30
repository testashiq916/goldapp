<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PdcCollectionController extends Controller
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
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw)) return $raw;
        return null;
    }

    private function fmtDate($raw): string
    {
        if (!$raw) return '';
        $ts = strtotime((string) $raw);
        return $ts ? date('d/m/Y', $ts) : (string) $raw;
    }

    private function getLevel(Request $request): int
    {
        $lvl = (int) ($request->session()->get('gilevel', 1));
        return $lvl > 0 ? $lvl : 1;
    }

    private function getColumns(string $table): array
    {
        if (!$this->hasTable($table)) return [];
        return array_map('strtolower', $this->columnList($table));
    }

    private function genInt(string $code): int
    {
        if (!$this->hasTable('generali')) return 0;
        return (int) (DB::table('generali')->where('code', $code)->value('cvalue') ?? 0);
    }

    private function incrementGenInt(string $code): int
    {
        if (!$this->hasTable('generali')) return 1;
        $cur = $this->genInt($code);
        $next = $cur + 1;
        $updated = DB::table('generali')->where('code', $code)->update(['cvalue' => $next]);
        if ($updated === 0) DB::table('generali')->insert(['code' => $code, 'cvalue' => $next]);
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

    private function nextJlVoucher(int $level): string
    {
        if ($level === 1) {
            return 'JL/' . str_pad((string) ($this->genInt('VCHNOJB') + 1), 5, '0', STR_PAD_LEFT);
        }
        return 'JL ' . str_pad((string) ($this->genInt('VCHNOJE') + 1), 5, '0', STR_PAD_LEFT);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        $module = trim((string) $request->query('module', 'cheque-clearance'));
        return view('account.pdc-collection', [
            'module' => $module,
            'prefillSlno' => (int) $request->query('slno', 0),
            'prefillChequeNo' => strtoupper(trim((string) $request->query('chqno', ''))),
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action = strtolower(trim((string) $request->input('action', '')));
        return match ($action) {
            'init' => $this->actionInit($request),
            'bank_list' => $this->actionBankList($request),
            'cheque_help' => $this->actionChequeHelp($request),
            'lookup_cheque' => $this->actionLookupCheque($request),
            'save' => $this->actionSave($request),
            default => $this->json(['success' => false, 'error' => 'Invalid action'], 400),
        };
    }

    private function actionInit(Request $request): JsonResponse
    {
        $level = $this->getLevel($request);
        return $this->json([
            'success' => true,
            'today' => date('d/m/Y'),
            'vchno' => $this->nextJlVoucher($level),
            'fr' => true,
        ]);
    }

    private function actionBankList(Request $request): JsonResponse
    {
        if (!$this->hasTable('accountm')) return $this->json(['success' => true, 'data' => []]);
        $rows = DB::table('accountm')
            ->selectRaw('TRIM(accode) AS accode, TRIM(name) AS name')
            ->whereRaw("TRIM(actype2) IN ('H','B')")
            ->orderBy('accode')
            ->limit(400)
            ->get()
            ->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    private function actionChequeHelp(Request $request): JsonResponse
    {
        if (!$this->hasTable('pdclist')) return $this->json(['success' => true, 'data' => []]);
        $search = trim((string) $request->input('search', ''));
        $q = DB::table('pdclist')
            ->selectRaw('MAX(slno) AS slno, TRIM(chqno) AS chqno, TRIM(docno) AS docno, TRIM(code) AS code, TRIM(pend) AS pend, MAX(chqdate) AS chqdate, MAX(amount) AS amount')
            ->whereRaw("TRIM(chqno) <> ''")
            ->whereRaw("(pend IS NULL OR TRIM(pend)='Y')");
        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(function ($x) use ($like) {
                $x->whereRaw('TRIM(chqno) LIKE ?', [$like])
                  ->orWhereRaw('TRIM(docno) LIKE ?', [$like])
                  ->orWhereRaw('TRIM(code) LIKE ?', [$like]);
            });
        }
        $rows = $q->groupBy('chqno', 'docno', 'code', 'pend')->orderByDesc('slno')->limit(300)->get()->map(function ($r) {
            return [
                'slno' => (int) ($r->slno ?? 0),
                'chqno' => trim((string) ($r->chqno ?? '')),
                'docno' => trim((string) ($r->docno ?? '')),
                'code' => trim((string) ($r->code ?? '')),
                'chqdate' => $this->fmtDate($r->chqdate ?? null),
                'amount' => (float) ($r->amount ?? 0),
            ];
        })->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    private function actionLookupCheque(Request $request): JsonResponse
    {
        $chqno = $this->trimUpper($request->input('chqno', ''));
        if ($chqno === '' || !$this->hasTable('pdclist')) {
            return $this->json(['success' => false, 'error' => 'Cheque no required'], 422);
        }

        $row = DB::table('pdclist')
            ->selectRaw('slno, TRIM(docno) AS docno, TRIM(code) AS code, TRIM(bank) AS bank, amount, chqdate, TRIM(pend) AS pend, TRIM(rp) AS rp')
            ->whereRaw('TRIM(chqno)=?', [$chqno])
            ->orderByDesc('slno')
            ->first();
        if (!$row) {
            return $this->json(['success' => false, 'error' => 'Cheque no does not exist in PDC list'], 404);
        }
        if (strtoupper(trim((string) ($row->pend ?? 'Y'))) === 'N') {
            return $this->json(['success' => false, 'error' => 'This cheque is not pending'], 422);
        }

        $code = trim((string) ($row->code ?? ''));
        $name = '';
        if ($code !== '' && $this->hasTable('accountm')) {
            $name = trim((string) (DB::table('accountm')->whereRaw('TRIM(accode)=?', [$code])->value('name') ?? ''));
        }
        if ($name === '' && $code !== '' && $this->hasTable('clients')) {
            $name = trim((string) (DB::table('clients')->whereRaw('TRIM(code)=?', [$code])->value('name') ?? ''));
        }

        $rp = strtoupper(trim((string) ($row->rp ?? '')));
        $typeText = $rp === 'P' ? 'PAID CHQ' : ($rp === 'R' ? 'RECEIVED CHQ' : '');

        return $this->json([
            'success' => true,
            'data' => [
                'sidocno' => trim((string) ($row->docno ?? '')),
                'party_code' => $code,
                'party_name' => $name,
                'bank' => trim((string) ($row->bank ?? '')),
                'amount' => (float) ($row->amount ?? 0),
                'chqdate' => $this->fmtDate($row->chqdate ?? null),
                'rp' => $rp,
                'type_text' => $typeText,
            ],
        ]);
    }

    private function actionSave(Request $request): JsonResponse
    {
        if (!$this->hasTable('pdclist') || !$this->hasTable('daybook') || !$this->hasTable('daybookpart')) {
            return $this->json(['success' => false, 'error' => 'Required tables missing'], 500);
        }

        $mode = strtoupper(substr(trim((string) $request->input('mode', 'A')), 0, 1));
        if (!in_array($mode, ['A', 'E'], true)) $mode = 'A';

        $sidocno = $this->trimUpper($request->input('sidocno', ''));
        $tdate = $this->parseDate($request->input('tdate', ''));
        $chqdate = $this->parseDate($request->input('chqdate', ''));
        $chequeno = $this->trimUpper($request->input('chequeno', ''));
        $cashCode = $this->trimUpper($request->input('bank', ''));
        $partyCode = $this->trimUpper($request->input('party_code', ''));
        $amount = round((float) $request->input('amount', 0), 2);
        $expense = round((float) $request->input('expense', 0), 2);
        $scharge = round((float) $request->input('scharge', 0), 2);
        $net = round((float) $request->input('net_amount', 0), 2);
        $bounce = $request->boolean('bounce') ? 'Y' : 'N';

        if (!$tdate) return $this->json(['success' => false, 'error' => 'Valid date required'], 422);
        if ($chequeno === '' || $partyCode === '' || $cashCode === '' || $net <= 0) {
            return $this->json(['success' => false, 'error' => 'Incomplete entry. Cheque, Party, Bank and Net amount are required'], 422);
        }

        $base = DB::table('pdclist')
            ->selectRaw('MAX(slno) AS slno, MAX(TRIM(rp)) AS rp, MAX(control) AS control, MAX(TRIM(particulars)) AS particulars')
            ->whereRaw('TRIM(chqno)=?', [$chequeno])
            ->first();
        $baseSlno = (int) ($base->slno ?? 0);
        $srp = strtoupper(trim((string) ($base->rp ?? 'R')));
        $control = (int) ($base->control ?? $this->getLevel($request));
        if ($control <= 0) $control = $this->getLevel($request);
        $sacpart = trim((string) ($base->particulars ?? ''));

        if ($sidocno === '') {
            $sidocno = trim((string) (DB::table('pdclist')->whereRaw('TRIM(chqno)=?', [$chequeno])->orderByDesc('slno')->value('docno') ?? ''));
        }

        $dtDueDate = null;
        $ttime = date('H:i:s');
        $ic = (string) ($request->session()->get('incharge') ?? '');
        $uid = (string) ($request->session()->get('user_code') ?? '');
        $gdgrate = (float) ($request->session()->get('gdgrate') ?? 0);

        $dbCols = $this->getColumns('daybook');
        $dpCols = $this->getColumns('daybookpart');

        try {
            DB::beginTransaction();

            if ($mode === 'E') {
                $slno = (int) $request->input('slno', 0);
                $vchno = trim((string) $request->input('vchno', ''));
                if ($slno <= 0 && $vchno !== '') {
                    $slno = (int) (DB::table('daybookpart')->whereRaw('TRIM(vchno)=?', [$vchno])->max('slno') ?? 0);
                }
                if ($slno <= 0) throw new \RuntimeException('Edit record not found');

                DB::table('collection')->where('slno', $slno)->delete();
                DB::table('daybook')->where('slno', $slno)->delete();
                DB::table('daybookpart')->where('slno', $slno)->delete();

                if ($sidocno !== '') {
                    DB::table('pdclist')
                        ->whereRaw('TRIM(docno)=?', [$sidocno])
                        ->update(['pend' => 'Y', 'colndate' => $tdate, 'slno2' => 0, 'bankexp' => 0, 'bounced' => 'N']);
                }
            } else {
                if ($srp === 'R') {
                    if ($control === 1) {
                        $vchno = 'VRB/' . str_pad((string) $this->incrementGenInt('VCHNORB'), 5, '0', STR_PAD_LEFT);
                    } else {
                        $vchno = 'VRE ' . str_pad((string) $this->incrementGenInt('VCHNORE'), 5, '0', STR_PAD_LEFT);
                    }
                } else {
                    if ($control === 1) {
                        $vchno = 'VPB/' . str_pad((string) $this->incrementGenInt('VCHNOPB'), 5, '0', STR_PAD_LEFT);
                    } else {
                        $vchno = 'VPE ' . str_pad((string) $this->incrementGenInt('VCHNOPE'), 5, '0', STR_PAD_LEFT);
                    }
                }
                $slno = $this->nextSerialNo();
            }

            if ($bounce === 'Y') {
                $sacpart = 'Cheque Bounced for ' . $chequeno . '-' . $partyCode;
            } else {
                if ($sacpart === '' && $baseSlno > 0) {
                    $sacpart = trim((string) (DB::table('daybookpart')->where('slno', $baseSlno)->value('particular') ?? ''));
                }
                $sacpart = trim($sacpart . ' > Cheque Clearance for ' . $chequeno);
            }
            $sacpart = mb_substr($sacpart, 0, 100);

            $dp = [
                'slno' => $slno,
                'vchno' => $vchno,
                'particular' => $sacpart,
                'staff' => '',
                'chequeno' => $chequeno,
                'chequedate' => $chqdate,
                'ic' => $ic,
                'duedate' => $dtDueDate,
                'uid' => $uid,
                'slno2' => 0,
                'ttime' => $ttime,
                'rate' => $gdgrate,
                'tdate' => $tdate,
            ];
            DB::table('daybookpart')->insert(array_filter($dp, fn ($k) => in_array($k, $dpCols, true), ARRAY_FILTER_USE_KEY));

            $insertDaybook = function (string $accode, float $amt, string $op) use ($slno, $tdate, $control, $dbCols): void {
                $r = ['slno' => $slno, 'tdate' => $tdate, 'accode' => $accode, 'amount' => $amt, 'control' => $control, 'opaccode' => $op];
                DB::table('daybook')->insert(array_filter($r, fn ($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY));
            };

            $dtexpense = $expense + $scharge;
            $dacamt = $srp === 'P' ? -$amount : $amount;
            $insertDaybook($partyCode, $dacamt, $cashCode);

            $dacamt2 = $srp === 'P' ? $net : -$net;
            if ($bounce === 'Y') {
                $dacamtBounce = $srp === 'P' ? ($amount + $expense + $scharge) : -($amount + $expense + $scharge);
                $insertDaybook($partyCode, $dacamtBounce, $cashCode);
                if ($dtexpense > 0) {
                    $dacamtExp = $srp === 'P' ? -$dtexpense : $dtexpense;
                    $insertDaybook($cashCode, $dacamtExp, $partyCode);
                }
            } else {
                $insertDaybook($cashCode, $dacamt2, $partyCode);
            }

            if ($dtexpense != 0 && $bounce === 'N') {
                $dacamtBexp = $srp === 'P' ? $dtexpense : -$dtexpense;
                $insertDaybook('BEXP', $dacamtBexp, $cashCode);
            }

            if ($scharge != 0) {
                $dacamtSc = $srp === 'P' ? -$scharge : $scharge;
                $insertDaybook('SCHARGE', $dacamtSc, $cashCode);
            }

            if ($sidocno !== '') {
                DB::table('pdclist')
                    ->whereRaw('TRIM(docno)=?', [$sidocno])
                    ->update([
                        'pend' => 'N',
                        'colndate' => $tdate,
                        'slno2' => $slno,
                        'bankexp' => $expense,
                        'scharge' => $scharge,
                        'bounced' => $bounce,
                    ]);
            }

            DB::commit();

            return $this->json([
                'success' => true,
                'message' => 'Saved',
                'slno' => $slno,
                'vchno' => $vchno,
                'nextVchno' => $this->nextJlVoucher($this->getLevel($request)),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Save failed: ' . $e->getMessage()], 500);
        }
    }
}
