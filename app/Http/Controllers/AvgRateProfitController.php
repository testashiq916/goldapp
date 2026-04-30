<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AvgRateProfitController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $dateFrom = $this->normalizeDate($request->query('date1', '')) ?: date('Y-m-01');
        $dateTo   = $this->normalizeDate($request->query('date2', '')) ?: date('Y-m-d');
        $show     = $request->boolean('show');
        $rlevel   = (int) $request->session()->get('gilevel', 1) ?: 1;

        $avgRate        = 0;
        $goldPurchased  = ['total_weight' => 0, 'total_amount' => 0];
        $goldSold       = ['total_weight' => 0, 'total_amount' => 0];
        $smithData      = ['issued_weight' => 0, 'received_weight' => 0, 'wastage' => 0];
        $silverData     = ['weight' => 0, 'rate' => 0, 'amount' => 0];
        $goldStockWgt   = 0;
        $goldStockAmt   = 0;
        $salesPeriods   = [];
        $purchaseTypes  = [];
        $profitPeriods  = [];
        $goldReceiveAmt = 0;
        $goldGiveAmt    = 0;
        $tmStockWgt     = 0;
        $tmStockAmt     = 0;
        $cashReceipts   = [];   // RECEIVE rows from daybook
        $cashPayments   = [];   // GIVE rows from daybook

        if ($show) {
            $goldPurchased  = $this->getGoldPurchased($dateFrom, $dateTo, $rlevel);
            $goldSold       = $this->getGoldSold($dateFrom, $dateTo, $rlevel);
            $smithData      = $this->getSmithData($dateFrom, $dateTo, $rlevel);
            $silverData     = $this->getSilverData($dateFrom, $dateTo, $rlevel);

            // Average rate: weighted average from purchases, fallback to generald
            $avgRate = $goldPurchased['total_weight'] > 0
                ? round($goldPurchased['total_amount'] / $goldPurchased['total_weight'], 2)
                : $this->getCurrentGoldRate();

            // Gold stock (closing) valued at avg rate
            $goldStockWgt = $this->getGoldStockWeight($dateTo);
            $goldStockAmt = round($goldStockWgt * $avgRate, 2);

            // Smith stock (items pending with smith)
            $tmStockWgt = max(0, $smithData['issued_weight'] - $smithData['received_weight']);
            $tmStockAmt = round($tmStockWgt * $avgRate, 2);

            $goldReceiveAmt = round($goldPurchased['total_weight'] * $avgRate, 2);
            $goldGiveAmt    = round($goldSold['total_weight'] * $avgRate, 2);

            // Cash receipts and payments from daybook
            [$cashReceipts, $cashPayments] = $this->getCashFlows($dateFrom, $dateTo, $rlevel);

            $salesPeriods  = $this->getSalesByPeriod($dateFrom, $dateTo, $rlevel);
            $purchaseTypes = $this->getPurchaseByType($dateFrom, $dateTo, $rlevel);
            $profitPeriods = $this->getProfitByPeriod($dateFrom, $dateTo, $rlevel, $avgRate);
        }

        return view('reports.avg-rate-profit', compact(
            'dateFrom', 'dateTo', 'show',
            'avgRate', 'goldPurchased', 'goldSold', 'smithData', 'silverData',
            'goldStockWgt', 'goldStockAmt',
            'salesPeriods', 'purchaseTypes', 'profitPeriods',
            'goldReceiveAmt', 'goldGiveAmt', 'tmStockWgt', 'tmStockAmt',
            'cashReceipts', 'cashPayments'
        ));
    }

    /* ── Gold Stock (closing weight from itemsstk) ─────────── */
    private function getGoldStockWeight(string $dateTo): float
    {
        // Try itemsstk first (stock ledger)
        if ($this->hasTable('itemsstk')) {
            $cols = array_map('strtolower', $this->columnList('itemsstk'));
            $hasClwgt = in_array('clwgt', $cols);
            $hasTdate = in_array('tdate', $cols);
            if ($hasClwgt && $hasTdate) {
                // Latest closing weight per item up to dateTo
                $r = DB::select("
                    SELECT COALESCE(SUM(s.clwgt), 0) AS total_wgt
                    FROM itemsstk s
                    INNER JOIN (
                        SELECT code, MAX(tdate) AS max_date
                        FROM itemsstk
                        WHERE tdate <= ?
                        GROUP BY code
                    ) lx ON s.code = lx.code AND s.tdate = lx.max_date
                ", [$dateTo]);
                return round((float)(($r[0] ?? null)->total_wgt ?? 0), 3);
            }
        }

        // Fallback: calculate from sales and purchases
        if (!$this->hasTable('purchasem') || !$this->hasTable('salesm')) return 0;

        $cols    = array_map('strtolower', $this->columnList('purchased'));
        $lessWgt = in_array('lesswgt', $cols) ? 'COALESCE(pd.lesswgt,0)' : '0';

        $purchased = DB::select("
            SELECT COALESCE(SUM(pd.weight - {$lessWgt}), 0) AS wgt
            FROM purchased pd JOIN purchasem pm ON pd.slno = pm.slno
            WHERE pm.tdate <= ?
        ", [$dateTo]);
        $pWgt = (float)(($purchased[0] ?? null)->wgt ?? 0);

        $sold = DB::select("
            SELECT COALESCE(SUM(sd.weight), 0) AS wgt
            FROM salesd sd JOIN salesm sm ON sd.slno = sm.slno
            WHERE sm.tdate <= ?
        ", [$dateTo]);
        $sWgt = (float)(($sold[0] ?? null)->wgt ?? 0);

        return round(max(0, $pWgt - $sWgt), 3);
    }

    /* ── Cash flows from daybook ───────────────────────────── */
    private function getCashFlows(string $d1, string $d2, int $rl): array
    {
        if (!$this->hasTable('daybook')) return [[], []];

        $hasOpaccode = Schema::hasColumn('daybook', 'opaccode');
        $hasAccountm = $this->hasTable('accountm');

        // Group daybook by the "other" account and sum amounts
        // Positive amount = receipt (RECEIVE), Negative = payment (GIVE)
        if ($hasOpaccode && $hasAccountm) {
            $rows = DB::select("
                SELECT
                    TRIM(COALESCE(am.name, db.opaccode)) AS acname,
                    TRIM(db.opaccode)                    AS accode,
                    SUM(db.amount)                       AS total
                FROM daybook db
                LEFT JOIN accountm am ON TRIM(am.accode) = TRIM(db.opaccode)
                WHERE db.tdate BETWEEN ? AND ?
                  AND db.control <= ?
                  AND TRIM(db.accode) = 'CASH'
                GROUP BY TRIM(db.opaccode), am.name
                HAVING SUM(db.amount) <> 0
                ORDER BY SUM(db.amount) DESC
            ", [$d1, $d2, $rl]);
        } else {
            // Minimal: just group by opaccode
            $rows = DB::select("
                SELECT
                    TRIM(COALESCE(db.opaccode,'')) AS acname,
                    TRIM(COALESCE(db.opaccode,'')) AS accode,
                    SUM(db.amount)                 AS total
                FROM daybook db
                WHERE db.tdate BETWEEN ? AND ?
                  AND db.control <= ?
                  AND TRIM(db.accode) = 'CASH'
                GROUP BY TRIM(db.opaccode)
                HAVING SUM(db.amount) <> 0
                ORDER BY SUM(db.amount) DESC
            ", [$d1, $d2, $rl]);
        }

        $receipts = [];
        $payments = [];
        foreach ($rows as $r) {
            $total = (float)($r->total ?? 0);
            $name  = trim((string)($r->acname ?? ''));
            if ($total > 0) {
                $receipts[] = ['label' => $name, 'amount' => round($total, 2)];
            } elseif ($total < 0) {
                $payments[] = ['label' => $name, 'amount' => round(abs($total), 2)];
            }
        }

        return [$receipts, $payments];
    }

    /* ── Gold Purchased ────────────────────────────────────── */
    private function getGoldPurchased(string $d1, string $d2, int $rl): array
    {
        if (!$this->hasTable('purchasem') || !$this->hasTable('purchased')) {
            return ['total_weight' => 0, 'total_amount' => 0];
        }
        $cols    = array_map('strtolower', $this->columnList('purchased'));
        $lessWgt = in_array('lesswgt', $cols) ? 'COALESCE(pd.lesswgt,0)' : '0';

        $r = DB::select("
            SELECT
                COALESCE(SUM(pd.weight - {$lessWgt}), 0) AS total_weight,
                COALESCE(SUM(pd.amount), 0)               AS total_amount
            FROM purchased pd
            JOIN purchasem pm ON pd.slno = pm.slno
            WHERE pm.tdate BETWEEN ? AND ?
              AND pm.control <= ?
        ", [$d1, $d2, $rl]);

        $row = $r[0] ?? null;
        return [
            'total_weight' => round((float)($row->total_weight ?? 0), 3),
            'total_amount' => round((float)($row->total_amount ?? 0), 2),
        ];
    }

    /* ── Gold Sold ─────────────────────────────────────────── */
    private function getGoldSold(string $d1, string $d2, int $rl): array
    {
        if (!$this->hasTable('salesm') || !$this->hasTable('salesd')) {
            return ['total_weight' => 0, 'total_amount' => 0];
        }
        $r = DB::select("
            SELECT
                COALESCE(SUM(sd.weight), 0) AS total_weight,
                COALESCE(SUM(sd.amount), 0) AS total_amount
            FROM salesd sd
            JOIN salesm sm ON sd.slno = sm.slno
            WHERE sm.tdate BETWEEN ? AND ?
              AND sm.control <= ?
        ", [$d1, $d2, $rl]);

        $row = $r[0] ?? null;
        return [
            'total_weight' => round((float)($row->total_weight ?? 0), 3),
            'total_amount' => round((float)($row->total_amount ?? 0), 2),
        ];
    }

    /* ── Smith Transactions ────────────────────────────────── */
    private function getSmithData(string $d1, string $d2, int $rl): array
    {
        if (!$this->hasTable('smithm') || !$this->hasTable('smithd')) {
            return ['issued_weight' => 0, 'received_weight' => 0, 'wastage' => 0];
        }
        $cols    = array_map('strtolower', $this->columnList('smithd'));
        $wastage = in_array('wastage', $cols) ? 'COALESCE(sd.wastage,0)' : '0';

        $r = DB::select("
            SELECT
                COALESCE(SUM(CASE WHEN sd.givrec='G' THEN sd.weight ELSE 0 END),0) AS issued_weight,
                COALESCE(SUM(CASE WHEN sd.givrec='R' THEN sd.weight ELSE 0 END),0) AS received_weight,
                COALESCE(SUM(CASE WHEN sd.givrec='R' THEN {$wastage} ELSE 0 END),0) AS wastage
            FROM smithd sd
            JOIN smithm sm ON sd.slno = sm.slno
            WHERE sm.tdate BETWEEN ? AND ?
              AND sm.control <= ?
        ", [$d1, $d2, $rl]);

        $row = $r[0] ?? null;
        return [
            'issued_weight'   => round((float)($row->issued_weight ?? 0), 3),
            'received_weight' => round((float)($row->received_weight ?? 0), 3),
            'wastage'         => round((float)($row->wastage ?? 0), 3),
        ];
    }

    /* ── Silver Purchased ──────────────────────────────────── */
    private function getSilverData(string $d1, string $d2, int $rl): array
    {
        if (!$this->hasTable('purchasem') || !$this->hasTable('purchased')) {
            return ['weight' => 0, 'rate' => 0, 'amount' => 0];
        }
        $cols      = array_map('strtolower', $this->columnList('purchased'));
        $hasPurity = in_array('purity', $cols);
        $lessWgt   = in_array('lesswgt', $cols) ? 'COALESCE(pd.lesswgt,0)' : '0';

        if (!$hasPurity) return ['weight' => 0, 'rate' => 0, 'amount' => 0];

        $r = DB::select("
            SELECT
                COALESCE(SUM(pd.weight - {$lessWgt}), 0) AS weight,
                COALESCE(AVG(pd.rate), 0)                AS rate,
                COALESCE(SUM(pd.amount), 0)              AS amount
            FROM purchased pd
            JOIN purchasem pm ON pd.slno = pm.slno
            WHERE pm.tdate BETWEEN ? AND ?
              AND pm.control <= ?
              AND UPPER(TRIM(pd.purity)) LIKE '%SILVER%'
        ", [$d1, $d2, $rl]);

        $row = $r[0] ?? null;
        return [
            'weight' => round((float)($row->weight ?? 0), 3),
            'rate'   => round((float)($row->rate ?? 0), 2),
            'amount' => round((float)($row->amount ?? 0), 2),
        ];
    }

    /* ── Sales by Period (monthly × sale type) ─────────────── */
    private function getSalesByPeriod(string $d1, string $d2, int $rl): array
    {
        if (!$this->hasTable('salesm') || !$this->hasTable('salesd')) return [];

        $smCols      = array_map('strtolower', $this->columnList('salesm'));
        $hasBilltype = in_array('billtype', $smCols);
        $hasGrate    = in_array('grate', $smCols);
        $typeField   = $hasBilltype ? 'sm.billtype' : "''";
        $rateField   = $hasGrate    ? 'AVG(sm.grate)' : '0';

        $rows = DB::select("
            SELECT
                DATE_FORMAT(sm.tdate,'%d/%m/%Y') AS period_key,
                DATE_FORMAT(sm.tdate,'%m/%Y')    AS period,
                {$typeField}                      AS sale_type,
                COALESCE(SUM(sd.weight),0)        AS weight,
                COALESCE({$rateField},0)           AS rate,
                COALESCE(SUM(sd.amount),0)        AS amount
            FROM salesm sm
            JOIN salesd sd ON sd.slno = sm.slno
            WHERE sm.tdate BETWEEN ? AND ?
              AND sm.control <= ?
            GROUP BY period_key, period, sale_type
            ORDER BY sm.tdate, sale_type
        ", [$d1, $d2, $rl]);

        return array_map(fn($r) => [
            'period_key' => $r->period_key ?? '',
            'period'     => $r->period ?? '',
            'sale_type'  => trim((string)($r->sale_type ?? '')),
            'weight'     => round((float)($r->weight ?? 0), 3),
            'rate'       => round((float)($r->rate ?? 0), 2),
            'amount'     => round((float)($r->amount ?? 0), 3),
        ], $rows);
    }

    /* ── Purchase by Type ──────────────────────────────────── */
    private function getPurchaseByType(string $d1, string $d2, int $rl): array
    {
        if (!$this->hasTable('purchasem') || !$this->hasTable('purchased')) return [];

        $cols      = array_map('strtolower', $this->columnList('purchased'));
        $hasPurity = in_array('purity', $cols);
        $lessWgt   = in_array('lesswgt', $cols) ? 'COALESCE(pd.lesswgt,0)' : '0';
        $typeField = $hasPurity ? 'TRIM(pd.purity)' : "'GOLD'";

        $rows = DB::select("
            SELECT
                {$typeField}                              AS purchase_type,
                COALESCE(SUM(pd.weight - {$lessWgt}), 0) AS weight
            FROM purchased pd
            JOIN purchasem pm ON pd.slno = pm.slno
            WHERE pm.tdate BETWEEN ? AND ?
              AND pm.control <= ?
            GROUP BY purchase_type
            ORDER BY purchase_type
        ", [$d1, $d2, $rl]);

        return array_map(fn($r) => [
            'type'   => trim((string)($r->purchase_type ?? '')),
            'weight' => round((float)($r->weight ?? 0), 3),
        ], $rows);
    }

    /* ── Profit by Period (per sales date) ─────────────────── */
    private function getProfitByPeriod(string $d1, string $d2, int $rl, float $avgRate): array
    {
        if (!$this->hasTable('salesm') || !$this->hasTable('salesd')) return [];

        $smCols    = array_map('strtolower', $this->columnList('salesm'));
        $hasGrate  = in_array('grate', $smCols);
        $rateField = $hasGrate ? 'AVG(sm.grate)' : '0';

        $rows = DB::select("
            SELECT
                DATE_FORMAT(sm.tdate,'%d/%m/%Y') AS period_key,
                DATE_FORMAT(sm.tdate,'%m/%Y')    AS period,
                COALESCE(SUM(sd.weight),0)        AS sales_weight,
                COALESCE(SUM(sd.amount),0)        AS sales_amount,
                COALESCE({$rateField},0)           AS sale_rate
            FROM salesm sm
            JOIN salesd sd ON sd.slno = sm.slno
            WHERE sm.tdate BETWEEN ? AND ?
              AND sm.control <= ?
            GROUP BY period_key, period
            ORDER BY sm.tdate
        ", [$d1, $d2, $rl]);

        $profits = [];
        foreach ($rows as $r) {
            $salesWgt = (float)($r->sales_weight ?? 0);
            $salesAmt = (float)($r->sales_amount ?? 0);

            // Profit = sales_amount − (salesWgt × avgRate)
            // Profit weight = profit_amount / avgRate
            if ($avgRate > 0) {
                $costAmt   = $salesWgt * $avgRate;
                $profitAmt = $salesAmt - $costAmt;
                $profitWgt = $profitAmt / $avgRate;
            } else {
                $profitAmt = 0;
                $profitWgt = 0;
            }

            $profits[] = [
                'period_key' => $r->period_key ?? '',
                'period'     => $r->period ?? '',
                'profit_wgt' => round($profitWgt, 5),
                'rate'       => $avgRate,
                'profit_amt' => round($profitWgt * $avgRate, 1),
            ];
        }

        return $profits;
    }

    /* ── Current Gold Rate fallback ────────────────────────── */
    private function getCurrentGoldRate(): float
    {
        if (!$this->hasTable('generald')) return 0;
        $r = DB::table('generald')->where('code', 'GRATE')->first();
        return (float)($r->amt ?? 0);
    }

    /* ── Date normalizer ───────────────────────────────────── */
    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if (!$value) return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $value, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        return null;
    }
}
