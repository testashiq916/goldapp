<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class PrintLayout
{
    public static function viewData(): array
    {
        $settings = self::loadSettings();
        $software = (array) ($settings['Software'] ?? []);

        $printContext = self::detectContext();
        $printerTopMargin = self::normalizeMargin($software['PrinterTopMargin'] ?? $software['TopMargin'] ?? 230, 230);
        $printerLeftMargin = self::normalizeMargin($software['PrinterLeftMargin'] ?? $software['LeftMargin'] ?? 100, 100);
        $printerPageAlign = self::normalizePageAlign($software['PrinterPageAlign'] ?? $software['PrintPageAlign'] ?? 'LEFT');
        $reportTopMargin = self::normalizeMargin($software['ReportTopMargin'] ?? 0, 0);
        $reportLeftMargin = self::normalizeMargin($software['ReportLeftMargin'] ?? 0, 0);
        $reportPageAlign = self::normalizePageAlign($software['ReportPageAlign'] ?? 'LEFT');
        $topMargin = $printContext === 'invoice' ? $printerTopMargin : $reportTopMargin;
        $leftMargin = $printContext === 'invoice' ? $printerLeftMargin : $reportLeftMargin;
        $bottomMargin = self::normalizeMargin($software['BottomMargin'] ?? 10, 10);
        $letterheadMode = self::normalizeLetterheadMode($software['PrintLetterheadMode'] ?? 'PREPRINTED');
        $pageAlign = $printContext === 'invoice' ? $printerPageAlign : $reportPageAlign;
        $appHeaderTopMargin = self::normalizeMargin($software['AppHeaderTopMargin'] ?? 10, 10);
        $appHeaderLeftMargin = self::normalizeMargin($software['AppHeaderLeftMargin'] ?? 4, 4);
        $appHeaderPageAlign = self::normalizePageAlign($software['AppHeaderPageAlign'] ?? 'CENTER');

        return [
            'printContext' => $printContext,
            'letterheadMode' => $letterheadMode,
            'topMargin' => $topMargin,
            'leftMargin' => $leftMargin,
            'bottomMargin' => $bottomMargin,
            'pageAlign' => $pageAlign,
            'printerTopMargin' => $printerTopMargin,
            'printerLeftMargin' => $printerLeftMargin,
            'printerPageAlign' => $printerPageAlign,
            'reportTopMargin' => $reportTopMargin,
            'reportLeftMargin' => $reportLeftMargin,
            'reportPageAlign' => $reportPageAlign,
            'appHeaderTopMargin' => $appHeaderTopMargin,
            'appHeaderLeftMargin' => $appHeaderLeftMargin,
            'appHeaderPageAlign' => $appHeaderPageAlign,
            'showAppHeader' => $letterheadMode === 'APP_HEADER',
        ];
    }

    public static function loadSettings(): array
    {
        $iniPath = storage_path('app/software-settings.ini');
        if (!File::exists($iniPath)) {
            return ['Software' => []];
        }

        $parsed = parse_ini_string((string) File::get($iniPath), true, INI_SCANNER_RAW);
        return is_array($parsed) ? $parsed : ['Software' => []];
    }

    public static function normalizeMargin(mixed $value, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $margin = (int) round((float) $value);
        return max(0, min($margin, 2000));
    }

    public static function normalizeLetterheadMode(mixed $value): string
    {
        $mode = strtoupper(trim((string) $value));
        return in_array($mode, ['PREPRINTED', 'APP_HEADER'], true) ? $mode : 'PREPRINTED';
    }

    public static function normalizePageAlign(mixed $value): string
    {
        $align = strtoupper(trim((string) $value));
        return in_array($align, ['LEFT', 'CENTER', 'RIGHT'], true) ? $align : 'LEFT';
    }

    private static function detectContext(): string
    {
        try {
            $path = strtolower(trim((string) request()->path(), '/'));
        } catch (\Throwable) {
            return 'report';
        }

        $invoicePaths = [
            'sales-bill-print',
            'sales-return-print',
            'purchase-bill-print',
            'order-bill/print',
            'goldsmith-transactions-print',
        ];

        foreach ($invoicePaths as $invoicePath) {
            if ($path === $invoicePath || str_starts_with($path, $invoicePath . '/')) {
                return 'invoice';
            }
        }

        return 'report';
    }
}
