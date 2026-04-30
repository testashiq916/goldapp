<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroupAmtAllocationController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('accounts-group-amt-allocation.index', [
            'title' => 'Group Amt Allocation',
        ]);
    }

    // Get distinct group codes from accountm
    public function groups(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $rows = DB::table('accountm')
            ->whereNotNull('grcode')
            ->where('grcode', '!=', '')
            ->select('grcode')
            ->distinct()
            ->orderBy('grcode')
            ->get()
            ->map(fn($r) => trim($r->grcode))
            ->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Search accounts for the Debit/Credit A/c lookup
    public function searchAccount(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim($request->query('q', ''));
        if ($q === '') return response()->json(['ok' => true, 'rows' => []]);

        $rows = DB::table('accountm')
            ->where(function ($query) use ($q) {
                $query->where('accode', 'like', $q . '%')
                      ->orWhere('name', 'like', '%' . $q . '%');
            })
            ->select('accode', 'name')
            ->orderBy('accode')
            ->limit(30)
            ->get()
            ->map(fn($r) => ['code' => trim($r->accode ?? ''), 'name' => trim($r->name ?? '')])
            ->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Show — load accounts in group with balances
    public function show(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $grp   = strtoupper(trim($request->query('grp', '')));
        $tdate = $request->query('tdate', date('Y-m-d'));

        if ($grp === '') return response()->json(['ok' => false, 'message' => 'Group required']);

        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control < 1 || $control > 2) $control = 1;

        // Reset amount column for this group
        DB::table('accountm')
            ->whereRaw('UPPER(TRIM(grcode)) = ?', [$grp])
            ->update(['amount' => 0]);

        // Get accounts in group with daybook transaction sum
        $rows = DB::table('accountm')
            ->whereRaw('UPPER(TRIM(grcode)) = ?', [$grp])
            ->select('accountm.accode', 'accountm.name', 'accountm.opbal', 'accountm.opbalb',
                     'accountm.amount', 'accountm.aclink', 'accountm.acperc')
            ->orderBy('accountm.name')
            ->get()
            ->map(function ($r) use ($control, $tdate) {
                $accode = trim($r->accode ?? '');

                // Compute ttran = sum of daybook amounts for this account
                $ttran = (float) (DB::table('daybook')
                    ->whereRaw('UPPER(TRIM(accode)) = ?', [strtoupper($accode)])
                    ->where('control', '<=', $control)
                    ->where('tdate', '<=', $tdate)
                    ->sum('amount') ?? 0);

                $opbal = $control === 1 ? (float) ($r->opbal ?? 0) : (float) ($r->opbalb ?? 0);
                $balance = abs($opbal + $ttran);

                return [
                    'accode'  => $accode,
                    'name'    => trim($r->name ?? ''),
                    'aclink'  => trim($r->aclink ?? ''),
                    'acperc'  => (float) ($r->acperc ?? 0),
                    'balance' => round($balance, 2),
                    'amount'  => 0,
                ];
            })
            ->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Save defaults (aclink, acperc) back to accountm
    public function setDef(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $items = $request->input('items', []);

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $accode = strtoupper(trim($item['accode'] ?? ''));
                $aclink = trim($item['aclink'] ?? '');
                $acperc = (float) ($item['acperc'] ?? 0);

                if ($accode !== '') {
                    DB::table('accountm')
                        ->whereRaw('UPPER(TRIM(accode)) = ?', [$accode])
                        ->update([
                            'aclink' => $aclink,
                            'acperc' => $acperc,
                        ]);
                }
            }
            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Defaults saved']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Save — create journal entries
    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $tdate      = trim($request->input('tdate', ''));
        $particular = trim($request->input('particular', ''));
        $opac       = strtoupper(trim($request->input('opac', '')));
        $credited   = (bool) $request->input('credited', true);
        $items      = $request->input('items', []);

        if ($tdate === '') return response()->json(['ok' => false, 'message' => 'Date required']);
        if ($opac === '') return response()->json(['ok' => false, 'message' => 'Debit/Credit A/c required']);

        // Filter items with amount > 0
        $validItems = [];
        foreach ($items as $item) {
            $amt = (float) ($item['amount'] ?? 0);
            if ($amt > 0) {
                $validItems[] = $item;
            }
        }

        if (count($validItems) === 0) {
            return response()->json(['ok' => false, 'message' => 'No amounts to allocate']);
        }

        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control < 1 || $control > 2) $control = 1;
        $incharge = $request->session()->get('user_code', '');

        DB::beginTransaction();
        try {
            // Next SERIALNO
            $currentSerial = (int) (DB::table('generali')->where('code', 'SERIALNO')->value('cvalue') ?? 0);
            $maxUsed = 0;
            foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
                if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                    $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
                }
            }
            $lslno = max($currentSerial, $maxUsed) + 1;
            DB::table('generali')->updateOrInsert(['code' => 'SERIALNO'], ['cvalue' => $lslno]);

            // Generate journal voucher number
            if ($control === 1) {
                $counterRow = DB::table('generali')->where('code', 'VCHNOJB')->first();
                $lno = $counterRow ? (int) $counterRow->cvalue : 0;
                DB::table('generali')->where('code', 'VCHNOJB')->update(['cvalue' => DB::raw('cvalue + 1')]);
                $docno = 'JLB/' . str_pad($lno + 1, 5, '0', STR_PAD_LEFT);
            } else {
                $counterRow = DB::table('generali')->where('code', 'VCHNOJE')->first();
                $lno = $counterRow ? (int) $counterRow->cvalue : 0;
                DB::table('generali')->where('code', 'VCHNOJE')->update(['cvalue' => DB::raw('cvalue + 1')]);
                $docno = 'JLE/' . str_pad($lno + 1, 5, '0', STR_PAD_LEFT);
            }

            // Build particulars
            if ($particular === '') {
                $particular = 'By Journal entry ' . $docno;
            }
            $particular = substr($particular, 0, 40);

            // Insert daybookpart
            $dpData = [
                'slno'       => $lslno,
                'vchno'      => $docno,
                'particular' => $particular,
                'ic'         => $incharge,
            ];
            $dpCols = array_map('strtolower', $this->columnList('daybookpart'));
            if (in_array('uid', $dpCols)) $dpData['uid'] = $incharge;
            DB::table('daybookpart')->insert($dpData);

            // Insert daybook entries for each account
            $totalAmt = 0;
            $lastAcCode = '';
            foreach ($validItems as $item) {
                $accode = strtoupper(trim($item['accode'] ?? ''));
                $aclink = trim($item['aclink'] ?? '');
                $amt    = (float) ($item['amount'] ?? 0);

                // Use aclink if set, otherwise accode
                $useCode = ($aclink !== '') ? strtoupper($aclink) : $accode;
                $opaccode = ($aclink !== '') ? $aclink : '';

                $dbAmt = $credited ? $amt : -$amt;

                DB::table('daybook')->insert([
                    'slno'     => $lslno,
                    'tdate'    => $tdate,
                    'accode'   => $useCode,
                    'amount'   => $dbAmt,
                    'control'  => $control,
                    'opaccode' => $opaccode,
                ]);

                $totalAmt += $amt;
                $lastAcCode = $useCode;
            }

            // Opposite account entry (the Debit/Credit A/c)
            $opAmt = $credited ? -$totalAmt : $totalAmt;

            DB::table('daybook')->insert([
                'slno'     => $lslno,
                'tdate'    => $tdate,
                'accode'   => $opac,
                'amount'   => $opAmt,
                'control'  => $control,
                'opaccode' => $lastAcCode,
            ]);

            DB::commit();
            return response()->json([
                'ok'      => true,
                'message' => "Saved {$docno} — Total: " . number_format($totalAmt, 2),
                'docno'   => $docno,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
