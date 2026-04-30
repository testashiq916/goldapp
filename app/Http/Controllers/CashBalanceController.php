<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CashBalanceController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $control = (int) ($request->query('semi', $request->session()->get('semi', 1)) ?: 1);
        if ($control < 1) {
            $control = 1;
        }
        if ($control > 2) {
            $control = 2;
        }

        $cashBalance = $this->getCashBalance($control);
        $displayCashBalance = abs($cashBalance);
        $displayCashSide = $cashBalance < 0 ? 'Cr' : 'Dr';

        return view('cash-balance.index', [
            'cashBalance' => $cashBalance,
            'displayCashBalance' => $displayCashBalance,
            'displayCashSide' => $displayCashSide,
            'control' => $control,
        ]);
    }

    private function getCashBalance(int $control): float
    {
        $opbal = 0.0;
        if ($this->hasTable('accountm')) {
            $cash = DB::table('accountm')->whereRaw('TRIM(accode) = ?', ['CASH'])->first();
            if ($cash) {
                $opbal = $control === 1
                    ? (float) ($cash->opbal ?? 0)
                    : (float) ($cash->opbalb ?? 0);
            }
        }

        $daySum = 0.0;
        if ($this->hasTable('daybook')) {
            $query = DB::table('daybook')->whereRaw('TRIM(accode) = ?', ['CASH']);
            if (Schema::hasColumn('daybook', 'control')) {
                $query->where('control', '<=', $control);
            }
            $daySum = (float) ($query->sum('amount') ?? 0);
        }

        return $opbal + $daySum;
    }
}
