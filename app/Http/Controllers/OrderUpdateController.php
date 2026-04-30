<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderUpdateController extends Controller
{
    // ─── View ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('order-update.index', [
            'title' => 'Order Update from Jewelleries',
        ]);
    }

    // ─── API: List orders from other jewelleries ─────────────────────────────

    public function listOrders(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $jewlcode = trim($request->query('jewlcode', ''));
        $from     = trim($request->query('from', ''));
        $to       = trim($request->query('to', ''));

        if (!$this->hasTable('orderm')) return response()->json(['ok' => true, 'rows' => []]);

        $query = DB::table('orderm as m')
            ->leftJoin('orderd as d', 'm.slno', '=', 'd.slno')
            ->select(
                'm.slno', 'm.ordno', 'm.tdate', 'm.custcode', 'm.custname',
                'm.duedate', 'm.rate', 'm.billamt', 'm.status', 'm.control',
                'm.smcode', 'm.jewlcode',
                'd.code as item_code', 'd.qty', 'd.weight', 'd.stonewgt', 'd.stoneprice',
                'd.mcharge', 'd.wastage', 'd.rate as item_rate', 'd.amount', 'd.part',
                'd.iqtype', 'd.sno'
            )
            ->where('m.jewlcode', '!=', '')
            ->whereNotNull('m.jewlcode')
            ->orderByDesc('m.slno')
            ->orderBy('d.sno');

        if ($jewlcode !== '') {
            $query->where('m.jewlcode', 'like', "%$jewlcode%");
        }
        if ($from !== '') {
            $query->whereDate('m.tdate', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('m.tdate', '<=', $to);
        }

        $rows = $query->limit(500)->get();

        // Group by orderm.slno
        $grouped = [];
        foreach ($rows as $r) {
            $slno = (int)$r->slno;
            if (!isset($grouped[$slno])) {
                $grouped[$slno] = [
                    'slno'      => $slno,
                    'ordno'     => trim($r->ordno ?? ''),
                    'tdate'     => $r->tdate ?? '',
                    'custcode'  => trim($r->custcode ?? ''),
                    'custname'  => trim($r->custname ?? ''),
                    'duedate'   => $r->duedate ?? '',
                    'rate'      => (float)($r->rate ?? 0),
                    'billamt'   => (float)($r->billamt ?? 0),
                    'status'    => (int)($r->status ?? 0),
                    'control'   => (int)($r->control ?? 0),
                    'smcode'    => trim($r->smcode ?? ''),
                    'jewlcode'  => trim($r->jewlcode ?? ''),
                    'items'     => [],
                ];
            }
            if ($r->item_code !== null) {
                $grouped[$slno]['items'][] = [
                    'sno'        => (int)($r->sno ?? 0),
                    'code'       => trim($r->item_code ?? ''),
                    'qty'        => (int)($r->qty ?? 0),
                    'weight'     => (float)($r->weight ?? 0),
                    'stonewgt'   => (float)($r->stonewgt ?? 0),
                    'stoneprice' => (float)($r->stoneprice ?? 0),
                    'mcharge'    => (float)($r->mcharge ?? 0),
                    'wastage'    => (float)($r->wastage ?? 0),
                    'rate'       => (float)($r->item_rate ?? 0),
                    'amount'     => (float)($r->amount ?? 0),
                    'part'       => trim($r->part ?? ''),
                    'iqtype'     => trim($r->iqtype ?? ''),
                ];
            }
        }

        return response()->json(['ok' => true, 'orders' => array_values($grouped)]);
    }

    // ─── API: Import CSV ────────────────────────────────────────────────────

    public function importCsv(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $file = $request->file('csv_file');
        if (!$file || !$file->isValid()) {
            return response()->json(['ok' => false, 'message' => 'No valid file uploaded']);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'txt'])) {
            return response()->json(['ok' => false, 'message' => 'Only CSV files accepted']);
        }

        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return response()->json(['ok' => false, 'message' => 'Cannot read file']);
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return response()->json(['ok' => false, 'message' => 'Empty CSV file']);
        }
        $header = array_map('strtolower', array_map('trim', $header));

        $orders = [];
        while (($row = fgetcsv($handle)) !== false) {
            $data = [];
            foreach ($header as $i => $col) {
                $data[$col] = trim($row[$i] ?? '');
            }
            $orders[] = $data;
        }
        fclose($handle);

        if (empty($orders)) {
            return response()->json(['ok' => false, 'message' => 'No data rows in CSV']);
        }

        return response()->json(['ok' => true, 'rows' => $orders, 'headers' => $header]);
    }

    // ─── API: Save imported orders ──────────────────────────────────────────

    public function saveImport(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $incharge = (string)($request->session()->get('user_code', ''));
        $orders   = (array)$request->input('orders', []);

        if (empty($orders)) {
            return response()->json(['ok' => false, 'message' => 'No orders to import']);
        }

        $omCols = array_map('strtolower', $this->columnList('orderm'));
        $odCols = array_map('strtolower', $this->columnList('orderd'));

        $imported  = 0;
        $skipped   = 0;
        $errors    = [];

        try {
            DB::beginTransaction();

            foreach ($orders as $order) {
                $slno = (int)($order['slno'] ?? 0);

                // Check if already exists
                if ($slno > 0) {
                    $exists = DB::table('orderm')->where('slno', $slno)->exists();
                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                } else {
                    // Generate new slno
                    $slno = $this->nextSlno();
                }

                $ordno    = trim($order['ordno']    ?? '');
                $tdate    = $this->parseDate($order['tdate']    ?? '') ?? date('Y-m-d');
                $duedate  = $this->parseDate($order['duedate']  ?? '');
                $custcode = trim($order['custcode'] ?? '');
                $custname = trim($order['custname'] ?? '');
                $rate     = (float)($order['rate']     ?? 0);
                $billamt  = (float)($order['billamt']  ?? 0);
                $status   = (int)($order['status']     ?? 1);
                $control  = (int)($order['control']    ?? 1);
                $smcode   = trim($order['smcode']   ?? '');
                $jewlcode = trim($order['jewlcode'] ?? '');

                if ($jewlcode === '') {
                    $jewlcode = 'EXT';
                }

                $mAll = [
                    'slno'        => $slno,
                    'ordno'       => $ordno,
                    'tdate'       => $tdate,
                    'duedate'     => $duedate,
                    'custcode'    => $custcode,
                    'custname'    => $custname,
                    'rate'        => $rate,
                    'billamt'     => $billamt,
                    'eamt'        => 0,
                    'advance'     => 0,
                    'status'      => $status,
                    'control'     => $control,
                    'smcode'      => $smcode,
                    'gadvance'    => 0,
                    'sretamt'     => 0,
                    'ob'          => 0,
                    'addr'        => '',
                    'refund'      => 0,
                    'closed'      => 0,
                    'jewlcode'    => $jewlcode,
                    'duedate_org' => $duedate,
                    'ic'          => $incharge,
                ];
                $mData = array_filter($mAll, fn($k) => in_array($k, $omCols), ARRAY_FILTER_USE_KEY);
                DB::table('orderm')->insert($mData);

                // Insert items
                $items = (array)($order['items'] ?? []);
                $sno = 0;
                foreach ($items as $item) {
                    $sno++;
                    $dAll = [
                        'slno'       => $slno,
                        'code'       => strtoupper(trim($item['code'] ?? '')),
                        'qty'        => (int)($item['qty'] ?? 0),
                        'weight'     => (float)($item['weight'] ?? 0),
                        'stonewgt'   => (float)($item['stonewgt'] ?? 0),
                        'stoneprice' => (float)($item['stoneprice'] ?? 0),
                        'mcharge'    => (float)($item['mcharge'] ?? 0),
                        'wastage'    => (float)($item['wastage'] ?? 0),
                        'rate'       => (float)($item['rate'] ?? $rate),
                        'amount'     => (float)($item['amount'] ?? 0),
                        'part'       => trim($item['part'] ?? ''),
                        'sno'        => $sno,
                        'iqtype'     => trim($item['iqtype'] ?? ''),
                        'smith'      => '',
                    ];
                    $dData = array_filter($dAll, fn($k) => in_array($k, $odCols), ARRAY_FILTER_USE_KEY);
                    DB::table('orderd')->insert($dData);
                }

                $imported++;
            }

            DB::commit();

            return response()->json([
                'ok'       => true,
                'message'  => "Imported $imported order(s), skipped $skipped existing.",
                'imported' => $imported,
                'skipped'  => $skipped,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
        }
    }

    // ─── API: Manually add order from jewellery ─────────────────────────────

    public function saveManual(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $incharge = (string)($request->session()->get('user_code', ''));
        $order    = (array)$request->input('order', []);
        $items    = (array)$request->input('items', []);

        $jewlcode = trim($order['jewlcode'] ?? '');
        $custname = trim($order['custname'] ?? '');
        if ($jewlcode === '' || $custname === '') {
            return response()->json(['ok' => false, 'message' => 'Jewellery code and customer name required']);
        }

        $validItems = [];
        foreach ($items as $item) {
            $code = strtoupper(trim($item['code'] ?? ''));
            if ($code === '') continue;
            $validItems[] = $item;
        }
        if (empty($validItems)) {
            return response()->json(['ok' => false, 'message' => 'No valid items']);
        }

        $omCols = array_map('strtolower', $this->columnList('orderm'));
        $odCols = array_map('strtolower', $this->columnList('orderd'));

        try {
            DB::beginTransaction();

            $slno = $this->nextSlno();

            // Generate order number
            $ordno = $this->nextOrdNo($jewlcode);

            $tdate   = $this->parseDate($order['tdate'] ?? '') ?? date('Y-m-d');
            $duedate = $this->parseDate($order['duedate'] ?? '');
            $rate    = (float)($order['rate'] ?? $this->genDec('GRATE'));

            $billTotal = 0;
            foreach ($validItems as $it) {
                $billTotal += (float)($it['amount'] ?? 0);
            }

            $mAll = [
                'slno'        => $slno,
                'ordno'       => $ordno,
                'tdate'       => $tdate,
                'duedate'     => $duedate,
                'custcode'    => trim($order['custcode'] ?? ''),
                'custname'    => $custname,
                'rate'        => $rate,
                'billamt'     => $billTotal,
                'eamt'        => 0,
                'advance'     => 0,
                'status'      => 1,
                'control'     => 1,
                'smcode'      => trim($order['smcode'] ?? ''),
                'gadvance'    => 0,
                'sretamt'     => 0,
                'ob'          => 0,
                'addr'        => '',
                'refund'      => 0,
                'closed'      => 0,
                'jewlcode'    => $jewlcode,
                'duedate_org' => $duedate,
                'ic'          => $incharge,
            ];
            $mData = array_filter($mAll, fn($k) => in_array($k, $omCols), ARRAY_FILTER_USE_KEY);
            DB::table('orderm')->insert($mData);

            $sno = 0;
            foreach ($validItems as $item) {
                $sno++;
                $dAll = [
                    'slno'       => $slno,
                    'code'       => strtoupper(trim($item['code'] ?? '')),
                    'qty'        => (int)($item['qty'] ?? 0),
                    'weight'     => (float)($item['weight'] ?? 0),
                    'stonewgt'   => (float)($item['stonewgt'] ?? 0),
                    'stoneprice' => (float)($item['stoneprice'] ?? 0),
                    'mcharge'    => (float)($item['mcharge'] ?? 0),
                    'wastage'    => (float)($item['wastage'] ?? 0),
                    'rate'       => (float)($item['rate'] ?? $rate),
                    'amount'     => (float)($item['amount'] ?? 0),
                    'part'       => trim($item['part'] ?? ''),
                    'sno'        => $sno,
                    'iqtype'     => trim($item['iqtype'] ?? ''),
                    'smith'      => '',
                ];
                $dData = array_filter($dAll, fn($k) => in_array($k, $odCols), ARRAY_FILTER_USE_KEY);
                DB::table('orderd')->insert($dData);
            }

            DB::commit();

            return response()->json([
                'ok'      => true,
                'message' => 'Order saved successfully',
                'ordno'   => $ordno,
                'slno'    => $slno,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    // ─── API: Delete order ──────────────────────────────────────────────────

    public function deleteOrder(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $slno = (int)$request->input('slno', 0);
        if ($slno <= 0) return response()->json(['ok' => false, 'message' => 'Invalid slno']);

        try {
            DB::beginTransaction();
            DB::table('orderd')->where('slno', $slno)->delete();
            DB::table('orderm')->where('slno', $slno)->delete();
            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Order deleted']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function nextSlno(): int
    {
        if (!$this->hasTable('generali')) return 1;
        $current = (int)(DB::table('generali')->where('code', 'SERIALNO')->value('cvalue') ?? 0);
        $next = $current + 1;
        $updated = DB::table('generali')->where('code', 'SERIALNO')->update(['cvalue' => $next]);
        if ($updated === 0) {
            DB::table('generali')->insert(['code' => 'SERIALNO', 'cvalue' => $next]);
        }
        return $next;
    }

    private function nextOrdNo(string $jewlcode): string
    {
        $prefix = 'ORD';
        if ($this->hasTable('generals')) {
            $p = DB::table('generals')->where('code', 'ORDPREF')->value('cvalue');
            if ($p) $prefix = trim($p);
        }

        $last = (int)(DB::table('orderm')->max('slno') ?? 0);
        $cnt  = (int)(DB::table('generali')->where('code', 'ORDERB')->value('cvalue') ?? 0);
        $next = $cnt + 1;
        DB::table('generali')->where('code', 'ORDERB')->update(['cvalue' => $next]);

        return $prefix . '/' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    private function genDec(string $code): float
    {
        if (!$this->hasTable('generald')) return 0.0;
        return (float)(DB::table('generald')->where('code', $code)->value('cvalue') ?? 0);
    }

    private function parseDate(?string $raw): ?string
    {
        $raw = trim((string)$raw);
        if ($raw === '' || $raw === '00/00/0000') return null;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            if ($m[1] === '00' || $m[2] === '00') return null;
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $raw, $m)) {
            if ($m[1] === '00' || $m[2] === '00') return null;
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
        return null;
    }
}
