<?php

namespace App\Http\Controllers;

use App\Support\SecondaryDatabaseSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class JournalController extends Controller
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
        if ($raw === '' || $raw === '00/00/0000') {
            return null;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw)) {
            return $raw;
        }
        return null;
    }

    private function getLevel(Request $request): int
    {
        $lvl = (int) ($request->session()->get('gilevel', 1));
        return $lvl > 0 ? $lvl : 1;
    }

    private function getColumns(string $table): array
    {
        if (!$this->hasTable($table)) {
            return [];
        }
        return array_map('strtolower', $this->columnList($table));
    }

    private function genInt(string $code): int
    {
        if (!$this->hasTable('generali')) {
            return 0;
        }
        return (int) (DB::table('generali')->where('code', $code)->value('cvalue') ?? 0);
    }

    private function incrementGenInt(string $code): int
    {
        if (!$this->hasTable('generali')) {
            return 1;
        }
        $current = $this->genInt($code);
        $next = $current + 1;
        $updated = DB::table('generali')->where('code', $code)->update(['cvalue' => $next]);
        if ($updated === 0) {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => $next]);
        }
        return $next;
    }

    private function nextVoucherNo(int $control): string
    {
        if ($control === 1) {
            return 'JLB/' . str_pad((string) ($this->genInt('VCHNOJB') + 1), 5, '0', STR_PAD_LEFT);
        }
        return 'JLE/' . str_pad((string) ($this->genInt('VCHNOJE') + 1), 5, '0', STR_PAD_LEFT);
    }

    private function nextSerialNo(): int
    {
        if (!$this->hasTable('generali')) {
            return 1;
        }
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

    public function index(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('account.journal', [
            'prefillSlno' => (int) $request->query('slno', 0),
            'prefillVchno' => strtoupper(trim((string) $request->query('vchno', ''))),
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
            'next_vchno' => $this->actionNextVoucher($request),
            'account_list' => $this->actionAccountList($request),
            'account_balance' => $this->actionAccountBalance($request),
            'entry_list' => $this->actionEntryList($request),
            'load' => $this->actionLoad($request),
            'save' => $this->actionSave($request),
            'delete' => $this->actionDelete($request),
            default => $this->json(['success' => false, 'error' => 'Invalid action'], 400),
        };
    }

    private function actionInit(Request $request): JsonResponse
    {
        $control = $this->getLevel($request);
        return $this->json([
            'success' => true,
            'today' => date('d/m/Y'),
            'nextVchno' => $this->nextVoucherNo($control),
            'autoAmtDefault' => true,
        ]);
    }

    private function actionNextVoucher(Request $request): JsonResponse
    {
        return $this->json(['success' => true, 'nextVchno' => $this->nextVoucherNo($this->getLevel($request))]);
    }

    private function actionAccountList(Request $request): JsonResponse
    {
        if (!$this->hasTable('accountm')) {
            return $this->json(['success' => true, 'data' => []]);
        }
        $q = trim((string) $request->input('search', ''));
        $rows = DB::table('accountm')
            ->selectRaw('TRIM(accode) AS accode, TRIM(name) AS name')
            ->when($q !== '', function ($x) use ($q) {
                $like = '%' . $q . '%';
                $x->where(function ($w) use ($like) {
                    $w->whereRaw('TRIM(accode) LIKE ?', [$like])->orWhereRaw('TRIM(name) LIKE ?', [$like]);
                });
            })
            ->orderBy('accode')
            ->limit(400)
            ->get()
            ->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    private function actionAccountBalance(Request $request): JsonResponse
    {
        if (!$this->hasTable('accountm')) {
            return $this->json(['success' => false, 'error' => 'Account table missing'], 500);
        }

        $code = $this->trimUpper($request->input('code', ''));
        if ($code === '') {
            return $this->json(['success' => false, 'error' => 'Account code is required'], 422);
        }

        $date = $this->parseDate((string) $request->input('tdate', '')) ?: date('Y-m-d');
        $row = DB::table('accountm')
            ->selectRaw('TRIM(accode) AS accode, TRIM(name) AS name, opbal, opbalb')
            ->whereRaw('TRIM(accode)=?', [$code])
            ->first();

        if (!$row) {
            return $this->json(['success' => false, 'error' => 'Account not found'], 404);
        }

        $gilevel = $this->getLevel($request);
        $openingSeed = (float) ($gilevel === 1 ? ($row->opbal ?? 0) : ($row->opbalb ?? 0));
        $openingBalance = $openingSeed;
        $closingBalance = $openingSeed;

        if ($this->hasTable('daybook')) {
            $openingBalance += (float) DB::table('daybook')
                ->whereRaw('TRIM(accode)=?', [$code])
                ->where('tdate', '<', $date)
                ->where('control', '<=', $gilevel)
                ->sum('amount');

            $closingBalance += (float) DB::table('daybook')
                ->whereRaw('TRIM(accode)=?', [$code])
                ->where('tdate', '<=', $date)
                ->where('control', '<=', $gilevel)
                ->sum('amount');
        }

        return $this->json([
            'success' => true,
            'data' => [
                'accode' => (string) ($row->accode ?? ''),
                'name' => (string) ($row->name ?? ''),
                'tdate' => date('d/m/Y', strtotime($date)),
                'opening_balance' => $openingBalance,
                'opening_side' => $openingBalance < 0 ? 'Dr' : 'Cr',
                'closing_balance' => $closingBalance,
                'closing_side' => $closingBalance < 0 ? 'Dr' : 'Cr',
            ],
        ]);
    }

    private function actionEntryList(Request $request): JsonResponse
    {
        if (!$this->hasTable('daybookpart')) {
            return $this->json(['success' => true, 'data' => []]);
        }
        $q = trim((string) $request->input('search', ''));
        $rows = DB::table('daybookpart')
            ->selectRaw('slno, TRIM(vchno) AS vchno, TRIM(particular) AS particular')
            ->when($q !== '', function ($x) use ($q) {
                $like = '%' . $q . '%';
                $x->where(function ($w) use ($like) {
                    $w->whereRaw('TRIM(vchno) LIKE ?', [$like])->orWhereRaw('TRIM(particular) LIKE ?', [$like]);
                });
            })
            ->whereRaw("TRIM(vchno) LIKE 'JL%'")
            ->orderByDesc('slno')
            ->limit(300)
            ->get()
            ->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    private function actionLoad(Request $request): JsonResponse
    {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart')) {
            return $this->json(['success' => false, 'error' => 'Required tables missing'], 500);
        }

        $slno = (int) $request->input('slno', 0);
        $vchno = $this->trimUpper($request->input('vchno', ''));
        $control = $this->getLevel($request);

        if ($slno <= 0 && $vchno !== '') {
            $slno = (int) (DB::table('daybookpart')
                ->join('daybook', 'daybook.slno', '=', 'daybookpart.slno')
                ->whereRaw('TRIM(daybookpart.vchno)=?', [$vchno])
                ->where('daybook.control', '<=', $control)
                ->max('daybookpart.slno') ?? 0);
        }
        if ($slno <= 0) {
            return $this->json(['success' => false, 'error' => 'Entry not found'], 404);
        }

        $part = DB::table('daybookpart')->where('slno', $slno)->first();
        if (!$part) {
            return $this->json(['success' => false, 'error' => 'Entry not found'], 404);
        }

        $rows = DB::table('daybook')
            ->selectRaw('TRIM(accode) AS accode, amount')
            ->where('slno', $slno)
            ->orderBy('accode')
            ->get()
            ->map(function ($r) {
                $amt = (float) ($r->amount ?? 0);
                return [
                    'particulars' => trim((string) ($r->accode ?? '')),
                    'amountd' => $amt < 0 ? abs($amt) : 0,
                    'amountc' => $amt > 0 ? abs($amt) : 0,
                ];
            })->all();

        $date = '';
        if (!empty($part->tdate)) {
            $ts = strtotime((string) $part->tdate);
            $date = $ts ? date('d/m/Y', $ts) : (string) $part->tdate;
        } else {
            $d = DB::table('daybook')->where('slno', $slno)->value('tdate');
            if ($d) {
                $ts = strtotime((string) $d);
                $date = $ts ? date('d/m/Y', $ts) : (string) $d;
            }
        }

        return $this->json([
            'success' => true,
            'data' => [
                'slno' => $slno,
                'vchno' => trim((string) ($part->vchno ?? '')),
                'narration' => trim((string) ($part->particular ?? '')),
                'tdate' => $date,
                'rows' => $rows,
            ],
        ]);
    }

    private function actionSave(Request $request): JsonResponse
    {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart')) {
            return $this->json(['success' => false, 'error' => 'Required tables missing'], 500);
        }

        $action = strtoupper(substr(trim((string) $request->input('mode', 'A')), 0, 1));
        if (!in_array($action, ['A', 'E'], true)) {
            $action = 'A';
        }

        $tdate = $this->parseDate($request->input('tdate', ''));
        if (!$tdate) {
            return $this->json(['success' => false, 'error' => 'Valid date required'], 422);
        }

        $rows = $request->input('rows', []);
        if (!is_array($rows) || count($rows) === 0) {
            return $this->json(['success' => false, 'error' => 'Empty entry. You cannot save'], 422);
        }

        $clean = [];
        $ddTotal = 0.0;
        $dcTotal = 0.0;
        $firstDebit = '';
        $firstCredit = '';

        foreach ($rows as $r) {
            $particulars = $this->trimUpper($r['particulars'] ?? '');
            $amountd = round(abs((float) ($r['amountd'] ?? 0)), 2);
            $amountc = round(abs((float) ($r['amountc'] ?? 0)), 2);

            if ($particulars === '' || ($amountd <= 0 && $amountc <= 0)) {
                continue;
            }
            if ($amountd > 0 && $amountc > 0) {
                return $this->json(['success' => false, 'error' => 'Debit and credit cannot both be entered in same row'], 422);
            }

            if (!$this->hasTable('accountm')) {
                return $this->json(['success' => false, 'error' => 'Account master missing'], 500);
            }
            $exists = DB::table('accountm')->whereRaw('TRIM(accode)=?', [$particulars])->exists();
            if (!$exists) {
                return $this->json(['success' => false, 'error' => 'Invalid account code: ' . $particulars], 422);
            }

            if ($amountd > 0) {
                $ddTotal += $amountd;
                if ($firstDebit === '') {
                    $firstDebit = $particulars;
                }
            } else {
                $dcTotal += $amountc;
                if ($firstCredit === '') {
                    $firstCredit = $particulars;
                }
            }

            $clean[] = [
                'particulars' => $particulars,
                'amountd' => $amountd,
                'amountc' => $amountc,
            ];
        }

        if (count($clean) === 0) {
            return $this->json(['success' => false, 'error' => 'Empty entry. You cannot save'], 422);
        }

        $ddTotal = round($ddTotal, 2);
        $dcTotal = round($dcTotal, 2);
        if ($ddTotal !== $dcTotal) {
            return $this->json(['success' => false, 'error' => 'Debit and credit totals are not equal'], 422);
        }

        $narration = trim((string) $request->input('narration', ''));
        $control = $this->getLevel($request);
        $dbCols = $this->getColumns('daybook');
        $dpCols = $this->getColumns('daybookpart');

        try {
            DB::beginTransaction();

            if ($action === 'E') {
                $slno = (int) $request->input('slno', 0);
                $vchno = $this->trimUpper($request->input('vchno', ''));
                if ($slno <= 0 && $vchno !== '') {
                    $slno = (int) (DB::table('daybookpart')->whereRaw('TRIM(vchno)=?', [$vchno])->max('slno') ?? 0);
                }
                if ($slno <= 0) {
                    throw new \RuntimeException('Edit entry not found');
                }
                if ($vchno === '') {
                    $vchno = trim((string) (DB::table('daybookpart')->where('slno', $slno)->value('vchno') ?? ''));
                }
                DB::table('daybook')->where('slno', $slno)->delete();
                DB::table('daybookpart')->where('slno', $slno)->delete();
            } else {
                $slno = $this->nextSerialNo();
                if ($control === 1) {
                    $no = $this->incrementGenInt('VCHNOJB');
                    $vchno = 'JLB/' . str_pad((string) $no, 5, '0', STR_PAD_LEFT);
                } else {
                    $no = $this->incrementGenInt('VCHNOJE');
                    $vchno = 'JLE/' . str_pad((string) $no, 5, '0', STR_PAD_LEFT);
                }
            }

            if ($narration === '') {
                $narration = 'Journal entry no: ' . $vchno;
            }
            $narration = mb_substr($narration, 0, 100);

            foreach ($clean as $r) {
                $isDebit = $r['amountd'] > 0;
                $amount = $isDebit ? -$r['amountd'] : $r['amountc'];
                $op = $isDebit ? $firstCredit : $firstDebit;

                $row = [
                    'slno' => $slno,
                    'tdate' => $tdate,
                    'accode' => $r['particulars'],
                    'amount' => $amount,
                    'control' => $control,
                    'opaccode' => $op,
                ];
                DB::table('daybook')->insert(array_filter($row, fn ($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY));
            }

            $daybookPartRow = [
                'slno' => $slno,
                'vchno' => $vchno,
                'particular' => $narration,
                'tdate' => $tdate,
                'ic' => (string) ($request->session()->get('incharge') ?? ''),
                'uid' => (string) ($request->session()->get('user_code') ?? ''),
            ];
            DB::table('daybookpart')->insert(array_filter($daybookPartRow, fn ($k) => in_array($k, $dpCols, true), ARRAY_FILTER_USE_KEY));

            DB::commit();

            $message = 'Saved';
            if (SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code'))) {
                try {
                    $secondarySync = (new SecondaryDatabaseSync())->sync('journal', (int) $slno);
                    $message .= ' and synced to secondary DB (' . ($secondarySync['database'] ?? '') . ')';
                } catch (\Throwable $e) {
                    $message .= '. Primary save completed, but secondary sync failed: ' . $e->getMessage();
                }
            }

            return $this->json([
                'success' => true,
                'message' => $message,
                'slno' => $slno,
                'vchno' => $vchno,
                'nextVchno' => $this->nextVoucherNo($control),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Save failed: ' . $e->getMessage()], 500);
        }
    }

    private function actionDelete(Request $request): JsonResponse
    {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart')) {
            return $this->json(['success' => false, 'error' => 'Required tables missing'], 500);
        }

        $slno = (int) $request->input('slno', 0);
        if ($slno <= 0) {
            return $this->json(['success' => false, 'error' => 'Invalid slno'], 422);
        }

        try {
            DB::beginTransaction();
            DB::table('daybook')->where('slno', $slno)->delete();
            DB::table('daybookpart')->where('slno', $slno)->delete();
            if ($this->hasTable('collection')) DB::table('collection')->where('slno', $slno)->delete();
            if ($this->hasTable('kuricolln')) DB::table('kuricolln')->where('slno', $slno)->delete();
            if ($this->hasTable('kurifinishdet')) DB::table('kurifinishdet')->where('slno', $slno)->delete();
            if ($this->hasTable('daybookratewgt')) DB::table('daybookratewgt')->where('slno', $slno)->delete();
            DB::commit();

            $message = 'Deleted';
            if (SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code'))) {
                try {
                    $secondarySync = (new SecondaryDatabaseSync())->sync('journal', $slno);
                    $message .= ' and synced to secondary DB (' . ($secondarySync['database'] ?? '') . ')';
                } catch (\Throwable $e) {
                    $message .= '. Primary delete completed, but secondary sync failed: ' . $e->getMessage();
                }
            }

            return $this->json(['success' => true, 'message' => $message]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Delete failed: ' . $e->getMessage()], 500);
        }
    }
}
