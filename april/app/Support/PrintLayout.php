<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class PrintLayout
{
    public static function viewData(): array
    {
        $settings = self::loadSettings();
        $software = (array) ($settings['Software'] ?? []);

        $topMargin = self::normalizeMargin($software['TopMargin'] ?? 230, 230);
        $leftMargin = self::normalizeMargin($software['LeftMargin'] ?? 100, 100);
        $bottomMargin = self::normalizeMargin($software['BottomMargin'] ?? 10, 10);
        $letterheadMode = self::normalizeLetterheadMode($software['PrintLetterheadMode'] ?? 'APP_HEADER');
        $pageAlign = self::normalizePageAlign($software['PrintPageAlign'] ?? 'LEFT');

        return [
            'letterheadMode' => $letterheadMode,
            'topMargin' => $topMargin,
            'leftMargin' => $leftMargin,
            'bottomMargin' => $bottomMargin,
            'pageAlign' => $pageAlign,
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
        return in_array($mode, ['PREPRINTED', 'APP_HEADER'], true) ? $mode : 'APP_HEADER';
    }

    public static function normalizePageAlign(mixed $value): string
    {
        $align = strtoupper(trim((string) $value));
        return in_array($align, ['LEFT', 'CENTER', 'RIGHT'], true) ? $align : 'LEFT';
    }
}
