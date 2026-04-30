<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdcReportController extends Controller
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
        $useChequeDate = $request->boolean('use_chqdate');
        $rpType = strtoupper(substr(trim((string) $request->query('rptype', 'A')), 0, 1));
        if (!in_array($rpType, ['A', 'R', 'P'], true)) {
            $rpType = 'A';
        }
        $pendType = strtoupper(substr(trim((string) $request->query('pend', 'P')), 0, 1));
        if (!in_array($pendType, ['A', 'P', 'N'], true)) {
            $pendType = 'P';
        }
        $partyCode = strtoupper(trim((string) $request->query('party_code', '')));
        $chequeNo = strtoupper(trim((string) $request->query('chqno', '')));
        $show = $request->boolean('show');
        $export = strtolower(trim((string) $request->query('export', '')));

        $rows = [];
        $totals = ['receipt' => 0.0, 'payment' => 0.0];
        $partyOptions = $this->partyOptions();

        if ($show) {
            $rows = $this->loadRows($dateFrom, $dateTo, $gilevel, $useChequeDate, $rpType, $pendType, $partyCode, $chequeNo);
            foreach ($rows as $row) {
                $totals['receipt'] += $row['receipt'];
                $totals['payment'] += $row['payment'];
            }
        }

        if ($show && $export === 'csv') {
            return $this->exportCsv($rows);
        }

        return view('reports.pdc-report', [
            'gilevel' => $gilevel,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'useChequeDate' => $useChequeDate,
            'rpType' => $rpType,
            'pendType' => $pendType,
            'partyCode' => $partyCode,
            'chequeNo' => $chequeNo,
            'partyOptions' => $partyOptions,
            'show' => $show,
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    public function destroy(Request $request, int $slno): RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        if ($this->hasTable('pdclist')) {
            DB::table('pdclist')->where('slno', $slno)->delete();
        }

        return redirect()->back()->with('status', 'Cheque details deleted.');
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

    private function partyOptions(): array
    {
        if (!$this->hasTable('clients')) {
            return [];
        }

        return DB::table('clients')
            ->selectRaw('TRIM(code) as code, TRIM(name) as name')
            ->orderBy('name')
            ->limit(1000)
            ->get()
            ->map(fn ($row) => ['code' => (string) $row->code, 'name' => (string) $row->name])
            ->all();
    }

    private function loadRows(
        string $dateFrom,
        string $dateTo,
        int $gilevel,
        bool $useChequeDate,
        string $rpType,
        string $pendType,
        string $partyCode,
        string $chequeNo
    ): array {
        if (!$this->hasTable('pdclist')) {
            return [];
        }

        $query = DB::table('pdclist')
            ->leftJoin('accountm as bankm', DB::raw('TRIM(pdclist.bank)'), '=', DB::raw('TRIM(bankm.accode)'))
            ->leftJoin('accountm as partym', DB::raw('TRIM(pdclist.code)'), '=', DB::raw('TRIM(partym.accode)'))
            ->selectRaw('
                pdclist.slno,
                pdclist.tdate,
                pdclist.docno,
                pdclist.chqdate,
                TRIM(COALESCE(pdclist.bank, "")) as bank,
                TRIM(COALESCE(pdclist.code, "")) as code,
                TRIM(COALESCE(pdclist.chqno, "")) as chqno,
                COALESCE(pdclist.amount, 0) as amount,
                TRIM(COALESCE(pdclist.particulars, "")) as particulars,
                TRIM(COALESCE(pdclist.rp, "")) as rp,
                TRIM(COALESCE(pdclist.pend, "")) as pend,
                TRIM(COALESCE(bankm.name, "")) as bankname,
                TRIM(COALESCE(partym.name, "")) as partyname
            ')
            ->where('pdclist.control', '<=', $gilevel)
            ->orderBy('pdclist.tdate')
            ->orderBy('pdclist.docno');

        $dateColumn = $useChequeDate ? 'pdclist.chqdate' : 'pdclist.tdate';
        $query->whereBetween($dateColumn, [$dateFrom, $dateTo]);

        if ($rpType === 'R' || $rpType === 'P') {
            $query->where('pdclist.rp', $rpType);
        }

        if ($pendType === 'P' || $pendType === 'N') {
            $query->where('pdclist.pend', $pendType);
        }

        if ($partyCode !== '') {
            $query->whereRaw('TRIM(pdclist.code) = ?', [$partyCode]);
        }

        if ($chequeNo !== '') {
            $query->whereRaw('TRIM(pdclist.chqno) = ?', [$chequeNo]);
        }

        return $query->get()->map(fn ($row) => [
            'slno' => (int) $row->slno,
            'tdate' => (string) $row->tdate,
            'docno' => (string) $row->docno,
            'chqdate' => $row->chqdate ? (string) $row->chqdate : '',
            'bank' => (string) $row->bank,
            'code' => (string) $row->code,
            'chqno' => (string) $row->chqno,
            'amount' => (float) $row->amount,
            'particulars' => (string) $row->particulars,
            'rp' => (string) $row->rp,
            'pend' => (string) $row->pend,
            'bankname' => (string) $row->bankname,
            'partyname' => (string) $row->partyname,
            'receipt' => (string) $row->rp === 'R' ? (float) $row->amount : 0.0,
            'payment' => (string) $row->rp === 'P' ? (float) $row->amount : 0.0,
        ])->all();
    }

    private function exportCsv(array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Doc No', 'Cheque No', 'Cheque Date', 'Party', 'Bank', 'Particulars', 'Receipt', 'Payment', 'Pend']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['tdate'],
                    $row['docno'],
                    $row['chqno'],
                    $row['chqdate'],
                    $row['partyname'],
                    $row['bankname'],
                    $row['particulars'],
                    $row['receipt'],
                    $row['payment'],
                    $row['pend'],
                ]);
            }
            fclose($handle);
        }, 'pdc_report_' . date('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
