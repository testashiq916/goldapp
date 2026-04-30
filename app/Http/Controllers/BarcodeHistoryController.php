<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarcodeHistoryController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));

        $bcode    = trim($request->query('bcode', ''));
        $dateFrom = $request->query('date1', date('Y-m-d'));
        $dateTo   = $request->query('date2', date('Y-m-d'));
        $showData = $request->has('show') && $bcode !== '';

        $bcInfo   = null;
        $history  = [];

        if ($showData && $this->hasTable('barcode')) {
            $bcInfo = $this->getBarcodeInfo($bcode);
            if ($bcInfo) {
                $history = $this->loadHistory((int) $bcode, $dateFrom, $dateTo);
            }
        }

        return view('barcode-list.history', compact(
            'bcode', 'dateFrom', 'dateTo', 'showData', 'bcInfo', 'history'
        ));
    }

    private function getBarcodeInfo(string $bcode): ?array
    {
        $bc = DB::table('barcode')
            ->where('bcode', $bcode)
            ->first(['icode', 'qty', 'rate', 'weight', 'stweight', 'tdate', 'transtouch', 'smithcode']);

        if (!$bc) return null;

        $bc = (array) $bc;
        $bc['itemname'] = '';
        if ($this->hasTable('items')) {
            $bc['itemname'] = (string) DB::table('items')->where('code', $bc['icode'])->value('name');
        }

        // Adjust weight for transfers out/in
        if ($this->hasTable('itemadj')) {
            $adjOut = DB::table('itemadj')->where('bcode', $bcode)
                ->selectRaw('COALESCE(SUM(fromwgt),0) as wgt, COALESCE(SUM(fromstwgt),0) as stwgt')->first();
            $adjIn = DB::table('itemadj')->where('tobcode', $bcode)
                ->selectRaw('COALESCE(SUM(towgt),0) as wgt, COALESCE(SUM(tostwgt),0) as stwgt')->first();
            $bc['weight'] = (float) $bc['weight'] + (float) $adjOut->wgt - (float) $adjIn->wgt;
            $bc['stweight'] = (float) $bc['stweight'] + (float) $adjOut->stwgt - (float) $adjIn->stwgt;
        }

        return $bc;
    }

    private function loadHistory(int $bcode, string $dateFrom, string $dateTo): array
    {
        $gl = $this->gilevel;
        $rows = [];

        // Created entry
        $bc = DB::table('barcode')->where('bcode', $bcode)->first();
        if ($bc) {
            $origWgt = (float) $bc->weight;
            $origSt  = (float) $bc->stweight;

            // Add back transfer adjustments for original weight
            if ($this->hasTable('itemadj')) {
                $adjOut = DB::table('itemadj')->where('bcode', $bcode)
                    ->selectRaw('COALESCE(SUM(fromwgt),0) as w, COALESCE(SUM(fromstwgt),0) as s')->first();
                $adjIn = DB::table('itemadj')->where('tobcode', $bcode)
                    ->selectRaw('COALESCE(SUM(towgt),0) as w, COALESCE(SUM(tostwgt),0) as s')->first();
                $origWgt += (float) $adjOut->w - (float) $adjIn->w;
                $origSt  += (float) $adjOut->s - (float) $adjIn->s;
            }

            $rows[] = [
                'tdate' => $bc->tdate, 'transaction' => 'Created', 'docno' => '',
                'qty' => (int) $bc->qty, 'rate' => (float) $bc->rate,
                'weight' => $origWgt, 'stone' => $origSt,
            ];
        }

        // Sales
        if ($this->hasTable('salesm') && $this->hasTable('salesd')) {
            $sales = DB::select("
                SELECT salesm.tdate, salesm.billno AS docno, salesd.weight, salesd.stonewgt AS stone,
                       salesd.qty, salesd.rate
                FROM salesm JOIN salesd ON salesd.slno = salesm.slno
                WHERE salesd.bcode = ? AND salesm.control <= ?
            ", [$bcode, $gl]);
            foreach ($sales as $s) {
                $rows[] = [
                    'tdate' => $s->tdate, 'transaction' => 'Sales', 'docno' => $s->docno,
                    'qty' => (int) $s->qty, 'rate' => (float) $s->rate,
                    'weight' => (float) $s->weight, 'stone' => (float) $s->stone,
                ];
            }
        }

        // Sales Returns
        if ($this->hasTable('salesrm') && $this->hasTable('salesrd')) {
            $srets = DB::select("
                SELECT salesrm.tdate, salesrm.billno AS docno, salesrd.weight, salesrd.stonewgt AS stone,
                       salesrd.qty, salesrd.rate
                FROM salesrm JOIN salesrd ON salesrd.slno = salesrm.slno
                WHERE salesrd.bcode = ? AND salesrm.control <= ?
            ", [$bcode, $gl]);
            foreach ($srets as $s) {
                $rows[] = [
                    'tdate' => $s->tdate, 'transaction' => 'Sales Return', 'docno' => $s->docno,
                    'qty' => (int) $s->qty, 'rate' => (float) $s->rate,
                    'weight' => (float) $s->weight, 'stone' => (float) $s->stone,
                ];
            }
        }

        // Smith/Jewl Issue & Receipt
        if ($this->hasTable('smithm') && $this->hasTable('smithd')) {
            $smithCols = array_map('strtolower', $this->columnList('smithd'));
            $hasGivrec = in_array('givrec', $smithCols);
            $hasBcode  = in_array('bcode', $smithCols);

            if ($hasBcode) {
                $sel = 'smithm.tdate, smithm.docno, smithd.weight, smithd.stonewgt AS stone, smithd.qty';
                if ($hasGivrec) $sel .= ', smithd.givrec';

                $smiths = DB::select("
                    SELECT {$sel}
                    FROM smithm JOIN smithd ON smithd.slno = smithm.slno
                    WHERE smithd.bcode = ? AND smithm.control <= ?
                ", [$bcode, $gl]);

                foreach ($smiths as $s) {
                    $gr = $hasGivrec ? ($s->givrec ?? '') : '';
                    $rows[] = [
                        'tdate' => $s->tdate,
                        'transaction' => ($gr === 'G') ? 'Smith/Jewl Issue' : 'Smith/Jewl Rcpt',
                        'docno' => $s->docno,
                        'qty' => (int) $s->qty, 'rate' => 0,
                        'weight' => (float) $s->weight, 'stone' => (float) $s->stone,
                    ];
                }
            }
        }

        // Transfer Out (itemadj.bcode)
        if ($this->hasTable('itemadj')) {
            $adjOut = DB::table('itemadj')
                ->where('bcode', $bcode)
                ->where('control', '<=', $gl)
                ->get(['tdate', 'fromwgt as weight', 'fromstwgt as stone', 'fromqty as qty']);
            foreach ($adjOut as $a) {
                $rows[] = [
                    'tdate' => $a->tdate, 'transaction' => 'Transfer Out', 'docno' => '',
                    'qty' => (int) $a->qty, 'rate' => 0,
                    'weight' => (float) $a->weight, 'stone' => (float) $a->stone,
                ];
            }

            // Transfer In (itemadj.tobcode)
            $adjIn = DB::table('itemadj')
                ->where('tobcode', $bcode)
                ->where('control', '<=', $gl)
                ->get(['tdate', 'towgt as weight', 'tostwgt as stone', 'toqty as qty']);
            foreach ($adjIn as $a) {
                $rows[] = [
                    'tdate' => $a->tdate, 'transaction' => 'Transfer In', 'docno' => '',
                    'qty' => (int) $a->qty, 'rate' => 0,
                    'weight' => (float) $a->weight, 'stone' => (float) $a->stone,
                ];
            }
        }

        // Sort by date, docno
        usort($rows, function ($a, $b) {
            $cmp = strcmp($a['tdate'] ?? '', $b['tdate'] ?? '');
            return $cmp !== 0 ? $cmp : strcmp($a['docno'] ?? '', $b['docno'] ?? '');
        });

        return $rows;
    }
}
