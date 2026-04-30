<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DailyStatementController extends Controller
{
    // ── Helpers ──

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

    private function getColumns(string $table): array
    {
        if (!$this->hasTable($table)) return [];
        return array_map('strtolower', $this->columnList($table));
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

    private function formatDate(?string $dbDate): string
    {
        $dbDate = trim((string) $dbDate);
        if ($dbDate === '') return '';
        $ts = strtotime($dbDate);
        return $ts === false ? $dbDate : date('d/m/Y', $ts);
    }

    private function getCashBalance(string $accode, int $control, ?string $upToDate = null): float
    {
        if ($accode === '' || !$this->hasTable('daybook')) return 0;

        $query = DB::table('daybook')
            ->whereRaw('TRIM(accode) = ?', [$accode])
            ->where('control', $control);

        if ($upToDate) {
            $query->where('tdate', '<=', $upToDate);
        }

        $sum = (float) $query->sum('amount');

        if ($this->hasTable('accountm')) {
            $ob = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$accode])->first();
            if ($ob) {
                $opbal = ($control === 1) ? (float) ($ob->opbal ?? 0) : (float) ($ob->opbalb ?? 0);
                $sum += $opbal;
            }
        }

        return $sum;
    }

    // ── Page ──

    public function index(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('account.daily-statement');
    }

    // ── API ──

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action = strtolower(trim((string) $request->input('action', '')));
        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control <= 0) $control = 1;

        switch ($action) {
            case 'init':
                return $this->actionInit($request, $control);
            case 'account_search':
                return $this->actionAccountSearch($request);
            case 'lookup_account':
                return $this->actionLookupAccount($request);
            case 'load':
                return $this->actionLoad($request, $control);
            case 'save':
                return $this->actionSave($request, $control);
            default:
                return $this->json(['success' => false, 'error' => 'Invalid action'], 400);
        }
    }

    // ── Action: init ──
    private function actionInit(Request $request, int $control): JsonResponse
    {
        // Cash/Bank accounts for dropdown
        $cbAccounts = [];
        if ($this->hasTable('accountm')) {
            $cbAccounts = DB::table('accountm')
                ->selectRaw('TRIM(accode) AS accode, TRIM(name) AS name, TRIM(actype2) AS actype2')
                ->whereRaw("TRIM(actype2) IN ('H','B')")
                ->when($this->hasCol('accountm', 'removed'), function ($q) {
                    $q->whereRaw('(removed <> 1 OR removed IS NULL)');
                })
                ->orderBy('accode')
                ->get()
                ->all();
        }

        // Salesman list
        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')
                ->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
                ->orderBy('code')
                ->get()
                ->all();
        }

        // Cash balance for default cash account
        $defaultCb = 'CASH';
        if (!empty($cbAccounts)) {
            $defaultCb = $cbAccounts[0]->accode;
        }
        $cashBalance = $this->getCashBalance($defaultCb, $control);

        return $this->json([
            'success' => true,
            'control' => $control,
            'cbAccounts' => $cbAccounts,
            'salesmen' => $salesmen,
            'cashBalance' => round($cashBalance, 2),
            'today' => date('d/m/Y'),
        ]);
    }

    // ── Action: account_search ──
    private function actionAccountSearch(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $nameExpr = $this->hasCol('accountm', 'name') ? 'TRIM(name)' : 'TRIM(accode)';
        $query = DB::table('accountm')
            ->selectRaw("TRIM(accode) AS accode, $nameExpr AS name");

        if ($this->hasCol('accountm', 'removed')) {
            $query->whereRaw('(removed <> 1 OR removed IS NULL)');
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('TRIM(accode) LIKE ?', [$like])
                  ->orWhereRaw('TRIM(name) LIKE ?', [$like]);
            });
        }

        return $this->json(['success' => true, 'data' => $query->orderBy('name')->limit(200)->get()->all()]);
    }

    // ── Action: lookup_account (get name for a code) ──
    private function actionLookupAccount(Request $request): JsonResponse
    {
        $accode = $this->trimUpper($request->input('accode', ''));
        if ($accode === '') return $this->json(['success' => false, 'error' => 'Account code required']);

        $row = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$accode])->first();
        if (!$row) return $this->json(['success' => false, 'error' => 'Account not found']);

        return $this->json([
            'success' => true,
            'accode' => $accode,
            'name' => trim((string) ($row->name ?? '')),
        ]);
    }

    // ── Action: load (edit existing DSV entries for a date) ──
    private function actionLoad(Request $request, int $control): JsonResponse
    {
        $tdate = $this->parseDate($request->input('tdate', ''));
        if (!$tdate) return $this->json(['success' => false, 'error' => 'Valid date is required']);

        $cbcode = $this->trimUpper($request->input('cbcode', 'CASH'));

        if (!$this->hasTable('daybookpart') || !$this->hasTable('daybook')) {
            return $this->json(['success' => true, 'rows' => [], 'cashBalance' => 0]);
        }

        // Load DSV entries: refno starts with 'DSV' and party account (not the cash/bank code)
        $rows = DB::table('daybook')
            ->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno')
            ->selectRaw('daybook.slno, TRIM(daybook.accode) AS accode, daybook.amount, TRIM(daybookpart.particular) AS description, TRIM(daybookpart.vchno) AS vchno, TRIM(daybookpart.chequeno) AS chqno, TRIM(daybookpart.staff) AS staff')
            ->whereRaw("LEFT(daybookpart.refno, 3) = 'DSV'")
            ->whereRaw('TRIM(daybook.accode) <> ?', [$cbcode])
            ->where('daybook.tdate', $tdate)
            ->orderBy('daybook.slno')
            ->orderByRaw('daybook.sno IS NULL, daybook.sno')
            ->get()
            ->all();

        // Transform: positive amount in daybook for party = payment (we paid them), negative = receipt (they paid us)
        $entries = [];
        $staff = '';
        foreach ($rows as $r) {
            $amt = (float) ($r->amount ?? 0);
            if (!$staff && !empty($r->staff)) {
                $staff = $r->staff;
            }

            // Look up account name
            $acname = '';
            $acRow = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$r->accode])->first();
            if ($acRow) {
                $acname = trim((string) ($acRow->name ?? ''));
            }

            $entries[] = [
                'slno' => (int) $r->slno,
                'accode' => $r->accode,
                'acname' => $acname,
                'description' => $r->description ?? '',
                'chqno' => $r->chqno ?? '',
                'receipt' => $amt < 0 ? round(abs($amt), 2) : 0,
                'payment' => $amt > 0 ? round($amt, 2) : 0,
                'vchno' => $r->vchno ?? '',
            ];
        }

        // Cash balance (excluding today's DSV transactions)
        $cashBal = $this->getCashBalance($cbcode, $control, $tdate);
        // Subtract today's DSV amounts from cash balance to get opening
        $dsvCashSum = (float) DB::table('daybook')
            ->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno')
            ->whereRaw("LEFT(daybookpart.refno, 3) = 'DSV'")
            ->whereRaw('TRIM(daybook.accode) = ?', [$cbcode])
            ->where('daybook.tdate', $tdate)
            ->sum('daybook.amount');

        $openingBal = $cashBal - $dsvCashSum;

        return $this->json([
            'success' => true,
            'rows' => $entries,
            'staff' => $staff,
            'cashBalance' => round($openingBal, 2),
        ]);
    }

    // ── Action: save ──
    private function actionSave(Request $request, int $control): JsonResponse
    {
        $tdate = $this->parseDate($request->input('tdate', ''));
        if (!$tdate) return $this->json(['success' => false, 'error' => 'Valid date is required']);

        $cbcode = $this->trimUpper($request->input('cbcode', 'CASH'));
        $staff = trim((string) $request->input('staff', ''));
        $rows = $request->input('rows', []);

        if (empty($staff)) return $this->json(['success' => false, 'error' => 'Salesman is required']);

        if (!is_array($rows) || empty($rows)) {
            return $this->json(['success' => false, 'error' => 'No entries to save']);
        }

        // Validate rows
        $validRows = [];
        foreach ($rows as $r) {
            $accode = $this->trimUpper($r['accode'] ?? '');
            $receipt = abs((float) ($r['receipt'] ?? 0));
            $payment = abs((float) ($r['payment'] ?? 0));
            if ($accode === '' || ($receipt <= 0 && $payment <= 0)) continue;

            $validRows[] = [
                'slno' => (int) ($r['slno'] ?? 0),
                'accode' => $accode,
                'description' => trim((string) ($r['description'] ?? '')),
                'chqno' => trim((string) ($r['chqno'] ?? '')),
                'receipt' => $receipt,
                'payment' => $payment,
                'vchno' => trim((string) ($r['vchno'] ?? '')),
            ];
        }

        if (empty($validRows)) {
            return $this->json(['success' => false, 'error' => 'No valid entries to save']);
        }

        try {
            DB::beginTransaction();

            $dbCols = $this->getColumns('daybook');
            $dpCols = $this->getColumns('daybookpart');

            // Get current serial number
            $lslno = $this->genInt('SERIALNO');

            // Generate DSV reference number
            $dsvNum = $this->incrementGenInt('VCHNODS');
            $refno = 'DSV/' . str_pad($dsvNum, 5, '0', STR_PAD_LEFT);

            $userCode = (string) $request->session()->get('user_code', '');
            $ttime = date('H:i:s');
            $sno = 0;

            foreach ($validRows as $row) {
                $existingSlno = $row['slno'];
                $existingVchno = $row['vchno'];

                if ($existingSlno > 0) {
                    // Edit mode: delete old entries and reuse slno
                    DB::table('daybook')->where('slno', $existingSlno)->delete();
                    DB::table('daybookpart')->where('slno', $existingSlno)->delete();
                    $rowSlno = $existingSlno;
                } else {
                    // New row: get next serial number
                    $lslno++;
                    $rowSlno = $lslno;
                }

                $sno++;

                $isReceipt = ($row['receipt'] > 0);
                $amount = $isReceipt ? $row['receipt'] : $row['payment'];

                // Cash/bank entry amount: receipt = negative (cash flows in to cash account = debit = negative in PB convention)
                // Actually from PB code: receipt → damount = -dincome → cash/bank gets negative, party gets positive
                // payment → damount = dexpense → cash/bank gets positive, party gets negative
                $cashAmount = $isReceipt ? -$amount : $amount;
                $partyAmount = -$cashAmount;

                // Generate voucher number if new
                $vchno = $existingVchno;
                if ($vchno === '') {
                    if ($isReceipt) {
                        if ($control === 1) {
                            $vn = $this->incrementGenInt('VCHNORB');
                            $vchno = 'VRB/' . str_pad($vn, 5, '0', STR_PAD_LEFT);
                        } else {
                            $vn = $this->incrementGenInt('VCHNORE');
                            $vchno = 'VRE/' . str_pad($vn, 5, '0', STR_PAD_LEFT);
                        }
                    } else {
                        if ($control === 1) {
                            $vn = $this->incrementGenInt('VCHNOPB');
                            $vchno = 'VPB/' . str_pad($vn, 5, '0', STR_PAD_LEFT);
                        } else {
                            $vn = $this->incrementGenInt('VCHNOPE');
                            $vchno = 'VPE/' . str_pad($vn, 5, '0', STR_PAD_LEFT);
                        }
                    }
                }

                // Insert daybook: cash/bank row
                $dbRow1 = [
                    'slno' => $rowSlno,
                    'tdate' => $tdate,
                    'accode' => $cbcode,
                    'amount' => round($cashAmount, 2),
                    'control' => $control,
                    'opaccode' => $row['accode'],
                    'sno' => $sno,
                ];
                $dbRow1F = array_filter($dbRow1, fn($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('daybook')->insert($dbRow1F);

                // Insert daybook: party row
                $dbRow2 = [
                    'slno' => $rowSlno,
                    'tdate' => $tdate,
                    'accode' => $row['accode'],
                    'amount' => round($partyAmount, 2),
                    'control' => $control,
                    'opaccode' => $cbcode,
                    'sno' => $sno,
                ];
                $dbRow2F = array_filter($dbRow2, fn($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('daybook')->insert($dbRow2F);

                // Insert daybookpart
                $dp = [
                    'slno' => $rowSlno,
                    'vchno' => $vchno,
                    'particular' => mb_substr($row['description'], 0, 200),
                    'staff' => $staff,
                    'chequeno' => $row['chqno'],
                    'ic' => $userCode,
                    'ttime' => $ttime,
                    'refno' => $refno,
                    'tdate' => $tdate,
                    'control' => $control,
                ];
                $dpF = array_filter($dp, fn($k) => in_array($k, $dpCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('daybookpart')->insert($dpF);
            }

            // Update SERIALNO
            DB::table('generali')->where('code', 'SERIALNO')->update(['cvalue' => $lslno]);

            DB::commit();

            return $this->json([
                'success' => true,
                'message' => 'Daily statement saved. ' . count($validRows) . ' entries.',
                'refno' => $refno,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Save failed: ' . $e->getMessage()]);
        }
    }
}
