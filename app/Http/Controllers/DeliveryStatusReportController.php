<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeliveryStatusReportController extends Controller
{
    private array $colCache = [];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.delivery-status', [
            'title'  => 'Delivery Status Report',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) $request->session()->get('gilevel', 1) ?: 1,
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $agents = [];
        if ($this->hasTable('accountm')) {
            $agents = DB::table('accountm')->select('accode as code', 'name')->orderBy('accode')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return response()->json(['ok' => true, 'salesmen' => $salesmen, 'agents' => $agents]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1    = (string) $request->query('date1', date('Y-m-d'));
        $date2    = (string) $request->query('date2', date('Y-m-d'));
        $rlevel   = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $custcode = trim((string) $request->query('custcode', ''));
        $cocode   = trim((string) $request->query('cocode', ''));
        $smcode   = trim((string) $request->query('smcode', ''));
        $agcode   = trim((string) $request->query('agcode', ''));
        $agon     = (int) $request->query('agon', 0);
        $reptype  = trim((string) $request->query('reptype', ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $q = DB::table('salesm as m')
            ->select(
                'm.slno', 'm.tdate', 'm.billno', 'm.custcode', 'm.custname',
                'm.billamt', 'm.eamt', 'm.sretamt', 'm.discount', 'm.smcode'
            );

        $q->addSelect(DB::raw($this->hasCol('salesm', 'dstatus')  ? 'm.dstatus'  : "'' as dstatus"));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'secwgt')   ? 'm.secwgt'   : '0 as secwgt'));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'secnote')  ? 'm.secnote'  : "'' as secnote"));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'agcode')   ? 'm.agcode'   : "'' as agcode"));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'cocode')   ? 'm.cocode'   : "'' as cocode"));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'advance')  ? 'm.advance'  : '0 as advance'));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'staxamt')  ? 'm.staxamt'  : '0 as staxamt'));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'round')    ? 'm.round'    : '0 as round'));

        // Gold weight subquery
        if ($this->hasTable('salesd') && $this->hasTable('items')) {
            $q->selectRaw("(SELECT COALESCE(SUM(sd.weight - sd.stonewgt), 0) FROM salesd sd JOIN items it ON it.code = sd.code WHERE sd.slno = m.slno AND it.itype = 'G') as goldwgt");
        } else {
            $q->selectRaw('0 as goldwgt');
        }

        $q->whereBetween('m.tdate', [$date1, $date2]);
        if ($this->hasCol('salesm', 'control')) $q->where('m.control', '<=', $rlevel);
        if ($this->hasCol('salesm', 'opbill'))  $q->where('m.opbill', '<>', 1);

        // Filters
        if ($custcode !== '') {
            $q->where('m.custcode', $custcode);
        }
        if ($cocode !== '' && $this->hasCol('salesm', 'cocode')) {
            $q->where('m.cocode', $cocode);
        }
        if ($smcode !== '') {
            $q->where('m.smcode', $smcode);
        }
        if ($agon && $agcode !== '' && $this->hasCol('salesm', 'agcode')) {
            $q->where('m.agcode', $agcode);
        }

        // RepType filter on dstatus
        if ($reptype !== '' && $this->hasCol('salesm', 'dstatus')) {
            switch ($reptype) {
                case 'delivered':
                    $q->where('m.dstatus', 'D');
                    break;
                case 'locker':
                    $q->where('m.dstatus', 'L');
                    break;
                case 'anamath':
                    $q->where('m.dstatus', 'A');
                    break;
                case 'pending':
                    $q->where(function ($qq) {
                        $qq->whereNull('m.dstatus')->orWhere('m.dstatus', '')->orWhere('m.dstatus', 'P');
                    });
                    break;
            }
        }

        $q->orderBy('m.tdate')->orderBy('m.slno');

        $rows = $q->get()->map(function ($r) {
            $row = (array) $r;
            $billamt  = (float) ($row['billamt'] ?? 0);
            $eamt     = (float) ($row['eamt'] ?? 0);
            $sretamt  = (float) ($row['sretamt'] ?? 0);
            $discount = (float) ($row['discount'] ?? 0);
            $staxamt  = (float) ($row['staxamt'] ?? 0);
            $round    = (float) ($row['round'] ?? 0);
            $advance  = (float) ($row['advance'] ?? 0);

            $row['netamt'] = ($billamt - $eamt - $sretamt) - $discount + $staxamt + $round - $advance;

            // Status label
            $ds = strtoupper(trim($row['dstatus'] ?? ''));
            $row['statuslabel'] = match ($ds) {
                'D' => 'Delivered',
                'L' => 'Locker',
                'A' => 'Anamath',
                default => 'Pending',
            };

            return $row;
        })->values()->all();

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows),
        ]);
    }

    public function updateStatus(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $slno    = (int) $request->input('slno');
        $dstatus = strtoupper(trim((string) $request->input('dstatus', '')));
        $secwgt  = (float) $request->input('secwgt', 0);
        $secnote = trim((string) $request->input('secnote', ''));

        if (!$slno) {
            return response()->json(['ok' => false, 'message' => 'Invalid slno'], 422);
        }

        if (!in_array($dstatus, ['D', 'L', 'A', 'P', ''])) {
            return response()->json(['ok' => false, 'message' => 'Invalid status'], 422);
        }

        $update = [];
        if ($this->hasCol('salesm', 'dstatus'))  $update['dstatus'] = $dstatus;
        if ($this->hasCol('salesm', 'secwgt'))    $update['secwgt']  = $secwgt;
        if ($this->hasCol('salesm', 'secnote'))   $update['secnote'] = $secnote;

        if (empty($update)) {
            return response()->json(['ok' => false, 'message' => 'No updatable columns'], 422);
        }

        DB::table('salesm')->where('slno', $slno)->update($update);

        return response()->json(['ok' => true]);
    }

    private function buildTotals(array $rows): array
    {
        $t = ['billamt' => 0, 'eamt' => 0, 'discount' => 0, 'netamt' => 0, 'goldwgt' => 0, 'count' => count($rows)];

        foreach ($rows as $row) {
            $t['billamt']  += (float) ($row['billamt'] ?? 0);
            $t['eamt']     += (float) ($row['eamt'] ?? 0);
            $t['discount'] += (float) ($row['discount'] ?? 0);
            $t['netamt']   += (float) ($row['netamt'] ?? 0);
            $t['goldwgt']  += (float) ($row['goldwgt'] ?? 0);
        }

        return $t;
    }

    private function hasCol(string $table, string $col): bool
    {
        $key = "$table.$col";
        if (!array_key_exists($key, $this->colCache)) {
            $this->colCache[$key] = Schema::hasColumn($table, $col);
        }
        return $this->colCache[$key];
    }
}
