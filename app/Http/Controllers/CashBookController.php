<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSecondarySeriesPrefix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CashBookController extends Controller
{
    use HandlesSecondarySeriesPrefix;

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $dateFrom = $this->normalizeDate((string) $request->query('date1', '')) ?: date('Y-m-d');
        $dateTo = $this->normalizeDate((string) $request->query('date2', '')) ?: date('Y-m-d');
        $incharge = strtoupper(trim((string) $request->query('ic', '')));
        $useIncharge = $request->boolean('use_ic', $incharge !== '');
        $breakDay = $request->boolean('breakday');
        $show = $request->boolean('show')
            || $request->query->has('date1')
            || $request->query->has('date2')
            || $request->query->has('ic')
            || $request->query->has('use_ic')
            || $request->query->has('breakday');

        $incharges = $this->incharges();
        $rows = [];
        $openingBalance = 0.0;
        $displayOpeningBalance = 0.0;
        $debitTotal = 0.0;
        $creditTotal = 0.0;
        $closingBalance = 0.0;
        $displayClosingBalance = 0.0;
        $statusText = '';

        if ($show) {
            $startedAt = microtime(true);
            $openingBalance = $this->openingBalance('CASH', $dateFrom, $gilevel, $useIncharge ? $incharge : '');
            // Legacy-consistent display opening:
            // previous day close = negative of raw CASH ledger balance.
            $displayOpeningBalance = -$openingBalance;
            $rows = $this->cashRows($dateFrom, $dateTo, $gilevel, $useIncharge ? $incharge : '');
            $running = $displayOpeningBalance;
            foreach ($rows as &$row) {
                $amount = $row['amount'];
                $row['debit'] = $amount < 0 ? abs($amount) : 0.0;
                $row['credit'] = $amount > 0 ? $amount : 0.0;
                $running = $running + $row['debit'] - $row['credit'];
                $row['running_balance'] = $running;
                $debitTotal += $row['debit'];
                $creditTotal += $row['credit'];
            }
            unset($row);
            // Match legacy cash-book display style:
            // show previous close / closing as positive display values.
            $displayClosingBalance = abs($running);
            $closingBalance = $running;
            $statusText = number_format(max(0, microtime(true) - $startedAt), 0) . ' Sec.';
        }

        return view('reports.cash-book', [
            'gilevel' => $gilevel,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'incharges' => $incharges,
            'incharge' => $incharge,
            'useIncharge' => $useIncharge,
            'breakDay' => $breakDay,
            'show' => $show,
            'rows' => $rows,
            'openingBalance' => $openingBalance,
            'displayOpeningBalance' => $displayOpeningBalance,
            'debitTotal' => $debitTotal,
            'creditTotal' => $creditTotal,
            'closingBalance' => $closingBalance,
            'displayClosingBalance' => $displayClosingBalance,
            'statusText' => $statusText,
        ]);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return null;
    }

    private function incharges(): array
    {
        if ($this->hasTable('incharge')) {
            return DB::table('incharge')
                ->selectRaw('TRIM(code) as code, TRIM(name) as name')
                ->orderBy('code')
                ->get()
                ->map(fn ($row) => ['code' => (string) $row->code, 'name' => (string) $row->name])
                ->all();
        }

        if ($this->hasTable('daybookpart') && Schema::hasColumn('daybookpart', 'ic')) {
            return DB::table('daybookpart')
                ->selectRaw('DISTINCT TRIM(ic) as code')
                ->whereRaw('TRIM(COALESCE(ic, "")) <> \'\'')
                ->orderBy('code')
                ->get()
                ->map(fn ($row) => ['code' => (string) $row->code, 'name' => (string) $row->code])
                ->all();
        }

        return [];
    }

    private function openingBalance(string $code, string $dateFrom, int $gilevel, string $incharge = ''): float
    {
        $opColumn = $gilevel === 1 ? 'opbal' : 'opbalb';
        $base = 0.0;
        if ($this->hasTable('accountm')) {
            $base = (float) (DB::table('accountm')->whereRaw('TRIM(accode)=?', [$code])->value($opColumn) ?? 0);
        }

        if (!$this->hasTable('daybook')) {
            return $base;
        }

        $query = DB::table('daybook')
            ->whereRaw('TRIM(daybook.accode)=?', [$code])
            ->where('daybook.control', '<=', $gilevel)
            ->where('daybook.tdate', '<', $dateFrom);

        // Keep opening balance aligned with the visible cash-book rows:
        // only count entries that participate in the cash-book row source.
        if ($this->hasTable('daybookpart')) {
            $query->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno');

            if ($incharge !== '' && Schema::hasColumn('daybookpart', 'ic')) {
                $query->whereRaw('TRIM(COALESCE(daybookpart.ic, "")) = ?', [$incharge]);
            }
        }

        return $base + (float) $query->sum('daybook.amount');
    }

    private function cashRows(string $dateFrom, string $dateTo, int $gilevel, string $incharge): array
    {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart')) {
            return [];
        }

        $query = DB::table('daybook')
            ->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno')
            ->leftJoin('accountm as oth', DB::raw('TRIM(daybook.opaccode)'), '=', DB::raw('TRIM(oth.accode)'))
            ->selectRaw('
                daybook.slno,
                daybook.tdate,
                daybook.amount,
                TRIM(COALESCE(daybookpart.vchno, "")) as vchno,
                TRIM(COALESCE(daybookpart.particular, "")) as particular,
                TRIM(COALESCE(oth.name, "")) as othacname,
                TRIM(COALESCE(daybookpart.ic, "")) as ic
            ')
            ->whereRaw('TRIM(daybook.accode)=?', ['CASH'])
            ->where('daybook.control', '<=', $gilevel)
            ->whereBetween('daybook.tdate', [$dateFrom, $dateTo])
            ->orderBy('daybook.tdate')
            ->orderBy('daybook.slno');

        if ($incharge !== '' && Schema::hasColumn('daybookpart', 'ic')) {
            $query->whereRaw('TRIM(COALESCE(daybookpart.ic, "")) = ?', [$incharge]);
        }

        $rawRows = $query->get();
        $sourceMap = $this->cashSourceMap($rawRows->pluck('slno')->map(fn ($v) => (int) $v)->all());

        return $rawRows->map(function ($row) use ($sourceMap) {
            $slno = (int) $row->slno;
            $amount = (float) $row->amount;
            $source = $sourceMap[$slno] ?? null;

            $vchno = trim((string) $row->vchno);
            $particular = trim((string) $row->particular);
            $othacname = trim((string) $row->othacname);

            if ($source !== null) {
                $vchno = $source['billno'] !== '' ? $source['billno'] : $vchno;
                $particular = $source['particular'];
                $othacname = $source['othacname'] !== '' ? $source['othacname'] : $othacname;
            }

            $othacname = $this->normalizeCashOtherAccountName($particular, $othacname);

            return [
                'slno' => $slno,
                'date' => (string) $row->tdate,
                'vchno' => $vchno,
                'particular' => $this->cashNarration($particular, $amount),
                'othacname' => $othacname,
                'ic' => (string) $row->ic,
                'amount' => $amount,
                'navigate_url' => $this->resolveCashDocumentUrl($slno, $vchno, $particular),
            ];
        })->all();
    }

    private function normalizeCashOtherAccountName(string $particular, string $othacname): string
    {
        $particular = trim($particular);
        $othacname = trim($othacname);
        $upper = strtoupper($particular);

        if (str_starts_with($upper, 'BY SALES (') || str_starts_with($upper, 'BY ORDER SALES (')) {
            return 'SALES A/C';
        }

        if (str_starts_with($upper, 'BY SALES RETURN (')) {
            return 'SALES RETURN A/C';
        }

        if (str_starts_with($upper, 'BY PURCHASE')) {
            return 'PURCHASE A/C';
        }

        if (str_starts_with($upper, 'BY ORDER - ')) {
            return 'ORDER A/C';
        }

        return $othacname;
    }

    private function cashSourceMap(array $slnos): array
    {
        $slnos = array_values(array_unique(array_filter(array_map('intval', $slnos), fn (int $v) => $v > 0)));
        if ($slnos === []) {
            return [];
        }

        $map = [];

        if ($this->hasTable('salesm')) {
            $rows = DB::table('salesm')
                ->whereIn('slno', $slnos)
                ->get(['slno', 'billno', 'custname']);

            foreach ($rows as $row) {
                $slno = (int) ($row->slno ?? 0);
                if ($slno <= 0) {
                    continue;
                }

                $billNo = trim((string) ($row->billno ?? ''));
                $custName = trim((string) ($row->custname ?? ''));
                $particular = 'By Sales';
                if ($billNo !== '') {
                    $particular .= ' (' . $billNo . ')';
                }
                if ($custName !== '') {
                    $particular .= ' To ' . $custName;
                }

                $map[$slno] = [
                    'billno' => $billNo,
                    'particular' => $particular,
                    'othacname' => 'SALES A/C',
                ];
            }
        }

        if ($this->hasTable('salesrm')) {
            $rows = DB::table('salesrm')
                ->whereIn('slno', $slnos)
                ->get(['slno', 'billno', 'custname']);

            foreach ($rows as $row) {
                $slno = (int) ($row->slno ?? 0);
                if ($slno <= 0) {
                    continue;
                }

                $billNo = trim((string) ($row->billno ?? ''));
                $custName = trim((string) ($row->custname ?? ''));
                $particular = 'By Sales Return';
                if ($billNo !== '') {
                    $particular .= ' (' . $billNo . ')';
                }
                if ($custName !== '') {
                    $particular .= ' From ' . $custName;
                }

                $map[$slno] = [
                    'billno' => $billNo,
                    'particular' => $particular,
                    'othacname' => 'SALES RETURN A/C',
                ];
            }
        }

        if ($this->hasTable('purchasem')) {
            $rows = DB::table('purchasem')
                ->whereIn('slno', $slnos)
                ->get(['slno', 'docno', 'name', 'pr', 'note']);

            foreach ($rows as $row) {
                $slno = (int) ($row->slno ?? 0);
                if ($slno <= 0 || isset($map[$slno])) {
                    continue;
                }

                $docNo = trim((string) ($row->docno ?? ''));
                $suppName = trim((string) ($row->name ?? ''));
                $pr = strtoupper(trim((string) ($row->pr ?? 'P')));
                $note = trim((string) ($row->note ?? ''));

                if ($note === 'Expense Voucher') {
                    $particular = 'By Expense Voucher';
                    if ($docNo !== '') {
                        $particular .= ' (' . $docNo . ')';
                    }

                    $map[$slno] = [
                        'billno' => $docNo,
                        'particular' => $particular,
                        'othacname' => 'EXPENSE A/C',
                    ];
                    continue;
                }

                if (!in_array($pr, ['P', 'E'], true)) {
                    continue;
                }

                $particular = 'By Purchase';
                if ($docNo !== '') {
                    $particular .= ' (' . $docNo . ')';
                }
                if ($suppName !== '') {
                    $particular .= ' From ' . $suppName;
                }

                $map[$slno] = [
                    'billno' => $docNo,
                    'particular' => $particular,
                    'othacname' => 'PURCHASE A/C',
                ];
            }
        }

        if ($this->hasTable('orderm')) {
            $rows = DB::table('orderm')
                ->whereIn('slno', $slnos)
                ->get(['slno', 'ordno', 'custname']);

            foreach ($rows as $row) {
                $slno = (int) ($row->slno ?? 0);
                if ($slno <= 0 || isset($map[$slno])) {
                    continue;
                }

                $orderNo = trim((string) ($row->ordno ?? ''));
                $custName = trim((string) ($row->custname ?? ''));
                if ($orderNo === '') {
                    continue;
                }

                $particular = 'By Order - ' . $orderNo;
                if ($custName !== '') {
                    $particular .= ' From ' . $custName;
                }

                $map[$slno] = [
                    'billno' => $orderNo,
                    'particular' => $particular,
                    'othacname' => $custName !== '' ? $custName : 'ORDER A/C',
                ];
            }
        }

        return $map;
    }

    private function resolveCashDocumentUrl(int $slno, string $vchno, string $particular = ''): string
    {
        if ($slno <= 0) {
            return '';
        }

        if ($this->hasTable('salesm')) {
            $sales = DB::table('salesm')
                ->where('slno', $slno)
                ->first(['billno']);
            $billNo = trim((string) ($sales->billno ?? ''));
            if ($billNo !== '') {
                return url('/sales-bill/edit?bill_no=' . urlencode($billNo));
            }

            $billNo = $this->extractBillNumberFromNarration($particular);
            if ($billNo !== '') {
                $sales = DB::table('salesm')
                    ->whereRaw('TRIM(billno) = ?', [$billNo])
                    ->first(['billno']);
                if ($sales) {
                    return url('/sales-bill/edit?bill_no=' . urlencode($billNo));
                }
            }
        }

        if ($this->hasTable('purchasem')) {
            $purchase = DB::table('purchasem')
                ->where('slno', $slno)
                ->first(['docno', 'pr', 'note']);
            $docNo = trim((string) ($purchase->docno ?? ''));
            $note = trim((string) ($purchase->note ?? ''));
            $pr = strtoupper(trim((string) ($purchase->pr ?? 'P')));

            if ($note === 'Expense Voucher') {
                return url('/accounts/expense-voucher-entry?' . http_build_query(['slno' => $slno]));
            }

            if ($docNo !== '' && in_array($pr, ['P', 'E'], true)) {
                return url('/purchase-bill/edit?' . http_build_query(['doc_no' => $docNo]));
            }
        }

        if ($this->hasTable('salesrm')) {
            $salesReturn = DB::table('salesrm')
                ->where('slno', $slno)
                ->first(['slno']);
            if ($salesReturn) {
                return url('/sales-return/edit?' . http_build_query(['slno' => $slno]));
            }
        }

        if ($this->hasTable('purchaserm')) {
            $purchaseReturn = DB::table('purchaserm')
                ->where('slno', $slno)
                ->first(['docno']);
            $docNo = trim((string) ($purchaseReturn->docno ?? ''));
            if ($docNo !== '') {
                return url('/purchase-return/edit?' . http_build_query(['doc_no' => $docNo]));
            }
        }

        if ($this->hasTable('orderm')) {
            $order = DB::table('orderm')
                ->where('slno', $slno)
                ->first(['ordno']);
            $orderNo = trim((string) ($order->ordno ?? ''));
            if ($orderNo !== '') {
                return url('/order-bill/edit?' . http_build_query(['doc' => $orderNo]));
            }
        }

        $voucherNo = strtoupper(trim($vchno));
        $receiptPrefixes = array_values(array_unique(array_filter([
            'VR',
            strtoupper(trim($this->secondaryPrefixFor('receipt'))),
        ])));
        $paymentPrefixes = array_values(array_unique(array_filter([
            'VP',
            strtoupper(trim($this->secondaryPrefixFor('payment'))),
        ])));

        foreach ($receiptPrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($voucherNo, $prefix)) {
                return url('/accounts/receipt?' . http_build_query([
                    'mode' => 'E',
                    'slno' => $slno,
                    'vchno' => trim($vchno),
                ]));
            }
        }

        foreach ($paymentPrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($voucherNo, $prefix)) {
                return url('/accounts/payment?' . http_build_query([
                    'mode' => 'E',
                    'slno' => $slno,
                ]));
            }
        }

        if (str_starts_with($voucherNo, 'JL')) {
            return url('/accounts/journal?' . http_build_query([
                'slno' => $slno,
                'vchno' => trim($vchno),
            ]));
        }

        return '';
    }

    private function extractBillNumberFromNarration(string $particular): string
    {
        $particular = trim($particular);
        if ($particular === '') {
            return '';
        }

        if (preg_match('/\(([A-Z]{1,4}\/[0-9]{2}-[0-9]{2}\/[0-9]+)\)/i', $particular, $matches)) {
            return strtoupper(trim((string) ($matches[1] ?? '')));
        }

        if (preg_match('/\(([A-Z]{1,4}\/[0-9]+)\)/i', $particular, $matches)) {
            return strtoupper(trim((string) ($matches[1] ?? '')));
        }

        return '';
    }

    private function cashNarration(string $particular, float $amount): string
    {
        $text = trim($particular);
        if ($text === '') {
            return $text;
        }

        $upper = strtoupper($text);
        if (str_starts_with($upper, 'BY SALES (') || str_starts_with($upper, 'BY ORDER SALES (')) {
            if ($amount < 0) {
                return $text . ' [Cash In]';
            }

            if ($amount > 0) {
                return $text . ' [Refund/Adjustment]';
            }
        }

        if (str_starts_with($upper, 'BY SALES RETURN (')) {
            if ($amount > 0) {
                return $text . ' [Cash Out]';
            }

            if ($amount < 0) {
                return $text . ' [Cash Back In]';
            }
        }

        return $text;
    }
}
