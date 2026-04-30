<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OrderCancelController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->session()->has('user_code'), 401);
        return view('order-cancel.index', ['title' => 'Order Cancellation']);
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('orderm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));

        $rows = DB::table('orderm')
            ->where('control', '<=', $gilevel)
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('ordno', 'like', "%{$q}%")
                      ->orWhere('custname', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('slno')
            ->limit(80)
            ->get(['ordno', 'custname', 'tdate', 'status'])
            ->map(fn($r) => [
                'ordno' => trim((string) ($r->ordno ?? '')),
                'custname' => trim((string) ($r->custname ?? '')),
                'tdate' => (string) ($r->tdate ?? ''),
                'status' => (int) ($r->status ?? 0),
            ])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function apply(Request $request, OrderBillController $orderBillController): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $request->merge([
            'doc_no' => strtoupper(trim((string) $request->input('doc_no', ''))),
            'reason' => trim((string) $request->input('reason', '')),
        ]);

        return $orderBillController->cancelBill($request);
    }
}
