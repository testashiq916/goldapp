<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TermSummaryController extends Controller
{
    private array $columnCache = [];
    private array $tableCache = [];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.term-summary', [
            'title' => 'Term Summary',
            'dateFrom' => date('Y-m-01'),
            'dateTo' => date('Y-m-d'),
            'gilevel' => max(1, (int) $request->session()->get('gilevel', 1)),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $dateFrom = $this->normalizeDate((string) $request->query('date_from', date('Y-m-01')));
        $dateTo = $this->normalizeDate((string) $request->query('date_to', date('Y-m-d')));
        $gilevel = max(1, (int) $request->query('gilevel', (int) $request->session()->get('gilevel', 1)));

        if (!$dateFrom || !$dateTo) {
            return response()->json(['ok' => false, 'message' => 'Invalid date range.'], 422);
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $openingDate = date('Y-m-d', strtotime($dateFrom . ' -1 day'));

        $sales = $this->salesSummary($dateFrom, $dateTo, $gilevel);
        $purchase = $this->purchaseSummary($dateFrom, $dateTo, $gilevel);
        $smith = $this->smithSummary($dateFrom, $dateTo, $dateTo, $gilevel);
        $refinery = $this->refinerySummary($dateFrom, $dateTo, $gilevel);
        $cash = $this->cashSummary($dateFrom, $dateTo, $gilevel);
        $balances = $this->balanceSummary($dateTo, $gilevel);
        $stock = [
            'opening' => $this->stockSnapshot($openingDate, $gilevel),
            'closing' => $this->stockSnapshot($dateTo, $gilevel),
        ];

        return response()->json([
            'ok' => true,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'overview' => [
                'sales_amount' => $sales['totals']['sales_amount'],
                'purchase_amount' => $purchase['totals']['purchase_amount'],
                'closing_cash' => $cash['closing_cash'],
                'customer_balance' => $balances['customer_balance'],
                'supplier_balance' => $balances['supplier_balance'],
                'advance_pending' => $balances['advance_pending'],
            ],
            'sales' => $sales,
            'purchase' => $purchase,
            'smith' => $smith,
            'refinery' => $refinery,
            'cash' => $cash,
            'balances' => $balances,
            'stock' => $stock,
        ]);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return null;
    }

    protected function hasTable(string $table): bool
    {
        return $this->tableCache[$table] ??= parent::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (!$this->hasTable($table)) {
            return false;
        }

        if (!isset($this->columnCache[$table])) {
            $this->columnCache[$table] = array_map('strtolower', $this->columnList($table));
        }

        return in_array(strtolower($column), $this->columnCache[$table], true);
    }

    private function levelColumn(string $base, int $gilevel, string $table): string
    {
        $secondary = $base . 'b';
        if ($gilevel > 1 && $this->hasColumn($table, $secondary)) {
            return $secondary;
        }

        return $base;
    }

    private function ogIsOrnament(): bool
    {
        if (!$this->hasTable('items')) {
            return false;
        }

        return strtoupper((string) (DB::table('items')->where('code', 'OG')->value('ornament') ?? 'N')) === 'Y';
    }

    private function salesSummary(string $dateFrom, string $dateTo, int $gilevel): array
    {
        $summary = [
            'gold_sales_wgt' => 0.0,
            'gold_sales_ret_wgt' => 0.0,
            'silver_sales_wgt' => 0.0,
            'platinum_sales_wgt' => 0.0,
            'diamond_sales_ct' => 0.0,
            'watch_sales_qty' => 0.0,
            'sales_amount' => 0.0,
            'sales_return_amount' => 0.0,
            'discount_amount' => 0.0,
            'exchange_amount' => 0.0,
            'received_amount' => 0.0,
            'tax_amount' => 0.0,
        ];

        if ($this->hasTable('salesd') && $this->hasTable('salesm') && $this->hasTable('items')) {
            $rows = DB::table('salesd as d')
                ->join('salesm as m', 'm.slno', '=', 'd.slno')
                ->join('items as i', 'i.code', '=', 'd.code')
                ->whereBetween('m.tdate', [$dateFrom, $dateTo])
                ->where('m.control', '<=', $gilevel)
                ->selectRaw("
                    SUM(CASE WHEN i.itype = 'G' THEN COALESCE(d.weight,0) ELSE 0 END) as gold_sales_wgt,
                    SUM(CASE WHEN i.itype = 'S' THEN COALESCE(d.weight,0) ELSE 0 END) as silver_sales_wgt,
                    SUM(CASE WHEN i.itype = 'P' THEN COALESCE(d.weight,0) ELSE 0 END) as platinum_sales_wgt,
                    SUM(CASE WHEN i.itype = 'D' THEN COALESCE(d.dmdwgt,0) ELSE 0 END) as diamond_sales_ct,
                    SUM(CASE WHEN COALESCE(i.dmdplt,'') = 'W' THEN COALESCE(d.qty,0) ELSE 0 END) as watch_sales_qty
                ")
                ->first();

            $summary['gold_sales_wgt'] = round((float) ($rows->gold_sales_wgt ?? 0), 3);
            $summary['silver_sales_wgt'] = round((float) ($rows->silver_sales_wgt ?? 0), 3);
            $summary['platinum_sales_wgt'] = round((float) ($rows->platinum_sales_wgt ?? 0), 3);
            $summary['diamond_sales_ct'] = round((float) ($rows->diamond_sales_ct ?? 0), 3);
            $summary['watch_sales_qty'] = round((float) ($rows->watch_sales_qty ?? 0), 0);
        }

        if ($this->hasTable('salesrd') && $this->hasTable('salesrm') && $this->hasTable('items')) {
            $rows = DB::table('salesrd as d')
                ->join('salesrm as m', 'm.slno', '=', 'd.slno')
                ->join('items as i', 'i.code', '=', 'd.code')
                ->whereBetween('m.tdate', [$dateFrom, $dateTo])
                ->where('m.control', '<=', $gilevel)
                ->selectRaw("
                    SUM(CASE WHEN i.itype = 'G' THEN COALESCE(d.weight,0) ELSE 0 END) as gold_sales_ret_wgt
                ")
                ->first();

            $summary['gold_sales_ret_wgt'] = round((float) ($rows->gold_sales_ret_wgt ?? 0), 3);
        }

        if ($this->hasTable('salesm')) {
            $row = DB::table('salesm')
                ->whereBetween('tdate', [$dateFrom, $dateTo])
                ->where('control', '<=', $gilevel)
                ->selectRaw("
                    SUM(COALESCE(billamt,0) + COALESCE(round,0)) as sales_amount,
                    SUM(COALESCE(discount,0)) as discount_amount,
                    SUM(COALESCE(eamt,0)) as exchange_amount,
                    SUM(COALESCE(ramt,0)) as received_amount,
                    SUM(COALESCE(staxamt,0) + COALESCE(astamt,0) + COALESCE(tcsamt,0)) as tax_amount
                ")
                ->first();

            $summary['sales_amount'] = round((float) ($row->sales_amount ?? 0), 2);
            $summary['discount_amount'] = round((float) ($row->discount_amount ?? 0), 2);
            $summary['exchange_amount'] = round((float) ($row->exchange_amount ?? 0), 2);
            $summary['received_amount'] = round((float) ($row->received_amount ?? 0), 2);
            $summary['tax_amount'] = round((float) ($row->tax_amount ?? 0), 2);
        }

        if ($this->hasTable('salesrm')) {
            $row = DB::table('salesrm')
                ->whereBetween('tdate', [$dateFrom, $dateTo])
                ->where('control', '<=', $gilevel)
                ->selectRaw('SUM(COALESCE(billamt,0) - COALESCE(discount,0) + COALESCE(staxamt,0)) as total')
                ->first();

            $summary['sales_return_amount'] = round((float) ($row->total ?? 0), 2);
        }

        $summary['net_gold_sales_wgt'] = round($summary['gold_sales_wgt'] - $summary['gold_sales_ret_wgt'], 3);
        $summary['net_sales_amount'] = round($summary['sales_amount'] - $summary['sales_return_amount'], 2);

        return ['totals' => $summary];
    }

    private function purchaseSummary(string $dateFrom, string $dateTo, int $gilevel): array
    {
        $summary = [
            'og_purchase_wgt' => 0.0,
            'og_purchase_amt' => 0.0,
            'og_less_wgt' => 0.0,
            'gold_orn_purchase_wgt' => 0.0,
            'gold_orn_purchase_amt' => 0.0,
            'other_gold_purchase_wgt' => 0.0,
            'other_gold_purchase_amt' => 0.0,
            'silver_purchase_wgt' => 0.0,
            'silver_purchase_amt' => 0.0,
            'purchase_return_wgt' => 0.0,
            'purchase_amount' => 0.0,
            'purchase_paid_amount' => 0.0,
            'purchase_return_amount' => 0.0,
        ];

        if ($this->hasTable('purchased') && $this->hasTable('purchasem')) {
            $row = DB::table('purchased as d')
                ->join('purchasem as m', 'm.slno', '=', 'd.slno')
                ->whereBetween('m.tdate', [$dateFrom, $dateTo])
                ->where('m.control', '<=', $gilevel)
                ->where('d.code', 'OG')
                ->selectRaw('
                    SUM(COALESCE(d.weight,0)) as og_purchase_wgt,
                    SUM(COALESCE(d.amount,0)) as og_purchase_amt,
                    SUM(COALESCE(d.lesswgt,0)) as og_less_wgt
                ')
                ->first();

            $summary['og_purchase_wgt'] = round((float) ($row->og_purchase_wgt ?? 0), 3);
            $summary['og_purchase_amt'] = round((float) ($row->og_purchase_amt ?? 0), 2);
            $summary['og_less_wgt'] = round((float) ($row->og_less_wgt ?? 0), 3);
        }

        if ($this->hasTable('purchased') && $this->hasTable('purchasem') && $this->hasTable('items')) {
            $row = DB::table('purchased as d')
                ->join('purchasem as m', 'm.slno', '=', 'd.slno')
                ->join('items as i', 'i.code', '=', 'd.code')
                ->whereBetween('m.tdate', [$dateFrom, $dateTo])
                ->where('m.control', '<=', $gilevel)
                ->selectRaw("
                    SUM(CASE WHEN i.itype='G' AND d.code <> 'OG' AND COALESCE(i.ornament,'N')='Y' THEN COALESCE(d.weight,0) ELSE 0 END) as gold_orn_purchase_wgt,
                    SUM(CASE WHEN i.itype='G' AND d.code <> 'OG' AND COALESCE(i.ornament,'N')='Y' THEN COALESCE(d.amount,0) ELSE 0 END) as gold_orn_purchase_amt,
                    SUM(CASE WHEN i.itype='G' AND d.code <> 'OG' AND COALESCE(i.ornament,'N')<>'Y' THEN COALESCE(d.weight,0) ELSE 0 END) as other_gold_purchase_wgt,
                    SUM(CASE WHEN i.itype='G' AND d.code <> 'OG' AND COALESCE(i.ornament,'N')<>'Y' THEN COALESCE(d.amount,0) ELSE 0 END) as other_gold_purchase_amt,
                    SUM(CASE WHEN i.itype='S' THEN COALESCE(d.weight,0) ELSE 0 END) as silver_purchase_wgt,
                    SUM(CASE WHEN i.itype='S' THEN COALESCE(d.amount,0) ELSE 0 END) as silver_purchase_amt
                ")
                ->first();

            $summary['gold_orn_purchase_wgt'] = round((float) ($row->gold_orn_purchase_wgt ?? 0), 3);
            $summary['gold_orn_purchase_amt'] = round((float) ($row->gold_orn_purchase_amt ?? 0), 2);
            $summary['other_gold_purchase_wgt'] = round((float) ($row->other_gold_purchase_wgt ?? 0), 3);
            $summary['other_gold_purchase_amt'] = round((float) ($row->other_gold_purchase_amt ?? 0), 2);
            $summary['silver_purchase_wgt'] = round((float) ($row->silver_purchase_wgt ?? 0), 3);
            $summary['silver_purchase_amt'] = round((float) ($row->silver_purchase_amt ?? 0), 2);
        }

        if ($this->hasTable('purchaserd') && $this->hasTable('purchaserm') && $this->hasTable('items')) {
            $row = DB::table('purchaserd as d')
                ->join('purchaserm as m', 'm.slno', '=', 'd.slno')
                ->join('items as i', 'i.code', '=', 'd.code')
                ->whereBetween('m.tdate', [$dateFrom, $dateTo])
                ->where('m.control', '<=', $gilevel)
                ->where('i.itype', 'G')
                ->selectRaw('SUM(COALESCE(d.weight,0)) as purchase_return_wgt, SUM(COALESCE(d.amount,0)) as purchase_return_amount')
                ->first();

            $summary['purchase_return_wgt'] = round((float) ($row->purchase_return_wgt ?? 0), 3);
            $summary['purchase_return_amount'] = round((float) ($row->purchase_return_amount ?? 0), 2);
        }

        if ($this->hasTable('purchasem')) {
            $row = DB::table('purchasem')
                ->whereBetween('tdate', [$dateFrom, $dateTo])
                ->where('control', '<=', $gilevel)
                ->selectRaw('SUM(COALESCE(billamt,0) - COALESCE(eamt,0) + COALESCE(addamt,0)) as purchase_amount, SUM(COALESCE(pamt,0)) as purchase_paid_amount')
                ->first();

            $summary['purchase_amount'] = round((float) ($row->purchase_amount ?? 0), 2);
            $summary['purchase_paid_amount'] = round((float) ($row->purchase_paid_amount ?? 0), 2);
        }

        $summary['total_gold_purchase_wgt'] = round(
            $summary['og_purchase_wgt'] + $summary['gold_orn_purchase_wgt'] + $summary['other_gold_purchase_wgt'],
            3
        );

        return ['totals' => $summary];
    }

    private function smithSummary(string $dateFrom, string $dateTo, string $closingDate, int $gilevel): array
    {
        $summary = [
            'smith_issue_wgt' => 0.0,
            'smith_issue_stone' => 0.0,
            'smith_rcvd_wgt' => 0.0,
            'smith_rcvd_stone' => 0.0,
            'jewellery_issue_wgt' => 0.0,
            'jewellery_issue_stone' => 0.0,
            'jewellery_rcvd_wgt' => 0.0,
            'jewellery_rcvd_stone' => 0.0,
            'smith_to_receive' => 0.0,
            'smith_to_give' => 0.0,
            'jewellery_to_receive' => 0.0,
            'jewellery_to_give' => 0.0,
        ];

        if (!($this->hasTable('smithd') && $this->hasTable('smithm') && $this->hasTable('items') && $this->hasTable('clients'))) {
            return ['totals' => $summary];
        }

        $periodRows = DB::table('smithd as d')
            ->join('smithm as m', 'm.slno', '=', 'd.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->join('clients as c', 'c.code', '=', 'm.smithcode')
            ->whereBetween('m.tdate', [$dateFrom, $dateTo])
            ->where('m.control', '<=', $gilevel)
            ->where('i.itype', 'G')
            ->selectRaw("
                SUM(CASE WHEN c.ctype='G' AND d.givrec='G' THEN COALESCE(d.weight,0) ELSE 0 END) as smith_issue_wgt,
                SUM(CASE WHEN c.ctype='G' AND d.givrec='G' THEN COALESCE(d.stonewgt,0) ELSE 0 END) as smith_issue_stone,
                SUM(CASE WHEN c.ctype='G' AND d.givrec='R' THEN COALESCE(d.weight,0) ELSE 0 END) as smith_rcvd_wgt,
                SUM(CASE WHEN c.ctype='G' AND d.givrec='R' THEN COALESCE(d.stonewgt,0) ELSE 0 END) as smith_rcvd_stone,
                SUM(CASE WHEN c.ctype='J' AND d.givrec='G' THEN COALESCE(d.weight,0) ELSE 0 END) as jewellery_issue_wgt,
                SUM(CASE WHEN c.ctype='J' AND d.givrec='G' THEN COALESCE(d.stonewgt,0) ELSE 0 END) as jewellery_issue_stone,
                SUM(CASE WHEN c.ctype='J' AND d.givrec='R' THEN COALESCE(d.weight,0) ELSE 0 END) as jewellery_rcvd_wgt,
                SUM(CASE WHEN c.ctype='J' AND d.givrec='R' THEN COALESCE(d.stonewgt,0) ELSE 0 END) as jewellery_rcvd_stone
            ")
            ->first();

        foreach (array_keys($summary) as $key) {
            if (isset($periodRows->{$key})) {
                $summary[$key] = round((float) ($periodRows->{$key} ?? 0), 3);
            }
        }

        $clientsGs = $this->hasTable('clientsgs')
            ? DB::table('clientsgs')
                ->selectRaw('TRIM(code) as code, COALESCE(' . $this->levelColumn('opweight', $gilevel, 'clientsgs') . ',0) as opening_wgt, TRIM(COALESCE(ctype, "")) as ctype')
                ->whereIn('ctype', ['G', 'J'])
                ->get()
            : collect();

        foreach ($clientsGs as $row) {
            $code = trim((string) $row->code);
            $ctype = strtoupper(trim((string) $row->ctype));
            $opening = (float) ($row->opening_wgt ?? 0);

            $issued = (float) DB::table('smithd as d')
                ->join('smithm as m', 'm.slno', '=', 'd.slno')
                ->where('m.smithcode', $code)
                ->where('m.control', '<=', $gilevel)
                ->where('m.tdate', '<=', $closingDate)
                ->where('d.givrec', 'G')
                ->sum('d.netwgt');

            $received = (float) DB::table('smithd as d')
                ->join('smithm as m', 'm.slno', '=', 'd.slno')
                ->where('m.smithcode', $code)
                ->where('m.control', '<=', $gilevel)
                ->where('m.tdate', '<=', $closingDate)
                ->where('d.givrec', 'R')
                ->sum('d.netwgt');

            $balance = round($opening - $issued + $received, 3);

            if ($ctype === 'G') {
                if ($balance < 0) {
                    $summary['smith_to_receive'] += abs($balance);
                } else {
                    $summary['smith_to_give'] += $balance;
                }
            } elseif ($ctype === 'J') {
                if ($balance < 0) {
                    $summary['jewellery_to_receive'] += abs($balance);
                } else {
                    $summary['jewellery_to_give'] += $balance;
                }
            }
        }

        foreach (['smith_to_receive', 'smith_to_give', 'jewellery_to_receive', 'jewellery_to_give'] as $key) {
            $summary[$key] = round($summary[$key], 3);
        }

        return ['totals' => $summary];
    }

    private function refinerySummary(string $dateFrom, string $dateTo, int $gilevel): array
    {
        $summary = [
            'issued_wgt' => 0.0,
            'issued_stone' => 0.0,
            'received_wgt' => 0.0,
            'test_pcs' => 0.0,
            'balance_wgt' => 0.0,
            'gold_advance_wgt' => 0.0,
            'gold_advance_rcpt_wgt' => 0.0,
        ];

        if ($this->hasTable('refineryd') && $this->hasTable('refinerym')) {
            $row = DB::table('refineryd as d')
                ->join('refinerym as m', 'm.slno', '=', 'd.slno')
                ->whereBetween('m.tdate', [$dateFrom, $dateTo])
                ->where('m.control', '<=', $gilevel)
                ->selectRaw("
                    SUM(CASE WHEN COALESCE(d.rcvdwgt,0)=0 THEN COALESCE(d.issuedwgt,0) ELSE 0 END) as issued_wgt,
                    SUM(CASE WHEN COALESCE(d.rcvdwgt,0)=0 THEN COALESCE(d.issuedstwgt,0) ELSE 0 END) as issued_stone,
                    SUM(CASE WHEN COALESCE(d.issuedwgt,0)=0 THEN COALESCE(d.rcvdwgt,0) ELSE 0 END) as received_wgt,
                    SUM(COALESCE(d.testpcs,0)) as test_pcs
                ")
                ->first();

            $summary['issued_wgt'] = round((float) ($row->issued_wgt ?? 0), 3);
            $summary['issued_stone'] = round((float) ($row->issued_stone ?? 0), 3);
            $summary['received_wgt'] = round((float) ($row->received_wgt ?? 0), 3);
            $summary['test_pcs'] = round((float) ($row->test_pcs ?? 0), 3);

            $balRow = DB::table('refineryd as d')
                ->join('refinerym as m', 'm.slno', '=', 'd.slno')
                ->where('m.tdate', '<=', $dateTo)
                ->where('m.control', '<=', $gilevel)
                ->where('m.status', 1)
                ->selectRaw('
                    SUM(COALESCE(d.issuedwgt,0) - COALESCE(d.issuedstwgt,0)) as issued_less_stone,
                    SUM(CASE WHEN COALESCE(d.oissuedwgt,0)=0 THEN COALESCE(d.testpcs,0) + COALESCE(d.mudless,0) ELSE 0 END) as less_part
                ')
                ->first();

            $summary['balance_wgt'] = round(
                (float) ($balRow->issued_less_stone ?? 0) - (float) ($balRow->less_part ?? 0),
                3
            );
        }

        if ($this->hasTable('orderm') && $this->hasTable('orderdga')) {
            $summary['gold_advance_wgt'] = round((float) DB::table('orderm as m')
                ->join('orderdga as d', 'd.slno', '=', 'm.slno')
                ->where('m.status', 1)
                ->where('m.tdate', '<=', $dateTo)
                ->where('m.control', '<=', $gilevel)
                ->sum('d.weight'), 3);

            if ($this->hasTable('advafter')) {
                $summary['gold_advance_wgt'] = round(
                    $summary['gold_advance_wgt'] + (float) DB::table('orderm as m')
                        ->join('advafter as a', 'a.ordno', '=', 'm.ordno')
                        ->join('orderdga as d', 'd.slno', '=', 'a.slno')
                        ->where('m.status', 1)
                        ->where('a.tdate', '<=', $dateTo)
                        ->where('a.control', '<=', $gilevel)
                        ->sum('d.weight'),
                    3
                );
            }

            $summary['gold_advance_rcpt_wgt'] = round((float) DB::table('orderdga')
                ->whereBetween('tdate', [$dateFrom, $dateTo])
                ->where('control', '<=', $gilevel)
                ->sum('weight'), 3);
        }

        return ['totals' => $summary];
    }

    private function cashSummary(string $dateFrom, string $dateTo, int $gilevel): array
    {
        $opCol = $this->levelColumn('opbal', $gilevel, 'accountm');
        $openingBase = $this->hasTable('accountm') ? (float) (DB::table('accountm')->where('accode', 'CASH')->value($opCol) ?? 0) : 0;
        $lockerBase = $this->hasTable('accountm') ? (float) (DB::table('accountm')->where('accode', 'CINL')->value($opCol) ?? 0) : 0;

        $beforeFrom = 0.0;
        $uptoTo = 0.0;
        $period = 0.0;
        $lockerTo = 0.0;

        if ($this->hasTable('daybook')) {
            $beforeFrom = (float) DB::table('daybook')
                ->where('accode', 'CASH')
                ->where('control', '<=', $gilevel)
                ->where('tdate', '<', $dateFrom)
                ->sum('amount');

            $uptoTo = (float) DB::table('daybook')
                ->where('accode', 'CASH')
                ->where('control', '<=', $gilevel)
                ->where('tdate', '<=', $dateTo)
                ->sum('amount');

            $period = (float) DB::table('daybook')
                ->where('accode', 'CASH')
                ->where('control', '<=', $gilevel)
                ->whereBetween('tdate', [$dateFrom, $dateTo])
                ->sum('amount');

            $lockerTo = (float) DB::table('daybook')
                ->where('accode', 'CINL')
                ->where('control', '<=', $gilevel)
                ->where('tdate', '<=', $dateTo)
                ->sum('amount');
        }

        return [
            'opening_cash' => round(-($openingBase + $beforeFrom), 2),
            'period_cash' => round(-$period, 2),
            'closing_cash' => round(-($openingBase + $uptoTo), 2),
            'cash_in_locker' => round(-($lockerBase + $lockerTo), 2),
        ];
    }

    private function balanceSummary(string $dateTo, int $gilevel): array
    {
        $summary = [
            'customer_balance' => 0.0,
            'supplier_balance' => 0.0,
            'kuri_party_balance' => 0.0,
            'advance_pending' => 0.0,
        ];

        if ($this->hasTable('accountm')) {
            $opCol = $this->levelColumn('opbal', $gilevel, 'accountm');
            $accountRows = DB::table('accountm')
                ->whereIn('actype2', ['C', 'S'])
                ->selectRaw("TRIM(accode) as accode, TRIM(actype2) as actype2, COALESCE($opCol,0) as opbal")
                ->get();

            $tranMap = $this->hasTable('daybook')
                ? DB::table('daybook')
                    ->selectRaw('TRIM(accode) as accode, SUM(COALESCE(amount,0)) as tran_amount')
                    ->where('control', '<=', $gilevel)
                    ->where('tdate', '<=', $dateTo)
                    ->groupBy('accode')
                    ->get()
                    ->keyBy(fn ($row) => strtoupper(trim((string) $row->accode)))
                : collect();

            $kuriCodes = $this->hasTable('clients_kuridet')
                ? DB::table('clients_kuridet')->pluck('code')->map(fn ($v) => strtoupper(trim((string) $v)))->all()
                : [];

            foreach ($accountRows as $row) {
                $code = strtoupper(trim((string) $row->accode));
                $net = (float) ($row->opbal ?? 0) + (float) ($tranMap[$code]->tran_amount ?? 0);

                if ($row->actype2 === 'C' && $net < 0) {
                    $summary['customer_balance'] += abs($net);
                }
                if ($row->actype2 === 'S' && $net > 0) {
                    $summary['supplier_balance'] += $net;
                }
                if (in_array($code, $kuriCodes, true)) {
                    $summary['kuri_party_balance'] += $net;
                }
            }

            $summary['customer_balance'] = round($summary['customer_balance'], 2);
            $summary['supplier_balance'] = round($summary['supplier_balance'], 2);
            $summary['kuri_party_balance'] = round(abs($summary['kuri_party_balance']), 2);
        }

        if ($this->hasTable('orderm')) {
            $advancePending = (float) (DB::table('orderm')
                ->where('status', 1)
                ->where('tdate', '<=', $dateTo)
                ->where('control', '<=', $gilevel)
                ->selectRaw('SUM(COALESCE(advance,0) + COALESCE(sretamt,0) + COALESCE(eamt,0) - COALESCE(refund,0)) as total')
                ->value('total') ?? 0);

            if ($this->hasTable('advafter')) {
                $advancePending += (float) DB::table('advafter as a')
                    ->join('orderm as m', 'm.ordno', '=', 'a.ordno')
                    ->where('m.status', 1)
                    ->where('m.tdate', '<=', $dateTo)
                    ->where('m.control', '<=', $gilevel)
                    ->sum('a.amount');
            }

            $summary['advance_pending'] = round($advancePending, 2);
        }

        return $summary;
    }

    private function stockSnapshot(string $date, int $gilevel): array
    {
        $stock = [
            'gold_ornaments' => [
                'weight' => $this->closingStockWeight($date, $gilevel, 'ORN'),
                'stone' => $this->closingStockStone($date, $gilevel, 'ORN'),
            ],
            'old_gold' => [
                'weight' => $this->closingStockWeight($date, $gilevel, 'OG'),
                'stone' => $this->closingStockStone($date, $gilevel, 'OG'),
            ],
            'other_gold' => [
                'weight' => $this->closingStockWeight($date, $gilevel, 'OTHER'),
                'stone' => $this->closingStockStone($date, $gilevel, 'OTHER'),
            ],
        ];

        $stock['totals'] = [
            'weight' => round($stock['gold_ornaments']['weight'] + $stock['old_gold']['weight'] + $stock['other_gold']['weight'], 3),
            'stone' => round($stock['gold_ornaments']['stone'] + $stock['old_gold']['stone'] + $stock['other_gold']['stone'], 3),
        ];

        return $stock;
    }

    private function closingStockWeight(string $date, int $gilevel, string $category): float
    {
        if ($category === 'OG' && $this->ogIsOrnament()) {
            return 0.0;
        }

        $openingColumn = $this->levelColumn('opweight', $gilevel, 'items');
        $total = $this->openingItemsValue($openingColumn, $category);

        $total -= $this->stockTxnSum('smithd as d', 'smithm as m', 'm.slno', 'd.slno', 'd.weight', $date, $gilevel, $category, fn ($q) => $q->where('d.givrec', 'G'));
        $total += $this->stockTxnSum('smithd as d', 'smithm as m', 'm.slno', 'd.slno', 'd.weight', $date, $gilevel, $category, fn ($q) => $q->where('d.givrec', 'R'));
        $total += $this->stockTxnSum('purchased as d', 'purchasem as m', 'm.slno', 'd.slno', 'd.weight', $date, $gilevel, $category);
        $total -= $this->stockTxnSum('purchaserd as d', 'purchaserm as m', 'm.slno', 'd.slno', 'd.weight', $date, $gilevel, $category);
        $total -= $this->stockTxnSum('repaird as d', 'repairm as m', 'm.slno', 'd.slno', 'd.weight', $date, $gilevel, $category, fn ($q) => $q->where('d.givrec', 'G'));
        $total += $this->stockTxnSum('repaird as d', 'repairm as m', 'm.slno', 'd.slno', 'd.weight', $date, $gilevel, $category, fn ($q) => $q->where('d.givrec', 'R'));
        $total -= $this->stockTxnSum('refineryd as d', 'refinerym as m', 'm.slno', 'd.slno', 'd.issuedwgt', $date, $gilevel, $category);
        $total += $this->stockTxnSum('refineryd as d', 'refinerym as m', 'm.slno', 'd.slno', 'COALESCE(NULLIF(d.ao,0), COALESCE(d.rcvdwgt,0) - COALESCE(d.bottlestk,0) - COALESCE(d.testpcs,0), COALESCE(d.rcvdwgt,0))', $date, $gilevel, $category);
        $total += $this->stockTxnSum('salesrd as d', 'salesrm as m', 'm.slno', 'd.slno', 'd.weight', $date, $gilevel, $category);
        $total += $this->stockTxnSum('orderdga as d', null, null, null, 'd.weight', $date, $gilevel, $category, null, 'd');
        $total -= $this->stockTxnSum('salesd as d', 'salesm as m', 'm.slno', 'd.slno', 'd.weight', $date, $gilevel, $category, function ($q) {
            $q->whereRaw("TRIM(COALESCE(d.jcode,'')) = ''");
        });
        $total -= $this->itemAdjSum($date, $gilevel, $category, 'fromwgt', 'fromcode');
        $total += $this->itemAdjSum($date, $gilevel, $category, 'towgt', 'tocode');

        return round($total, 3);
    }

    private function closingStockStone(string $date, int $gilevel, string $category): float
    {
        if ($category === 'OG' && $this->ogIsOrnament()) {
            return 0.0;
        }

        $openingColumn = $this->levelColumn('opstonewgt', $gilevel, 'items');
        $total = $this->openingItemsValue($openingColumn, $category);

        $total -= $this->stockTxnSum('smithd as d', 'smithm as m', 'm.slno', 'd.slno', 'd.stonewgt', $date, $gilevel, $category, fn ($q) => $q->where('d.givrec', 'G'));
        $total += $this->stockTxnSum('smithd as d', 'smithm as m', 'm.slno', 'd.slno', 'd.stonewgt', $date, $gilevel, $category, fn ($q) => $q->where('d.givrec', 'R'));
        $total += $this->stockTxnSum('purchased as d', 'purchasem as m', 'm.slno', 'd.slno', 'd.stwgt', $date, $gilevel, $category);
        $total -= $this->stockTxnSum('purchaserd as d', 'purchaserm as m', 'm.slno', 'd.slno', 'd.stwgt', $date, $gilevel, $category);
        $total -= $this->stockTxnSum('repaird as d', 'repairm as m', 'm.slno', 'd.slno', 'd.stonewgt', $date, $gilevel, $category, fn ($q) => $q->where('d.givrec', 'G'));
        $total += $this->stockTxnSum('repaird as d', 'repairm as m', 'm.slno', 'd.slno', 'd.stonewgt', $date, $gilevel, $category, fn ($q) => $q->where('d.givrec', 'R'));
        $total -= $this->stockTxnSum('refineryd as d', 'refinerym as m', 'm.slno', 'd.slno', 'd.issuedstwgt', $date, $gilevel, $category);
        $total += $this->stockTxnSum('salesrd as d', 'salesrm as m', 'm.slno', 'd.slno', 'd.stonewgt', $date, $gilevel, $category);
        $total += $this->stockTxnSum('orderdga as d', null, null, null, 'd.stonewgt', $date, $gilevel, $category, null, 'd');
        $total -= $this->stockTxnSum('salesd as d', 'salesm as m', 'm.slno', 'd.slno', 'd.stonewgt', $date, $gilevel, $category, function ($q) {
            $q->whereRaw("TRIM(COALESCE(d.jcode,'')) = ''");
        });
        $total -= $this->itemAdjSum($date, $gilevel, $category, 'fromstwgt', 'fromcode');
        $total += $this->itemAdjSum($date, $gilevel, $category, 'tostwgt', 'tocode');

        return round($total, 3);
    }

    private function openingItemsValue(string $column, string $category): float
    {
        if (!$this->hasTable('items') || !$this->hasColumn('items', $column)) {
            return 0.0;
        }

        $query = DB::table('items');
        if ($category === 'OG') {
            $query->where('code', 'OG');
        } elseif ($category === 'ORN') {
            $query->where('itype', 'G')->where('ornament', 'Y');
        } elseif ($category === 'OTHER') {
            $query->where('itype', 'G')->where('code', '<>', 'OG')->whereRaw("COALESCE(ornament,'N') <> 'Y'");
        }
        return (float) ($query->sum($column) ?? 0);
    }

    private function stockTxnSum(
        string $detailFrom,
        ?string $masterFrom,
        ?string $masterKey,
        ?string $detailKey,
        string $sumExpr,
        string $date,
        int $gilevel,
        string $category,
        ?callable $extra = null,
        string $detailAlias = 'd'
    ): float {
        [$detailTable] = explode(' as ', $detailFrom);
        if (!$this->hasTable(trim($detailTable))) {
            return 0.0;
        }

        $query = DB::table($detailFrom);

        if ($masterFrom) {
            [$masterTable] = explode(' as ', $masterFrom);
            if (!$this->hasTable(trim($masterTable))) {
                return 0.0;
            }
            $query->join($masterFrom, $masterKey, '=', $detailKey)
                ->where('m.control', '<=', $gilevel)
                ->where('m.tdate', '<=', $date);
        } else {
            $query->where($detailAlias . '.control', '<=', $gilevel)
                ->where($detailAlias . '.tdate', '<=', $date);
        }

        $this->applyCategoryFilter($query, $category, $detailAlias);

        if ($extra) {
            $extra($query);
        }

        $row = $query->selectRaw("SUM(COALESCE($sumExpr,0)) as total")->first();
        return (float) ($row->total ?? 0);
    }

    private function itemAdjSum(string $date, int $gilevel, string $category, string $column, string $codeField): float
    {
        if (!$this->hasTable('itemadj') || !$this->hasColumn('itemadj', $column) || !$this->hasColumn('itemadj', $codeField)) {
            return 0.0;
        }

        $query = DB::table('itemadj as d')
            ->where('d.control', '<=', $gilevel)
            ->where('d.tdate', '<=', $date);

        if ($category === 'OG') {
            $query->where("d.$codeField", 'OG');
        } else {
            if (!$this->hasTable('items')) {
                return 0.0;
            }
            $query->join('items as i', 'i.code', '=', "d.$codeField");
            if ($category === 'ORN') {
                $query->where('i.itype', 'G')->where('i.ornament', 'Y');
            } elseif ($category === 'OTHER') {
                $query->where('i.itype', 'G')->where('i.code', '<>', 'OG')->whereRaw("COALESCE(i.ornament,'N') <> 'Y'");
            }
        }

        return (float) ($query->sum("d.$column") ?? 0);
    }

    private function applyCategoryFilter(\Illuminate\Database\Query\Builder $query, string $category, string $alias): void
    {
        if ($category === 'OG') {
            $query->where("$alias.code", 'OG');
            return;
        }

        if (!$this->hasTable('items')) {
            $query->whereRaw('1 = 0');
            return;
        }

        $joined = collect($query->joins ?? [])->contains(function ($join) {
            return str_contains(strtolower((string) $join->table), 'items');
        });

        if (!$joined) {
            $query->join('items as i', 'i.code', '=', "$alias.code");
        }

        $query->where('i.itype', 'G');

        if ($category === 'ORN') {
            $query->where('i.ornament', 'Y');
        } elseif ($category === 'OTHER') {
            $query->where('i.code', '<>', 'OG')->whereRaw("COALESCE(i.ornament,'N') <> 'Y'");
        }
    }
}
