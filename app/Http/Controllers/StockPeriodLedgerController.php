<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockPeriodLedgerController extends Controller
{
    private int $gilevel = 1;

    // ─── Period Stock Ledger ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = $this->resolveControl($request);

        $dateFrom   = $request->input('date_from', date('Y-m-d'));
        $dateTo     = $request->input('date_to',   date('Y-m-d'));
        $rptType    = (string)$request->input('rpt_type',  '');   // '' | 'G' | 'SG'
        $metalType  = (string)$request->input('metal_type','All');
        $ornType    = (string)$request->input('orn_type',  'All');
        $stkType    = (string)$request->input('stk_type',  'All');
        $grpCode    = (string)$request->input('grp_code',  '');
        $subGrpCode = (string)$request->input('subgrp_code','');
        $showData    = $request->has('show');
        $sortOnCode  = $request->boolean('sort_on_code');
        $clstkOnly   = $showData ? $request->boolean('clstk_only') : true;
        $netWgtModel = $request->boolean('net_wgt_model');
        $withStone   = $request->boolean('with_stone');
        $withDmd     = $request->boolean('with_dmd');

        $rows   = [];
        $totals = array_fill_keys(['G','S','O','P'], $this->blankTotals());

        if ($showData && $this->hasTable('items')) {
            $items = $this->loadItems($metalType, $ornType, $grpCode, $subGrpCode, $sortOnCode);
            $grouped = [];   // for G and SG modes: keyed by grpcode or subgrpcode

            foreach ($items as $item) {
                $stk = $this->calcItemStock($item['code'], $dateFrom, $dateTo, $netWgtModel, $withStone || $withDmd);
                $row = array_merge($item, $stk);

                $t = in_array($item['itype'] ?? '', ['G','S','P'], true) ? $item['itype'] : 'O';
                foreach (['opqty','opwgt','opswgt','issuedqty','issuedwgt','issuedswgt','rcvdqty','rcvdwgt','rcvdswgt','clqty','clwgt','clswgt'] as $k) {
                    $totals[$t][$k] += $stk[$k];
                }

                if ($rptType === 'G') {
                    $gk = $item['grpcode'] ?: '(none)';
                    if (!isset($grouped[$gk])) {
                        $grouped[$gk] = ['groupkey'=>$gk,'itype'=>$t] + $this->blankTotals();
                    }
                    foreach (['opqty','opwgt','opswgt','issuedqty','issuedwgt','issuedswgt','rcvdqty','rcvdwgt','rcvdswgt','clqty','clwgt','clswgt'] as $k) {
                        $grouped[$gk][$k] += $stk[$k];
                    }
                } elseif ($rptType === 'SG') {
                    $gk = $item['subgrpcode'] ?: '(none)';
                    if (!isset($grouped[$gk])) {
                        $grouped[$gk] = ['groupkey'=>$gk,'itype'=>$t] + $this->blankTotals();
                    }
                    foreach (['opqty','opwgt','opswgt','issuedqty','issuedwgt','issuedswgt','rcvdqty','rcvdwgt','rcvdswgt','clqty','clwgt','clswgt'] as $k) {
                        $grouped[$gk][$k] += $stk[$k];
                    }
                } else {
                    if (!$this->passesStockFilter($row, $stkType)) continue;
                    $rows[] = $row;
                }
            }

            if ($rptType === 'G' || $rptType === 'SG') {
                ksort($grouped);
                foreach ($grouped as $row) {
                    if (!$this->passesStockFilter($row, $stkType)) continue;
                    $rows[] = $row;
                }
            }
        }

        return view('stock.period-ledger', [
            'rows'         => $rows,
            'totals'       => $totals,
            'dateFrom'     => $dateFrom,
            'dateTo'       => $dateTo,
            'rptType'      => $rptType,
            'metalType'    => $metalType,
            'ornType'      => $ornType,
            'stkType'      => $stkType,
            'grpCode'      => $grpCode,
            'subGrpCode'   => $subGrpCode,
            'sortOnCode'   => $sortOnCode,
            'clstkOnly'    => $clstkOnly,
            'netWgtModel'  => $netWgtModel,
            'withStone'    => $withStone,
            'withDmd'      => $withDmd,
            'showData'     => $showData,
            'groupCodes'   => $this->fetchGroupCodes(),
            'subGroupCodes'=> $this->fetchSubGroupCodes(),
        ]);
    }

    // ─── Item History ────────────────────────────────────────────────────────

    public function itemHistory(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = $this->resolveControl($request);

        $scode        = strtoupper(trim((string) $request->input('code', '')));
        $dateFrom     = $request->input('date_from', date('Y-m-d'));
        $dateTo       = $request->input('date_to',   date('Y-m-d'));
        $stktype      = trim((string) $request->input('stktype', ''));
        $withStkTouch  = $request->input('with_stktouch') === '1';
        $noStkTouchWgt = $request->input('no_stk_touch_wgt') === '1';
        $grpcode       = strtoupper(trim((string) $request->input('grpcode', '')));
        $showLedger    = $request->input('show_ledger') === '1';
        $showed        = $request->has('code') && $scode !== '';

        $stockTypes = $this->hasTable('stktype')
            ? DB::table('stktype')->orderBy('code')->get()
            : collect();
        $groupCodes = $this->fetchGroupCodes();

        $itemName = '';
        $opQty = 0; $opWgt = 0.0;
        $clQty = 0; $clWgt = 0.0;
        $sections    = [];
        $ledgerRows  = [];

        if ($showed && $this->hasTable('items')) {
            $itemName = (string)(DB::table('items')->where('code', $scode)->value('name') ?? $scode);
            $op = $this->openingStock($scode, $dateFrom);
            $opQty = $op['qty'];
            $opWgt = $op['wgt'];

            $st = $stktype !== '' ? $stktype : null;
            $wst = $withStkTouch && !$noStkTouchWgt;

            if ($showLedger) {
                $ledgerRows = $this->buildStockLedger($scode, $dateFrom, $dateTo, $opQty, $opWgt, $st, $wst);
                if ($ledgerRows) {
                    $last = end($ledgerRows);
                    $clQty = $last['tclstkqty'];
                    $clWgt = $last['tclstkwgt'];
                }
            } else {
                $rQty = $opQty; $rWgt = $opWgt;
                $sections = $this->buildHistorySections($scode, $dateFrom, $dateTo, $rQty, $rWgt, $st, $wst);
                $clQty = $rQty; $clWgt = $rWgt;
            }
        }

        return view('stock.item-history', compact(
            'scode','dateFrom','dateTo','itemName',
            'opQty','opWgt','clQty','clWgt','sections','ledgerRows',
            'stockTypes','groupCodes','stktype','grpcode',
            'withStkTouch','noStkTouchWgt','showLedger','showed'
        ));
    }

    // ─── Ledger helpers ──────────────────────────────────────────────────────

    private function blankTotals(): array
    {
        return ['opqty'=>0,'opwgt'=>0.0,'opswgt'=>0.0,
                'issuedqty'=>0,'issuedwgt'=>0.0,'issuedswgt'=>0.0,
                'rcvdqty'=>0,'rcvdwgt'=>0.0,'rcvdswgt'=>0.0,
                'clqty'=>0,'clwgt'=>0.0,'clswgt'=>0.0];
    }

    private function loadItems(string $metalType, string $ornType,
                               string $grpCode, string $subGrpCode, bool $sortOnCode): array
    {
        $cols = array_map('strtolower', $this->columnList('items'));

        $q = DB::table('items')->select(
            array_values(array_intersect(
                ['code','name','itype','grpcode','subgrpcode','ornament'],
                $cols
            ))
        );

        if (in_array('disabled', $cols)) {
            $q->where(fn($qq) => $qq->where('disabled','!=',1)->orWhereNull('disabled'));
        }
        if (in_array('showinstkrep', $cols)) {
            $q->where(fn($qq) => $qq->where('showinstkrep','!=','N')->orWhereNull('showinstkrep'));
        }
        if ($metalType !== 'All' && in_array('itype', $cols)) {
            $q->where('itype', substr($metalType, 0, 1));
        }
        if ($ornType === 'Ornaments Only' && in_array('ornament', $cols)) {
            $q->where('ornament','Y');
        } elseif ($ornType === 'Not Ornaments' && in_array('ornament', $cols)) {
            $q->where('ornament','N');
        }
        if ($grpCode !== '' && in_array('grpcode', $cols)) {
            $q->where('grpcode', $grpCode);
        }
        if ($subGrpCode !== '' && in_array('subgrpcode', $cols)) {
            $q->where('subgrpcode', $subGrpCode);
        }

        if ($sortOnCode) {
            $q->orderBy('code');
        } else {
            if (in_array('itype', $cols)) $q->orderBy('itype');
            $q->orderBy('name');
        }

        return $q->get()->map(fn($r) => [
            'code'       => (string)($r->code ?? ''),
            'name'       => (string)($r->name ?? ''),
            'itype'      => (string)($r->itype ?? ''),
            'grpcode'    => (string)($r->grpcode ?? ''),
            'subgrpcode' => (string)($r->subgrpcode ?? ''),
            'ornament'   => (string)($r->ornament ?? ''),
        ])->all();
    }

    private function passesStockFilter(array $row, string $stkType): bool
    {
        $clw = (float)$row['clwgt'];
        $iw  = (float)$row['issuedwgt'];
        $rw  = (float)$row['rcvdwgt'];
        if (str_contains($stkType,'With Stock Only') && $clw <= 0) return false;
        if (str_contains($stkType,'With -ve')        && $clw >= 0) return false;
        if (str_contains($stkType,'With Zero')       && $clw != 0) return false;
        if (str_contains($stkType,'With Not Zero')   && $clw == 0) return false;
        if (str_contains($stkType,'Non Zero Stock or Transaction') && $iw <= 0 && $rw <= 0 && $clw == 0) return false;
        if (str_contains($stkType,'With Transaction')  && $iw <= 0 && $rw <= 0) return false;
        return true;
    }

    private function fetchGroupCodes(): array
    {
        if (!$this->hasTable('items')) return [];
        $cols = array_map('strtolower', $this->columnList('items'));
        if (!in_array('grpcode', $cols)) return [];
        return DB::table('items')->whereNotNull('grpcode')->where('grpcode','!=','')
            ->orderBy('grpcode')->pluck('grpcode')
            ->map(fn($v) => strtoupper(trim((string)$v)))->filter()->unique()->values()->all();
    }

    private function fetchSubGroupCodes(): array
    {
        if (!$this->hasTable('items')) return [];
        $cols = array_map('strtolower', $this->columnList('items'));
        if (!in_array('subgrpcode', $cols)) return [];
        return DB::table('items')->whereNotNull('subgrpcode')->where('subgrpcode','!=','')
            ->orderBy('subgrpcode')->pluck('subgrpcode')
            ->map(fn($v) => strtoupper(trim((string)$v)))->filter()->unique()->values()->all();
    }

    // ─── calcItemStock (converted from PDO calcItemStock.php) ────────────────

    private function resolveControl(Request $request): int
    {
        $queryValue = trim((string) $request->query('gisemi', ''));
        if ($queryValue !== '' && is_numeric($queryValue)) {
            return max(1, (int) $queryValue);
        }

        $sessionValue = $request->session()->get('semi');
        if ($sessionValue === null || $sessionValue === '') {
            $sessionValue = $request->session()->get('control');
        }
        if ($sessionValue === null || $sessionValue === '') {
            $sessionValue = $request->session()->get('gilevel', 2);
        }

        return max(1, (int) $sessionValue);
    }

    private function calcItemStock(string $scode, string $d1, string $d2,
                                   bool $netWgt = false, bool $withStone = false): array
    {
        $g = $this->gilevel;

        // Net-weight SQL fragments (trusted server-side strings, safe to interpolate)
        $wS  = $netWgt ? '(d.weight - COALESCE(d.stonewgt,0))' : 'd.weight';   // salesd/salesrd/smithd/repaird
        $wP  = $netWgt ? '(d.weight - COALESCE(d.stwgt,0))'    : 'd.weight';   // purchased/purchaserd
        $wRI = $netWgt ? '(d.issuedwgt - COALESCE(d.issuedstwgt,0))' : 'd.issuedwgt'; // refineryd issued
        $wAF = $netWgt ? '(fromwgt - COALESCE(fromstwgt,0))'   : 'fromwgt';    // itemadj from-side
        $wAT = $netWgt ? '(towgt - COALESCE(tostwgt,0))'       : 'towgt';      // itemadj to-side
        $wO  = $netWgt ? '(weight - COALESCE(stonewgt,0))'     : 'weight';     // orderdga

        // Stone-weight SQL fragments ('0' when stone off — keeps query valid, result = 0)
        $sS  = $withStone ? 'COALESCE(SUM(d.stonewgt),0)' : '0'; // salesd/salesrd/smithd/repaird
        $sP  = $withStone ? 'COALESCE(SUM(d.stwgt),0)'    : '0'; // purchased/purchaserd
        $sAF = $withStone ? 'fromstwgt' : '0'; // inside CASE WHEN for itemadj
        $sAT = $withStone ? 'tostwgt'   : '0'; // inside CASE WHEN for itemadj
        $sO  = $withStone ? 'COALESCE(SUM(stonewgt),0)'   : '0'; // orderdga

        // Opening base from items table
        $opq = 0; $opw = 0.0; $opSw = 0.0;
        if ($this->hasTable('items')) {
            $cols = array_map('strtolower', $this->columnList('items'));
            $qc = $g === 1 ? 'opqty'   : 'opqtyb';
            $wc = $g === 1 ? 'opweight': 'opweightb';
            $row = DB::table('items')->where('code', $scode)->first();
            if ($row) {
                if (in_array($qc, $cols)) $opq = (int)($row->$qc ?? 0);
                if (in_array($wc, $cols)) $opw = (float)($row->$wc ?? 0);
                $sc = $g === 1 ? 'opstonewgt' : 'opstonewgtb';
                if ($netWgt    && in_array($sc, $cols)) $opw  -= (float)($row->$sc ?? 0);
                if ($withStone && in_array($sc, $cols)) $opSw  = (float)($row->$sc ?? 0);
            }
        }

        // ── Pre-period adjustments ──
        if ($this->hasTable('salesd') && $this->hasTable('salesm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wS),0) AS w, $sS AS s
                FROM salesd d JOIN salesm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate<? AND (TRIM(d.jcode)='' OR d.jcode IS NULL)",
                [$scode,$g,$d1]);
            $opq -= $r->q; $opw -= $r->w; $opSw -= $r->s;
        }
        if ($this->hasTable('salesrd') && $this->hasTable('salesrm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wS),0) AS w, $sS AS s
                FROM salesrd d JOIN salesrm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate<?",[$scode,$g,$d1]);
            $opq += $r->q; $opw += $r->w; $opSw += $r->s;
        }
        if ($this->hasTable('purchased') && $this->hasTable('purchasem')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wP),0) AS w, $sP AS s
                FROM purchased d JOIN purchasem m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate<?",[$scode,$g,$d1]);
            $opq += $r->q; $opw += $r->w; $opSw += $r->s;
        }
        if ($this->hasTable('purchaserd') && $this->hasTable('purchaserm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wP),0) AS w, $sP AS s
                FROM purchaserd d JOIN purchaserm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate<?",[$scode,$g,$d1]);
            $opq -= $r->q; $opw -= $r->w; $opSw -= $r->s;
        }
        if ($this->hasTable('smithd') && $this->hasTable('smithm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wS),0) AS w, $sS AS s
                FROM smithd d JOIN smithm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND d.givrec='G' AND m.tdate<?",[$scode,$g,$d1]);
            $opq -= $r->q; $opw -= $r->w; $opSw -= $r->s;
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wS),0) AS w, $sS AS s
                FROM smithd d JOIN smithm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND d.givrec='R' AND m.tdate<?",[$scode,$g,$d1]);
            $opq += $r->q; $opw += $r->w; $opSw += $r->s;
        }
        if ($this->hasTable('repaird') && $this->hasTable('repairm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wS),0) AS w, $sS AS s FROM repaird d
                JOIN repairm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND d.givrec='R' AND m.tdate<?",[$scode,$g,$d1]);
            $opq += $r->q; $opw += $r->w; $opSw += $r->s;
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wS),0) AS w, $sS AS s FROM repaird d
                JOIN repairm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND d.givrec='G' AND m.tdate<?",[$scode,$g,$d1]);
            $opq -= $r->q; $opw -= $r->w; $opSw -= $r->s;
        }
        if ($this->hasTable('refineryd') && $this->hasTable('refinerym')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.issuedqty),0) AS iq,
                COALESCE(SUM($wRI),0) AS iw,
                COALESCE(SUM(d.rcvdqty),0) AS rq,
                COALESCE(SUM(COALESCE(d.ao, d.rcvdwgt-d.bottlestk-d.testpcs, d.rcvdwgt)),0) AS rw
                FROM refineryd d JOIN refinerym m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate<? AND COALESCE(m.status,1)<>0",[$scode,$g,$d1]);
            $opq -= $r->iq; $opw -= $r->iw;
            $opq += $r->rq; $opw += $r->rw;
        }
        if ($this->hasTable('itemadj')) {
            $r = $this->rawOne("SELECT
                COALESCE(SUM(CASE WHEN fromcode=? THEN fromqty ELSE 0 END),0) AS fq,
                COALESCE(SUM(CASE WHEN fromcode=? THEN $wAF   ELSE 0 END),0) AS fw,
                COALESCE(SUM(CASE WHEN fromcode=? THEN $sAF   ELSE 0 END),0) AS fs,
                COALESCE(SUM(CASE WHEN tocode=?   THEN toqty  ELSE 0 END),0) AS tq,
                COALESCE(SUM(CASE WHEN tocode=?   THEN $wAT   ELSE 0 END),0) AS tw,
                COALESCE(SUM(CASE WHEN tocode=?   THEN $sAT   ELSE 0 END),0) AS ts
                FROM itemadj WHERE control<=? AND tdate<?",
                [$scode,$scode,$scode,$scode,$scode,$scode,$g,$d1]);
            $opq -= $r->fq; $opw -= $r->fw; $opSw -= $r->fs;
            $opq += $r->tq; $opw += $r->tw; $opSw += $r->ts;
        }
        if ($this->hasTable('orderdga') && $this->hasTable('orderm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wO),0) AS w, $sO AS s
                FROM orderdga d
                JOIN orderm m ON m.slno = d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate<? AND COALESCE(m.status,1)<>0",[$scode,$g,$d1]);
            $opq += $r->q; $opw += $r->w; $opSw += $r->s;
        }

        // ── Period (issued / received) ──
        $iqty = 0; $iwgt = 0.0; $iSwgt = 0.0;
        $rqty = 0; $rwgt = 0.0; $rSwgt = 0.0;

        if ($this->hasTable('salesd') && $this->hasTable('salesm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wS),0) AS w, $sS AS s
                FROM salesd d JOIN salesm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=?
                AND (TRIM(d.jcode)='' OR d.jcode IS NULL)",[$scode,$g,$d1,$d2]);
            $iqty += $r->q; $iwgt += $r->w; $iSwgt += $r->s;
        }
        if ($this->hasTable('salesrd') && $this->hasTable('salesrm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wS),0) AS w, $sS AS s
                FROM salesrd d JOIN salesrm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=?",[$scode,$g,$d1,$d2]);
            $rqty += $r->q; $rwgt += $r->w; $rSwgt += $r->s;
        }
        if ($this->hasTable('purchased') && $this->hasTable('purchasem')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wP),0) AS w, $sP AS s
                FROM purchased d JOIN purchasem m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=?",[$scode,$g,$d1,$d2]);
            $rqty += $r->q; $rwgt += $r->w; $rSwgt += $r->s;
        }
        if ($this->hasTable('purchaserd') && $this->hasTable('purchaserm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wP),0) AS w, $sP AS s
                FROM purchaserd d JOIN purchaserm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=?",[$scode,$g,$d1,$d2]);
            $iqty += $r->q; $iwgt += $r->w; $iSwgt += $r->s;
        }
        if ($this->hasTable('smithd') && $this->hasTable('smithm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wS),0) AS w, $sS AS s
                FROM smithd d JOIN smithm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND d.givrec='G' AND m.tdate>=? AND m.tdate<=?",[$scode,$g,$d1,$d2]);
            $iqty += $r->q; $iwgt += $r->w; $iSwgt += $r->s;
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wS),0) AS w, $sS AS s
                FROM smithd d JOIN smithm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND d.givrec='R' AND m.tdate>=? AND m.tdate<=?",[$scode,$g,$d1,$d2]);
            $rqty += $r->q; $rwgt += $r->w; $rSwgt += $r->s;
        }
        if ($this->hasTable('repaird') && $this->hasTable('repairm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wS),0) AS w, $sS AS s FROM repaird d
                JOIN repairm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND d.givrec='G' AND m.tdate>=? AND m.tdate<=?",[$scode,$g,$d1,$d2]);
            $iqty += $r->q; $iwgt += $r->w; $iSwgt += $r->s;
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wS),0) AS w, $sS AS s FROM repaird d
                JOIN repairm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND d.givrec='R' AND m.tdate>=? AND m.tdate<=?",[$scode,$g,$d1,$d2]);
            $rqty += $r->q; $rwgt += $r->w; $rSwgt += $r->s;
        }
        if ($this->hasTable('refineryd') && $this->hasTable('refinerym')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.issuedqty),0) AS iq,
                COALESCE(SUM($wRI),0) AS iw,
                COALESCE(SUM(d.rcvdqty),0) AS rq,
                COALESCE(SUM(COALESCE(d.ao, d.rcvdwgt-d.bottlestk-d.testpcs, d.rcvdwgt)),0) AS rw
                FROM refineryd d JOIN refinerym m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=? AND COALESCE(m.status,1)<>0",[$scode,$g,$d1,$d2]);
            $iqty += $r->iq; $iwgt += $r->iw;
            $rqty += $r->rq; $rwgt += $r->rw;
        }
        if ($this->hasTable('itemadj')) {
            $r = $this->rawOne("SELECT
                COALESCE(SUM(CASE WHEN fromcode=? THEN fromqty ELSE 0 END),0) AS fq,
                COALESCE(SUM(CASE WHEN fromcode=? THEN $wAF   ELSE 0 END),0) AS fw,
                COALESCE(SUM(CASE WHEN fromcode=? THEN $sAF   ELSE 0 END),0) AS fs,
                COALESCE(SUM(CASE WHEN tocode=?   THEN toqty  ELSE 0 END),0) AS tq,
                COALESCE(SUM(CASE WHEN tocode=?   THEN $wAT   ELSE 0 END),0) AS tw,
                COALESCE(SUM(CASE WHEN tocode=?   THEN $sAT   ELSE 0 END),0) AS ts
                FROM itemadj WHERE control<=? AND tdate>=? AND tdate<=?",
                [$scode,$scode,$scode,$scode,$scode,$scode,$g,$d1,$d2]);
            $iqty += $r->fq; $iwgt += $r->fw; $iSwgt += $r->fs;
            $rqty += $r->tq; $rwgt += $r->tw; $rSwgt += $r->ts;
        }
        if ($this->hasTable('orderdga') && $this->hasTable('orderm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM($wO),0) AS w, $sO AS s
                FROM orderdga d
                JOIN orderm m ON m.slno = d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=? AND COALESCE(m.status,1)<>0",[$scode,$g,$d1,$d2]);
            $rqty += $r->q; $rwgt += $r->w; $rSwgt += $r->s;
        }

        $clqty  = $opq  + $rqty  - $iqty;
        $clwgt  = $opw  + $rwgt  - $iwgt;
        $clSwgt = $opSw + $rSwgt - $iSwgt;

        return [
            'opqty'      => (int)$opq,
            'opwgt'      => round((float)$opw, 3),
            'opswgt'     => round($opSw, 3),
            'issuedqty'  => (int)$iqty,
            'issuedwgt'  => round((float)$iwgt, 3),
            'issuedswgt' => round($iSwgt, 3),
            'rcvdqty'    => (int)$rqty,
            'rcvdwgt'    => round((float)$rwgt, 3),
            'rcvdswgt'   => round($rSwgt, 3),
            'clqty'      => (int)$clqty,
            'clwgt'      => round((float)$clwgt, 3),
            'clswgt'     => round($clSwgt, 3),
        ];
    }

    // ─── Item History helpers ────────────────────────────────────────────────

    private function openingStock(string $scode, string $d1): array
    {
        $g = $this->gilevel;
        $opq = 0; $opw = 0.0;

        if (!$this->hasTable('items')) return ['qty'=>0,'wgt'=>0.0];
        $cols = array_map('strtolower', $this->columnList('items'));
        $qc = $g === 1 ? 'opqty' : 'opqtyb';
        $wc = $g === 1 ? 'opweight' : 'opweightb';
        $row = DB::table('items')->where('code', $scode)->first();
        if ($row) {
            if (in_array($qc,$cols)) $opq = (int)($row->$qc ?? 0);
            if (in_array($wc,$cols)) $opw = (float)($row->$wc ?? 0);
        }

        if ($this->hasTable('salesd') && $this->hasTable('salesm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM(d.weight),0) AS w
                FROM salesd d JOIN salesm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate<? AND (TRIM(d.jcode)='' OR d.jcode IS NULL)",[$scode,$g,$d1]);
            $opq -= $r->q; $opw -= $r->w;
        }
        if ($this->hasTable('salesrd') && $this->hasTable('salesrm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM(d.weight),0) AS w
                FROM salesrd d JOIN salesrm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate<?",[$scode,$g,$d1]);
            $opq += $r->q; $opw += $r->w;
        }
        if ($this->hasTable('purchased') && $this->hasTable('purchasem')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM(d.weight),0) AS w
                FROM purchased d JOIN purchasem m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate<?",[$scode,$g,$d1]);
            $opq += $r->q; $opw += $r->w;
        }
        if ($this->hasTable('purchaserd') && $this->hasTable('purchaserm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM(d.weight),0) AS w
                FROM purchaserd d JOIN purchaserm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate<?",[$scode,$g,$d1]);
            $opq -= $r->q; $opw -= $r->w;
        }
        if ($this->hasTable('smithd') && $this->hasTable('smithm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM(d.weight),0) AS w
                FROM smithd d JOIN smithm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND d.givrec='R' AND m.tdate<?",[$scode,$g,$d1]);
            $opq += $r->q; $opw += $r->w;
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM(d.weight),0) AS w
                FROM smithd d JOIN smithm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND d.givrec='G' AND m.tdate<?",[$scode,$g,$d1]);
            $opq -= $r->q; $opw -= $r->w;
        }
        if ($this->hasTable('refineryd') && $this->hasTable('refinerym')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.issuedqty),0) AS iq, COALESCE(SUM(d.issuedwgt),0) AS iw,
                COALESCE(SUM(d.rcvdqty),0) AS rq,
                COALESCE(SUM(COALESCE(d.ao, d.rcvdwgt-d.bottlestk-d.testpcs, d.rcvdwgt)),0) AS rw
                FROM refineryd d JOIN refinerym m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate<? AND COALESCE(m.status,1)<>0",[$scode,$g,$d1]);
            $opq -= $r->iq; $opw -= $r->iw;
            $opq += $r->rq; $opw += $r->rw;
        }
        if ($this->hasTable('itemadj')) {
            $r = $this->rawOne("SELECT
                COALESCE(SUM(CASE WHEN fromcode=? THEN fromqty ELSE 0 END),0) AS fq,
                COALESCE(SUM(CASE WHEN fromcode=? THEN fromwgt  ELSE 0 END),0) AS fw,
                COALESCE(SUM(CASE WHEN tocode=?   THEN toqty   ELSE 0 END),0) AS tq,
                COALESCE(SUM(CASE WHEN tocode=?   THEN towgt   ELSE 0 END),0) AS tw
                FROM itemadj WHERE control<=? AND tdate<?",
                [$scode,$scode,$scode,$scode,$g,$d1]);
            $opq -= $r->fq; $opw -= $r->fw;
            $opq += $r->tq; $opw += $r->tw;
        }
        if ($this->hasTable('repaird') && $this->hasTable('repairm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.weight),0) AS w FROM repaird d
                JOIN repairm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND d.givrec='G' AND m.tdate<?",[$scode,$g,$d1]);
            $opw -= $r->w;
            $r = $this->rawOne("SELECT COALESCE(SUM(d.weight),0) AS w FROM repaird d
                JOIN repairm m ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND d.givrec='R' AND m.tdate<?",[$scode,$g,$d1]);
            $opw += $r->w;
        }
        if ($this->hasTable('orderdga') && $this->hasTable('orderm')) {
            $r = $this->rawOne("SELECT COALESCE(SUM(d.qty),0) AS q, COALESCE(SUM(d.weight),0) AS w
                FROM orderdga d
                JOIN orderm m ON m.slno = d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate<? AND COALESCE(m.status,1)<>0",[$scode,$g,$d1]);
            $opq += $r->q; $opw += $r->w;
        }

        return ['qty' => (int)$opq, 'wgt' => round((float)$opw, 3)];
    }

    // ─── Stock Ledger (d_stockledger_simple_history) ─────────────────────────
    // Day-by-day summary: Date | Op.Qty | Op.Wgt | Rcvd.Qty | Rcvd.Wgt | Issued.Qty | Issued.Wgt | Cl.Qty | Cl.Wgt
    private function buildStockLedger(string $scode, string $d1, string $d2,
                                      int $opQty, float $opWgt,
                                      ?string $stktype, bool $withStkTouch): array
    {
        $g   = $this->gilevel;
        $stF = $stktype !== null ? ' AND d.stktype = ?' : '';
        $stB = $stktype !== null ? [$stktype] : [];
        $byDate = []; // date => [rqty,rwgt,iqty,iwgt]

        $add = function(string $date, int $rq, float $rw, int $iq, float $iw) use (&$byDate) {
            if (!isset($byDate[$date])) $byDate[$date] = [0, 0.0, 0, 0.0];
            $byDate[$date][0] += $rq; $byDate[$date][1] += $rw;
            $byDate[$date][2] += $iq; $byDate[$date][3] += $iw;
        };

        // Purchases → received
        if ($this->hasTable('purchased') && $this->hasTable('purchasem')) {
            foreach (DB::select("SELECT m.tdate, SUM(d.qty) q, SUM(d.weight) w FROM purchasem m JOIN purchased d ON m.slno=d.slno WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=?{$stF} GROUP BY m.tdate", array_merge([$scode,$g,$d1,$d2],$stB)) as $r)
                $add($r->tdate, (int)$r->q, (float)$r->w, 0, 0.0);
        }
        // Purchase Returns → issued
        if ($this->hasTable('purchaserd') && $this->hasTable('purchaserm')) {
            foreach (DB::select("SELECT m.tdate, SUM(d.qty) q, SUM(d.weight) w FROM purchaserm m JOIN purchaserd d ON m.slno=d.slno WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=?{$stF} GROUP BY m.tdate", array_merge([$scode,$g,$d1,$d2],$stB)) as $r)
                $add($r->tdate, 0, 0.0, (int)$r->q, (float)$r->w);
        }
        // Smith Received → received; Smith Issued → issued (with optional stk touch)
        if ($this->hasTable('smithd') && $this->hasTable('smithm')) {
            $hasST = in_array('stktouch', array_map('strtolower', $this->columnList('smithd')));
            foreach (DB::select("SELECT m.tdate, SUM(d.qty) q, SUM(d.weight) w FROM smithm m JOIN smithd d ON m.slno=d.slno WHERE m.control<=? AND d.code=? AND m.tdate>=? AND m.tdate<=? AND d.givrec='R'{$stF} GROUP BY m.tdate", array_merge([$g,$scode,$d1,$d2],$stB)) as $r)
                $add($r->tdate, (int)$r->q, (float)$r->w, 0, 0.0);
            $wCol = ($withStkTouch && $hasST) ? "SUM(d.weight*COALESCE(d.stktouch,d.touch,0)/100)" : "SUM(d.weight)";
            foreach (DB::select("SELECT m.tdate, SUM(d.qty) q, {$wCol} w FROM smithm m JOIN smithd d ON m.slno=d.slno WHERE m.control<=? AND d.code=? AND m.tdate>=? AND m.tdate<=? AND d.givrec='G'{$stF} GROUP BY m.tdate", array_merge([$g,$scode,$d1,$d2],$stB)) as $r)
                $add($r->tdate, 0, 0.0, (int)$r->q, (float)$r->w);
        }
        // Sales → issued (exclude job items)
        if ($this->hasTable('salesd') && $this->hasTable('salesm')) {
            foreach (DB::select("SELECT m.tdate, SUM(d.qty) q, SUM(d.weight) w FROM salesm m JOIN salesd d ON m.slno=d.slno WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=?{$stF} AND (d.jcode IS NULL OR d.jcode='') GROUP BY m.tdate", array_merge([$scode,$g,$d1,$d2],$stB)) as $r)
                $add($r->tdate, 0, 0.0, (int)$r->q, (float)$r->w);
        }
        // Sales Returns → received
        if ($this->hasTable('salesrd') && $this->hasTable('salesrm')) {
            foreach (DB::select("SELECT m.tdate, SUM(d.qty) q, SUM(d.weight) w FROM salesrm m JOIN salesrd d ON m.slno=d.slno WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=?{$stF} GROUP BY m.tdate", array_merge([$scode,$g,$d1,$d2],$stB)) as $r)
                $add($r->tdate, (int)$r->q, (float)$r->w, 0, 0.0);
        }
        // Refinery (only open transactions where issued ≠ received)
        if ($this->hasTable('refineryd') && $this->hasTable('refinerym')) {
            foreach (DB::select("SELECT m.tdate, d.issuedqty, d.issuedwgt, d.rcvdqty, d.rcvdwgt, COALESCE(d.bottlestk,0) bottlestk, COALESCE(d.testpcs,0) testpcs, d.ao FROM refinerym m JOIN refineryd d ON m.slno=d.slno WHERE m.control<=? AND d.code=? AND m.tdate>=? AND m.tdate<=? AND COALESCE(m.status,1)<>0", [$g,$scode,$d1,$d2]) as $r) {
                $issued = (float)$r->issuedwgt;
                $net    = is_null($r->ao) ? round((float)$r->rcvdwgt-(float)$r->bottlestk-(float)$r->testpcs,3) : round((float)$r->rcvdwgt,3);
                if (abs(round($issued,3)-$net) > 0.0005) {
                    if ($issued > 0) $add($r->tdate, 0, 0.0, (int)$r->issuedqty, $issued);
                    if ($net   > 0) $add($r->tdate, (int)$r->rcvdqty, $net, 0, 0.0);
                }
            }
        }
        // Adjustments Out → issued, In → received
        if ($this->hasTable('itemadj')) {
            foreach (DB::select("SELECT tdate, SUM(CASE WHEN fromcode=? THEN fromqty ELSE 0 END) iq, SUM(CASE WHEN fromcode=? THEN fromwgt ELSE 0 END) iw, SUM(CASE WHEN tocode=? THEN toqty ELSE 0 END) rq, SUM(CASE WHEN tocode=? THEN towgt ELSE 0 END) rw FROM itemadj WHERE control<=? AND tdate>=? AND tdate<=? GROUP BY tdate", [$scode,$scode,$scode,$scode,$g,$d1,$d2]) as $r)
                $add($r->tdate, (int)$r->rq, (float)$r->rw, (int)$r->iq, (float)$r->iw);
        }
        // Repair Received → received; Repair Issued → issued
        if ($this->hasTable('repaird') && $this->hasTable('repairm')) {
            foreach (DB::select("SELECT m.tdate, SUM(CASE WHEN d.givrec='R' THEN d.qty ELSE 0 END) rq, SUM(CASE WHEN d.givrec='R' THEN d.weight ELSE 0 END) rw, SUM(CASE WHEN d.givrec='G' THEN d.qty ELSE 0 END) iq, SUM(CASE WHEN d.givrec='G' THEN d.weight ELSE 0 END) iw FROM repairm m JOIN repaird d ON m.slno=d.slno WHERE m.control<=? AND d.code=? AND m.tdate>=? AND m.tdate<=?{$stF} GROUP BY m.tdate", array_merge([$g,$scode,$d1,$d2],$stB)) as $r)
                $add($r->tdate, (int)$r->rq, (float)$r->rw, (int)$r->iq, (float)$r->iw);
        }
        // Order Advance → received
        if ($this->hasTable('orderdga') && $this->hasTable('orderm')) {
            foreach (DB::select("SELECT m.tdate, SUM(d.qty) q, SUM(d.weight) w FROM orderm m JOIN orderdga d ON m.slno=d.slno WHERE m.control<=? AND d.code=? AND m.tdate>=? AND m.tdate<=? AND COALESCE(m.status,1)<>0 GROUP BY m.tdate", [$g,$scode,$d1,$d2]) as $r)
                $add($r->tdate, (int)$r->q, (float)$r->w, 0, 0.0);
        }

        ksort($byDate);

        $rows = [];
        $runQty = $opQty; $runWgt = $opWgt;
        foreach ($byDate as $date => [$rq,$rw,$iq,$iw]) {
            $clQty = $runQty + $rq - $iq;
            $clWgt = round($runWgt + $rw - $iw, 3);
            $rows[] = [
                'tdate'      => $date,
                'topstkqty'  => $runQty,
                'topstkwgt'  => round($runWgt, 3),
                'trcvedqty'  => $rq,
                'trcvedwgt'  => round($rw, 3),
                'tissuedqty' => $iq,
                'tissuedwgt' => round($iw, 3),
                'tclstkqty'  => $clQty,
                'tclstkwgt'  => $clWgt,
            ];
            $runQty = $clQty; $runWgt = $clWgt;
        }
        return $rows;
    }

    // Section order matches PowerBuilder taxrepprint subroutine:
    // Purchase → Purchase Return → Smith Rcvd → Smith Issued → Sales → Sales Return
    // → Refinery Issued → Refinery Rcvd → Adj Out → Adj In → Repair Rcvd → Repair Issued → Order Advance
    private function buildHistorySections(string $scode, string $d1, string $d2,
                                          int &$rQty, float &$rWgt,
                                          ?string $stktype = null,
                                          bool $withStkTouch = false): array
    {
        $g        = $this->gilevel;
        $sections = [];
        $stF      = $stktype !== null ? ' AND d.stktype = ?' : '';
        $stB      = $stktype !== null ? [$stktype] : [];
        $clientCache = [];

        $clientName = function(string $code) use (&$clientCache): string {
            if (!isset($clientCache[$code])) {
                $clientCache[$code] = (string)(DB::table('clients')->where('code',$code)->value('name') ?? '');
            }
            return $clientCache[$code];
        };

        // ── 1. PURCHASE DETAILS ──────────────────────────────────────────────
        if ($this->hasTable('purchased') && $this->hasTable('purchasem')) {
            $rows = DB::select("SELECT m.tdate, m.docno AS billno, m.name AS custname, d.code, d.name,
                d.qty, d.weight, d.stwgt AS stonewgt, d.rate, d.amount, m.smcode
                FROM purchasem m JOIN purchased d ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=?{$stF}
                ORDER BY m.tdate, m.docno", array_merge([$scode,$g,$d1,$d2],$stB));
            if ($rows) {
                $tq=0; $tw=0.0;
                $sect = ['title'=>'PURCHASE DETAILS','cols'=>['Date','Item','Qty','Weight','St.Wgt','Rate','Amount','Doc.No','SMan','Supplier'],'rows'=>[],'tq'=>0,'tw'=>0.0];
                foreach ($rows as $r) {
                    $wgt=(float)$r->weight;
                    $sect['rows'][] = [date('d/m/Y',strtotime($r->tdate)), mb_substr(trim($r->name),0,14),
                        $r->qty, number_format($wgt,3), number_format((float)$r->stonewgt,3),
                        number_format((float)$r->rate,2), number_format((float)$r->amount,2),
                        $r->billno, $r->smcode, mb_substr(trim($r->custname??''),0,20)];
                    $tq+=(int)$r->qty; $tw+=$wgt; $rQty+=(int)$r->qty; $rWgt+=$wgt;
                }
                $sect['tq']=$tq; $sect['tw']=round($tw,3); $sections[]=$sect;
            }
        }

        // ── 2. PURCHASE RETURN DETAILS ───────────────────────────────────────
        if ($this->hasTable('purchaserd') && $this->hasTable('purchaserm')) {
            $rows = DB::select("SELECT m.tdate, m.docno AS billno, m.name AS custname, d.code, d.name,
                d.qty, d.weight, d.stwgt AS stonewgt, d.rate, d.amount, m.smcode
                FROM purchaserm m JOIN purchaserd d ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=?{$stF}
                ORDER BY m.tdate, m.docno", array_merge([$scode,$g,$d1,$d2],$stB));
            if ($rows) {
                $tq=0; $tw=0.0;
                $sect = ['title'=>'PURCHASE RETURN DETAILS','cols'=>['Date','Item','Qty','Weight','St.Wgt','Rate','Amount','Doc.No','SMan','Supplier'],'rows'=>[],'tq'=>0,'tw'=>0.0];
                foreach ($rows as $r) {
                    $wgt=(float)$r->weight;
                    $sect['rows'][] = [date('d/m/Y',strtotime($r->tdate)), mb_substr(trim($r->name),0,14),
                        $r->qty, number_format($wgt,3), number_format((float)$r->stonewgt,3),
                        number_format((float)$r->rate,2), number_format((float)$r->amount,2),
                        $r->billno, $r->smcode, mb_substr(trim($r->custname??''),0,20)];
                    $tq+=(int)$r->qty; $tw+=$wgt; $rQty-=(int)$r->qty; $rWgt-=$wgt;
                }
                $sect['tq']=$tq; $sect['tw']=round($tw,3); $sections[]=$sect;
            }
        }

        // ── 3 & 4. SMITH RECEIVED / ISSUED ──────────────────────────────────
        if ($this->hasTable('smithd') && $this->hasTable('smithm')) {
            $smithCols = array_map('strtolower', $this->columnList('smithd'));
            $hasStkTouch = in_array('stktouch', $smithCols);
            foreach (['R'=>'SMITH/JEWELLERY RECEIVED DETAILS','G'=>'SMITH/JEWELLERY ISSUED DETAILS'] as $gr=>$title) {
                $rows = DB::select("SELECT m.tdate, m.docno, m.smithcode, d.code, i.name,
                    d.qty, d.weight, d.stonewgt, d.givrec, d.touch"
                    .($hasStkTouch ? ', d.stktouch' : '')."
                    FROM smithm m JOIN smithd d ON m.slno=d.slno JOIN items i ON d.code=i.code
                    WHERE m.control<=? AND i.code=? AND m.tdate>=? AND m.tdate<=? AND d.givrec=?{$stF}
                    ORDER BY m.tdate, m.docno", array_merge([$g,$scode,$d1,$d2,$gr],$stB));
                if ($rows) {
                    $tq=0; $tw=0.0;
                    $sect = ['title'=>$title,'cols'=>['Date','Item','Qty','Weight','St.Wgt','Touch','Doc.No','Party'],'rows'=>[],'tq'=>0,'tw'=>0.0];
                    foreach ($rows as $r) {
                        $rawWgt = (float)$r->weight;
                        $touch  = (float)($hasStkTouch ? ($r->stktouch ?? $r->touch ?? 0) : ($r->touch ?? 0));
                        $wgt    = ($withStkTouch && $gr === 'G' && $touch > 0)
                                  ? round($rawWgt * $touch / 100, 3)
                                  : $rawWgt;
                        $sect['rows'][] = [date('d/m/Y',strtotime($r->tdate)), mb_substr(trim($r->name),0,14),
                            $r->qty, number_format($wgt,3), number_format((float)$r->stonewgt,3),
                            number_format($touch,2), $r->docno, mb_substr($clientName($r->smithcode),0,20)];
                        $tq+=(int)$r->qty; $tw+=$wgt;
                        if ($gr==='G') { $rQty-=(int)$r->qty; $rWgt-=$wgt; }
                        else           { $rQty+=(int)$r->qty; $rWgt+=$wgt; }
                    }
                    $sect['tq']=$tq; $sect['tw']=round($tw,3); $sections[]=$sect;
                }
            }
        }

        // ── 5. SALES DETAILS ─────────────────────────────────────────────────
        if ($this->hasTable('salesd') && $this->hasTable('salesm')) {
            $rows = DB::select("SELECT m.tdate, m.billno, m.custname, d.code, d.name,
                d.qty, d.weight, d.stonewgt, d.rate, d.amount, m.smcode, d.jcode
                FROM salesm m JOIN salesd d ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=?{$stF}
                ORDER BY m.tdate, m.billno", array_merge([$scode,$g,$d1,$d2],$stB));
            if ($rows) {
                $tq=0; $tw=0.0;
                $sect = ['title'=>'SALES DETAILS','cols'=>['Date','Item','Qty','Weight','St.Wgt','Rate','Amount','Doc.No','SMan','Customer'],'rows'=>[],'tq'=>0,'tw'=>0.0];
                foreach ($rows as $r) {
                    $jcode = trim((string)($r->jcode ?? ''));
                    $wgt   = (float)$r->weight;
                    $sect['rows'][] = [date('d/m/Y',strtotime($r->tdate)), mb_substr(trim($r->name),0,14),
                        $r->qty, ($jcode!==''?'*':'').number_format($wgt,3), number_format((float)$r->stonewgt,3),
                        number_format((float)$r->rate,2), number_format((float)$r->amount,2),
                        $r->billno, $r->smcode, mb_substr(trim($r->custname??''),0,20)];
                    if ($jcode === '') { $tq+=(int)$r->qty; $tw+=$wgt; $rQty-=(int)$r->qty; $rWgt-=$wgt; }
                }
                $sect['tq']=$tq; $sect['tw']=round($tw,3); $sections[]=$sect;
            }
        }

        // ── 6. SALES RETURN DETAILS ──────────────────────────────────────────
        if ($this->hasTable('salesrd') && $this->hasTable('salesrm')) {
            $rows = DB::select("SELECT m.tdate, m.billno, m.custname, d.code, d.name,
                d.qty, d.weight, d.stonewgt, d.rate, d.amount, m.smcode
                FROM salesrm m JOIN salesrd d ON m.slno=d.slno
                WHERE d.code=? AND m.control<=? AND m.tdate>=? AND m.tdate<=?{$stF}
                ORDER BY m.tdate, m.billno", array_merge([$scode,$g,$d1,$d2],$stB));
            if ($rows) {
                $tq=0; $tw=0.0;
                $sect = ['title'=>'SALES RETURN DETAILS','cols'=>['Date','Item','Qty','Weight','St.Wgt','Rate','Amount','Doc.No','SMan','Customer'],'rows'=>[],'tq'=>0,'tw'=>0.0];
                foreach ($rows as $r) {
                    $wgt=(float)$r->weight;
                    $sect['rows'][] = [date('d/m/Y',strtotime($r->tdate)), mb_substr(trim($r->name),0,14),
                        $r->qty, number_format($wgt,3), number_format((float)$r->stonewgt,3),
                        number_format((float)$r->rate,2), number_format((float)$r->amount,2),
                        $r->billno, $r->smcode, mb_substr(trim($r->custname??''),0,20)];
                    $tq+=(int)$r->qty; $tw+=$wgt; $rQty+=(int)$r->qty; $rWgt+=$wgt;
                }
                $sect['tq']=$tq; $sect['tw']=round($tw,3); $sections[]=$sect;
            }
        }

        // ── 7 & 8. REFINERY ISSUED / RECEIVED ───────────────────────────────
        if ($this->hasTable('refineryd') && $this->hasTable('refinerym')) {
            $refRows = DB::select("SELECT m.tdate, m.docno, m.refcode, d.code, i.name,
                d.issuedqty, d.issuedwgt, d.rcvdqty, d.rcvdwgt,
                COALESCE(d.bottlestk,0) AS bottlestk,
                COALESCE(d.testpcs,0) AS testpcs, d.ao
                FROM refinerym m JOIN refineryd d ON m.slno=d.slno JOIN items i ON d.code=i.code
                WHERE m.control<=? AND i.code=? AND m.tdate>=? AND m.tdate<=? AND COALESCE(m.status,1)<>0
                ORDER BY m.tdate, m.docno, m.slno",[$g,$scode,$d1,$d2]);
            $issuedRows=[]; $rcvdRows=[];
            foreach ($refRows as $r) {
                $issued = (float)$r->issuedwgt;
                $rcvd   = (float)$r->rcvdwgt;
                $net    = is_null($r->ao) ? round($rcvd-(float)$r->bottlestk-(float)$r->testpcs,3) : round($rcvd,3);
                $diff   = abs(round($issued,3) - $net) > 0.0005;
                $clientName($r->refcode); // pre-cache
                if ($issued > 0 && $diff) $issuedRows[] = [$r,$issued];
                if ($rcvd   > 0 && $diff) $rcvdRows[]   = [$r,$net];
            }
            if ($issuedRows) {
                $tq=0; $tw=0.0;
                $sect = ['title'=>'REFINERY ISSUED DETAILS','cols'=>['Date','Item','Qty','Weight','Doc.No','Refiner'],'rows'=>[],'tq'=>0,'tw'=>0.0];
                foreach ($issuedRows as [$r,$wgt]) {
                    $sect['rows'][] = [date('d/m/Y',strtotime($r->tdate)),mb_substr(trim($r->name),0,14),
                        $r->issuedqty,number_format($wgt,3),$r->docno,mb_substr($clientCache[$r->refcode]??'',0,20)];
                    $tq+=(int)$r->issuedqty; $tw+=$wgt; $rQty-=(int)$r->issuedqty; $rWgt-=$wgt;
                }
                $sect['tq']=$tq; $sect['tw']=round($tw,3); $sections[]=$sect;
            }
            if ($rcvdRows) {
                $tq=0; $tw=0.0;
                $sect = ['title'=>'REFINERY RECEIVED DETAILS','cols'=>['Date','Item','Qty','Weight','Doc.No','Refiner'],'rows'=>[],'tq'=>0,'tw'=>0.0];
                foreach ($rcvdRows as [$r,$wgt]) {
                    $sect['rows'][] = [date('d/m/Y',strtotime($r->tdate)),mb_substr(trim($r->name),0,14),
                        $r->rcvdqty,number_format($wgt,3),$r->docno,mb_substr($clientCache[$r->refcode]??'',0,20)];
                    $tq+=(int)$r->rcvdqty; $tw+=$wgt; $rQty+=(int)$r->rcvdqty; $rWgt+=$wgt;
                }
                $sect['tq']=$tq; $sect['tw']=round($tw,3); $sections[]=$sect;
            }
        }

        // ── 9 & 10. ADJUSTMENT OUT / IN ─────────────────────────────────────
        if ($this->hasTable('itemadj')) {
            foreach (['out','in'] as $dir) {
                $title      = $dir==='out' ? 'ITEM ADJUSTMENT (STOCK OUT)' : 'ITEM ADJUSTMENT (STOCK IN)';
                $codeField  = $dir==='out' ? 'fromcode' : 'tocode';
                $qtyField   = $dir==='out' ? 'fromqty'  : 'toqty';
                $wgtField   = $dir==='out' ? 'fromwgt'  : 'towgt';
                $otherField = $dir==='out' ? 'tocode'   : 'fromcode';
                $otherLabel = $dir==='out' ? 'To Item'  : 'From Item';
                $rows = DB::select("SELECT a.tdate, a.{$codeField} AS code, i.name,
                    a.{$qtyField} AS qty, a.{$wgtField} AS wgt,
                    a.{$otherField} AS othercode, a.smcode, a.particular
                    FROM itemadj a JOIN items i ON a.{$codeField}=i.code
                    WHERE a.control<=? AND i.code=? AND a.tdate>=? AND a.tdate<=?
                    ORDER BY a.tdate, a.slno",[$g,$scode,$d1,$d2]);
                if ($rows) {
                    $tq=0; $tw=0.0;
                    $sect = ['title'=>$title,'cols'=>['Date','Item','Qty','Weight',$otherLabel,'SMan','Description'],'rows'=>[],'tq'=>0,'tw'=>0.0];
                    foreach ($rows as $r) {
                        $wgt=(float)$r->wgt;
                        $sect['rows'][] = [date('d/m/Y',strtotime($r->tdate)),mb_substr(trim($r->name),0,14),
                            $r->qty,number_format($wgt,3),$r->othercode,$r->smcode,
                            mb_substr(trim($r->particular??''),0,20)];
                        $tq+=(int)$r->qty; $tw+=$wgt;
                        if ($dir==='out') { $rQty-=(int)$r->qty; $rWgt-=$wgt; }
                        else              { $rQty+=(int)$r->qty; $rWgt+=$wgt; }
                    }
                    $sect['tq']=$tq; $sect['tw']=round($tw,3); $sections[]=$sect;
                }
            }
        }

        // ── 11 & 12. REPAIR RECEIVED / ISSUED ───────────────────────────────
        if ($this->hasTable('repaird') && $this->hasTable('repairm')) {
            foreach (['R'=>'REPAIR RECEIVED DETAILS','G'=>'REPAIR ISSUED DETAILS'] as $gr=>$title) {
                $rows = DB::select("SELECT m.tdate, m.billno, m.custname, d.code, i.name,
                    d.qty, d.weight, d.stonewgt, d.givrec
                    FROM repairm m JOIN repaird d ON m.slno=d.slno JOIN items i ON d.code=i.code
                    WHERE m.control<=? AND i.code=? AND m.tdate>=? AND m.tdate<=? AND d.givrec=?{$stF}
                    ORDER BY m.tdate, m.billno", array_merge([$g,$scode,$d1,$d2,$gr],$stB));
                if ($rows) {
                    $tq=0; $tw=0.0;
                    $sect = ['title'=>$title,'cols'=>['Date','Item','Qty','Weight','St.Wgt','Doc.No','Customer'],'rows'=>[],'tq'=>0,'tw'=>0.0];
                    foreach ($rows as $r) {
                        $wgt=(float)$r->weight;
                        $sect['rows'][] = [date('d/m/Y',strtotime($r->tdate)),mb_substr(trim($r->name),0,14),
                            $r->qty,number_format($wgt,3),number_format((float)$r->stonewgt,3),
                            $r->billno,mb_substr(trim($r->custname??''),0,20)];
                        $tq+=(int)$r->qty; $tw+=$wgt;
                        if ($gr==='R') { $rQty+=(int)$r->qty; $rWgt+=$wgt; }
                        else           { $rQty-=(int)$r->qty; $rWgt-=$wgt; }
                    }
                    $sect['tq']=$tq; $sect['tw']=round($tw,3); $sections[]=$sect;
                }
            }
        }

        // ── 13. ORDER ADVANCE DETAILS ────────────────────────────────────────
        if ($this->hasTable('orderdga') && $this->hasTable('orderm')) {
            $rows = DB::select("SELECT m.tdate, m.ordno, m.custname, d.code, i.name, d.qty, d.weight
                FROM orderm m JOIN orderdga d ON m.slno=d.slno JOIN items i ON d.code=i.code
                WHERE m.control<=? AND i.code=? AND m.tdate>=? AND m.tdate<=? AND COALESCE(m.status,1)<>0
                ORDER BY m.tdate, m.ordno",[$g,$scode,$d1,$d2]);
            if ($rows) {
                $tq=0; $tw=0.0;
                $sect = ['title'=>'ORDER ADVANCE DETAILS','cols'=>['Date','Item','Qty','Weight','Ord.No','Customer'],'rows'=>[],'tq'=>0,'tw'=>0.0];
                foreach ($rows as $r) {
                    $wgt=(float)$r->weight;
                    $sect['rows'][] = [date('d/m/Y',strtotime($r->tdate)),mb_substr(trim($r->name),0,14),
                        $r->qty,number_format($wgt,3),$r->ordno,mb_substr(trim($r->custname??''),0,20)];
                    $tq+=(int)$r->qty; $tw+=$wgt; $rQty+=(int)$r->qty; $rWgt+=$wgt;
                }
                $sect['tq']=$tq; $sect['tw']=round($tw,3); $sections[]=$sect;
            }
        }

        return $sections;
    }

    // ─── DB helper ───────────────────────────────────────────────────────────

    private function rawOne(string $sql, array $bindings): object
    {
        $rows = DB::select($sql, $bindings);
        return $rows[0] ?? (object)['q'=>0,'w'=>0.0,'s'=>0.0,'iq'=>0,'iw'=>0.0,'rq'=>0,'rw'=>0.0,
            'fq'=>0,'fw'=>0.0,'fs'=>0.0,'tq'=>0,'tw'=>0.0,'ts'=>0.0];
    }
}
