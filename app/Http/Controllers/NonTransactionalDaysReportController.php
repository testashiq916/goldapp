<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NonTransactionalDaysReportController extends Controller
{
    public function index(Request $request): View|RedirectResponse|StreamedResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $dateFrom = $this->normalizeDate((string) $request->query('date1', '')) ?: date('Y-m-d');
        $dateTo = $this->normalizeDate((string) $request->query('date2', '')) ?: date('Y-m-d');
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $show = $request->boolean('show');
        $export = strtolower(trim((string) $request->query('export', '')));
        $rows = [];

        if ($show) {
            $rows = $this->loadRows($dateFrom, $dateTo);
        }

        if ($show && $export === 'csv') {
            return $this->exportCsv($rows, $dateFrom, $dateTo);
        }

        return view('reports.non-transactional-days-report', [
            'title' => 'Non Transactional Days Report',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'show' => $show,
            'rows' => $rows,
        ]);
    }

    private function loadRows(string $dateFrom, string $dateTo): array
    {
        $activity = [];

        if ($this->hasTable('daybook')) {
            $daybookDays = DB::table('daybook')
                ->selectRaw('tdate, count(*) as row_count')
                ->whereBetween('tdate', [$dateFrom, $dateTo])
                ->groupBy('tdate')
                ->pluck('row_count', 'tdate');

            foreach ($daybookDays as $tdate => $count) {
                $activity[(string) $tdate] = ((int) ($activity[(string) $tdate] ?? 0)) + (int) $count;
            }
        }

        if ($this->hasTable('smithm')) {
            $smithDays = DB::table('smithm')
                ->selectRaw('tdate, count(*) as row_count')
                ->whereBetween('tdate', [$dateFrom, $dateTo])
                ->groupBy('tdate')
                ->pluck('row_count', 'tdate');

            foreach ($smithDays as $tdate => $count) {
                $activity[(string) $tdate] = ((int) ($activity[(string) $tdate] ?? 0)) + (int) $count;
            }
        }

        $rows = [];
        $current = Carbon::parse($dateFrom);
        $last = Carbon::parse($dateTo);
        $sno = 1;

        while ($current->lte($last)) {
            $key = $current->toDateString();
            if ((int) ($activity[$key] ?? 0) === 0) {
                $rows[] = [
                    'sno' => $sno++,
                    'tdate' => $key,
                ];
            }
            $current->addDay();
        }

        return $rows;
    }

    private function exportCsv(array $rows, string $dateFrom, string $dateTo): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['SNo', 'Holiday']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row['sno'], $row['tdate']]);
            }
            fclose($handle);
        }, 'non_transactional_days_' . str_replace('-', '', $dateFrom) . '_' . str_replace('-', '', $dateTo) . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return null;
    }
}
