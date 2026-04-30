<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DebitCreditNoteController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    private function ok(array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['ok' => true], $extra));
    }

    private function err(string $msg): JsonResponse
    {
        return response()->json(['ok' => false, 'msg' => $msg]);
    }

    private function trimUpper($v): string
    {
        return strtoupper(trim((string) $v));
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
        $next    = $current + 1;
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

    private function parseDate(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '00/00/0000') return null;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return null;
    }

    private function filterCols(string $table, array $data): array
    {
        $cols = array_map('strtolower', $this->columnList($table));
        return array_filter($data, fn($k) => in_array(strtolower($k), $cols, true), ARRAY_FILTER_USE_KEY);
    }

    private function hasCol(string $table, string $col): bool
    {
        static $cache = [];
        $key = $table . ':' . strtolower($col);
        if (!isset($cache[$key])) {
            $cache[$key] = Schema::hasColumn($table, $col);
        }
        return $cache[$key];
    }

    /** Preview next voucher number without incrementing */
    private function previewVchno(string $type, int $control): string
    {
        if ($type === 'D') {
            $code = ($control === 1) ? 'VCHNODNB' : 'VCHNODNE';
            $pfx  = ($control === 1) ? 'DNB/'     : 'DNE/';
        } else {
            $code = ($control === 1) ? 'VCHNOCNB' : 'VCHNOCNE';
            $pfx  = ($control === 1) ? 'CNB/'     : 'CNE/';
        }
        $n = $this->genInt($code) + 1;
        return $pfx . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    /** Increment counter and return generated voucher number */
    private function generateVchno(string $type, int $control): string
    {
        if ($type === 'D') {
            $code = ($control === 1) ? 'VCHNODNB' : 'VCHNODNE';
            $pfx  = ($control === 1) ? 'DNB/'     : 'DNE/';
        } else {
            $code = ($control === 1) ? 'VCHNOCNB' : 'VCHNOCNE';
            $pfx  = ($control === 1) ? 'CNB/'     : 'CNE/';
        }
        $n = $this->incrementGenInt($code);
        return $pfx . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    /** Compute running balance for an account: -(opbal + daybook_sum) */
    private function getAccountBalance(string $code, int $control): float
    {
        if (!$this->hasTable('accountm')) return 0.0;

        $balCol = ($control === 1) ? 'opbal' : 'opbalb';
        $opbal  = 0.0;
        if ($this->hasCol('accountm', $balCol)) {
            $opbal = (float) (DB::table('accountm')
                ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
                ->value($balCol) ?? 0);
        }

        $daySum = 0.0;
        if ($this->hasTable('daybook')) {
            if ($this->hasCol('daybook', 'control')) {
                $daySum = (float) (DB::table('daybook')
                    ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
                    ->where('control', '<=', $control)
                    ->sum('amount') ?? 0);
            } else {
                $daySum = (float) (DB::table('daybook')
                    ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
                    ->sum('amount') ?? 0);
            }
        }

        return -($opbal + $daySum);
    }

    // ── Entry points ──────────────────────────────────────────────────────────

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }
        return view('account.debit-credit-note');
    }

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->err('Unauthorized');
        }

        $action  = (string) ($request->input('action', ''));
        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control < 1 || $control > 2) $control = 1;

        return match ($action) {
            'init'           => $this->actionInit($request, $control),
            'load_account'   => $this->actionLoadAccount($request, $control),
            'account_search' => $this->actionAccountSearch($request),
            'save'           => $this->actionSave($request, $control),
            default          => $this->err('Unknown action'),
        };
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    private function actionInit(Request $request, int $control): JsonResponse
    {
        $today    = date('d/m/Y');
        $salesmen = [];

        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')
                ->select('code', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn($r) => ['code' => (string) $r->code, 'name' => (string) $r->name])
                ->values()
                ->all();
        }

        return $this->ok([
            'today'    => $today,
            'vchnoCN'  => $this->previewVchno('C', $control),
            'vchnoDN'  => $this->previewVchno('D', $control),
            'control'  => $control,
            'salesmen' => $salesmen,
        ]);
    }

    private function actionLoadAccount(Request $request, int $control): JsonResponse
    {
        $code = $this->trimUpper($request->input('code', ''));
        if ($code === '') return $this->err('Account code required');
        if (!$this->hasTable('accountm')) return $this->err('accountm table not found');

        $row = DB::table('accountm')
            ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
            ->first();

        if (!$row) return $this->err('This code does not exist...');

        if (isset($row->removed) && (int) $row->removed === 1) {
            return $this->err('This code is removed...');
        }
        if (isset($row->blocked) && strtoupper(trim((string) ($row->blocked ?? ''))) === 'Y') {
            return $this->err('Account is blocked...Sorry....');
        }

        $balance = $this->getAccountBalance($code, $control);
        $actype2 = strtoupper(trim((string) ($row->actype2 ?? '')));

        // Direction label matching PB acbalance2 display logic
        $customerTypes = ['C', 'S', 'G', 'J', 'R'];
        if (in_array($actype2, $customerTypes, true)) {
            $label = $balance > 0.005 ? 'To Give' : ($balance < -0.005 ? 'To Receive' : '');
        } else {
            $label = $balance > 0.005 ? 'Credit' : ($balance < -0.005 ? 'Debit' : '');
        }

        return $this->ok([
            'name'    => (string) ($row->name ?? ''),
            'balance' => round(abs($balance), 2),
            'label'   => $label,
        ]);
    }

    private function actionAccountSearch(Request $request): JsonResponse
    {
        if (!$this->hasTable('accountm')) return $this->ok(['data' => []]);

        $q = trim((string) $request->input('q', ''));
        if ($q === '') return $this->ok(['data' => []]);

        $query = DB::table('accountm')
            ->select('accode', 'name')
            ->where(function ($qb) use ($q): void {
                $qb->where('accode', 'like', $q . '%')
                   ->orWhere('name', 'like', '%' . $q . '%');
            });

        if ($this->hasCol('accountm', 'removed')) {
            $query->where(function ($qb): void {
                $qb->whereNull('removed')->orWhere('removed', '!=', 1);
            });
        }

        $rows = $query->orderBy('accode')
            ->limit(30)
            ->get()
            ->map(fn($r) => ['code' => (string) $r->accode, 'name' => (string) $r->name])
            ->all();

        return $this->ok(['data' => $rows]);
    }

    private function actionSave(Request $request, int $control): JsonResponse
    {
        $tdate    = $this->parseDate($request->input('tdate'));
        $stype    = strtoupper(substr(trim((string) $request->input('type', 'C')), 0, 1));
        $saccode  = $this->trimUpper($request->input('accode', ''));
        $sadjac   = $this->trimUpper($request->input('adjac', ''));
        $sstaff   = trim((string) $request->input('staff', ''));
        $spart    = trim((string) $request->input('part', ''));
        $damt     = round((float) $request->input('amount', 0), 2);
        $dtaxperc = round((float) $request->input('taxperc', 0), 2);
        $dtaxamt  = round((float) $request->input('taxamt', 0), 2);
        $dnetamt  = round($damt + $dtaxamt, 2);

        if (!$tdate)            return $this->err('Invalid date');
        if ($saccode === '')    return $this->err('Main account required');
        if ($sadjac === '')     return $this->err('Adjusted account required');
        if ($damt <= 0)         return $this->err('Amount must be greater than zero');
        if ($stype !== 'D' && $stype !== 'C') $stype = 'C';

        try {
            $vchno = '';
            DB::transaction(function () use (
                $tdate, $stype, $saccode, $sadjac, $sstaff, $spart,
                $damt, $dtaxperc, $dtaxamt, $dnetamt, $control, &$vchno
            ): void {
                $lslno = $this->nextSerialNo();
                $vchno = $this->generateVchno($stype, $control);

                // Auto-generate particulars if blank
                if ($spart === '') {
                    $spart = ($stype === 'D')
                        ? 'Debit Note to ' . $saccode . ' - ' . $vchno
                        : 'Credit Note to ' . $saccode . ' - ' . $vchno;
                }
                $spart = mb_substr(strtoupper($spart), 0, 70);

                // Insert daybookpart
                $dbpData = $this->filterCols('daybookpart', [
                    'slno'       => $lslno,
                    'vchno'      => $vchno,
                    'particular' => $spart,
                    'staff'      => $sstaff,
                    'ic'         => $sstaff,
                    'uid'        => '',
                    'slno2'      => 0,
                    'ttime'      => now()->format('H:i:s'),
                    'tdate'      => $tdate,
                    'taxperc'    => $dtaxperc,
                    'taxamt'     => $dtaxamt,
                    'control'    => $control,
                ]);
                if ($dbpData) DB::table('daybookpart')->insert($dbpData);

                // Build daybook row inserter
                $sno         = 0;
                $hasSno      = $this->hasCol('daybook', 'sno');
                $hasOpaccode = $this->hasCol('daybook', 'opaccode');
                $hasControl  = $this->hasCol('daybook', 'control');

                $insertDaybook = function (
                    string $accode, float $amount, string $opaccode = ''
                ) use ($lslno, $tdate, $control, &$sno, $hasSno, $hasOpaccode, $hasControl): void {
                    $sno++;
                    $row = ['slno' => $lslno, 'tdate' => $tdate, 'accode' => $accode, 'amount' => $amount];
                    if ($hasControl)  $row['control']  = $control;
                    if ($hasSno)      $row['sno']      = $sno;
                    if ($hasOpaccode) $row['opaccode'] = $opaccode;
                    $row = $this->filterCols('daybook', $row);
                    if ($row) DB::table('daybook')->insert($row);
                };

                /*
                 * Double-entry logic (mirrors PB cb_ok event):
                 * Credit Note (CN): saccode +dnetamt, sadjac -damt
                 * Debit Note  (DN): saccode -dnetamt, sadjac +damt
                 * Tax rows (SGST/CGST): sign follows note type
                 */
                if ($stype === 'D') {
                    $insertDaybook($saccode, -$dnetamt, '');
                    $insertDaybook($sadjac,  +$damt,    $saccode);
                } else {
                    $insertDaybook($saccode, +$dnetamt, '');
                    $insertDaybook($sadjac,  -$damt,    $saccode);
                }

                if ($dtaxamt > 0) {
                    $taxHalf = round($dtaxamt / 2, 2);
                    if ($stype === 'D') {
                        $insertDaybook('SGST', +$taxHalf, $saccode);
                        $insertDaybook('CGST', +$taxHalf, $saccode);
                    } else {
                        $insertDaybook('SGST', -$taxHalf, $saccode);
                        $insertDaybook('CGST', -$taxHalf, $saccode);
                    }
                }

                // Rounding: sum of all entries for this slno must equal 0
                $dround = (float) DB::table('daybook')->where('slno', $lslno)->sum('amount');
                if (abs($dround) > 0.001) {
                    $insertDaybook('ROUND', -$dround, $saccode);
                }
            });
        } catch (\Throwable $e) {
            return $this->err('Save failed: ' . $e->getMessage());
        }

        return $this->ok([
            'vchno'   => $vchno,
            'message' => 'Entry saved successfully',
        ]);
    }
}
