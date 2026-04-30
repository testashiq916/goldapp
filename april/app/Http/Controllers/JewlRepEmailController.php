<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jewellery Transactions Report / Send Mail To Jewl
 * (PB: w_jewlrep_email / d_jewlrep_email)
 *
 * Detailed smith transactions for jewellery-type clients.
 * Shows items with weight, stone, touch, net wgt, stone price, mcharge.
 * Filterable by issued/received, jeweller code, doc no.
 */
class JewlRepEmailController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.jewl-rep-email', [
            'title'  => 'Jewellery Transactions Report',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) ($request->session()->get('gilevel', 1) ?: 1),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1  = (string) $request->query('date1', date('Y-m-d'));
        $date2  = (string) $request->query('date2', date('Y-m-d'));
        $code   = trim((string) $request->query('code', ''));
        $docno  = trim((string) $request->query('docno', ''));
        $type   = strtoupper(trim((string) $request->query('type', '')));   // I=Issued, R=Received, ''=All
        $rlevel = (int) $request->query('rlevel', (int) ($request->session()->get('gilevel', 1)));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('smithm') || !$this->hasTable('smithd') || !$this->hasTable('clients')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $hasClientsgs = $this->hasTable('clientsgs');
        $hasItems     = $this->hasTable('items');

        $query = DB::table('smithd as sd')
            ->join('smithm as sm', 'sm.slno', '=', 'sd.slno')
            ->join('clients as c', DB::raw('TRIM(c.code)'), '=', DB::raw('TRIM(sm.smithcode)'))
            ->where('sm.tdate', '>=', $date1)
            ->where('sm.tdate', '<=', $date2)
            ->where('sm.control', '<=', $rlevel)
            ->where('c.ctype', 'J')
            ->select([
                'sm.tdate',
                DB::raw('TRIM(sm.docno) as docno'),
                DB::raw('TRIM(sm.smithcode) as smithcode'),
                DB::raw("TRIM(COALESCE(c.name, '')) as clientname"),
                DB::raw('TRIM(sd.code) as itemcode'),
                DB::raw("TRIM(COALESCE(sd.name, '')) as itemname"),
                DB::raw('COALESCE(sd.bcode, 0) as bcode'),
                DB::raw('COALESCE(sd.qty, 0) as qty'),
                DB::raw('COALESCE(sd.weight, 0) as weight'),
                DB::raw('COALESCE(sd.stonewgt, 0) as stonewgt'),
                DB::raw('COALESCE(sd.wastage, 0) as wastage'),
                DB::raw('COALESCE(sd.touchwgt, 0) as touchwgt'),
                DB::raw('COALESCE(sd.touch, 0) as touch'),
                DB::raw('COALESCE(sd.stoneprice, 0) as stoneprice'),
                DB::raw('COALESCE(sd.mcharge, 0) as mcharge'),
                DB::raw("COALESCE(sd.givrec, '') as givrec"),
                'sm.slno',
            ]);

        // Get item name from items table if smithd.name is empty
        if ($hasItems) {
            $query->leftJoin('items as i', DB::raw('TRIM(i.code)'), '=', DB::raw('TRIM(sd.code)'));
            $query->addSelect(DB::raw("TRIM(COALESCE(i.name, '')) as citemname"));
        } else {
            $query->addSelect(DB::raw("'' as citemname"));
        }

        // Filter by givrec (Issued=G, Received=R)
        if ($type === 'I') {
            $query->where('sd.givrec', 'G');
        } elseif ($type === 'R') {
            $query->where('sd.givrec', 'R');
        }

        // Filter by jeweller code
        if ($code !== '') {
            $query->whereRaw('TRIM(sm.smithcode) = ?', [$code]);
        }

        // Filter by doc no
        if ($docno !== '') {
            $query->whereRaw('TRIM(sm.docno) = ?', [$docno]);
        }

        $query->orderBy('sm.tdate')->orderBy('sm.docno')->orderBy('sd.sno');

        $results = $query->get();

        $rows = [];
        $totQty = 0;
        $totWeight = 0;
        $totStoneWgt = 0;
        $totNetWgt = 0;
        $totStonePr = 0;
        $totMcharge = 0;
        $totTouch = 0;

        foreach ($results as $r) {
            $qty       = (int) $r->qty;
            $weight    = round((float) $r->weight, 3);
            $stonewgt  = round((float) $r->stonewgt, 3);
            $wastage   = round((float) $r->wastage, 3);
            $touchwgt  = round((float) $r->touchwgt, 3);
            $touch     = round((float) $r->touch, 2);
            $stoneprice = round((float) $r->stoneprice, 2);
            $mcharge   = round((float) $r->mcharge, 2);
            // PB: netwgt = weight + wastage - stonewgt - touchwgt
            $netwgt    = round($weight + $wastage - $stonewgt - $touchwgt, 3);

            $displayName = trim($r->itemname);
            if ($displayName === '' && isset($r->citemname)) {
                $displayName = trim($r->citemname);
            }

            $rows[] = [
                'tdate'      => $r->tdate,
                'docno'      => trim($r->docno),
                'smithcode'  => trim($r->smithcode),
                'clientname' => trim($r->clientname),
                'itemcode'   => trim($r->itemcode),
                'itemname'   => $displayName,
                'bcode'      => (int) $r->bcode ?: '',
                'qty'        => $qty,
                'weight'     => $weight,
                'stonewgt'   => $stonewgt,
                'touch'      => $touch,
                'netwgt'     => $netwgt,
                'stoneprice' => $stoneprice,
                'mcharge'    => $mcharge,
                'givrec'     => trim($r->givrec),
            ];

            $totQty      += $qty;
            $totWeight   += $weight;
            $totStoneWgt += $stonewgt;
            $totTouch    += $touch;
            $totNetWgt   += $netwgt;
            $totStonePr  += $stoneprice;
            $totMcharge  += $mcharge;
        }

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => [
                'count'      => count($rows),
                'qty'        => $totQty,
                'weight'     => round($totWeight, 3),
                'stonewgt'   => round($totStoneWgt, 3),
                'touch'      => round($totTouch, 2),
                'netwgt'     => round($totNetWgt, 3),
                'stoneprice' => round($totStonePr, 2),
                'mcharge'    => round($totMcharge, 2),
            ],
        ]);
    }

    /**
     * Lookup jeweller name and email for the toolbar.
     */
    public function lookup(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'No code'], 422);
        }

        $name  = '';
        $email = '';

        if ($this->hasTable('clients')) {
            $cl = DB::table('clients')
                ->whereRaw('TRIM(code) = ?', [$code])
                ->where('ctype', 'J')
                ->select('name')
                ->first();

            if (!$cl) {
                return response()->json(['ok' => false, 'message' => 'Invalid code or not Jewellery type']);
            }
            $name = trim($cl->name ?? '');
        }

        if ($this->hasTable('clientsgs')) {
            $gs = DB::table('clientsgs')
                ->whereRaw('TRIM(code) = ?', [$code])
                ->select('email')
                ->first();
            if ($gs) {
                $email = trim($gs->email ?? '');
            }
        }

        return response()->json([
            'ok'    => true,
            'name'  => $name,
            'email' => $email,
        ]);
    }
}
