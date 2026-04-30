<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesBillConfirmationController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');

        $cashBanks = DB::select("SELECT code, name FROM clients WHERE ctype IN ('B','H') ORDER BY name");

        return view('sales-bill-confirmation.index', [
            'cashBanks' => $cashBanks,
        ]);
    }

    /**
     * Load pending bills for a given date
     */
    public function load(Request $request): JsonResponse
    {
        $date = $request->input('date', date('Y-m-d'));

        $controlFilter = 'm.control >= 2';

        $bills = DB::select("
            SELECT m.slno, m.tdate, m.billno, m.custcode, m.custname, m.smcode,
                   m.cocode, m.staxamt, m.staxperc, m.discount, m.ramt,
                   m.netamt, m.duedate, m.control, m.orderno, m.sr,
                   m.billamt, m.ccamt, m.chqamt, m.chqno, m.chqdate, m.chqbank,
                   m.cbcode, m.eamt, m.sretamt, m.astamt, m.astperc, m.cst,
                   m.`round`, m.advance, m.fr, m.billtype,
                   (m.netamt - m.ramt) as balance
            FROM salesm m
            WHERE m.tdate = ? AND {$controlFilter}
            ORDER BY m.tdate ASC, m.slno ASC
        ", [$date]);

        // Filter out TSE/SOE prefix bills
        $bills = array_values(array_filter($bills, function($b) {
            $pre = substr($b->billno ?? '', 0, 3);
            return $pre !== 'TSE' && $pre !== 'SOE';
        }));

        return response()->json(['ok' => true, 'bills' => $bills]);
    }

    /**
     * Update & Confirm a bill (Update & Print)
     */
    public function confirm(Request $request): JsonResponse
    {
        $slno     = (int) $request->input('slno');
        $discount = round((float) $request->input('discount', 0), 2);
        $ramt     = round((float) $request->input('ramt', 0), 2);
        $custcode = trim((string) $request->input('custcode', ''));
        $custname = trim((string) $request->input('custname', ''));
        $duedate  = $request->input('duedate');
        $staxamt  = round((float) $request->input('staxamt', 0), 2);
        $ccamt    = round((float) $request->input('ccamt', 0), 2);
        $chqamt   = round((float) $request->input('chqamt', 0), 2);
        $cbcode   = trim((string) $request->input('cbcode', 'CASH'));
        $cocode   = trim((string) $request->input('cocode', ''));
        $estToBill = (bool) $request->input('estToBill', false);

        $bill = DB::selectOne("SELECT * FROM salesm WHERE slno = ?", [$slno]);
        if (!$bill) {
            return response()->json(['ok' => false, 'message' => 'Bill not found']);
        }

        $g = $this->gilevel;
        $control = $bill->control;
        $billno = $bill->billno;
        $tdate = $bill->tdate;
        $billamt = (float) $bill->billamt;
        $astperc = (float) ($bill->astperc ?? 0);
        $taxperc = (float) ($bill->staxperc ?? 0);
        $cst = $bill->cst ?? 'N';
        $oldDisc = (float) $bill->discount;
        $oldRamt = (float) $bill->ramt;
        $oldTax = (float) $bill->staxamt;
        $oldCcamt = (float) ($bill->ccamt ?? 0);
        $oldAst = (float) ($bill->astamt ?? 0);
        $ast = $oldAst;
        $round = (float) ($bill->round ?? 0);
        $eamt = (float) ($bill->eamt ?? 0);
        $sretamt = (float) ($bill->sretamt ?? 0);
        $sname = $bill->custname ?? '';
        $billtype = $bill->billtype ?? '';
        $orderno = trim($bill->orderno ?? '');
        $newBillno = $billno;

        // Est to Bill conversion: if control > 1 and estToBill, get new bill number
        if ($control > 1 && $estToBill) {
            $newBillno = $this->getNextBillNo($billtype);
            $control = 1;

            $sacpart = "By Sales ({$newBillno})";
            if ($custname) $sacpart .= " To " . trim($custname);

            DB::update("UPDATE salesm SET billno = ?, control = 1, sr = 'S' WHERE slno = ?", [$newBillno, $slno]);
            DB::update("UPDATE daybook SET control = 1 WHERE slno = ?", [$slno]);
            DB::update("UPDATE salesrm SET control = 1 WHERE slno = ?", [$slno]);
            DB::update("UPDATE purchasem SET control = 1 WHERE slno = ?", [$slno]);
            if ($this->hasTable('daybookpart')) {
                DB::update("UPDATE daybookpart SET particular = ? WHERE slno = ?", [$sacpart, $slno]);
            }
        } elseif ($control >= 2) {
            // SalesConfirmModel queue: approve bill → set control=1 (confirmed/posted)
            $control = 1;
            DB::update("UPDATE salesm SET control = 1 WHERE slno = ?", [$slno]);
            DB::update("UPDATE daybook SET control = 1 WHERE slno = ?", [$slno]);
            DB::update("UPDATE salesrm SET control = 1 WHERE slno = ?", [$slno]);
            DB::update("UPDATE purchasem SET control = 1 WHERE slno = ?", [$slno]);
        }

        // Recalculate tax if discount changed
        $tax = $staxamt;
        if ($taxperc > 0) {
            $tax = round(($billamt - $discount) * $taxperc / 100, 2);
            $ast = round(($billamt - $discount) * $astperc / 100, 2);
        }

        // Recalc netamt
        $netamt = $billamt - $discount + $tax + $ast + $round - $eamt - $sretamt;
        $dround = 0;
        $netamt2 = round($netamt, 0);
        $dround = $netamt - $netamt2;

        $balance = $netamt2 - $ramt;

        // Validate: can't have credit without customer code
        if ($custcode == '' && $balance != 0) {
            return response()->json(['ok' => false, 'message' => 'Party account required for credit sales']);
        }

        try {
            // Update salesm
            DB::update("UPDATE salesm SET staxamt = ?, discount = ?, ramt = ?, custcode = ?,
                custname = ?, duedate = ?, netamt = ?, ccamt = ?, cbcode = ?,
                `round` = ?, cocode = ?, staxperc = ?, astamt = ?
                WHERE slno = ?", [
                $tax, $discount, $ramt, $custcode, $custname,
                $duedate, $netamt2, $ccamt, $cbcode, $dround,
                $cocode, $taxperc, $ast, $slno
            ]);

            // Update pdclist control (optional table)
            if ($this->hasTable('pdclist')) {
                DB::update("UPDATE pdclist SET control = ? WHERE slno = ?", [$control, $slno]);
            }

            // Rebuild daybook entries if amounts changed
            if ($discount != $oldDisc || $ramt != $oldRamt || $tax != $oldTax || $ccamt != $oldCcamt) {
                $this->rebuildDaybook($slno, $control, $tdate, $custcode, $discount, $ramt,
                    $tax, $ast, $ccamt, $chqamt, $cbcode, $netamt2, $cst,
                    $orderno, $bill);
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Update failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Bill confirmed: ' . $newBillno,
            'billno' => $newBillno,
            'slno' => $slno,
        ]);
    }

    /**
     * Delete/Cancel a bill — reverse stock and delete records
     */
    public function delete(Request $request): JsonResponse
    {
        $slno = (int) $request->input('slno');
        $bill = DB::selectOne("SELECT * FROM salesm WHERE slno = ?", [$slno]);
        if (!$bill) {
            return response()->json(['ok' => false, 'message' => 'Bill not found']);
        }

        $g = $this->gilevel;
        $userId = $request->session()->get('user_code', '');

        // Reverse sales items stock
        $salesItems = DB::select("
            SELECT d.code, d.qty, d.weight, d.stonewgt, m.control, d.jcode, d.bcode, d.stktype
            FROM salesd d JOIN salesm m ON m.slno = d.slno
            WHERE d.slno = ?
        ", [$slno]);

        foreach ($salesItems as $si) {
            $code = trim($si->code ?? '');
            $qty = (int) ($si->qty ?? 0);
            $wgt = round((float) ($si->weight ?? 0), 3);
            $stwgt = round((float) ($si->stonewgt ?? 0), 3);
            $jcode = trim($si->jcode ?? '');
            $stktype = trim($si->stktype ?? '');

            // Only reverse if not job work item
            if ($jcode == '') {
                if ($si->control == 1) {
                    DB::update("UPDATE items SET weight = weight + ?, qty = qty + ?,
                        weightb = weightb + ?, qtyb = qtyb + ?,
                        stonewgt = stonewgt + ?, stonewgtb = stonewgtb + ?
                        WHERE TRIM(code) = ?", [$wgt, $qty, $wgt, $qty, $stwgt, $stwgt, $code]);

                    if ($stktype != '') {
                        DB::update("UPDATE itemsstk SET weight = weight + ?, qty = qty + ?,
                            weightb = weightb + ?, qtyb = qtyb + ?,
                            stonewgt = stonewgt + ?, stonewgtb = stonewgtb + ?
                            WHERE TRIM(code) = ? AND stktype = ?",
                            [$wgt, $qty, $wgt, $qty, $stwgt, $stwgt, $code, $stktype]);
                    }
                } elseif ($si->control == 2) {
                    DB::update("UPDATE items SET weightb = weightb + ?, qtyb = qtyb + ?,
                        stonewgtb = stonewgtb + ?
                        WHERE TRIM(code) = ?", [$wgt, $qty, $stwgt, $code]);

                    if ($stktype != '') {
                        DB::update("UPDATE itemsstk SET weightb = weightb + ?, qtyb = qtyb + ?,
                            stonewgtb = stonewgtb + ?
                            WHERE TRIM(code) = ? AND stktype = ?",
                            [$wgt, $qty, $stwgt, $code, $stktype]);
                    }
                }
            }

            // Restore barcode stock
            if (($si->bcode ?? 0) > 0) {
                DB::update("UPDATE barcode SET stk = 'Y' WHERE bcode = ?", [$si->bcode]);
            }
        }

        // Reverse sales return items (subtract back)
        $srItems = DB::select("
            SELECT d.code, d.qty, d.weight, d.stonewgt, m.control, d.stktype
            FROM salesrd d JOIN salesrm m ON m.slno = d.slno
            WHERE d.slno = ?
        ", [$slno]);

        foreach ($srItems as $sr) {
            $code = trim($sr->code ?? '');
            $qty = (int) ($sr->qty ?? 0);
            $wgt = round((float) ($sr->weight ?? 0), 3);
            $stwgt = round((float) ($sr->stonewgt ?? 0), 3);
            $stktype = trim($sr->stktype ?? '');

            if ($sr->control == 1) {
                DB::update("UPDATE items SET weight = weight - ?, qty = qty - ?,
                    weightb = weightb - ?, qtyb = qtyb - ?,
                    stonewgt = stonewgt - ?, stonewgtb = stonewgtb - ?
                    WHERE TRIM(code) = ?", [$wgt, $qty, $wgt, $qty, $stwgt, $stwgt, $code]);

                if ($stktype != '') {
                    DB::update("UPDATE itemsstk SET weight = weight - ?, qty = qty - ?,
                        weightb = weightb - ?, qtyb = qtyb - ?,
                        stonewgt = stonewgt - ?, stonewgtb = stonewgtb - ?
                        WHERE TRIM(code) = ? AND stktype = ?",
                        [$wgt, $qty, $wgt, $qty, $stwgt, $stwgt, $code, $stktype]);
                }
            } elseif ($sr->control == 2) {
                DB::update("UPDATE items SET weightb = weightb - ?, qtyb = qtyb - ?,
                    stonewgtb = stonewgtb - ?
                    WHERE TRIM(code) = ?", [$wgt, $qty, $stwgt, $code]);

                if ($stktype != '') {
                    DB::update("UPDATE itemsstk SET weightb = weightb - ?, qtyb = qtyb - ?,
                        stonewgtb = stonewgtb - ?
                        WHERE TRIM(code) = ? AND stktype = ?",
                        [$wgt, $qty, $stwgt, $code, $stktype]);
                }
            }
        }

        // Reverse exchange purchase items
        $purchItems = DB::select("
            SELECT d.code, d.qty, d.weight, m.control, d.cost, d.stktype
            FROM purchased d JOIN purchasem m ON m.slno = d.slno
            WHERE d.slno = ?
        ", [$slno]);

        foreach ($purchItems as $pi) {
            $code = trim($pi->code ?? '');
            $qty = (int) ($pi->qty ?? 0);
            $wgt = round((float) ($pi->weight ?? 0), 3);
            $stktype = trim($pi->stktype ?? '');

            if ($pi->control == 1) {
                DB::update("UPDATE items SET weight = weight - ?, qty = qty - ?,
                    weightb = weightb - ?, qtyb = qtyb - ?
                    WHERE TRIM(code) = ?", [$wgt, $qty, $wgt, $qty, $code]);

                if ($stktype != '') {
                    DB::update("UPDATE itemsstk SET weight = weight - ?, qty = qty - ?,
                        weightb = weightb - ?, qtyb = qtyb - ?
                        WHERE TRIM(code) = ? AND stktype = ?",
                        [$wgt, $qty, $wgt, $qty, $code, $stktype]);
                }
            } elseif ($pi->control == 2) {
                DB::update("UPDATE items SET weightb = weightb - ?, qtyb = qtyb - ?
                    WHERE TRIM(code) = ?", [$wgt, $qty, $code]);

                if ($stktype != '') {
                    DB::update("UPDATE itemsstk SET weightb = weightb - ?, qtyb = qtyb - ?
                        WHERE TRIM(code) = ? AND stktype = ?",
                        [$wgt, $qty, $code, $stktype]);
                }
            }
        }

        // Get bill info for log
        $netamt = (float) ($bill->netamt ?? 0);
        $tdate = $bill->tdate;
        $billno = $bill->billno;
        $orderno = trim($bill->orderno ?? '');

        // Delete all records for this slno
        DB::delete("DELETE FROM salesd WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM salesm WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM salesrd WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM salesrm WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM purchased WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM purchasem WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM daybook WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM daybookpart WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM stkandprofit WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM oglist WHERE slno = ?", [$slno]);

        // Log deletion
        DB::insert("INSERT INTO delpart (tdate, part, control, slno, utype, ttype, updtdate, updttime, uid, ic)
            VALUES (?, ?, ?, ?, 'C', 'S', ?, ?, ?, ?)", [
            $tdate,
            "Sales Entry({$billno}) - NetAmt " . number_format($netamt, 2) . " Canceled - {$tdate}",
            $g, $slno, date('Y-m-d'), date('H:i:s'), $userId, $userId
        ]);

        // Reset order status if order-based
        if ($orderno != '') {
            DB::update("UPDATE orderm SET status = 1 WHERE ordno = ?", [$orderno]);
        } elseif (substr($billno, 0, 2) == 'SO') {
            DB::update("UPDATE orderm SET status = 1 WHERE salebill = ?", [$billno]);
        }

        DB::statement("COMMIT");

        return response()->json(['ok' => true, 'message' => 'Bill cancelled: ' . $billno]);
    }

    /**
     * View bill details
     */
    public function view(Request $request): JsonResponse
    {
        $slno = (int) $request->input('slno');
        $bill = DB::selectOne("SELECT * FROM salesm WHERE slno = ?", [$slno]);
        if (!$bill) {
            return response()->json(['ok' => false, 'message' => 'Bill not found']);
        }

        $items = DB::select("
            SELECT d.*, i.name as itemname
            FROM salesd d
            LEFT JOIN items i ON i.code = d.code
            WHERE d.slno = ?
            ORDER BY d.sno
        ", [$slno]);

        $srItems = DB::select("
            SELECT d.*, i.name as itemname
            FROM salesrd d
            LEFT JOIN items i ON i.code = d.code
            WHERE d.slno = ?
            ORDER BY d.sno
        ", [$slno]);

        $exchItems = DB::select("
            SELECT d.*, i.name as itemname
            FROM purchased d
            LEFT JOIN items i ON i.code = d.code
            WHERE d.slno = ?
            ORDER BY d.sno
        ", [$slno]);

        return response()->json([
            'ok' => true,
            'bill' => $bill,
            'items' => $items,
            'srItems' => $srItems,
            'exchItems' => $exchItems,
        ]);
    }

    // ── Private helpers ──

    private function getSalesConfirmModel(): bool
    {
        $iniPath = storage_path('app/software-settings.ini');
        if (!file_exists($iniPath)) return false;
        $parsed = @parse_ini_file($iniPath, true);
        $val = strtoupper(trim((string) ($parsed['Software']['SalesConfirmModel'] ?? 'N')));
        return $val === 'Y';
    }

    private function getNextBillNo(string $billtype): string
    {
        $prefix = '';
        $len = 5;

        // Check if billtype-wise numbering
        if ($billtype != '') {
            $st = DB::selectOne("SELECT prefix, startno FROM salestype WHERE code = ?", [$billtype]);
            if ($st && $st->prefix) {
                $prefix = $st->prefix;
                $counterCode = 'SALES' . $prefix;
            }
        }

        if (!$prefix) {
            $counterCode = 'SALESB';
            $pref = DB::selectOne("SELECT cvalue FROM generals WHERE code = 'SBPREF'");
            $prefix = $pref->cvalue ?? 'SLB/';
        }

        $lenRow = DB::selectOne("SELECT cvalue FROM generals WHERE code = 'SBLEN'");
        $len = (int) ($lenRow->cvalue ?? 5);

        // Get current counter
        $counter = DB::selectOne("SELECT cvalue FROM generali WHERE code = ?", [$counterCode]);
        if (!$counter) {
            DB::insert("INSERT INTO generali (code, cvalue) VALUES (?, 0)", [$counterCode]);
            $num = 1;
        } else {
            $num = (int) $counter->cvalue + 1;
        }

        DB::update("UPDATE generali SET cvalue = ? WHERE code = ?", [$num, $counterCode]);

        return $prefix . str_pad($num, $len, '0', STR_PAD_LEFT);
    }

    private function rebuildDaybook(int $slno, int $control, string $tdate, string $custcode,
        float $discount, float $ramt, float $tax, float $ast, float $ccamt, float $chqamt,
        string $cbcode, float $netamt, string $cst, string $orderno, $bill): void
    {
        $opaccode = 'RS';

        // Delete old daybook entries for recalculable items
        DB::delete("DELETE FROM daybook WHERE slno = ? AND accode = 'DISC'", [$slno]);
        DB::delete("DELETE FROM daybook WHERE slno = ? AND accode = ?", [$slno, $bill->custcode ?? '']);
        DB::delete("DELETE FROM daybook WHERE slno = ? AND accode = 'CASH'", [$slno]);
        DB::delete("DELETE FROM daybook WHERE slno = ? AND accode = 'ROUND'", [$slno]);
        DB::delete("DELETE FROM daybook WHERE slno = ? AND accode = ?", [$slno, $cbcode]);
        DB::delete("DELETE FROM daybook WHERE slno = ? AND accode IN ('TAX','SGST','CGST','IGST','AST')", [$slno]);

        // Discount entry
        if ($discount != 0) {
            DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                VALUES (?, 'DISC', ?, ?, ?, ?)", [$slno, -$discount, $control, $tdate, $opaccode]);
        }

        // Tax entries
        if ($tax != 0) {
            if ($cst == 'Y') {
                // IGST
                DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                    VALUES (?, 'IGST', ?, ?, ?, ?)", [$slno, $tax, $control, $tdate, $opaccode]);
            } else {
                // SGST + CGST
                DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                    VALUES (?, 'SGST', ?, ?, ?, ?)", [$slno, $tax / 2, $control, $tdate, $opaccode]);
                DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                    VALUES (?, 'CGST', ?, ?, ?, ?)", [$slno, $tax / 2, $control, $tdate, $opaccode]);
            }

            if ($ast > 0) {
                DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                    VALUES (?, 'AST', ?, ?, ?, ?)", [$slno, $ast, $control, $tdate, $opaccode]);
            }
        }

        // Cash entry
        $cashAmt = $ramt - $ccamt - $chqamt;
        if ($cashAmt != 0) {
            DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                VALUES (?, 'CASH', ?, ?, ?, ?)", [$slno, -$cashAmt, $control, $tdate, $opaccode]);
        }

        // Card/Bank entry
        if ($ccamt != 0) {
            DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                VALUES (?, ?, ?, ?, ?, ?)", [$slno, $cbcode, -$ccamt, $control, $tdate, $opaccode]);
        }

        // Customer account entries
        if ($custcode != '') {
            if ($netamt != 0) {
                DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                    VALUES (?, ?, ?, ?, ?, ?)", [$slno, $custcode, -$netamt, $control, $tdate, $opaccode]);
            }
            if ($ramt != 0) {
                DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                    VALUES (?, ?, ?, ?, ?, ?)", [$slno, $custcode, $ramt, $control, $tdate, $opaccode]);
            }

            // Order advance adjustments
            if ($orderno != '') {
                $order = DB::selectOne("SELECT eamt, sretamt, ichadv, iexadv, isradv, custcode
                    FROM orderm WHERE ordno = ?", [$orderno]);
                if ($order && $order->custcode == $custcode) {
                    $adv = (float) ($bill->advance ?? 0);
                    if ($adv != 0 && ($order->ichadv ?? 0) == 1) {
                        DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                            VALUES (?, ?, ?, ?, ?, ?)", [$slno, $custcode, -$adv, $control, $tdate, $opaccode]);
                    }
                }
            }
        }

        // Sales return tax entries
        $srDisc = 0; $srTax = 0; $srAst = 0;
        $srRow = DB::selectOne("SELECT discount, staxamt, astamt FROM salesrm WHERE slno = ?", [$slno]);
        if ($srRow) {
            $srTax = (float) ($srRow->staxamt ?? 0);
            $srAst = (float) ($srRow->astamt ?? 0);
        }

        if ($srTax > 0) {
            if ($cst == 'Y') {
                DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                    VALUES (?, 'IGST', ?, ?, ?, ?)", [$slno, -$srTax, $control, $tdate, $opaccode]);
            } else {
                DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                    VALUES (?, 'SGST', ?, ?, ?, ?)", [$slno, -$srTax / 2, $control, $tdate, $opaccode]);
                DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                    VALUES (?, 'CGST', ?, ?, ?, ?)", [$slno, -$srTax / 2, $control, $tdate, $opaccode]);
            }
        }

        if ($srAst > 0) {
            DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                VALUES (?, 'AST', ?, ?, ?, ?)", [$slno, -$srAst, $control, $tdate, $opaccode]);
        }

        // Round-off entry
        $dbSum = DB::selectOne("SELECT COALESCE(SUM(amount),0) as v FROM daybook WHERE slno = ?", [$slno]);
        $roundAmt = (float) ($dbSum->v ?? 0);
        if ($roundAmt != 0) {
            DB::insert("INSERT INTO daybook (slno, accode, amount, control, tdate, opaccode)
                VALUES (?, 'ROUND', ?, ?, ?, 'RS')", [$slno, -$roundAmt, $control, $tdate]);
        }
    }
}
