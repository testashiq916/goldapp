<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class GoldRateStoryController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('rate.story-maker', [
            'storyData' => $this->loadStoryData(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'ok' => true,
            'data' => $this->loadStoryData(),
        ]);
    }

    private function loadStoryData(): array
    {
        $generalSettings = $this->readGeneralSettings(['SHOPNM', 'SHOPADDR', 'SHOPPHONE', 'SHOPLOGO']);
        $rates = $this->readRates(['GRATE', 'G18RATE', 'SRATE', 'PGRATE']);

        // Previous day rates for trend
        $prevRates = ['grate' => 0, 'srate' => 0];
        try {
            if ($this->hasTable('ratehistory')) {
                $prev = DB::table('ratehistory')
                    ->orderByDesc('tdate')
                    ->skip(1)->take(1)
                    ->first();
                if ($prev) {
                    $prevRates['grate'] = (float) ($prev->grate ?? 0);
                    $prevRates['srate'] = (float) ($prev->srate ?? 0);
                }
            }
        } catch (\Throwable) {}

        return [
            'shop_name' => trim((string) ($generalSettings['SHOPNM'] ?? '')),
            'shop_address' => trim((string) ($generalSettings['SHOPADDR'] ?? '')),
            'shop_phone' => trim((string) ($generalSettings['SHOPPHONE'] ?? '')),
            'shop_logo_url' => $this->logoUrl((string) ($generalSettings['SHOPLOGO'] ?? '')),
            'rate_22k' => (float) ($rates['GRATE'] ?? 0),
            'rate_18k' => (float) ($rates['G18RATE'] ?? 0),
            'rate_silver' => (float) ($rates['SRATE'] ?? 0),
            'rate_platinum' => (float) ($rates['PGRATE'] ?? 0),
            'prev_22k' => $prevRates['grate'],
            'prev_silver' => $prevRates['srate'],
            'today' => now()->format('Y-m-d'),
        ];
    }

    private function readGeneralSettings(array $codes): array
    {
        $settings = [];

        try {
            if (!$this->hasTable('generals')) {
                return $settings;
            }

            $rows = DB::table('generals')
                ->select(['code', 'cvalue'])
                ->whereIn('code', $codes)
                ->get();

            foreach ($rows as $row) {
                $settings[(string) $row->code] = (string) ($row->cvalue ?? '');
            }
        } catch (\Throwable) {
            return $settings;
        }

        return $settings;
    }

    private function readRates(array $codes): array
    {
        $rates = [];

        try {
            if (!$this->hasTable('generald')) {
                return $rates;
            }

            $rows = DB::table('generald')
                ->select(['code', 'cvalue'])
                ->whereIn('code', $codes)
                ->get();

            foreach ($rows as $row) {
                $rates[(string) $row->code] = (float) ($row->cvalue ?? 0);
            }
        } catch (\Throwable) {
            return $rates;
        }

        return $rates;
    }

    private function logoUrl(string $filename): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            return '';
        }

        $path = public_path('uploads/logo/' . $filename);

        return file_exists($path) ? url('uploads/logo/' . $filename) : '';
    }
}
