<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JournalReportController extends Controller
{
    public function index(Request $request): View|RedirectResponse|StreamedResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $dateFrom = $this->normalizeDate((string) $request->query('date1', '')) ?: date('Y-m-d');
        $dateTo = $this->normalizeDate((string) $request->query('date2', '')) ?: date('Y-m-d');
        $show = $request->boolean('show');
        $export = strtolower(trim((string) $request->query('export', '')));

        $rows = [];
        if ($show) {
            $rows = $this->loadRows($gilevel, $dateFrom, $dateTo);
        }

        if ($show && $export === 'csv') {
            return $this->exportCsv($rows);
        }

        return view('reports.journal-report', [
            'gilevel' => $gilevel,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'show' => $show,
            'rows' => $rows,
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

    private function loadRows(int $gilevel, string $dateFrom, string $dateTo): array
    {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart') || !$this->hasTable('accountm')) {
            return [];
        }

        $rows = DB::table('daybook')
            ->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno')
            ->join('accountm', DB::raw('TRIM(accountm.accode)'), '=', DB::raw('TRIM(daybook.accode)'))
            ->selectRaw('
                daybook.slno,
                daybook.tdate,
                TRIM(daybook.accode) as accode,
                daybook.amount,
                TRIM(daybookpart.vchno) as vchno,
                TRIM(COALESCE(accountm.name, "")) as account_name,
                TRIM(COALESCE(daybookpart.particular, "")) as particular
            ')
            ->whereRaw("TRIM(daybookpart.vchno) LIKE 'JL%'")
            ->where('daybook.control', '<=', $gilevel)
            ->whereBetween('daybook.tdate', [$dateFrom, $dateTo])
            ->orderBy('daybook.tdate')
            ->orderBy('daybookpart.vchno')
            ->orderBy('daybook.slno')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $slno = (int) $row->slno;
            if (!isset($grouped[$slno])) {
                $grouped[$slno] = [
                    'slno' => $slno,
                    'date' => (string) $row->tdate,
                    'vchno' => (string) $row->vchno,
                    'particular' => (string) $row->particular,
                    'lines' => [],
                ];
            }

            $amount = (float) $row->amount;
            $grouped[$slno]['lines'][] = [
                'account_name' => (string) $row->account_name,
                'debit' => $amount < 0 ? abs($amount) : 0.0,
                'credit' => $amount > 0 ? $amount : 0.0,
            ];
        }

        return array_values($grouped);
    }

    private function exportCsv(array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Voucher No', 'Date', 'Account', 'Debit', 'Credit', 'Narration']);
            foreach ($rows as $row) {
                foreach ($row['lines'] as $line) {
                    fputcsv($handle, [
                        $row['vchno'],
                        $row['date'],
                        $line['account_name'],
                        $line['debit'],
                        $line['credit'],
                        $row['particular'],
                    ]);
                }
            }
            fclose($handle);
        }, 'journal_report_' . date('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
