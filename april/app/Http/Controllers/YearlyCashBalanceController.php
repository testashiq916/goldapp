<?php

namespace App\Http\Controllers;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class YearlyCashBalanceController extends Controller
{
    public function index(Request $request): View|RedirectResponse|StreamedResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $fyStartYear = $this->resolveFinancialYearStart($request);
        $fyStart = sprintf('%04d-04-01', $fyStartYear);
        $fyEnd = sprintf('%04d-03-31', $fyStartYear + 1);

        $openingRaw = $this->openingBalance('CASH', $gilevel);
        $amountsByDate = $this->amountsByDate('CASH', $fyStart, $fyEnd, $gilevel);
        $monthLabels = ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'];

        $grid = [];
        for ($day = 1; $day <= 31; $day++) {
            $grid[$day] = array_fill(1, 12, null);
        }

        $runningRaw = $openingRaw;
        $datesWithLinks = [];
        $period = new DatePeriod(
            new DateTimeImmutable($fyStart),
            new DateInterval('P1D'),
            (new DateTimeImmutable($fyEnd))->modify('+1 day')
        );

        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $dayAmount = (float) ($amountsByDate[$key] ?? 0);

            if ($dayAmount == 0.0) {
                continue;
            }

            $runningRaw += $dayAmount;
            $monthIndex = $this->monthIndex((int) $date->format('n'));
            $dayNumber = (int) $date->format('j');

            $grid[$dayNumber][$monthIndex] = round(-$runningRaw, 2);
            $datesWithLinks[$dayNumber . '-' . $monthIndex] = $key;
        }

        if (strtolower(trim((string) $request->query('export', ''))) === 'csv') {
            return $this->exportCsv($fyStartYear, $monthLabels, $grid);
        }

        return view('reports.yearly-cash-balance', [
            'gilevel' => $gilevel,
            'fyStartYear' => $fyStartYear,
            'fyStart' => $fyStart,
            'fyEnd' => $fyEnd,
            'monthLabels' => $monthLabels,
            'grid' => $grid,
            'datesWithLinks' => $datesWithLinks,
            'openingDisplay' => round(-$openingRaw, 2),
        ]);
    }

    private function exportCsv(int $fyStartYear, array $monthLabels, array $grid): StreamedResponse
    {
        $filename = 'yearly_cash_balance_' . $fyStartYear . '_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($monthLabels, $grid) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_merge(['Day'], $monthLabels));
            for ($day = 1; $day <= 31; $day++) {
                $row = [$day];
                for ($monthIndex = 1; $monthIndex <= 12; $monthIndex++) {
                    $row[] = $grid[$day][$monthIndex];
                }
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function resolveFinancialYearStart(Request $request): int
    {
        $requestedYear = (int) $request->query('year', 0);
        if ($requestedYear >= 2000 && $requestedYear <= 2100) {
            return $requestedYear;
        }

        $sessionStart = trim((string) $request->session()->get('gdtstartdate', ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sessionStart)) {
            return (int) substr($sessionStart, 0, 4);
        }

        $today = new DateTimeImmutable('today');
        $year = (int) $today->format('Y');
        $month = (int) $today->format('n');

        return $month < 4 ? $year - 1 : $year;
    }

    private function openingBalance(string $code, int $gilevel): float
    {
        $opColumn = $gilevel === 1 ? 'opbal' : 'opbalb';

        if (!$this->hasTable('accountm')) {
            return 0.0;
        }

        return (float) (DB::table('accountm')
            ->whereRaw('TRIM(accode) = ?', [$code])
            ->value($opColumn) ?? 0);
    }

    private function amountsByDate(string $code, string $dateFrom, string $dateTo, int $gilevel): array
    {
        if (!$this->hasTable('daybook')) {
            return [];
        }

        return DB::table('daybook')
            ->selectRaw('tdate, COALESCE(SUM(amount), 0) as day_amount')
            ->whereRaw('TRIM(accode) = ?', [$code])
            ->whereBetween('tdate', [$dateFrom, $dateTo])
            ->where('control', '<=', $gilevel)
            ->groupBy('tdate')
            ->orderBy('tdate')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->tdate => (float) $row->day_amount])
            ->all();
    }

    private function monthIndex(int $month): int
    {
        return $month >= 4 ? $month - 3 : $month + 9;
    }
}
