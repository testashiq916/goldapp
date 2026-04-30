<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseRegisterController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.purchase-register', [
            'title'  => 'Purchase Register',
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

        $incharges = [];
        if ($this->hasTable('incharge')) {
            $incharges = DB::table('incharge')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $counters = [];
        if ($this->hasTable('counter')) {
            $counters = DB::table('counter')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return response()->json(['ok' => true, 'salesmen' => $salesmen, 'incharges' => $incharges, 'counters' => $counters]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1    = (string) $request->query('date1', date('Y-m-d'));
        $date2    = (string) $request->query('date2', date('Y-m-d'));
        $rlevel   = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $reptype  = trim((string) $request->query('reptype', 'billwise'));
        $smcode   = trim((string) $request->query('smcode', ''));
        $suppcode = trim((string) $request->query('suppcode', ''));
        $itemcode = trim((string) $request->query('itemcode', ''));
        $ic       = trim((string) $request->query('ic', ''));
        $icon     = (int) $request->query('icon', 1);
        $counter  = trim((string) $request->query('counter', ''));
        $counteron = (int) $request->query('counteron', 1);
        $stktype  = trim((string) $request->query('stktype', ''));
        $pe       = trim((string) $request->query('pe', ''));
        $type     = trim((string) $request->query('type', ''));     // G/S/O/orn_Y/orn_N
        $type2    = trim((string) $request->query('type2', ''));    // D/P/G/S/O/C/W (dmdplt)

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('purchased') || !$this->hasTable('purchasem') || !$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $hasDmdplt  = Schema::hasColumn('items', 'dmdplt');
        $hasOrn     = Schema::hasColumn('items', 'ornament');
        $hasCounter = Schema::hasColumn('purchasem', 'counter');
        $hasBtype   = Schema::hasColumn('purchasem', 'billtype');
        $hasTouch   = Schema::hasColumn('purchased', 'touch');
        $hasMud     = Schema::hasColumn('purchased', 'mud');

        $q = DB::table('purchased as d')
            ->join('purchasem as m', 'm.slno', '=', 'd.slno')
            ->join('items as i', 'i.code', '=', 'd.code');

        if ($this->hasTable('sman') && Schema::hasColumn('sman', 'code')) {
            $q->leftJoin('sman as s', 'm.smcode', '=', 's.code');
        }

        $q = $q
            ->select(
                'm.slno', 'm.tdate', 'm.docno', 'm.suppcode', 'm.name as suppname',
                'm.billamt', 'm.pamt', 'm.addamt', 'm.eamt', 'm.pr', 'm.smcode', 'm.ic',
                'i.name as itemname', 'i.itype', 'i.code as itemcode',
                'd.code', 'd.rate', 'd.qty', 'd.weight', 'd.lesswgt',
                'd.stwgt', 'd.stprice', 'd.amount', 'd.stktype'
            );

        if ($this->hasTable('sman') && Schema::hasColumn('sman', 'name')) {
            $q->addSelect('s.name as smname');
        } else {
            $q->selectRaw("'' as smname");
        }

        if ($hasOrn)    $q->addSelect('i.ornament');   else $q->selectRaw("'' as ornament");
        if ($hasDmdplt) $q->addSelect('i.dmdplt');     else $q->selectRaw("'' as dmdplt");
        if ($hasCounter) $q->addSelect('m.counter');   else $q->selectRaw("'' as counter");
        if ($hasBtype)  $q->addSelect('m.billtype');   else $q->selectRaw("'' as billtype");
        if ($hasTouch)  $q->addSelect('d.touch');      else $q->selectRaw('0 as touch');
        if ($hasMud)    $q->addSelect('d.mud');        else $q->selectRaw('0 as mud');

        // Optional: purchased.name
        if (Schema::hasColumn('purchased', 'name')) {
            $q->addSelect('d.name as dname');
        } else {
            $q->selectRaw("'' as dname");
        }

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $q->where('m.control', '<=', $rlevel);

        // ── Filters ──
        if ($smcode !== '')   $q->where('m.smcode', $smcode);
        if ($suppcode !== '') $q->where('m.suppcode', $suppcode);
        if ($itemcode !== '') $q->where('i.code', $itemcode);
        if ($stktype !== '')  $q->where('d.stktype', $stktype);

        if ($pe === 'P')      $q->where('m.pr', 'P');
        elseif ($pe === 'E')  $q->where('m.pr', 'E');

        if ($ic !== '' && $icon) $q->where('m.ic', $ic);

        if ($counter !== '' && $counteron && $hasCounter) $q->where('m.counter', $counter);

        // Type filter (ornament/itype)
        if ($type !== '') {
            if ($type === 'G' || $type === 'S' || $type === 'O') {
                $q->where('i.itype', $type);
            } elseif ($type === 'orn_Y' && $hasOrn) {
                $q->where('i.ornament', 'Y');
            } elseif ($type === 'orn_N' && $hasOrn) {
                $q->where('i.ornament', 'N');
            }
        }

        // Type2 filter (dmdplt)
        if ($type2 !== '' && $hasDmdplt) {
            $q->where('i.dmdplt', $type2);
        }

        // Order by reptype
        if ($reptype === 'itemwise' || $reptype === 'smanwise_summary') {
            $q->orderBy('i.name')->orderBy('m.tdate')->orderBy('m.docno');
        } elseif ($reptype === 'smanwise') {
            $q->orderBy('m.smcode')->orderBy('i.name')->orderBy('m.tdate')->orderBy('m.docno');
        } else {
            $q->orderBy('m.tdate')->orderBy('m.slno');
        }

        $rows = $q->get()->map(function ($r) {
            $row = (array) $r;
            $row['smcode']  = trim((string) ($row['smcode'] ?? ''));
            $row['smname']  = trim((string) ($row['smname'] ?? ''));
            if ($row['smname'] === '') {
                $row['smname'] = $row['smcode'];
            }
            $row['qty']     = (int) ($row['qty'] ?? 0);
            $row['rate']    = (float) ($row['rate'] ?? 0);
            $row['weight']  = (float) ($row['weight'] ?? 0);
            $row['lesswgt'] = (float) ($row['lesswgt'] ?? 0);
            $row['stwgt']   = (float) ($row['stwgt'] ?? 0);
            $row['stprice'] = (float) ($row['stprice'] ?? 0);
            $row['amount']  = (float) ($row['amount'] ?? 0);
            $row['touch']   = (float) ($row['touch'] ?? 0);
            $row['mud']     = (float) ($row['mud'] ?? 0);
            $row['billamt'] = (float) ($row['billamt'] ?? 0);
            $row['pamt']    = (float) ($row['pamt'] ?? 0);
            $row['eamt']    = (float) ($row['eamt'] ?? 0);
            $row['addamt']  = (float) ($row['addamt'] ?? 0);

            // Computed columns
            $row['netwgt'] = round($row['weight'] - $row['lesswgt'] - $row['stwgt'], 3);

            // Touch profit: purewgt = netwgt * touch / 100, touchless = netwgt - purewgt
            $row['purewgt'] = ($row['touch'] > 0) ? round($row['netwgt'] * $row['touch'] / 100, 3) : 0;
            $row['avgpurity'] = ($row['netwgt'] > 0 && $row['touch'] > 0) ? round($row['touch'], 2) : 0;
            $row['touchless'] = round($row['netwgt'] - $row['purewgt'], 3);
            $row['touchprof'] = $row['touchless']; // touch profit = weight lost in conversion

            // Display name
            $dname = trim($row['dname'] ?? '');
            $row['displayname'] = ($dname !== '') ? $dname : ($row['itemname'] ?? '');
            $row['navigate_url'] = $this->purchaseNavigateUrl((string) ($row['docno'] ?? ''), (string) ($row['pr'] ?? ''));

            return $row;
        })->values()->all();

        if ($reptype === 'billwise') {
            $rows = collect($rows)
                ->groupBy('slno')
                ->map(function ($group) {
                    $first = $group->first();
                    $itemNames = $group->pluck('displayname')
                        ->map(fn ($name) => trim((string) $name))
                        ->filter()
                        ->unique()
                        ->values();

                    $totalWeight = (float) $group->sum('weight');
                    $totalAmount = (float) $group->sum('amount');

                    $first['displayname'] = $itemNames->count() <= 1
                        ? (string) ($itemNames->first() ?? '')
                        : 'MULTI ITEM';
                    $first['qty'] = (int) $group->sum('qty');
                    $first['weight'] = round($totalWeight, 3);
                    $first['stwgt'] = round((float) $group->sum('stwgt'), 3);
                    $first['lesswgt'] = round((float) $group->sum('lesswgt'), 3);
                    $first['netwgt'] = round((float) $group->sum('netwgt'), 3);
                    $first['amount'] = round($totalAmount, 2);
                    $first['purewgt'] = round((float) $group->sum('purewgt'), 3);
                    $first['touchless'] = round((float) $group->sum('touchless'), 3);
                    $first['touchprof'] = round((float) $group->sum('touchprof'), 3);
                    $first['mud'] = round((float) $group->sum('mud'), 3);
                    $first['rate'] = $totalWeight > 0 ? round($totalAmount / $totalWeight, 2) : 0.0;

                    return $first;
                })
                ->sortBy([
                    ['tdate', 'asc'],
                    ['slno', 'asc'],
                ])
                ->values()
                ->all();
        }

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows),
        ]);
    }

    private function purchaseNavigateUrl(string $docNo, string $pr): string
    {
        $docNo = trim($docNo);
        $pr = strtoupper(trim($pr));
        if ($docNo === '' || !in_array($pr, ['P', 'E'], true)) {
            return '';
        }

        return url('/purchase-bill/edit?' . http_build_query(['doc_no' => $docNo]));
    }

    private function buildTotals(array $rows): array
    {
        $t = ['count' => count($rows), 'qty' => 0, 'weight' => 0, 'lesswgt' => 0,
              'stwgt' => 0, 'stprice' => 0, 'netwgt' => 0, 'amount' => 0,
              'goldwgt' => 0, 'silverwgt' => 0, 'otherwgt' => 0,
              'billamt' => 0, 'pamt' => 0, 'eamt' => 0, 'addamt' => 0,
              'purewgt' => 0, 'touchless' => 0, 'touchprof' => 0,
              'billcount' => 0, 'mud' => 0];

        $seenSlno = [];
        foreach ($rows as $row) {
            $t['qty']       += (int) ($row['qty'] ?? 0);
            $t['weight']    += (float) ($row['weight'] ?? 0);
            $t['lesswgt']   += (float) ($row['lesswgt'] ?? 0);
            $t['stwgt']     += (float) ($row['stwgt'] ?? 0);
            $t['stprice']   += (float) ($row['stprice'] ?? 0);
            $t['netwgt']    += (float) ($row['netwgt'] ?? 0);
            $t['amount']    += (float) ($row['amount'] ?? 0);
            $t['purewgt']   += (float) ($row['purewgt'] ?? 0);
            $t['touchless'] += (float) ($row['touchless'] ?? 0);
            $t['touchprof'] += (float) ($row['touchprof'] ?? 0);
            $t['mud']       += (float) ($row['mud'] ?? 0);

            $itype = strtoupper(trim($row['itype'] ?? ''));
            if ($itype === 'G') $t['goldwgt'] += (float) ($row['weight'] ?? 0);
            elseif ($itype === 'S') $t['silverwgt'] += (float) ($row['weight'] ?? 0);
            else $t['otherwgt'] += (float) ($row['weight'] ?? 0);

            $slno = $row['slno'] ?? '';
            if (!isset($seenSlno[$slno])) {
                $seenSlno[$slno] = true;
                $t['billcount']++;
                $t['billamt'] += (float) ($row['billamt'] ?? 0);
                $t['pamt']    += (float) ($row['pamt'] ?? 0);
                $t['eamt']    += (float) ($row['eamt'] ?? 0);
                $t['addamt']  += (float) ($row['addamt'] ?? 0);
            }
        }

        return $t;
    }
}
