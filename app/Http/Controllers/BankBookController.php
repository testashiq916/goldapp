<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankBookController extends Controller
{
    public function index(Request $request): View|RedirectResponse|StreamedResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $dateFrom = $this->normalizeDate((string) $request->query('date1', ''))
            ?: $this->normalizeDate((string) $request->session()->get('gdtstartdate', ''))
            ?: date('Y-m-d');
        $dateTo = $this->normalizeDate((string) $request->query('date2', '')) ?: date('Y-m-d');
        $chequeDateFrom = $this->normalizeDate((string) $request->query('chqdate1', ''));
        $chequeDateTo = $this->normalizeDate((string) $request->query('chqdate2', ''));
        $bankCode = strtoupper(trim((string) $request->query('bankcode', '')));
        $withoutOpening = $request->boolean('without_op');
        $show = $request->boolean('show') || $bankCode !== '';
        $export = strtolower(trim((string) $request->query('export', '')));

        $banks = $this->bankOptions();
        if ($bankCode === '' && !empty($banks)) {
            $bankCode = (string) $banks[0]['accode'];
        }

        $rows = collect();
        $openingBalance = 0.0;
        $closingBalance = 0.0;
        $bankName = '';
        $totals = ['debit' => 0.0, 'credit' => 0.0];

        if ($show && $bankCode !== '') {
            $bankName = $this->bankName($bankCode);
            $openingBalance = $withoutOpening ? 0.0 : $this->openingBalance($bankCode, $dateFrom, $gilevel);
            $rows = $this->loadRows($bankCode, $dateFrom, $dateTo, $gilevel, $openingBalance, $chequeDateFrom, $chequeDateTo);
            $totals = [
                'debit' => round((float) $rows->sum('debit'), 2),
                'credit' => round((float) $rows->sum('credit'), 2),
            ];
            $closingBalance = $rows->isEmpty()
                ? $openingBalance
                : (float) ($rows->last()['running_balance'] ?? $openingBalance);
        }

        if ($show && $export === 'csv') {
            return $this->exportCsv($rows, $bankCode, $dateFrom, $dateTo, $openingBalance, $closingBalance);
        }

        return view('reports.bank-book', [
            'gilevel' => $gilevel,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'chequeDateFrom' => $chequeDateFrom,
            'chequeDateTo' => $chequeDateTo,
            'bankCode' => $bankCode,
            'bankName' => $bankName,
            'banks' => $banks,
            'withoutOpening' => $withoutOpening,
            'show' => $show,
            'rows' => $rows->all(),
            'openingBalance' => $openingBalance,
            'closingBalance' => $closingBalance,
            'totals' => $totals,
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
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $value, $m)) {
            $year = (int) $m[3];
            $year += $year >= 50 ? 1900 : 2000;
            return sprintf('%04d-%02d-%02d', $year, (int) $m[2], (int) $m[1]);
        }
        return null;
    }

    private function bankOptions(): array
    {
        if (!$this->hasTable('accountm')) {
            return [];
        }

        $query = DB::table('accountm')
            ->selectRaw('TRIM(accode) as accode, TRIM(name) as name')
            ->whereRaw("TRIM(COALESCE(actype2, '')) = 'B'")
            ->orderBy('name');

        if (Schema::hasColumn('accountm', 'removed')) {
            $query->where(function ($inner) {
                $inner->where('removed', '!=', 1)->orWhereNull('removed');
            });
        }

        return $query->get()->map(fn ($row) => [
            'accode' => (string) $row->accode,
            'name' => (string) $row->name,
        ])->all();
    }

    private function bankName(string $bankCode): string
    {
        if (!$this->hasTable('accountm')) {
            return $bankCode;
        }

        return trim((string) (DB::table('accountm')
            ->whereRaw('TRIM(accode) = ?', [$bankCode])
            ->value('name') ?? $bankCode));
    }

    private function openingBalance(string $bankCode, string $dateFrom, int $gilevel): float
    {
        $base = 0.0;
        if ($this->hasTable('accountm')) {
            $base = (float) (DB::table('accountm')
                ->whereRaw('TRIM(accode) = ?', [$bankCode])
                ->value($gilevel === 1 ? 'opbal' : 'opbalb') ?? 0);
        }

        if (!$this->hasTable('daybook')) {
            return round($base, 2);
        }

        $prior = (float) DB::table('daybook')
            ->whereRaw('TRIM(accode) = ?', [$bankCode])
            ->where('control', '<=', $gilevel)
            ->where('tdate', '<', $dateFrom)
            ->sum('amount');

        return round($base + $prior, 2);
    }

    private function loadRows(
        string $bankCode,
        string $dateFrom,
        string $dateTo,
        int $gilevel,
        float $openingBalance,
        ?string $chequeDateFrom,
        ?string $chequeDateTo
    ): Collection {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart')) {
            return collect();
        }

        $query = DB::table('daybook as d')
            ->join('daybookpart as dp', 'd.slno', '=', 'dp.slno')
            ->leftJoin('accountm as oth', DB::raw('TRIM(d.opaccode)'), '=', DB::raw('TRIM(oth.accode)'))
            ->selectRaw('
                d.slno,
                d.tdate,
                d.amount,
                TRIM(COALESCE(dp.vchno, "")) as vchno,
                TRIM(COALESCE(dp.chequeno, "")) as chequeno,
                dp.chequedate,
                TRIM(COALESCE(dp.particular, "")) as particular,
                TRIM(COALESCE(d.opaccode, "")) as opaccode,
                TRIM(COALESCE(oth.name, "")) as opacname
            ')
            ->whereRaw('TRIM(d.accode) = ?', [$bankCode])
            ->where('d.control', '<=', $gilevel)
            ->whereBetween('d.tdate', [$dateFrom, $dateTo])
            ->orderBy('d.tdate')
            ->orderBy('d.slno');

        if ($chequeDateFrom !== null) {
            $query->whereDate('dp.chequedate', '>=', $chequeDateFrom);
        }
        if ($chequeDateTo !== null) {
            $query->whereDate('dp.chequedate', '<=', $chequeDateTo);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return collect();
        }

        $oppositeMap = $this->oppositeAccountMap($rows->pluck('slno')->unique()->all(), $bankCode);
        $running = $openingBalance;

        return $rows->map(function ($row) use (&$running, $oppositeMap) {
            $slno = (int) $row->slno;
            $amount = (float) $row->amount;
            $opposite = $oppositeMap[$slno] ?? ['accode' => (string) $row->opaccode, 'name' => (string) $row->opacname];

            $running += $amount;

            return [
                'slno' => $slno,
                'date' => (string) $row->tdate,
                'vchno' => (string) $row->vchno,
                'particular' => (string) $row->particular,
                'chequeno' => (string) $row->chequeno,
                'chequedate' => $row->chequedate ? (string) $row->chequedate : '',
                'opaccode' => (string) ($opposite['accode'] ?? ''),
                'opacname' => (string) ($opposite['name'] ?? ''),
                'debit' => $amount < 0 ? abs($amount) : 0.0,
                'credit' => $amount > 0 ? $amount : 0.0,
                'running_balance' => round($running, 2),
            ];
        });
    }

    private function oppositeAccountMap(array $slnos, string $bankCode): array
    {
        if (empty($slnos) || !$this->hasTable('daybook')) {
            return [];
        }

        $rows = DB::table('daybook as d')
            ->leftJoin('accountm as a', DB::raw('TRIM(d.accode)'), '=', DB::raw('TRIM(a.accode)'))
            ->selectRaw('d.slno, TRIM(d.accode) as accode, TRIM(COALESCE(a.name, "")) as name, ABS(d.amount) as abs_amount')
            ->whereIn('d.slno', $slnos)
            ->whereRaw('TRIM(d.accode) <> ?', [$bankCode])
            ->orderBy('d.slno')
            ->orderByDesc('abs_amount')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $slno = (int) $row->slno;
            if (isset($map[$slno])) {
                continue;
            }
            $map[$slno] = [
                'accode' => (string) $row->accode,
                'name' => (string) $row->name,
            ];
        }

        return $map;
    }

    private function exportCsv(Collection $rows, string $bankCode, string $dateFrom, string $dateTo, float $openingBalance, float $closingBalance): StreamedResponse
    {
        $filename = 'bank_book_' . Str::slug($bankCode ?: 'bank') . '_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows, $openingBalance, $closingBalance) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Voucher No', 'A/c Name', 'Narration', 'Cheque No', 'Cheque Date', 'Debit', 'Credit', 'Balance']);
            fputcsv($handle, ['', '', '', 'Opening Balance', '', '', $openingBalance < 0 ? abs($openingBalance) : '', $openingBalance > 0 ? abs($openingBalance) : '', '']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['date'],
                    $row['vchno'],
                    $row['opacname'],
                    $row['particular'],
                    $row['chequeno'],
                    $row['chequedate'],
                    $row['debit'] ?: '',
                    $row['credit'] ?: '',
                    $row['running_balance'],
                ]);
            }

            fputcsv($handle, ['', '', '', 'Closing Balance', '', '', $closingBalance < 0 ? abs($closingBalance) : '', $closingBalance > 0 ? abs($closingBalance) : '', '']);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
