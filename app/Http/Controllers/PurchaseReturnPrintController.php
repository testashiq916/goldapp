<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PurchaseReturnPrintController extends Controller
{
    private function loadSettings(): array
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        $ini = storage_path('app/software-settings.ini');
        if (!File::exists($ini)) {
            return $cached = ['Software' => [], 'Company' => [], 'Printer' => [], 'Rates' => []];
        }

        $out = [];
        $sec = '';
        foreach (preg_split('/\r\n|\r|\n/', (string) File::get($ini)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === ';' || $line[0] === '#' || str_starts_with($line, '//')) continue;
            if (preg_match('/^\[(.+)\]$/', $line, $m)) {
                $sec = $m[1];
                continue;
            }
            if ($sec && str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $out[$sec][trim($k)] = trim($v);
            }
        }
        foreach (['Software', 'Company', 'Printer', 'Rates'] as $req) {
            if (!isset($out[$req])) $out[$req] = [];
        }

        return $cached = $out;
    }

    private function ini(array $settings, string $section, string $key, string $default = ''): string
    {
        return (string) ($settings[$section][$key] ?? $default);
    }

    private function flag(array $settings, string $section, string $key, string $default = 'N'): bool
    {
        return strtoupper($this->ini($settings, $section, $key, $default)) === 'Y';
    }

    private function generalRate(string $code): float
    {
        foreach (['generald', 'generals'] as $table) {
            if (!$this->hasTable($table)) continue;
            $row = DB::table($table)->where('code', $code)->first();
            if ($row) return (float) (($row->cvalue ?? null) ?: ($row->dvalue ?? 0));
        }
        return 0.0;
    }

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
        $docNo = trim((string) ($request->query('doc_no') ?: $request->query('billno', '')));
        if ($slno <= 0 && $docNo === '') {
            return response('<p style="padding:20px">No purchase return selected. Usage: ?slno=XXX or ?doc_no=YYY</p>', 400);
        }
        if (!$this->hasTable('purchaserm')) {
            return response('<p style="padding:20px">Purchase return table not found.</p>', 404);
        }

        $query = DB::table('purchaserm')->where('pr', 'R');
        $master = $slno > 0
            ? $query->where('slno', $slno)->first()
            : $query->where('docno', $docNo)->orderByDesc('slno')->first();
        if (!$master) {
            return response('<p style="padding:20px">Purchase return bill not found.</p>', 404);
        }
        $slno = (int) $master->slno;

        $settings = $this->loadSettings();

        $detailRows = [];
        if ($this->hasTable('purchaserd')) {
            $detailRows = DB::select("
                SELECT rd.*, COALESCE(i.name,'') as itemname2, COALESCE(i.itype,'') as itemtype,
                       COALESCE(i.vatcode,'') as vatcode
                FROM purchaserd rd
                LEFT JOIN items i ON i.code = rd.code
                WHERE rd.slno = ?
                ORDER BY rd.sno
            ", [$slno]);
            $detailRows = array_map(fn ($row) => (array) $row, $detailRows);
        }

        $supplier = [];
        if (!empty($master->suppcode) && $this->hasTable('clients')) {
            $row = DB::table('clients')->where('code', $master->suppcode)->first();
            if ($row) $supplier = (array) $row;
        }

        $smanName = '';
        if (!empty($master->smcode) && $this->hasTable('sman')) {
            $smanName = (string) (DB::table('sman')->where('code', $master->smcode)->value('name') ?? '');
        }

        $stateName = '';
        if (!empty($master->statecode ?? null)) {
            foreach (['statestate', 'state'] as $table) {
                if (!$this->hasTable($table)) continue;
                $stateName = (string) (DB::table($table)->where('code', $master->statecode)->value('name') ?? '');
                if ($stateName !== '') break;
            }
        }

        $sw = fn($key, $default = '') => $this->ini($settings, 'Software', $key, $default);
        $fl = fn($key, $default = 'N') => $this->flag($settings, 'Software', $key, $default);

        $showShopInfo = $fl('ShowShopInfoInSalesPrint', 'N');
        $printGSTIN = $fl('PrintGSTINAlways', 'Y');
        $printCustMob = $fl('PrintCustMobNo', 'Y');
        $printSalesMan = $fl('PrintSalesManName', 'N');
        $printDiscount = $fl('PrintDiscount', 'Y');
        $printFooter = $fl('PrintFooterInBIll', 'N');
        $billFooter1 = $sw('BillFooter1', '');
        $billFooter2 = $sw('BillFooter2', '');
        $copies = $this->ini($settings, 'Printer', 'Copies', '1');
        $zoom = $this->ini($settings, 'Printer', 'SZoom', '90');

        $generals = [];
        if ($this->hasTable('generals')) {
            try {
                DB::table('generals')
                    ->whereIn('code', ['SHOPNM', 'SHOPADDR', 'SHOPPHONE'])
                    ->get(['code', 'cvalue'])
                    ->each(function ($row) use (&$generals) {
                        $generals[trim((string) $row->code)] = (string) ($row->cvalue ?? '');
                    });
            } catch (\Throwable) {}
        }

        $companyName = ($generals['SHOPNM'] ?? '') ?: $this->ini($settings, 'Company', 'Name', '');
        $companyAddr = ($generals['SHOPADDR'] ?? '') ?: ($this->ini($settings, 'Company', 'Addr', '') ?: $this->ini($settings, 'Company', 'Addr1', ''));
        $companyAddr2 = $this->ini($settings, 'Company', 'Addr2', '') ?: $this->ini($settings, 'Company', 'Address2', '');
        $companyState = $this->ini($settings, 'Company', 'State', '') ?: $this->ini($settings, 'Company', 'StateCodeName', '');
        $companyGSTIN = $this->ini($settings, 'Company', 'GSTIN', '') ?: $this->ini($settings, 'Company', 'KGST', '');
        $companyPhone = ($generals['SHOPPHONE'] ?? '') ?: $this->ini($settings, 'Company', 'Phone', '');

        $billno = $master->docno ?? '';
        $tdate = $master->tdate ?? '';
        $custname = $master->name ?? '';
        $custcode = $master->suppcode ?? '';
        $billamt = (float) ($master->billamt ?? 0);
        $netamt = (float) ($master->netamt ?? 0);
        $pamt = (float) ($master->pamt ?? 0);
        $staxamt = (float) ($master->taxamt ?? 0);
        $staxperc = (float) ($master->taxperc ?? 0);
        $discount = 0.0;
        $astamt = (float) ($master->addamt ?? 0);
        $sgst = (float) ($master->sgst ?? 0);
        $cgst = (float) ($master->cgst ?? 0);
        $igst = (float) ($master->igst ?? 0);
        $cst = $master->cst ?? 'N';
        $ob = (float) ($master->ob ?? 0);
        $sbillno = $master->billno ?? '';
        $control = (int) ($master->control ?? 1);
        $smcode = $master->smcode ?? '';
        $grate = (float) ($master->rate ?? 0);
        if ($grate <= 0) $grate = $this->generalRate('GRATE');

        $ad1 = $master->addr ?? ($supplier['addr1'] ?? '');
        $ad2 = $supplier['addr2'] ?? '';
        $ad3 = $supplier['addr3'] ?? '';
        $ad4 = $supplier['city'] ?? '';
        $addr = $ad1 ?: '';
        $ctin = $supplier['tin'] ?? ($master->gstno ?? '');
        $cphone = $supplier['telephone'] ?? '';
        $cmobile = $master->mobile ?? ($supplier['mobile'] ?? '');
        $pan = $master->pan ?? ($supplier['panadhar'] ?? '');
        $phoneLine = trim(($cphone ? $cphone . ' ' : '') . ($cmobile ?: ''));
        $statecode = $master->statecode ?? '';
        $custState = $statecode ? trim($statecode . '-' . $stateName, '-') : $companyState;

        $effectiveTaxPerc = $staxperc;
        if ($effectiveTaxPerc <= 0 && $staxamt > 0) {
            $base = $billamt > 0 ? $billamt : max($netamt - $staxamt, 0);
            if ($base > 0) $effectiveTaxPerc = round(($staxamt * 100) / $base, 3);
        }
        if (strtoupper((string) $cst) === 'Y') {
            $gstLabel = 'IGST (' . number_format($effectiveTaxPerc, 1) . '%)';
            $sgstLabel = '';
            $cgstShow = $staxamt;
            $sgstShow = 0.0;
        } else {
            $half = $effectiveTaxPerc / 2;
            $gstLabel = 'CGST (' . number_format($half, 1) . '%)';
            $sgstLabel = 'SGST (' . number_format($half, 1) . '%)';
            $cgstShow = $staxamt / 2;
            $sgstShow = $staxamt / 2;
        }

        $rows = [];
        $totQty = 0; $totGrossWgt = 0.0; $totStWgt = 0.0; $totNetWgt = 0.0;
        $totStPrice = 0.0; $totMc = 0.0; $totAmt = 0.0;
        foreach ($detailRows as $row) {
            $weight = (float) ($row['weight'] ?? 0);
            $stoneWgt = (float) ($row['stwgt'] ?? $row['stonewgt'] ?? 0);
            $lessWgt = (float) ($row['lesswgt'] ?? 0);
            $netWgt = max($weight - $lessWgt, 0);
            $qty = (int) ($row['qty'] ?? 0);
            $amount = (float) ($row['amount'] ?? 0);
            $stonePrice = (float) ($row['stprice'] ?? $row['stoneprice'] ?? 0);
            $mc = (float) ($row['mcharge'] ?? 0);

            $totQty += $qty;
            $totGrossWgt += $weight;
            $totStWgt += $stoneWgt;
            $totNetWgt += $netWgt;
            $totStPrice += $stonePrice;
            $totMc += $mc;
            $totAmt += $amount;

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') $name = (string) ($row['itemname2'] ?? $row['code'] ?? '');

            $rows[] = [
                'sno' => (int) ($row['sno'] ?? 0),
                'code' => $row['code'] ?? '',
                'name' => $name,
                'qty' => $qty,
                'weight' => $weight,
                'stonewgt' => $stoneWgt,
                'netwgt' => $netWgt,
                'rate' => (float) ($row['rate'] ?? 0),
                'stoneprice' => $stonePrice,
                'mcharge' => $mc,
                'wastage' => (float) ($row['wastage'] ?? 0),
                'amount' => $amount,
                'iqtype' => $row['iqtype'] ?? '',
                'vatcode' => $row['vatcode'] ?? '',
                'note' => $row['note'] ?? '',
            ];
        }

        $refund = $netamt - $pamt;
        $balance = $netamt - $pamt;
        $clBalance = $ob - $balance;
        $printDocumentTitle = ($control === 1) ? 'Purchase Return Invoice' : 'Purchase Return Estimate';
        $landscape = false;
        $printKind = 'purchase-return';
        $partyLabel = 'Supplier';
        $partyDetailsLabel = 'Supplier Details';
        $partySignatureLabel = 'Supplier Signature';
        $origBillLabel = 'Supplier Bill';
        $netTotalLabel = 'Net Total';
        $paidLabel = 'Received Amt';

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
            'printDocumentTitle', 'printKind', 'partyLabel', 'partyDetailsLabel',
            'partySignatureLabel', 'origBillLabel', 'netTotalLabel', 'paidLabel'
        ));
    }
}
