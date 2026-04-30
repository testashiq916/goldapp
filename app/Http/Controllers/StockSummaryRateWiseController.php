<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockSummaryRateWiseController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('stock-summary-rate-wise.index');
    }

    public function show(Request $request)
    {
        $date1 = $request->input('date1');
        $date2 = $request->input('date2');
        if (!$date1 || !$date2) {
            return response()->json(['ok' => false, 'message' => 'Date range required']);
        }

        $g = $this->gilevel;

        $txn     = $this->getTransactions($date1, $date2, $g);
        $closing = $this->getClosingStock($date2, $g);
        $smith   = $this->getSmithBalance($date2, $g);
        $cust    = $this->getCustomerBalance($date2, $g);
        $supp    = $this->getSupplierBalance($date2, $g);

        return response()->json([
            'ok'      => true,
            'txn'     => $txn,
            'closing' => $closing,
            'smith'   => $smith,
            'cust'    => $cust,
            'supp'    => $supp,
        ]);
    }

    private function getTransactions(string $date1, string $date2, int $g): array
    {
        // Gold Sales — weight & bill amount (salesd.amount)
        $gSales = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt, COALESCE(SUM(d.amount),0) as amt
            FROM salesd d JOIN salesm m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE m.tdate>=? AND m.tdate<=? AND m.control<=? AND i.itype='G'
        ", [$date1, $date2, $g]);

        // Silver Sales
        $sSales = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt, COALESCE(SUM(d.amount),0) as amt
            FROM salesd d JOIN salesm m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE m.tdate>=? AND m.tdate<=? AND m.control<=? AND i.itype='S'
        ", [$date1, $date2, $g]);

        // Gold Sales Return
        $gSalesR = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt, COALESCE(SUM(d.amount),0) as amt
            FROM salesrd d JOIN salesrm m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE m.tdate>=? AND m.tdate<=? AND m.control<=? AND i.itype='G'
        ", [$date1, $date2, $g]);

        // Silver Sales Return
        $sSalesR = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt, COALESCE(SUM(d.amount),0) as amt
            FROM salesrd d JOIN salesrm m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE m.tdate>=? AND m.tdate<=? AND m.control<=? AND i.itype='S'
        ", [$date1, $date2, $g]);

        // OG Purchase
        $ogPurch = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt, COALESCE(SUM(d.amount),0) as amt
            FROM purchased d JOIN purchasem m ON m.slno=d.slno
            WHERE d.code='OG' AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // GB Purchase
        $gbPurch = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt, COALESCE(SUM(d.amount),0) as amt
            FROM purchased d JOIN purchasem m ON m.slno=d.slno
            WHERE d.code='GB' AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // Other Gold Purchase (itype=G, code not OG/GB)
        $gPurch = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt, COALESCE(SUM(d.amount),0) as amt
            FROM purchased d JOIN purchasem m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='G' AND d.code NOT IN ('OG','GB')
              AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // Silver Purchase (itype=S, code<>OS)
        $sPurch = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt, COALESCE(SUM(d.amount),0) as amt
            FROM purchased d JOIN purchasem m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='S' AND d.code<>'OS' AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // OS Purchase
        $osPurch = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt, COALESCE(SUM(d.amount),0) as amt
            FROM purchased d JOIN purchasem m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='S' AND d.code='OS' AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // Smith Issued — Gold Ornaments (wgtamt for rate-wise amount)
        $smithIssuOrn = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt, COALESCE(SUM(d.wgtamt),0) as amt
            FROM smithd d JOIN smithm m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='G' AND i.ornament='Y' AND d.givrec='G'
              AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // Smith Issued — Other Gold
        $smithIssuOther = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt, COALESCE(SUM(d.wgtamt),0) as amt
            FROM smithd d JOIN smithm m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='G' AND i.ornament<>'Y' AND d.givrec='G'
              AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // Smith Rcvd — Gold Ornaments
        $smithRcvdOrn = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt, COALESCE(SUM(d.wgtamt),0) as amt
            FROM smithd d JOIN smithm m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='G' AND i.ornament='Y' AND d.givrec='R'
              AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // Smith Rcvd — Other Gold
        $smithRcvdOther = DB::selectOne("
            SELECT COALESCE(SUM(d.weight),0) as wgt, COALESCE(SUM(d.wgtamt),0) as amt
            FROM smithd d JOIN smithm m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='G' AND i.ornament<>'Y' AND d.givrec='R'
              AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // Refinery Issued (uses issuedwgtamt for rate-wise)
        $refnIssued = DB::selectOne("
            SELECT COALESCE(SUM(d.issuedwgt),0) as wgt, COALESCE(SUM(d.issuedwgtamt),0) as amt
            FROM refineryd d JOIN refinerym m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='G' AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // Refinery Rcvd (uses rcvdwgtamt for rate-wise)
        $refnRcvd = DB::selectOne("
            SELECT COALESCE(SUM(d.rcvdwgt),0) as wgt, COALESCE(SUM(d.rcvdwgtamt),0) as amt
            FROM refineryd d JOIN refinerym m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='G' AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // Gold Repair Rcvd (addwgt for weight, amount for rate-wise)
        $gRpRcvd = DB::selectOne("
            SELECT COALESCE(SUM(d.addwgt),0) as wgt, COALESCE(SUM(d.amount),0) as amt
            FROM repaird d JOIN repairm m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='G' AND d.givrec='R' AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // Gold Repair Issued
        $gRpIssued = DB::selectOne("
            SELECT COALESCE(SUM(d.addwgt),0) as wgt, COALESCE(SUM(d.amount),0) as amt
            FROM repaird d JOIN repairm m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='G' AND d.givrec='G' AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // Silver Repair Rcvd
        $sRpRcvd = DB::selectOne("
            SELECT COALESCE(SUM(d.addwgt),0) as wgt, COALESCE(SUM(d.amount),0) as amt
            FROM repaird d JOIN repairm m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='S' AND d.givrec='R' AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // Silver Repair Issued
        $sRpIssued = DB::selectOne("
            SELECT COALESCE(SUM(d.addwgt),0) as wgt, COALESCE(SUM(d.amount),0) as amt
            FROM repaird d JOIN repairm m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='S' AND d.givrec='G' AND m.tdate>=? AND m.tdate<=? AND m.control<=?
        ", [$date1, $date2, $g]);

        // Smith Wastage (sum wastage * cost as rate-wise amount)
        $smithWastage = DB::selectOne("
            SELECT COALESCE(SUM(d.wastage),0) as wgt
            FROM smithd d JOIN smithm m ON m.slno=d.slno JOIN items i ON i.code=d.code
            WHERE i.itype='G' AND d.givrec='R' AND m.tdate>=? AND m.tdate<=? AND m.control<=?
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
            'gbPurchWgt'         => (float)$gbPurch->wgt,
            'gbPurchAmt'         => (float)$gbPurch->amt,
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
            'smithWastageWgt'    => (float)$smithWastage->wgt,
        ];
    }

    /**
     * Closing stock: ornaments, old gold, gold bar, other gold, silver orn, old silver
     * Rate-wise uses salesd.amount, smithd.wgtamt, refineryd amounts
     */
    private function getClosingStock(string $date2, int $g): array
    {
        $ornWgt   = $this->closingWgt($date2, $g, 'G', 'Y');
        $ogWgt    = $this->closingWgt($date2, $g, 'OG', null);
        $gbWgt    = $this->closingWgt($date2, $g, 'GB', null);
        $otherWgt = $this->closingWgt($date2, $g, 'G', 'N'); // excludes OG and GB
        $soWgt    = $this->closingWgt($date2, $g, 'S', 'Y');
        $osWgt    = $this->closingWgt($date2, $g, 'OS', null);

        $ornAmt   = $this->closingAmt($date2, $g, 'G', 'Y');
        $ogAmt    = $this->closingAmt($date2, $g, 'OG', null);
        $gbAmt    = $this->closingAmt($date2, $g, 'GB', null);
        $otherAmt = $this->closingAmt($date2, $g, 'G', 'N');
        $soAmt    = $this->closingAmt($date2, $g, 'S', 'Y');
        $osAmt    = $this->closingAmt($date2, $g, 'OS', null);

        return [
            'ornWgt'   => $ornWgt,   'ornAmt'   => $ornAmt,
            'ogWgt'    => $ogWgt,    'ogAmt'    => $ogAmt,
            'gbWgt'    => $gbWgt,    'gbAmt'    => $gbAmt,
            'otherWgt' => $otherWgt, 'otherAmt' => $otherAmt,
            'soWgt'    => $soWgt,    'soAmt'    => $soAmt,
            'osWgt'    => $osWgt,    'osAmt'    => $osAmt,
        ];
    }

    private function closingWgt(string $date2, int $g, string $category, ?string $ornament): float
    {
        $opCol = $g === 1 ? 'opweight' : 'opweightb';

        // Opening from items
        if ($category === 'OG') {
            $op = DB::selectOne("SELECT COALESCE(SUM({$opCol}),0) as v FROM items WHERE code='OG'");
        } elseif ($category === 'GB') {
            $op = DB::selectOne("SELECT COALESCE(SUM({$opCol}),0) as v FROM items WHERE code='GB'");
        } elseif ($category === 'OS') {
            $op = DB::selectOne("SELECT COALESCE(SUM({$opCol}),0) as v FROM items WHERE code='OS'");
        } elseif ($ornament === 'Y') {
            $op = DB::selectOne("SELECT COALESCE(SUM({$opCol}),0) as v FROM items WHERE itype=? AND ornament='Y' AND code NOT IN ('OG','OS','GB')", [$category]);
        } else {
            // Other gold/silver (non-ornament, excluding OG/GB/OS)
            $op = DB::selectOne("SELECT COALESCE(SUM({$opCol}),0) as v FROM items WHERE itype=? AND ornament<>'Y' AND code NOT IN ('OG','OS','GB')", [$category]);
        }

        $total = (float)$op->v;

        // Purchases
        $total += $this->sumPurchWgt($date2, $g, $category, $ornament);
        // Sales
        $total -= $this->sumSalesWgt($date2, $g, $category, $ornament);
        // Sales Return
        $total += $this->sumSalesRetWgt($date2, $g, $category, $ornament);

        // Gold categories: smith, refinery, repair
        if (in_array($category, ['G']) && $ornament !== null) {
            $total += $this->sumSmithWgt($date2, $g, $ornament, 'R');
            $total -= $this->sumSmithWgt($date2, $g, $ornament, 'G');

            if ($ornament === 'Y') {
                $total += $this->sumRefWgt($date2, $g, 'rcvdwgt');
                $total -= $this->sumRefWgt($date2, $g, 'issuedwgt');
            }

            $total += $this->sumRepairWgt($date2, $g, 'G', 'R');
            $total -= $this->sumRepairWgt($date2, $g, 'G', 'G');
        }

        if ($category === 'S' && $ornament === 'Y') {
            $total += $this->sumRepairWgt($date2, $g, 'S', 'R');
            $total -= $this->sumRepairWgt($date2, $g, 'S', 'G');
        }

        return round($total, 3);
    }

    /**
     * Rate-wise closing amount: uses salesd.amount, smithd.wgtamt, refineryd wgtamt, repaird.amount
     */
    private function closingAmt(string $date2, int $g, string $category, ?string $ornament): float
    {
        // Opening cost from items (same as cost-wise — opcost is the base)
        if ($category === 'OG') {
            $op = DB::selectOne("SELECT COALESCE(SUM(opcost),0) as v FROM items WHERE code='OG'");
        } elseif ($category === 'GB') {
            $op = DB::selectOne("SELECT COALESCE(SUM(opcost),0) as v FROM items WHERE code='GB'");
        } elseif ($category === 'OS') {
            $op = DB::selectOne("SELECT COALESCE(SUM(opcost),0) as v FROM items WHERE code='OS'");
        } elseif ($ornament === 'Y') {
            $op = DB::selectOne("SELECT COALESCE(SUM(opcost),0) as v FROM items WHERE itype=? AND ornament='Y' AND code NOT IN ('OG','OS','GB')", [$category]);
        } else {
            $op = DB::selectOne("SELECT COALESCE(SUM(opcost),0) as v FROM items WHERE itype=? AND ornament<>'Y' AND code NOT IN ('OG','OS','GB')", [$category]);
        }

        $total = (float)$op->v;

        // Purchase amount
        $total += $this->sumPurchAmt($date2, $g, $category, $ornament);
        // Sales amount (rate-wise: salesd.amount)
        $total -= $this->sumSalesAmt($date2, $g, $category, $ornament);
        // Sales Return amount
        $total += $this->sumSalesRetAmt($date2, $g, $category, $ornament);

        // Gold: smith, refinery, repair
        if ($category === 'G' && $ornament !== null) {
            // Smith uses wgtamt for rate-wise
            $total += $this->sumSmithAmt($date2, $g, $ornament, 'R');
            $total -= $this->sumSmithAmt($date2, $g, $ornament, 'G');

            if ($ornament === 'Y') {
                // Refinery uses rcvdwgtamt / issuedwgtamt
                $total += $this->sumRefAmt($date2, $g, 'rcvdwgtamt');
                $total -= $this->sumRefAmt($date2, $g, 'issuedwgtamt');
            }

            // Repair uses repaird.amount
            $total += $this->sumRepairAmt($date2, $g, 'G', 'R');
            $total -= $this->sumRepairAmt($date2, $g, 'G', 'G');
        }

        if ($category === 'S' && $ornament === 'Y') {
            $total += $this->sumRepairAmt($date2, $g, 'S', 'R');
            $total -= $this->sumRepairAmt($date2, $g, 'S', 'G');
        }

        return round($total, 2);
    }

    // ── Weight helpers ──

    private function sumPurchWgt(string $date2, int $g, string $cat, ?string $orn): float
    {
        if ($cat === 'OG') {
            $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno WHERE d.code='OG' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        } elseif ($cat === 'GB') {
            $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno WHERE d.code='GB' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        } elseif ($cat === 'OS') {
            $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno WHERE d.code='OS' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        } else {
            $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
            $excl = $cat === 'G' ? "AND d.code NOT IN ('OG','GB')" : "AND d.code<>'OS'";
            $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? {$ornCond} {$excl} AND m.tdate<=? AND m.control<=?", [$cat, $date2, $g]);
        }
        return (float)$r->v;
    }

    private function sumSalesWgt(string $date2, int $g, string $cat, ?string $orn): float
    {
        if (in_array($cat, ['OG', 'GB', 'OS'])) return 0;
        $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
        $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM salesd d JOIN salesm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? {$ornCond} AND m.tdate<=? AND m.control<=?", [$cat, $date2, $g]);
        return (float)$r->v;
    }

    private function sumSalesRetWgt(string $date2, int $g, string $cat, ?string $orn): float
    {
        if (in_array($cat, ['OG', 'GB', 'OS'])) return 0;
        $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
        $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM salesrd d JOIN salesrm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? {$ornCond} AND m.tdate<=? AND m.control<=?", [$cat, $date2, $g]);
        return (float)$r->v;
    }

    private function sumSmithWgt(string $date2, int $g, ?string $orn, string $givrec): float
    {
        $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
        $r = DB::selectOne("SELECT COALESCE(SUM(d.weight),0) as v FROM smithd d JOIN smithm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype='G' {$ornCond} AND d.givrec=? AND m.tdate<=? AND m.control<=?", [$givrec, $date2, $g]);
        return (float)$r->v;
    }

    private function sumRefWgt(string $date2, int $g, string $col): float
    {
        $r = DB::selectOne("SELECT COALESCE(SUM(d.{$col}),0) as v FROM refineryd d JOIN refinerym m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype='G' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        return (float)$r->v;
    }

    private function sumRepairWgt(string $date2, int $g, string $itype, string $givrec): float
    {
        $r = DB::selectOne("SELECT COALESCE(SUM(d.addwgt),0) as v FROM repaird d JOIN repairm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? AND d.givrec=? AND m.tdate<=? AND m.control<=?", [$itype, $givrec, $date2, $g]);
        return (float)$r->v;
    }

    // ── Amount helpers (rate-wise) ──

    private function sumPurchAmt(string $date2, int $g, string $cat, ?string $orn): float
    {
        if ($cat === 'OG') {
            $r = DB::selectOne("SELECT COALESCE(SUM(d.amount),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno WHERE d.code='OG' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        } elseif ($cat === 'GB') {
            $r = DB::selectOne("SELECT COALESCE(SUM(d.amount),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno WHERE d.code='GB' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        } elseif ($cat === 'OS') {
            $r = DB::selectOne("SELECT COALESCE(SUM(d.amount),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno WHERE d.code='OS' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        } else {
            $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
            $excl = $cat === 'G' ? "AND d.code NOT IN ('OG','GB')" : "AND d.code<>'OS'";
            $r = DB::selectOne("SELECT COALESCE(SUM(d.amount),0) as v FROM purchased d JOIN purchasem m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? {$ornCond} {$excl} AND m.tdate<=? AND m.control<=?", [$cat, $date2, $g]);
        }
        return (float)$r->v;
    }

    private function sumSalesAmt(string $date2, int $g, string $cat, ?string $orn): float
    {
        if (in_array($cat, ['OG', 'GB', 'OS'])) return 0;
        $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
        // Rate-wise: use salesd.amount
        $r = DB::selectOne("SELECT COALESCE(SUM(d.amount),0) as v FROM salesd d JOIN salesm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? {$ornCond} AND m.tdate<=? AND m.control<=?", [$cat, $date2, $g]);
        return (float)$r->v;
    }

    private function sumSalesRetAmt(string $date2, int $g, string $cat, ?string $orn): float
    {
        if (in_array($cat, ['OG', 'GB', 'OS'])) return 0;
        $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
        // Rate-wise: use salesrd.amount
        $r = DB::selectOne("SELECT COALESCE(SUM(d.amount),0) as v FROM salesrd d JOIN salesrm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? {$ornCond} AND m.tdate<=? AND m.control<=?", [$cat, $date2, $g]);
        return (float)$r->v;
    }

    private function sumSmithAmt(string $date2, int $g, ?string $orn, string $givrec): float
    {
        $ornCond = $orn === 'Y' ? "AND i.ornament='Y'" : "AND i.ornament<>'Y'";
        // Rate-wise: use smithd.wgtamt
        $r = DB::selectOne("SELECT COALESCE(SUM(d.wgtamt),0) as v FROM smithd d JOIN smithm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype='G' {$ornCond} AND d.givrec=? AND m.tdate<=? AND m.control<=?", [$givrec, $date2, $g]);
        return (float)$r->v;
    }

    private function sumRefAmt(string $date2, int $g, string $col): float
    {
        // Rate-wise: use refineryd.rcvdwgtamt / issuedwgtamt
        $r = DB::selectOne("SELECT COALESCE(SUM(d.{$col}),0) as v FROM refineryd d JOIN refinerym m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype='G' AND m.tdate<=? AND m.control<=?", [$date2, $g]);
        return (float)$r->v;
    }

    private function sumRepairAmt(string $date2, int $g, string $itype, string $givrec): float
    {
        // Rate-wise: use repaird.amount
        $r = DB::selectOne("SELECT COALESCE(SUM(d.amount),0) as v FROM repaird d JOIN repairm m ON m.slno=d.slno JOIN items i ON i.code=d.code WHERE i.itype=? AND d.givrec=? AND m.tdate<=? AND m.control<=?", [$itype, $givrec, $date2, $g]);
        return (float)$r->v;
    }

    /**
     * Smith balance — weight & wgtamt (rate-wise)
     */
    private function getSmithBalance(string $date2, int $g): array
    {
        $opCol    = $g === 1 ? 'opweight'  : 'opweightb';
        $opAmtCol = $g === 1 ? 'opwgtamt'  : 'opwgtamtb';

        $smiths = DB::select("SELECT code, {$opCol} as opwgt, {$opAmtCol} as opamt FROM clientsgs WHERE ctype='G'");

        $toGetWgt = 0; $toGiveWgt = 0;
        $toGetAmt = 0; $toGiveAmt = 0;
        $wastageWgt = 0;

        foreach ($smiths as $s) {
            $code  = $s->code;
            $clWgt = (float)($s->opwgt ?? 0);
            $clAmt = (float)($s->opamt ?? 0);

            // Given to smith
            $given = DB::selectOne("
                SELECT COALESCE(SUM(d.weight + d.wastage - d.stonewgt - d.touchwgt),0) as wgt,
                       COALESCE(SUM(d.wgtamt),0) as amt
                FROM smithd d JOIN smithm m ON m.slno=d.slno
                WHERE m.smithcode=? AND d.givrec='G' AND m.control<=? AND m.tdate<=?
            ", [$code, $g, $date2]);

            $clWgt -= (float)$given->wgt;
            $clAmt -= (float)$given->amt;

            // Received from smith
            $rcvd = DB::selectOne("
                SELECT COALESCE(SUM(d.weight + d.wastage - d.stonewgt - d.touchwgt),0) as wgt,
                       COALESCE(SUM(d.wgtamt),0) as amt,
                       COALESCE(SUM(d.wastage),0) as waste
                FROM smithd d JOIN smithm m ON m.slno=d.slno
                WHERE m.smithcode=? AND d.givrec='R' AND m.control<=? AND m.tdate<=?
            ", [$code, $g, $date2]);

            $clWgt += (float)$rcvd->wgt;
            $clAmt += (float)$rcvd->amt;
            $wastageWgt += (float)$rcvd->waste;

            if ($clWgt < 0) {
                $toGetWgt += abs($clWgt);
                $toGetAmt += abs($clAmt);
            } else {
                $toGiveWgt += $clWgt;
                $toGiveAmt += $clAmt;
            }
        }

        return [
            'toGetWgt'    => round($toGetWgt, 3),
            'toGetAmt'    => round($toGetAmt, 2),
            'toGiveWgt'   => round($toGiveWgt, 3),
            'toGiveAmt'   => round($toGiveAmt, 2),
            'wastageWgt'  => round($wastageWgt, 3),
        ];
    }

    /**
     * Customer balance (ctype=C): to get, to give
     * Uses salesm.custcode, salesrm.custcode, daybook.accode for receipts
     */
    private function getCustomerBalance(string $date2, int $g): array
    {
        $opCol = $g === 1 ? 'opbalance' : 'opbalanceb';

        $customers = DB::select("SELECT code, {$opCol} as opbal FROM clients WHERE ctype='C'");

        $toGet = 0; $toGive = 0;

        foreach ($customers as $c) {
            $bal = (float)($c->opbal ?? 0);

            // Sales (adds to customer balance)
            $sales = DB::selectOne("
                SELECT COALESCE(SUM(m.netamt),0) as v
                FROM salesm m WHERE m.custcode=? AND m.tdate<=? AND m.control<=?
            ", [$c->code, $date2, $g]);
            $bal += (float)$sales->v;

            // Sales Returns (reduces customer balance)
            $sr = DB::selectOne("
                SELECT COALESCE(SUM(m.netamt),0) as v
                FROM salesrm m WHERE m.custcode=? AND m.tdate<=? AND m.control<=?
            ", [$c->code, $date2, $g]);
            $bal -= (float)$sr->v;

            // Receipts from daybook (reduces customer balance)
            $rcpt = DB::selectOne("
                SELECT COALESCE(SUM(d.amount),0) as v
                FROM daybook d WHERE d.accode=? AND d.tdate<=? AND d.control<=?
            ", [$c->code, $date2, $g]);
            $bal -= (float)$rcpt->v;

            if ($bal > 0) {
                $toGet += $bal;
            } else {
                $toGive += abs($bal);
            }
        }

        return [
            'toGet'   => round($toGet, 2),
            'toGive'  => round($toGive, 2),
        ];
    }

    /**
     * Supplier balance (ctype=S): to get, to give
     * Uses purchasem.suppcode, purchaserm.suppcode, daybook.accode for payments
     */
    private function getSupplierBalance(string $date2, int $g): array
    {
        $opCol = $g === 1 ? 'opbalance' : 'opbalanceb';

        $suppliers = DB::select("SELECT code, {$opCol} as opbal FROM clients WHERE ctype='S'");

        $toGet = 0; $toGive = 0;

        foreach ($suppliers as $s) {
            $bal = (float)($s->opbal ?? 0);

            // Purchases (adds to supplier balance — we owe them)
            $purch = DB::selectOne("
                SELECT COALESCE(SUM(m.netamt),0) as v
                FROM purchasem m WHERE m.suppcode=? AND m.tdate<=? AND m.control<=?
            ", [$s->code, $date2, $g]);
            $bal += (float)$purch->v;

            // Purchase Returns (reduces supplier balance)
            $pr = DB::selectOne("
                SELECT COALESCE(SUM(m.netamt),0) as v
                FROM purchaserm m WHERE m.suppcode=? AND m.tdate<=? AND m.control<=?
            ", [$s->code, $date2, $g]);
            $bal -= (float)$pr->v;

            // Payments from daybook (reduces supplier balance)
            $pay = DB::selectOne("
                SELECT COALESCE(SUM(d.amount),0) as v
                FROM daybook d WHERE d.accode=? AND d.tdate<=? AND d.control<=?
            ", [$s->code, $date2, $g]);
            $bal -= (float)$pay->v;

            if ($bal > 0) {
                $toGive += $bal; // we owe supplier
            } else {
                $toGet += abs($bal); // supplier owes us
            }
        }

        return [
            'toGet'  => round($toGet, 2),
            'toGive' => round($toGive, 2),
        ];
    }
}
