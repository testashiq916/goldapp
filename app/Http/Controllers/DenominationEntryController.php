<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DenominationEntryController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('accounts-denomination-entry.index', [
            'title' => 'Denomination Entry',
        ]);
    }

    // Load denominations + existing counts for a date + cash balance
    public function load(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $tdate = $request->query('tdate', date('Y-m-d'));

        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control < 1 || $control > 2) $control = 1;

        // Get denominations with existing counts
        $rows = DB::table('denom_master')
            ->orderBy('denom_master.code')
            ->get()
            ->map(function ($r) use ($tdate) {
                $code = trim($r->code ?? '');
                $nos = (int) (DB::table('denom_trans')
                    ->where('denom_code', $code)
                    ->where('tdate', $tdate)
                    ->sum('nos') ?? 0);

                return [
                    'code'   => $code,
                    'name'   => trim($r->name ?? ''),
                    'cvalue' => (float) ($r->cvalue ?? 0),
                    'nos'    => $nos,
                ];
            })
            ->values()->all();

        // Cash balance as of date: abs(opbal + sum(daybook.amount) for CASH account)
        $cashAc = DB::table('accountm')->where('accode', 'CASH')->first();
        $opbal = 0.0;
        if ($cashAc) {
            $opbal = $control === 1 ? (float) ($cashAc->opbal ?? 0) : (float) ($cashAc->opbalb ?? 0);
        }

        $daySum = (float) (DB::table('daybook')
            ->where('accode', 'CASH')
            ->where('control', '<=', $control)
            ->where('tdate', '<=', $tdate)
            ->sum('amount') ?? 0);

        $cashBal = abs($opbal + $daySum);

        return response()->json([
            'ok'   => true,
            'rows' => $rows,
            'cash' => round($cashBal, 2),
        ]);
    }

    // Save denomination counts
    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $tdate = trim($request->input('tdate', ''));
        $items = $request->input('items', []);

        if ($tdate === '') return response()->json(['ok' => false, 'message' => 'Date required']);

        DB::beginTransaction();
        try {
            // Delete existing entries for this date
            DB::table('denom_trans')->where('tdate', $tdate)->delete();

            // Insert rows with nos > 0
            foreach ($items as $item) {
                $nos = (int) ($item['nos'] ?? 0);
                if ($nos > 0) {
                    $cvalue = (float) ($item['cvalue'] ?? 0);
                    DB::table('denom_trans')->insert([
                        'tdate'      => $tdate,
                        'denom_code' => trim($item['code'] ?? ''),
                        'nos'        => $nos,
                        'totalvalue' => round($cvalue * $nos, 2),
                    ]);
                }
            }

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Saved']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
