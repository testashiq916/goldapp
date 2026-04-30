<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OrderRateFixController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->session()->has('user_code'), 401);

        return view('order-rate-fix.index', [
            'title' => 'Order Rate Fix',
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('orderm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $q = trim((string) $request->query('q', ''));

        $rows = DB::table('orderm')
            ->where('control', '<=', $gilevel)
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('ordno', 'like', "%{$q}%")
                      ->orWhere('custname', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('slno')
            ->limit(50)
            ->get(['ordno', 'custname', 'status', 'tdate'])
            ->map(fn($r) => [
                'ordno' => trim((string) ($r->ordno ?? '')),
                'custname' => trim((string) ($r->custname ?? '')),
                'status' => (int) ($r->status ?? 0),
                'tdate' => (string) ($r->tdate ?? ''),
            ])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function apply(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('orderm')) {
            return response()->json(['ok' => false, 'message' => 'Order table not found.'], 422);
        }

        $in = $request->validate([
            'ordno' => 'required|string|max:40',
            'rate' => 'required|numeric',
        ]);

        $ordno = strtoupper(trim((string) $in['ordno']));
        $rate = (float) $in['rate'];
        if ($rate <= 0) {
            return response()->json(['ok' => false, 'message' => 'Enter valid rate.'], 422);
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $order = DB::table('orderm')
            ->whereRaw('TRIM(ordno)=?', [$ordno])
            ->where('control', '<=', $gilevel)
            ->first(['slno', 'status', 'tdate']);

        if (!$order) {
            return response()->json(['ok' => false, 'message' => 'This Order number does not exist...'], 404);
        }

        if ((int) ($order->status ?? 0) === 2) {
            return response()->json(['ok' => false, 'message' => "This is not a pending order. You can't edit an order entry which is returned..."], 422);
        }

        $note = 'Rate Fixed :' . number_format($rate, 2, '.', '') . ' - Dt: ' . date('d/m/y');
        DB::table('orderm')->whereRaw('TRIM(ordno)=?', [$ordno])->update(['note' => $note]);

        return response()->json(['ok' => true, 'message' => 'Updated....', 'note' => $note]);
    }
}
