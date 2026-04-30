<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AmtWgtTransferController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('accounts-amt-wgt-transfer-entry.index', [
            'title' => 'Amt, Wgt Transfer Entry',
        ]);
    }

    // Init — get gold rate
    public function init(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $grate = (float) (DB::table('generald')->where('code', 'GRATE')->value('cvalue') ?? 0);

        return response()->json(['ok' => true, 'grate' => $grate]);
    }

    // Load party — balance + weight balance
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

        // Opening balances
        $opbal = $control === 1 ? (float) ($client->opbalance ?? 0) : (float) ($client->opbalanceb ?? 0);
        $opwgt = (float) ($client->opweight ?? 0);

        // Daybook sum
        $daySum = (float) (DB::table('daybook')
            ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
            ->where('control', '<=', $control)
            ->sum('amount') ?? 0);

        // Weight sum from daybookratewgt
        $wgtSum = 0.0;
        if ($this->hasTable('daybookratewgt')) {
            $wgtSum = (float) (DB::table('daybookratewgt')
                ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                ->where('control', '<=', $control)
                ->sum('wgt') ?? 0);
        }

        $balance = -($opbal + $daySum);
        $wgtbal  = -($opwgt + $wgtSum);

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

    // Save — create journal + daybookratewgt entries
    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $tdate  = trim($request->input('tdate', ''));
        $code   = strtoupper(trim($request->input('code', '')));
        $ttype  = trim($request->input('ttype', 'Amt To Wgt'));
        $amt    = (float) ($request->input('amt', 0));
        $rate   = (float) ($request->input('rate', 0));
        $weight = (float) ($request->input('weight', 0));

        if ($tdate === '') return response()->json(['ok' => false, 'message' => 'Date required']);
        if ($code === '') return response()->json(['ok' => false, 'message' => 'Party required']);
        if ($amt <= 0 || $weight <= 0) {
            return response()->json(['ok' => false, 'message' => 'Nothing to do']);
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

            // Journal voucher number
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

            $isAmtToWgt = ($ttype === 'Amt To Wgt');

            // Description
            $typeLabel = $isAmtToWgt ? 'Amt To Wgt' : 'Wgt To Amt';
            $desc = "By {$typeLabel} Entry {$code} " . number_format($weight, 3) . ' X ' . number_format($rate, 2);

            // Insert daybookpart
            $dpData = [
                'slno'       => $lslno,
                'vchno'      => $docno,
                'particular' => $desc,
                'ic'         => $incharge,
            ];
            $dpCols = array_map('strtolower', $this->columnList('daybookpart'));
            if (in_array('uid', $dpCols)) $dpData['uid'] = $incharge;
            DB::table('daybookpart')->insert($dpData);

            // Insert daybookratewgt
            $wgtVal = $isAmtToWgt ? -$weight : $weight;
            DB::table('daybookratewgt')->insert([
                'slno'    => $lslno,
                'rate'    => $rate,
                'mcp'     => 0,
                'wgt'     => $wgtVal,
                'code'    => $code,
                'tdate'   => $tdate,
                'control' => $control,
            ]);

            // Daybook entries
            if ($isAmtToWgt) {
                // ATOW gets -amt (debit), party gets +amt (credit)
                DB::table('daybook')->insert([
                    'slno' => $lslno, 'accode' => 'ATOW', 'amount' => -$amt,
                    'control' => $control, 'tdate' => $tdate,
                ]);
                DB::table('daybook')->insert([
                    'slno' => $lslno, 'accode' => $code, 'amount' => $amt,
                    'control' => $control, 'tdate' => $tdate,
                ]);
            } else {
                // ATOW gets +amt, party gets -amt
                DB::table('daybook')->insert([
                    'slno' => $lslno, 'accode' => 'ATOW', 'amount' => $amt,
                    'control' => $control, 'tdate' => $tdate,
                ]);
                DB::table('daybook')->insert([
                    'slno' => $lslno, 'accode' => $code, 'amount' => -$amt,
                    'control' => $control, 'tdate' => $tdate,
                ]);
            }

            DB::commit();
            return response()->json([
                'ok'      => true,
                'message' => "Saved {$docno} — {$typeLabel}: " . number_format($amt, 2),
                'docno'   => $docno,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
