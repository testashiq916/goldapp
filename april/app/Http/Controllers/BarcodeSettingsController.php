<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class BarcodeSettingsController extends Controller
{
    private const DEFAULTS = [
        'Software' => [
            'BCDefQty' => '1',
            'BCDefRate' => '',
            'BCDefCost' => '',
            'BCDefVAP' => '',
            'BCDefMCA' => '',
            'BCDefMcPerGm' => '',
            'BCDefStWgt' => '',
            'BCDefStPrice' => '',
            'BCTransTouch' => '0',
            'BCStickerWgt' => '0',
            'BCFreshMC' => 'Y',
            'BCEncrypt' => 'N',
            'MCEncrypt' => 'N',
            'BPrintType' => 'TemplateDesigner',
            'BCDensity' => '14',
            'StWgtInfo' => 'Aprox',
            'BCForm' => '1',
            'ShowBCListInEdit' => 'Y',
            'PrintThisBCode' => 'N',
            'GoToDmdDetails' => 'N',
            'BCSubGrpMust' => 'N',
            'BCBalWgtCheck' => 'Y',
            'RoundOffAllAmt' => 'N',
            'BCMaxNo' => 'Y',
            'BCodePrintAfter2Entry' => 'Y',
            'BCPrintSmith' => 'Y',
            'BPrintQty' => 'N',
            'BCDefCostMcPerGm' => '',
            'BCDefMinVAP' => '',
            'BCDefQType' => '',
            'BCPYINC' => '0',
            'BCPXINC' => '0',
            'BCPPrnPath' => '',
            'BCPrintBat' => '',
            'BCPrintMode' => 'windows_image',
            'BCPrinterName' => '',
            'BCPrinterShareName' => '',
            'BCStickerMode' => 'single',
            'BCDesignerColumns' => '1',
            'BCDesignerTextRows' => '1',
            'BCDesignerTemplate' => '',
            'BCRenderAsImage' => 'Y',
            'BCElementWidth' => '130',
            'BCElementHeight' => '42',
            'BCLastParm' => '',
            'MaMCInAutoRearange' => '0',
        ],
        'Application' => [
            'BCPhotoPath' => '',
            'AppPath' => '.',
        ],
    ];

    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $settings = $this->loadSettings();
        return view('barcode-single-entry.settings', [
            'settings' => $settings,
            'settingsJson' => json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function printers(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $printers = [];
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = 'powershell -NoProfile -Command "Get-Printer | Select-Object Name,ShareName,PortName,Default | ConvertTo-Json -Compress" 2>$null';
                $out = @shell_exec($cmd);
                if (is_string($out) && trim($out) !== '') {
                    $decoded = json_decode(trim($out), true);
                    if (is_array($decoded)) {
                        // Convert single object payload to array
                        if (array_key_exists('Name', $decoded)) {
                            $decoded = [$decoded];
                        }
                        foreach ($decoded as $row) {
                            if (!is_array($row)) {
                                continue;
                            }
                            $name = trim((string) ($row['Name'] ?? ''));
                            $share = trim((string) ($row['ShareName'] ?? ''));
                            $port = trim((string) ($row['PortName'] ?? ''));
                            $isDefault = (bool) ($row['Default'] ?? false);
                            if ($name === '') {
                                continue;
                            }
                            $printers[] = [
                                'name' => $name,
                                'shareName' => $share,
                                'portName' => $port,
                                'isDefault' => $isDefault,
                            ];
                        }
                    } else {
                        // fallback plain name list
                        $lines = preg_split('/\r\n|\r|\n/', trim($out)) ?: [];
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if ($line !== '') {
                                $printers[] = ['name' => $line, 'shareName' => '', 'portName' => '', 'isDefault' => false];
                            }
                        }
                    }
                }

                // Fallback for older environments where Get-Printer is unavailable.
                if (count($printers) === 0) {
                    $cmd2 = 'powershell -NoProfile -Command "Get-CimInstance Win32_Printer | Select-Object Name,ShareName,PortName,Default | ConvertTo-Json -Compress" 2>$null';
                    $out2 = @shell_exec($cmd2);
                    if (is_string($out2) && trim($out2) !== '') {
                        $decoded2 = json_decode(trim($out2), true);
                        if (is_array($decoded2)) {
                            if (array_key_exists('Name', $decoded2)) {
                                $decoded2 = [$decoded2];
                            }
                            foreach ($decoded2 as $row) {
                                if (!is_array($row)) {
                                    continue;
                                }
                                $name = trim((string) ($row['Name'] ?? ''));
                                if ($name === '') {
                                    continue;
                                }
                                $printers[] = [
                                    'name' => $name,
                                    'shareName' => trim((string) ($row['ShareName'] ?? '')),
                                    'portName' => trim((string) ($row['PortName'] ?? '')),
                                    'isDefault' => (bool) ($row['Default'] ?? false),
                                ];
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // keep empty list
        }
        return response()->json(['success' => true, 'data' => array_values($printers)]);
    }

    public function get(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $this->loadSettings(),
        ]);
    }

    public function images(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $dir = $this->imageDirectory();
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0775, true);
        }

        $files = [];
        try {
            foreach (File::files($dir) as $file) {
                $ext = strtolower((string) $file->getExtension());
                if (!in_array($ext, ['png', 'jpg', 'jpeg', 'bmp', 'gif', 'webp'], true)) {
                    continue;
                }
                $name = (string) $file->getFilename();
                $rel = 'uploads/barcode-sticker/' . $name;
                $files[] = [
                    'name' => $name,
                    'path' => $rel,
                    'url' => url($rel),
                ];
            }
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to read image library.'], 500);
        }

        usort($files, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        return response()->json(['success' => true, 'data' => $files]);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $file = $request->file('image');
        if (!$file || !$file->isValid()) {
            return response()->json(['success' => false, 'message' => 'Image file is required.'], 422);
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'bmp', 'gif', 'webp'], true)) {
            return response()->json(['success' => false, 'message' => 'Only image files are allowed.'], 422);
        }
        if ((int) $file->getSize() > (5 * 1024 * 1024)) {
            return response()->json(['success' => false, 'message' => 'Image size must be <= 5MB.'], 422);
        }

        $dir = $this->imageDirectory();
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0775, true);
        }

        $baseName = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $baseName);
        $safe = trim((string) $safe, '-');
        if ($safe === '') {
            $safe = 'sticker-image';
        }
        $name = $safe . '-' . date('YmdHis') . '.' . $ext;
        $file->move($dir, $name);

        $rel = 'uploads/barcode-sticker/' . $name;
        return response()->json([
            'success' => true,
            'message' => 'Image uploaded.',
            'data' => [
                'name' => $name,
                'path' => $rel,
                'url' => url($rel),
            ],
        ]);
    }

    public function setDefaultPrinter(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $printerName = trim((string) $request->input('printerName', ''));
        $shareName = trim((string) $request->input('shareName', ''));
        if ($printerName === '') {
            return response()->json(['success' => false, 'message' => 'Printer name is required.'], 422);
        }

        $settings = $this->loadSettings();
        if (!isset($settings['Software']) || !is_array($settings['Software'])) {
            $settings['Software'] = [];
        }
        $settings['Software']['BCPrinterName'] = $printerName;
        $settings['Software']['BCPrinterShareName'] = $shareName;
        $settings['Software']['BCPrintMode'] = (string) ($settings['Software']['BCPrintMode'] ?? 'windows_image');
        $this->persistSettings($this->normalizeSettings($settings));

        return response()->json([
            'success' => true,
            'message' => 'Default printer saved in application settings.',
            'data' => [
                'printerName' => $printerName,
                'shareName' => $shareName,
            ],
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->input('settings');
        if (!is_array($payload)) {
            $json = (string) $request->input('json', '');
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                return response()->json(['success' => false, 'message' => 'Invalid JSON payload.'], 422);
            }
            $payload = $decoded;
        }

        $normalized = $this->normalizeSettings($payload);
        $this->persistSettings($normalized);

        return response()->json([
            'success' => true,
            'message' => 'Settings saved.',
            'data' => $normalized,
        ]);
    }

    private function storagePath(): string
    {
        return storage_path('app/software-settings.ini');
    }

    private function legacyJsonPath(): string
    {
        return storage_path('app/barcode-settings.json');
    }

    private function imageDirectory(): string
    {
        return public_path('uploads/barcode-sticker');
    }

    private function loadSettings(): array
    {
        $path = $this->storagePath();
        if (File::exists($path)) {
            $parsed = $this->parseIni((string) File::get($path));
            return $this->normalizeSettings($parsed);
        }

        // One-time import from legacy JSON if present.
        $legacy = $this->legacyJsonPath();
        if (File::exists($legacy)) {
            $raw = (string) File::get($legacy);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $normalized = $this->normalizeSettings($decoded);
                $this->persistSettings($normalized);
                return $normalized;
            }
        }

        $this->persistSettings(self::DEFAULTS);
        return self::DEFAULTS;
    }

    private function persistSettings(array $settings): void
    {
        $path = $this->storagePath();
        $dir = dirname($path);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0775, true);
        }
        $content = $this->buildIni($settings);
        File::put($path, $content);
    }

    private function parseIni(string $raw): array
    {
        $out = [];
        $sec = '';
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === ';' || $line[0] === '#' || str_starts_with($line, '//')) {
                continue;
            }
            if (preg_match('/^\[(.+)\]$/', $line, $m)) {
                $sec = trim((string) $m[1]);
                if ($sec !== '' && !isset($out[$sec])) {
                    $out[$sec] = [];
                }
                continue;
            }
            if ($sec === '' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim((string) $k);
            if ($k === '') {
                continue;
            }
            $out[$sec][$k] = trim((string) $v);
        }
        return $out;
    }

    private function buildIni(array $settings): string
    {
        $buf = '';
        foreach ($settings as $section => $pairs) {
            if (!is_array($pairs) || trim((string) $section) === '') {
                continue;
            }
            $buf .= '[' . trim((string) $section) . "]\n";
            foreach ($pairs as $k => $v) {
                if (!is_string($k) || trim($k) === '') {
                    continue;
                }
                $buf .= trim($k) . '=' . (string) ($v ?? '') . "\n";
            }
            $buf .= "\n";
        }
        return $buf;
    }

    private function normalizeSettings(array $input): array
    {
        $base = self::DEFAULTS;
        foreach ($input as $section => $pairs) {
            if (!is_string($section) || trim($section) === '') {
                continue;
            }
            if (!is_array($pairs)) {
                continue;
            }
            if (!isset($base[$section])) {
                $base[$section] = [];
            }
            foreach ($pairs as $key => $val) {
                if (!is_string($key) || trim($key) === '') {
                    continue;
                }
                $base[$section][$key] = is_scalar($val) || $val === null ? (string) ($val ?? '') : '';
            }
            ksort($base[$section]);
        }
        ksort($base);
        return $base;
    }

    private function isAuthorized(Request $request): bool
    {
        if ((string) ($request->session()->get('user_code') ?? '') !== '') {
            return true;
        }

        $legacySessionId = preg_replace('/[^a-zA-Z0-9,-]/', '', (string) ($_COOKIE['PHPSESSID'] ?? ''));
        if ($legacySessionId === '') {
            return false;
        }

        $savePath = (string) ini_get('session.save_path');
        if ($savePath === '') {
            return false;
        }
        if (str_contains($savePath, ';')) {
            $parts = explode(';', $savePath);
            $savePath = (string) end($parts);
        }
        $savePath = trim($savePath);
        if ($savePath === '') {
            return false;
        }

        $sessionFile = rtrim($savePath, "/\\") . DIRECTORY_SEPARATOR . 'sess_' . $legacySessionId;
        if (!is_file($sessionFile) || !is_readable($sessionFile)) {
            return false;
        }

        $raw = (string) @file_get_contents($sessionFile);
        return $raw !== '' && str_contains($raw, 'user_code|');
    }
}
