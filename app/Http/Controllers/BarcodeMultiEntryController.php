<?php

namespace App\Http\Controllers;

use App\Support\SecondaryDatabaseSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarcodeMultiEntryController extends Controller
{
    // ─── View ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $items     = $this->hasTable('items')
            ? DB::table('items')->select('code', 'name')->orderBy('code')->get()->toArray()
            : [];
        $subgroups = $this->hasTable('itemsubgrp')
            ? DB::table('itemsubgrp')->select('code', 'name')->orderBy('name')->get()->toArray()
            : [];
        $models    = $this->hasTable('models')
            ? DB::table('models')->select('name')->orderBy('name')->get()->pluck('name')->toArray()
            : [];
        $salesmen  = $this->hasTable('salesman')
            ? DB::table('salesman')->select('code', 'name')->orderBy('code')->get()->toArray()
            : [];
        $smiths    = [];
        if ($this->hasTable('accountm')) {
            $cols = array_map('strtolower', $this->columnList('accountm'));
            if (in_array('code', $cols) && in_array('name', $cols)) {
                $q = DB::table('accountm')->select('code', 'name');
                if (in_array('grcode', $cols)) {
                    $q->where('grcode', 'like', '%SM%')->orWhere('grcode', 'like', '%GS%');
                }
                $smiths = $q->orderBy('code')->limit(200)->get()->toArray();
            }
        }
        $stktypes = $this->hasTable('stktype')
            ? DB::table('stktype')->select('code', 'name')->orderBy('code')->get()->toArray()
            : [];

        return view('barcode-multi-entry.index', [
            'title'     => 'Barcode Multi Entry',
            'items'     => $items,
            'subgroups' => $subgroups,
            'models'    => $models,
            'salesmen'  => $salesmen,
            'smiths'    => $smiths,
            'stktypes'  => $stktypes,
        ]);
    }

    // ─── API: Next barcode number ────────────────────────────────────────────

    public function nextBarcode(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $next = 0;
        // Use MAX(bcode) from barcode table (PB: BCMaxNo=Y default)
        if ($this->hasTable('barcode')) {
            $next = (int)(DB::table('barcode')->max('bcode') ?? 0);
        }
        if ($next <= 0) {
            if ($this->hasTable('generali')) {
                $next = (int)(DB::table('generali')->where('code', 'BCNO')->value('cvalue') ?? 0);
            }
        }
        if ($next <= 0) $next = 1000000;
        $next++;

        return response()->json(['ok' => true, 'barcode' => $next]);
    }

    // ─── API: Next document number ───────────────────────────────────────────

    public function nextDocNo(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $current = 0;
        if ($this->hasTable('generali')) {
            $current = (int)(DB::table('generali')->where('code', 'BCDOCNO')->value('cvalue') ?? 0);
        }
        $next = $current + 1;
        return response()->json([
            'ok'    => true,
            'docno' => 'BC/' . str_pad($next, 6, '0', STR_PAD_LEFT),
            'num'   => $next,
        ]);
    }

    // ─── API: Load item details ──────────────────────────────────────────────

    public function loadItem(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = strtoupper(trim($request->query('code', '')));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'No code']);

        if (!$this->hasTable('items')) return response()->json(['ok' => false]);

        $row = DB::table('items')->where('code', $code)->first();
        if (!$row) return response()->json(['ok' => false, 'message' => 'Item not found']);

        return response()->json([
            'ok'   => true,
            'code' => trim($row->code ?? ''),
            'name' => trim($row->name ?? ''),
        ]);
    }

    // ─── API: Save batch ─────────────────────────────────────────────────────

    public function save(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $gisemi    = (int)($request->session()->get('semi', 1));
        $rows      = (array)$request->input('rows', []);
        $tdate     = $request->input('tdate', date('Y-m-d'));
        $smcode    = trim((string)$request->input('smcode', ''));
        $smithcode = trim((string)$request->input('smithcode', ''));

        if (empty($rows)) return response()->json(['ok' => false, 'message' => 'No rows to save']);

        if (!$this->hasTable('barcode')) return response()->json(['ok' => false, 'message' => 'Barcode table missing']);

        $bcCols = array_map('strtolower', $this->columnList('barcode'));

        // Get next SERIALNO for rslno
        $rslno = $this->incrementGenInt('SERIALNO');

        // Get next doc number
        $docNum = $this->incrementGenInt('BCDOCNO');
        $docno  = 'BC/' . str_pad($docNum, 6, '0', STR_PAD_LEFT);

        $saved   = 0;
        $errors  = [];

        try {
            DB::beginTransaction();

            foreach ($rows as $row) {
                $bcode  = (int)($row['barcode'] ?? 0);
                $icode  = strtoupper(trim($row['itemcode'] ?? ''));
                $weight = (float)($row['weight'] ?? 0);

                if ($bcode <= 0 || $icode === '' || $weight <= 0) continue;

                $qty        = (int)($row['qty'] ?? 1);
                $stwgt      = (float)($row['stwgt'] ?? 0);
                $stprice    = (float)($row['stprice'] ?? 0);
                $vaperc     = (float)($row['vaperc'] ?? 0);
                $minvap     = (float)($row['minvap'] ?? 0);
                $stickerwgt = (float)($row['stickerwgt'] ?? 0);
                $mcrate     = (float)($row['mcrate'] ?? 0);
                $mcamt      = (float)($row['mcamt'] ?? 0);
                $smithmcrate = (float)($row['smithmcrate'] ?? 0);
                $huid       = strtoupper(trim($row['huid'] ?? ''));
                $subgrp     = trim($row['subgrp'] ?? '');
                $size       = strtoupper(trim($row['size'] ?? ''));
                $model      = strtoupper(trim($row['model'] ?? ''));
                $wastage    = (float)($row['wastage'] ?? 0);
                $weight2    = $weight + $stickerwgt;

                $data = [
                    'bcode'        => $bcode,
                    'icode'        => $icode,
                    'qty'          => $qty,
                    'weight'       => $weight,
                    'stweight'     => $stwgt,
                    'stprice'      => $stprice,
                    'wastage'      => $wastage,
                    'mc'           => $mcamt,
                    'tdate'        => $tdate,
                    'smcode'       => $smcode,
                    'control'      => $gisemi,
                    'mcrate'       => $mcrate,
                    'rslno'        => $rslno,
                    'islno'        => 0,
                    'stk'          => 'Y',
                    'rate'         => 0,
                    'smithmcrate'  => $smithmcrate,
                    'weight2'      => $weight2,
                    'vap'          => $vaperc,
                    'sizemodel'    => $size,
                    'model'        => $model,
                    'counter'      => '',
                    'subgrp'       => $subgrp,
                    'minvap'       => $minvap,
                    'stkinnos'     => 'N',
                    'docno'        => $docno,
                    'status'       => 'N',
                    'smithcode'    => $smithcode,
                ];
                if ($huid !== '' && in_array('huid', $bcCols)) {
                    $data['huid'] = $huid;
                }

                // Filter to existing columns
                $data = array_filter($data, fn($k) => in_array($k, $bcCols), ARRAY_FILTER_USE_KEY);

                // Check if barcode exists
                $exists = DB::table('barcode')->where('bcode', $bcode)->exists();

                if ($exists) {
                    DB::table('barcode')->where('bcode', $bcode)->update($data);
                } else {
                    DB::table('barcode')->insert($data);
                }

                // Update BCNO counter
                $currentBcno = (int)(DB::table('generali')->where('code', 'BCNO')->value('cvalue') ?? 0);
                if ($bcode > $currentBcno) {
                    $upd = DB::table('generali')->where('code', 'BCNO')->update(['cvalue' => $bcode]);
                    if ($upd === 0) {
                        DB::table('generali')->insert(['code' => 'BCNO', 'cvalue' => $bcode]);
                    }
                }

                $saved++;
            }

            DB::commit();

            $message = "Saved $saved barcode(s)";
            if (SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code'))) {
                try {
                    $sync = new SecondaryDatabaseSync();
                    foreach ($rows as $row) {
                        $bcode = (int) ($row['barcode'] ?? 0);
                        if ($bcode > 0) {
                            $sync->syncBarcode($bcode);
                        }
                    }
                    $message .= ' and synced to secondary DB (' . $sync->targetDatabaseName() . ')';
                } catch (\Throwable $e) {
                    $message .= '. Primary save completed, but secondary sync failed: ' . $e->getMessage();
                }
            }

            return response()->json([
                'ok'      => true,
                'message' => $message,
                'docno'   => $docno,
                'saved'   => $saved,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    // ─── API: Delete a barcode row ───────────────────────────────────────────

    public function deleteBarcode(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $bcode = (int)$request->input('bcode', 0);
        if ($bcode <= 0) return response()->json(['ok' => false, 'message' => 'Invalid barcode']);

        if (!$this->hasTable('barcode')) return response()->json(['ok' => false]);

        $docno = trim((string) (DB::table('barcode')->where('bcode', $bcode)->value('docno') ?? ''));
        DB::table('barcode')->where('bcode', $bcode)->delete();
        if ($this->hasTable('barcode_dmddet')) {
            DB::table('barcode_dmddet')->where('bcode', $bcode)->delete();
        }
        if ($this->hasTable('barcodedmd')) {
            DB::table('barcodedmd')->where('bcode', $bcode)->delete();
        }
        if ($docno !== '' && $this->hasTable('barcodedoc') && Schema::hasColumn('barcode', 'docno')) {
            $stillExists = DB::table('barcode')->where('docno', $docno)->exists();
            if (!$stillExists) {
                DB::table('barcodedoc')->where('docno', $docno)->delete();
            }
        }

        $message = 'Barcode deleted';
        if (SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code'))) {
            try {
                $secondarySync = (new SecondaryDatabaseSync())->deleteBarcode($bcode, $docno);
                $message .= ' and synced to secondary DB (' . ($secondarySync['database'] ?? '') . ')';
            } catch (\Throwable $e) {
                $message .= '. Primary delete completed, but secondary sync failed: ' . $e->getMessage();
            }
        }

        return response()->json(['ok' => true, 'message' => $message]);
    }

    // ─── API: Load document ──────────────────────────────────────────────────

    public function loadDocument(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $docno = trim($request->query('docno', ''));
        if ($docno === '') return response()->json(['ok' => false, 'message' => 'No document number']);

        if (!$this->hasTable('barcode')) return response()->json(['ok' => false]);

        $rows = DB::table('barcode')
            ->where('docno', $docno)
            ->orderBy('bcode')
            ->get();

        if ($rows->isEmpty()) return response()->json(['ok' => false, 'message' => 'Document not found']);

        $data = $rows->map(function ($r) {
            return [
                'barcode'     => (int)$r->bcode,
                'itemcode'    => trim($r->icode ?? ''),
                'itemname'    => '',
                'subgrp'      => trim($r->subgrp ?? ''),
                'size'        => trim($r->sizemodel ?? ''),
                'model'       => trim($r->model ?? ''),
                'qty'         => (int)($r->qty ?? 1),
                'weight'      => (float)($r->weight ?? 0),
                'stwgt'       => (float)($r->stweight ?? 0),
                'stprice'     => (float)($r->stprice ?? 0),
                'vaperc'      => (float)($r->vap ?? 0),
                'minvap'      => (float)($r->minvap ?? 0),
                'stickerwgt'  => (float)($r->weight2 ?? 0) - (float)($r->weight ?? 0),
                'mcrate'      => (float)($r->mcrate ?? 0),
                'mcamt'       => (float)($r->mc ?? 0),
                'smithmcrate' => (float)($r->smithmcrate ?? 0),
                'huid'        => trim($r->huid ?? ''),
                'wastage'     => (float)($r->wastage ?? 0),
            ];
        });

        $first = $rows->first();

        return response()->json([
            'ok'     => true,
            'rows'   => $data,
            'tdate'  => $first->tdate ?? '',
            'smcode' => trim($first->smcode ?? ''),
            'smith'  => trim($first->smithcode ?? ''),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function incrementGenInt(string $code): int
    {
        if (!$this->hasTable('generali')) return 1;
        $current = (int)(DB::table('generali')->where('code', $code)->value('cvalue') ?? 0);
        if ($code === 'SERIALNO') {
            $maxUsed = 0;
            foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
                if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                    $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
                }
            }
            $current = max($current, $maxUsed);
        }
        $next = $current + 1;
        $upd = DB::table('generali')->where('code', $code)->update(['cvalue' => $next]);
        if ($upd === 0) {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => $next]);
        }
        return $next;
    }
}
