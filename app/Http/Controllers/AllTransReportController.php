<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AllTransReportController extends Controller
{
    private const REPORT_TYPES = [
        'Sales',
        'SReturn',
        'Purchase',
        'PReturn',
        'Order',
        'Smith',
        'Jewellery',
        'Item Adjustment',
        'Receipt',
        'Payment',
        'Journal',
    ];

    public function index(Request $request): View|RedirectResponse|StreamedResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $date1 = $this->normalizeDate((string) $request->query('date1', '')) ?: date('Y-m-d');
        $date2 = $this->normalizeDate((string) $request->query('date2', '')) ?: date('Y-m-d');
        $rtype = trim((string) $request->query('rtype', 'Sales'));
        if (!in_array($rtype, self::REPORT_TYPES, true)) {
            $rtype = 'Sales';
        }

        $billtype = strtoupper(trim((string) $request->query('billtype', '')));
        $show = $request->boolean('show');
        $export = strtolower(trim((string) $request->query('export', '')));

        $config = $this->reportConfig($rtype);
        $rows = [];
        $totals = [];

        if ($show) {
            $rows = $this->loadRows($rtype, $date1, $date2, $gilevel, $billtype);
            $totals = $this->buildTotals($config['columns'], $rows);
        }

        if ($show && $export === 'csv') {
            return $this->exportCsv($rtype, $config['columns'], $rows);
        }

        return view('reports.all-trans-report', [
            'title' => 'All Trans Report',
            'reportTypes' => self::REPORT_TYPES,
            'currentType' => $rtype,
            'date1' => $date1,
            'date2' => $date2,
            'show' => $show,
            'billtype' => $billtype,
            'billtypes' => $this->billtypes(),
            'rows' => $rows,
            'totals' => $totals,
            'config' => $config,
        ]);
    }

    private function reportConfig(string $rtype): array
    {
        return match ($rtype) {
            'Sales' => [
                'uses_billtype' => true,
                'columns' => [
                    ['key' => 'tdate', 'label' => 'Date', 'type' => 'date'],
                    ['key' => 'billno', 'label' => 'Bill No'],
                    ['key' => 'custname', 'label' => 'Customer'],
                    ['key' => 'maxrate', 'label' => 'Rate', 'type' => 'number', 'decimals' => 2],
                    ['key' => 'grosswgt', 'label' => 'Gr.Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'totwgt', 'label' => 'Net Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'va', 'label' => 'VA', 'type' => 'number', 'decimals' => 2, 'total' => true],
                    ['key' => 'billamt', 'label' => 'Amount', 'type' => 'number', 'decimals' => 2, 'total' => true],
                    ['key' => 'staxamt', 'label' => 'Tax Amt.', 'type' => 'number', 'decimals' => 2, 'total' => true],
                    ['key' => 'astamt', 'label' => 'Cess', 'type' => 'number', 'decimals' => 2, 'total' => true],
                    ['key' => 'discount', 'label' => 'Discount', 'type' => 'number', 'decimals' => 2, 'total' => true],
                    ['key' => 'netamt', 'label' => 'Net Amount', 'type' => 'number', 'decimals' => 2, 'total' => true],
                ],
            ],
            'SReturn' => [
                'uses_billtype' => false,
                'columns' => [
                    ['key' => 'tdate', 'label' => 'Date', 'type' => 'date'],
                    ['key' => 'billno', 'label' => 'Bill No'],
                    ['key' => 'custname', 'label' => 'Customer'],
                    ['key' => 'totwgt', 'label' => 'Net Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'billamt', 'label' => 'Bill Amount', 'type' => 'number', 'decimals' => 2, 'total' => true],
                    ['key' => 'discount', 'label' => 'Discount', 'type' => 'number', 'decimals' => 2, 'total' => true],
                    ['key' => 'staxamt', 'label' => 'Tax Amt.', 'type' => 'number', 'decimals' => 2, 'total' => true],
                    ['key' => 'netamt', 'label' => 'Net Amount', 'type' => 'number', 'decimals' => 2, 'total' => true],
                ],
            ],
            'Purchase' => [
                'uses_billtype' => true,
                'columns' => [
                    ['key' => 'tdate', 'label' => 'Date', 'type' => 'date'],
                    ['key' => 'docno', 'label' => 'Bill No'],
                    ['key' => 'name', 'label' => 'Customer'],
                    ['key' => 'maxrate', 'label' => 'Rate', 'type' => 'number', 'decimals' => 2],
                    ['key' => 'grwgt', 'label' => 'Gross Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'totwgt', 'label' => 'Net Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'billamt', 'label' => 'Bill Amount', 'type' => 'number', 'decimals' => 2, 'total' => true],
                    ['key' => 'taxamt', 'label' => 'Tax Amt.', 'type' => 'number', 'decimals' => 2, 'total' => true],
                    ['key' => 'netamt', 'label' => 'Net Amount', 'type' => 'number', 'decimals' => 2, 'total' => true],
                ],
            ],
            'PReturn' => [
                'uses_billtype' => false,
                'columns' => [
                    ['key' => 'tdate', 'label' => 'Date', 'type' => 'date'],
                    ['key' => 'docno', 'label' => 'Bill No'],
                    ['key' => 'name', 'label' => 'Customer'],
                    ['key' => 'maxrate', 'label' => 'Rate', 'type' => 'number', 'decimals' => 2],
                    ['key' => 'grwgt', 'label' => 'Gross Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'totwgt', 'label' => 'Net Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'billamt', 'label' => 'Bill Amount', 'type' => 'number', 'decimals' => 2, 'total' => true],
                    ['key' => 'taxamt', 'label' => 'Tax Amt.', 'type' => 'number', 'decimals' => 2, 'total' => true],
                    ['key' => 'netamt', 'label' => 'Net Amount', 'type' => 'number', 'decimals' => 2, 'total' => true],
                ],
            ],
            'Order' => [
                'uses_billtype' => false,
                'columns' => [
                    ['key' => 'tdate', 'label' => 'Date', 'type' => 'date'],
                    ['key' => 'ordno', 'label' => 'Order No'],
                    ['key' => 'custname', 'label' => 'Customer'],
                    ['key' => 'maxrate', 'label' => 'Rate', 'type' => 'number', 'decimals' => 2],
                    ['key' => 'grwgt', 'label' => 'Gross Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'totwgt', 'label' => 'Net Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'billamt', 'label' => 'Bill Amount', 'type' => 'number', 'decimals' => 2, 'total' => true],
                ],
            ],
            'Smith' => [
                'uses_billtype' => false,
                'columns' => [
                    ['key' => 'tdate', 'label' => 'Date', 'type' => 'date'],
                    ['key' => 'docno', 'label' => 'Doc No'],
                    ['key' => 'cname', 'label' => 'Party'],
                    ['key' => 'totwgt', 'label' => 'Net Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'issuedwgt', 'label' => 'Issued Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'rcvdwgt', 'label' => 'Rcvd Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                ],
            ],
            'Jewellery' => [
                'uses_billtype' => false,
                'columns' => [
                    ['key' => 'tdate', 'label' => 'Date', 'type' => 'date'],
                    ['key' => 'docno', 'label' => 'Doc No'],
                    ['key' => 'cname', 'label' => 'Party'],
                    ['key' => 'totwgt', 'label' => 'Net Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'issuedwgt', 'label' => 'Issued Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'rcvdwgt', 'label' => 'Rcvd Wgt.', 'type' => 'number', 'decimals' => 3, 'total' => true],
                ],
            ],
            'Item Adjustment' => [
                'uses_billtype' => false,
                'columns' => [
                    ['key' => 'tdate', 'label' => 'Date', 'type' => 'date'],
                    ['key' => 'fomitem', 'label' => 'From Item'],
                    ['key' => 'toitem', 'label' => 'To Item'],
                    ['key' => 'totwgt', 'label' => 'Wgt', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'fromwgt', 'label' => 'From Wgt', 'type' => 'number', 'decimals' => 3, 'total' => true],
                    ['key' => 'towgt', 'label' => 'To Wgt', 'type' => 'number', 'decimals' => 3, 'total' => true],
                ],
            ],
            'Receipt', 'Payment', 'Journal' => [
                'uses_billtype' => false,
                'columns' => [
                    ['key' => 'tdate', 'label' => 'Date', 'type' => 'date'],
                    ['key' => 'docno', 'label' => 'Doc No'],
                    ['key' => 'acname', 'label' => 'Account'],
                    ['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'decimals' => 2, 'total' => true],
                ],
            ],
            default => [
                'uses_billtype' => false,
                'columns' => [],
            ],
        };
    }

    private function loadRows(string $rtype, string $date1, string $date2, int $gilevel, string $billtype): array
    {
        return match ($rtype) {
            'Sales' => $this->salesRows($date1, $date2, $gilevel, $billtype),
            'SReturn' => $this->salesReturnRows($date1, $date2, $gilevel),
            'Purchase' => $this->purchaseRows($date1, $date2, $gilevel, $billtype),
            'PReturn' => $this->purchaseReturnRows($date1, $date2, $gilevel),
            'Order' => $this->orderRows($date1, $date2, $gilevel),
            'Smith' => $this->smithRows($date1, $date2, $gilevel, 'G'),
            'Jewellery' => $this->smithRows($date1, $date2, $gilevel, 'J'),
            'Item Adjustment' => $this->itemAdjustmentRows($date1, $date2, $gilevel),
            'Receipt' => $this->daybookRows($date1, $date2, $gilevel, 'receipt'),
            'Payment' => $this->daybookRows($date1, $date2, $gilevel, 'payment'),
            'Journal' => $this->daybookRows($date1, $date2, $gilevel, 'journal'),
            default => [],
        };
    }

    private function salesRows(string $date1, string $date2, int $gilevel, string $billtype): array
    {
        if (!$this->hasTable('salesm')) {
            return [];
        }

        $q = DB::table('salesm as m')
            ->selectRaw('m.tdate, TRIM(m.billno) as billno, TRIM(COALESCE(m.custname, "")) as custname')
            ->selectRaw('COALESCE(m.billamt, 0) as billamt')
            ->selectRaw($this->hasCol('salesm', 'discount') ? 'COALESCE(m.discount, 0) as discount' : '0 as discount')
            ->selectRaw($this->hasCol('salesm', 'staxamt') ? 'COALESCE(m.staxamt, 0) as staxamt' : '0 as staxamt')
            ->selectRaw($this->hasCol('salesm', 'astamt') ? 'COALESCE(m.astamt, 0) as astamt' : '0 as astamt')
            ->whereBetween('m.tdate', [$date1, $date2]);

        if ($this->hasCol('salesm', 'control')) {
            $q->where('m.control', '<=', $gilevel);
        }
        if ($this->hasCol('salesm', 'opbill')) {
            $q->where('m.opbill', '<>', 1);
        }
        if ($billtype !== '' && $this->hasCol('salesm', 'billtype')) {
            $q->where('m.billtype', $billtype);
        }

        if ($this->hasTable('salesd')) {
            $q->selectRaw('(select coalesce(sum(sd.weight - sd.stonewgt),0) from salesd sd where sd.slno = m.slno) as totwgt');
            $q->selectRaw('(select coalesce(sum(sd.weight),0) from salesd sd where sd.slno = m.slno) as grosswgt');
            $q->selectRaw('(select coalesce(sum(sd.mcharge),0) from salesd sd where sd.slno = m.slno) as va');
            $q->selectRaw('(select coalesce(max(sd.rate),0) from salesd sd where sd.slno = m.slno) as maxrate');
        } else {
            $q->selectRaw('0 as totwgt, 0 as grosswgt, 0 as va, 0 as maxrate');
        }

        return $q->orderBy('m.tdate')->orderBy('m.billno')->get()->map(function ($row) {
            $billamt = (float) ($row->billamt ?? 0);
            $discount = (float) ($row->discount ?? 0);
            $staxamt = (float) ($row->staxamt ?? 0);
            $astamt = (float) ($row->astamt ?? 0);
            return [
                'tdate' => (string) $row->tdate,
                'billno' => (string) $row->billno,
                'custname' => (string) $row->custname,
                'maxrate' => (float) ($row->maxrate ?? 0),
                'grosswgt' => (float) ($row->grosswgt ?? 0),
                'totwgt' => (float) ($row->totwgt ?? 0),
                'va' => (float) ($row->va ?? 0),
                'billamt' => $billamt,
                'staxamt' => $staxamt,
                'astamt' => $astamt,
                'discount' => $discount,
                'netamt' => $billamt - $discount + $staxamt + $astamt,
            ];
        })->all();
    }

    private function salesReturnRows(string $date1, string $date2, int $gilevel): array
    {
        if (!$this->hasTable('salesrm')) {
            return [];
        }

        $q = DB::table('salesrm as m')
            ->selectRaw('m.tdate, TRIM(m.billno) as billno, TRIM(COALESCE(m.custname, "")) as custname')
            ->selectRaw('COALESCE(m.billamt, 0) as billamt')
            ->selectRaw($this->hasCol('salesrm', 'discount') ? 'COALESCE(m.discount, 0) as discount' : '0 as discount')
            ->selectRaw($this->hasCol('salesrm', 'staxamt') ? 'COALESCE(m.staxamt, 0) as staxamt' : '0 as staxamt')
            ->selectRaw($this->hasCol('salesrm', 'netamt') ? 'COALESCE(m.netamt, 0) as netamt' : '(COALESCE(m.billamt, 0) - COALESCE(m.discount, 0) + COALESCE(m.staxamt, 0)) as netamt')
            ->whereBetween('m.tdate', [$date1, $date2]);

        if ($this->hasCol('salesrm', 'control')) {
            $q->where('m.control', '<=', $gilevel);
        }

        if ($this->hasTable('salesrd')) {
            $q->selectRaw('(select coalesce(sum(sd.weight - sd.stonewgt),0) from salesrd sd where sd.slno = m.slno) as totwgt');
        } else {
            $q->selectRaw('0 as totwgt');
        }

        return $q->orderBy('m.tdate')->orderBy('m.billno')->get()->map(fn ($row) => [
            'tdate' => (string) $row->tdate,
            'billno' => (string) $row->billno,
            'custname' => (string) $row->custname,
            'totwgt' => (float) ($row->totwgt ?? 0),
            'billamt' => (float) ($row->billamt ?? 0),
            'discount' => (float) ($row->discount ?? 0),
            'staxamt' => (float) ($row->staxamt ?? 0),
            'netamt' => (float) ($row->netamt ?? 0),
        ])->all();
    }

    private function purchaseRows(string $date1, string $date2, int $gilevel, string $billtype): array
    {
        if (!$this->hasTable('purchasem')) {
            return [];
        }

        $q = DB::table('purchasem as m')
            ->selectRaw('m.tdate, TRIM(COALESCE(m.docno, "")) as docno, TRIM(COALESCE(m.name, "")) as name')
            ->selectRaw('COALESCE(m.billamt, 0) as billamt')
            ->selectRaw($this->hasCol('purchasem', 'taxamt') ? 'COALESCE(m.taxamt, 0) as taxamt' : '0 as taxamt')
            ->selectRaw($this->hasCol('purchasem', 'netamt') ? 'COALESCE(m.netamt, 0) as netamt' : 'COALESCE(m.billamt, 0) as netamt')
            ->whereBetween('m.tdate', [$date1, $date2]);

        if ($this->hasCol('purchasem', 'control')) {
            $q->where('m.control', '<=', $gilevel);
        }
        if ($billtype !== '' && $this->hasCol('purchasem', 'billtype')) {
            $q->where('m.billtype', $billtype);
        }

        if ($this->hasTable('purchased')) {
            $q->selectRaw('(select coalesce(sum(d.weight - d.stwgt),0) from purchased d where d.slno = m.slno) as totwgt');
            $q->selectRaw('(select coalesce(sum(d.weight),0) from purchased d where d.slno = m.slno) as grwgt');
            $q->selectRaw('(select coalesce(max(d.rate),0) from purchased d where d.slno = m.slno) as maxrate');
        } else {
            $q->selectRaw('0 as totwgt, 0 as grwgt, 0 as maxrate');
        }

        return $q->orderBy('m.tdate')->orderBy('m.docno')->get()->map(fn ($row) => [
            'tdate' => (string) $row->tdate,
            'docno' => (string) $row->docno,
            'name' => (string) $row->name,
            'maxrate' => (float) ($row->maxrate ?? 0),
            'grwgt' => (float) ($row->grwgt ?? 0),
            'totwgt' => (float) ($row->totwgt ?? 0),
            'billamt' => (float) ($row->billamt ?? 0),
            'taxamt' => (float) ($row->taxamt ?? 0),
            'netamt' => (float) ($row->netamt ?? 0),
        ])->all();
    }

    private function purchaseReturnRows(string $date1, string $date2, int $gilevel): array
    {
        if (!$this->hasTable('purchaserm')) {
            return [];
        }

        $q = DB::table('purchaserm as m')
            ->selectRaw('m.tdate, TRIM(COALESCE(m.docno, "")) as docno, TRIM(COALESCE(m.name, "")) as name')
            ->selectRaw('COALESCE(m.billamt, 0) as billamt')
            ->selectRaw($this->hasCol('purchaserm', 'taxamt') ? 'COALESCE(m.taxamt, 0) as taxamt' : '0 as taxamt')
            ->selectRaw($this->hasCol('purchaserm', 'netamt') ? 'COALESCE(m.netamt, 0) as netamt' : 'COALESCE(m.billamt, 0) as netamt')
            ->whereBetween('m.tdate', [$date1, $date2]);

        if ($this->hasCol('purchaserm', 'control')) {
            $q->where('m.control', '<=', $gilevel);
        }

        if ($this->hasTable('purchaserd')) {
            $q->selectRaw('(select coalesce(sum(d.weight - d.stwgt),0) from purchaserd d where d.slno = m.slno) as totwgt');
            $q->selectRaw('(select coalesce(sum(d.weight),0) from purchaserd d where d.slno = m.slno) as grwgt');
            $q->selectRaw('(select coalesce(max(d.rate),0) from purchaserd d where d.slno = m.slno) as maxrate');
        } else {
            $q->selectRaw('0 as totwgt, 0 as grwgt, 0 as maxrate');
        }

        return $q->orderBy('m.tdate')->orderBy('m.docno')->get()->map(fn ($row) => [
            'tdate' => (string) $row->tdate,
            'docno' => (string) $row->docno,
            'name' => (string) $row->name,
            'maxrate' => (float) ($row->maxrate ?? 0),
            'grwgt' => (float) ($row->grwgt ?? 0),
            'totwgt' => (float) ($row->totwgt ?? 0),
            'billamt' => (float) ($row->billamt ?? 0),
            'taxamt' => (float) ($row->taxamt ?? 0),
            'netamt' => (float) ($row->netamt ?? 0),
        ])->all();
    }

    private function orderRows(string $date1, string $date2, int $gilevel): array
    {
        if (!$this->hasTable('orderm')) {
            return [];
        }

        $q = DB::table('orderm as m')
            ->selectRaw('m.tdate, TRIM(COALESCE(m.ordno, "")) as ordno, TRIM(COALESCE(m.custname, "")) as custname')
            ->selectRaw('COALESCE(m.billamt, 0) as billamt')
            ->whereBetween('m.tdate', [$date1, $date2]);

        if ($this->hasCol('orderm', 'control')) {
            $q->where('m.control', '<=', $gilevel);
        }

        if ($this->hasTable('orderd')) {
            $q->selectRaw('(select coalesce(sum(d.weight - d.stonewgt),0) from orderd d where d.slno = m.slno) as totwgt');
            $q->selectRaw('(select coalesce(sum(d.weight),0) from orderd d where d.slno = m.slno) as grwgt');
            $q->selectRaw('(select coalesce(max(d.rate),0) from orderd d where d.slno = m.slno) as maxrate');
        } else {
            $q->selectRaw('0 as totwgt, 0 as grwgt, 0 as maxrate');
        }

        return $q->orderBy('m.tdate')->orderBy('m.ordno')->get()->map(fn ($row) => [
            'tdate' => (string) $row->tdate,
            'ordno' => (string) $row->ordno,
            'custname' => (string) $row->custname,
            'maxrate' => (float) ($row->maxrate ?? 0),
            'grwgt' => (float) ($row->grwgt ?? 0),
            'totwgt' => (float) ($row->totwgt ?? 0),
            'billamt' => (float) ($row->billamt ?? 0),
        ])->all();
    }

    private function smithRows(string $date1, string $date2, int $gilevel, string $ctype): array
    {
        if (!$this->hasTable('smithm') || !$this->hasTable('clients')) {
            return [];
        }

        $q = DB::table('smithm as m')
            ->join('clients as c', 'c.code', '=', 'm.smithcode')
            ->selectRaw('m.tdate, TRIM(COALESCE(m.docno, "")) as docno, TRIM(COALESCE(c.name, "")) as cname')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('c.ctype', $ctype);

        if ($this->hasCol('smithm', 'control')) {
            $q->where('m.control', '<=', $gilevel);
        }

        if ($this->hasTable('smithd')) {
            $q->selectRaw('(select coalesce(sum(d.weight - d.stonewgt),0) from smithd d where d.slno = m.slno) as totwgt');
            $q->selectRaw("(select coalesce(sum(d.netwgt),0) from smithd d where d.slno = m.slno and d.givrec = 'G') as issuedwgt");
            $q->selectRaw("(select coalesce(sum(d.netwgt),0) from smithd d where d.slno = m.slno and d.givrec = 'R') as rcvdwgt");
        } else {
            $q->selectRaw('0 as totwgt, 0 as issuedwgt, 0 as rcvdwgt');
        }

        return $q->orderBy('m.tdate')->orderBy('m.docno')->get()->map(fn ($row) => [
            'tdate' => (string) $row->tdate,
            'docno' => (string) $row->docno,
            'cname' => (string) $row->cname,
            'totwgt' => (float) ($row->totwgt ?? 0),
            'issuedwgt' => (float) ($row->issuedwgt ?? 0),
            'rcvdwgt' => (float) ($row->rcvdwgt ?? 0),
        ])->all();
    }

    private function itemAdjustmentRows(string $date1, string $date2, int $gilevel): array
    {
        if (!$this->hasTable('itemadj')) {
            return [];
        }

        return DB::table('itemadj')
            ->selectRaw('tdate')
            ->selectRaw('(select trim(coalesce(items.name, "")) from items where items.code = itemadj.fromcode) as fomitem')
            ->selectRaw('(select trim(coalesce(items.name, "")) from items where items.code = itemadj.tocode) as toitem')
            ->selectRaw('coalesce(fromwgt, 0) as fromwgt, coalesce(towgt, 0) as towgt, coalesce(fromwgt, 0) as totwgt')
            ->whereBetween('tdate', [$date1, $date2])
            ->where('control', '<=', $gilevel)
            ->orderBy('tdate')
            ->orderBy('slno')
            ->get()
            ->map(fn ($row) => [
                'tdate' => (string) $row->tdate,
                'fomitem' => (string) $row->fomitem,
                'toitem' => (string) $row->toitem,
                'totwgt' => (float) ($row->totwgt ?? 0),
                'fromwgt' => (float) ($row->fromwgt ?? 0),
                'towgt' => (float) ($row->towgt ?? 0),
            ])->all();
    }

    private function daybookRows(string $date1, string $date2, int $gilevel, string $mode): array
    {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart') || !$this->hasTable('accountm')) {
            return [];
        }

        $q = DB::table('daybook')
            ->leftJoin('daybookpart', 'daybookpart.slno', '=', 'daybook.slno')
            ->leftJoin('accountm', 'accountm.accode', '=', 'daybook.accode')
            ->selectRaw('daybook.tdate')
            ->selectRaw('TRIM(COALESCE(daybookpart.vchno, "")) as docno')
            ->selectRaw('TRIM(COALESCE(accountm.name, "")) as acname')
            ->selectRaw('ABS(COALESCE(daybook.amount, 0)) as amount')
            ->whereBetween('daybook.tdate', [$date1, $date2])
            ->where('daybook.control', '<=', $gilevel);

        if ($mode === 'receipt') {
            $q->whereRaw("TRIM(COALESCE(daybookpart.vchno, '')) LIKE 'VR%'")
                ->where('daybook.amount', '>', 0);
        } elseif ($mode === 'payment') {
            $q->whereRaw("TRIM(COALESCE(daybookpart.vchno, '')) LIKE 'VP%'")
                ->where('daybook.amount', '<', 0);
        } else {
            $q->whereRaw("TRIM(COALESCE(daybookpart.vchno, '')) LIKE 'JL%'");
        }

        return $q->orderBy('daybook.tdate')->orderBy('daybook.slno')->get()->map(fn ($row) => [
            'tdate' => (string) $row->tdate,
            'docno' => (string) $row->docno,
            'acname' => (string) $row->acname,
            'amount' => (float) ($row->amount ?? 0),
        ])->all();
    }

    private function buildTotals(array $columns, array $rows): array
    {
        $totals = [];
        foreach ($columns as $column) {
            if (!($column['total'] ?? false)) {
                continue;
            }
            $key = $column['key'];
            $totals[$key] = array_reduce($rows, fn ($carry, $row) => $carry + (float) ($row[$key] ?? 0), 0.0);
        }

        return $totals;
    }

    private function exportCsv(string $rtype, array $columns, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_map(fn ($column) => $column['label'], $columns));
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($column) => $row[$column['key']] ?? '', $columns));
            }
            fclose($handle);
        }, 'all_trans_report_' . strtolower(str_replace(' ', '_', $rtype)) . '_' . date('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function billtypes(): array
    {
        if (!$this->hasTable('salestype')) {
            return [];
        }

        return DB::table('salestype')
            ->select('code', 'name')
            ->orderBy('code')
            ->get()
            ->map(fn ($row) => [
                'code' => strtoupper(trim((string) ($row->code ?? ''))),
                'name' => trim((string) ($row->name ?? '')),
            ])
            ->all();
    }

    private function hasCol(string $table, string $column): bool
    {
        return $this->hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return null;
    }
}
