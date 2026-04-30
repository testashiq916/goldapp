<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Smith List – directory of all goldsmiths/jewellers.
 * Reads from clients + clientsgs tables.
 */
class SmithListController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $groups = [];
        if ($this->hasTable('clientsgrp')) {
            $groups = DB::table('clientsgrp')->select('code', 'name')->orderBy('code')->get()
                ->map(fn ($r) => ['code' => trim($r->code ?? ''), 'name' => trim($r->name ?? '')])->all();
        }

        return view('reports.smith-list', [
            'title'  => 'Smith List',
            'groups' => $groups,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $ctype  = strtoupper(trim((string) $request->query('ctype', '')));
        $group  = trim((string) $request->query('group', ''));
        $search = trim((string) $request->query('search', ''));
        $metal  = strtoupper(trim((string) $request->query('metal', '')));

        if (!$this->hasTable('clients') || !$this->hasTable('clientsgs')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => ['count' => 0]]);
        }

        $cols = array_map('strtolower', $this->columnList('clients'));

        $query = DB::table('clients as c')
            ->join('clientsgs as gs', 'gs.code', '=', 'c.code')
            ->select([
                DB::raw('TRIM(c.code) as code'),
                DB::raw('TRIM(c.name) as name'),
                DB::raw('TRIM(c.grp) as grp'),
                DB::raw("TRIM(COALESCE(gs.ctype, c.ctype)) as ctype"),
                DB::raw('COALESCE(gs.opweight, 0) as opweight'),
                DB::raw('COALESCE(gs.opweightb, 0) as opweightb'),
                DB::raw('COALESCE(c.opbalance, 0) as opbalance'),
                DB::raw('COALESCE(c.opbalanceb, 0) as opbalanceb'),
                DB::raw("COALESCE(gs.silver, 'N') as silver"),
                DB::raw('COALESCE(gs.stocktouch, 0) as stocktouch'),
                DB::raw('COALESCE(gs.convtouch, 0) as convtouch'),
            ]);

        // Add optional columns if they exist
        if (in_array('addr1', $cols)) {
            $query->addSelect(DB::raw("TRIM(COALESCE(c.addr1, '')) as addr1"));
        } else {
            $query->addSelect(DB::raw("'' as addr1"));
        }
        if (in_array('addr2', $cols)) {
            $query->addSelect(DB::raw("TRIM(COALESCE(c.addr2, '')) as addr2"));
        } else {
            $query->addSelect(DB::raw("'' as addr2"));
        }
        if (in_array('city', $cols)) {
            $query->addSelect(DB::raw("TRIM(COALESCE(c.city, '')) as city"));
        } else {
            $query->addSelect(DB::raw("'' as city"));
        }
        if (in_array('mobile', $cols)) {
            $query->addSelect(DB::raw("TRIM(COALESCE(c.mobile, '')) as mobile"));
        } else {
            $query->addSelect(DB::raw("'' as mobile"));
        }
        if (in_array('telephone', $cols)) {
            $query->addSelect(DB::raw("TRIM(COALESCE(c.telephone, '')) as telephone"));
        } else {
            $query->addSelect(DB::raw("'' as telephone"));
        }
        if (in_array('email', $cols)) {
            $query->addSelect(DB::raw("TRIM(COALESCE(c.email, '')) as email"));
        } else {
            $query->addSelect(DB::raw("'' as email"));
        }
        if (in_array('panadhar', $cols)) {
            $query->addSelect(DB::raw("TRIM(COALESCE(c.panadhar, '')) as pan"));
        } else {
            $query->addSelect(DB::raw("'' as pan"));
        }

        // Filter by ctype
        if (in_array($ctype, ['G', 'J'], true)) {
            $query->where('gs.ctype', $ctype);
        } else {
            $query->where(function ($q) {
                $q->where('gs.ctype', 'G')->orWhere('gs.ctype', 'J');
            });
        }

        // Filter by metal
        if ($metal === 'G') {
            $query->where('gs.silver', 'N');
        } elseif ($metal === 'S') {
            $query->where('gs.silver', 'Y');
        }

        // Filter by group
        if ($group !== '') {
            $query->where('c.grp', $group);
        }

        // Search by name or code
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('c.name', 'like', '%' . $search . '%')
                  ->orWhere('c.code', 'like', '%' . $search . '%');
            });
        }

        $results = $query->orderBy('c.name')->get();
        $rows = [];

        foreach ($results as $r) {
            $addr = trim($r->addr1);
            if ($r->addr2) $addr .= ($addr ? ', ' : '') . trim($r->addr2);
            if ($r->city) $addr .= ($addr ? ', ' : '') . trim($r->city);

            $phone = trim($r->mobile);
            if (!$phone) $phone = trim($r->telephone);

            $rows[] = [
                'code'       => trim($r->code),
                'name'       => trim($r->name),
                'grp'        => trim($r->grp),
                'ctype'      => trim($r->ctype),
                'ctypelabel' => trim($r->ctype) === 'G' ? 'Goldsmith' : (trim($r->ctype) === 'J' ? 'Jewellery' : trim($r->ctype)),
                'silver'     => trim($r->silver),
                'metallabel' => trim($r->silver) === 'Y' ? 'Silver' : 'Gold',
                'opweight'   => round((float) $r->opweight, 3),
                'opweightb'  => round((float) $r->opweightb, 3),
                'opbalance'  => round((float) $r->opbalance, 2),
                'opbalanceb' => round((float) $r->opbalanceb, 2),
                'stocktouch' => round((float) $r->stocktouch, 2),
                'convtouch'  => round((float) $r->convtouch, 2),
                'address'    => $addr,
                'phone'      => $phone,
                'email'      => trim($r->email),
                'pan'        => trim($r->pan),
            ];
        }

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => ['count' => count($rows)],
        ]);
    }
}
