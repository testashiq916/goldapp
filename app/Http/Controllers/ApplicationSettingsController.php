<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ApplicationSettingsController extends Controller
{
    use LogsDelpartAudit;

    private const DEFAULTS = [
        'Application' => [
            'AppPath'     => '.',
            'BCPhotoPath' => '',
        ],
        'API' => [
            'SMSPROVIDER'  => '',
            'SMSAPIURL'    => '',
            'SMSAPIKEY'    => '',
            'SMSSENDERID'  => '',
            'SMSENTITYID'  => '',
            'SMSTEMPLATEID'=> '',
            'SMSROUTE'     => '',
            'WAPPROVIDER'  => '',
            'WAPAPIURL'    => '',
            'WAPAPIKEY'    => '',
            'WAPSENDER'    => '',
            'EMAILPROVIDER'=> '',
            'EMAILAPIURL'  => '',
            'EMAILAPIKEY'  => '',
            'EMAILFROMNAME'=> '',
            'EMAILFROMADDR'=> '',
            'EINVOICEAPIURL' => '',
            'EINVOICETOKENURL' => '',
            'EINVOICEPROVIDER' => '',
            'EINVOICEAUTHMODE' => 'basic',
            'EINVOICEUSERNAME' => '',
            'EINVOICEPASSWORD' => '',
            'EINVOICEUSERGSTIN' => '',
            'EINVOICESELLERGSTIN' => '',
            'EINVOICEGSPUSERNAME' => '',
            'EINVOICEGSPPASSWORD' => '',
            'EINVOICEBUYERGSTIN' => '',
            'EINVOICEAPIKEY' => '',
            'EINVOICEAPIKEYHEADER' => 'x-api-key',
            'EINVOICEEXTRAHEADERS' => '',
            'EINVOICEPAYLOADROOT' => '',
            'EINVOICEIRNKEY' => '',
            'EINVOICEACKNOKEY' => '',
            'EINVOICEACKDATEKEY' => '',
            'EINVOICEQRKEY' => '',
            'EWAYAPIURL' => '',
            'EWAYTOKENURL' => '',
            'EWAYUSERNAME' => '',
            'EWAYPASSWORD' => '',
            'EWAYUSERGSTIN' => '',
            'EWAYSELLERGSTIN' => '',
            'EWAYGSPUSERNAME' => '',
            'EWAYGSPPASSWORD' => '',
            'EWAYTRANSPORTERID' => '',
            'EWAYTRANSPORTERNAME' => '',
        ],
        'Company' => [
            'Name'         => '',
            'Addr'         => '',
            'Addr1'        => '',
            'Addr2'        => '',
            'Phone'        => '',
            'Branch'       => '',
            'BankName'     => '',
            'BankAccountName' => '',
            'BankAccountNo'   => '',
            'BankBranch'      => '',
            'BankIFSC'        => '',
            'KGST'         => '',
            'CST'          => '',
            'TIN'          => '',
            'DefStateCode' => '',
            'HOMailID'     => '',
            'TaxRules'     => '',
            'BillForm'     => '1',
            'OrderForm'    => '1',
            'Stktype'      => '1',
        ],
        'Rates' => [
            'GRATE'   => '0.00',
            'G18RATE' => '0.00',
            'SRATE'   => '0.00',
            'JRATE'   => '0.00',
            'OGRATE'  => '0.00',
            'OSRATE'  => '0.00',
            'THRATE'  => '0.000',
            'PRATE'   => '0.00',
            'BULRATE' => '0.00',
            'BULTOUCH'=> '99.5',
        ],
        'Software' => [
            // Tax
            'TaxSystem'        => 'GST',
            'GSTEnable'        => 'Y',
            'VATEnable'        => 'N',
            'IGSTEnable'       => 'N',
            'TCSEnable'        => 'N',
            'TCSPerc'          => '1',
            'CessEnable'       => 'N',
            'CessPerc'         => '0',
            'TaxAfterDiscount' => 'Y',
            'HSNEnable'        => 'Y',
            'CompanyState'     => '',
            'DefTaxPerc'       => '3',
            'DefSGST'          => '1.5',
            'DefCGST'          => '1.5',
            'DefIGST'          => '3',
            // Bill / Starting
            'DefBillType'          => 'G',
            'BillTypewiseBillNo'   => 'Y',
            'StartBNo'             => '',
            'BillForm'             => '1',
            'OrderForm'            => '2',
            'Stktype'              => '1',
            'StartDate'            => '',
            'StartENo'             => '1',
            'EstStarNo'            => '1000',
            'RestartEstNoEachDay'  => 'Y',
            'OrderSalesSeperateNo' => 'Y',
            'SalesFirstQtnNo'      => 'N',
            // Printer / Display
            'PrinterType'          => 'Laser',
            'ToPrinter'            => '',
            'ToPrinterBill'        => '',
            'AskPrinterAlways'     => 'N',
            'ShowPreviewBeforePrint'=> 'Y',
            'AllReportPrintPreview' => 'Y',
            'PrintGSTINAlways'     => 'Y',
            'PrintTimeInBills'     => 'Y',
            // Margins
            'TopMargin'    => '230',
            'LeftMargin'   => '100',
            'BottomMargin' => '10',
            'PrintLetterheadMode' => 'PREPRINTED',
            'PrintPageAlign' => 'LEFT',
            'PrinterTopMargin' => '230',
            'PrinterLeftMargin' => '100',
            'PrinterPageAlign' => 'LEFT',
            'ReportTopMargin' => '0',
            'ReportLeftMargin' => '0',
            'ReportPageAlign' => 'LEFT',
            'AppHeaderTopMargin' => '10',
            'AppHeaderLeftMargin' => '4',
            'AppHeaderPageAlign' => 'CENTER',
            'AskEInvoiceAboveAmount' => 'Y',
            'EInvoiceThresholdAmount' => '1000000',
            'EWayBillThresholdAmount' => '1000000',
            // Rates / Bullion
            'SRateBeforeTax'          => 'N',
            'SRateBasedOnBullionRate' => 'N',
            'PurchConvTouch'          => '0.00',
            'SDiscPerc'               => '0.00',
            'TDSPerc'                 => '0.00',
            'DefRate'                 => 'RTR',
            'AutoGoldRate'            => 'Y',
            // Touch / Weight
            'StdTouch'              => '98.',
            'ToStockTouch'          => '90.',
            'WithStockTouchCalc'    => 'N',
            'CoperAuto'             => 'Y',
            'CoperPerc'             => '8.',
            'WastageInNetWgt'       => 'Y',
            // Sales options
            'SalesFullScreen'         => 'N',
            'SalesConfirmModel'       => 'N',
            'EstimateConfirmModel'    => 'N',
            'EstToBill'               => 'N',
            'EstToBillModel'          => 'N',
            'ShowEstNumbers'          => 'N',
            'RoundOffAllAmt'          => 'Y',
            'AmtRoundTo'              => '1.00',
            'Amt3Dec'                 => 'N',
            'DateEncrypt'             => 'N',
            'ShowOldBalance'          => 'Y',
            'PrintOldBalance'         => 'Y',
            'AllowInsufficientStockSales' => 'Y',
            'AllowSalesBillManual'    => 'N',
            'FocusOnPartyCode'        => 'Y',
            'ShowAllClientsInSale'    => 'N',
            'MobileNoBasedSearch'     => 'Y',
            'CoPartySeperate'         => 'Y',
            'CheckCoPartyCreditLimit' => 'N',
            'CreditBillCoCompulsory'  => 'N',
            'StopOnMudLess'           => 'Y',
            'StopOnItemName'          => 'N',
            'StopOnModel'             => 'Y',
            'StopOnStwgt'             => 'Y',
            'StopOnMcPerc'            => 'Y',
            'QtyMust'                 => 'N',
            'QtyWarning'              => 'N',
            // Print options
            'PrintMC'              => 'Y',
            'PrintWastage'         => 'Y',
            'PrintVA'              => 'Y',
            'PrintRate'            => 'Y',
            'PrintHSN'             => 'Y',
            'PrintHUID'            => 'N',
            'PrintTax'             => 'Y',
            'PrintLogo'            => 'N',
            'LogoPath'             => '',
            'PrintPartyAddress'    => 'Y',
            'PrintPartyCodeInSales'=> 'Y',
            'PrintBCodeInSales'    => 'Y',
            'PrintTotWgt'          => 'Y',
            'PrintTotalMC'         => 'N',
            'PrintTotalVA'         => 'Y',
            'PrintWgtAmt'          => 'N',
            'PrintDepPerc'         => 'N',
            'PrintCSlip'           => 'N',
            'PrintSlip'            => 'N',
            'Footer'               => 'Y',
            'FOOTERE1'             => '',
            'FOOTERE2'             => '',
            'FOOTERE3'             => '',
            // Order
            'DueDateDays'             => '0',
            'DueDateCurDate'          => 'Y',
            'OrderDelDateCompulsary'  => 'Y',
            'OrderNoManual'           => 'N',
            'OrderNoManualAllow'      => 'N',
            'OrderTaxable'            => 'Y',
            'OrderCAToCust'           => 'Y',
            'OrderExToCust'           => 'Y',
            'OrderSRToCust'           => 'Y',
            'OrdPrintForm'            => '',
            'DmdPrintForm'            => 'PNT',
            'GoToDmdDetails'          => 'Y',
            'OrderSlipFooter'         => 'Y',
            'ORDERFOOTERE1'           => '',
            'ORDERFOOTERE2'           => '',
            'ORDERFOOTERE3'           => '',
            // System
            'Language'      => '1',
            'Sound'         => 'N',
            'AutoMaximise'  => 'Y',
            'DashboardTheme' => 'default',
            'UseEscToCloseApp'  => 'Y',
            'AlwaysShowSMList'  => 'N',
            'ShowBalInAcHelp'   => 'N',
            'KeepUpdateLogs'    => 'Y',
            'ALLOWSECONDARYDBSYNC' => 'Y',
            'SecondaryPartySeriesSeperate' => 'N',
            'SecondaryTransactionSeriesSeperate' => 'N',
            'SecSalesSeriesPrefix'           => 'S/',
            'SecPurchaseSeriesPrefix'        => 'P/',
            'SecReceiptSeriesPrefix'         => 'R/',
            'SecPaymentSeriesPrefix'         => 'PY/',
            'SecOrderSeriesPrefix'           => 'ORD/',
            'SecSalesReturnSeriesPrefix'     => 'SR/',
            'SecPurchaseReturnSeriesPrefix'  => 'PR/',
            // Smith / Touch entry
            'TouchToSmithEntry'              => 'N',
            'GoToTouchColumnInSmithEntry'    => 'Y',
            'SmithMulti'                     => '-1',
            'SmithMaxItems'                  => '10',
            'SmithIssueWastage'              => 'Y',
            'SmithVAFromPartyMCTable'        => 'N',
            'SmithRISeperateDocNo'           => 'N',
            'JewlRISeperateDocNo'            => 'N',
            'JewSmithAcWgtManualEntry'       => 'N',
            'AdjustTPToAccInSmithJewlTrans'  => 'N',
            'AllowOnewayEntryInSmithJewlEntry' => 'N',
            // Barcode
            'BCCompulsory'        => 'Y',
            'BCForm'              => '2',
            'BCDefQType'          => '22',
            'BCDefVAP'            => '15.00',
            'BCMaxNo'             => 'N',
            'BCAdjustableWgt'     => '0.000',
            'CreateBCodeInSmithEntry' => 'N',
            // Stock
            'Manualstkvalue'          => 'Y',
            'OpStkvalueFromAcMaster'  => 'Y',
            'ClStkvalueFromItemStock' => 'N',
            'SameStock'               => 'Y',
            // Scheme / Kuri
            'KuriCash'               => 'CASH',
            'KuriAutoCode'           => 'N',
            'SchemeCash'             => 'SCASH',
            'SchemeTrans'            => 'Y',
            'SchemeCollnWgtDecRound' => '3',
            'SchemeCollnWgtRoundDown'=> 'N',
            'ChkMaturityDate'        => 'N',
        ],
        'Master'  => [],
        'Printer' => [],
        'Sales'   => [],
    ];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('application-settings.index');
    }

    /** GET /api/application-settings/load */
    public function load()
    {
        $settings = $this->loadIniSettings();
        $shopInfo = $this->loadShopInfo($settings);
        $clastno  = (string) (DB::table('generali')->where('code', 'CLASTNO')->value('cvalue') ?? '0');
        $slastno  = (string) (DB::table('generali')->where('code', 'SLASTNO')->value('cvalue') ?? '0');
        $sbpref   = Schema::hasTable('generals') ? (string) (DB::table('generals')->where('code', 'SBPREF')->value('cvalue') ?? '') : '';
        $sblen    = Schema::hasTable('generals') ? (string) (DB::table('generals')->where('code', 'SBLEN')->value('cvalue') ?? '5') : '5';

        return response()->json([
            'ok'        => true,
            'settings'  => $settings,
            'shop_info' => $shopInfo,
            'clastno'   => $clastno,
            'slastno'   => $slastno,
            'sbpref'    => $sbpref,
            'sblen'     => $sblen,
        ]);
    }

    /** POST /api/application-settings/save */
    public function save(Request $request)
    {
        $payload = $request->input('settings');
        if (!is_array($payload)) {
            $json = (string) $request->input('json', '');
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                return response()->json(['ok' => false, 'msg' => 'Invalid settings payload.'], 422);
            }
            $payload = $decoded;
        }

        try {
            $existing = [];
            $iniPath = $this->iniPath();
            if (File::exists($iniPath)) {
                $existing = $this->normalizeSettings($this->parseIni((string) File::get($iniPath)));
            }
            $normalized = $this->normalizeSettings($payload);
            foreach ($existing as $section => $pairs) {
                if (!is_array($pairs)) continue;
                if (!isset($normalized[$section]) || !is_array($normalized[$section])) {
                    $normalized[$section] = [];
                }
                foreach ($pairs as $k => $v) {
                    if (!isset($payload[$section][$k])) {
                        $normalized[$section][$k] = $v;
                    }
                }
            }
            $hasShopInfo = $request->has('shop_info');
            $shopInfoPayload = $request->input('shop_info', []);
            if (!is_array($shopInfoPayload)) {
                $decodedShopInfo = json_decode((string) $shopInfoPayload, true);
                $shopInfoPayload = is_array($decodedShopInfo) ? $decodedShopInfo : [];
                $hasShopInfo = $hasShopInfo && is_array($decodedShopInfo);
            }
            $shopInfo = $this->normalizeShopInfo($shopInfoPayload);
            if ($hasShopInfo && $shopInfo['gstin'] !== '') {
                if (!isset($normalized['Company']) || !is_array($normalized['Company'])) {
                    $normalized['Company'] = [];
                }
                $normalized['Company']['KGST'] = $shopInfo['gstin'];
            }
            $this->persistIniSettings($normalized);
            if ($hasShopInfo) {
                $this->persistShopInfo($shopInfo);
            }
            if ($request->has('clastno')) $this->persistGeneraliCounter('CLASTNO', (string) $request->input('clastno', ''));
            if ($request->has('slastno')) $this->persistGeneraliCounter('SLASTNO', (string) $request->input('slastno', ''));
            if ($request->has('sbpref'))  $this->persistGeneralsValue('SBPREF', (string) $request->input('sbpref', ''));
            if ($request->has('sblen'))   $this->persistGeneralsValue('SBLEN', (string) $request->input('sblen', ''));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()]);
        }

        $this->logDelpart($request, 'Application Settings Saved', ['utype' => 'E', 'ttype' => 'R']);
        return response()->json(['ok' => true, 'msg' => 'Application settings saved.']);
    }

    /** POST /api/application-settings/upload-logo */
    public function uploadLogo(Request $request)
    {
        if (!$request->hasFile('logo')) {
            return response()->json(['ok' => false, 'msg' => 'No file uploaded']);
        }

        $file = $request->file('logo');

        if (!$file->isValid()) {
            return response()->json(['ok' => false, 'msg' => 'Upload failed']);
        }

        $mime = $file->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'])) {
            return response()->json(['ok' => false, 'msg' => 'Only image files allowed (jpg, png, gif, webp, svg)']);
        }

        $dir = public_path('uploads/logo');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Remove old logo file
        try {
            $old = DB::table('generals')->where('code', 'SHOPLOGO')->value('cvalue');
            if ($old) {
                $oldPath = public_path('uploads/logo/' . $old);
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }
        } catch (\Throwable) {}

        $ext      = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'shop_logo_' . time() . '.' . $ext;
        $file->move($dir, $filename);

        try {
            $exists = DB::table('generals')->where('code', 'SHOPLOGO')->exists();
            if ($exists) {
                DB::table('generals')->where('code', 'SHOPLOGO')->update(['cvalue' => $filename]);
            } else {
                DB::table('generals')->insert(['code' => 'SHOPLOGO', 'cvalue' => $filename]);
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()]);
        }

        return response()->json([
            'ok'       => true,
            'filename' => $filename,
            'logo_url' => $this->logoUrl($filename),
        ]);
    }

    /** POST /api/application-settings/remove-logo */
    public function removeLogo()
    {
        try {
            $old = DB::table('generals')->where('code', 'SHOPLOGO')->value('cvalue');
            if ($old) {
                $path = public_path('uploads/logo/' . $old);
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
            DB::table('generals')->where('code', 'SHOPLOGO')->update(['cvalue' => '']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }



    // ── helpers ──────────────────────────────────────
    private function logoUrl(string $filename): string
    {
        if ($filename === '') return '';
        return url('uploads/logo/' . $filename);
    }

    /** Static helper used by NativeAuthController */
    public static function getLogoUrl(): string
    {
        try {
            $filename = DB::table('generals')->where('code', 'SHOPLOGO')->value('cvalue') ?? '';
            if ($filename === '') return '';
            $path = public_path('uploads/logo/' . $filename);
            return file_exists($path) ? url('uploads/logo/' . $filename) : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function iniPath(): string
    {
        return storage_path('app/software-settings.ini');
    }

    private function loadShopInfo(array $settings): array
    {
        $values = [
            'SHOPNM' => '',
            'SHOPADDR' => '',
            'SHOPPHONE' => '',
            'SHOPLOGO' => '',
        ];
        try {
            $rows = DB::table('generals')->whereIn('code', array_keys($values))->get();
            foreach ($rows as $row) {
                $values[trim($row->code)] = (string) ($row->cvalue ?? '');
            }
        } catch (\Throwable) {
        }

        return [
            'name' => trim($values['SHOPNM']),
            'address' => trim($values['SHOPADDR']),
            'phone' => trim($values['SHOPPHONE']),
            'logo' => trim($values['SHOPLOGO']),
            'logo_url' => $this->logoUrl((string) $values['SHOPLOGO']),
            'gstin' => trim((string) ($settings['Company']['KGST'] ?? '')),
        ];
    }

    private function normalizeShopInfo(mixed $input): array
    {
        if (!is_array($input)) {
            $input = [];
        }

        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'address' => trim((string) ($input['address'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'gstin' => trim((string) ($input['gstin'] ?? '')),
        ];
    }

    private function persistShopInfo(array $shopInfo): void
    {
        $this->ensureGeneralsValueCapacity(255);

        $map = [
            'SHOPNM' => $shopInfo['name'] ?? '',
            'SHOPADDR' => $shopInfo['address'] ?? '',
            'SHOPPHONE' => $shopInfo['phone'] ?? '',
        ];

        foreach ($map as $code => $value) {
            $exists = DB::table('generals')->where('code', $code)->exists();
            if ($exists) {
                DB::table('generals')->where('code', $code)->update(['cvalue' => $value]);
            } else {
                DB::table('generals')->insert(['code' => $code, 'cvalue' => $value]);
            }
        }
    }

    private function ensureGeneralsValueCapacity(int $minimumLength = 255): void
    {
        if (!$this->hasTable('generals') || !Schema::hasColumn('generals', 'cvalue')) {
            return;
        }

        try {
            $database = (string) DB::getDatabaseName();
            if ($database === '') {
                return;
            }

            $column = DB::selectOne(
                "SELECT DATA_TYPE AS data_type, CHARACTER_MAXIMUM_LENGTH AS char_len
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'generals' AND COLUMN_NAME = 'cvalue'
                 LIMIT 1",
                [$database]
            );

            $dataType = strtolower((string) ($column->data_type ?? ''));
            $charLen = (int) ($column->char_len ?? 0);
            if (in_array($dataType, ['text', 'mediumtext', 'longtext'], true) || $charLen >= $minimumLength) {
                return;
            }

            DB::statement("ALTER TABLE `generals` MODIFY `cvalue` VARCHAR($minimumLength) NULL");
        } catch (\Throwable) {
            // Keep save flow resilient; if schema change is not possible the later write will surface the real error.
        }
    }

    private function loadIniSettings(): array
    {
        $path = $this->iniPath();
        if (!File::exists($path)) {
            $this->persistIniSettings(self::DEFAULTS);
            return self::DEFAULTS;
        }

        return $this->normalizeSettings($this->parseIni((string) File::get($path)));
    }

    private function persistIniSettings(array $settings): void
    {
        $path = $this->iniPath();
        $dir = dirname($path);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0775, true);
        }
        File::put($path, $this->buildIni($settings));
    }

    private function normalizeSettings(array $input): array
    {
        $base = self::DEFAULTS;
        foreach ($input as $section => $pairs) {
            if (!is_string($section) || trim($section) === '' || !is_array($pairs)) {
                continue;
            }
            if (!isset($base[$section])) {
                $base[$section] = [];
            }
            foreach ($pairs as $key => $value) {
                if (!is_string($key) || trim($key) === '') {
                    continue;
                }
                $base[$section][trim($key)] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_SLASHES);
            }
        }
        return $base;
    }

    private function parseIni(string $raw): array
    {
        $result = [];
        $section = '';
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#') || str_starts_with($line, '//')) {
                continue;
            }
            if (preg_match('/^\[(.+)\]$/', $line, $match) === 1) {
                $section = trim((string) $match[1]);
                if ($section !== '' && !isset($result[$section])) {
                    $result[$section] = [];
                }
                continue;
            }
            $position = strpos($line, '=');
            if ($position === false) {
                continue;
            }
            $key = trim(substr($line, 0, $position));
            $value = trim(substr($line, $position + 1));
            if ($key === '') {
                continue;
            }
            if ($section === '') {
                $section = 'Software';
                if (!isset($result[$section])) {
                    $result[$section] = [];
                }
            }
            $result[$section][$key] = $value;
        }
        return $result;
    }

    private function persistGeneraliCounter(string $code, string $value): void
    {
        if ($value === '') return;
        $intVal = (int) $value;
        $exists = DB::table('generali')->where('code', $code)->exists();
        if ($exists) {
            DB::table('generali')->where('code', $code)->update(['cvalue' => $intVal]);
        } else {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => $intVal]);
        }
    }

    private function persistGeneralsValue(string $code, string $value): void
    {
        if (!Schema::hasTable('generals')) return;
        $exists = DB::table('generals')->where('code', $code)->exists();
        if ($exists) {
            DB::table('generals')->where('code', $code)->update(['cvalue' => $value]);
        } else {
            DB::table('generals')->insert(['code' => $code, 'cvalue' => $value]);
        }
    }

    private function buildIni(array $settings): string
    {
        $buffer = '';
        foreach ($settings as $section => $pairs) {
            if (!is_array($pairs) || trim((string) $section) === '') {
                continue;
            }
            $buffer .= '[' . trim((string) $section) . "]\n";
            foreach ($pairs as $key => $value) {
                if (!is_string($key) || trim($key) === '') {
                    continue;
                }
                $buffer .= trim($key) . '=' . (string) ($value ?? '') . "\n";
            }
            $buffer .= "\n";
        }
        return $buffer;
    }
}
