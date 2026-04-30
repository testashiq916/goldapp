<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExpenseVoucherEntryController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('accounts-expense-voucher-entry.index', [
            'title' => 'Expense Voucher Entry',
        ]);
    }

    // Load bill types from salestype
    public function loadBillTypes(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $rows = DB::table('salestype')
            ->select('code', 'name', 'taxperc', 'pprefix')
            ->get()
            ->map(fn($r) => [
                'code'    => trim($r->code ?? ''),
                'name'    => trim($r->name ?? ''),
                'taxperc' => (float) ($r->taxperc ?? 0),
                'pprefix' => trim($r->pprefix ?? ''),
            ])->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Get defaults (account codes from generals)
    public function defaults(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $etaxac = DB::table('generals')->where('code', 'ETAXAC')->value('cvalue') ?? 'ETAX';
        $pdiscac = DB::table('generals')->where('code', 'PDISCAC')->value('cvalue') ?? 'PDISC';

        return response()->json([
            'ok'       => true,
            'taxAc'    => trim($etaxac),
            'discAc'   => trim($pdiscac),
            'pamtAc'   => 'EP',
            'cbAc'     => 'CASH',
        ]);
    }

    // Get next doc number for a bill type
    public function nextDoc(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $btype = trim($request->query('btype', ''));
        if ($btype === '') return response()->json(['ok' => false, 'message' => 'Bill type required']);

        // Get prefix from salestype
        $st = DB::table('salestype')->where('code', $btype)->first();
        $pprefix = trim($st->pprefix ?? '');
        if ($pprefix === '') $pprefix = 'PA/';

        $counterCode = 'PURCH' . $pprefix;

        $counter = DB::table('generali')->where('code', $counterCode)->first();
        if (!$counter) {
            // Counter doesn't exist yet
            $nextNum = 1;
        } else {
            $nextNum = (int) $counter->cvalue + 1;
        }

        $docno = $pprefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        return response()->json([
            'ok'      => true,
            'docno'   => $docno,
            'prefix'  => $pprefix,
            'taxperc' => (float) ($st->taxperc ?? 0),
        ]);
    }

    // Lookup party (supplier)
    public function lookupParty(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = trim($request->query('code', ''));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'Code required']);

        $client = DB::table('clients')->where('code', $code)->first();
        if (!$client) return response()->json(['ok' => false, 'message' => 'Party not found']);

        return response()->json([
            'ok'   => true,
            'code' => trim($client->code ?? ''),
            'name' => trim($client->name ?? ''),
        ]);
    }

    // Search parties
    public function searchParty(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim($request->query('q', ''));
        if ($q === '') return response()->json(['ok' => true, 'rows' => []]);

        $rows = DB::table('clients')
            ->where(function ($query) use ($q) {
                $query->where('code', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%");
            })
            ->select('code', 'name')
            ->limit(20)
            ->get()
            ->map(fn($r) => ['code' => trim($r->code ?? ''), 'name' => trim($r->name ?? '')])
            ->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Search account codes
    public function searchAccount(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim($request->query('q', ''));
        if ($q === '') return response()->json(['ok' => true, 'rows' => []]);

        $rows = DB::table('clients')
            ->where(function ($query) use ($q) {
                $query->where('code', 'like', "%{$q}%")
                      ->orWhere('name', 'like', "%{$q}%");
            })
            ->select('code', 'name')
            ->limit(20)
            ->get()
            ->map(fn($r) => ['code' => trim($r->code ?? ''), 'name' => trim($r->name ?? '')])
            ->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Save expense voucher
    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $btype     = trim($request->input('btype', ''));
        $tdate     = trim($request->input('tdate', ''));
        $billno    = trim($request->input('billno', ''));
        $billdate  = $request->input('billdate', null);
        $partyCode = trim($request->input('partyCode', ''));
        $partyName = trim($request->input('partyName', ''));
        $bamt      = (float) ($request->input('bamt', 0));
        $discount  = (float) ($request->input('discount', 0));
        $taxperc   = (float) ($request->input('taxperc', 0));
        $taxamt    = (float) ($request->input('taxamt', 0));
        $netamt    = (float) ($request->input('netamt', 0));
        $paidamt   = (float) ($request->input('paidamt', 0));
        $pamtAc    = trim($request->input('pamtAc', 'EP'));
        $taxAc     = trim($request->input('taxAc', 'ETAX'));
        $discAc    = trim($request->input('discAc', 'PDISC'));
        $cbAc      = trim($request->input('cbAc', 'CASH'));
        $createPB  = (bool) $request->input('createPB', true);
        $editSlno  = $request->input('editSlno', null);

        if ($tdate === '') return response()->json(['ok' => false, 'message' => 'Date required']);
        if ($bamt <= 0) return response()->json(['ok' => false, 'message' => 'Bill amount required']);

        $incharge = $request->session()->get('user_code', '');

        DB::beginTransaction();
        try {
            if ($editSlno) {
                // Edit mode — delete old records
                $lslno = (int) $editSlno;
                $docno = trim($request->input('docno', ''));

                DB::table('purchasem')->where('slno', $lslno)->delete();
                DB::table('daybook')->where('slno', $lslno)->delete();
                DB::table('daybookpart')->where('slno', $lslno)->delete();
            } else {
                // New — get next SERIALNO
                $currentSerial = (int) (DB::table('generali')->where('code', 'SERIALNO')->value('cvalue') ?? 0);
                $maxUsed = 0;
                foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
                    if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                        $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
                    }
                }
                $lslno = max($currentSerial, $maxUsed) + 1;
                DB::table('generali')->updateOrInsert(['code' => 'SERIALNO'], ['cvalue' => $lslno]);

                // Get prefix and increment counter
                $st = DB::table('salestype')->where('code', $btype)->first();
                $pprefix = trim($st->pprefix ?? '');
                if ($pprefix === '') $pprefix = 'PA/';

                $counterCode = 'PURCH' . $pprefix;
                $counterRow = DB::table('generali')->where('code', $counterCode)->first();

                if ($counterRow) {
                    $nextNum = (int) $counterRow->cvalue + 1;
                    DB::table('generali')->where('code', $counterCode)->update(['cvalue' => $nextNum]);
                } else {
                    $nextNum = 1;
                    DB::table('generali')->insert(['code' => $counterCode, 'cvalue' => 1]);
                }

                $docno = $pprefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
            }

            $desc = 'By Expense voucher ' . $docno;
            $ttime = now()->format('H:i:s');

            // Insert purchasem if checkbox checked
            if ($createPB) {
                $pmData = [
                    'slno'     => $lslno,
                    'docno'    => $docno,
                    'billno'   => $billno,
                    'suppcode' => $partyCode,
                    'name'     => $partyName,
                    'billamt'  => $bamt,
                    'eamt'     => 0,
                    'pamt'     => $paidamt,
                    'addamt'   => 0,
                    'status'   => 2,
                    'pr'       => 'P',
                    'control'  => 0,
                    'tdate'    => $tdate,
                    'ttime'    => $ttime,
                    'rate'     => 0,
                    'smcode'   => '',
                    'round'    => 0,
                    'taxamt'   => $taxamt,
                    'netamt'   => $netamt,
                    'ob'       => 0,
                    'astamt'   => 0,
                    'ic'       => $incharge,
                    'taxexternal' => 'N',
                    'taxperc'  => $taxperc,
                    'billtype' => $btype,
                    'discperc' => 0,
                    'discount' => $discount,
                    'addr'     => '',
                    'note'     => 'Expense Voucher',
                    'exchslno' => 0,
                    'fr'       => 'N',
                ];

                // Guard optional columns
                $pmCols = array_map('strtolower', $this->columnList('purchasem'));
                if (in_array('chqbank', $pmCols)) $pmData['chqbank'] = '';
                if (in_array('chqamt', $pmCols))  $pmData['chqamt'] = 0;
                if (in_array('chqno', $pmCols))   $pmData['chqno'] = '';
                if (in_array('chqpdc', $pmCols))  $pmData['chqpdc'] = '';
                if (in_array('pan', $pmCols))      $pmData['pan'] = '';
                if (in_array('statecode', $pmCols)) $pmData['statecode'] = '';
                if (in_array('sgst', $pmCols))     $pmData['sgst'] = 0;
                if (in_array('cgst', $pmCols))     $pmData['cgst'] = 0;
                if (in_array('igst', $pmCols))     $pmData['igst'] = 0;
                if (in_array('mobile', $pmCols))   $pmData['mobile'] = '';
                if (in_array('counter', $pmCols))  $pmData['counter'] = '';

                DB::table('purchasem')->insert($pmData);
            }

            // Insert daybookpart
            $dpData = [
                'slno'      => $lslno,
                'vchno'     => $docno,
                'particular'=> $desc,
                'ic'        => $incharge,
            ];
            $dpCols = array_map('strtolower', $this->columnList('daybookpart'));
            if (in_array('uid', $dpCols)) $dpData['uid'] = $request->session()->get('user_code', '');

            DB::table('daybookpart')->insert($dpData);

            $sno = 0;

            // sno=1: Purchase amount account (negative bill amount)
            if ($bamt > 0) {
                $sno++;
                DB::table('daybook')->insert([
                    'slno'    => $lslno,
                    'accode'  => $pamtAc,
                    'amount'  => -$bamt,
                    'control' => 0,
                    'tdate'   => $tdate,
                    'sno'     => $sno,
                ]);
            }

            // sno=2: Tax account (negative tax amount)
            if ($taxamt > 0) {
                $sno++;
                DB::table('daybook')->insert([
                    'slno'    => $lslno,
                    'accode'  => $taxAc,
                    'amount'  => -$taxamt,
                    'control' => 0,
                    'tdate'   => $tdate,
                    'sno'     => $sno,
                ]);
            }

            // sno=3: Discount account (positive discount)
            if ($discount > 0) {
                $sno++;
                DB::table('daybook')->insert([
                    'slno'    => $lslno,
                    'accode'  => $discAc,
                    'amount'  => $discount,
                    'control' => 0,
                    'tdate'   => $tdate,
                    'sno'     => $sno,
                ]);
            }

            // sno=4: Cash/Bank account (positive paid amount)
            if ($paidamt > 0) {
                $sno++;
                DB::table('daybook')->insert([
                    'slno'    => $lslno,
                    'accode'  => $cbAc,
                    'amount'  => $paidamt,
                    'control' => 0,
                    'tdate'   => $tdate,
                    'sno'     => $sno,
                ]);
            }

            // sno=5: Party account (positive net amount)
            if ($partyCode !== '') {
                $sno++;
                DB::table('daybook')->insert([
                    'slno'    => $lslno,
                    'accode'  => $partyCode,
                    'amount'  => $netamt,
                    'control' => 0,
                    'tdate'   => $tdate,
                    'sno'     => $sno,
                ]);

                // sno=6: Party account (negative paid amount)
                if ($paidamt > 0) {
                    $sno++;
                    DB::table('daybook')->insert([
                        'slno'    => $lslno,
                        'accode'  => $partyCode,
                        'amount'  => -$paidamt,
                        'control' => 0,
                        'tdate'   => $tdate,
                        'sno'     => $sno,
                    ]);
                }
            }

            DB::commit();
            return response()->json([
                'ok'      => true,
                'message' => "Saved {$docno}",
                'docno'   => $docno,
                'slno'    => $lslno,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Get voucher for editing
    public function get(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $slno = (int) $request->query('slno', 0);
        if ($slno <= 0) return response()->json(['ok' => false, 'message' => 'Invalid slno']);

        $m = DB::table('purchasem')->where('slno', $slno)->first();
        if (!$m) return response()->json(['ok' => false, 'message' => 'Document not found']);

        // Get account codes from daybook entries
        $dbEntries = DB::table('daybook')->where('slno', $slno)->orderBy('sno')->get();

        $pamtAc = 'EP'; $taxAc = 'ETAX'; $discAc = 'PDISC'; $cbAc = 'CASH';
        $bamt = (float) ($m->billamt ?? 0);
        $taxamt = (float) ($m->taxamt ?? 0);
        $discount = (float) ($m->discount ?? 0);
        $paidamt = (float) ($m->pamt ?? 0);

        foreach ($dbEntries as $entry) {
            $amt = (float) ($entry->amount ?? 0);
            $ac = trim($entry->accode ?? '');
            if ($bamt > 0 && $amt == -$bamt) $pamtAc = $ac;
            if ($taxamt > 0 && $amt == -$taxamt) $taxAc = $ac;
            if ($discount > 0 && $amt == $discount) $discAc = $ac;
            if ($paidamt > 0 && $amt == $paidamt && $ac !== trim($m->suppcode ?? '')) $cbAc = $ac;
        }

        return response()->json([
            'ok'        => true,
            'slno'      => $slno,
            'docno'     => trim($m->docno ?? ''),
            'tdate'     => $m->tdate ?? '',
            'billno'    => trim($m->billno ?? ''),
            'billtype'  => trim($m->billtype ?? ''),
            'partyCode' => trim($m->suppcode ?? ''),
            'partyName' => trim($m->name ?? ''),
            'bamt'      => $bamt,
            'discount'  => $discount,
            'taxperc'   => (float) ($m->taxperc ?? 0),
            'taxamt'    => $taxamt,
            'netamt'    => (float) ($m->netamt ?? 0),
            'paidamt'   => $paidamt,
            'pamtAc'    => $pamtAc,
            'taxAc'     => $taxAc,
            'discAc'    => $discAc,
            'cbAc'      => $cbAc,
        ]);
    }

    // Search vouchers for edit
    public function search(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim($request->query('q', ''));

        $query = DB::table('purchasem')
            ->where('note', 'Expense Voucher')
            ->select('slno', 'docno', 'tdate', 'suppcode', 'name', 'netamt');

        if ($q !== '') {
            $query->where(function ($qr) use ($q) {
                $qr->where('docno', 'like', "%{$q}%")
                   ->orWhere('name', 'like', "%{$q}%")
                   ->orWhere('suppcode', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderByDesc('slno')->limit(50)->get()
            ->map(fn($r) => [
                'slno'   => $r->slno,
                'docno'  => trim($r->docno ?? ''),
                'tdate'  => $r->tdate ?? '',
                'party'  => trim($r->suppcode ?? ''),
                'name'   => trim($r->name ?? ''),
                'netamt' => (float) ($r->netamt ?? 0),
            ])->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }
}
