<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RateController extends Controller
{
    private array $rateCodes = [
        'BULRATE' => ['label' => 'Bullion Rate', 'decimals' => 2, 'hidden' => true],
        'BULTOUCH' => ['label' => 'Bullion Touch', 'decimals' => 3, 'hidden' => true, 'type' => 'select', 'options' => [99.5, 99.9]],
        'GRATE' => ['label' => 'Gold Rate (22ct)', 'decimals' => 2, 'icon' => 'fa-star'],
        'G18RATE' => ['label' => 'Gold Rate (18ct)', 'decimals' => 2, 'icon' => 'fa-star'],
        'THRATE' => ['label' => 'TH Rate (24ct)', 'decimals' => 3, 'icon' => 'fa-gem'],
        'PRATE' => ['label' => 'Platinum', 'decimals' => 2, 'icon' => 'fa-atom'],
        'SRATE' => ['label' => 'Silver Rate', 'decimals' => 2, 'icon' => 'fa-moon'],
        'JRATE' => ['label' => 'Smith Rate', 'decimals' => 2, 'icon' => 'fa-hammer'],
        'OGRATE' => ['label' => 'Old Gold Rate', 'decimals' => 2, 'icon' => 'fa-recycle'],
        'OSRATE' => ['label' => 'Old Silver Rate', 'decimals' => 2, 'icon' => 'fa-recycle'],
    ];

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $hasPermission = $this->checkPermission($request);
        $rates = [];
        foreach ($this->rateCodes as $code => $info) {
            $rates[$code] = $this->getRate($code);
        }

        if ($code === 'BULTOUCH' && $rates['BULTOUCH'] == 0) {
            $rates['BULTOUCH'] = 99.9;
        }

        return view('rate.index', [
            'rates' => $rates,
            'rateCodes' => $this->rateCodes,
            'hasPermission' => $hasPermission,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        if (!$this->checkPermission($request)) {
            return response()->json(['success' => false, 'error' => 'You do not have permission to update rates']);
        }

        try {
            DB::beginTransaction();

            $rates = [];
            foreach ($this->rateCodes as $code => $info) {
                $value = round((float) $request->input(strtolower($code), 0), $info['decimals']);
                $rates[$code] = $value;
                $this->saveRate($code, $value);
            }

            // Save to rate history
            $this->saveRateHistory($rates);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rates saved successfully! Updated at ' . date('h:i A'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => 'Error saving rates: ' . $e->getMessage()]);
        }
    }

    public function history(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $history = [];
        try {
            if ($this->hasTable('ratehistory')) {
                $history = DB::table('ratehistory')
                    ->orderByDesc('tdate')
                    ->orderByDesc('ttime')
                    ->limit(30)
                    ->get();
            }
        } catch (\Throwable) {
            // table may not exist
        }

        return view('rate.history', ['history' => $history]);
    }

    public function current(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'goldRate22K' => $this->getRate('GRATE'),
            'goldRate18K' => $this->getRate('G18RATE'),
            'silverRate'  => $this->getRate('SRATE'),
            'thRate'      => $this->getRate('THRATE'),
        ]);
    }

    private function checkPermission(Request $request): bool
    {
        $userCode = (string) $request->session()->get('user_code', '');
        if ($userCode === 'admin') {
            return true;
        }
        $blockedItems = $request->session()->get('blocked_items', []);
        return !in_array('RATESETUP', $blockedItems, true);
    }

    private function getRate(string $code): float
    {
        try {
            $row = DB::table('generald')->where('code', $code)->first();
            if ($row) {
                return (float) $row->cvalue;
            }
            DB::table('generald')->insert(['code' => $code, 'cvalue' => 0]);
            return 0.0;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function saveRate(string $code, float $value): void
    {
        $row = DB::table('generald')->where('code', $code)->first();
        if ($row) {
            DB::table('generald')->where('code', $code)->update(['cvalue' => $value]);
        } else {
            $data = ['code' => $code, 'cvalue' => $value];
            if (Schema::hasColumn('generald', 'description')) {
                $data['description'] = $this->rateCodes[$code]['label'] ?? $code;
            }
            DB::table('generald')->insert($data);
        }
    }

    private function saveRateHistory(array $rates): void
    {
        if (!$this->hasTable('ratehistory')) {
            return;
        }

        $today = date('Y-m-d');
        $time = date('H:i:s');

        $exists = DB::table('ratehistory')->where('tdate', $today)->first();

        $data = [
            'ttime' => $time,
            'grate' => $rates['GRATE'] ?? 0,
            'srate' => $rates['SRATE'] ?? 0,
            'thrate' => $rates['THRATE'] ?? 0,
            'prate' => $rates['PRATE'] ?? 0,
            'bulrate' => $rates['BULRATE'] ?? 0,
            'bultouch' => $rates['BULTOUCH'] ?? 0,
        ];

        if ($exists) {
            DB::table('ratehistory')->where('tdate', $today)->update($data);
        } else {
            $data['tdate'] = $today;
            DB::table('ratehistory')->insert($data);
        }
    }
}
