<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OrderReprintController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->session()->has('user_code'), 401);
        return view('order-reprint.index', ['title' => 'Order Reprint']);
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
            ->limit(80)
            ->get(['ordno', 'custname', 'tdate'])
            ->map(fn($r) => [
                'ordno' => trim((string) ($r->ordno ?? '')),
                'custname' => trim((string) ($r->custname ?? '')),
                'tdate' => (string) ($r->tdate ?? ''),
            ])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function resolve(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $docNo = strtoupper(trim((string) $request->query('doc_no', '')));
        if ($docNo === '') {
            return response()->json(['ok' => false, 'message' => 'Enter order no']);
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));

        if ($this->hasTable('orderm')) {
            $ord = DB::table('orderm')
                ->whereRaw('TRIM(ordno)=?', [$docNo])
                ->where('control', '<=', $gilevel)
                ->first(['ordno']);
            if ($ord) {
                return response()->json([
                    'ok' => true,
                    'type' => 'order',
                    'url' => url('/order-bill/print') . '?doc=' . urlencode(trim((string) $ord->ordno)),
                ]);
            }
        }

        if ($this->hasTable('salesm')) {
            $sale = DB::table('salesm')
                ->whereRaw('TRIM(billno)=?', [$docNo])
                ->where('control', '<=', $gilevel)
                ->first(['slno', 'billno']);
            if ($sale) {
                return response()->json([
                    'ok' => true,
                    'type' => 'sales',
                    'url' => url('/sales-bill-print') . '?slno=' . (int) ($sale->slno ?? 0),
                ]);
            }
        }

        return response()->json(['ok' => false, 'message' => 'This number does not exist...']);
    }
}
