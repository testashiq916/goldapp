<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use ZipArchive;

class LegacyEInvoiceJsonService
{
    public function generateBulk(array $slnos): array
    {
        $slnos = collect($slnos)
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values()
            ->all();

        if ($slnos === []) {
            throw new RuntimeException('No sales bills selected for e-invoice generation.');
        }

        $dir = $this->outputDir();
        $generated = [];

        foreach ($slnos as $slno) {
            $generated[] = $this->generateForSlno($slno);
        }

        $zipPath = $this->zipGeneratedFiles($generated);

        return [
            'count' => count($generated),
            'files' => array_map(fn ($path) => basename($path), $generated),
            'zip_path' => $zipPath,
            'zip_name' => basename($zipPath),
            'download_name' => count($generated) === 1 ? basename($generated[0]) : basename($zipPath),
            'directory' => $dir,
        ];
    }

    public function absoluteOutputPath(string $relativeFile): string
    {
        $relativeFile = str_replace(['..\\', '../'], '', str_replace('\\', '/', trim($relativeFile)));
        return $this->outputDir() . DIRECTORY_SEPARATOR . $relativeFile;
    }

    public function generateForSlno(int $slno): string
    {
        if ($slno <= 0) {
            throw new RuntimeException('Invalid SLNO for e-invoice JSON.');
        }

        $sale = DB::table('salesm')->where('slno', $slno)->first();
        if (!$sale) {
            throw new RuntimeException('Sales bill not found for SLNO ' . $slno);
        }

        $payload = $this->buildPayload($sale);
        $docNo = $this->safeFileDocNo((string) ($sale->billno ?? ('sale-' . $slno)));
        $filePath = $this->outputDir() . DIRECTORY_SEPARATOR . $docNo . '.json';

        File::put(
            $filePath,
            $this->encodeInvoiceJson([$payload])
        );

        return $filePath;
    }

    private function buildPayload(object $sale): array
    {
        $settings = $this->settings();
        $company = $settings['Company'] ?? [];
        $software = $settings['Software'] ?? [];

        $buyer = $this->resolveBuyer($sale, (string) ($software['DefState'] ?? 'Kerala'));
        $sellerGstin = trim((string) ($company['KGST'] ?? $company['TIN'] ?? ''));
        $sellerStateCode = $this->normalizeStateCode(
            trim((string) ($company['StCode'] ?? $company['DefStateCode'] ?? '')),
            $sellerGstin
        );
        $sellerPin = $this->nullableInt($company['PIN'] ?? $company['Pin'] ?? null)
            ?? $this->defaultPinForState($sellerStateCode);
        $sellerAddr1 = $this->normalizeInvoiceAddressLine((string) ($company['Addr1'] ?? $company['Addr'] ?? ''), (string) ($company['Addr2'] ?? ''), (string) ($company['Loc'] ?? $company['Branch'] ?? ''));
        $sellerAddr2 = $this->normalizeInvoiceAddressLine((string) ($company['Addr2'] ?? ''), $sellerAddr1, (string) ($company['Loc'] ?? $company['Branch'] ?? ''));
        $sellerLoc = $this->normalizeInvoiceLocation((string) ($company['Loc'] ?? $company['Branch'] ?? ''), $sellerAddr2, $sellerAddr1);
        $items = $this->resolveItems((int) $sale->slno, $sale);
        $ewbDetails = $this->buildEwbDetails($sale);

        $docDate = $this->formatLegacyDate((string) ($sale->tdate ?? ''));
        $round = $this->num($sale->round ?? 0);
        $igst = $this->sumItemNumber($items, 'IgstAmt');
        $sgst = $this->sumItemNumber($items, 'SgstAmt');
        $cgst = $this->sumItemNumber($items, 'CgstAmt');
        $discount = $this->num($sale->discount ?? 0);
        $billAmt = $this->num($sale->billamt ?? 0);
        $netAmt = $this->num($sale->netamt ?? 0);
        $tcsAmt = $this->num($sale->tcsamt ?? 0);
        $hmc = $this->num($sale->hmc ?? 0);
        $others = $tcsAmt + $hmc;

        $payload = [
            'Version' => '1.1',
            'TranDtls' => [
                'TaxSch' => 'GST',
                'SupTyp' => 'B2B',
                'IgstOnIntra' => 'N',
                'RegRev' => 'N',
                'EcmGstin' => null,
            ],
            'DocDtls' => [
                'Typ' => 'INV',
                'No' => $this->invoiceDocNo((string) ($sale->billno ?? '')),
                'Dt' => $docDate,
            ],
            'SellerDtls' => [
                'Gstin' => $sellerGstin,
                'LglNm' => trim((string) ($company['Name'] ?? '')),
                'Addr1' => $sellerAddr1,
                'Addr2' => $sellerAddr2,
                'Loc' => $sellerLoc,
                'Pin' => $sellerPin,
                'Stcd' => $sellerStateCode,
                'Ph' => $this->validPhoneOrNull((string) ($company['Phone'] ?? '')),
                'Em' => $this->validEmailOrNull((string) ($company['Email'] ?? $company['HOMailID'] ?? '')),
            ],
            'BuyerDtls' => [
                'Gstin' => $buyer['gstin'],
                'LglNm' => $buyer['name'],
                'Addr1' => $buyer['addr1'],
                'Addr2' => $buyer['addr2'],
                'Loc' => $buyer['loc'],
                'Pin' => $buyer['pin'],
                'Pos' => $buyer['state_code'],
                'Stcd' => $buyer['state_code'],
                'Ph' => $buyer['phone'],
                'Em' => $this->validEmailOrNull($buyer['email']),
            ],
            'ValDtls' => [
                'AssVal' => $this->rawNumber($billAmt, 2),
                'IgstVal' => $this->rawNumber($igst, 2),
                'CgstVal' => $this->rawNumber($cgst, 2),
                'SgstVal' => $this->rawNumber($sgst, 2),
                'CesVal' => 0,
                'StCesVal' => 0,
                'Discount' => $this->rawNumber($discount, 2),
                'OthChrg' => $this->rawNumber($others, 2),
                'RndOffAmt' => $this->rawNumber($round, 2),
                'TotInvVal' => $this->rawNumber($netAmt, 2),
            ],
            'RefDtls' => [
                'InvRm' => null,
            ],
            'ItemList' => $items,
        ];

        if ($ewbDetails !== null) {
            $payload['EwbDtls'] = $ewbDetails;
        }

        return $payload;
    }

    private function resolveBuyer(object $sale, string $defaultStateName): array
    {
        $buyer = [
            'name' => trim((string) ($sale->custname ?? '')),
            'addr1' => trim((string) ($sale->addr ?? '')),
            'addr2' => '',
            'loc' => '',
            'pin' => null,
            'phone' => trim((string) ($sale->mobno ?? '')),
            'email' => '',
            'gstin' => trim((string) ($sale->tin ?? '')),
            'state_code' => trim((string) ($sale->statecode ?? '')),
            'state_name' => '',
        ];

        $custCode = trim((string) ($sale->custcode ?? ''));
        if ($custCode !== '' && Schema::hasTable('clients')) {
            $client = DB::table('clients')->where('code', $custCode)->first();
            if ($client) {
                $buyer['name'] = trim((string) ($client->name ?? $buyer['name']));
                $buyer['addr1'] = trim((string) ($client->addr1 ?? $buyer['addr1']));
                $buyer['addr2'] = trim((string) ($client->addr2 ?? ''));
                $buyer['loc'] = trim((string) ($client->city ?? ''));
                $buyer['pin'] = $this->nullableInt($client->pin ?? null);
                $buyer['phone'] = trim((string) ($client->telephone ?? $buyer['phone']));
                if ($buyer['phone'] === '') {
                    $buyer['phone'] = trim((string) ($client->mobile ?? ''));
                }
                $buyer['email'] = trim((string) ($client->email ?? ''));
                $buyer['gstin'] = trim((string) ($client->tin ?? $buyer['gstin']));
                $buyer['state_code'] = trim((string) ($client->state ?? $buyer['state_code']));
            }
        }

        $buyer['state_code'] = $this->normalizeStateCode($buyer['state_code'], $buyer['gstin']);
        if ($buyer['pin'] === null) {
            $buyer['pin'] = $this->defaultPinForState($buyer['state_code']);
        }
        $buyer['phone'] = $this->validPhoneOrNull($buyer['phone']);

        if ($buyer['state_code'] !== '' && Schema::hasTable('state')) {
            $buyer['state_name'] = trim((string) (DB::table('state')->where('code', $buyer['state_code'])->value('name') ?? ''));
        }
        if ($buyer['state_name'] === '') {
            $buyer['state_name'] = $defaultStateName;
        }

        $buyer['addr1'] = $this->normalizeInvoiceAddressLine($buyer['addr1'], $buyer['addr2'], $buyer['loc']);
        $buyer['addr2'] = $this->normalizeInvoiceAddressLine($buyer['addr2'], $buyer['loc'], $buyer['addr1']);
        $buyer['loc'] = $this->normalizeInvoiceLocation($buyer['loc'], $buyer['addr2'], $buyer['addr1']);

        return $buyer;
    }

    private function normalizeInvoiceAddressLine(string $preferred, string ...$fallbacks): string
    {
        $candidates = array_merge([$preferred], $fallbacks);
        foreach ($candidates as $candidate) {
            $value = trim(preg_replace('/\s+/', ' ', (string) $candidate));
            if (mb_strlen($value) >= 3) {
                return mb_substr($value, 0, 100);
            }
        }

        return 'NA';
    }

    private function normalizeInvoiceLocation(string $preferred, string ...$fallbacks): string
    {
        $value = $this->normalizeInvoiceAddressLine($preferred, ...$fallbacks);
        return mb_substr($value, 0, 50);
    }

    private function resolveItems(int $slno, object $sale): array
    {
        $taxPerc = $this->num($sale->staxperc ?? 0);
        $igst = $this->num($sale->igst ?? 0);
        $unit = 'GMS';
        $itemColumns = $this->tableColumns('items');
        $hsnColumn = $this->firstAvailableColumn($itemColumns, ['hsncode', 'hsn', 'hsncd', 'gstcode', 'vatcode']);
        $selects = [
            'sd.code',
            'sd.name as sales_name',
            'im.name as item_name',
            'sd.weight',
            'sd.rate',
            'sd.mcharge',
            'sd.stoneprice',
            'sd.amount',
        ];

        if ($hsnColumn !== null) {
            $selects[] = "im.{$hsnColumn} as hsn_code";
        }

        return DB::table('salesd as sd')
            ->leftJoin('items as im', 'sd.code', '=', 'im.code')
            ->where('sd.slno', $slno)
            ->orderBy('sd.sno')
            ->get($selects)
            ->values()
            ->map(function ($row, int $index) use ($taxPerc, $igst, $unit) {
                $amount = $this->num($row->amount ?? 0);
                $qty = $this->num($row->weight ?? 0);
                $unitPrice = $qty > 0
                    ? $amount / $qty
                    : $this->num($row->rate ?? 0);
                $taxAmt = ($amount * $taxPerc) / 100;
                $itemName = trim((string) ($row->item_name ?? $row->sales_name ?? ''));
                $hsnCode = preg_replace('/\D+/', '', trim((string) ($row->hsn_code ?? ''))) ?? '';

                return [
                    'SlNo' => (string) ($index + 1),
                    'PrdDesc' => $itemName,
                    'IsServc' => 'N',
                    'HsnCd' => $hsnCode,
                    'Qty' => $this->rawNumber($qty, 3),
                    'FreeQty' => 0,
                    'Unit' => $unit,
                    'UnitPrice' => $this->rawNumber($unitPrice, 2),
                    'TotAmt' => $this->rawNumber($amount, 2),
                    'Discount' => 0,
                    'PreTaxVal' => 0,
                    'AssAmt' => $this->rawNumber($amount, 2),
                    'GstRt' => $this->rawNumber($taxPerc, 2),
                    'IgstAmt' => $igst > 0 ? $this->rawNumber($taxAmt, 2) : 0,
                    'CgstAmt' => $igst > 0 ? 0 : $this->rawNumber($taxAmt / 2, 2),
                    'SgstAmt' => $igst > 0 ? 0 : $this->rawNumber($taxAmt / 2, 2),
                    'CesRt' => 0,
                    'CesAmt' => 0,
                    'CesNonAdvlAmt' => 0,
                    'StateCesRt' => 0,
                    'StateCesAmt' => 0,
                    'StateCesNonAdvlAmt' => 0,
                    'OthChrg' => $this->rawNumber(0, 2),
                    'TotItemVal' => $this->rawNumber($amount + $taxAmt, 2),
                ];
            })
            ->all();
    }

    private function buildEwbDetails(object $sale): ?array
    {
        $vehNo = strtoupper(trim((string) ($sale->vehno ?? '')));
        $distance = (int) $this->num($sale->distance ?? 0);
        $transDocNo = trim((string) ($sale->transdocno ?? ''));
        $transId = trim((string) ($sale->transid ?? ''));
        $transName = trim((string) ($sale->transname ?? ''));

        if (strlen($vehNo) < 4 && $distance <= 0 && $transDocNo === '' && $transId === '' && $transName === '') {
            return null;
        }

        return [
            'TransId' => $transId !== '' ? $transId : null,
            'TransName' => $transName !== '' ? $transName : null,
            'TransMode' => '1',
            'Distance' => $distance,
            'TransDocDt' => null,
            'TransDocNo' => $transDocNo !== '' ? $transDocNo : null,
            'VehNo' => strlen($vehNo) >= 4 ? $vehNo : null,
            'VehType' => 'R',
        ];
    }

    private function outputDir(): string
    {
        $dir = storage_path('app/einvoice-jsons');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return $dir;
    }

    private function zipGeneratedFiles(array $files): string
    {
        $stamp = now()->format('Ymd_His');
        $zipPath = $this->outputDir() . DIRECTORY_SEPARATOR . 'einvoice_bulk_' . $stamp . '.zip';

        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create bulk e-invoice ZIP.');
            }
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
            return $zipPath;
        }

        $tmpDir = $this->outputDir() . DIRECTORY_SEPARATOR . '_zip_' . $stamp;
        File::ensureDirectoryExists($tmpDir);
        try {
            foreach ($files as $file) {
                File::copy($file, $tmpDir . DIRECTORY_SEPARATOR . basename($file));
            }
            $src = str_replace("'", "''", $tmpDir);
            $dst = str_replace("'", "''", $zipPath);
            $command = "powershell -NoProfile -Command \"Compress-Archive -Path '$src\\*' -DestinationPath '$dst' -Force\"";
            exec($command, $output, $status);
            if ($status !== 0 || !File::exists($zipPath)) {
                throw new RuntimeException('Unable to create bulk e-invoice ZIP.');
            }
        } finally {
            File::deleteDirectory($tmpDir);
        }

        return $zipPath;
    }

    private function settings(): array
    {
        $iniPath = storage_path('app/software-settings.ini');
        if (!File::exists($iniPath)) {
            return [];
        }

        $parsed = parse_ini_file($iniPath, true, INI_SCANNER_RAW);
        return is_array($parsed) ? $parsed : [];
    }

    private function invoiceDocNo(string $value): string
    {
        $value = trim(preg_replace('/\s+/', '', $value) ?? '');
        if ($value === '') {
            return 'invoice';
        }

        $value = preg_replace('/[^A-Za-z0-9\/-]+/', '', $value) ?? $value;
        return preg_replace('/0+(\d+)$/', '$1', $value) ?? $value;
    }

    private function safeFileDocNo(string $value): string
    {
        $value = trim(str_replace(['\\', '/'], '', $value));
        return $value !== '' ? $value : 'invoice';
    }

    private function formatLegacyDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return now()->format('d/m/Y');
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return now()->format('d/m/Y');
        }
    }

    private function num(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $raw = str_replace(',', '', trim((string) $value));
        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    private function decimal(mixed $value, int $precision): float
    {
        return (float) number_format($this->num($value), $precision, '.', '');
    }

    private function tableColumns(string $table): array
    {
        return Schema::hasTable($table)
            ? array_map('strtolower', Schema::getColumnListing($table))
            : [];
    }

    private function firstAvailableColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array(strtolower($candidate), $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeStateCode(string $stateCode, string $gstin = ''): string
    {
        $stateCode = trim($stateCode);
        if (preg_match('/^\d{1,2}$/', $stateCode)) {
            return str_pad($stateCode, 2, '0', STR_PAD_LEFT);
        }

        $gstin = strtoupper(trim($gstin));
        if (preg_match('/^\d{2}[A-Z0-9]{13}$/', $gstin)) {
            return substr($gstin, 0, 2);
        }

        return $stateCode;
    }

    private function rawNumber(mixed $value, int $precision): string
    {
        return '__RAWNUM__' . number_format($this->num($value), $precision, '.', '');
    }

    private function sumItemNumber(array $items, string $key): float
    {
        return array_reduce($items, function (float $sum, array $item) use ($key) {
            return $sum + $this->num(str_replace('__RAWNUM__', '', (string) ($item[$key] ?? 0)));
        }, 0.0);
    }

    private function encodeInvoiceJson(array $payload): string
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Unable to encode e-invoice JSON.');
        }

        return preg_replace('/"__RAWNUM__(-?\d+(?:\.\d+)?)"/', '$1', $json) ?? $json;
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = trim((string) $value);
        return ctype_digit($value) ? (int) $value : null;
    }

    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function validPhoneOrNull(string $value): ?string
    {
        $digits = $this->digitsOnly($value);
        $length = strlen($digits);

        return $length >= 6 && $length <= 12 ? $digits : null;
    }

    private function validEmailOrNull(string $value): ?string
    {
        $email = trim($value);
        $length = strlen($email);

        return $length >= 6 && $length <= 100 ? $email : null;
    }

    private function defaultPinForState(string $stateCode): ?int
    {
        return match (str_pad(trim($stateCode), 2, '0', STR_PAD_LEFT)) {
            '32' => 673001,
            default => null,
        };
    }
}
