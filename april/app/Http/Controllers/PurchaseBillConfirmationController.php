<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PurchaseBillConfirmationController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('purchase-bill-confirmation.index');
    }

    /**
     * Load pending purchase bills (control = 4, pr = 'P')
     */
    public function load(Request $request): JsonResponse
    {
        $bills = DB::select("
            SELECT m.slno, m.docno, m.tdate, m.billno, m.name, m.suppcode,
                   m.billamt, m.netamt, m.pamt, m.dmd, m.control,
                   (m.netamt - m.pamt) as balance
            FROM purchasem m
            WHERE m.control = 4 AND m.pr = 'P'
            ORDER BY m.tdate ASC, m.slno ASC, m.docno ASC
        ");

        return response()->json(['ok' => true, 'bills' => $bills]);
    }

    /**
     * Confirm a purchase bill — set control = 1 across all related tables
     */
    public function confirm(Request $request): JsonResponse
    {
        $slno = (int) $request->input('slno');
        $bill = DB::selectOne("SELECT * FROM purchasem WHERE slno = ?", [$slno]);
        if (!$bill) {
            return response()->json(['ok' => false, 'message' => 'Bill not found']);
        }

        $userId = $request->session()->get('user_code', '');
        $tdate = $bill->tdate;
        $docno = trim($bill->docno ?? '');
        $billno = trim($bill->billno ?? '');

        // Update control = 1 on all related tables
        DB::update("UPDATE purchasem SET control = 1 WHERE slno = ?", [$slno]);
        DB::update("UPDATE purchaserm SET control = 1 WHERE slno = ?", [$slno]);
        DB::update("UPDATE advafter SET control = 1 WHERE slno = ?", [$slno]);
        DB::update("UPDATE pdclist SET control = 1 WHERE slno = ?", [$slno]);
        DB::update("UPDATE stkandprofit SET control = 1 WHERE slno = ?", [$slno]);
        DB::update("UPDATE daybook SET control = 1 WHERE slno = ?", [$slno]);

        // Log confirmation
        $logPart = "Purchase Entry({$billno} - {$docno}) Confirmed-{$tdate}";
        DB::insert("INSERT INTO delpart (tdate, part, control, slno, utype, ttype, updtdate, updttime, uid, ic)
            VALUES (?, ?, ?, ?, 'C', 'P', ?, ?, ?, ?)", [
            $tdate, $logPart, $this->gilevel, $slno,
            date('Y-m-d'), date('H:i:s'), $userId, $userId
        ]);

        DB::statement("COMMIT");

        return response()->json(['ok' => true, 'message' => "Confirmed: {$docno}"]);
    }

    /**
     * Delete/Cancel a purchase bill — reverse stock and delete records
     */
    public function delete(Request $request): JsonResponse
    {
        $slno = (int) $request->input('slno');
        $bill = DB::selectOne("SELECT * FROM purchasem WHERE slno = ?", [$slno]);
        if (!$bill) {
            return response()->json(['ok' => false, 'message' => 'Bill not found']);
        }

        $userId = $request->session()->get('user_code', '');

        // Reverse purchase items stock
        $items = DB::select("
            SELECT d.code, d.qty, d.weight, d.cost, d.stktype, m.control
            FROM purchased d JOIN purchasem m ON m.slno = d.slno
            WHERE d.slno = ?
        ", [$slno]);

        foreach ($items as $pi) {
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
        $tdate = $bill->tdate;
        $docno = trim($bill->docno ?? '');
        $netamt = (float) ($bill->netamt ?? 0);

        // Delete all records
        DB::delete("DELETE FROM purchasem WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM purchased WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM purchaserm WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM purchaserd WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM daybook WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM daybookpart WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM stkandprofit WHERE slno = ?", [$slno]);
        DB::delete("DELETE FROM oglist WHERE slno = ?", [$slno]);

        // Log deletion
        $logPart = "Purchase Entry({$docno}) - NetAmt " . number_format($netamt, 2) . " Canceled -{$tdate}";
        DB::insert("INSERT INTO delpart (tdate, part, control, slno, utype, ttype, updtdate, updttime, uid, ic)
            VALUES (?, ?, ?, ?, 'C', 'P', ?, ?, ?, ?)", [
            $tdate, $logPart, $this->gilevel, $slno,
            date('Y-m-d'), date('H:i:s'), $userId, $userId
        ]);

        DB::statement("COMMIT");

        return response()->json(['ok' => true, 'message' => "Cancelled: {$docno}"]);
    }

    /**
     * View purchase bill details
     */
    public function view(Request $request): JsonResponse
    {
        $slno = (int) $request->input('slno');
        $bill = DB::selectOne("SELECT * FROM purchasem WHERE slno = ?", [$slno]);
        if (!$bill) {
            return response()->json(['ok' => false, 'message' => 'Bill not found']);
        }

        $items = DB::select("
            SELECT d.*, i.name as itemname
            FROM purchased d LEFT JOIN items i ON i.code = d.code
            WHERE d.slno = ? ORDER BY d.sno
        ", [$slno]);

        return response()->json(['ok' => true, 'bill' => $bill, 'items' => $items]);
    }
}
