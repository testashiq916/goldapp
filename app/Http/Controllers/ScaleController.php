<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class ScaleController extends Controller
{
    public function settings(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $settings = $this->loadScaleSettings();
        return view('scale.settings', compact('settings'));
    }

    // ── API ───────────────────────────────────────────────────────────────

    public function saveSettings(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $keys = ['SCALE_ENABLED', 'SCALE_PORT', 'SCALE_BAUD', 'SCALE_BRAND',
                 'SCALE_PROTOCOL', 'SCALE_DECIMALS', 'SCALE_UNIT'];

        try {
            foreach ($keys as $key) {
                $value = (string) ($request->input($key) ?? '');
                $this->upsertGeneral($key, $value);
            }
            return response()->json(['ok' => true, 'message' => 'Scale settings saved']);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getSettings(): JsonResponse
    {
        return response()->json(['ok' => true, 'settings' => $this->loadScaleSettings()]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function loadScaleSettings(): array
    {
        $settings = [];
        try {
            $rows = DB::table('generals')
                ->whereRaw("code LIKE 'SCALE_%'")
                ->get(['code', 'cvalue']);
            foreach ($rows as $row) {
                $settings[(string) $row->code] = (string) ($row->cvalue ?? '');
            }
        } catch (Throwable) {
        }

        return array_merge([
            'SCALE_ENABLED'  => 'N',
            'SCALE_PORT'     => 'COM1',
            'SCALE_BAUD'     => '9600',
            'SCALE_BRAND'    => 'generic',
            'SCALE_PROTOCOL' => 'continuous',
            'SCALE_DECIMALS' => '3',
            'SCALE_UNIT'     => 'g',
        ], $settings);
    }

    private function upsertGeneral(string $code, string $value): void
    {
        $exists = DB::table('generals')->where('code', $code)->exists();
        if ($exists) {
            DB::table('generals')->where('code', $code)->update(['cvalue' => $value]);
        } else {
            DB::table('generals')->insert(['code' => $code, 'cvalue' => $value]);
        }
    }
}
