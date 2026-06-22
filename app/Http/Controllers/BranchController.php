<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class BranchController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('branch.index');
    }

    public function transfer(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('branch.transfer', [
            'today' => date('Y-m-d'),
        ]);
    }

    public function report(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('branch.report', [
            'dateFrom' => $request->query('date1', date('Y-m-01')),
            'dateTo'   => $request->query('date2', date('Y-m-d')),
        ]);
    }

    // ── Branch Master API ─────────────────────────────────────────────────

    public function list(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        try {
            $rows = DB::table('branches')->orderByDesc('is_head_office')->orderBy('name')->get();
            return response()->json(['ok' => true, 'rows' => $rows]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function saveBranch(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $id   = (int) $request->input('id', 0);
        $code = strtoupper(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));

        if ($code === '' || $name === '') {
            return response()->json(['ok' => false, 'message' => 'Branch code and name required']);
        }

        try {
            $data = [
                'code'           => $code,
                'name'           => $name,
                'address'        => trim((string) $request->input('address', '')),
                'city'           => trim((string) $request->input('city', '')),
                'phone'          => trim((string) $request->input('phone', '')),
                'email'          => trim((string) $request->input('email', '')),
                'gstin'          => trim((string) $request->input('gstin', '')),
                'db_host'        => trim((string) $request->input('db_host', '')),
                'db_name'        => trim((string) $request->input('db_name', '')),
                'db_user'        => trim((string) $request->input('db_user', '')),
                'db_pass'        => trim((string) $request->input('db_pass', '')),
                'is_head_office' => (bool) $request->input('is_head_office', false),
                'is_active'      => (bool) $request->input('is_active', true),
                'updated_at'     => now(),
            ];

            if ($id > 0) {
                DB::table('branches')->where('id', $id)->update($data);
            } else {
                $data['created_at'] = now();
                $id = DB::table('branches')->insertGetId($data);
            }

            return response()->json(['ok' => true, 'id' => $id]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function deleteBranch(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $id = (int) $request->input('id', 0);
        try {
            DB::table('branches')->delete($id);
            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    // ── Inter-Branch Transfer API ─────────────────────────────────────────

    public function saveTransfer(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $fromBranch = trim((string) $request->input('from_branch', ''));
        $toBranch   = trim((string) $request->input('to_branch', ''));
        $tdate      = (string) $request->input('transfer_date', date('Y-m-d'));
        $items      = $request->input('items', []);

        if ($fromBranch === '' || $toBranch === '' || empty($items)) {
            return response()->json(['ok' => false, 'message' => 'From/To branch and items required']);
        }

        if ($fromBranch === $toBranch) {
            return response()->json(['ok' => false, 'message' => 'From and To branch cannot be same']);
        }

        try {
            $prefix = 'IBT' . date('ymd');
            $last   = DB::table('inter_branch_transfers')
                ->where('transfer_no', 'like', $prefix . '%')
                ->max('transfer_no');
            $seq    = $last ? ((int) substr($last, -3)) + 1 : 1;
            $tno    = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);

            $saved = 0;
            foreach ($items as $item) {
                DB::table('inter_branch_transfers')->insert([
                    'transfer_no'  => $tno . '-' . ($saved + 1),
                    'transfer_date'=> $tdate,
                    'from_branch'  => $fromBranch,
                    'to_branch'    => $toBranch,
                    'item_code'    => trim((string) ($item['item_code'] ?? '')),
                    'barcode'      => trim((string) ($item['barcode'] ?? '')),
                    'item_desc'    => trim((string) ($item['item_desc'] ?? '')),
                    'weight'       => (float) ($item['weight'] ?? 0),
                    'purity'       => (float) ($item['purity'] ?? 0),
                    'fine_weight'  => (float) ($item['fine_weight'] ?? 0),
                    'rate'         => (float) ($item['rate'] ?? 0),
                    'value'        => (float) ($item['value'] ?? 0),
                    'status'       => 'pending',
                    'notes'        => trim((string) ($item['notes'] ?? '')),
                    'created_by'   => (string) $request->session()->get('user_code', ''),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                $saved++;
            }

            return response()->json(['ok' => true, 'transfer_no' => $tno, 'items_saved' => $saved]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function receiveTransfer(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return response()->json(['ok' => false, 'message' => 'No transfers selected']);
        }

        try {
            DB::table('inter_branch_transfers')
                ->whereIn('id', $ids)
                ->update([
                    'status'        => 'received',
                    'received_date' => date('Y-m-d'),
                    'updated_at'    => now(),
                ]);
            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function transferReport(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $dateFrom = (string) $request->query('date1', date('Y-m-01'));
        $dateTo   = (string) $request->query('date2', date('Y-m-d'));
        $status   = (string) $request->query('status', 'all');

        try {
            $q = DB::table('inter_branch_transfers')
                ->whereBetween('transfer_date', [$dateFrom, $dateTo]);

            if ($status !== 'all') {
                $q->where('status', $status);
            }

            $rows   = $q->orderByDesc('transfer_date')->orderBy('transfer_no')->get();
            $totals = [
                'total_weight' => $rows->sum('weight'),
                'total_value'  => $rows->sum('value'),
                'count'        => $rows->count(),
            ];

            return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }
}
