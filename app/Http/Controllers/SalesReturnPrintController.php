<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SalesReturnPrintController extends Controller
{
    // ── Settings ──────────────────────────────────────────────────────────────

    private function loadSettings(): array
    {
        static $cached = null;
        if ($cached !== null) return $cached;
        $ini = storage_path('app/software-settings.ini');
        if (File::exists($ini)) {
            return $cached = $this->parseIni((string) File::get($ini));
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
        foreach (['Software', 'Company', 'Printer', 'Rates'] as $req) {
            if (!isset($out[$req])) $out[$req] = [];
        }
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

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = Schema::hasColumn($table, $column);
        }
        return $cache[$key];
    }

    private function generalRate(string $code): float
    {
        static $cache = null;
        if ($cache === null) {
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
                    break;
                } catch (\Throwable) {}
            }
        }
        return $cache[strtoupper(trim($code))] ?? 0.0;
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
        $billno = trim((string) $request->query('billno', ''));

        if ($slno <= 0 && $billno === '') {
            return response('<p style="padding:20px">No bill selected. Usage: ?slno=XXX or ?billno=YYY</p>', 400);
        }

        if (!$this->hasTable('salesrm')) {
            return response('<p style="padding:20px">Sales return table not found.</p>', 404);
        }

        $q = DB::table('salesrm');
        if ($slno > 0) {
            $master = $q->where('slno', $slno)->first();
        } else {
            $master = $q->where('billno', $billno)->where('sr', 'R')->orderByDesc('slno')->first();
        }
        if (!$master) {
            return response('<p style="padding:20px">Sales return bill not found.</p>', 404);
        }
        $slno = (int) $master->slno;

        $s = $this->loadSettings();

        // ── Details ─────────────────────────────────────────────────────────
        $detailRows = [];
        if ($this->hasTable('salesrd')) {
            $detailRows = DB::select("
                SELECT rd.*, COALESCE(i.name,'') as itemname2, COALESCE(i.itype,'') as itemtype,
                       COALESCE(i.vatcode,'') as vatcode
                FROM salesrd rd
                LEFT JOIN items i ON i.code = rd.code
                WHERE rd.slno = ?
                ORDER BY rd.sno
            ", [$slno]);
            $detailRows = array_map(fn ($r) => (array) $r, $detailRows);
        }

        // ── Customer info ───────────────────────────────────────────────────
        $cust = [];
        if (!empty($master->custcode) && $this->hasTable('clients')) {
            $c = DB::table('clients')->where('code', $master->custcode)->first();
            if ($c) $cust = (array) $c;
        }

        // ── Salesman name ───────────────────────────────────────────────────
        $smanName = '';
        if (!empty($master->smcode) && $this->hasTable('sman')) {
            $smanName = (string) (DB::table('sman')->where('code', $master->smcode)->value('name') ?? '');
        }

        // ── State name ──────────────────────────────────────────────────────
        $stateName = '';
        if (!empty($master->statecode ?? null) && $this->hasTable('state')) {
            $stateName = (string) (DB::table('state')->where('code', $master->statecode)->value('name') ?? '');
        }

        // ── INI Settings ──────────────────────────────────────────────────
        $sw = fn($k, $d='') => $this->ini($s, 'Software', $k, $d);
        $fl = fn($k, $d='N') => $this->flag($s, 'Software', $k, $d);

        $showShopInfo   = $fl('ShowShopInfoInSalesPrint', 'N');
        $printGSTIN     = $fl('PrintGSTINAlways', 'Y');
        $printCustMob   = $fl('PrintCustMobNo', 'Y');
        $printSalesMan  = $fl('PrintSalesManName', 'N');
        $printDiscount  = $fl('PrintDiscount', 'Y');
        $printFooter    = $fl('PrintFooterInBIll', 'N');
        $billFooter1    = $sw('BillFooter1', '');
        $billFooter2    = $sw('BillFooter2', '');

        $copies     = $this->ini($s, 'Printer', 'Copies', '1');
        $zoom       = $this->ini($s, 'Printer', 'SZoom', '90');

        // ── Company / Shop ────────────────────────────────────────────────
        $generals = [];
        try {
            if ($this->hasTable('generals')) {
                $generalRows = DB::table('generals')
                    ->whereIn('code', ['SHOPNM', 'SHOPADDR', 'SHOPPHONE', 'SHOPLOGO'])
                    ->get(['code', 'cvalue']);
                foreach ($generalRows as $r) {
                    $generals[trim($r->code)] = (string) ($r->cvalue ?? '');
                }
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

        // ── Master field aliases ──────────────────────────────────────────
        $billno   = $master->billno ?? '';
        $tdate    = $master->tdate ?? '';
        $custname = $master->custname ?? '';
        $custcode = $master->custcode ?? '';
        $billamt  = (float)($master->billamt ?? 0);
        $netamt   = (float)($master->netamt ?? 0);
        $pamt     = (float)($master->pamt ?? 0);
        $staxamt  = (float)($master->staxamt ?? 0);
        $staxperc = (float)($master->staxperc ?? 0);
        $discount = (float)($master->discount ?? 0);
        $astamt   = (float)($master->astamt ?? 0);
        $sgst     = (float)($master->sgst ?? 0);
        $cgst     = (float)($master->cgst ?? 0);
        $igst     = (float)($master->igst ?? 0);
        $cst      = $master->cst ?? 'N';
        $ob       = (float)($master->ob ?? 0);
        $sbillno  = $master->sbillno ?? '';
        $control  = (int)($master->control ?? 1);
        $smcode   = $master->smcode ?? '';

        $grate    = (float) ($master->grate ?? 0);
        if ($grate <= 0) $grate = $this->generalRate('GRATE');

        // ── Customer address ──────────────────────────────────────────────
        $ad1 = $cust['addr1'] ?? ''; $ad2 = $cust['addr2'] ?? '';
        $ad3 = $cust['addr3'] ?? ''; $ad4 = $cust['city'] ?? '';
        $addr = $ad1 ?: '';
        $ctin = $cust['tin'] ?? '';
        $cphone  = $cust['telephone'] ?? '';
        $cmobile = $cust['mobile'] ?? '';
        $pan     = $cust['pan'] ?? ($master->pan ?? '');
        $phoneLine = trim(($cphone ? $cphone . ' ' : '') . ($cmobile ? $cmobile : ''));

        $statecode  = $master->statecode ?? '';
        $custState  = $statecode ? $statecode . '-' . $stateName : $companyState;

        // ── GST split ─────────────────────────────────────────────────────
        $effectiveTaxPerc = $staxperc;
        if ($effectiveTaxPerc <= 0 && $staxamt > 0) {
            $base = $billamt > 0 ? $billamt : max($netamt - $staxamt, 0);
            if ($base > 0) $effectiveTaxPerc = round(($staxamt * 100) / $base, 3);
        }
        if (strtoupper((string)$cst) === 'Y') {
            $gstLabel  = 'IGST (' . number_format($effectiveTaxPerc, 1) . '%)';
            $sgstLabel = '';
            $cgstShow  = $staxamt;
            $sgstShow  = 0.0;
        } else {
            $half = $effectiveTaxPerc / 2;
            $gstLabel  = 'CGST (' . number_format($half, 1) . '%)';
            $sgstLabel = 'SGST (' . number_format($half, 1) . '%)';
            $cgstShow  = $staxamt / 2;
            $sgstShow  = $staxamt / 2;
        }

        // ── Row totals ────────────────────────────────────────────────────
        $rows = [];
        $totQty = 0; $totGrossWgt = 0.0; $totStWgt = 0.0; $totNetWgt = 0.0;
        $totStPrice = 0.0; $totMc = 0.0; $totAmt = 0.0;
        foreach ($detailRows as $d) {
            $w  = (float)($d['weight'] ?? 0);
            $sw2 = (float)($d['stonewgt'] ?? 0);
            $nw = max($w - $sw2, 0);
            $amt = (float)($d['amount'] ?? 0);
            $sp  = (float)($d['stoneprice'] ?? 0);
            $mc  = (float)($d['mcharge'] ?? 0);
            $qty = (int)($d['qty'] ?? 0);

            $totQty      += $qty;
            $totGrossWgt += $w;
            $totStWgt    += $sw2;
            $totNetWgt   += $nw;
            $totStPrice  += $sp;
            $totMc       += $mc;
            $totAmt      += $amt;

            $iname = trim((string)($d['name'] ?? ''));
            if ($iname === '') $iname = (string)($d['itemname2'] ?? $d['code'] ?? '');

            $rows[] = [
                'sno'      => (int)($d['sno'] ?? 0),
                'code'     => $d['code'] ?? '',
                'name'     => $iname,
                'qty'      => $qty,
                'weight'   => $w,
                'stonewgt' => $sw2,
                'netwgt'   => $nw,
                'rate'     => (float)($d['rate'] ?? 0),
                'stoneprice' => $sp,
                'mcharge'  => $mc,
                'wastage'  => (float)($d['wastage'] ?? 0),
                'amount'   => $amt,
                'iqtype'   => $d['iqtype'] ?? '',
                'vatcode'  => $d['vatcode'] ?? '',
                'note'     => $d['note'] ?? '',
            ];
        }

        // ── Computed totals ───────────────────────────────────────────────
        $discAdj  = $printDiscount ? $discount : 0;
        $refund   = $netamt - $pamt;
        $balance  = $netamt - $pamt;
        $clBalance = $ob + $balance;

        $printDocumentTitle = ($control == 1) ? 'Sales Return Invoice' : 'Sales Return Estimate';

        $landscape = false;

        return view('sales-return.print', compact(
            'slno', 'master', 'rows',
            'custname', 'custcode', 'billno', 'tdate', 'sbillno',
            'addr', 'ad1', 'ad2', 'ad3', 'ad4',
            'ctin', 'cphone', 'cmobile', 'phoneLine',
            'custState', 'statecode', 'pan', 'control',
            'billamt', 'netamt', 'pamt', 'staxamt', 'staxperc', 'discount', 'astamt',
            'sgst', 'cgst', 'igst', 'cst', 'ob', 'grate',
            'smcode', 'smanName',
            'gstLabel', 'sgstLabel', 'cgstShow', 'sgstShow', 'effectiveTaxPerc',
            'refund', 'balance', 'clBalance',
            'totQty', 'totGrossWgt', 'totStWgt', 'totNetWgt', 'totStPrice', 'totMc', 'totAmt',
            'landscape', 'copies', 'zoom',
            'companyName', 'companyAddr', 'companyAddr2', 'companyState', 'companyGSTIN', 'companyPhone',
            'showShopInfo', 'printGSTIN', 'printCustMob', 'printSalesMan', 'printDiscount',
            'printFooter', 'billFooter1', 'billFooter2',
            'printDocumentTitle'
        ));
    }
}
