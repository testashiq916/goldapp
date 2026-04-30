<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderBlockController extends Controller
{
    // ─── View ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('order-block.index', [
            'title' => 'Block/Unblock an Order',
        ]);
    }

    // ─── API: Search orders (Help button) ────────────────────────────────────

    public function searchOrders(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q       = trim($request->query('q', ''));
        $gilevel = (int) ($request->session()->get('gilevel', 1));

        $query = DB::table('orderm')
            ->select('slno', 'ordno', 'tdate', 'custcode', 'custname', 'billamt', 'status', 'blocked', 'duedate')
            ->where('control', '<=', $gilevel);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('ordno', 'like', "%$q%")
                   ->orWhere('custname', 'like', "%$q%")
                   ->orWhere('custcode', 'like', "%$q%");
            });
        }

        $rows = $query->orderByDesc('slno')->limit(50)->get()->map(function ($r) {
            return [
                'slno'     => (int) $r->slno,
                'ordno'    => trim($r->ordno ?? ''),
                'tdate'    => $r->tdate ?? '',
                'custcode' => trim($r->custcode ?? ''),
                'custname' => trim($r->custname ?? ''),
                'billamt'  => (float) ($r->billamt ?? 0),
                'status'   => (int) ($r->status ?? 0),
                'blocked'  => strtoupper(trim($r->blocked ?? 'N')) === 'Y' ? 'Y' : 'N',
                'duedate'  => $r->duedate ?? '',
            ];
        });

        return response()->json(['ok' => true, 'orders' => $rows]);
    }

    // ─── API: Get order info by ordno ────────────────────────────────────────

    public function getOrder(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $ordno   = strtoupper(trim($request->query('ordno', '')));
        $gilevel = (int) ($request->session()->get('gilevel', 1));

        if ($ordno === '') {
            return response()->json(['ok' => false, 'message' => 'Order number required']);
        }

        // Check for prefix (PB: sfc2 = DP prefix, strip if matches)
        $dpPrefix = '';
        if ($this->hasTable('generals')) {
            $dpPrefix = strtoupper(trim(DB::table('generals')->where('code', 'DP')->value('cvalue') ?? ''));
        }

        $searchNo = $ordno;
        if ($dpPrefix !== '' && strpos($ordno, $dpPrefix) === 0) {
            $searchNo = substr($ordno, strlen($dpPrefix));
        }

        $row = DB::table('orderm')
            ->select('slno', 'ordno', 'tdate', 'custcode', 'custname', 'billamt', 'status', 'blocked', 'control', 'duedate')
            ->where('control', '<=', $gilevel)
            ->where(DB::raw('TRIM(ordno)'), $searchNo)
            ->first();

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'This Order number does not exist']);
        }

        if ((int) $row->status === 2) {
            return response()->json([
                'ok'      => false,
                'message' => "This is not a pending order.\nYou can't block/unblock an order which is returned.",
            ]);
        }

        return response()->json([
            'ok'       => true,
            'slno'     => (int) $row->slno,
            'ordno'    => trim($row->ordno),
            'tdate'    => $row->tdate ?? '',
            'custcode' => trim($row->custcode ?? ''),
            'custname' => trim($row->custname ?? ''),
            'billamt'  => (float) ($row->billamt ?? 0),
            'status'   => (int) ($row->status ?? 0),
            'blocked'  => strtoupper(trim($row->blocked ?? 'N')) === 'Y' ? 'Y' : 'N',
            'duedate'  => $row->duedate ?? '',
        ]);
    }

    // ─── API: Block/Unblock ──────────────────────────────────────────────────

    public function toggleBlock(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $ordno   = strtoupper(trim($request->input('ordno', '')));
        $block   = $request->input('block') ? 'Y' : 'N';
        $gilevel = (int) ($request->session()->get('gilevel', 1));

        if ($ordno === '') {
            return response()->json(['ok' => false, 'message' => 'Order number required']);
        }

        // Strip prefix if present
        $dpPrefix = '';
        if ($this->hasTable('generals')) {
            $dpPrefix = strtoupper(trim(DB::table('generals')->where('code', 'DP')->value('cvalue') ?? ''));
        }
        $searchNo = $ordno;
        if ($dpPrefix !== '' && strpos($ordno, $dpPrefix) === 0) {
            $searchNo = substr($ordno, strlen($dpPrefix));
        }

        $row = DB::table('orderm')
            ->select('slno', 'status', 'tdate')
            ->where('control', '<=', $gilevel)
            ->where(DB::raw('TRIM(ordno)'), $searchNo)
            ->first();

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'This Order number does not exist']);
        }

        if ((int) $row->status === 2) {
            return response()->json([
                'ok'      => false,
                'message' => "This is not a pending order.\nYou can't block/unblock a returned order.",
            ]);
        }

        DB::table('orderm')
            ->where(DB::raw('TRIM(ordno)'), $searchNo)
            ->update(['blocked' => $block]);

        $msg = $block === 'Y' ? 'Order Blocked.' : 'Block Removed.';

        return response()->json(['ok' => true, 'message' => $msg, 'blocked' => $block]);
    }
}
