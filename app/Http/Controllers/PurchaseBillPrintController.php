<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class PurchaseBillPrintController extends Controller
{
    public function show(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $docNo = trim((string) $request->query('doc_no', ''));
        $slno = (int) $request->query('slno', 0);

        if (!$this->hasTable('purchasem')) {
            return response('<p style="padding:20px">Purchase table not found.</p>', 404);
        }

        $query = DB::table('purchasem')->where('pr', 'P');
        if ($slno > 0) {
            $query->where('slno', $slno);
        } elseif ($docNo !== '') {
            $query->where('docno', $docNo);
        } else {
            return response('<p style="padding:20px">No purchase bill selected.</p>', 400);
        }

        $master = $query->first();
        if (!$master) {
            return response('<p style="padding:20px">Purchase bill not found.</p>', 404);
        }

        $detailRows = $this->hasTable('purchased')
            ? DB::table('purchased')->where('slno', $master->slno)->orderBy('sno')->get()
            : collect();

        $exchangeRows = $this->hasTable('purchaserd')
            ? DB::table('purchaserd')->where('slno', $master->slno)->orderBy('sno')->get()
            : collect();

        $itemCodes = $detailRows->pluck('code')->merge($exchangeRows->pluck('code'))
            ->filter(fn ($code) => trim((string) $code) !== '')
            ->unique()
            ->values()
            ->all();

        $itemNames = [];
        if (!empty($itemCodes) && $this->hasTable('items')) {
            $itemNames = DB::table('items')
                ->whereIn('code', $itemCodes)
                ->pluck('name', 'code')
                ->map(fn ($name) => trim((string) $name))
                ->all();
        }

        $supplier = null;
        if (!empty($master->suppcode) && $this->hasTable('clients')) {
            $supplier = DB::table('clients')->where('code', $master->suppcode)->first();
        }

        $salesmanName = '';
        if (!empty($master->smcode) && $this->hasTable('sman')) {
            $salesmanName = (string) (DB::table('sman')->where('code', $master->smcode)->value('name') ?? '');
        }

        $stateName = '';
        if (!empty($master->statecode)) {
            if ($this->hasTable('statestate')) {
                $stateName = (string) (DB::table('statestate')->where('code', $master->statecode)->value('name') ?? '');
            } elseif ($this->hasTable('state')) {
                $stateName = (string) (DB::table('state')->where('code', $master->statecode)->value('name') ?? '');
            }
        }

        $rows = $detailRows->map(function ($row, $index) use ($itemNames) {
            $fallbackName = $itemNames[$row->code] ?? '';

            return [
                'sno' => $index + 1,
                'code' => trim((string) ($row->code ?? '')),
                'name' => trim((string) (($row->name ?? '') !== '' ? $row->name : $fallbackName)),
                'purity' => trim((string) ($row->iqtype ?? '')),
                'qty' => (float) ($row->qty ?? 0),
                'weight' => (float) ($row->weight ?? 0),
                'stone_weight' => (float) ($row->stwgt ?? 0),
                'mud_less' => (float) ($row->mud ?? 0),
                'less_weight' => (float) ($row->lesswgt ?? 0),
                'net_weight' => (float) (($row->netwgt ?? (($row->weight ?? 0) - ($row->stwgt ?? 0) - ($row->lesswgt ?? 0) - ($row->mud ?? 0))) ?? 0),
                'rate' => (float) ($row->rate ?? 0),
                'mc' => (float) ($row->mcharge ?? 0),
                'amount' => (float) ($row->amount ?? 0),
            ];
        })->all();

        $exchange = $exchangeRows->map(function ($row, $index) use ($itemNames) {
            $fallbackName = $itemNames[$row->code] ?? '';

            return [
                'sno' => $index + 1,
                'code' => trim((string) ($row->code ?? '')),
                'name' => trim((string) (($row->name ?? '') !== '' ? $row->name : $fallbackName)),
                'qty' => (float) ($row->qty ?? 0),
                'weight' => (float) ($row->weight ?? 0),
                'less_perc' => (float) ($row->lessperc ?? 0),
                'less_weight' => (float) ($row->lesswgt ?? 0),
                'rate' => (float) ($row->rate ?? 0),
                'amount' => (float) ($row->amount ?? 0),
            ];
        })->all();

        $settings = $this->loadSettings();
        $company = $this->loadCompanyInfo($settings);

        $taxPerc = (float) ($master->taxperc ?? 0);
        $cgst = (float) ($master->cgst ?? 0);
        $sgst = (float) ($master->sgst ?? 0);
        $igst = (float) ($master->igst ?? 0);
        $taxAmt = (float) ($master->taxamt ?? 0);
        if ($cgst <= 0 && $sgst <= 0 && $igst <= 0 && $taxAmt > 0) {
            if (($master->cst ?? 'N') === 'Y') {
                $igst = $taxAmt;
            } else {
                $cgst = round($taxAmt / 2, 2);
                $sgst = round($taxAmt / 2, 2);
            }
        }
        $cgstPerc = ($cgst > 0 && $taxPerc > 0) ? $taxPerc / 2 : 0.0;
        $sgstPerc = ($sgst > 0 && $taxPerc > 0) ? $taxPerc / 2 : 0.0;
        $igstPerc = ($igst > 0) ? $taxPerc : 0.0;
        $paidAmount = (float) ($master->pamt ?? 0);
        $balance = (float) ($master->bal ?? (($master->netamt ?? 0) - $paidAmount));

        return view('purchase-bill.print', [
            'master' => $master,
            'rows' => $rows,
            'exchange' => $exchange,
            'supplier' => $supplier,
            'salesmanName' => $salesmanName,
            'stateName' => $stateName,
            'company' => $company,
            'taxPerc' => $taxPerc,
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => $igst,
            'cgstPerc' => $cgstPerc,
            'sgstPerc' => $sgstPerc,
            'igstPerc' => $igstPerc,
            'paidAmount' => $paidAmount,
            'balance' => $balance,
            'autoprint' => $request->boolean('autoprint'),
        ]);
    }

    private function loadSettings(): array
    {
        $iniPath = storage_path('app/software-settings.ini');
        if (!File::exists($iniPath)) {
            return ['Company' => []];
        }

        $parsed = parse_ini_string((string) File::get($iniPath), true, INI_SCANNER_RAW);
        return is_array($parsed) ? $parsed : ['Company' => []];
    }

    private function loadCompanyInfo(array $settings): array
    {
        $info = [
            'name' => (string) ($settings['Company']['Name'] ?? config('app.name', '')),
            'address' => (string) ($settings['Company']['Addr'] ?? ($settings['Company']['Addr1'] ?? '')),
            'address2' => (string) ($settings['Company']['Addr2'] ?? ''),
            'phone' => (string) ($settings['Company']['Phone'] ?? ''),
            'gstin' => (string) ($settings['Company']['GSTIN'] ?? ($settings['Company']['KGST'] ?? '')),
        ];

        if ($this->hasTable('generals')) {
            try {
                $generalRows = DB::table('generals')
                    ->whereIn('code', ['SHOPNM', 'SHOPADDR', 'SHOPPHONE', 'SHOPGSTIN', 'GSTIN'])
                    ->get(['code', 'cvalue']);

                foreach ($generalRows as $row) {
                    $code = trim((string) ($row->code ?? ''));
                    $value = trim((string) ($row->cvalue ?? ''));
                    if ($value === '') continue;

                    if ($code === 'SHOPNM')   $info['name'] = $value;
                    if ($code === 'SHOPADDR') $info['address'] = $value;
                    if ($code === 'SHOPPHONE')$info['phone'] = $value;
                    if ($code === 'SHOPGSTIN' || $code === 'GSTIN') $info['gstin'] = $value;
                }
            } catch (\Throwable) {
            }
        }

        return $info;
    }
}
