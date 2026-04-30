<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSecondarySeriesPrefix;
use App\Support\SecondaryDatabaseSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PaymentController extends Controller
{
    use HandlesSecondarySeriesPrefix;
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

    private function ensureGenInt(string $code, int $default = 0): void
    {
        if (!$this->hasTable('generali')) return;
        if (!DB::table('generali')->where('code', $code)->exists()) {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => $default]);
        }
    }

    private function lastVoucherNumberForPrefix(string $prefix): int
    {
        if ($prefix === '' || !$this->hasTable('daybookpart') || !$this->hasCol('daybookpart', 'vchno')) {
            return 0;
        }

        $maxNo = 0;
        $rows = DB::table('daybookpart')
            ->whereNotNull('vchno')
            ->where('vchno', 'like', $prefix . '%')
            ->get(['vchno']);

        foreach ($rows as $row) {
            $value = trim((string) ($row->vchno ?? ''));
            if ($value === '' || !str_starts_with($value, $prefix)) {
                continue;
            }

            $suffix = substr($value, strlen($prefix));
            if (!ctype_digit($suffix)) {
                continue;
            }

            $number = (int) $suffix;
            if ($number > $maxNo) {
                $maxNo = $number;
            }
        }

        return $maxNo;
    }

    private function previewVoucherWithCounter(string $prefix, string $counterCode, int $pad = 5): string
    {
        $current = $this->genInt($counterCode);
        $maxUsed = $this->lastVoucherNumberForPrefix($prefix);
        $next = max($current, $maxUsed) + 1;
        return $prefix . str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
    }

    private function reserveVoucherWithCounter(string $prefix, string $counterCode, int $pad = 5): string
    {
        $current = $this->genInt($counterCode);
        $maxUsed = $this->lastVoucherNumberForPrefix($prefix);
        $next = max($current, $maxUsed) + 1;
        $updated = DB::table('generali')->where('code', $counterCode)->update(['cvalue' => $next]);
        if ($updated === 0) {
            DB::table('generali')->insert(['code' => $counterCode, 'cvalue' => $next]);
        }
        return $prefix . str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
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

    private function generalProfile(string $code, string $default = ''): string
    {
        if (!$this->hasTable('generals')) return $default;
        $value = trim((string) (DB::table('generals')->where('code', $code)->value('cvalue') ?? ''));
        return $value === '' ? $default : $value;
    }

    private function printHeaderData(): array
    {
        $name = $this->generalProfile('SHOPNM');
        $address1 = $this->generalProfile('SHOPADDR', $this->generalProfile('COADR1'));
        $address2 = $this->generalProfile('COADR2');
        $phone = $this->generalProfile('SHOPPHONE');
        $gstin = $this->generalProfile('KGST');

        return [
            'name' => $name,
            'address1' => $address1,
            'address2' => $address2,
            'phone' => $phone,
            'gstin' => $gstin,
        ];
    }

    private function amountToWordsIndian(float $num): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $twoDigits = function (int $n) use ($ones, $tens): string {
            if ($n < 20) {
                return $ones[$n];
            }

            return $tens[intdiv($n, 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
        };

        $threeDigits = function (int $n) use ($ones, $twoDigits): string {
            $hundred = intdiv($n, 100);
            $rest = $n % 100;
            $out = '';

            if ($hundred) {
                $out .= $ones[$hundred] . ' Hundred';
            }
            if ($rest) {
                $out .= ($out !== '' ? ' ' : '') . $twoDigits($rest);
            }

            return $out;
        };

        $wholeNumberWords = function (int $n) use ($threeDigits): string {
            if ($n === 0) {
                return 'Zero';
            }

            $parts = [];
            $crore = intdiv($n, 10000000);
            $n %= 10000000;
            $lakh = intdiv($n, 100000);
            $n %= 100000;
            $thousand = intdiv($n, 1000);
            $n %= 1000;

            if ($crore) {
                $parts[] = $threeDigits($crore) . ' Crore';
            }
            if ($lakh) {
                $parts[] = $threeDigits($lakh) . ' Lakh';
            }
            if ($thousand) {
                $parts[] = $threeDigits($thousand) . ' Thousand';
            }
            if ($n) {
                $parts[] = $threeDigits($n);
            }

            return trim(implode(' ', $parts));
        };

        $safe = max(0, $num);
        $rupees = (int) floor($safe);
        $paise = (int) round(($safe - $rupees) * 100);

        $out = $wholeNumberWords($rupees) . ' Rupees';
        if ($paise > 0) {
            $out .= ' and ' . $wholeNumberWords($paise) . ' Paise';
        }

        return $out . ' Only';
    }

    private function nextPaymentVoucherPreview(int $control, ?string $cbcode = null): string
    {
        if ($control === 1 && $this->shouldUseSecondaryPrefix('payment')) {
            $prefix = $this->secondaryPrefixFor('payment');
            $counterCode = 'SEC' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $prefix));
            $this->ensureGenInt($counterCode);
            return $this->previewVoucherWithCounter($prefix, $counterCode);
        }

        $cbcode = $this->trimUpper($cbcode);
        $separate = strtoupper($this->generalProfile('BankCashSeperateVoucherNo', 'N')) === 'Y';

        if ($control === 1 && $separate && $cbcode !== '' && $this->hasTable('accountm')) {
            $actype2 = strtoupper(trim((string) (DB::table('accountm')->whereRaw('TRIM(accode)=?', [$cbcode])->value('actype2') ?? '')));
            if ($actype2 === 'B') {
                return $this->previewVoucherWithCounter('VPB/', 'VCHNOPB');
            }
            $this->ensureGenInt('VCHNOPC');
            return $this->previewVoucherWithCounter('VPC/', 'VCHNOPC');
        }

        return $this->previewVoucherWithCounter($control === 1 ? 'VPB/' : 'VPE/', $control === 1 ? 'VCHNOPB' : 'VCHNOPE');
    }

    private function nextPaymentVoucherNumber(int $control, ?string $cbcode = null): string
    {
        if ($control === 1 && $this->shouldUseSecondaryPrefix('payment')) {
            $prefix = $this->secondaryPrefixFor('payment');
            $counterCode = 'SEC' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $prefix));
            $this->ensureGenInt($counterCode);
            return $this->reserveVoucherWithCounter($prefix, $counterCode);
        }

        $cbcode = $this->trimUpper($cbcode);
        $separate = strtoupper($this->generalProfile('BankCashSeperateVoucherNo', 'N')) === 'Y';

        if ($control === 1 && $separate && $cbcode !== '' && $this->hasTable('accountm')) {
            $actype2 = strtoupper(trim((string) (DB::table('accountm')->whereRaw('TRIM(accode)=?', [$cbcode])->value('actype2') ?? '')));
            if ($actype2 === 'B') {
                return $this->reserveVoucherWithCounter('VPB/', 'VCHNOPB');
            }
            $this->ensureGenInt('VCHNOPC');
            return $this->reserveVoucherWithCounter('VPC/', 'VCHNOPC');
        }

        if ($control === 1) {
            return $this->reserveVoucherWithCounter('VPB/', 'VCHNOPB');
        }

        return $this->reserveVoucherWithCounter('VPE/', 'VCHNOPE');
    }

    private function nextPdcVoucherNumber(int $control): string
    {
        if ($control === 1) {
            $this->ensureGenInt('PDCPB');
            return $this->reserveVoucherWithCounter('PDCP/', 'PDCPB', 4);
        }

        $this->ensureGenInt('PDCPE');
        return $this->reserveVoucherWithCounter('PDCP', 'PDCPE', 4);
    }

    // ── Page ──

    public function index(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('account.payment');
    }

    public function print(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);

        $slno = (int) $request->query('slno', 0);
        if ($slno <= 0) {
            abort(404);
        }

        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control <= 0) $control = 1;

        $load = $this->actionLoad(new Request(['slno' => $slno]), $control)->getData(true);
        if (($load['success'] ?? false) !== true || empty($load['data'])) {
            abort(404);
        }

        $data = $load['data'];
        return view('account.voucher-print', [
            'voucherType' => 'Payment',
            'voucherData' => $data,
            'amountInWords' => $this->amountToWordsIndian((float) ($data['amount'] ?? 0)),
            'company' => $this->printHeaderData(),
            'showObCb' => false,
            'printSlip' => $request->boolean('slip', false),
            'printRuff' => $request->boolean('ruff', false),
            'pageMode' => 'half-portrait',
        ]);
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
            case 'account_list':
                return $this->actionAccountList($request);
            case 'load_account':
                return $this->actionLoadAccount($request, $control);
            case 'load':
                return $this->actionLoad($request, $control);
            case 'save':
                return $this->actionSave($request, $control);
            case 'delete':
                return $this->actionDelete($request);
            case 'list':
                return $this->actionList($request, $control);
            case 'navigate':
                return $this->actionNavigate($request);
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

        $defaultCbCode = '';
        foreach ($cbAccounts as $account) {
            if (strtoupper(trim((string) ($account->accode ?? ''))) === 'CASH') {
                $defaultCbCode = 'CASH';
                break;
            }
        }
        if ($defaultCbCode === '' && !empty($cbAccounts)) {
            $defaultCbCode = trim((string) ($cbAccounts[0]->accode ?? ''));
        }
        $selectedCbCode = $this->trimUpper($request->input('cbcode', $request->query('cbcode', $defaultCbCode)));
        if ($selectedCbCode === '') {
            $selectedCbCode = $defaultCbCode;
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

        // Cash balance (sum of daybook for default cash/bank account)
        $cashBalance = 0;
        if ($selectedCbCode !== '') {
            $cashBalance = $this->getAccountBalance($selectedCbCode, $control);
        }

        return $this->json([
            'success' => true,
            'nextVchno' => $this->nextPaymentVoucherPreview($control, $selectedCbCode),
            'control' => $control,
            'cbAccounts' => $cbAccounts,
            'salesmen' => $salesmen,
            'cashBalance' => round($cashBalance, 2),
            'defaultCbCode' => $selectedCbCode,
            'today' => date('d/m/Y'),
        ]);
    }

    // ── Action: account_list ──
    private function actionAccountList(Request $request): JsonResponse
    {
        $type = strtolower(trim((string) $request->input('type', 'A')));
        $search = trim((string) $request->input('search', ''));

        $nameExpr = $this->hasCol('accountm', 'name') ? 'TRIM(name)' : 'TRIM(accode)';
        $query = DB::table('accountm')
            ->selectRaw("TRIM(accode) AS accode, $nameExpr AS name");

        if ($this->hasCol('accountm', 'actype2')) {
            $query->addSelect('actype2');
        }

        if ($this->hasCol('accountm', 'removed')) {
            $query->whereRaw('(removed <> 1 OR removed IS NULL)');
        }

        if ($type === 'c') $query->whereRaw("TRIM(actype2) = 'C'");
        elseif ($type === 's') $query->whereRaw("TRIM(actype2) = 'S'");
        elseif ($type === 'j') $query->whereRaw("TRIM(actype2) = 'J'");
        elseif ($type === 'g') $query->whereRaw("TRIM(actype2) = 'G'");
        elseif ($type === 'r') $query->whereRaw("TRIM(actype2) = 'R'");
        elseif ($type === 'f') $query->whereRaw("TRIM(actype2) = 'F'");
        elseif ($type === 'o') $query->whereRaw("TRIM(actype2) NOT IN ('C','S','J','G','R','F','H','B')");

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('TRIM(accode) LIKE ?', [$like])
                  ->orWhereRaw('TRIM(name) LIKE ?', [$like]);
            });
        }

        return $this->json(['success' => true, 'data' => $query->orderBy('name')->get()->all()]);
    }

    // ── Action: load_account ──
    private function actionLoadAccount(Request $request, int $control): JsonResponse
    {
        $accode = $this->trimUpper($request->input('accode', ''));
        if ($accode === '') return $this->json(['success' => false, 'error' => 'Account code required']);

        $row = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$accode])->first();
        if (!$row) return $this->json(['success' => false, 'error' => 'Account not found']);

        $name = trim((string) ($row->name ?? ''));
        $actype2 = strtoupper(trim((string) ($row->actype2 ?? '')));
        $balance = $this->getAccountBalance($accode, $control);

        return $this->json([
            'success' => true,
            'accode' => $accode,
            'name' => $name,
            'actype2' => $actype2,
            'balance' => round($balance, 2),
            'balance_label' => ($balance >= 0) ? 'Cr' : 'Dr',
        ]);
    }

    // ── Action: load_cb_balance (cash/bank balance) ──
    private function getCbBalance(string $cbcode, int $control): float
    {
        return $this->getAccountBalance($cbcode, $control);
    }

    // ── Action: list ──
    private function actionList(Request $request, int $control): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        if (!$this->hasTable('daybookpart') || !$this->hasTable('daybook')) {
            return $this->json(['success' => true, 'data' => []]);
        }

        $query = DB::table('daybookpart')
            ->join('daybook', function ($j) {
                $j->on('daybookpart.slno', '=', 'daybook.slno');
                $j->whereRaw('daybook.amount > 0');
            })
            ->selectRaw('daybookpart.slno, daybookpart.vchno, daybook.tdate, TRIM(daybook.accode) AS cbcode, ABS(daybook.amount) AS amount, TRIM(daybookpart.particular) AS particular')
            ->whereRaw("LEFT(daybookpart.vchno, 2) = 'VP'");

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('daybookpart.vchno', 'LIKE', $like)
                  ->orWhereRaw('TRIM(daybookpart.particular) LIKE ?', [$like]);
            });
        }

        $rows = $query->orderByDesc('daybookpart.slno')->limit(200)->get()->all();

        return $this->json(['success' => true, 'data' => $rows]);
    }

    // ── Action: load (edit mode) ──
    private function actionLoad(Request $request, int $control): JsonResponse
    {
        $slno = (int) $request->input('slno', 0);
        if ($slno <= 0) return $this->json(['success' => false, 'error' => 'Invalid slno']);

        if ((!$this->hasTable('daybookpart') || !$this->hasTable('daybook')) && !$this->hasTable('pdclist')) {
            return $this->json(['success' => false, 'error' => 'Tables missing']);
        }

        $dp = DB::table('daybookpart')->where('slno', $slno)->first();
        $cbcode = '';
        $accode = '';
        $amount = 0;
        $tdate = '';
        $dbControl = $control;
        $chequedate = '';
        $duedate = '';
        $particular = '';
        $staff = '';
        $chequeno = '';
        $rate = 0.0;
        $taxperc = 0.0;
        $taxamt = 0.0;
        $interstate = false;
        $taxreverse = false;
        $isPdc = false;

        if (!$dp && $this->hasTable('pdclist')) {
            $pdc = DB::table('pdclist')->where('slno', $slno)->first();
            if (!$pdc) return $this->json(['success' => false, 'error' => 'Record not found']);

            $vchno = trim((string) ($pdc->docno ?? ''));
            $tdate = (string) ($pdc->tdate ?? '');
            $amount = abs((float) ($pdc->amount ?? 0));
            $dbControl = (int) ($pdc->control ?? $control);
            $cbcode = trim((string) ($pdc->bank ?? ''));
            $accode = trim((string) ($pdc->code ?? ''));
            $particular = trim((string) ($pdc->particulars ?? ''));
            $chequeno = trim((string) ($pdc->chqno ?? ''));
            $chequedate = $this->formatDate((string) ($pdc->chqdate ?? ''));
            $isPdc = true;
        } else {
            if (!$dp) return $this->json(['success' => false, 'error' => 'Record not found']);

            $vchno = trim((string) ($dp->vchno ?? ''));
            $dbRows = DB::table('daybook')->where('slno', $slno)->get();
            foreach ($dbRows as $r) {
                $amt = (float) ($r->amount ?? 0);
                $dbControl = (int) ($r->control ?? $control);
                $tdate = (string) ($r->tdate ?? '');
                if ($amt > 0) {
                    $cbcode = trim((string) ($r->accode ?? ''));
                    $amount = abs($amt);
                } elseif ($amt < 0) {
                    $accode = trim((string) ($r->accode ?? ''));
                }
            }

            $particular = trim((string) ($dp->particular ?? ''));
            $staff = trim((string) ($dp->staff ?? ''));
            $chequeno = trim((string) ($dp->chequeno ?? ''));
            if (isset($dp->chequedate)) {
                $chequedate = $this->formatDate((string) $dp->chequedate);
            }
            if (isset($dp->duedate) && $dp->duedate) {
                $y = (int) date('Y', strtotime((string) $dp->duedate));
                if ($y > 1950) $duedate = $this->formatDate((string) $dp->duedate);
            }
            $rate = (float) ($dp->rate ?? 0);
            $taxperc = (float) ($dp->taxperc ?? 0);
            $taxamt = (float) ($dp->taxamt ?? 0);
            $interstate = strtoupper(trim((string) ($dp->interstate ?? 'N'))) === 'Y';
            $taxreverse = strtoupper(trim((string) ($dp->taxreverse ?? 'N'))) === 'Y';
            $isPdc = (substr($vchno, 0, 3) === 'PDC');
        }

        // Get account names
        $cbname = '';
        $acname = '';
        $actype2 = '';
        if ($cbcode !== '' && $this->hasTable('accountm')) {
            $row = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$cbcode])->first();
            if ($row) $cbname = trim((string) ($row->name ?? ''));
        }
        if ($accode !== '' && $this->hasTable('accountm')) {
            $row = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$accode])->first();
            if ($row) {
                $acname = trim((string) ($row->name ?? ''));
                $actype2 = strtoupper(trim((string) ($row->actype2 ?? '')));
            }
        }

        $balance = $this->getAccountBalance($accode, $dbControl);
        $cashBalance = $this->getAccountBalance($cbcode, $dbControl);

        return $this->json([
            'success' => true,
            'data' => [
                'slno' => $slno,
                'vchno' => $vchno,
                'tdate' => $this->formatDate($tdate),
                'cbcode' => $cbcode,
                'cbname' => $cbname,
                'accode' => $accode,
                'acname' => $acname,
                'actype2' => $actype2,
                'amount' => $amount,
                'particular' => $particular,
                'staff' => $staff,
                'chequeno' => $chequeno,
                'chequedate' => $chequedate,
                'duedate' => $duedate,
                'rate' => $rate,
                'taxperc' => $taxperc,
                'taxamt' => $taxamt,
                'interstate' => $interstate,
                'taxreverse' => $taxreverse,
                'pdc' => $isPdc,
                'balance' => round($balance, 2),
                'balance_label' => ($balance >= 0) ? 'Cr' : 'Dr',
                'cashBalance' => round($cashBalance, 2),
                'control' => $dbControl,
            ],
        ]);
    }

    // ── Action: save ──
    private function actionSave(Request $request, int $control): JsonResponse
    {
        $shouldSecondarySync = $request->boolean('secondary_sync');
        if ($shouldSecondarySync && !SecondaryDatabaseSync::userCanUse($request->session()->get('user_code'))) {
            return $this->json(['success' => false, 'error' => 'You do not have permission for secondary database sync.'], 403);
        }

        $mode = strtoupper(trim((string) $request->input('mode', 'A')));
        $editSlno = (int) $request->input('slno', 0);

        $tdate = $this->parseDate($request->input('tdate', ''));
        if (!$tdate) return $this->json(['success' => false, 'error' => 'Valid date is required']);

        $cbcode = $this->trimUpper($request->input('cbcode', ''));
        $accode = $this->trimUpper($request->input('accode', ''));
        $amount = abs((float) $request->input('amount', 0));
        $particular = trim((string) $request->input('particular', ''));
        $staff = trim((string) $request->input('staff', ''));
        $chequeno = trim((string) $request->input('chequeno', ''));
        $chequedate = $this->parseDate($request->input('chequedate', ''));
        $duedate = $this->parseDate($request->input('duedate', ''));
        $rate = (float) $request->input('rate', 0);
        $taxperc = (float) $request->input('taxperc', 0);
        $taxamt = (float) $request->input('taxamt', 0);
        $interstate = $request->boolean('interstate') ? 'Y' : 'N';
        $taxreverse = $request->boolean('taxreverse') ? 'Y' : 'N';
        $isPdc = $request->boolean('pdc');

        if ($cbcode === '') return $this->json(['success' => false, 'error' => 'Cash/Bank account is required']);
        if ($accode === '') return $this->json(['success' => false, 'error' => 'Account code is required']);
        if ($amount <= 0) return $this->json(['success' => false, 'error' => 'Amount must be greater than zero']);

        try {
            DB::beginTransaction();

            $dbCols = $this->getColumns('daybook');
            $dpCols = $this->getColumns('daybookpart');

            if ($mode === 'E' && $editSlno > 0) {
                DB::table('daybook')->where('slno', $editSlno)->delete();
                DB::table('daybookpart')->where('slno', $editSlno)->delete();
                if ($this->hasTable('pdclist')) {
                    DB::table('pdclist')->where('slno', $editSlno)->delete();
                }
                $lslno = $editSlno;
                $svchno = trim((string) $request->input('vchno', ''));
            } else {
                $lslno = $this->nextSerialNo();
                $svchno = $isPdc ? $this->nextPdcVoucherNumber($control) : $this->nextPaymentVoucherNumber($control, $cbcode);
            }

            if ($isPdc && $this->hasTable('pdclist')) {
                $pdcCols = $this->getColumns('pdclist');
                $pdc = [
                    'slno' => $lslno,
                    'tdate' => $tdate,
                    'docno' => $svchno,
                    'bank' => $cbcode,
                    'code' => $accode,
                    'chqno' => $chequeno,
                    'chqdate' => $chequedate,
                    'amount' => round($amount, 2),
                    'particulars' => mb_substr($particular, 0, 200),
                    'rp' => 'P',
                    'pend' => 'Y',
                    'control' => $control,
                ];
                $pdcFiltered = array_filter($pdc, fn($k) => in_array($k, $pdcCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('pdclist')->insert($pdcFiltered);
            } else {
                $dp = [
                    'slno' => $lslno,
                    'vchno' => $svchno,
                    'particular' => mb_substr($particular, 0, 200),
                    'staff' => $staff,
                    'chequeno' => $chequeno,
                    'chequedate' => $chequedate,
                    'duedate' => $duedate,
                    'rate' => $rate,
                    'taxperc' => $taxperc,
                    'taxamt' => $taxamt,
                    'interstate' => $interstate,
                    'taxreverse' => $taxreverse,
                    'tdate' => $tdate,
                    'control' => $control,
                    'ic' => (string) $request->session()->get('user_code', ''),
                    'ttime' => date('H:i:s'),
                ];
                $dpFiltered = array_filter($dp, fn($k) => in_array($k, $dpCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('daybookpart')->insert($dpFiltered);

                $row1 = [
                    'slno' => $lslno,
                    'tdate' => $tdate,
                    'accode' => $cbcode,
                    'amount' => round($amount, 2),
                    'control' => $control,
                    'opaccode' => $accode,
                ];
                $row1Filtered = array_filter($row1, fn($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('daybook')->insert($row1Filtered);

                $row2 = [
                    'slno' => $lslno,
                    'tdate' => $tdate,
                    'accode' => $accode,
                    'amount' => -round($amount, 2),
                    'control' => $control,
                    'opaccode' => $cbcode,
                ];
                $row2Filtered = array_filter($row2, fn($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('daybook')->insert($row2Filtered);
            }

            DB::commit();

            $message = 'Payment saved successfully';
            $secondarySync = null;
            if ($shouldSecondarySync && $lslno > 0) {
                try {
                    $secondarySync = (new SecondaryDatabaseSync())->sync('payment', (int) $lslno);
                    $message .= ' Secondary sync completed to ' . ($secondarySync['database'] ?? '') . '.';
                } catch (\Throwable $e) {
                    $message .= ' Primary save completed, but secondary sync failed: ' . $e->getMessage();
                }
            }

            return $this->json([
                'success' => true,
                'message' => $message,
                'slno' => $lslno,
                'vchno' => $svchno,
                'secondary_sync' => $secondarySync,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    // ── Action: delete ──
    private function actionDelete(Request $request): JsonResponse
    {
        $slno = (int) $request->input('slno', 0);
        if ($slno <= 0) return $this->json(['success' => false, 'error' => 'Invalid slno']);

        try {
            DB::beginTransaction();
            DB::table('daybook')->where('slno', $slno)->delete();
            DB::table('daybookpart')->where('slno', $slno)->delete();
            if ($this->hasTable('pdclist')) {
                DB::table('pdclist')->where('slno', $slno)->delete();
            }
            if ($this->hasTable('daybookratewgt')) {
                DB::table('daybookratewgt')->where('slno', $slno)->delete();
            }
            DB::commit();

            $message = 'Payment deleted successfully';
            if (SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code'))) {
                try {
                    $secondarySync = (new SecondaryDatabaseSync())->sync('payment', $slno);
                    $message .= ' and synced to secondary DB (' . ($secondarySync['database'] ?? '') . ')';
                } catch (\Throwable $e) {
                    $message .= '. Primary delete completed, but secondary sync failed: ' . $e->getMessage();
                }
            }

            return $this->json(['success' => true, 'message' => $message]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Delete failed: ' . $e->getMessage()]);
        }
    }

    // ── Action: navigate (prev/next) ──
    private function actionNavigate(Request $request): JsonResponse
    {
        $currentSlno = (int) $request->input('slno', 0);
        $dir = strtolower(trim((string) $request->input('dir', 'next')));

        if (!$this->hasTable('daybookpart')) {
            return $this->json(['success' => false, 'error' => 'Table missing']);
        }

        if ($dir === 'prev') {
            $row = DB::table('daybookpart')
                ->whereRaw("LEFT(vchno, 2) = 'VP'")
                ->where('slno', '<', $currentSlno)
                ->orderByDesc('slno')
                ->first();
        } else {
            $row = DB::table('daybookpart')
                ->whereRaw("LEFT(vchno, 2) = 'VP'")
                ->where('slno', '>', $currentSlno)
                ->orderBy('slno')
                ->first();
        }

        if (!$row) {
            return $this->json(['success' => false, 'error' => 'No more records']);
        }

        return $this->json(['success' => true, 'slno' => (int) $row->slno]);
    }
}
