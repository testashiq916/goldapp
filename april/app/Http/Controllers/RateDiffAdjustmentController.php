<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RateDiffAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('accounts-rate-diff-adjustment.index', [
            'title' => 'Rate Diff Adjustment',
        ]);
    }

    // Init — get gold rate
    public function init(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $grate = (float) (DB::table('generald')->where('code', 'GRATE')->value('cvalue') ?? 0);
        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control < 1 || $control > 2) $control = 1;

        return response()->json([
            'ok'      => true,
            'grate'   => $grate,
            'control' => $control,
        ]);
    }

    // Load party — get balance, weight balance
    public function loadParty(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = strtoupper(trim($request->query('code', '')));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'Code required']);

        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control < 1 || $control > 2) $control = 1;

        $client = DB::table('clients')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->first();
        if (!$client) return response()->json(['ok' => false, 'message' => 'This code does not exist']);

        $name = trim($client->name ?? '');

        // Opening balance
        $opbal = 0.0;
        $opwgt = 0.0;
        if ($control === 1) {
            $opbal = (float) ($client->opbalance ?? 0);
        } else {
            $opbal = (float) ($client->opbalanceb ?? 0);
        }
        $opwgt = (float) ($client->opweight ?? 0);

        // Daybook sum for balance
        $daySum = 0.0;
        if ($this->hasTable('daybook') && Schema::hasColumn('daybook', 'control')) {
            $daySum = (float) (DB::table('daybook')
                ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
                ->where('control', '<=', $control)
                ->sum('amount') ?? 0);
        }

        // Weight balance from daybookratewgt
        $dwgt = 0.0;
        if ($this->hasTable('daybookratewgt')) {
            $dwgt = (float) (DB::table('daybookratewgt')
                ->join('daybook', 'daybookratewgt.slno', '=', 'daybook.slno')
                ->whereRaw('UPPER(TRIM(daybook.accode)) = ?', [$code])
                ->where('daybook.control', '<=', $control)
                ->selectRaw('SUM(daybook.amount / daybookratewgt.rate) as wgt')
                ->value('wgt') ?? 0);
        }

        $balance = -($opbal + $daySum);
        $wgtbal = -($opwgt + $dwgt);

        return response()->json([
            'ok'      => true,
            'name'    => $name,
            'balance' => round($balance, 2),
            'wgtbal'  => round($wgtbal, 3),
        ]);
    }

    // Search party
    public function searchParty(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim($request->query('q', ''));
        if ($q === '') return response()->json(['ok' => true, 'rows' => []]);

        $rows = DB::table('clients')
            ->where(function ($query) use ($q) {
                $query->where('code', 'like', $q . '%')
                      ->orWhere('name', 'like', '%' . $q . '%');
            })
            ->select('code', 'name')
            ->orderBy('code')
            ->limit(30)
            ->get()
            ->map(fn($r) => ['code' => trim($r->code ?? ''), 'name' => trim($r->name ?? '')])
            ->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Load bill details from salesm
    public function loadBill(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billno = strtoupper(trim($request->query('billno', '')));
        $code   = strtoupper(trim($request->query('code', '')));
        $tdate  = $request->query('tdate', date('Y-m-d'));

        if ($billno === '' || $code === '') {
            return response()->json(['ok' => false, 'message' => 'Bill No and Party required']);
        }

        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control < 1 || $control > 2) $control = 1;

        // Clean # prefix for semi
        $cleanBillno = $billno;
        if (str_starts_with($billno, '#') && $control > 1) {
            $cleanBillno = substr($billno, 1);
        }

        $bill = DB::table('salesm')
            ->whereRaw('UPPER(TRIM(billno)) = ?', [$cleanBillno])
            ->whereRaw('UPPER(TRIM(custcode)) = ?', [$code])
            ->where('control', '<=', $control)
            ->first();

        if (!$bill) {
            return response()->json(['ok' => false, 'message' => 'This Bill number does not exist']);
        }

        $slno   = (int) $bill->slno;
        $netamt = (float) ($bill->netamt ?? 0);
        $ramt   = (float) ($bill->ramt ?? 0);
        $grate  = (float) ($bill->grate ?? 0);

        // Get weight from salesd
        $weight = 0.0;
        if ($this->hasTable('salesd')) {
            $weight = (float) (DB::table('salesd')
                ->where('slno', $slno)
                ->selectRaw('SUM(weight - stonewgt) as wgt')
                ->value('wgt') ?? 0);
        }

        // Calculate weight balance
        $balwgt = 0.0;
        if ($grate > 0) {
            $balwgt = ($netamt - $ramt) / $grate;
        }

        // Subtract collection weight
        if ($this->hasTable('collection')) {
            $colWgt = (float) (DB::table('collection')
                ->where('islno', $slno)
                ->where('tdate', '<=', $tdate)
                ->where('grate', '>', 0)
                ->selectRaw('SUM((tranamt + discount) / grate) as wgt')
                ->value('wgt') ?? 0);
            $balwgt -= $colWgt;
        }

        return response()->json([
            'ok'      => true,
            'slno'    => $slno,
            'netamt'  => round($netamt, 2),
            'weight'  => round($weight, 3),
            'oldrate' => round($grate, 2),
            'wgtbal'  => round($balwgt, 3),
            'control' => (int) ($bill->control ?? $control),
        ]);
    }

    // Save rate diff adjustment
    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $tdate   = trim($request->input('tdate', ''));
        $code    = strtoupper(trim($request->input('code', '')));
        $billno  = strtoupper(trim($request->input('billno', '')));
        $weight  = (float) ($request->input('weight', 0));
        $diffamt = (float) ($request->input('diffamt', 0));
        $newrate = (float) ($request->input('newrate', 0));
        $islno   = (int) ($request->input('islno', 0));
        $billControl = (int) ($request->input('billControl', 0));

        if ($tdate === '') return response()->json(['ok' => false, 'message' => 'Date required']);
        if ($code === '') return response()->json(['ok' => false, 'message' => 'Party required']);
        if ($diffamt <= 0) return response()->json(['ok' => false, 'message' => 'Nothing to do. Diff amount must be > 0']);

        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control < 1 || $control > 2) $control = 1;
        $icontrol = $billControl > 0 ? $billControl : $control;

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

            // Build description
            $desc = 'By Rate Diff Entry ' . $code;
            if ($billno !== '') {
                $desc .= ' ' . $billno;
            } else {
                $desc .= ' ' . number_format($weight, 3) . ' X ' . number_format($newrate, 2);
            }

            // Insert daybookpart
            $dpData = [
                'slno'      => $lslno,
                'vchno'     => $docno,
                'particular'=> $desc,
                'ic'        => $incharge,
            ];
            $dpCols = array_map('strtolower', $this->columnList('daybookpart'));
            if (in_array('uid', $dpCols)) $dpData['uid'] = $incharge;
            DB::table('daybookpart')->insert($dpData);

            // Daybook entry 1: RDIFF account (debit = positive diffamt)
            DB::table('daybook')->insert([
                'slno'    => $lslno,
                'accode'  => 'RDIFF',
                'amount'  => $diffamt,
                'control' => $icontrol,
                'tdate'   => $tdate,
            ]);

            // Daybook entry 2: Party account (credit = negative diffamt)
            DB::table('daybook')->insert([
                'slno'    => $lslno,
                'accode'  => $code,
                'amount'  => -$diffamt,
                'control' => $icontrol,
                'tdate'   => $tdate,
            ]);

            // If bill-based, insert collection record
            if ($billno !== '' && $islno > 0) {
                $colData = [
                    'slno'    => $lslno,
                    'code'    => $code,
                    'tdate'   => $tdate,
                    'billno'  => $billno,
                    'tranamt' => -$diffamt,
                    'discount'=> 0,
                    'control' => $icontrol,
                    'islno'   => $islno,
                    'grate'   => $newrate,
                    'grate2'  => $newrate,
                ];
                $colCols = array_map('strtolower', $this->columnList('collection'));
                $colData = array_filter($colData, fn($k) => in_array(strtolower($k), $colCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('collection')->insert($colData);
            }

            DB::commit();
            return response()->json([
                'ok'      => true,
                'message' => "Saved {$docno} — Diff {$diffamt}",
                'docno'   => $docno,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
