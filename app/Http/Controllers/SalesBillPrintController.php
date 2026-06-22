<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SalesBillPrintController extends Controller
{
    // ── Settings ──────────────────────────────────────────────────────────────

    private function loadSettings(): array
    {
        static $cached = null;
        if ($cached !== null) return $cached;
        $iniPaths = [
            storage_path('app/software-settings.ini'),
        ];
        foreach ($iniPaths as $ini) {
            if (File::exists($ini)) {
                return $cached = $this->parseIni((string) File::get($ini));
            }
        }
        return $cached = ['Software' => [], 'Company' => [], 'Printer' => [], 'Rates' => []];
    }

    private function parseIni(string $raw): array
    {
        $out = []; $sec = '';
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === ';' || $line[0] === '#' || str_starts_with($line, '//')) continue;
            if (preg_match('/^\[(.+)\]$/', $line, $m)) { $sec = $m[1]; continue; }
            if ($sec && str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $out[$sec][trim($k)] = trim($v);
            }
        }
        if (!isset($out['Software'])) $out['Software'] = [];
        if (!isset($out['Company'])) $out['Company'] = [];
        if (!isset($out['Printer'])) $out['Printer'] = [];
        if (!isset($out['Rates'])) $out['Rates'] = [];
        return $out;
    }

    private function ini(array $s, string $sec, string $key, string $def = ''): string
    {
        return (string) ($s[$sec][$key] ?? $def);
    }

    private function flag(array $s, string $sec, string $key, string $def = 'N'): bool
    {
        return strtoupper($this->ini($s, $sec, $key, $def)) === 'Y';
    }

    /** Fetch all rates in one query and cache for the request lifetime. */
    private function allGeneralRates(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = [];
        foreach (['generald', 'generals'] as $tbl) {
            try {
                if (!$this->hasTable($tbl)) continue;
                $hasDvalue = $this->hasColumn($tbl, 'dvalue');
                $cols = $hasDvalue ? ['code', 'cvalue', 'dvalue'] : ['code', 'cvalue'];
                DB::table($tbl)->get($cols)->each(function ($r) use ($hasDvalue, &$cache) {
                    $k = strtoupper(trim((string) ($r->code ?? '')));
                    if ($k === '' || isset($cache[$k])) return;
                    $v = $r->cvalue ?? null;
                    if (($v === null || $v === '') && $hasDvalue) $v = $r->dvalue ?? null;
                    if ($v !== null && $v !== '') $cache[$k] = (float) $v;
                });
                break; // stop after first table that works
            } catch (\Throwable) {}
        }
        return $cache;
    }

    private function generalRate(string $code): float
    {
        $rates = $this->allGeneralRates();
        return $rates[strtoupper(trim($code))] ?? 0.0;
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = Schema::hasColumn($table, $column);
        }
        return $cache[$key];
    }

    // ── Main View ─────────────────────────────────────────────────────────────

    public function show(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');

        $requestedDatabase = trim((string) $request->query('db', ''));
        if ($requestedDatabase !== '' && $requestedDatabase !== (string) Config::get('database.connections.mysql.database', '')) {
            Config::set('database.connections.mysql.database', $requestedDatabase);
            DB::purge('mysql');
            DB::reconnect('mysql');
        }

        $slno = (int) $request->query('slno', 0);
        if ($slno <= 0) return response('<p style="padding:20px">No bill selected. Usage: ?slno=XXX</p>', 400);

        $s = $this->loadSettings();

        // ── Master ──────────────────────────────────────────────────────────
        $master = DB::table('salesm')->where('slno', $slno)->first();
        if (!$master) return response('<p style="padding:20px">Bill not found for slno: ' . $slno . '</p>', 404);

        // ── Details ─────────────────────────────────────────────────────────
        $detailRows = DB::table('salesd')->where('slno', $slno)->orderBy('sno')->get()->map(fn($r) => (array)$r)->all();

        // ── Customer info ───────────────────────────────────────────────────
        $cust = [];
        if ($master->custcode && $this->hasTable('clients')) {
            $c = DB::table('clients')->where('code', $master->custcode)->first();
            if ($c) $cust = (array)$c;
        }

        // ── Salesman name ───────────────────────────────────────────────────
        $smanName = '';
        if (!empty($master->smcode) && $this->hasTable('sman')) {
            $smanName = (string) (DB::table('sman')->where('code', $master->smcode)->value('name') ?? '');
        }

        // ── State name ──────────────────────────────────────────────────────
        $stateName = '';
        if (!empty($master->statecode) && $this->hasTable('state')) {
            $stateName = (string) (DB::table('state')->where('code', $master->statecode)->value('name') ?? '');
        }

        // ── Gold advance ────────────────────────────────────────────────────
        $gadvAmt = 0.0;
        if (!empty($master->orderno) && $this->hasTable('orderm')) {
            $gadvAmt = (float) (DB::table('orderm')->where('ordno', $master->orderno)->value('gadvance') ?? 0);
        }

        // ── Pre-fetch item info for all detail codes ─────────────────────
        $itemCodes = array_filter(array_unique(array_column($detailRows, 'code')));
        $itemMap   = [];
        if ($itemCodes && $this->hasTable('items')) {
            $cols = ['code', 'name', 'itype', 'vatcode'];
            foreach (['printvaamt', 'defquality'] as $c) {
                if ($this->hasColumn('items', $c)) $cols[] = $c;
            }
            DB::table('items')->whereIn('code', $itemCodes)->get($cols)->each(function($r) use (&$itemMap) {
                $itemMap[$r->code] = (array)$r;
            });
        }

        // ── Pre-fetch barcode info ─────────────────────────────────────────
        $bcodes = array_filter(array_unique(array_column($detailRows, 'bcode')));
        $barcodeMap = [];
        if ($bcodes && $this->hasTable('barcode')) {
            $cols = ['bcode', 'vap', 'mc', 'model'];
            if ($this->hasColumn('barcode', 'huid')) $cols[] = 'huid';
            DB::table('barcode')->whereIn('bcode', $bcodes)->get($cols)->each(function($r) use (&$barcodeMap) {
                $barcodeMap[$r->bcode] = (array)$r;
            });
        }

        // ── Pre-fetch dmd amounts per sno ──────────────────────────────────
        $dmdMap = [];
        if ($this->hasTable('spdmddet')) {
            DB::table('spdmddet')->where('slno', $slno)->get(['sno', 'dmdamt'])->each(function($r) use (&$dmdMap) {
                $dmdMap[$r->sno] = ($dmdMap[$r->sno] ?? 0) + (float)$r->dmdamt;
            });
        }

        // ── Total diamond weight ────────────────────────────────────────────
        $dmdWgt = (float) (DB::table('salesd')->where('slno', $slno)->sum('dmdwgt') ?? 0);

        // ── Exchange detail rows ────────────────────────────────────────────
        $exchangeRows = [];
        if ($this->hasTable('purchased')) {
            $exchangeRows = DB::table('purchased')
                ->where('slno', $slno)
                ->orderBy('sno')
                ->get(['sno', 'name', 'qty', 'weight', 'stwgt', 'mud', 'rate', 'amount'])
                ->map(fn($r) => (array)$r)
                ->all();
        }

        // ── Sales return detail rows ────────────────────────────────────────
        $sretRows = [];
        if ($this->hasTable('salesrd')) {
            $sretSelect = ['sno', 'name', 'qty', 'weight', 'amount'];
            foreach (['rate', 'stonewgt', 'stoneprice', 'mcharge', 'wastage', 'vaperc'] as $col) {
                if ($this->hasColumn('salesrd', $col)) {
                    $sretSelect[] = $col;
                }
            }
            $sretRows = DB::table('salesrd')
                ->where('slno', $slno)
                ->orderBy('sno')
                ->get($sretSelect)
                ->map(fn($r) => (array)$r)
                ->all();
        }

        // ── Build rows with computed fields ───────────────────────────────
        $rows = $this->buildRows($detailRows, $master, $s, $itemMap, $barcodeMap, $dmdMap);

        // ── INI Settings ──────────────────────────────────────────────────
        $sw = fn($k, $d='') => $this->ini($s, 'Software', $k, $d);
        $fl = fn($k, $d='N') => $this->flag($s, 'Software', $k, $d);

        $showShopInfo   = $fl('ShowShopInfoInSalesPrint', 'N');
        $showStateCode  = $fl('ShowStateCode', 'Y');
        $billFormEst    = $fl('BillFormEstimatePrint', 'N');
        $printGSTIN     = $fl('PrintGSTINAlways', 'Y');
        $printCustMob   = $fl('PrintCustMobNo', 'Y');
        $printGRate     = $fl('PrintGRate', 'Y');
        $printRate8gm   = $fl('PrintRate8gm', 'N');
        $printSalesMan  = $fl('PrintSalesManName', 'N');
        $printBCode     = $fl('PrintBCode', 'N');
        $printVA        = $fl('PrintVA', 'N');
        $printVAPerc    = $fl('PrintVAPercInBill', 'Y');
        $printVAPerGm   = $fl('PrintVAPerGm', 'N');
        $printVAInWgt   = $fl('PrintVAInWgt', 'N');
        $showPrintVAInWgt = $fl('ShowPrintVAInWgt', 'N');
        $printDiscount  = $fl('PrintDiscount', 'Y');
        $printDiscAlways= $fl('PrintDiscountAlways', 'N');
        $printOldBal    = $fl('PrintOldBalance', 'N');
        $printFooter    = $fl('PrintFooterInBIll', 'N');
        $printRateStyle = $fl('Rateprint', 'Y');
        $printPartyCode = $fl('PrintPartyCodeInSales', 'N');
        $printNetVALast = $fl('PrintNetVAAmtLast', 'N');
        $showBCVADiff   = $fl('ShowBCVADiff', 'Y');
        $totGST         = $fl('TotGST', 'N');
        $printOrgMCPerc = $fl('PrintOrgMCPerc', 'N');
        $printBCVA      = $fl('PrintBCBasedVA', 'N');
        $adjDiscToVA    = $fl('AdjustDiscountToVA', 'N');
        $printVAPerGmTale = $sw('PrintVAPerGmTale', '');
        $printVAAmtBelow  = (float) $sw('PrintVAAmtBelowWgt', '1');
        $billFooter1    = $sw('BillFooter1', '');
        $billFooter2    = $sw('BillFooter2', '');
        $maxItems       = max(1, (int) $sw('MaxItemsB', '6'));
        if ($dmdWgt > 0) $maxItems = max(1, (int) $sw('MaxItemsD', '6'));

        if ($request->has('vaperc')) {
            $printVAPerc = $request->boolean('vaperc');
        }
        if ($request->has('vapergm')) {
            $printVAPerGm = $request->boolean('vapergm');
        }
        if ($printVAPerc && $printVAPerGm) {
            $printVAPerGm = false;
        }

        $landscape  = false;
        $copies     = $this->ini($s, 'Printer', 'Copies', '1');
        $zoom       = $this->ini($s, 'Printer', 'SZoom', '90');

        // Shop info is stored in generals table (managed via Application Settings)
        $generals = [];
        try {
            $generalRows = DB::table('generals')
                ->whereIn('code', ['SHOPNM', 'SHOPADDR', 'SHOPPHONE', 'SHOPLOGO'])
                ->get(['code', 'cvalue']);
            foreach ($generalRows as $r) {
                $generals[trim($r->code)] = (string) ($r->cvalue ?? '');
            }
        } catch (\Throwable) {}

        $companyName  = ($generals['SHOPNM']    ?? '') !== ''
            ? $generals['SHOPNM']
            : ($this->ini($s, 'Company', 'Name', '') ?: '');
        $companyAddr  = ($generals['SHOPADDR']   ?? '') !== ''
            ? $generals['SHOPADDR']
            : ($this->ini($s, 'Company', 'Addr', '')
                ?: ($this->ini($s, 'Company', 'Addr1', '')
                    ?: $this->ini($s, 'Company', 'Address1', '')));
        $companyAddr2 = $this->ini($s, 'Company', 'Addr2', '')
            ?: $this->ini($s, 'Company', 'Address2', '');
        $companyState = $this->ini($s, 'Company', 'State', '')
            ?: $this->ini($s, 'Company', 'StateCodeName', '');
        $companyGSTIN = $this->ini($s, 'Company', 'GSTIN', '')
            ?: $this->ini($s, 'Company', 'KGST', '');
        $companyPhone = ($generals['SHOPPHONE']  ?? '') !== ''
            ? $generals['SHOPPHONE']
            : ($this->ini($s, 'Company', 'Phone', '') ?: '');
        $companyLogo  = $generals['SHOPLOGO']   ?? '';
        $companyBankName = $this->ini($s, 'Company', 'BankName', '');
        $companyBankAccountName = $this->ini($s, 'Company', 'BankAccountName', '');
        $companyBankAccountNo = $this->ini($s, 'Company', 'BankAccountNo', '');
        $companyBankBranch = $this->ini($s, 'Company', 'BankBranch', '');
        $companyBankIFSC = $this->ini($s, 'Company', 'BankIFSC', '');
        $showBankDetails = $companyBankName !== '' || $companyBankAccountName !== '' || $companyBankAccountNo !== '' || $companyBankBranch !== '' || $companyBankIFSC !== '';
        $g18rate      = 0.0;

        // ── Master field aliases ──────────────────────────────────────────
        $billno   = $master->billno ?? '';
        $tdate    = $master->tdate ?? '';
        $custname = $master->custname ?? '';
        $custcode = $master->custcode ?? '';
        $billamt  = (float)($master->billamt ?? 0);
        $netamt   = (float)($master->netamt ?? 0);
        $ramt     = (float)($master->ramt ?? 0);
        $staxamt  = (float)($master->staxamt ?? 0);
        $staxperc = (float)($master->staxperc ?? 0);
        $discount = (float)($master->discount ?? 0);
        $discperc = (float)($master->discperc ?? 0);
        if ($discperc <= 0 && $discount > 0 && $billamt > 0) {
            $discperc = round($discount * 100 / $billamt, 2);
        }
        $eamt     = (float)($master->eamt ?? 0);
        $sretamt  = (float)($master->sretamt ?? 0);
        $round    = (float)($master->round ?? 0);
        $astamt   = (float)($master->astamt ?? 0);
        $advance  = (float)($master->advance ?? 0);
        $ccamt    = (float)($master->ccamt ?? 0);
        $chqamt   = (float)($master->chqamt ?? 0);
        $cbcode   = trim((string) ($master->cbcode ?? ''));
        if ($cbcode === '') {
            $cbcode = 'CASH';
        }
        $cashBankName = '';
        if ($this->hasTable('accountm') && $cbcode !== '') {
            $nameCol = Schema::hasColumn('accountm', 'acname') ? 'acname' : (Schema::hasColumn('accountm', 'name') ? 'name' : '');
            if ($nameCol !== '') {
                $cashBankName = trim((string) (DB::table('accountm')->whereRaw('TRIM(accode)=?', [$cbcode])->value($nameCol) ?? ''));
            }
        }
        $cashBankDisplay = $cashBankName !== '' ? $cashBankName : $cbcode;
        $ob       = (float)($master->ob ?? 0);
        $schmamt  = (float)($master->schmamt ?? 0);
        $tcsperc  = (float)($master->tcsperc ?? 0);
        $tcsamt   = (float)($master->tcsamt ?? 0);
        $cst      = $master->cst ?? 'N';
        $grate    = $this->generalRate('GRATE');
        if ($grate <= 0) {
            $grate = (float) ($master->grate ?? 0);
        }
        if ($grate <= 0) {
            $grate = (float) $this->ini($s, 'Rates', 'GRATE', '0');
        }

        $srate = $this->generalRate('SRATE');
        if ($srate <= 0) {
            $srate = (float) ($master->srate ?? 0);
        }
        if ($srate <= 0) {
            $srate = (float) $this->ini($s, 'Rates', 'SRATE', '0');
        }
        if ($srate <= 0) {
            $srate = (float) $this->ini($s, 'Software', 'SRATE', '0');
        }

        $g18rate = $this->generalRate('G18RATE');
        if ($g18rate <= 0) {
            $g18rate = (float) $this->ini($s, 'Rates', 'G18RATE', '0');
        }
        if ($g18rate <= 0) {
            $g18rate = (float) $this->ini($s, 'Software', 'G18RATE', '0');
        }
        $smcode   = $master->smcode ?? '';
        $counter  = $master->counter ?? '';
        $pan      = $master->pan ?? '';
        $note     = $master->note ?? '';
        $placeos  = $master->placeos ?? '';
        $control  = (int)($master->control ?? 1);
        $addr     = $master->addr ?? '';
        $duedate  = $master->duedate ?? '';
        $orderno  = trim((string) ($master->orderno ?? ''));
        $orderPrintDetails = null;
        $orderCredit = 0.0;
        if ($orderno !== '' && $this->hasTable('orderm')) {
            $orderMaster = DB::table('orderm')->whereRaw('TRIM(ordno)=?', [$orderno])->first();
            if ($orderMaster) {
                $orderCashAdvance = (float) ($orderMaster->advance ?? 0);
                $orderAdvanceAfter = 0.0;
                if ($this->hasTable('advafter')) {
                    $advQuery = DB::table('advafter')->whereRaw('TRIM(ordno)=?', [$orderno]);
                    if ($this->hasColumn('advafter', 'amttowgt')) {
                        $advQuery->where(function ($q) {
                            $q->where('amttowgt', '!=', 'Y')->orWhereNull('amttowgt');
                        });
                    }
                    $orderAdvanceAfter = (float) ($advQuery->sum('amount') ?? 0);
                }
                $orderExchangeWeight = 0.0;
                $orderExchangeAmount = (float) ($orderMaster->eamt ?? $orderMaster->exchange ?? 0);
                $orderExchangeRate = 0.0;
                if ($this->hasTable('purchased')) {
                    $orderExchangeRows = DB::table('purchased')
                        ->where('slno', (int) ($orderMaster->slno ?? 0))
                        ->orderBy('sno')
                        ->get(['sno', 'name', 'qty', 'weight', 'stwgt', 'mud', 'rate', 'amount']);
                    $orderExchangeWeight = (float) $orderExchangeRows->sum(fn($r) => (float) ($r->weight ?? 0));
                    $orderExchangeAmount = (float) $orderExchangeRows->sum(fn($r) => (float) ($r->amount ?? 0));
                    $firstRate = $orderExchangeRows->first(fn($r) => (float) ($r->rate ?? 0) > 0);
                    $orderExchangeRate = $firstRate ? (float) ($firstRate->rate ?? 0) : 0.0;
                    if ($orderExchangeRate <= 0 && $orderExchangeWeight > 0 && $orderExchangeAmount > 0) {
                        $orderExchangeRate = round($orderExchangeAmount / $orderExchangeWeight, 2);
                    }
                    if ($exchangeRows === [] && $orderExchangeRows->isNotEmpty()) {
                        $exchangeRows = $orderExchangeRows->map(fn($r) => (array) $r)->all();
                    }
                }
                $orderPrintDetails = [
                    'order_no' => trim((string) ($orderMaster->ordno ?? $orderno)),
                    'date' => $orderMaster->tdate ?? $orderMaster->date ?? null,
                    'cash_advance' => $orderCashAdvance,
                    'advance_after' => $orderAdvanceAfter,
                    'total_advance' => $advance,
                    'gold_advance' => (float) ($orderMaster->gadvance ?? 0),
                    'exchange_rate' => $orderExchangeRate,
                    'exchange_weight' => $orderExchangeWeight,
                    'exchange_amount' => $orderExchangeAmount,
                ];
            }
        }
        if ($orderno !== '' && $custcode !== '' && $advance > 0) {
            $postedOrderAdvance = $this->postedOrderCashAdvanceForCustomer($orderno, $custcode);
            $ob = round($ob - min($postedOrderAdvance, max($advance, 0)), 2);
        }
        if ($orderno !== '' && $custcode !== '') {
            $postedOrderAdvance = $this->postedOrderCashAdvanceForCustomer($orderno, $custcode);
            $orderCredit = round(max($postedOrderAdvance - max($advance, 0), 0), 2);
            $ob = round($ob - $orderCredit, 2);
            if (abs($ob) < 0.5) {
                $ob = 0.0;
            }
        }

        // ── Customer address lookup ────────────────────────────────────────
        $ad1 = $cust['addr1'] ?? ''; $ad2 = $cust['addr2'] ?? '';
        $ad3 = $cust['addr3'] ?? ''; $ad4 = $cust['city'] ?? '';
        if (!empty($ad1)) $addr = $ad1;
        $ctin = $cust['tin'] ?? '';
        $cphone  = $cust['telephone'] ?? '';
        $cmobile = $cust['mobile'] ?? '';
        $mobno   = $master->mobno ?? '';
        $coname  = '';
        $phoneLine = '';
        if ($cphone)  $phoneLine .= $cphone . ' ';
        if ($cmobile) $phoneLine .= $cmobile . ' ';
        elseif ($mobno) $phoneLine .= $mobno . ', ';

        // ── State ─────────────────────────────────────────────────────────
        $statecode  = $master->statecode ?? '';
        $custState  = $statecode ? $statecode . '-' . $stateName : $companyState;

        // ── Display values ────────────────────────────────────────────────
        $grateDisplay = $printRate8gm ? $grate * 8 : $grate;

        $showDueDate = false;
        if (!empty($duedate) && $netamt != $ramt) {
            try { $showDueDate = strtotime($duedate) > strtotime('2000-01-01'); } catch (\Throwable $e) {}
        }

        // ── GST split ─────────────────────────────────────────────────────
        $effectiveTaxPerc = $staxperc;
        if ($effectiveTaxPerc <= 0 && $staxamt > 0) {
            $taxableBase = $billamt > 0 ? $billamt : max($netamt - $staxamt, 0);
            if ($taxableBase > 0) {
                $effectiveTaxPerc = round(($staxamt * 100) / $taxableBase, 3);
            }
        }
        $cgstPerc = $cst === 'Y' ? $effectiveTaxPerc : ($effectiveTaxPerc / 2);
        $sgstPerc = $cst === 'Y' ? 0 : ($effectiveTaxPerc / 2);

        if ($cst === 'Y') {
            $cgst = $staxamt; $sgst = 0;
            $gstLabel  = 'IGST (' . number_format($effectiveTaxPerc, 1) . '%)';
            $sgstLabel = '';
        } else {
            $cgst = $staxamt / 2; $sgst = $staxamt / 2;
            $gstLabel  = 'CGST (' . number_format($cgstPerc, 1) . '%)';
            $sgstLabel = 'SGST (' . number_format($sgstPerc, 1) . '%)';
        }

        // ── Computed totals ───────────────────────────────────────────────
        $isOrderSalePrint = $orderno !== '';
        $balanceOrderCredit = $isOrderSalePrint ? 0.0 : $orderCredit;
        $discAdj   = $printDiscount ? $discount : 0;
        $advAdj    = $advance > 0 ? $advance : 0;
        $subtotal  = ($netamt + $eamt + $sretamt - $staxamt - $round) + $schmamt + $discAdj - $astamt + $advAdj;
        $grandTotal= round(($netamt + $eamt + $sretamt - $staxamt - $round) - $astamt + $advAdj + $staxamt, 0) + $schmamt;
        $salesTotal= $grandTotal + ($printDiscount ? $discount : 0);
        $balance   = $netamt - $discount - $ramt - $balanceOrderCredit;
        if (abs($balance) < 0.5) {
            $balance = 0.0;
        }
        $cashRcvd  = $ramt - $chqamt - $ccamt;
        $isCashAccount = in_array(strtoupper($cbcode), ['CASH', 'CASH IN HAND'], true);
        $chqCcTotal= $chqamt + $ccamt;
        $bankReceived = $chqCcTotal > 0 ? $chqCcTotal : ($isCashAccount ? 0.0 : $ramt);
        $cashReceived = $chqCcTotal > 0 ? max($ramt - $chqCcTotal, 0.0) : ($isCashAccount ? $ramt : 0.0);
        $clBalance = $ob - $balance;

        // ── Row totals ────────────────────────────────────────────────────
        $totQty=0; $totGrossWgt=0; $totStWgt=0; $totNetWgt=0;
        $totStPrice=0; $totVA=0; $totItemAmt=0; $totAmt=0; $tbcva=0;
        foreach ($rows as $r) {
            $totQty      += (int)($r['salesd_qty'] ?? 0);
            $totGrossWgt += (float)($r['salesd_weight'] ?? 0);
            $sw2 = (float)($r['salesd_stonewgt'] ?? 0);
            $dw2 = (float)($r['salesd_dmdwgt'] ?? 0);
            $totStWgt    += $dw2 > 0 ? $dw2 : $sw2;
            $totNetWgt   += $r['netwgt'] ?? 0;
            $totStPrice  += $r['stprice'] ?? 0;
            $totVA       += $r['va'] ?? 0;
            $totItemAmt  += $r['itemamt'] ?? 0;
            $totAmt      += ($r['netwgt'] ?? 0) * (float)($r['salesd_rate'] ?? 0);
            $bcvap_r = (float)($r['bcvap'] ?? 0);
            if ($bcvap_r > 0) {
                $amt_r   = (float)($r['salesd_amount'] ?? 0);
                $orgva_r = (float)($r['orgva'] ?? 0);
                $sp_r    = (float)($r['salesd_stoneprice'] ?? 0);
                $da_r    = (float)($r['dmdamt'] ?? 0);
                $tbcva  += ($amt_r - $orgva_r - $sp_r - $da_r) * $bcvap_r / 100;
            }
        }
        $padRows = max(0, $maxItems - count($rows));
        $totVADisplay = '';
        if ($printVA) {
            if ($printVAPerc) {
                $totVADisplay = $totAmt > 0 ? number_format(($totVA * 100) / $totAmt, 2) . '%' : '0%';
            } elseif ($printVAPerGm && $totNetWgt > 0) {
                $totVADisplay = number_format($totVA / $totNetWgt, 2) . $printVAPerGmTale;
            } else {
                $totVADisplay = number_format($totVA, 0);
            }
        }

        return view('sales-bill.print', compact(
            'slno', 'master', 'rows', 'padRows',
            'custname', 'custcode', 'billno', 'tdate', 'addr', 'ad1', 'ad2', 'ad3', 'ad4',
            'ctin', 'cphone', 'cmobile', 'mobno', 'phoneLine', 'coname',
            'custState', 'statecode', 'pan', 'note', 'placeos', 'control',
            'billamt', 'netamt', 'ramt', 'staxamt', 'staxperc', 'discount', 'discperc', 'eamt',
            'sretamt', 'round', 'astamt', 'advance', 'ccamt', 'chqamt', 'ob', 'schmamt',
            'exchangeRows', 'sretRows',
            'tcsperc', 'tcsamt', 'cst', 'grate', 'srate', 'smcode', 'counter', 'duedate',
            'gadvAmt', 'smanName', 'dmdWgt', 'orderno', 'orderPrintDetails',
            'cst', 'cgst', 'sgst', 'gstLabel', 'sgstLabel', 'effectiveTaxPerc', 'cgstPerc', 'sgstPerc',
            'grateDisplay', 'showDueDate',
            'subtotal', 'grandTotal', 'salesTotal', 'balance', 'cashRcvd', 'chqCcTotal', 'clBalance',
            'cbcode', 'cashReceived', 'bankReceived',
            'totQty', 'totGrossWgt', 'totStWgt', 'totNetWgt', 'totStPrice', 'totVA', 'totItemAmt', 'totAmt', 'tbcva',
            // settings
            'landscape', 'copies', 'zoom',
            'companyName', 'companyAddr', 'companyAddr2', 'companyState', 'companyGSTIN', 'companyPhone', 'companyLogo', 'g18rate',
            'companyBankName', 'companyBankAccountName', 'companyBankAccountNo', 'companyBankBranch', 'companyBankIFSC', 'showBankDetails',
            'showShopInfo', 'showStateCode', 'billFormEst', 'printGSTIN', 'printCustMob',
            'printGRate', 'printRate8gm', 'printSalesMan', 'printBCode',
            'printVA', 'printVAPerc', 'printVAPerGm', 'printVAInWgt', 'showPrintVAInWgt',
            'printDiscount', 'printDiscAlways', 'printOldBal', 'printFooter',
            'printRateStyle', 'printPartyCode', 'printNetVALast', 'showBCVADiff', 'totGST',
            'printOrgMCPerc', 'printBCVA', 'printVAPerGmTale', 'printVAAmtBelow',
            'billFooter1', 'billFooter2', 'totVADisplay'
        ));
    }

    // ── Save Setting (AJAX) ───────────────────────────────────────────────────

    public function saveSetting(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);
        // Settings are read-only in this Laravel app (stored in INI/JSON files)
        return response()->json(['ok' => true]);
    }

    // ── Build per-row computed fields ─────────────────────────────────────────

    private function buildRows(array $details, object $master, array $s,
                               array $itemMap, array $barcodeMap, array $dmdMap): array
    {
        $adjDiscToVA = $this->flag($s, 'Software', 'AdjustDiscountToVA', 'N');

        $rows = [];
        foreach ($details as $d) {
            $code     = $d['code'] ?? '';
            $weight   = (float)($d['weight'] ?? 0);
            $stonewgt = (float)($d['stonewgt'] ?? 0);
            $mcharge  = (float)($d['mcharge'] ?? 0);
            $wastage  = (float)($d['wastage'] ?? 0);
            $rate     = (float)($d['rate'] ?? 0);
            $amount   = (float)($d['amount'] ?? 0);
            $sno      = (int)($d['sno'] ?? 0);
            $bcode    = (int)($d['bcode'] ?? 0);
            $stoneprice = (float)($d['stoneprice'] ?? 0);
            $dmdwgt   = (float)($d['dmdwgt'] ?? 0);

            $netwgt  = $weight - $stonewgt;
            $orgva   = $mcharge + $wastage * $rate;
            $dmdamt  = $dmdMap[$sno] ?? 0;
            $stprice = $stoneprice + $dmdamt;
            $va      = $orgva;
            $itemamt = $amount;

            $item = $itemMap[$code] ?? [];
            $bc   = $bcode > 0 ? ($barcodeMap[$bcode] ?? []) : [];

            $row = array_merge($d, [
                'netwgt'          => $netwgt,
                'orgva'           => $orgva,
                'va'              => $va,
                'stprice'         => $stprice,
                'itemamt'         => $itemamt,
                'dmdamt'          => $dmdamt,
                'itemname'        => $item['name'] ?? ($d['name'] ?? ''),
                'vatcode'         => $item['vatcode'] ?? '',
                'items_printvaamt'=> $item['printvaamt'] ?? 'N',
                'itype'           => $item['itype'] ?? 'G',
                'bcvap'           => $bc['vap'] ?? 0,
                'bcmc'            => $bc['mc'] ?? 0,
                'huid'            => $d['huid'] ?? ($bc['huid'] ?? ''),
                'salesd_weight'   => $weight,
                'salesd_qty'      => $d['qty'] ?? 0,
                'salesd_rate'     => $rate,
                'salesd_stonewgt' => $stonewgt,
                'salesd_stoneprice'=> $stoneprice,
                'salesd_mcharge'  => $mcharge,
                'salesd_amount'   => $amount,
                'salesd_name'     => $d['name'] ?? '',
                'salesd_bcode'    => $bcode,
                'salesd_iqtype'   => $d['iqtype'] ?? '',
                'salesd_vaperc'   => $d['vaperc'] ?? 0,
                'salesd_dmdwgt'   => $dmdwgt,
                'salesd_model'    => $d['model'] ?? ($bc['model'] ?? ''),
                'salesd_huid'     => $d['huid'] ?? ($bc['huid'] ?? ''),
                'salesd_wastage'  => $wastage,
                'salesd_note'     => $d['note'] ?? '',
                'salesd_sno'      => $sno,
                'salesd_dmdamt_col'=> $dmdamt,
                'stcut'           => '',
                'stcolor'         => '',
            ]);
            $rows[] = $row;
        }
        return $rows;
    }

    private function postedOrderCashAdvanceForCustomer(string $ordno, string $custCode): float
    {
        $custCode = trim($custCode);
        $ordno = trim($ordno);
        if ($custCode === '' || $ordno === '' || !$this->hasTable('daybook')) {
            return 0.0;
        }

        $slnos = [];
        if ($this->hasTable('orderm')) {
            $orderSlno = (int) (DB::table('orderm')->whereRaw('TRIM(ordno)=?', [$ordno])->value('slno') ?? 0);
            if ($orderSlno > 0) {
                $slnos[] = $orderSlno;
            }
        }
        if ($this->hasTable('advafter')) {
            $slnos = array_merge($slnos, DB::table('advafter')
                ->whereRaw('TRIM(ordno)=?', [$ordno])
                ->pluck('slno')
                ->map(fn($v) => (int) $v)
                ->filter(fn($v) => $v > 0)
                ->all());
        }

        $slnos = array_values(array_unique($slnos));
        if ($slnos === []) {
            return 0.0;
        }

        return round((float) (DB::table('daybook')
            ->whereIn('slno', $slnos)
            ->whereRaw('TRIM(accode)=?', [$custCode])
            ->where('amount', '>', 0)
            ->sum('amount') ?? 0), 2);
    }
}
