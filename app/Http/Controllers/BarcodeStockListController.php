<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarcodeStockListController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));

        $asOn      = $request->query('ason', '');
        $stkType   = $request->query('stk', 'Stock Only');
        $counter   = trim($request->query('counter', ''));
        $counterFilter = $request->has('counter_filter');
        $itemCode  = strtoupper(trim($request->query('item', '')));
        $grpCode   = trim($request->query('grp', ''));
        $subGrp    = trim($request->query('subgrp', ''));
        $report    = $request->query('report', 'Detailed Report');
        $wgtFrom   = $request->query('wgt_from', '');
        $wgtTo     = $request->query('wgt_to', '');
        $model     = strtoupper(trim($request->query('model', '')));
        $size      = trim($request->query('size', ''));
        $showData  = $request->has('show');

        $rows   = [];
        $totals = ['count' => 0, 'qty' => 0, 'weight' => 0.0, 'stweight' => 0.0, 'netwgt' => 0.0, 'bcnos' => 0];

        $counters = $this->hasTable('counter')
            ? DB::table('counter')->orderBy('name')->get(['code','name'])->toArray() : [];
        $groupCodes = [];
        if ($this->hasTable('items')) {
            $groupCodes = DB::table('items')->whereNotNull('grpcode')
                ->where('grpcode','<>','')->distinct()->orderBy('grpcode')->pluck('grpcode')->toArray();
        }

        $compRows = [];
        $compTotals = ['items'=>0,'slQty'=>0,'slWgt'=>0.0,'slNet'=>0.0,'bcQty'=>0,'bcWgt'=>0.0,'bcNet'=>0.0,'dQty'=>0,'dWgt'=>0.0,'dNet'=>0.0];

        if ($showData && $this->hasTable('barcode')) {
            if ($report === 'SL Comparison') {
                $compRows = $this->loadComparison($asOn);
                $compTotals['items'] = count($compRows);
                foreach ($compRows as $cr) {
                    $compTotals['slQty'] += $cr['slQty']; $compTotals['slWgt'] += $cr['slWgt']; $compTotals['slNet'] += $cr['slNet'];
                    $compTotals['bcQty'] += $cr['bcQty']; $compTotals['bcWgt'] += $cr['bcWgt']; $compTotals['bcNet'] += $cr['bcNet'];
                    $compTotals['dQty'] += $cr['dQty']; $compTotals['dWgt'] += $cr['dWgt']; $compTotals['dNet'] += $cr['dNet'];
                }
            } elseif (str_contains($report, 'Summary') || str_contains($report, 'Touch')) {
                $rows = $this->loadSummary($asOn, $stkType, $counterFilter ? $counter : null,
                    $itemCode, $grpCode, $subGrp, $report);
            } else {
                $rows = $this->loadDetailed($asOn, $stkType, $counterFilter ? $counter : null,
                    $itemCode, $grpCode, $subGrp, $wgtFrom, $wgtTo, $model, $size);
            }
            $totals['count'] = count($rows);
            foreach ($rows as $r) {
                $totals['qty']      += (int) ($r['qty'] ?? $r['tqty'] ?? 0);
                $totals['weight']   += (float) ($r['weight'] ?? $r['twgt'] ?? 0);
                $totals['stweight'] += (float) ($r['stweight'] ?? $r['tstwgt'] ?? 0);
                $totals['bcnos']    += (int) ($r['tbcnos'] ?? 1);
            }
            $totals['netwgt'] = $totals['weight'] - $totals['stweight'];
        }

        return view('barcode-list.stock-list', compact(
            'asOn', 'stkType', 'counter', 'counterFilter', 'itemCode',
            'grpCode', 'subGrp', 'report', 'wgtFrom', 'wgtTo', 'model', 'size',
            'showData', 'rows', 'totals', 'counters', 'groupCodes',
            'compRows', 'compTotals'
        ));
    }

    private function loadDetailed(string $asOn, string $stkType, ?string $counter,
        string $itemCode, string $grpCode, string $subGrp,
        string $wgtFrom, string $wgtTo, string $model, string $size): array
    {
        $bcCols = array_map('strtolower', $this->columnList('barcode'));
        $hasDate = ($asOn !== '' && strtotime($asOn) > mktime(0,0,0,1,1,1980));

        $select = [
            'barcode.bcode', 'barcode.icode', 'barcode.qty', 'barcode.weight',
            'barcode.stweight', 'barcode.stk', 'barcode.tdate',
            'items.code as itemcode', 'items.name as itemname',
        ];
        $opt = ['serialno','huid','sizemodel','model','stktouch','counter','stprice',
                'dmdwgt','dmdamt','sdate','subgrp','vap','mcrate','mc','rate','tamt'];
        foreach ($opt as $c) { if (in_array($c, $bcCols)) $select[] = "barcode.{$c}"; }

        $q = DB::table('barcode')
            ->join('items', 'barcode.icode', '=', 'items.code')
            ->select($select)
            ->where('barcode.control', '<=', $this->gilevel)
            ->orderBy('barcode.bcode');

        // Stock filter with as-on date logic
        if ($stkType === 'Stock Only') {
            if ($hasDate) {
                $q->where(function ($w) use ($asOn) {
                    $w->where('barcode.stk', 'Y')
                      ->orWhereNull('barcode.sdate')
                      ->orWhere('barcode.sdate', '>', $asOn);
                })->where(function ($w) use ($asOn) {
                    $w->where('barcode.tdate', '<=', $asOn);
                });
            } else {
                $q->where('barcode.stk', 'Y');
            }
        } elseif ($stkType === 'Not in Stock') {
            $q->where('barcode.stk', 'N');
        }

        if ($counter !== null && $counter !== '' && in_array('counter', $bcCols))
            $q->where('barcode.counter', $counter);
        if ($itemCode !== '') $q->where('barcode.icode', $itemCode);
        if ($grpCode !== '') $q->where('items.grpcode', $grpCode);
        if ($subGrp !== '' && in_array('subgrp', $bcCols)) $q->where('barcode.subgrp', $subGrp);
        if ($model !== '' && in_array('model', $bcCols)) $q->where('barcode.model', 'like', "%{$model}%");
        if ($size !== '' && in_array('sizemodel', $bcCols)) $q->where('barcode.sizemodel', $size);
        if ($wgtFrom !== '' && is_numeric($wgtFrom)) $q->where('barcode.weight', '>=', (float)$wgtFrom);
        if ($wgtTo !== '' && is_numeric($wgtTo)) $q->where('barcode.weight', '<=', (float)$wgtTo);

        return $q->get()->map(fn ($r) => (array) $r)->toArray();
    }

    private function loadSummary(string $asOn, string $stkType, ?string $counter,
        string $itemCode, string $grpCode, string $subGrp, string $report): array
    {
        $bcCols = array_map('strtolower', $this->columnList('barcode'));
        $hasDate = ($asOn !== '' && strtotime($asOn) > mktime(0,0,0,1,1,1980));

        $stkWhere = "";
        if ($stkType === 'Stock Only') $stkWhere = "AND bc.stk = 'Y'";
        elseif ($stkType === 'Not in Stock') $stkWhere = "AND bc.stk = 'N'";

        $counterWhere = ($counter !== null && $counter !== '') ? "AND bc.counter = " . DB::getPdo()->quote($counter) : "";
        $dateWhere = $hasDate ? "AND (bc.stk = 'Y' OR bc.sdate IS NULL OR bc.sdate > " . DB::getPdo()->quote($asOn) . ") AND bc.tdate <= " . DB::getPdo()->quote($asOn) : "";

        if (str_contains($report, 'Touch')) {
            // Touch Summary
            $rows = DB::select("
                SELECT barcode.stktouch,
                    SUM(barcode.qty) as tqty,
                    SUM(barcode.weight) as twgt,
                    SUM(barcode.stweight) as tstwgt,
                    COUNT(barcode.bcode) as tbcnos
                FROM barcode
                JOIN items ON items.code = barcode.icode
                WHERE barcode.control <= ?
                " . ($stkType === 'Stock Only' ? "AND barcode.stk = 'Y'" : ($stkType === 'Not in Stock' ? "AND barcode.stk = 'N'" : "")) . "
                " . ($counter ? "AND barcode.counter = ?" : "") . "
                GROUP BY barcode.stktouch
                ORDER BY barcode.stktouch
            ", $counter ? [$this->gilevel, $counter] : [$this->gilevel]);
        } else {
            // Item Summary (with optional subgrp grouping)
            $groupBy = str_contains($report, 'SubGrp') ? 'barcode.icode, barcode.subgrp' : 'barcode.icode';
            $selectExtra = str_contains($report, 'SubGrp') ? ', barcode.subgrp' : '';

            $params = [$this->gilevel];
            $cWhere = '';
            if ($counter) { $cWhere = "AND barcode.counter = ?"; $params[] = $counter; }

            $sWhere = $stkType === 'Stock Only' ? "AND barcode.stk = 'Y'" : ($stkType === 'Not in Stock' ? "AND barcode.stk = 'N'" : "");
            $iWhere = $itemCode !== '' ? "AND barcode.icode = " . DB::getPdo()->quote($itemCode) : '';
            $gWhere = $grpCode !== '' ? "AND items.grpcode = " . DB::getPdo()->quote($grpCode) : '';

            $rows = DB::select("
                SELECT barcode.icode, items.name as itemname, items.code as itemcode
                    {$selectExtra},
                    SUM(barcode.qty) as tqty,
                    SUM(barcode.weight) as twgt,
                    SUM(barcode.stweight) as tstwgt,
                    COUNT(barcode.bcode) as tbcnos
                FROM barcode
                JOIN items ON items.code = barcode.icode
                WHERE barcode.control <= ?
                {$sWhere} {$cWhere} {$iWhere} {$gWhere}
                GROUP BY {$groupBy}, items.name, items.code
                ORDER BY items.name
            ", $params);
        }

        return array_map(fn ($r) => (array) $r, $rows);
    }

    private function loadComparison(string $asOn): array
    {
        $gl = $this->gilevel;
        $opW = $gl === 1 ? 'opweight' : 'opweightb';
        $opQ = $gl === 1 ? 'opqty' : 'opqtyb';
        $opS = $gl === 1 ? 'opstonewgt' : 'opstonewgtb';

        // Pre-aggregate all transaction tables into temp arrays keyed by item code
        $sales = $this->bulkSum('salesd','salesm','code',$asOn,$gl);
        $srets = $this->bulkSum('salesrd','salesrm','code',$asOn,$gl);
        $purch = $this->bulkSumP('purchased','purchasem','code',$asOn,$gl);
        $prets = $this->bulkSumP('purchaserd','purchaserm','code',$asOn,$gl);
        $smithG = $this->bulkSmith($asOn,$gl,'G');
        $smithR = $this->bulkSmith($asOn,$gl,'R');
        $adjF = $this->bulkAdj($asOn,$gl,'from');
        $adjT = $this->bulkAdj($asOn,$gl,'to');

        // Barcode stock aggregated
        $bcStk = [];
        $bcRows = DB::table('barcode')->where('stk','Y')
            ->when(strtotime($asOn)>mktime(0,0,0,1,1,1980), fn($q)=>$q->where('tdate','<=',$asOn))
            ->groupBy('icode')
            ->selectRaw('icode, SUM(qty) as q, SUM(weight) as w, SUM(stweight) as s')
            ->get();
        foreach ($bcRows as $b) { $bcStk[$b->icode] = ['q'=>(int)$b->q,'w'=>(float)$b->w,'s'=>(float)$b->s]; }

        // Items
        $items = DB::table('items')
            ->where('disabled','<>',1)->where('showinstkrep','Y')
            ->orderBy('itype')->orderBy('name')
            ->get(['code','name',$opQ.' as oq',$opW.' as ow',$opS.' as os']);

        $result = [];
        foreach ($items as $it) {
            $c = $it->code;
            $z = ['q'=>0,'w'=>0,'s'=>0];
            $is=$sales[$c]??$z; $sr=$srets[$c]??$z; $rc=$purch[$c]??$z; $pr=$prets[$c]??$z;
            $gi=$smithG[$c]??$z; $gr=$smithR[$c]??$z; $af=$adjF[$c]??$z; $at=$adjT[$c]??$z;

            $slQ=(int)$it->oq - ($is['q']+$gi['q']+$af['q']) + ($rc['q']+$gr['q']+$at['q']+$sr['q']-$pr['q']);
            $slW=(float)$it->ow - ($is['w']+$gi['w']+$af['w']) + ($rc['w']+$gr['w']+$at['w']+$sr['w']-$pr['w']);
            $slS=(float)$it->os - ($is['s']+$gi['s']) + ($rc['s']+$gr['s']+$sr['s']);
            $slN=$slW-$slS;

            $bc=$bcStk[$c]??$z;
            $bcQ=$bc['q']; $bcW=$bc['w']; $bcN=$bcW-$bc['s'];

            if($slQ||round($slW,3)||$bcQ||round($bcW,3)){
                $result[]=['code'=>$c,'name'=>$it->name,
                    'slQty'=>$slQ,'slWgt'=>round($slW,3),'slNet'=>round($slN,3),
                    'bcQty'=>$bcQ,'bcWgt'=>round($bcW,3),'bcNet'=>round($bcN,3),
                    'dQty'=>$slQ-$bcQ,'dWgt'=>round($slW-$bcW,3),'dNet'=>round($slN-$bcN,3)];
            }
        }
        return $result;
    }

    private function bulkSum(string $d,string $m,string $codeCol,string $dt,int $gl):array{
        if(!$this->hasTable($d)||!$this->hasTable($m))return[];
        $rows=DB::table($d)->join($m,"$m.slno",'=',"$d.slno")
            ->where("$m.control",'<=',$gl)->where("$m.tdate",'<=',$dt)
            ->groupBy("$d.$codeCol")
            ->selectRaw("$d.$codeCol as c,SUM($d.qty) as q,SUM($d.weight) as w,SUM($d.stonewgt) as s")->get();
        $r=[];foreach($rows as $row)$r[$row->c]=['q'=>(int)$row->q,'w'=>(float)$row->w,'s'=>(float)$row->s];return $r;
    }
    private function bulkSumP(string $d,string $m,string $codeCol,string $dt,int $gl):array{
        if(!$this->hasTable($d)||!$this->hasTable($m))return[];
        $sc=Schema::hasColumn($d,'stonewgt')?'stonewgt':(Schema::hasColumn($d,'stwgt')?'stwgt':null);
        $se=$sc?"SUM($d.$sc)":"0";
        $rows=DB::table($d)->join($m,"$m.slno",'=',"$d.slno")
            ->where("$m.control",'<=',$gl)->where("$m.tdate",'<=',$dt)
            ->groupBy("$d.$codeCol")
            ->selectRaw("$d.$codeCol as c,SUM($d.qty) as q,SUM($d.weight) as w,$se as s")->get();
        $r=[];foreach($rows as $row)$r[$row->c]=['q'=>(int)$row->q,'w'=>(float)$row->w,'s'=>(float)$row->s];return $r;
    }
    private function bulkSmith(string $dt,int $gl,string $gr):array{
        if(!$this->hasTable('smithd')||!$this->hasTable('smithm'))return[];
        $rows=DB::table('smithd')->join('smithm','smithm.slno','=','smithd.slno')
            ->where('smithm.control','<=',$gl)->where('smithd.givrec',$gr)->where('smithm.tdate','<=',$dt)
            ->groupBy('smithd.code')
            ->selectRaw('smithd.code as c,SUM(smithd.qty) as q,SUM(smithd.weight) as w,SUM(smithd.stonewgt) as s')->get();
        $r=[];foreach($rows as $row)$r[$row->c]=['q'=>(int)$row->q,'w'=>(float)$row->w,'s'=>(float)$row->s];return $r;
    }
    private function bulkAdj(string $dt,int $gl,string $dir):array{
        if(!$this->hasTable('itemadj'))return[];
        $cc=$dir==='from'?'fromcode':'tocode';$qc=$dir==='from'?'fromqty':'toqty';
        $wc=$dir==='from'?'fromwgt':'towgt';$sc=$dir==='from'?'fromstwgt':'tostwgt';
        $rows=DB::table('itemadj')->where('control','<=',$gl)->where('tdate','<=',$dt)
            ->groupBy($cc)->selectRaw("$cc as c,SUM($qc) as q,SUM($wc) as w,SUM($sc) as s")->get();
        $r=[];foreach($rows as $row)$r[$row->c]=['q'=>(int)$row->q,'w'=>(float)$row->w,'s'=>(float)$row->s];return $r;
    }
}
