<?php

namespace App\Http\Controllers;

use App\Support\LegacyEInvoiceJsonService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesBookReportController extends Controller
{
    private array $colCache = [];

    public function __construct(
        private readonly LegacyEInvoiceJsonService $legacyEInvoiceJsonService
    ) {
    }

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $issr = strtoupper((string) $request->query('issr', 'S'));
        if (!in_array($issr, ['S', 'R'], true)) {
            $issr = 'S';
        }

        $viewMode = strtolower((string) $request->query('view', 'book'));
        if (!in_array($viewMode, ['book', 'tax', 'other'], true)) {
            $viewMode = 'book';
        }

        $moduleId = (string) $request->query('module', ($issr === 'R' ? 'sales-reports-sales-return-book' : 'sales-reports-sales-book'));
        $title = $issr === 'R' ? 'Sales Return Book' : 'Sales Book';
        if ($viewMode === 'tax') {
            $title = 'Tax Sales Book';
        } elseif ($viewMode === 'other') {
            $title = 'Other Item Sales Book';
        }

        $rlevel = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        if ($rlevel <= 0) {
            $rlevel = 1;
        }

        return view('reports.sales-book', [
            'title' => $title,
            'moduleId' => $moduleId,
            'issr' => $issr,
            'viewMode' => $viewMode,
            'date1' => (string) $request->query('date1', date('Y-m-d')),
            'date2' => (string) $request->query('date2', date('Y-m-d')),
            'rlevel' => $rlevel,
        ]);
    }

    /* ── Lookup endpoint for dropdowns ──────────────────────────────── */
    public function lookups(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $counters = [];
        if ($this->hasTable('counter')) {
            $counters = DB::table('counter')->select('code', 'name')->orderBy('code')->get()->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $incharges = [];
        if ($this->hasTable('incharge')) {
            $incharges = DB::table('incharge')->select('code', 'name')->orderBy('code')->get()->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->select('code', 'name')->orderBy('code')->get()->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        } elseif ($this->hasTable('salesman')) {
            $salesmen = DB::table('salesman')->select('code', 'name')->orderBy('code')->get()->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $billtypes = [];
        if ($this->hasTable('salestype')) {
            $billtypes = DB::table('salestype')->select('code', 'name')->orderBy('code')->get()->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return response()->json([
            'ok' => true,
            'counters' => $counters,
            'incharges' => $incharges,
            'salesmen' => $salesmen,
            'billtypes' => $billtypes,
        ]);
    }

    /* ── Data endpoint ──────────────────────────────────────────────── */
    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $issr = strtoupper((string) $request->query('issr', 'S'));
        if (!in_array($issr, ['S', 'R'], true)) {
            $issr = 'S';
        }

        $viewMode = strtolower((string) $request->query('view', 'book'));
        if (!in_array($viewMode, ['book', 'tax', 'other', 'register', 'counterwise'], true)) {
            $viewMode = 'book';
        }

        $date1 = (string) $request->query('date1', date('Y-m-d'));
        $date2 = (string) $request->query('date2', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['success' => false, 'message' => 'Invalid date range'], 422);
        }

        $rlevel = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        if ($rlevel <= 0) {
            $rlevel = 1;
        }

        $filters = [
            'counter' => trim((string) $request->query('counter', '')),
            'ic' => trim((string) $request->query('ic', '')),
            'smcode' => trim((string) $request->query('smcode', '')),
            'billtype' => trim((string) $request->query('billtype', '')),
            'sale_status' => trim((string) $request->query('sale_status', '')),
        ];

        if ($issr === 'R') {
            $rows = $this->salesReturnRows($date1, $date2, $rlevel, $filters);
            return response()->json([
                'success' => true,
                'rows' => $rows,
                'totals' => $this->buildTotals($rows),
                'groupTotals' => $this->buildStatusTotals($rows),
                'mode' => 'return',
            ]);
        }

        if ($viewMode === 'register') {
            $rows = $this->registerRows($date1, $date2, $rlevel, $filters);
        } elseif ($viewMode === 'counterwise') {
            $rows = $this->counterwiseRows($date1, $date2, $rlevel, $filters);
        } elseif ($viewMode === 'tax' || $viewMode === 'other') {
            $rows = $this->taxRows($date1, $date2, $rlevel, $filters);
        } else {
            $rows = $this->salesBookRows($date1, $date2, $rlevel, $filters);
        }

        return response()->json([
            'success' => true,
            'rows' => $rows,
            'totals' => $this->buildTotals($rows),
            'groupTotals' => $this->buildStatusTotals($rows),
            'mode' => $viewMode,
        ]);
    }

    /* ── Sales Book (summary one row per bill) ─────────────────────── */
    public function bulkEInvoiceJson(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->validate([
            'slnos' => 'required|array|min:1',
            'slnos.*' => 'integer|min:1',
        ]);

        try {
            $result = $this->legacyEInvoiceJsonService->generateBulk($payload['slnos']);

            return response()->json([
                'ok' => true,
                'message' => 'Generated ' . $result['count'] . ' e-invoice JSON file(s).',
                'count' => $result['count'],
                'files' => $result['files'],
                'download_url' => url('/sales-book-report/einvoice-download/' . urlencode($result['zip_name'])),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Bulk e-invoice generation failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function downloadEInvoiceArchive(Request $request, string $file)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $path = $this->legacyEInvoiceJsonService->absoluteOutputPath($file);
        abort_unless(is_file($path), 404, 'E-invoice archive not found');

        return response()->download($path);
    }

    public function rearrangeBillNos(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->validate([
            'issr' => 'required|string|max:1',
            'date_from' => 'required|string|max:20',
            'include_estimates' => 'nullable|boolean',
            'selected_slnos' => 'nullable|array',
            'selected_slnos.*' => 'integer|min:1',
        ]);

        $issr = strtoupper(trim((string) ($payload['issr'] ?? 'S')));
        if (!in_array($issr, ['S', 'R'], true)) {
            return response()->json(['ok' => false, 'message' => 'Invalid report type.'], 422);
        }

        $fromDate = $this->normalizeDate((string) ($payload['date_from'] ?? ''));
        if ($fromDate === null) {
            return response()->json(['ok' => false, 'message' => 'Invalid from date.'], 422);
        }

        $includeEstimates = (bool) ($payload['include_estimates'] ?? false);
        $selectedSlnos = collect($payload['selected_slnos'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        $table = $issr === 'R' ? 'salesrm' : 'salesm';
        if (!$this->hasTable($table)) {
            return response()->json(['ok' => false, 'message' => 'Sales table not found.'], 404);
        }

        $query = DB::table($table)
            ->select(['slno', 'billno', 'tdate', 'control', 'custname']);
        if ($issr === 'S' && $this->hasColumn($table, 'billtype')) {
            $query->addSelect('billtype');
        }
        if ($issr === 'R' && $this->hasColumn($table, 'sr')) {
            $query->where('sr', 'R');
        }

        $query->whereDate('tdate', '>=', $fromDate);
        if ($selectedSlnos !== []) {
            $query->whereIn('slno', $selectedSlnos);
        }
        if (!$includeEstimates) {
            $query->where('control', 1);
        } else {
            $query->whereIn('control', [1, 2]);
        }

        $rows = $query->orderBy('tdate')->orderBy('slno')->get();
        if ($rows->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'No rows found for rearrange.'], 422);
        }

        $billTypeWise = strtoupper($this->readSoftwareSetting('BillTypewiseBillNo', 'N')) === 'Y';

        $updated = 0;
        DB::transaction(function () use ($rows, $issr, $fromDate, $includeEstimates, $billTypeWise, &$updated): void {
            $salesCounter = $issr === 'R'
                ? $this->initialCounterBeforeDate('salesrm', $fromDate, 1, 'SRETURNB', null, 'SRB/')
                : $this->initialCounterBeforeDate('salesm', $fromDate, 1, 'SALESB', null, $this->readGeneralText('SBPREF', 'SLB/'));

            $estimateCounter = $includeEstimates
                ? ($issr === 'R'
                    ? $this->initialCounterBeforeDate('salesrm', $fromDate, 2, 'SRETURNE', null, 'SRE/')
                    : $this->initialCounterBeforeDate('salesm', $fromDate, 2, 'SALESE', null, $this->readGeneralText('SEPREF', 'SLE/')))
                : 0;

            $prefixCounters = [];

            foreach ($rows as $row) {
                $control = (int) ($row->control ?? 1);
                $slno = (int) ($row->slno ?? 0);
                if ($slno <= 0) {
                    continue;
                }

                $billNo = '';
                $particular = '';

                if ($control === 1) {
                    if ($issr === 'S' && $billTypeWise) {
                        $billType = trim((string) ($row->billtype ?? ''));
                        [$prefix] = $this->salesTypePrefixAndTax($billType);
                        if ($prefix !== '') {
                            $counterCode = 'SALES' . $prefix;
                            if (!array_key_exists($counterCode, $prefixCounters)) {
                                $prefixCounters[$counterCode] = $this->initialCounterBeforeDate('salesm', $fromDate, 1, $counterCode, $prefix, $prefix);
                            }
                            $prefixCounters[$counterCode]++;
                            $billNo = $this->buildRunningDocNo($prefix, $prefixCounters[$counterCode], $this->salesBillNumberLength());
                            $this->upsertGeneralCounter($counterCode, $prefixCounters[$counterCode]);
                        }
                    }

                    if ($billNo === '') {
                        $salesCounter++;
                        if ($issr === 'R') {
                            $billNo = $this->buildRunningDocNo('SRB/', $salesCounter, $this->salesBillNumberLength());
                        } else {
                            $billNo = $this->buildRunningDocNo($this->readGeneralText('SBPREF', 'SLB/'), $salesCounter, $this->salesBillNumberLength());
                        }
                    }

                    $particular = $issr === 'R'
                        ? 'By Sales Return (' . $billNo . ')'
                        : 'By Sales (' . $billNo . ')';
                } elseif ($control === 2 && $includeEstimates) {
                    $estimateCounter++;
                    if ($issr === 'R') {
                        $billNo = $this->buildRunningDocNo('SRE/', $estimateCounter, $this->salesBillNumberLength());
                        $particular = 'By Sales Return (' . $billNo . ')';
                    } else {
                        $billNo = $this->buildRunningDocNo($this->readGeneralText('SEPREF', 'SLE/'), $estimateCounter, $this->salesBillNumberLength());
                        $particular = 'By Sales (' . $billNo . ')';
                    }
                } else {
                    continue;
                }

                $partyName = trim((string) ($row->custname ?? ''));
                if ($issr === 'S' && $partyName !== '') {
                    $particular .= ' To ' . $partyName;
                }
                $particular = mb_substr($particular, 0, 40);

                DB::table($issr === 'R' ? 'salesrm' : 'salesm')
                    ->where('slno', $slno)
                    ->update(['billno' => $billNo]);

                if ($this->hasTable('purchasem') && Schema::hasColumn('purchasem', 'docno')) {
                    DB::table('purchasem')->where('slno', $slno)->update(['docno' => $billNo]);
                }

                if ($issr === 'S' && $this->hasTable('salesrm') && Schema::hasColumn('salesrm', 'billno')) {
                    DB::table('salesrm')->where('slno', $slno)->update(['billno' => $billNo]);
                }

                if ($this->hasTable('daybookpart') && Schema::hasColumn('daybookpart', 'particular')) {
                    DB::table('daybookpart')->where('slno', $slno)->update(['particular' => $particular]);
                }

                $updated++;
            }

            if ($issr === 'R') {
                $this->upsertGeneralCounter('SRETURNB', $salesCounter);
                if ($includeEstimates) {
                    $this->upsertGeneralCounter('SRETURNE', $estimateCounter);
                }
            } else {
                if (!$billTypeWise) {
                    $this->upsertGeneralCounter('SALESB', $salesCounter);
                }
                if ($includeEstimates) {
                    $this->upsertGeneralCounter('SALESE', $estimateCounter);
                }
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Rearranged ' . $updated . ' bill(s) successfully.',
            'updated' => $updated,
        ]);
    }

    private function salesBookRows(string $date1, string $date2, int $rlevel, array $filters): array
    {
        if (!$this->hasTable('salesm')) {
            return [];
        }

        $salesdExists = $this->hasTable('salesd');
        $q = DB::table('salesm as m')
            ->selectRaw('m.slno, m.tdate, m.billno, m.custname')
            ->selectRaw($this->columnExpr('salesm', 'billamt', 'm', 'billamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'status', 'm', 'status', '0'))
            ->selectRaw($this->columnExpr('salesm', 'discount', 'm', 'discount', '0'))
            ->selectRaw($this->columnExpr('salesm', 'discperc', 'm', 'discperc', '0'))
            ->selectRaw($this->columnExpr('salesm', 'ramt', 'm', 'ramt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'eamt', 'm', 'eamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'staxamt', 'm', 'staxamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'sretamt', 'm', 'sretamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'ramtafter', 'm', 'ramtafter', '0'))
            ->selectRaw($this->columnExpr('salesm', 'round', 'm', 'round_amt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'netamt', 'm', 'netamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'astamt', 'm', 'astamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'counter', 'm', 'counter', "''"))
            ->selectRaw($this->columnExpr('salesm', 'ic', 'm', 'ic', "''"))
            ->selectRaw($this->columnExpr('salesm', 'hmc', 'm', 'hmc', '0'))
            ->selectRaw($this->columnExpr('salesm', 'smcode', 'm', 'smcode', "''"))
            ->selectRaw($this->columnExpr('salesm', 'advance', 'm', 'advance', '0'))
            ->selectRaw($this->columnExpr('salesm', 'billtype', 'm', 'billtype', "''"))
            ->selectRaw($this->columnExpr('salesm', 'duedate', 'm', 'duedate', "''"))
            ->selectRaw($this->columnExpr('salesm', 'loan', 'm', 'loan', "'N'"));

        if ($salesdExists) {
            $q->selectRaw('(select coalesce(sum(sd.mcharge),0) from salesd sd where sd.slno = m.slno) as totva');
            $q->selectRaw('(select coalesce(sum(sd.weight),0) from salesd sd where sd.slno = m.slno) as totwgt');
            $q->selectRaw('(select coalesce(sum(sd.stonewgt),0) from salesd sd where sd.slno = m.slno) as totstwgt');
        } else {
            $q->selectRaw('0 as totva, 0 as totwgt, 0 as totstwgt');
        }

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $this->applyFilters($q, 'salesm', 'm', $rlevel, $filters);

        $rows = $q->orderBy('tdate')->orderBy('billno')->get()->map(function ($r) {
            $row = (array) $r;
            $row['ttax'] = (float) ($row['staxamt'] ?? 0) + (float) ($row['astamt'] ?? 0);
            $row['tnetamt'] = (float) ($row['netamt'] ?? 0);
            $row['tramt'] = (float) ($row['ramt'] ?? 0);
            $row['balance'] = $row['tnetamt'] - $row['tramt'];
            $totva = (float) ($row['totva'] ?? 0);
            $totwgt = (float) ($row['totwgt'] ?? 0);
            $totstwgt = (float) ($row['totstwgt'] ?? 0);
            $row['dvperc'] = $totva > 0 ? (((float) ($row['discount'] ?? 0) / $totva) * 100) : 0;
            $den = $totwgt - $totstwgt;
            $row['va_per_gm'] = $den > 0 ? (($totva - (float) ($row['discount'] ?? 0)) / $den) : 0;
            $saleType = $this->deriveSaleType((float) $row['balance'], (float) $row['tramt'], (string) ($row['loan'] ?? 'N'));
            $row['sale_type_code'] = $saleType['code'];
            $row['sale_type_label'] = $saleType['label'];
            return $row;
        })->values()->all();

        return $this->filterBySaleStatus($rows, $filters);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return str_contains($value, '/')
                ? Carbon::createFromFormat('d/m/Y', $value)->toDateString()
                : Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array(strtolower($column), $this->getColumns($table), true);
    }

    private function getColumns(string $table): array
    {
        if (!isset($this->colCache[$table])) {
            $this->colCache[$table] = array_map('strtolower', $this->columnList($table));
        }

        return $this->colCache[$table];
    }

    private function salesBillNumberLength(): int
    {
        $len = (int) $this->toNum($this->readGeneralText('SBLEN', '5'));
        return $len > 0 ? $len : 5;
    }

    private function readGeneralText(string $code, string $default = ''): string
    {
        if (!$this->hasTable('generals')) {
            return $default;
        }

        $value = DB::table('generals')->where('code', $code)->value('cvalue');
        return $value === null ? $default : trim((string) $value);
    }

    private function readSoftwareSetting(string $key, string $default = ''): string
    {
        $path = storage_path('app/software-settings.ini');
        if (!is_file($path) || !is_readable($path)) {
            return $default;
        }

        $inSoftware = false;
        foreach (preg_split('/\r\n|\r|\n/', (string) @file_get_contents($path)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#') || str_starts_with($line, '//')) {
                continue;
            }
            if ($line === '[Software]') {
                $inSoftware = true;
                continue;
            }
            if ($inSoftware && str_starts_with($line, '[')) {
                break;
            }
            if ($inSoftware && stripos($line, $key . '=') === 0) {
                return trim(substr($line, strlen($key) + 1));
            }
        }

        return $default;
    }

    private function salesTypePrefixAndTax(string $billType): array
    {
        if ($billType === '' || !$this->hasTable('salestype')) {
            return ['', 0.0];
        }

        $row = DB::table('salestype')
            ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper(trim($billType))])
            ->first(['prefix', 'taxperc']);

        return [
            trim((string) ($row->prefix ?? '')),
            (float) ($row->taxperc ?? 0),
        ];
    }

    private function initialCounterBeforeDate(string $table, string $fromDate, int $control, string $counterCode, ?string $prefix, string $defaultPrefix): int
    {
        $lastBillNo = '';
        if ($this->hasTable($table)) {
            $query = DB::table($table)
                ->whereDate('tdate', '<', $fromDate)
                ->where('control', $control);

            if ($table === 'salesrm' && $this->hasColumn($table, 'sr')) {
                $query->where('sr', 'R');
            }

            if ($prefix !== null && $prefix !== '' && $this->hasColumn($table, 'billno')) {
                $query->where('billno', 'like', $prefix . '%');
            }

            $lastBillNo = trim((string) ($query->max('billno') ?? ''));
        }

        if ($lastBillNo !== '') {
            return $this->extractRunningNumber($lastBillNo, $prefix ?: $defaultPrefix);
        }

        return $this->readGeneralCounter($counterCode);
    }

    private function extractRunningNumber(string $billNo, string $prefix = ''): int
    {
        $billNo = trim($billNo);
        if ($billNo === '') {
            return 0;
        }

        if ($prefix !== '' && str_starts_with($billNo, $prefix)) {
            $suffix = substr($billNo, strlen($prefix));
            return (int) $this->toNum($suffix);
        }

        if (preg_match('/(\d+)\s*$/', $billNo, $m) === 1) {
            return (int) $this->toNum($m[1]);
        }

        return (int) $this->toNum($billNo);
    }

    private function buildRunningDocNo(string $prefix, int $number, int $len = 5): string
    {
        return trim($prefix) . str_pad((string) max(0, $number), max(1, $len), '0', STR_PAD_LEFT);
    }

    private function readGeneralCounter(string $code): int
    {
        if (!$this->hasTable('generali')) {
            return 0;
        }

        return (int) $this->toNum(DB::table('generali')->where('code', trim($code))->value('cvalue') ?? 0);
    }

    private function upsertGeneralCounter(string $code, int $value): void
    {
        if (!$this->hasTable('generali')) {
            return;
        }

        DB::table('generali')->updateOrInsert(
            ['code' => trim($code)],
            ['cvalue' => (string) max(0, $value)]
        );
    }

    private function toNum(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = str_replace(',', '', trim((string) $value));
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /* ── Sales Return Book ─────────────────────────────────────────── */
    private function salesReturnRows(string $date1, string $date2, int $rlevel, array $filters): array
    {
        if (!$this->hasTable('salesrm')) {
            return [];
        }

        $q = DB::table('salesrm as m')
            ->selectRaw('m.slno, m.tdate, m.billno, m.custname')
            ->selectRaw($this->columnExpr('salesrm', 'billamt', 'm', 'billamt', '0'))
            ->selectRaw($this->columnExpr('salesrm', 'status', 'm', 'status', '0'))
            ->selectRaw($this->columnExpr('salesrm', 'discount', 'm', 'discount', '0'))
            ->selectRaw($this->columnExpr('salesrm', 'staxamt', 'm', 'staxamt', '0'))
            ->selectRaw($this->columnExpr('salesrm', 'pamt', 'm', 'pamt', '0'))
            ->selectRaw($this->columnExpr('salesrm', 'pamtafter', 'm', 'pamtafter', '0'))
            ->selectRaw($this->columnExpr('salesrm', 'duedate', 'm', 'duedate', "''"))
            ->selectRaw($this->columnExpr('salesrm', 'ic', 'm', 'ic', "''"));

        $q->whereBetween('m.tdate', [$date1, $date2]);
        if ($this->hasCol('salesrm', 'control')) {
            $q->where('m.control', '<=', $rlevel);
        }
        if ($filters['ic'] !== '' && $this->hasCol('salesrm', 'ic')) {
            $q->where('m.ic', $filters['ic']);
        }
        if ($filters['counter'] !== '' && $this->hasCol('salesrm', 'counter')) {
            $q->where('m.counter', $filters['counter']);
        }

        $rows = $q->orderBy('tdate')->orderBy('slno')->get()->map(function ($r) {
            $row = (array) $r;
            $row['netamt'] = (float) ($row['billamt'] ?? 0) - (float) ($row['discount'] ?? 0) + (float) ($row['staxamt'] ?? 0);
            $row['tramt'] = (float) ($row['pamt'] ?? 0) + (float) ($row['pamtafter'] ?? 0);
            $row['balance'] = $row['netamt'] - $row['tramt'];
            return $row;
        })->values()->all();

        return $rows;
    }

    /* ── Register mode (item-level detail) ─────────────────────────── */
    private function registerRows(string $date1, string $date2, int $rlevel, array $filters): array
    {
        if (!$this->hasTable('salesm') || !$this->hasTable('salesd') || !$this->hasTable('items')) {
            return [];
        }

        $q = DB::table('salesd as d')
            ->join('salesm as m', 'd.slno', '=', 'm.slno')
            ->join('items as i', 'd.code', '=', 'i.code')
            ->selectRaw('m.slno as m_slno, m.tdate, m.billno, m.custname')
            ->selectRaw($this->columnExpr('salesm', 'status', 'm', 'status', '0'))
            ->selectRaw($this->columnExpr('salesm', 'billamt', 'm', 'billamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'eamt', 'm', 'eamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'sretamt', 'm', 'sretamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'staxamt', 'm', 'staxamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'discount', 'm', 'm_discount', '0'))
            ->selectRaw($this->columnExpr('salesm', 'ramt', 'm', 'ramt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'ramtafter', 'm', 'ramtafter', '0'))
            ->selectRaw($this->columnExpr('salesm', 'round', 'm', 'round_amt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'counter', 'm', 'counter', "''"))
            ->selectRaw($this->columnExpr('salesm', 'ic', 'm', 'ic', "''"))
            ->selectRaw($this->columnExpr('salesm', 'smcode', 'm', 'smcode', "''"))
            ->selectRaw($this->columnExpr('salesm', 'astamt', 'm', 'astamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'hmc', 'm', 'hmc', '0'))
            ->selectRaw($this->columnExpr('salesm', 'netamt', 'm', 'netamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'loan', 'm', 'loan', "'N'"))
            ->selectRaw($this->columnExpr('salesm', 'custcode', 'm', 'custcode', "''"))
            ->selectRaw($this->columnExpr('salesm', 'addr', 'm', 'addr', "''"))
            ->selectRaw($this->columnExpr('salesm', 'mobno', 'm', 'mobno', "''"))
            ->selectRaw('d.sno, i.name as item_name, d.code as item_code')
            ->selectRaw('d.qty, d.weight, d.rate, d.amount');

        // Optional salesd columns
        if ($this->hasCol('salesd', 'stonewgt')) {
            $q->selectRaw('d.stonewgt');
        } else {
            $q->selectRaw('0 as stonewgt');
        }
        if ($this->hasCol('salesd', 'mcharge')) {
            $q->selectRaw('d.mcharge');
        } else {
            $q->selectRaw('0 as mcharge');
        }
        if ($this->hasCol('salesd', 'wastage')) {
            $q->selectRaw('d.wastage');
        } else {
            $q->selectRaw('0 as wastage');
        }
        if ($this->hasCol('salesd', 'stoneprice')) {
            $q->selectRaw('d.stoneprice');
        } else {
            $q->selectRaw('0 as stoneprice');
        }
        if ($this->hasCol('salesd', 'stktype')) {
            $q->selectRaw('d.stktype');
        } else {
            $q->selectRaw("'' as stktype");
        }
        if ($this->hasCol('salesd', 'jcode')) {
            $q->selectRaw('d.jcode');
        } else {
            $q->selectRaw("'' as jcode");
        }
        if ($this->hasCol('salesd', 'bcode')) {
            $q->selectRaw('d.bcode');
        } else {
            $q->selectRaw('0 as bcode');
        }
        if ($this->hasCol('items', 'itype')) {
            $q->selectRaw('i.itype');
        } else {
            $q->selectRaw("'' as itype");
        }
        if ($this->hasCol('items', 'grpcode')) {
            $q->selectRaw('i.grpcode');
        } else {
            $q->selectRaw("'' as grpcode");
        }

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $this->applyFilters($q, 'salesm', 'm', $rlevel, $filters);

        $rows = $q->orderBy('m.tdate')->orderBy('m.slno')->orderBy('d.sno')
            ->get()->map(function ($r) {
                $row = (array) $r;
                $row['netwgt'] = (float) ($row['weight'] ?? 0) - (float) ($row['stonewgt'] ?? 0);
                $va = (float) ($row['mcharge'] ?? 0) + (float) ($row['wastage'] ?? 0) * (float) ($row['rate'] ?? 0);
                $row['va'] = $va;
                $baseAmt = (float) ($row['amount'] ?? 0) - $va;
                $row['vaperc'] = $baseAmt > 0 ? ($va * 100 / $baseAmt) : 0;
                $netAmt = (float) ($row['netamt'] ?? 0);
                $received = (float) ($row['ramt'] ?? 0);
                $balance = $netAmt - $received;
                $saleType = $this->deriveSaleType($balance, $received, (string) ($row['loan'] ?? 'N'));
                $row['balance'] = $balance;
                $row['sale_type_code'] = $saleType['code'];
                $row['sale_type_label'] = $saleType['label'];
                return $row;
            })->values()->all();

        return $this->filterBySaleStatus($rows, $filters);
    }

    /* ── Counter-wise summary ──────────────────────────────────────── */
    private function counterwiseRows(string $date1, string $date2, int $rlevel, array $filters): array
    {
        if (!$this->hasTable('salesm') || !$this->hasCol('salesm', 'counter')) {
            return [];
        }

        $q = DB::table('salesm as m')
            ->selectRaw('m.counter')
            ->selectRaw('COUNT(DISTINCT m.slno) as bill_count')
            ->selectRaw('SUM(' . ($this->hasCol('salesm', 'billamt') ? 'm.billamt' : '0') . ') as total_billamt')
            ->selectRaw('SUM(' . ($this->hasCol('salesm', 'netamt') ? 'm.netamt' : '0') . ') as total_netamt')
            ->selectRaw('SUM(' . ($this->hasCol('salesm', 'discount') ? 'm.discount' : '0') . ') as total_discount')
            ->selectRaw('SUM(' . ($this->hasCol('salesm', 'ramt') ? 'm.ramt' : '0') . ') as total_ramt')
            ->selectRaw('SUM(' . ($this->hasCol('salesm', 'staxamt') ? 'm.staxamt' : '0') . ') as total_tax');

        $q->whereBetween('m.tdate', [$date1, $date2]);
        if ($this->hasCol('salesm', 'control')) {
            $q->where('m.control', '<=', $rlevel);
        }
        if ($this->hasCol('salesm', 'opbill')) {
            $q->where(function ($sub) {
                $sub->whereNull('m.opbill')
                    ->orWhere('m.opbill', '<>', 1);
            });
        }

        $rows = $q->groupBy('m.counter')->orderBy('m.counter')
            ->get()->map(function ($r) {
                $row = (array) $r;
                $row['total_balance'] = (float) ($row['total_netamt'] ?? 0) - (float) ($row['total_ramt'] ?? 0);
                return $row;
            })->values()->all();

        return $rows;
    }

    /* ── Tax / Other Items view ────────────────────────────────────── */
    private function taxRows(string $date1, string $date2, int $rlevel, array $filters): array
    {
        if (!$this->hasTable('salesm')) {
            return [];
        }

        $salesdExists = $this->hasTable('salesd');
        $clientsExists = $this->hasTable('clients');

        $q = DB::table('salesm as m')
            ->selectRaw('m.slno, m.billno, m.tdate, m.custname')
            ->selectRaw($this->columnExpr('salesm', 'addr', 'm', 'addr', "''"))
            ->selectRaw($this->columnExpr('salesm', 'pan', 'm', 'pan', "''"))
            ->selectRaw($this->columnExpr('salesm', 'statecode', 'm', 'statecode', "''"))
            ->selectRaw($this->columnExpr('salesm', 'eamt', 'm', 'eamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'billamt', 'm', 'billamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'status', 'm', 'status', '0'))
            ->selectRaw($this->columnExpr('salesm', 'sgst', 'm', 'sgst', '0'))
            ->selectRaw($this->columnExpr('salesm', 'cgst', 'm', 'cgst', '0'))
            ->selectRaw($this->columnExpr('salesm', 'igst', 'm', 'igst', '0'))
            ->selectRaw($this->columnExpr('salesm', 'staxamt', 'm', 'staxamt', '0'))
            ->selectRaw($this->columnExpr('salesm', 'discount', 'm', 'discount', '0'))
            ->selectRaw($this->columnExpr('salesm', 'advance', 'm', 'prevadv', '0'))
            ->selectRaw($this->columnExpr('salesm', 'billtype', 'm', 'billtype', "''"))
            ->selectRaw($this->columnExpr('salesm', 'counter', 'm', 'counter', "''"))
            ->selectRaw($this->columnExpr('salesm', 'ic', 'm', 'ic', "''"))
            ->selectRaw($this->columnExpr('salesm', 'smcode', 'm', 'smcode', "''"))
            ->selectRaw($this->columnExpr('salesm', 'loan', 'm', 'loan', "'N'"));

        if ($salesdExists) {
            $q->selectRaw('(select coalesce(max(sd.rate),0) from salesd sd where sd.slno = m.slno) as maxrate');
            $q->selectRaw('(select coalesce(max(it.vatcode),"") from items it, salesd sd where it.code = sd.code and sd.slno = m.slno) as hsncode');
            $q->selectRaw('(select coalesce(max(it.name),"") from items it, salesd sd where it.code = sd.code and sd.slno = m.slno) as itemname');
            $q->selectRaw('(select coalesce(sum(sd.weight),0) from salesd sd where sd.slno = m.slno) as grosswgt');
            $q->selectRaw('(select coalesce(sum(sd.stonewgt),0) from salesd sd where sd.slno = m.slno) as stonewgt');
            $q->selectRaw('(select coalesce(sum(sd.weight - sd.stonewgt),0) from salesd sd where sd.slno = m.slno) as totwgt');
            $q->selectRaw('(select coalesce(sum(sd.mcharge),0) from salesd sd where sd.slno = m.slno) as va');
            $q->selectRaw('(select coalesce(sum(sd.stoneprice),0) from salesd sd where sd.slno = m.slno) as stoneprice');
        } else {
            $q->selectRaw('0 as maxrate, "" as hsncode, "" as itemname, 0 as grosswgt, 0 as stonewgt, 0 as totwgt, 0 as va, 0 as stoneprice');
        }

        if ($this->hasTable('spdmddet') && $salesdExists) {
            $q->selectRaw('(select coalesce(sum(sp.dmdamt),0) from spdmddet sp, salesd sd where sp.slno = m.slno and sp.sno = sd.sno and sd.slno = m.slno) as dmdamt');
        } else {
            $q->selectRaw('0 as dmdamt');
        }

        if ($clientsExists && $this->hasCol('salesm', 'custcode') && $this->hasCol('clients', 'code')) {
            if ($this->hasCol('clients', 'tin')) {
                $q->selectRaw('(select c.tin from clients c where c.code = m.custcode limit 1) as ctin');
            } else {
                $q->selectRaw('"" as ctin');
            }
        } else {
            $q->selectRaw('"" as ctin');
        }

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $this->applyFilters($q, 'salesm', 'm', $rlevel, $filters);

        $rows = $q->orderBy('m.tdate')->orderBy('m.billno')->get()->map(function ($r) {
            $row = (array) $r;
            $row['stone_total'] = (float) ($row['stoneprice'] ?? 0) + (float) ($row['dmdamt'] ?? 0);
            $row['totamt'] = (float) ($row['billamt'] ?? 0) - (float) ($row['discount'] ?? 0) + (float) ($row['staxamt'] ?? 0);
            $received = (float) ($row['ramt'] ?? 0);
            $balance = $row['totamt'] - $received;
            $saleType = $this->deriveSaleType($balance, $received, (string) ($row['loan'] ?? 'N'));
            $row['balance'] = $balance;
            $row['sale_type_code'] = $saleType['code'];
            $row['sale_type_label'] = $saleType['label'];
            return $row;
        })->values()->all();

        return $this->filterBySaleStatus($rows, $filters);
    }

    /* ── Shared filter helper ──────────────────────────────────────── */
    private function applyFilters($q, string $table, string $alias, int $rlevel, array $filters): void
    {
        if ($this->hasCol($table, 'control')) {
            $q->where($alias . '.control', '<=', $rlevel);
        }
        if ($this->hasCol($table, 'opbill')) {
            $q->where(function ($sub) use ($alias) {
                $sub->whereNull($alias . '.opbill')
                    ->orWhere($alias . '.opbill', '<>', 1);
            });
        }
        if ($filters['counter'] !== '' && $this->hasCol($table, 'counter')) {
            $q->where($alias . '.counter', $filters['counter']);
        }
        if ($filters['ic'] !== '' && $this->hasCol($table, 'ic')) {
            $q->where($alias . '.ic', $filters['ic']);
        }
        if ($filters['smcode'] !== '' && $this->hasCol($table, 'smcode')) {
            $q->where($alias . '.smcode', $filters['smcode']);
        }
        if ($filters['billtype'] !== '' && $this->hasCol($table, 'billtype')) {
            $q->where($alias . '.billtype', $filters['billtype']);
        }
    }

    /* ── Totals ────────────────────────────────────────────────────── */
    private function buildTotals(array $rows): array
    {
        $keys = [
            'billamt', 'eamt', 'sretamt', 'advance', 'discount', 'hmc', 'ttax', 'tnetamt', 'tramt', 'balance',
            'netamt', 'staxamt', 'pamt', 'pamtafter', 'totamt', 'va', 'sgst', 'cgst', 'igst',
            'grosswgt', 'stonewgt', 'totwgt', 'stone_total', 'qty', 'weight', 'netwgt', 'amount', 'mcharge',
            'total_billamt', 'total_netamt', 'total_discount', 'total_ramt', 'total_tax', 'total_balance', 'bill_count',
        ];
        $totals = [];
        foreach ($keys as $k) {
            $totals[$k] = 0.0;
        }
        foreach ($rows as $row) {
            foreach ($keys as $k) {
                $totals[$k] += (float) ($row[$k] ?? 0);
            }
        }
        return $totals;
    }

    private function buildStatusTotals(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $status = (int) ($row['sale_type_code'] ?? $row['status'] ?? 0);
            if (!isset($grouped[$status])) {
                $grouped[$status] = ['status' => $status, 'rows' => 0, 'billamt' => 0.0, 'netamt' => 0.0, 'balance' => 0.0];
            }
            $grouped[$status]['rows']++;
            $grouped[$status]['billamt'] += (float) ($row['billamt'] ?? $row['total_billamt'] ?? 0);
            $grouped[$status]['netamt'] += (float) ($row['tnetamt'] ?? $row['netamt'] ?? $row['total_netamt'] ?? 0);
            $grouped[$status]['balance'] += (float) ($row['balance'] ?? $row['total_balance'] ?? 0);
        }
        ksort($grouped);
        return array_values($grouped);
    }

    private function filterBySaleStatus(array $rows, array $filters): array
    {
        $wanted = strtoupper(trim((string) ($filters['sale_status'] ?? '')));
        if ($wanted === '') {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($wanted) {
            $code = (string) (int) ($row['sale_type_code'] ?? 0);
            return match ($wanted) {
                'CASH' => $code === '3',
                '2', 'PARTIAL' => $code === '2',
                '1', 'CREDIT' => $code === '1',
                default => true,
            };
        }));
    }

    private function deriveSaleType(float $balance, float $received, string $loan = 'N'): array
    {
        $balance = round($balance, 2);
        $received = round($received, 2);
        $loan = strtoupper(trim($loan));

        if ($loan !== 'Y') {
            return ['code' => 3, 'label' => 'Cash Sale'];
        }

        if (abs($balance) <= 0.009) {
            return ['code' => 3, 'label' => 'Cash Sale'];
        }

        if ($balance > 0 && abs($received) > 0.009) {
            return ['code' => 2, 'label' => 'Partial Credit'];
        }

        return ['code' => 1, 'label' => 'Credit Sale'];
    }

    /* ── Column helpers ────────────────────────────────────────────── */
    private function hasCol(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $this->colCache)) {
            $this->colCache[$key] = $this->hasTable($table) && Schema::hasColumn($table, $column);
        }
        return $this->colCache[$key];
    }

    private function columnExpr(string $table, string $column, string $alias, string $as, string $fallback): string
    {
        if ($this->hasCol($table, $column)) {
            return $alias . '.' . $column . ' as ' . $as;
        }
        return $fallback . ' as ' . $as;
    }
}
