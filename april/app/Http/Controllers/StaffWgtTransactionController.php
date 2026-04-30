<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffWgtTransactionController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('staff-wgt-transaction.index', [
            'title' => 'Staff Weight Transaction',
        ]);
    }

    // Get next document number
    public function nextDoc(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $counter = DB::table('generali')->where('code', 'STFWGTT')->first();
        $next = $counter ? ((int) $counter->cvalue + 1) : 1;
        $docno = 'ST' . str_pad($next, 5, '0', STR_PAD_LEFT);

        return response()->json(['ok' => true, 'docno' => $docno, 'counter' => $next]);
    }

    // Load salesman list
    public function loadSman(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $rows = DB::table('sman')
            ->select('code', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn($r) => ['code' => trim($r->code ?? ''), 'name' => trim($r->name ?? '')])
            ->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Lookup item by code
    public function lookupItem(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = trim($request->query('code', ''));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'Code required']);

        $item = DB::table('items')->where('code', $code)->first();
        if (!$item) return response()->json(['ok' => false, 'message' => 'Item not found']);

        $stkinnos = trim($item->stkinnos ?? 'N');
        $rtype = ($stkinnos === 'Y') ? 'Pc' : 'Gm';

        return response()->json([
            'ok'    => true,
            'code'  => trim($item->code ?? ''),
            'name'  => trim($item->name ?? ''),
            'rtype' => $rtype,
        ]);
    }

    // Search items for dropdown
    public function searchItems(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim($request->query('q', ''));
        if ($q === '') return response()->json(['ok' => true, 'rows' => []]);

        $rows = DB::table('items')
            ->where(function ($query) use ($q) {
                $query->where('code', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%");
            })
            ->select('code', 'name', 'stkinnos')
            ->limit(20)
            ->get()
            ->map(fn($r) => [
                'code'  => trim($r->code ?? ''),
                'name'  => trim($r->name ?? ''),
                'rtype' => (trim($r->stkinnos ?? 'N') === 'Y') ? 'Pc' : 'Gm',
            ])->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Save transaction
    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $docno  = trim($request->input('docno', ''));
        $tdate  = trim($request->input('tdate', ''));
        $staff  = trim($request->input('staff', ''));
        $ttype  = trim($request->input('ttype', ''));
        $route  = trim($request->input('route', ''));
        $items  = (array) $request->input('items', []);
        $editSlno = $request->input('editSlno', null); // If editing existing

        if ($tdate === '') return response()->json(['ok' => false, 'message' => 'Date required']);
        if ($staff === '') return response()->json(['ok' => false, 'message' => 'SMan required']);
        if ($ttype === '') return response()->json(['ok' => false, 'message' => 'Trans Type required']);
        if (empty($items)) return response()->json(['ok' => false, 'message' => 'No items to save']);

        DB::beginTransaction();
        try {
            // If editing, delete old records
            if ($editSlno) {
                DB::table('staffwgtd')->where('slno', $editSlno)->delete();
                DB::table('staffwgtm')->where('slno', $editSlno)->delete();
            }

            // Get next SERIALNO
            $currentSerial = (int) (DB::table('generali')->where('code', 'SERIALNO')->value('cvalue') ?? 0);
            $maxUsed = 0;
            foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
                if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                    $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
                }
            }
            $slno = max($currentSerial, $maxUsed) + 1;
            DB::table('generali')->updateOrInsert(['code' => 'SERIALNO'], ['cvalue' => $slno]);

            // Get/create STFWGTT counter for docno
            if (!$editSlno) {
                $counterRow = DB::table('generali')->where('code', 'STFWGTT')->first();
                if ($counterRow) {
                    $nextNum = (int) $counterRow->cvalue + 1;
                    DB::table('generali')->where('code', 'STFWGTT')->update(['cvalue' => $nextNum]);
                } else {
                    $nextNum = 1;
                    DB::table('generali')->insert(['code' => 'STFWGTT', 'cvalue' => 1]);
                }
                $docno = 'ST' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
            }

            // Insert master
            DB::table('staffwgtm')->insert([
                'slno'    => $slno,
                'tdate'   => $tdate,
                'docno'   => $docno,
                'staff'   => $staff,
                'ttype'   => $ttype,
                'route'   => $route,
                'control' => '',
            ]);

            // Insert detail items
            $sno = 0;
            foreach ($items as $item) {
                $code   = trim($item['code'] ?? '');
                if ($code === '') continue;

                $sno++;
                DB::table('staffwgtd')->insert([
                    'slno'   => $slno,
                    'code'   => $code,
                    'qty'    => (float) ($item['qty'] ?? 0),
                    'weight' => (float) ($item['weight'] ?? 0),
                    'stwgt'  => (float) ($item['stwgt'] ?? 0),
                    'rate'   => (float) ($item['rate'] ?? 0),
                    'rtype'  => trim($item['rtype'] ?? 'Gm'),
                    'sno'    => $sno,
                ]);
            }

            DB::commit();
            return response()->json([
                'ok'      => true,
                'message' => "Saved {$docno} with {$sno} items",
                'docno'   => $docno,
                'slno'    => $slno,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Get transaction by docno (for edit)
    public function get(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $docno = trim($request->query('docno', ''));
        if ($docno === '') return response()->json(['ok' => false, 'message' => 'Doc No required']);

        $master = DB::table('staffwgtm')->where('docno', $docno)->first();
        if (!$master) return response()->json(['ok' => false, 'message' => 'Document not found']);

        $details = DB::table('staffwgtd')
            ->where('slno', $master->slno)
            ->orderBy('sno')
            ->get();

        // Get item names
        $codes = $details->pluck('code')->unique()->filter()->all();
        $itemNames = [];
        if (!empty($codes)) {
            $itemNames = DB::table('items')
                ->whereIn('code', $codes)
                ->pluck('name', 'code')
                ->toArray();
        }

        // Get sman name
        $smanName = '';
        if ($master->staff) {
            $sman = DB::table('sman')->where('code', $master->staff)->first();
            $smanName = $sman ? trim($sman->name ?? '') : '';
        }

        $items = [];
        foreach ($details as $d) {
            $code = trim($d->code ?? '');
            $items[] = [
                'code'   => $code,
                'name'   => trim($itemNames[$code] ?? ''),
                'qty'    => (float) ($d->qty ?? 0),
                'weight' => (float) ($d->weight ?? 0),
                'stwgt'  => (float) ($d->stwgt ?? 0),
                'rate'   => (float) ($d->rate ?? 0),
                'rtype'  => trim($d->rtype ?? 'Gm'),
                'sno'    => (int) ($d->sno ?? 0),
            ];
        }

        return response()->json([
            'ok'       => true,
            'slno'     => $master->slno,
            'docno'    => trim($master->docno ?? ''),
            'tdate'    => $master->tdate ?? '',
            'staff'    => trim($master->staff ?? ''),
            'staffname'=> $smanName,
            'ttype'    => trim($master->ttype ?? ''),
            'route'    => trim($master->route ?? ''),
            'items'    => $items,
        ]);
    }

    // Search documents
    public function search(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim($request->query('q', ''));

        $query = DB::table('staffwgtm')
            ->leftJoin('sman', 'staffwgtm.staff', '=', 'sman.code')
            ->select('staffwgtm.slno', 'staffwgtm.docno', 'staffwgtm.tdate', 'staffwgtm.staff',
                     'staffwgtm.ttype', 'staffwgtm.route', 'sman.name as staffname');

        if ($q !== '') {
            $query->where(function ($qr) use ($q) {
                $qr->where('staffwgtm.docno', 'like', "%{$q}%")
                   ->orWhere('sman.name', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderByDesc('staffwgtm.slno')->limit(50)->get()
            ->map(fn($r) => [
                'slno'     => $r->slno,
                'docno'    => trim($r->docno ?? ''),
                'tdate'    => $r->tdate ?? '',
                'staff'    => trim($r->staff ?? ''),
                'staffname'=> trim($r->staffname ?? ''),
                'ttype'    => trim($r->ttype ?? ''),
                'route'    => trim($r->route ?? ''),
            ])->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Cancel (delete) transaction
    public function cancel(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $docno = trim($request->input('docno', ''));
        if ($docno === '') return response()->json(['ok' => false, 'message' => 'Doc No required']);

        $master = DB::table('staffwgtm')->where('docno', $docno)->first();
        if (!$master) return response()->json(['ok' => false, 'message' => 'Document not found']);

        DB::beginTransaction();
        try {
            DB::table('staffwgtd')->where('slno', $master->slno)->delete();
            DB::table('staffwgtm')->where('slno', $master->slno)->delete();

            DB::commit();
            return response()->json(['ok' => true, 'message' => "Cancelled {$docno}"]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
