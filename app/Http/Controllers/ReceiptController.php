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

class ReceiptController extends Controller
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

        // Opening balance from accountm
        $opbal = 0.0;
        if ($this->hasTable('accountm')) {
            $ob = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$accode])->first();
            if ($ob) {
                $opbal = ($control === 1) ? (float) ($ob->opbal ?? 0) : (float) ($ob->opbalb ?? 0);
            }
        }

        // Match Cash Book's logic exactly so the figure here equals the Cash Book closing:
        //   • control <= $control (include lower levels, e.g. drafts)
        //   • JOIN daybookpart (only count entries that have a part row, like Cash Book does)
        //   • date <= today (skip post-dated entries — that cash hasn't arrived yet)
        $query = DB::table('daybook')
            ->whereRaw('TRIM(daybook.accode) = ?', [$accode])
            ->where('daybook.control', '<=', $control)
            ->whereDate('daybook.tdate', '<=', now()->toDateString());

        if ($this->hasTable('daybookpart')) {
            $query->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno');
        }

        return $opbal + (float) $query->sum('daybook.amount');
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

    private function nextReceiptVoucherPreview(int $control, ?string $cbcode = null): string
    {
        if ($control === 1 && $this->shouldUseSecondaryPrefix('receipt')) {
            $prefix = $this->secondaryPrefixFor('receipt');
            $counterCode = 'SEC' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $prefix));
            $this->ensureGenInt($counterCode);
            return $this->previewVoucherWithCounter($prefix, $counterCode);
        }

        $cbcode = $this->trimUpper($cbcode);
        $separate = strtoupper($this->generalProfile('BankCashSeperateVoucherNo', 'N')) === 'Y';

        if ($control === 1 && $separate && $cbcode !== '' && $this->hasTable('accountm')) {
            $actype2 = strtoupper(trim((string) (DB::table('accountm')->whereRaw('TRIM(accode)=?', [$cbcode])->value('actype2') ?? '')));
            if ($actype2 === 'B') {
                return $this->previewVoucherWithCounter('VRB/', 'VCHNORB');
            }
            $this->ensureGenInt('VCHNORC');
            return $this->previewVoucherWithCounter('VRC/', 'VCHNORC');
        }

        return $this->previewVoucherWithCounter($control === 1 ? 'VRB/' : 'VRE/', $control === 1 ? 'VCHNORB' : 'VCHNORE');
    }

    private function nextReceiptVoucherNumber(int $control, ?string $cbcode = null): string
    {
        if ($control === 1 && $this->shouldUseSecondaryPrefix('receipt')) {
            $prefix = $this->secondaryPrefixFor('receipt');
            $counterCode = 'SEC' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $prefix));
            $this->ensureGenInt($counterCode);
            return $this->reserveVoucherWithCounter($prefix, $counterCode);
        }

        $cbcode = $this->trimUpper($cbcode);
        $separate = strtoupper($this->generalProfile('BankCashSeperateVoucherNo', 'N')) === 'Y';

        if ($control === 1 && $separate && $cbcode !== '' && $this->hasTable('accountm')) {
            $actype2 = strtoupper(trim((string) (DB::table('accountm')->whereRaw('TRIM(accode)=?', [$cbcode])->value('actype2') ?? '')));
            if ($actype2 === 'B') {
                return $this->reserveVoucherWithCounter('VRB/', 'VCHNORB');
            }
            $this->ensureGenInt('VCHNORC');
            return $this->reserveVoucherWithCounter('VRC/', 'VCHNORC');
        }

        if ($control === 1) {
            return $this->reserveVoucherWithCounter('VRB/', 'VCHNORB');
        }

        return $this->reserveVoucherWithCounter('VRE/', 'VCHNORE');
    }

    private function nextPdcReceiptVoucherNumber(int $control): string
    {
        if ($control === 1) {
            $this->ensureGenInt('PDCRB');
            return $this->reserveVoucherWithCounter('PDCR/', 'PDCRB', 4);
        }

        $this->ensureGenInt('PDCRE');
        return $this->reserveVoucherWithCounter('PDCR', 'PDCRE', 4);
    }

    private function receiptVoucherPrefixes(): array
    {
        $prefixes = ['VR'];

        $secondaryPrefix = trim($this->secondaryPrefixFor('receipt'));
        if ($secondaryPrefix !== '') {
            $prefixes[] = $secondaryPrefix;
        }

        return array_values(array_unique(array_filter($prefixes, fn ($prefix) => trim((string) $prefix) !== '')));
    }

    private function applyReceiptVoucherFilter($query)
    {
        $prefixes = $this->receiptVoucherPrefixes();

        return $query->where(function ($inner) use ($prefixes) {
            foreach ($prefixes as $index => $prefix) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $inner->{$method}('TRIM(COALESCE(daybookpart.vchno, "")) LIKE ?', [$prefix . '%']);
            }
        });
    }

    // ── Page ──

    public function index(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('account.receipt');
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
        $showBalance = $request->boolean('show_balance', $request->boolean('obcb', false));
        $openingBalance = (float) ($data['balance'] ?? 0);
        $closingBalance = $openingBalance;
        if (!($data['pdc'] ?? false)) {
            $closingBalance += (float) ($data['amount'] ?? 0) + (float) ($data['discount'] ?? 0);
        }

        return view('account.voucher-print', [
            'voucherType' => 'Receipt',
            'voucherData' => $data,
            'amountInWords' => $this->amountToWordsIndian((float) ($data['amount'] ?? 0)),
            'company' => $this->printHeaderData(),
            'showBalance' => $showBalance,
            'balanceInfo' => [
                'account_code' => trim((string) ($data['accode'] ?? '')),
                'account_name' => trim((string) ($data['acname'] ?? '')),
                'opening_balance' => round($openingBalance, 2),
                'opening_label' => $openingBalance >= 0 ? 'Cr' : 'Dr',
                'closing_balance' => round($closingBalance, 2),
                'closing_label' => $closingBalance >= 0 ? 'Cr' : 'Dr',
            ],
            'printSlip' => $request->boolean('slip', false),
            'printRuff' => false,
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

        $defaultCbCode = $this->trimUpper((string) $request->input('cbcode', ''));
        if ($defaultCbCode === '' || !collect($cbAccounts)->contains(fn ($row) => strtoupper((string) $row->accode) === $defaultCbCode)) {
            $cashRow = collect($cbAccounts)->first(fn ($row) => strtoupper((string) $row->accode) === 'CASH');
            $defaultCbCode = strtoupper((string) ($cashRow->accode ?? ''));
            if ($defaultCbCode === '' && !empty($cbAccounts)) {
                $defaultCbCode = strtoupper((string) $cbAccounts[0]->accode);
            }
        }

        $nextVchno = $this->nextReceiptVoucherPreview($control, $defaultCbCode);

        // Salesman list
        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')
                ->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
                ->orderBy('code')
                ->get()
                ->all();
        }

        // Discount account code
        $discAccCode = $this->generalProfile('RDISCAC', 'DISC');

        return $this->json([
            'success' => true,
            'nextVchno' => $nextVchno,
            'control' => $control,
            'defaultCbCode' => $defaultCbCode,
            'cbAccounts' => $cbAccounts,
            'salesmen' => $salesmen,
            'today' => date('d/m/Y'),
            'discAccCode' => $discAccCode,
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

        // Filter by type
        if ($type === 'c') $query->whereRaw("TRIM(actype2) = 'C'");
        elseif ($type === 's') $query->whereRaw("TRIM(actype2) = 'S'");
        elseif ($type === 'j') $query->whereRaw("TRIM(actype2) = 'J'");
        elseif ($type === 'g') $query->whereRaw("TRIM(actype2) = 'G'");
        elseif ($type === 'r') $query->whereRaw("TRIM(actype2) = 'R'");
        elseif ($type === 'f') $query->whereRaw("TRIM(actype2) = 'F'");
        elseif ($type === 'o') $query->whereRaw("TRIM(actype2) NOT IN ('C','S','J','G','R','F','H','B')");

        $clientSearchColumns = [];
        if ($this->hasTable('clients')) {
            foreach (['mobile', 'telephone', 'homemobile', 'idno', 'panadhar'] as $col) {
                if ($this->hasCol('clients', $col)) {
                    $clientSearchColumns[] = $col;
                }
            }
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            // Also match account codes whose linked clients row has a matching mobile/telephone/idno.
            // Customers/suppliers often live in `clients` keyed by the same code as in `accountm`.
            $clientCodes = [];
            if ($this->hasTable('clients') && $clientSearchColumns !== []) {
                $clientCodes = DB::table('clients')
                    ->where(function ($q) use ($like, $clientSearchColumns) {
                        foreach ($clientSearchColumns as $idx => $col) {
                            $idx === 0
                                ? $q->where($col, 'like', $like)
                                : $q->orWhere($col, 'like', $like);
                        }
                    })
                    ->limit(200)
                    ->pluck('code')
                    ->map(fn ($c) => trim((string) $c))
                    ->filter()
                    ->all();
            }
            $query->where(function ($q) use ($like, $clientCodes) {
                $q->whereRaw('TRIM(accode) LIKE ?', [$like])
                  ->orWhereRaw('TRIM(name) LIKE ?', [$like]);
                if (!empty($clientCodes)) {
                    $q->orWhereIn(DB::raw('TRIM(accode)'), $clientCodes);
                }
            });
        }

        $rows = $query->orderBy('name')->limit(500)->get();

        if ($this->hasTable('clients') && $rows->isNotEmpty()) {
            $phoneColumns = array_values(array_filter(['mobile', 'telephone', 'homemobile'], fn ($col) => $this->hasCol('clients', $col)));
            if ($phoneColumns !== []) {
                $codes = $rows->pluck('accode')->map(fn ($c) => trim((string) $c))->filter()->values()->all();
                $select = array_merge(['code'], $phoneColumns);
                $phones = DB::table('clients')
                    ->whereIn('code', $codes)
                    ->get($select)
                    ->keyBy(fn ($row) => trim((string) ($row->code ?? '')));

                $rows = $rows->map(function ($row) use ($phones, $phoneColumns) {
                    $client = $phones->get(trim((string) ($row->accode ?? '')));
                    $phone = '';
                    if ($client) {
                        foreach ($phoneColumns as $col) {
                            $phone = trim((string) ($client->{$col} ?? ''));
                            if ($phone !== '') {
                                break;
                            }
                        }
                    }
                    $row->mobile = $phone;
                    return $row;
                });
            }
        }

        return $this->json(['success' => true, 'data' => $rows->values()->all()]);
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
                $j->whereRaw('daybook.amount < 0');
            })
            ->selectRaw('daybookpart.slno, daybookpart.vchno, daybook.tdate, TRIM(daybook.accode) AS cbcode, ABS(daybook.amount) AS amount, TRIM(daybookpart.particular) AS particular');

        $this->applyReceiptVoucherFilter($query);

        $discAccCode = $this->generalProfile('RDISCAC', 'DISC');
        $query->whereRaw('UPPER(TRIM(daybook.accode)) <> ?', [strtoupper($discAccCode)]);

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
        $vchno = $this->trimUpper($request->input('vchno', ''));

        if ($slno <= 0 && $vchno !== '' && $this->hasTable('daybookpart')) {
            $slno = (int) (DB::table('daybookpart')
                ->join('daybook', 'daybook.slno', '=', 'daybookpart.slno')
                ->whereRaw('TRIM(daybookpart.vchno)=?', [$vchno])
                ->where('daybook.control', '<=', $control)
                ->max('daybookpart.slno') ?? 0);
        }

        if ($slno <= 0) return $this->json(['success' => false, 'error' => 'Invalid slno']);

        if (!$this->hasTable('daybookpart') || !$this->hasTable('daybook')) {
            return $this->json(['success' => false, 'error' => 'Tables missing']);
        }

        $dp = DB::table('daybookpart')->where('slno', $slno)->first();
        if (!$dp && $this->hasTable('pdclist')) {
            $pdc = DB::table('pdclist')->where('slno', $slno)->first();
            if ($pdc) {
                $cbcode = trim((string) ($pdc->bank ?? ''));
                $accode = trim((string) ($pdc->code ?? ''));
                $cbname = '';
                $acname = '';
                $actype2 = '';

                if ($cbcode !== '' && $this->hasTable('accountm')) {
                    $row = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$cbcode])->first();
                    if ($row) {
                        $cbname = trim((string) ($row->name ?? ''));
                    }
                }

                if ($accode !== '' && $this->hasTable('accountm')) {
                    $row = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$accode])->first();
                    if ($row) {
                        $acname = trim((string) ($row->name ?? ''));
                        $actype2 = strtoupper(trim((string) ($row->actype2 ?? '')));
                    }
                }

                return $this->json([
                    'success' => true,
                    'data' => [
                        'slno' => $slno,
                        'vchno' => trim((string) ($pdc->docno ?? '')),
                        'tdate' => $this->formatDate((string) ($pdc->tdate ?? '')),
                        'cbcode' => $cbcode,
                        'cbname' => $cbname,
                        'accode' => $accode,
                        'acname' => $acname,
                        'actype2' => $actype2,
                        'amount' => abs((float) ($pdc->amount ?? 0)),
                        'particular' => trim((string) ($pdc->particulars ?? '')),
                        'staff' => '',
                        'chequeno' => trim((string) ($pdc->chqno ?? '')),
                        'chequedate' => $this->formatDate((string) ($pdc->chqdate ?? '')),
                        'duedate' => '',
                        'rate' => 0,
                        'taxperc' => 0,
                        'taxamt' => 0,
                        'interstate' => false,
                        'taxreverse' => false,
                        'pdc' => true,
                        'balance' => round($this->getAccountBalance($accode, (int) ($pdc->control ?? $control)), 2),
                        'balance_label' => ($this->getAccountBalance($accode, (int) ($pdc->control ?? $control)) >= 0) ? 'Cr' : 'Dr',
                        'control' => (int) ($pdc->control ?? $control),
                    ],
                ]);
            }
        }
        if (!$dp) return $this->json(['success' => false, 'error' => 'Record not found']);

        $vchno = trim((string) ($dp->vchno ?? ''));

        // Get daybook entries for this slno
        $dbRows = DB::table('daybook')->where('slno', $slno)->get();
        $cbcode = '';
        $accode = '';
        $amount = 0;
        $tdate = '';
        $dbControl = $control;

        $discAccCode = $this->generalProfile('RDISCAC', 'DISC');
        foreach ($dbRows as $r) {
            $amt = (float) ($r->amount ?? 0);
            $dbControl = (int) ($r->control ?? $control);
            $tdate = (string) ($r->tdate ?? '');
            $rowAccode = trim((string) ($r->accode ?? ''));
            if ($amt < 0 && strtoupper($rowAccode) !== strtoupper($discAccCode)) {
                // Receipt stores cash/bank as negative row.
                $cbcode = $rowAccode;
                $amount = abs($amt);
            } elseif ($amt > 0) {
                $accode = $rowAccode;
            }
            // Discount account row is skipped (discount read from daybookpart)
        }

        // Get additional details from daybookpart
        $particular = trim((string) ($dp->particular ?? ''));
        $staff = trim((string) ($dp->staff ?? ''));
        $chequeno = trim((string) ($dp->chequeno ?? ''));
        $chequedate = '';
        if (isset($dp->chequedate)) {
            $chequedate = $this->formatDate((string) $dp->chequedate);
        }
        $duedate = '';
        if (isset($dp->duedate) && $dp->duedate) {
            $y = (int) date('Y', strtotime((string) $dp->duedate));
            if ($y > 1950) $duedate = $this->formatDate((string) $dp->duedate);
        }
        $rate = (float) ($dp->rate ?? 0);
        $taxperc = (float) ($dp->taxperc ?? 0);
        $taxamt = (float) ($dp->taxamt ?? 0);
        $discount = (float) ($dp->discount ?? 0);
        $interstate = strtoupper(trim((string) ($dp->interstate ?? 'N'))) === 'Y';
        $taxreverse = strtoupper(trim((string) ($dp->taxreverse ?? 'N'))) === 'Y';

        if ($discount == 0.0) {
            $discAccCode = strtoupper($this->generalProfile('RDISCAC', 'DISC'));
            foreach ($dbRows as $r) {
                $rowAccode = strtoupper(trim((string) ($r->accode ?? '')));
                $amt = (float) ($r->amount ?? 0);
                if ($rowAccode === $discAccCode && $amt < 0) {
                    $discount = abs($amt);
                    break;
                }
            }
        }

        // Check if PDC
        $isPdc = (substr($vchno, 0, 3) === 'PDC');

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
        $openingBalance = $balance;
        if (!$isPdc) {
            $openingBalance -= ($amount + $discount);
        }

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
                'discount' => $discount,
                'interstate' => $interstate,
                'taxreverse' => $taxreverse,
                'pdc' => $isPdc,
                'balance' => round($openingBalance, 2),
                'balance_label' => ($openingBalance >= 0) ? 'Cr' : 'Dr',
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
        $discount = abs((float) $request->input('discount', 0));

        if ($cbcode === '') return $this->json(['success' => false, 'error' => 'Cash/Bank account is required']);
        if ($accode === '') return $this->json(['success' => false, 'error' => 'Account code is required']);
        if ($amount <= 0) return $this->json(['success' => false, 'error' => 'Amount must be greater than zero']);

        if (!$this->hasTable('accountm')) {
            return $this->json(['success' => false, 'error' => 'Account master table not found.']);
        }
        if (!DB::table('accountm')->whereRaw('UPPER(TRIM(accode)) = ?', [$cbcode])->exists()) {
            return $this->json(['success' => false, 'error' => "Cash/Bank account '{$cbcode}' not found. Cannot save."]);
        }
        if (!DB::table('accountm')->whereRaw('UPPER(TRIM(accode)) = ?', [$accode])->exists()) {
            return $this->json(['success' => false, 'error' => "Account code '{$accode}' not found. Cannot save."]);
        }

        try {
            DB::beginTransaction();

            $dbCols = $this->getColumns('daybook');
            $dpCols = $this->getColumns('daybookpart');

            if ($mode === 'E' && $editSlno > 0) {
                // Delete old entries
                DB::table('daybook')->where('slno', $editSlno)->delete();
                DB::table('daybookpart')->where('slno', $editSlno)->delete();
                if ($this->hasTable('pdclist')) {
                    DB::table('pdclist')->where('slno', $editSlno)->delete();
                }
                $lslno = $editSlno;
                // Keep old voucher number
                $svchno = trim((string) $request->input('vchno', ''));
            } else {
                $lslno = $this->nextSerialNo();
                $svchno = $isPdc
                    ? $this->nextPdcReceiptVoucherNumber($control)
                    : $this->nextReceiptVoucherNumber($control, $cbcode);
            }

            if ($isPdc && $this->hasTable('pdclist')) {
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
                    'rp' => 'R',
                    'pend' => 'Y',
                    'control' => $control,
                ];
                $pdcCols = $this->getColumns('pdclist');
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
                    'discount' => round($discount, 2),
                    'interstate' => $interstate,
                    'taxreverse' => $taxreverse,
                    'tdate' => $tdate,
                    'control' => $control,
                    'ic' => (string) $request->session()->get('user_code', ''),
                    'ttime' => date('H:i:s'),
                ];
                $dpFiltered = array_filter($dp, fn($k) => in_array($k, $dpCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('daybookpart')->insert($dpFiltered);

                // Party account: amount + discount (total settlement)
                $partyAmount = round($amount + $discount, 2);

                $row1 = [
                    'slno' => $lslno,
                    'tdate' => $tdate,
                    'accode' => $accode,
                    'amount' => $partyAmount,
                    'control' => $control,
                    'opaccode' => $cbcode,
                ];
                $row1Filtered = array_filter($row1, fn($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('daybook')->insert($row1Filtered);

                // Cash/Bank: receives the receipt amount
                $row2 = [
                    'slno' => $lslno,
                    'tdate' => $tdate,
                    'accode' => $cbcode,
                    'amount' => -round($amount, 2),
                    'control' => $control,
                    'opaccode' => $accode,
                ];
                $row2Filtered = array_filter($row2, fn($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('daybook')->insert($row2Filtered);

                // Discount account entry (if discount > 0)
                if ($discount > 0) {
                    $discAccCode = $this->generalProfile('RDISCAC', 'DISC');
                    $row3 = [
                        'slno' => $lslno,
                        'tdate' => $tdate,
                        'accode' => $discAccCode,
                        'amount' => -round($discount, 2),
                        'control' => $control,
                        'opaccode' => $accode,
                    ];
                    $row3Filtered = array_filter($row3, fn($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY);
                    DB::table('daybook')->insert($row3Filtered);
                }
            }

            DB::commit();

            $message = 'Receipt saved successfully';
            $secondarySync = null;
            if ($shouldSecondarySync && $lslno > 0) {
                try {
                    $secondarySync = (new SecondaryDatabaseSync())->sync('receipt', (int) $lslno);
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

            $message = 'Receipt deleted successfully';
            if (SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code'))) {
                try {
                    $secondarySync = (new SecondaryDatabaseSync())->sync('receipt', $slno);
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
                ->where(function ($inner) {
                    foreach ($this->receiptVoucherPrefixes() as $index => $prefix) {
                        $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                        $inner->{$method}('TRIM(COALESCE(vchno, "")) LIKE ?', [$prefix . '%']);
                    }
                })
                ->where('slno', '<', $currentSlno)
                ->orderByDesc('slno')
                ->first();
        } else {
            $row = DB::table('daybookpart')
                ->where(function ($inner) {
                    foreach ($this->receiptVoucherPrefixes() as $index => $prefix) {
                        $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                        $inner->{$method}('TRIM(COALESCE(vchno, "")) LIKE ?', [$prefix . '%']);
                    }
                })
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
