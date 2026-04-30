<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KuriDetailsController extends Controller
{
    private int $gilevel = 1;

    // ─── View ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');

        // "scheme" or "kuri" mode based on query param
        $mode = strtolower(trim($request->query('mode', 'scheme')));
        if (!in_array($mode, ['scheme', 'kuri', 'deposit'])) $mode = 'scheme';

        $titles = [
            'scheme' => 'Scheme Party Details',
            'kuri'   => 'Kuri Party Details',
            'deposit'=> 'Deposit Party Details',
        ];

        $defaultGrp = [
            'scheme' => 'SCHM',
            'kuri'   => 'KC',
            'deposit'=> 'DEP',
        ];

        // Load lookup data
        $kuritypes = [];
        if ($this->hasTable('kuritype')) {
            $kuritypes = DB::table('kuritype')->orderBy('name')->get(['code', 'name'])->toArray();
        }

        $clientsGrps = [];
        if ($this->hasTable('clientsgrp')) {
            $clientsGrps = DB::table('clientsgrp')->orderBy('name')->get(['code', 'name'])->toArray();
        }

        $routes = [];
        if ($this->hasTable('clientsroute')) {
            $routes = DB::table('clientsroute')->orderBy('name')->get(['code', 'name'])->toArray();
        }

        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray();
        }

        $relations = ['Father','Mother','Son','Daughter','Brother','Sister','Husband','Wife','Others'];

        return view('kuri-details.index', [
            'title'       => $titles[$mode],
            'mode'        => $mode,
            'defaultGrp'  => $defaultGrp[$mode],
            'kuritypes'   => $kuritypes,
            'clientsGrps' => $clientsGrps,
            'routes'      => $routes,
            'salesmen'    => $salesmen,
            'relations'   => $relations,
        ]);
    }

    // ─── API: Search Customers ────────────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim((string) $request->input('q', ''));
        $grp = trim((string) $request->input('grp', ''));

        $query = DB::table('clients as c')
            ->leftJoin('clients_kuridet as k', 'k.code', '=', 'c.code')
            ->select(['c.code', 'c.name', 'c.mobile', 'c.city', 'c.telephone',
                       'k.kuritype', 'k.startdate', 'k.instnos', 'k.instamt', 'k.totamt',
                       'k.colntype', 'k.finished'])
            ->whereIn('c.ctype', ['C', 'B']);

        if ($grp !== '') {
            $query->where('c.grp', $grp);
        }

        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('c.code', 'like', "%$q%")
                   ->orWhere('c.name', 'like', "%$q%")
                   ->orWhere('c.mobile', 'like', "%$q%")
                   ->orWhere('c.telephone', 'like', "%$q%");
            });
        }

        $rows = $query->orderBy('c.name')->limit(100)->get();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // ─── API: Load Single Customer ────────────────────────────────────────────

    public function load(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'Code is required']);

        $client = DB::table('clients')->where('code', $code)->first();
        if (!$client) return response()->json(['ok' => false, 'message' => 'Customer not found']);

        $kuridet = null;
        if ($this->hasTable('clients_kuridet')) {
            $kuridet = DB::table('clients_kuridet')->where('code', $code)->first();
        }

        return response()->json([
            'ok'      => true,
            'client'  => $client,
            'kuridet' => $kuridet,
        ]);
    }

    // ─── API: Next Auto Code ──────────────────────────────────────────────────

    public function nextCode(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $grp = strtoupper(trim((string) $request->input('grp', 'SCHM')));

        // Find next code by max existing code in that group
        $maxCode = DB::table('clients')
            ->where('grp', $grp)
            ->orderByDesc('code')
            ->value('code');

        if ($maxCode) {
            // Try to increment numeric part
            if (preg_match('/^([A-Z]*)(\d+)$/', trim($maxCode), $m)) {
                $prefix = $m[1];
                $num = (int) $m[2] + 1;
                $nextCode = $prefix . str_pad($num, strlen($m[2]), '0', STR_PAD_LEFT);
            } else {
                $nextCode = '';
            }
        } else {
            $nextCode = $grp . '001';
        }

        return response()->json(['ok' => true, 'code' => $nextCode]);
    }

    // ─── API: Save Customer ──────────────────────────────────────────────────

    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $isEdit = $request->input('is_edit', false);
        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'Code is required']);

        $name     = strtoupper(trim((string) $request->input('name', '')));
        $addr1    = trim((string) $request->input('addr1', ''));
        $addr2    = trim((string) $request->input('addr2', ''));
        $addr3    = trim((string) $request->input('addr3', ''));
        $city     = strtoupper(trim((string) $request->input('city', '')));
        $phone    = trim((string) $request->input('telephone', ''));
        $mobile   = trim((string) $request->input('mobile', ''));
        $grp      = strtoupper(trim((string) $request->input('grp', '')));
        $route    = strtoupper(trim((string) $request->input('route', '')));
        $cocode   = strtoupper(trim((string) $request->input('cocode', '')));

        $opbalance    = (float) $request->input('opbalance', 0);
        $balType      = trim((string) $request->input('bal_type', 'To Give'));
        $opbalanceb   = (float) $request->input('opbalanceb', 0);
        $balTypeB     = trim((string) $request->input('bal_type_b', 'To Give'));
        $opweight     = (float) $request->input('opweight', 0);
        $wgtType      = trim((string) $request->input('wgt_type', 'To Give'));
        $display      = $request->input('display', true) ? 1 : 0;
        $removed      = $request->input('removed', false) ? 1 : 0;

        // Signed balances
        if ($balType === 'To Give') $opbalance = abs($opbalance);
        else $opbalance = -abs($opbalance);

        if ($balTypeB === 'To Give') $opbalanceb = abs($opbalanceb);
        else $opbalanceb = -abs($opbalanceb);

        if ($wgtType === 'To Give') $opweight = abs($opweight);
        else $opweight = -abs($opweight);

        $clientData = [
            'name'       => $name,
            'addr1'      => $addr1,
            'addr2'      => $addr2,
            'addr3'      => $addr3,
            'city'       => $city,
            'telephone'  => $phone,
            'mobile'     => $mobile,
            'grp'        => $grp,
            'route'      => $route,
            'cocode'     => $cocode,
            'opbalance'  => $opbalance,
            'opbalanceb' => $opbalanceb,
            'opweight'   => $opweight,
            'removed'    => $removed,
        ];

        $clientCols = array_map('strtolower', $this->columnList('clients'));

        // Only set fields that exist
        $filteredClient = [];
        foreach ($clientData as $k => $v) {
            if (in_array($k, $clientCols)) $filteredClient[$k] = $v;
        }

        if ($isEdit) {
            $exists = DB::table('clients')->where('code', $code)->exists();
            if (!$exists) return response()->json(['ok' => false, 'message' => 'Customer not found']);
            DB::table('clients')->where('code', $code)->update($filteredClient);
        } else {
            $exists = DB::table('clients')->where('code', $code)->exists();
            if ($exists) return response()->json(['ok' => false, 'message' => 'Code already exists']);
            $filteredClient['code'] = $code;
            $filteredClient['ctype'] = 'C';
            if (in_array('control', $clientCols)) $filteredClient['control'] = $this->gilevel;
            DB::table('clients')->insert($filteredClient);
        }

        // Save kuridet
        if ($this->hasTable('clients_kuridet')) {
            $kuriData = [
                'startdate'    => $request->input('startdate') ?: null,
                'instnos'      => (int) $request->input('instnos', 0),
                'instamt'      => (float) $request->input('instamt', 0),
                'totamt'       => (float) $request->input('totamt', 0),
                'bonus'        => (float) $request->input('bonus', 0),
                'kuritype'     => strtoupper(trim((string) $request->input('kuritype', ''))),
                'intrate'      => (float) $request->input('intrate', 0),
                'colntype'     => trim((string) $request->input('colntype', 'Monthly')),
                'colnagent'    => strtoupper(trim((string) $request->input('colnagent', ''))),
                'matdate'      => $request->input('matdate') ?: null,
                'wadate'       => $request->input('wadate') ?: null,
                'bdate'        => $request->input('bdate') ?: null,
                'nomname'      => trim((string) $request->input('nomname', '')),
                'nomaddr'      => trim((string) $request->input('nomaddr', '')),
                'nomrelation'  => trim((string) $request->input('nomrelation', '')),
                'showwgtdet'   => $request->input('showwgtdet', true) ? 'Y' : 'N',
                'collnopbal'   => (float) $request->input('collnopbal', 0),
                'custlinkac'   => strtoupper(trim((string) $request->input('custlinkac', ''))),
                'collnmaxamt'  => (float) $request->input('collnmaxamt', 0),
                'collnminamt'  => (float) $request->input('collnminamt', 0),
                'bankacno'     => trim((string) $request->input('bankacno', '')),
                'bankname'     => strtoupper(trim((string) $request->input('bankname', ''))),
                'bankifsc'     => strtoupper(trim((string) $request->input('bankifsc', ''))),
            ];

            $kuriCols = array_map('strtolower', $this->columnList('clients_kuridet'));
            $filteredKuri = [];
            foreach ($kuriData as $k => $v) {
                if (in_array($k, $kuriCols)) $filteredKuri[$k] = $v;
            }

            $kuriExists = DB::table('clients_kuridet')->where('code', $code)->exists();
            if ($kuriExists) {
                DB::table('clients_kuridet')->where('code', $code)->update($filteredKuri);
            } else {
                $filteredKuri['code'] = $code;
                DB::table('clients_kuridet')->insert($filteredKuri);
            }
        }

        return response()->json(['ok' => true, 'message' => ($isEdit ? 'Updated' : 'Saved') . ': ' . $code]);
    }

    // ─── API: Delete Customer ─────────────────────────────────────────────────

    public function delete(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'Code is required']);

        $exists = DB::table('clients')->where('code', $code)->exists();
        if (!$exists) return response()->json(['ok' => false, 'message' => 'Customer not found']);

        // Check if there are collections
        if ($this->hasTable('kuricolln')) {
            $hasCollections = DB::table('kuricolln')->where('code', $code)->exists();
            if ($hasCollections) {
                return response()->json(['ok' => false, 'message' => 'Cannot delete — customer has collection records']);
            }
        }

        DB::table('clients')->where('code', $code)->delete();
        if ($this->hasTable('clients_kuridet')) {
            DB::table('clients_kuridet')->where('code', $code)->delete();
        }

        return response()->json(['ok' => true, 'message' => 'Deleted: ' . $code]);
    }
}
