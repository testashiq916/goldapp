<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderPendingDetailsController extends Controller
{
    private int $gilevel = 1;

    private function buildReceiptRows(array $order, array $advafterRows = []): array
    {
        $rows = [];
        $orderDate = (string) ($order['tdate'] ?? '');
        $orderNo = trim((string) ($order['ordno'] ?? ''));
        $orderRate = (float) ($order['rate'] ?? 0);

        $pushRow = function (float $amount, string $type, float $weight = 0.0, ?string $docNo = null, ?string $tdate = null, ?float $rate = null) use (&$rows, $orderDate, $orderNo, $orderRate): void {
            if (abs($amount) <= 0.00001 && abs($weight) <= 0.00001) {
                return;
            }
            $rows[] = [
                'tdate' => $tdate ?: $orderDate,
                'docno' => trim((string) ($docNo ?: $orderNo)),
                'ttype' => $type,
                'rate' => $rate ?? $orderRate,
                'wgt' => $weight,
                'amount' => round($amount, 2),
            ];
        };

        $pushRow((float) ($order['advance'] ?? 0), 'CASH ADV');
        $pushRow((float) ($order['eamt'] ?? 0), 'EX ADV');
        $pushRow((float) ($order['sretamt'] ?? 0), 'SRET ADV');
        $pushRow(0.0, 'GOLD ADV', (float) ($order['gadvance'] ?? 0));

        foreach ($advafterRows as $r) {
            $pushRow(
                (float) ($r['amount'] ?? 0),
                trim((string) ($r['ttype'] ?? 'ADV RCPT')) ?: 'ADV RCPT',
                (float) ($r['wgt'] ?? 0),
                trim((string) ($r['docno'] ?? '')) ?: $orderNo,
                (string) ($r['tdate'] ?? $orderDate),
                isset($r['rate']) ? (float) $r['rate'] : $orderRate
            );
        }

        return $rows;
    }

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));

        $dateFrom  = $request->query('date1', date('Y-m-d'));
        $dateTo    = $request->query('date2', date('Y-m-d'));
        $dduFrom   = $request->query('ddate1', '');
        $dduTo     = $request->query('ddate2', '');
        $smcode    = trim($request->query('smcode', ''));
        $ordno     = strtoupper(trim($request->query('ordno', '')));
        $showAll   = $request->has('show_all');
        $showData  = $request->has('show');

        $orders    = [];
        $summary   = ['count' => 0, 'grandtotal' => 0.0];

        $salesmen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray() : [];

        if ($showData && $this->hasTable('orderm')) {
            $orders = $this->loadOrders($dateFrom, $dateTo, $dduFrom, $dduTo, $smcode, $ordno, $showAll);
            $summary['count'] = count($orders);
            foreach ($orders as $o) {
                $summary['grandtotal'] += $o['totadv'];
            }
        }

        return view('order-report.pending-details', compact(
            'dateFrom', 'dateTo', 'dduFrom', 'dduTo',
            'smcode', 'ordno', 'showAll', 'showData',
            'orders', 'summary', 'salesmen'
        ));
    }

    private function loadOrders(string $dateFrom, string $dateTo,
                                string $dduFrom, string $dduTo,
                                string $smcode, string $ordno, bool $showAll): array
    {
        $query = DB::table('orderm')
            ->leftJoin('sman', 'orderm.smcode', '=', 'sman.code')
            ->select([
                'orderm.slno', 'orderm.ordno', 'orderm.tdate', 'orderm.duedate',
                'orderm.rate', 'orderm.custname', 'orderm.custcode',
                'orderm.billamt', 'orderm.eamt', 'orderm.advance',
                'orderm.status', 'orderm.smcode', 'orderm.gadvance',
                'orderm.sretamt', 'orderm.addr', 'orderm.refund',
                'sman.name as smname',
            ])
            ->where('orderm.control', '<=', $this->gilevel)
            ->whereBetween('orderm.tdate', [$dateFrom, $dateTo])
            ->orderBy('orderm.duedate')
            ->orderBy('orderm.slno');

        if (!$showAll) {
            $query->where('orderm.status', 1);
        }

        if ($smcode !== '') {
            $query->where('orderm.smcode', $smcode);
        }
        if ($ordno !== '') {
            $query->where('orderm.ordno', $ordno);
        }

        $rows = $query->get()->map(fn ($r) => (array) $r)->toArray();

        // Client-side due date filter
        if ($dduFrom !== '' || $dduTo !== '') {
            $rows = array_filter($rows, function ($r) use ($dduFrom, $dduTo) {
                $dd = $r['duedate'] ?? '';
                if (!$dd) return true;
                if ($dduFrom !== '' && $dd < $dduFrom) return false;
                if ($dduTo !== '' && $dd > $dduTo) return false;
                return true;
            });
            $rows = array_values($rows);
        }

        $hasOrderd   = $this->hasTable('orderd');
        $hasAdvafter = $this->hasTable('advafter');
        $hasOrderdga = $this->hasTable('orderdga');

        $orderdCols = $hasOrderd ? array_map('strtolower', $this->columnList('orderd')) : [];

        foreach ($rows as &$order) {
            $slno = $order['slno'];

            // advafter total
            $order['advaft'] = 0;
            if ($hasAdvafter) {
                $order['advaft'] = (float) DB::table('advafter')
                    ->where('slno', $slno)
                    ->sum('amount');
            }

            $order['totadv'] = (float) $order['advance'] + (float) $order['eamt']
                             + (float) $order['sretamt'] + $order['advaft'];

            // Order detail items (extended columns)
            $order['items'] = [];
            if ($hasOrderd) {
                $sel = [
                    'orderd.sno', 'orderd.code', 'orderd.qty', 'orderd.weight',
                    'orderd.wastage', 'orderd.mcharge', 'orderd.stonewgt',
                    'orderd.stoneprice', 'orderd.amount', 'orderd.part',
                    'items.name as itemname',
                ];
                if (in_array('rate', $orderdCols))   $sel[] = 'orderd.rate';
                if (in_array('iqtype', $orderdCols)) $sel[] = 'orderd.iqtype';
                if (in_array('smith', $orderdCols))  $sel[] = 'orderd.smith';
                if (in_array('stage', $orderdCols))  $sel[] = 'orderd.stage';

                $order['items'] = DB::table('orderd')
                    ->leftJoin('items', 'orderd.code', '=', 'items.code')
                    ->select($sel)
                    ->where('orderd.slno', $slno)
                    ->orderBy('orderd.sno')
                    ->get()->map(fn ($r) => (array) $r)->toArray();
            }

            // Advance receipts
            $order['receipts'] = [];
            if ($hasAdvafter) {
                $order['receipts'] = DB::table('advafter')
                    ->where('slno', $slno)
                    ->where('control', '<=', $this->gilevel)
                    ->orderBy('tdate')
                    ->get()->map(fn ($r) => (array) $r)->toArray();
            }
            $order['receipts'] = $this->buildReceiptRows($order, $order['receipts']);

            // Gold advance items
            $order['goldadv'] = [];
            if ($hasOrderdga) {
                $order['goldadv'] = DB::table('orderdga')
                    ->leftJoin('items', 'orderdga.code', '=', 'items.code')
                    ->select(['orderdga.sno','orderdga.code','orderdga.qty',
                              'orderdga.weight','orderdga.cost','items.name as itemname'])
                    ->where('orderdga.slno', $slno)
                    ->orderBy('orderdga.sno')
                    ->get()->map(fn ($r) => (array) $r)->toArray();
            }
        }
        unset($order);

        return $rows;
    }
}
