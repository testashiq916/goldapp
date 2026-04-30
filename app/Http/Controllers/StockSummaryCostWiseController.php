<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockSummaryCostWiseController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('stock-summary-cost-wise.index');
    }

    /**
     * Show — fetch all stock summary data for a date range
     */
    public function show(Request $request)
    {
        $date1 = $request->input('date1');
        $date2 = $request->input('date2');
        if (!$date1 || !$date2) {
            return response()->json(['ok' => false, 'message' => 'Date range required']);
        }

        $g = $this->gilevel;

        // ── Stock Transactions (period queries) ──
        $txn = $this->getTransactions($date1, $date2, $g);

        // ── Closing Stock (as on date2) ──
        $closing = $this->getClosingStock($date2, $g);

        // ── Smith To Get / To Give ──
        $smith = $this->getSmithBalance($date2, $g);

        return response()->json([
            'ok'      => true,
            'txn'     => $txn,
            'closing' => $closing,
            'smith'   => $smith,
        ]);
    }

    /**
     * Period transaction sums from salesd, purchased, smithd, refineryd, repaird
     */
    private function getTransactions(string $date1, string $date2, int $g): array
    {
        // Gold Sales — weight & cost amount (weight * cost)
        $gSales = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.weight * d.cost),0) as amt
            FROM salesd d
            JOIN salesm m ON m.slno = d.slno
            JOIN items i  ON i.code = d.code
            WHERE m.tdate >= ? AND m.tdate <= ? AND m.control <= ? AND i.itype = 'G'
        ", [$date1, $date2, $g]);

        // Silver Sales
        $sSales = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.weight * d.cost),0) as amt
            FROM salesd d
            JOIN salesm m ON m.slno = d.slno
            JOIN items i  ON i.code = d.code
            WHERE m.tdate >= ? AND m.tdate <= ? AND m.control <= ? AND i.itype = 'S'
        ", [$date1, $date2, $g]);

        // Gold Sales Return
        $gSalesR = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.weight * d.cost),0) as amt
            FROM salesrd d
            JOIN salesrm m ON m.slno = d.slno
            JOIN items i   ON i.code = d.code
            WHERE m.tdate >= ? AND m.tdate <= ? AND m.control <= ? AND i.itype = 'G'
        ", [$date1, $date2, $g]);

        // Silver Sales Return
        $sSalesR = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.weight * d.cost),0) as amt
            FROM salesrd d
            JOIN salesrm m ON m.slno = d.slno
            JOIN items i   ON i.code = d.code
            WHERE m.tdate >= ? AND m.tdate <= ? AND m.control <= ? AND i.itype = 'S'
        ", [$date1, $date2, $g]);

        // OG (Old Gold) Purchase
        $ogPurch = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.amount),0) as amt
            FROM purchased d
            JOIN purchasem m ON m.slno = d.slno
            WHERE d.code = 'OG' AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        // Other Gold Purchase (itype=G, code<>OG)
        $gPurch = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.amount),0) as amt
            FROM purchased d
            JOIN purchasem m ON m.slno = d.slno
            JOIN items i     ON i.code = d.code
            WHERE i.itype = 'G' AND d.code <> 'OG'
              AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        // Silver Purchase (itype=S, code<>OS)
        $sPurch = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.amount),0) as amt
            FROM purchased d
            JOIN purchasem m ON m.slno = d.slno
            JOIN items i     ON i.code = d.code
            WHERE i.itype = 'S' AND d.code <> 'OS'
              AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        // OS (Old Silver) Purchase
        $osPurch = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.amount),0) as amt
            FROM purchased d
            JOIN purchasem m ON m.slno = d.slno
            JOIN items i     ON i.code = d.code
            WHERE i.itype = 'S' AND d.code = 'OS'
              AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        // Smith Issued — Gold Ornaments (itype=G, ornament=Y, givrec=G)
        $smithIssuOrn = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.weight * d.cost),0) as amt
            FROM smithd d
            JOIN smithm m ON m.slno = d.slno
            JOIN items i  ON i.code = d.code
            WHERE i.itype = 'G' AND i.ornament = 'Y' AND d.givrec = 'G'
              AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        // Smith Issued — Other Gold (itype=G, ornament<>Y, givrec=G)
        $smithIssuOther = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.weight * d.cost),0) as amt
            FROM smithd d
            JOIN smithm m ON m.slno = d.slno
            JOIN items i  ON i.code = d.code
            WHERE i.itype = 'G' AND i.ornament <> 'Y' AND d.givrec = 'G'
              AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        // Smith Rcvd — Gold Ornaments
        $smithRcvdOrn = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.weight * d.cost),0) as amt
            FROM smithd d
            JOIN smithm m ON m.slno = d.slno
            JOIN items i  ON i.code = d.code
            WHERE i.itype = 'G' AND i.ornament = 'Y' AND d.givrec = 'R'
              AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        // Smith Rcvd — Other Gold
        $smithRcvdOther = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.weight * d.cost),0) as amt
            FROM smithd d
            JOIN smithm m ON m.slno = d.slno
            JOIN items i  ON i.code = d.code
            WHERE i.itype = 'G' AND i.ornament <> 'Y' AND d.givrec = 'R'
              AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        // Refinery Issued (itype=G)
        $refnIssued = DB::selectOne("
            SELECT COALESCE(SUM(d.issuedwgt),0) as wgt,
                   COALESCE(SUM(d.issuedwgt * d.cost),0) as amt
            FROM refineryd d
            JOIN refinerym m ON m.slno = d.slno
            JOIN items i     ON i.code = d.code
            WHERE i.itype = 'G'
              AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        // Refinery Rcvd (itype=G)
        $refnRcvd = DB::selectOne("
            SELECT COALESCE(SUM(d.rcvdwgt),0) as wgt,
                   COALESCE(SUM(d.rcvdwgt * d.cost),0) as amt
            FROM refineryd d
            JOIN refinerym m ON m.slno = d.slno
            JOIN items i     ON i.code = d.code
            WHERE i.itype = 'G'
              AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        // Gold Repair Rcvd (itype=G, givrec=R)
        $gRpRcvd = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.weight * d.cost),0) as amt
            FROM repaird d
            JOIN repairm m ON m.slno = d.slno
            JOIN items i   ON i.code = d.code
            WHERE i.itype = 'G' AND d.givrec = 'R'
              AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        // Gold Repair Issued (itype=G, givrec=G)
        $gRpIssued = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.weight * d.cost),0) as amt
            FROM repaird d
            JOIN repairm m ON m.slno = d.slno
            JOIN items i   ON i.code = d.code
            WHERE i.itype = 'G' AND d.givrec = 'G'
              AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        // Silver Repair Rcvd (itype=S, givrec=R)
        $sRpRcvd = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.weight * d.cost),0) as amt
            FROM repaird d
            JOIN repairm m ON m.slno = d.slno
            JOIN items i   ON i.code = d.code
            WHERE i.itype = 'S' AND d.givrec = 'R'
              AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        // Silver Repair Issued (itype=S, givrec=G)
        $sRpIssued = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt,
                   COALESCE(SUM(d.weight * d.cost),0) as amt
            FROM repaird d
            JOIN repairm m ON m.slno = d.slno
            JOIN items i   ON i.code = d.code
            WHERE i.itype = 'S' AND d.givrec = 'G'
              AND m.tdate >= ? AND m.tdate <= ? AND m.control <= ?
        ", [$date1, $date2, $g]);

        return [
            'gSalesWgt'          => (float)$gSales->wgt,
            'gSalesAmt'          => (float)$gSales->amt,
            'sSalesWgt'          => (float)$sSales->wgt,
            'sSalesAmt'          => (float)$sSales->amt,
            'gSalesRWgt'         => (float)$gSalesR->wgt,
            'gSalesRAmt'         => (float)$gSalesR->amt,
            'sSalesRWgt'         => (float)$sSalesR->wgt,
            'sSalesRAmt'         => (float)$sSalesR->amt,
            'ogPurchWgt'         => (float)$ogPurch->wgt,
            'ogPurchAmt'         => (float)$ogPurch->amt,
            'gPurchWgt'          => (float)$gPurch->wgt,
            'gPurchAmt'          => (float)$gPurch->amt,
            'sPurchWgt'          => (float)$sPurch->wgt,
            'sPurchAmt'          => (float)$sPurch->amt,
            'osPurchWgt'         => (float)$osPurch->wgt,
            'osPurchAmt'         => (float)$osPurch->amt,
            'smithIssuOrnWgt'    => (float)$smithIssuOrn->wgt,
            'smithIssuOrnAmt'    => (float)$smithIssuOrn->amt,
            'smithIssuOtherWgt'  => (float)$smithIssuOther->wgt,
            'smithIssuOtherAmt'  => (float)$smithIssuOther->amt,
            'smithRcvdOrnWgt'    => (float)$smithRcvdOrn->wgt,
            'smithRcvdOrnAmt'    => (float)$smithRcvdOrn->amt,
            'smithRcvdOtherWgt'  => (float)$smithRcvdOther->wgt,
            'smithRcvdOtherAmt'  => (float)$smithRcvdOther->amt,
            'refnIssuedWgt'      => (float)$refnIssued->wgt,
            'refnIssuedAmt'      => (float)$refnIssued->amt,
            'refnRcvdWgt'        => (float)$refnRcvd->wgt,
            'refnRcvdAmt'        => (float)$refnRcvd->amt,
            'gRpRcvdWgt'         => (float)$gRpRcvd->wgt,
            'gRpRcvdAmt'         => (float)$gRpRcvd->amt,
            'gRpIssuedWgt'       => (float)$gRpIssued->wgt,
            'gRpIssuedAmt'       => (float)$gRpIssued->amt,
            'sRpRcvdWgt'         => (float)$sRpRcvd->wgt,
            'sRpRcvdAmt'         => (float)$sRpRcvd->amt,
            'sRpIssuedWgt'       => (float)$sRpIssued->wgt,
            'sRpIssuedAmt'       => (float)$sRpIssued->amt,
        ];
    }

    /**
     * Closing stock by category: ornaments, old gold, other gold, silver orn, old silver
     * Uses items opening + itemsstk + transaction sums up to date2
     */
    private function getClosingStock(string $date2, int $g): array
    {
        $opCol    = $g === 1 ? 'opweight'    : 'opweightb';
        $stkOpCol = $g === 1 ? 'opweight'    : 'opweightb';
        $costOpCol = 'opcost'; // items.opcost for amount

        // Gold Ornaments closing weight: items opening + purchases - sales + sales return + smith rcvd - smith issued + refinery rcvd - refinery issued + repair rcvd - repair issued
        // Simplified: use items.opweight + net movements from all transaction tables
        // The PB code uses external functions like getclosingornwgt(date) — we'll replicate

        $ornWgt   = $this->closingWgt($date2, $g, 'G', 'Y', false);
        $ogWgt    = $this->closingWgt($date2, $g, 'OG', null, false);
        $otherWgt = $this->closingWgt($date2, $g, 'G', 'N', false);
        $soWgt    = $this->closingWgt($date2, $g, 'S', 'Y', false);
        $osWgt    = $this->closingWgt($date2, $g, 'OS', null, false);

        $ornAmt   = $this->closingAmt($date2, $g, 'G', 'Y');
        $ogAmt    = $this->closingAmt($date2, $g, 'OG', null);
        $otherAmt = $this->closingAmt($date2, $g, 'G', 'N');
        $soAmt    = $this->closingAmt($date2, $g, 'S', 'Y');
        $osAmt    = $this->closingAmt($date2, $g, 'OS', null);

        return [
            'ornWgt'   => $ornWgt,   'ornAmt'   => $ornAmt,
            'ogWgt'    => $ogWgt,    'ogAmt'    => $ogAmt,
            'otherWgt' => $otherWgt, 'otherAmt' => $otherAmt,
            'soWgt'    => $soWgt,    'soAmt'    => $soAmt,
            'osWgt'    => $osWgt,    'osAmt'    => $osAmt,
        ];
    }

    /**
     * Closing weight for a category
     * category: 'G' (gold orn/other), 'S' (silver orn), 'OG', 'OS'
     * ornament: 'Y' for ornaments, 'N' for other, null for OG/OS
     */
    private function closingWgt(string $date2, int $g, string $category, ?string $ornament, bool $amountMode = false): float
    {
        $opCol = $g === 1 ? 'opweight' : 'opweightb';
        $valExpr = 'weight';

        // Opening stock from items
        if ($category === 'OG') {
            $op = DB::selectOne("SELECT COALESCE(SUM(i.{$opCol}), 0) as v FROM items i WHERE i.code = 'OG'");
        } elseif ($category === 'OS') {
            $op = DB::selectOne("SELECT COALESCE(SUM(i.{$opCol}), 0) as v FROM items i WHERE i.code = 'OS'");
        } elseif ($ornament === 'Y') {
            $op = DB::selectOne("SELECT COALESCE(SUM(i.{$opCol}), 0) as v FROM items i WHERE i.itype = ? AND i.ornament = 'Y' AND i.code NOT IN ('OG','OS')", [$category]);
        } else {
            $op = DB::selectOne("SELECT COALESCE(SUM(i.{$opCol}), 0) as v FROM items i WHERE i.itype = ? AND i.ornament <> 'Y' AND i.code NOT IN ('OG','OS')", [$category]);
        }

        $total = (float)$op->v;

        // Add purchases
        $total += $this->sumPurchased($date2, $g, $category, $ornament);

        // Subtract sales
        $total -= $this->sumSales($date2, $g, $category, $ornament);

        // Add sales returns
        $total += $this->sumSalesReturn($date2, $g, $category, $ornament);

        // Gold categories: smith and refinery and repair movements
        if (in_array($category, ['G', 'OG'])) {
            if ($category !== 'OG') {
                // Smith: rcvd adds, issued subtracts
                $total += $this->sumSmith($date2, $g, $ornament, 'R');
                $total -= $this->sumSmith($date2, $g, $ornament, 'G');

                // Refinery: rcvd adds, issued subtracts
                $total += $this->sumRefinery($date2, $g, 'rcvdwgt');
                $total -= $this->sumRefinery($date2, $g, 'issuedwgt');

                // Repair: rcvd adds, issued subtracts
                $total += $this->sumRepair($date2, $g, 'G', 'R');
                $total -= $this->sumRepair($date2, $g, 'G', 'G');
            }
        }

        if (in_array($category, ['S'])) {
            if ($ornament === 'Y') {
                $total += $this->sumRepair($date2, $g, 'S', 'R');
                $total -= $this->sumRepair($date2, $g, 'S', 'G');
            }
        }

        return round($total, 3);
    }

    /**
     * Closing cost amount for a category — simpler: use items.opcost + cost-weighted movements
     */
    private function closingAmt(string $date2, int $g, string $category, ?string $ornament): float
    {
        // Opening cost from items
        if ($category === 'OG') {
            $op = DB::selectOne("SELECT COALESCE(SUM(i.opcost), 0) as v FROM items i WHERE i.code = 'OG'");
        } elseif ($category === 'OS') {
            $op = DB::selectOne("SELECT COALESCE(SUM(i.opcost), 0) as v FROM items i WHERE i.code = 'OS'");
        } elseif ($ornament === 'Y') {
            $op = DB::selectOne("SELECT COALESCE(SUM(i.opcost), 0) as v FROM items i WHERE i.itype = ? AND i.ornament = 'Y' AND i.code NOT IN ('OG','OS')", [$category]);
        } else {
            $op = DB::selectOne("SELECT COALESCE(SUM(i.opcost), 0) as v FROM items i WHERE i.itype = ? AND i.ornament <> 'Y' AND i.code NOT IN ('OG','OS')", [$category]);
        }

        $total = (float)$op->v;

        // Purchases: use purchased.amount for cost
        $total += $this->sumPurchasedAmt($date2, $g, $category, $ornament);

        // Sales: weight * cost
        $total -= $this->sumSalesAmt($date2, $g, $category, $ornament);

        // Sales Return
        $total += $this->sumSalesReturnAmt($date2, $g, $category, $ornament);

        // Gold categories: smith, refinery, repair
        if ($category === 'G' && $ornament !== null) {
            $total += $this->sumSmithAmt($date2, $g, $ornament, 'R');
            $total -= $this->sumSmithAmt($date2, $g, $ornament, 'G');

            if ($ornament === 'Y') {
                $total += $this->sumRefineryAmt($date2, $g, 'rcvdwgt');
                $total -= $this->sumRefineryAmt($date2, $g, 'issuedwgt');
            }

            $total += $this->sumRepairAmt($date2, $g, 'G', 'R');
            $total -= $this->sumRepairAmt($date2, $g, 'G', 'G');
        }

        if ($category === 'S' && $ornament === 'Y') {
            $total += $this->sumRepairAmt($date2, $g, 'S', 'R');
            $total -= $this->sumRepairAmt($date2, $g, 'S', 'G');
        }

        return round($total, 2);
    }

    // ── Helper sum functions (weight) ──

    private function sumPurchased(string $date2, int $g, string $cat, ?string $orn): float
    {
        if ($cat === 'OG') {
            $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno WHERE d.code='OG' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        } elseif ($cat === 'OS') {
            $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype='S' AND d.code='OS' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        } else {
            $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
            $codeCond = $cat === 'G' ? "AND d.code<>'OG'" : "AND d.code<>'OS'";
            $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? {$ornCond} {$codeCond} AND m.tdate<=? AND m.control<=?", [$cat, $date2, $g]);
        }
        return (float)$r->v;
    }

    private function sumSales(string $date2, int $g, string $cat, ?string $orn): float
    {
        if (in_array($cat, ['OG', 'OS'])) return 0;
        $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
        $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM salesd d JOIN salesm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? {$ornCond} AND m.tdate<=? AND m.control<=?", [$cat, $date2, $g]);
        return (float)$r->v;
    }

    private function sumSalesReturn(string $date2, int $g, string $cat, ?string $orn): float
    {
        if (in_array($cat, ['OG', 'OS'])) return 0;
        $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
        $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM salesrd d JOIN salesrm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? {$ornCond} AND m.tdate<=? AND m.control<=?", [$cat, $date2, $g]);
        return (float)$r->v;
    }

    private function sumSmith(string $date2, int $g, ?string $orn, string $givrec): float
    {
        $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
        $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM smithd d JOIN smithm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype='G' {$ornCond} AND d.givrec=? AND m.tdate<=? AND m.control<=?", [$givrec, $date2, $g]);
        return (float)$r->v;
    }

    private function sumRefinery(string $date2, int $g, string $col): float
    {
        $r = DB::selectOne("SELECT COALESCE(SUM(d.{$col}),0) as v FROM refineryd d JOIN refinerym m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype='G' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        return (float)$r->v;
    }

    private function sumRepair(string $date2, int $g, string $itype, string $givrec): float
    {
        $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM repaird d JOIN repairm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? AND d.givrec=? AND m.tdate<=? AND m.control<=?", [$itype, $givrec, $date2, $g]);
        return (float)$r->v;
    }

    // ── Helper sum functions (cost amount) ──

    private function sumPurchasedAmt(string $date2, int $g, string $cat, ?string $orn): float
    {
        if ($cat === 'OG') {
            $r = DB::selectOne("SELECT COALESCE(SUM(d.amount),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno WHERE d.code='OG' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        } elseif ($cat === 'OS') {
            $r = DB::selectOne("SELECT COALESCE(SUM(d.amount),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype='S' AND d.code='OS' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        } else {
            $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
            $codeCond = $cat === 'G' ? "AND d.code<>'OG'" : "AND d.code<>'OS'";
            $r = DB::selectOne("SELECT COALESCE(SUM(d.amount),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? {$ornCond} {$codeCond} AND m.tdate<=? AND m.control<=?", [$cat, $date2, $g]);
        }
        return (float)$r->v;
    }

    private function sumSalesAmt(string $date2, int $g, string $cat, ?string $orn): float
    {
        if (in_array($cat, ['OG', 'OS'])) return 0;
        $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
        $r = DB::selectOne("SELECT COALESCE(SUM(d.weight * d.cost),0) as v FROM salesd d JOIN salesm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? {$ornCond} AND m.tdate<=? AND m.control<=?", [$cat, $date2, $g]);
        return (float)$r->v;
    }

    private function sumSalesReturnAmt(string $date2, int $g, string $cat, ?string $orn): float
    {
        if (in_array($cat, ['OG', 'OS'])) return 0;
        $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
        $r = DB::selectOne("SELECT COALESCE(SUM(d.weight * d.cost),0) as v FROM salesrd d JOIN salesrm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? {$ornCond} AND m.tdate<=? AND m.control<=?", [$cat, $date2, $g]);
        return (float)$r->v;
    }

    private function sumSmithAmt(string $date2, int $g, ?string $orn, string $givrec): float
    {
        $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
        $r = DB::selectOne("SELECT COALESCE(SUM(d.weight * d.cost),0) as v FROM smithd d JOIN smithm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype='G' {$ornCond} AND d.givrec=? AND m.tdate<=? AND m.control<=?", [$givrec, $date2, $g]);
        return (float)$r->v;
    }

    private function sumRefineryAmt(string $date2, int $g, string $col): float
    {
        $r = DB::selectOne("SELECT COALESCE(SUM(d.{$col} * d.cost),0) as v FROM refineryd d JOIN refinerym m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype='G' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        return (float)$r->v;
    }

    private function sumRepairAmt(string $date2, int $g, string $itype, string $givrec): float
    {
        $r = DB::selectOne("SELECT COALESCE(SUM(d.weight * d.cost),0) as v FROM repaird d JOIN repairm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? AND d.givrec=? AND m.tdate<=? AND m.control<=?", [$itype, $givrec, $date2, $g]);
        return (float)$r->v;
    }

    /**
     * Smith balance (to get / to give) from clientsgs ctype=G
     */
    private function getSmithBalance(string $date2, int $g): array
    {
        $opCol    = $g === 1 ? 'opweight'  : 'opweightb';
        $opAmtCol = $g === 1 ? 'opwgtamt'  : 'opwgtamtb';

        $smiths = DB::select("SELECT code, {$opCol} as opwgt, {$opAmtCol} as opamt FROM clientsgs WHERE ctype = 'G'");

        $toGetWgt = 0; $toGiveWgt = 0;
        $toGetAmt = 0; $toGiveAmt = 0;

        foreach ($smiths as $s) {
            $code  = $s->code;
            $clWgt = (float)($s->opwgt ?? 0);
            $clAmt = (float)($s->opamt ?? 0);

            // Given to smith (reduces smith balance)
            $given = DB::selectOne("
                SELECT COALESCE(SUM(d.weight + d.wastage - d.stonewgt - d.touchwgt), 0) as wgt,
                       COALESCE(SUM((d.weight + d.wastage - d.stonewgt - d.touchwgt) * d.cost), 0) as amt
                FROM smithd d JOIN smithm m ON m.slno = d.slno
                WHERE m.smithcode = ? AND d.givrec = 'G' AND m.control <= ? AND m.tdate <= ?
            ", [$code, $g, $date2]);

            $clWgt -= (float)$given->wgt;
            $clAmt -= (float)$given->amt;

            // Received from smith (adds to smith balance)
            $rcvd = DB::selectOne("
                SELECT COALESCE(SUM(d.weight + d.wastage - d.stonewgt - d.touchwgt), 0) as wgt,
                       COALESCE(SUM((d.weight + d.wastage - d.stonewgt - d.touchwgt) * d.cost), 0) as amt
                FROM smithd d JOIN smithm m ON m.slno = d.slno
                WHERE m.smithcode = ? AND d.givrec = 'R' AND m.control <= ? AND m.tdate <= ?
            ", [$code, $g, $date2]);

            $clWgt += (float)$rcvd->wgt;
            $clAmt += (float)$rcvd->amt;

            if ($clWgt < 0) {
                $toGetWgt += abs($clWgt);
                $toGetAmt += abs($clAmt);
            } else {
                $toGiveWgt += $clWgt;
                $toGiveAmt += $clAmt;
            }
        }

        return [
            'toGetWgt'  => round($toGetWgt, 3),
            'toGetAmt'  => round($toGetAmt, 2),
            'toGiveWgt' => round($toGiveWgt, 3),
            'toGiveAmt' => round($toGiveAmt, 2),
        ];
    }
}
