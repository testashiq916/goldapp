<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\File;

trait HandlesSecondarySeriesPrefix
{
    private function isSecondaryDb(): bool
    {
        $current = trim((string) config('database.connections.mysql.database', ''));
        $primary = trim((string) env('DB_DATABASE', ''));
        if ($current === '' || $primary === '') {
            return false;
        }
        if (strcasecmp($current, $primary) !== 0) {
            return true;
        }
        $selected = trim((string) session('selected_database', ''));
        return $selected !== '' && strcasecmp($selected, $primary) !== 0;
    }

    private function secondarySeriesEnabled(): bool
    {
        return strtoupper($this->iniSoftwareValue('SecondaryTransactionSeriesSeperate', 'N')) === 'Y';
    }

    private function secondaryPrefixFor(string $module): string
    {
        $defaults = [
            'sales'    => 'S/',
            'purchase' => 'P/',
            'receipt'  => 'R/',
            'payment'  => 'PY/',
            'order'    => 'ORD/',
            'salesreturn'    => 'SR/',
            'purchasereturn' => 'PR/',
        ];
        $keys = [
            'sales'    => 'SecSalesSeriesPrefix',
            'purchase' => 'SecPurchaseSeriesPrefix',
            'receipt'  => 'SecReceiptSeriesPrefix',
            'payment'  => 'SecPaymentSeriesPrefix',
            'order'    => 'SecOrderSeriesPrefix',
            'salesreturn'    => 'SecSalesReturnSeriesPrefix',
            'purchasereturn' => 'SecPurchaseReturnSeriesPrefix',
        ];
        $m = strtolower(trim($module));
        $key = $keys[$m] ?? null;
        if ($key === null) return '';

        $val = trim($this->iniSoftwareValue($key, ''));
        return $val !== '' ? $val : ($defaults[$m] ?? '');
    }

    private function shouldUseSecondaryPrefix(string $module): bool
    {
        return $this->isSecondaryDb()
            && $this->secondarySeriesEnabled()
            && $this->secondaryPrefixFor($module) !== '';
    }

    private function iniSoftwareValue(string $key, string $default = ''): string
    {
        $path = storage_path('app/software-settings.ini');
        if (!File::exists($path)) return $default;
        $parsed = parse_ini_string((string) File::get($path), true, INI_SCANNER_RAW);
        if (!is_array($parsed)) return $default;
        return (string) ($parsed['Software'][$key] ?? $default);
    }
}
