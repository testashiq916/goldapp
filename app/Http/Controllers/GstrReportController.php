<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class GstrReportController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $shopGstin = $this->generalValue('KGST');
        $shopName  = $this->generalValue('SHOPNM');
        $dateFrom  = $request->query('date1', date('Y-m-01'));
        $dateTo    = $request->query('date2', date('Y-m-d'));
        $type      = $request->query('type', 'gstr1'); // gstr1, gstr3b, hsn

        return view('reports.gstr-report', compact(
            'shopGstin', 'shopName', 'dateFrom', 'dateTo', 'type'
        ));
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $dateFrom = $this->normalizeDate((string) $request->query('date1', date('Y-m-01')));
        $dateTo   = $this->normalizeDate((string) $request->query('date2', date('Y-m-d')));
        $type     = (string) $request->query('type', 'gstr1');
        $gilevel  = max(1, (int) $request->session()->get('gilevel', 1));

        try {
            $data = match ($type) {
                'gstr3b' => $this->buildGstr3b($dateFrom, $dateTo, $gilevel),
                'hsn'    => $this->buildHsnSummary($dateFrom, $dateTo, $gilevel),
                default  => $this->buildGstr1($dateFrom, $dateTo, $gilevel),
            };
            return response()->json(['ok' => true, 'data' => $data]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    // ── GSTR-1 ────────────────────────────────────────────────────────────

    private function buildGstr1(string $dateFrom, string $dateTo, int $gilevel): array
    {
        $b2b  = $this->gstr1B2b($dateFrom, $dateTo, $gilevel);
        $b2c  = $this->gstr1B2c($dateFrom, $dateTo, $gilevel);
        $hsn  = $this->buildHsnSummary($dateFrom, $dateTo, $gilevel);
        $docs = $this->documentSummary($dateFrom, $dateTo, $gilevel);

        $totalTaxable = array_sum(array_column($b2b, 'taxable_value'))
                      + array_sum(array_column($b2c, 'taxable_value'));
        $totalTax     = array_sum(array_column($b2b, 'total_tax'))
                      + array_sum(array_column($b2c, 'total_tax'));

        return compact('b2b', 'b2c', 'hsn', 'docs', 'totalTaxable', 'totalTax');
    }

    private function gstr1B2b(string $dateFrom, string $dateTo, int $gilevel): array
    {
        if (!$this->hasTable('sales_bills')) {
            return [];
        }

        $rows = DB::table('sales_bills as s')
            ->whereBetween('s.bill_date', [$dateFrom, $dateTo])
            ->where('s.control', '<=', $gilevel)
            ->whereNotNull('s.gstin')
            ->where('s.gstin', '<>', '')
            ->selectRaw('
                TRIM(s.bill_no) as bill_no,
                s.bill_date,
                TRIM(COALESCE(s.cust_name,"")) as cust_name,
                TRIM(COALESCE(s.gstin,"")) as gstin,
                COALESCE(s.taxable_value,0) as taxable_value,
                COALESCE(s.cgst_amount,0) as cgst,
                COALESCE(s.sgst_amount,0) as sgst,
                COALESCE(s.igst_amount,0) as igst,
                COALESCE(s.cgst_amount,0)+COALESCE(s.sgst_amount,0)+COALESCE(s.igst_amount,0) as total_tax,
                COALESCE(s.net_amount,s.grand_total,0) as invoice_value,
                COALESCE(s.place_of_supply,"") as pos
            ')
            ->orderBy('s.bill_date')
            ->get();

        return $rows->map(fn($r) => (array) $r)->all();
    }

    private function gstr1B2c(string $dateFrom, string $dateTo, int $gilevel): array
    {
        if (!$this->hasTable('sales_bills')) {
            return [];
        }

        $rows = DB::table('sales_bills as s')
            ->whereBetween('s.bill_date', [$dateFrom, $dateTo])
            ->where('s.control', '<=', $gilevel)
            ->where(function ($q) {
                $q->whereNull('s.gstin')->orWhere('s.gstin', '');
            })
            ->selectRaw('
                TRIM(s.bill_no) as bill_no,
                s.bill_date,
                TRIM(COALESCE(s.cust_name,"")) as cust_name,
                COALESCE(s.taxable_value,0) as taxable_value,
                COALESCE(s.cgst_amount,0) as cgst,
                COALESCE(s.sgst_amount,0) as sgst,
                COALESCE(s.igst_amount,0) as igst,
                COALESCE(s.cgst_amount,0)+COALESCE(s.sgst_amount,0)+COALESCE(s.igst_amount,0) as total_tax,
                COALESCE(s.net_amount,s.grand_total,0) as invoice_value
            ')
            ->orderBy('s.bill_date')
            ->get();

        return $rows->map(fn($r) => (array) $r)->all();
    }

    private function documentSummary(string $dateFrom, string $dateTo, int $gilevel): array
    {
        $invoiceCount = 0;
        $cancelCount  = 0;

        if ($this->hasTable('sales_bills')) {
            $invoiceCount = DB::table('sales_bills')
                ->whereBetween('bill_date', [$dateFrom, $dateTo])
                ->where('control', '<=', $gilevel)
                ->count();
        }

        return [
            'invoices' => $invoiceCount,
            'cancelled' => $cancelCount,
        ];
    }

    // ── GSTR-3B ───────────────────────────────────────────────────────────

    private function buildGstr3b(string $dateFrom, string $dateTo, int $gilevel): array
    {
        $outward  = $this->gstr3bOutward($dateFrom, $dateTo, $gilevel);
        $inward   = $this->gstr3bInward($dateFrom, $dateTo, $gilevel);
        $itcAvail = $this->gstr3bItc($dateFrom, $dateTo, $gilevel);

        $netPayable = max(0,
            ($outward['cgst'] + $outward['sgst'] + $outward['igst'])
            - ($itcAvail['cgst'] + $itcAvail['sgst'] + $itcAvail['igst'])
        );

        return compact('outward', 'inward', 'itcAvail', 'netPayable');
    }

    private function gstr3bOutward(string $dateFrom, string $dateTo, int $gilevel): array
    {
        $row = ['taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0, 'total_tax' => 0, 'invoice_value' => 0];

        if (!$this->hasTable('sales_bills')) {
            return $row;
        }

        $result = DB::table('sales_bills')
            ->whereBetween('bill_date', [$dateFrom, $dateTo])
            ->where('control', '<=', $gilevel)
            ->selectRaw('
                SUM(COALESCE(taxable_value,0)) as taxable,
                SUM(COALESCE(cgst_amount,0)) as cgst,
                SUM(COALESCE(sgst_amount,0)) as sgst,
                SUM(COALESCE(igst_amount,0)) as igst,
                SUM(COALESCE(cgst_amount,0)+COALESCE(sgst_amount,0)+COALESCE(igst_amount,0)) as total_tax,
                SUM(COALESCE(net_amount,grand_total,0)) as invoice_value
            ')
            ->first();

        if ($result) {
            $row['taxable']       = (float) ($result->taxable ?? 0);
            $row['cgst']          = (float) ($result->cgst ?? 0);
            $row['sgst']          = (float) ($result->sgst ?? 0);
            $row['igst']          = (float) ($result->igst ?? 0);
            $row['total_tax']     = (float) ($result->total_tax ?? 0);
            $row['invoice_value'] = (float) ($result->invoice_value ?? 0);
        }

        return $row;
    }

    private function gstr3bInward(string $dateFrom, string $dateTo, int $gilevel): array
    {
        $row = ['taxable' => 0, 'cgst' => 0, 'sgst' => 0, 'igst' => 0];

        if ($this->hasTable('purchasem')) {
            $result = DB::table('purchasem')
                ->whereBetween('tdate', [$dateFrom, $dateTo])
                ->where('control', '<=', $gilevel)
                ->selectRaw('
                    SUM(COALESCE(taxable_value,netamt,0)) as taxable,
                    SUM(COALESCE(cgst,0)) as cgst,
                    SUM(COALESCE(sgst,0)) as sgst,
                    SUM(COALESCE(igst,0)) as igst
                ')
                ->first();

            if ($result) {
                $row['taxable'] = (float) ($result->taxable ?? 0);
                $row['cgst']    = (float) ($result->cgst ?? 0);
                $row['sgst']    = (float) ($result->sgst ?? 0);
                $row['igst']    = (float) ($result->igst ?? 0);
            }
        }

        return $row;
    }

    private function gstr3bItc(string $dateFrom, string $dateTo, int $gilevel): array
    {
        // ITC from purchase — simplified
        return $this->gstr3bInward($dateFrom, $dateTo, $gilevel);
    }

    // ── HSN Summary ───────────────────────────────────────────────────────

    private function buildHsnSummary(string $dateFrom, string $dateTo, int $gilevel): array
    {
        if (!$this->hasTable('salesd')) {
            return [];
        }

        $rows = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->whereBetween('m.tdate', [$dateFrom, $dateTo])
            ->where('m.control', '<=', $gilevel)
            ->selectRaw('
                TRIM(COALESCE(d.hsncode,"9018")) as hsn_code,
                TRIM(COALESCE(d.icode,"")) as item_code,
                COUNT(*) as qty,
                SUM(COALESCE(d.wgt,0)) as total_weight,
                SUM(COALESCE(d.netamount,d.amount,0)) as taxable_value,
                SUM(COALESCE(d.cgst,0)) as cgst,
                SUM(COALESCE(d.sgst,0)) as sgst,
                SUM(COALESCE(d.igst,0)) as igst
            ')
            ->groupBy(DB::raw('TRIM(COALESCE(d.hsncode,"9018"))'), DB::raw('TRIM(COALESCE(d.icode,""))'))
            ->orderBy('hsn_code')
            ->get();

        return $rows->map(fn($r) => (array) $r)->all();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        return date('Y-m-d');
    }

    private function generalValue(string $code): string
    {
        try {
            return trim((string) (DB::table('generals')->where('code', $code)->value('cvalue') ?? ''));
        } catch (Throwable) {
            return '';
        }
    }
}
