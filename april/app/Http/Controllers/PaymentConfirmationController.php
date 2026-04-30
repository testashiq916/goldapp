<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PaymentConfirmationController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('accounts-payment-confirmation.index', [
            'title' => 'Payment Confirmation',
        ]);
    }

    // Load pending payment entries (control=4, VP vouchers, amount < 0)
    public function load(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $userId = $request->session()->get('user_code', '');

        // Check permission
        $allowed = DB::table('userd')
            ->where('code', $userId)
            ->where('menuitem', 'ALLOWPMNTCONFIRMATION')
            ->exists();

        $rows = DB::table('daybook')
            ->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno')
            ->where('daybook.control', 4)
            ->whereRaw("LEFT(daybookpart.vchno, 2) = 'VP'")
            ->where('daybook.amount', '<', 0)
            ->select(
                'daybookpart.vchno',
                'daybook.tdate',
                'daybook.accode',
                'daybook.opaccode',
                'daybook.amount',
                'daybookpart.particular',
                'daybookpart.chequeno',
                'daybook.slno'
            )
            ->orderBy('daybook.tdate')
            ->orderBy('daybook.slno')
            ->get()
            ->map(function ($r) {
                // Get account names
                $acname = '';
                $cbacname = '';
                $accode = trim($r->accode ?? '');
                $opaccode = trim($r->opaccode ?? '');

                if ($accode !== '') {
                    $acname = trim(DB::table('accountm')->where('accode', $accode)->value('name') ?? '');
                    if ($acname === '') {
                        $acname = trim(DB::table('clients')->where('code', $accode)->value('name') ?? '');
                    }
                }
                if ($opaccode !== '') {
                    $cbacname = trim(DB::table('accountm')->where('accode', $opaccode)->value('name') ?? '');
                    if ($cbacname === '') {
                        $cbacname = trim(DB::table('clients')->where('code', $opaccode)->value('name') ?? '');
                    }
                }

                return [
                    'slno'      => (int) $r->slno,
                    'vchno'     => trim($r->vchno ?? ''),
                    'tdate'     => $r->tdate,
                    'acname'    => $acname,
                    'cbacname'  => $cbacname,
                    'amount'    => round(abs((float) ($r->amount ?? 0)), 2),
                    'particular'=> trim($r->particular ?? ''),
                    'chequeno'  => trim($r->chequeno ?? ''),
                ];
            })
            ->values()->all();

        return response()->json([
            'ok'      => true,
            'rows'    => $rows,
            'allowed' => $allowed,
        ]);
    }

    // Confirm a payment entry — set control=1
    public function confirm(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $userId = $request->session()->get('user_code', '');
        $incharge = $request->session()->get('user_code', '');

        // Permission check
        $allowed = DB::table('userd')
            ->where('code', $userId)
            ->where('menuitem', 'ALLOWPMNTCONFIRMATION')
            ->exists();
        if (!$allowed) return response()->json(['ok' => false, 'message' => 'You are not allowed to update this module']);

        $slno  = (int) $request->input('slno', 0);
        $vchno = trim($request->input('vchno', ''));
        if ($slno <= 0) return response()->json(['ok' => false, 'message' => 'No entry selected']);

        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control < 1 || $control > 2) $control = 1;

        DB::beginTransaction();
        try {
            // Get tdate
            $tdate = DB::table('daybook')->where('slno', $slno)->max('tdate');

            // Update pdclist
            if ($this->hasTable('pdclist')) {
                DB::table('pdclist')->where('slno', $slno)->update(['control' => 1]);
            }

            // Update daybook control to 1
            DB::table('daybook')->where('slno', $slno)->update(['control' => 1]);

            // Insert audit log into delpart
            $logData = [
                'tdate'    => $tdate ?? date('Y-m-d'),
                'part'     => 'Payment Entry(' . trim($vchno) . ') Confirmed-' . ($tdate ?? date('Y-m-d')),
                'control'  => $control,
                'slno'     => $slno,
                'utype'    => 'C',
                'ttype'    => 'VH',
                'updtdate' => date('Y-m-d'),
                'updttime' => date('H:i:s'),
                'uid'      => $userId,
                'ic'       => $incharge,
            ];
            DB::table('delpart')->insert($logData);

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Entry confirmed: ' . $vchno]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Delete/cancel a payment entry — zero out amount, set control=1
    public function cancel(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $userId = $request->session()->get('user_code', '');
        $incharge = $request->session()->get('user_code', '');

        // Permission check
        $allowed = DB::table('userd')
            ->where('code', $userId)
            ->where('menuitem', 'ALLOWPMNTCONFIRMATION')
            ->exists();
        if (!$allowed) return response()->json(['ok' => false, 'message' => 'You are not allowed to update this module']);

        $slno  = (int) $request->input('slno', 0);
        $vchno = trim($request->input('vchno', ''));
        if ($slno <= 0) return response()->json(['ok' => false, 'message' => 'No entry selected']);

        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control < 1 || $control > 2) $control = 1;

        DB::beginTransaction();
        try {
            $tdate = DB::table('daybook')->where('slno', $slno)->max('tdate');

            // Zero out + set control=1
            if ($this->hasTable('pdclist')) {
                DB::table('pdclist')->where('slno', $slno)->update(['amount' => 0, 'control' => 1]);
            }
            DB::table('daybook')->where('slno', $slno)->update(['amount' => 0, 'control' => 1]);

            // Audit log
            $logData = [
                'tdate'    => $tdate ?? date('Y-m-d'),
                'part'     => 'Payment Entry(' . trim($vchno) . ') Cancelled -' . ($tdate ?? date('Y-m-d')),
                'control'  => $control,
                'slno'     => $slno,
                'utype'    => 'C',
                'ttype'    => 'VH',
                'updtdate' => date('Y-m-d'),
                'updttime' => date('H:i:s'),
                'uid'      => $userId,
                'ic'       => $incharge,
            ];
            DB::table('delpart')->insert($logData);

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Entry cancelled: ' . $vchno]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
