<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderEntryReportController extends Controller
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

        $dateFrom = $request->query('date1', date('Y-m-d'));
        $dateTo   = $request->query('date2', date('Y-m-d'));
        $filter   = $request->query('filter', 'All');   // All | Pending | Returned
        $smcode   = trim($request->query('smcode', ''));
        $smFilter = $request->has('sm_filter');
        $ordno    = trim($request->query('ordno', ''));
        $showData = $request->has('show');

        $orders  = [];
        $summary = ['tcashadv' => 0, 'texadv' => 0, 'tretadv' => 0, 'ttotal' => 0];

        // Salesman list for dropdown
        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray();
        }

        if ($showData && $this->hasTable('orderm')) {
            $orders = $this->loadOrders($dateFrom, $dateTo, $filter, $smcode, $smFilter, $ordno);
            $summary = $this->calcSummary($orders);
        }

        return view('order-report.index', compact(
            'dateFrom', 'dateTo', 'filter', 'smcode', 'smFilter', 'ordno',
            'showData', 'orders', 'summary', 'salesmen'
        ));
    }

    private function loadOrders(string $dateFrom, string $dateTo, string $filter,
                                string $smcode, bool $smFilter, string $ordno): array
    {
        $query = DB::table('orderm')
            ->leftJoin('sman', 'orderm.smcode', '=', 'sman.code')
            ->select([
                'orderm.slno', 'orderm.ordno', 'orderm.tdate', 'orderm.duedate',
                'orderm.rate', 'orderm.custname', 'orderm.custcode',
                'orderm.billamt', 'orderm.eamt', 'orderm.advance',
                'orderm.status', 'orderm.smcode', 'orderm.gadvance',
                'orderm.sretamt', 'orderm.addr', 'orderm.refund',
                'orderm.duedate_org',
                'sman.name as smname',
            ])
            ->where('orderm.control', '<=', $this->gilevel)
            ->whereBetween('orderm.tdate', [$dateFrom, $dateTo])
            ->orderBy('orderm.tdate')
            ->orderBy('orderm.slno');

        $rows = $query->get()->map(fn ($r) => (array) $r)->toArray();

        // Apply client-side-style filters (status, smcode, ordno)
        $rows = array_filter($rows, function ($row) use ($filter, $smcode, $smFilter, $ordno) {
            if ($filter === 'Pending' && (int) $row['status'] !== 1) return false;
            if ($filter === 'Returned' && (int) $row['status'] !== 2) return false;
            if ($smFilter && $smcode !== '' && $row['smcode'] !== $smcode) return false;
            if ($ordno !== '' && strtoupper(trim($row['ordno'])) !== strtoupper($ordno)) return false;
            return true;
        });

        // Load sub-details for each order
        $hasOrderd    = $this->hasTable('orderd');
        $hasAdvafter  = $this->hasTable('advafter');
        $hasOrderdga  = $this->hasTable('orderdga');

        $orderdCols   = $hasOrderd   ? array_map('strtolower', $this->columnList('orderd'))   : [];
        $advafterCols = $hasAdvafter ? array_map('strtolower', $this->columnList('advafter')) : [];
        $orderdgaCols = $hasOrderdga ? array_map('strtolower', $this->columnList('orderdga')) : [];

        foreach ($rows as &$order) {
            $slno = $order['slno'];

            // Order detail items
            $order['items'] = [];
            if ($hasOrderd) {
                $select = ['orderd.sno', 'orderd.code', 'orderd.qty', 'orderd.weight',
                           'orderd.wastage', 'orderd.mcharge', 'orderd.stonewgt',
                           'orderd.stoneprice', 'orderd.amount'];
                if (in_array('part', $orderdCols)) $select[] = 'orderd.part';
                if (in_array('rate', $orderdCols)) $select[] = 'orderd.rate';

                $itemQ = DB::table('orderd')
                    ->leftJoin('items', 'orderd.code', '=', 'items.code')
                    ->select(array_merge($select, ['items.name as itemname']))
                    ->where('orderd.slno', $slno);

                if (in_array('sno', $orderdCols)) {
                    $itemQ->orderBy('orderd.sno');
                }

                $order['items'] = $itemQ->get()->map(fn ($r) => (array) $r)->toArray();
            }

            // Advance receipts (advafter)
            $order['receipts'] = [];
            if ($hasAdvafter) {
                $order['receipts'] = DB::table('advafter')
                    ->where('slno', $slno)
                    ->where('control', '<=', $this->gilevel)
                    ->orderBy('tdate')
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->toArray();
            }
            $order['receipts'] = $this->buildReceiptRows($order, $order['receipts']);

            // Gold advance items (orderdga)
            $order['goldadv'] = [];
            if ($hasOrderdga) {
                $gaSelect = ['orderdga.sno', 'orderdga.code', 'orderdga.qty',
                             'orderdga.weight', 'orderdga.cost'];
                if (in_array('mark', $orderdgaCols))    $gaSelect[] = 'orderdga.mark';
                if (in_array('stktype', $orderdgaCols)) $gaSelect[] = 'orderdga.stktype';

                $order['goldadv'] = DB::table('orderdga')
                    ->leftJoin('items', 'orderdga.code', '=', 'items.code')
                    ->select(array_merge($gaSelect, ['items.name as itemname']))
                    ->where('orderdga.slno', $slno)
                    ->orderByRaw(in_array('sno', $orderdgaCols) ? 'orderdga.sno' : 'orderdga.code')
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->toArray();
            }

            // Total advance per order
            $order['totadv'] = (float) $order['advance'] + (float) $order['eamt'] + (float) $order['sretamt'];
        }
        unset($order);

        return array_values($rows);
    }

    private function calcSummary(array $orders): array
    {
        $tcash = 0;
        $tex   = 0;
        $tret  = 0;
        foreach ($orders as $o) {
            $tcash += (float) $o['advance'];
            $tex   += (float) $o['eamt'];
            $tret  += (float) $o['sretamt'];
        }
        return [
            'tcashadv' => $tcash,
            'texadv'   => $tex,
            'tretadv'  => $tret,
            'ttotal'   => $tcash + $tex + $tret,
        ];
    }
}
