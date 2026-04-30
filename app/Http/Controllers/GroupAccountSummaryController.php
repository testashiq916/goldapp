<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GroupAccountSummaryController extends Controller
{
    public function index(Request $request): View|RedirectResponse|StreamedResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $date1 = $this->normalizeDate((string) $request->query('date1', '')) ?: date('Y-m-d');
        $date2 = $this->normalizeDate((string) $request->query('date2', '')) ?: date('Y-m-d');
        $groupCode = strtoupper(trim((string) $request->query('grcode', 'SUNDB')));
        $sort = strtolower(trim((string) $request->query('sort', 'name')));
        $type = strtolower(trim((string) $request->query('type', 'with_balance_or_transactions')));
        $relativeToOb = $request->boolean('relative_to_ob', true);
        $show = $request->boolean('show') || $groupCode !== '';
        $export = strtolower(trim((string) $request->query('export', '')));

        $groupOptions = $this->groupOptions();
        $groupName = $this->groupName($groupCode);
        $rows = [];
        $totals = [
            'opening_signed' => 0.0,
            'debit' => 0.0,
            'credit' => 0.0,
            'closing_signed' => 0.0,
            'count' => 0,
        ];

        if ($show && $groupCode !== '') {
            $rows = $this->loadRows($groupCode, $date1, $date2, $gilevel, $type, $sort, $relativeToOb);
            foreach ($rows as $row) {
                $totals['opening_signed'] += $row['opening_signed'];
                $totals['debit'] += $row['debit'];
                $totals['credit'] += $row['credit'];
                $totals['closing_signed'] += $row['closing_signed'];
            }
            $totals['count'] = count($rows);
        }

        if ($show && $export === 'csv') {
            return $this->exportCsv($groupCode, $rows);
        }

        return view('reports.group-account-summary', [
            'title' => 'Group A/c Summary',
            'gilevel' => $gilevel,
            'date1' => $date1,
            'date2' => $date2,
            'groupCode' => $groupCode,
            'groupName' => $groupName,
            'groupOptions' => $groupOptions,
            'sort' => $sort,
            'type' => $type,
            'relativeToOb' => $relativeToOb,
            'show' => $show,
            'rows' => $rows,
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

        return null;
    }

    private function groupOptions(): array
    {
        if (!$this->hasTable('accountg')) {
            return [];
        }

        return DB::table('accountg')
            ->selectRaw('TRIM(grcode) as grcode, TRIM(name) as name')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => ['grcode' => (string) $row->grcode, 'name' => (string) $row->name])
            ->all();
    }

    private function groupName(string $groupCode): string
    {
        if ($groupCode === '' || !$this->hasTable('accountg')) {
            return '';
        }

        return trim((string) (DB::table('accountg')
            ->whereRaw('TRIM(grcode) = ?', [$groupCode])
            ->value('name') ?? ''));
    }

    private function loadRows(
        string $groupCode,
        string $date1,
        string $date2,
        int $gilevel,
        string $type,
        string $sort,
        bool $relativeToOb
    ): array {
        if (!$this->hasTable('accountm')) {
            return [];
        }

        $opColumn = $gilevel === 1 ? 'opbal' : 'opbalb';
        $accounts = DB::table('accountm as a')
            ->leftJoin('clients as c', 'a.accode', '=', 'c.code')
            ->selectRaw("
                TRIM(a.accode) as accode,
                TRIM(COALESCE(a.name, '')) as account_name,
                COALESCE(a.$opColumn, 0) as opening_base,
                TRIM(COALESCE(c.addr1, '')) as addr1,
                TRIM(COALESCE(c.addr2, '')) as addr2,
                TRIM(COALESCE(c.addr3, '')) as addr3,
                TRIM(COALESCE(c.city, '')) as city,
                TRIM(COALESCE(c.telephone, '')) as telephone
            ")
            ->where('a.control', '<=', $gilevel)
            ->whereRaw('TRIM(a.grcode) = ?', [$groupCode])
            ->get();

        if ($accounts->isEmpty()) {
            return [];
        }

        $codes = $accounts->pluck('accode')->map(fn ($code) => trim((string) $code))->filter()->values()->all();

        $openingTrans = [];
        $periodStats = [];
        if ($this->hasTable('daybook') && count($codes) > 0) {
            $openingTrans = DB::table('daybook')
                ->selectRaw('TRIM(accode) as accode, COALESCE(SUM(amount), 0) as amount')
                ->whereIn('accode', $codes)
                ->where('control', '<=', $gilevel)
                ->where('tdate', '<', $date1)
                ->groupBy('accode')
                ->get()
                ->keyBy(fn ($row) => trim((string) $row->accode))
                ->all();

            $periodStats = DB::table('daybook')
                ->selectRaw("
                    TRIM(accode) as accode,
                    COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as debit,
                    COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as credit,
                    COALESCE(SUM(amount), 0) as net
                ")
                ->whereIn('accode', $codes)
                ->where('control', '<=', $gilevel)
                ->whereBetween('tdate', [$date1, $date2])
                ->groupBy('accode')
                ->get()
                ->keyBy(fn ($row) => trim((string) $row->accode))
                ->all();
        }

        $rows = $accounts->map(function ($account) use ($groupCode, $openingTrans, $periodStats, $type, $relativeToOb) {
            $code = trim((string) $account->accode);
            $openingBefore = (float) ($openingTrans[$code]->amount ?? 0);
            $debit = round((float) ($periodStats[$code]->debit ?? 0), 2);
            $credit = round((float) ($periodStats[$code]->credit ?? 0), 2);
            $periodNet = round((float) ($periodStats[$code]->net ?? 0), 2);
            $openingSigned = round((float) $account->opening_base + $openingBefore, 2);
            $closingSigned = round($openingSigned + $periodNet, 2);

            $openingDisplay = abs($openingSigned);
            $openingSide = $openingSigned < 0 ? 'Db' : ($openingSigned > 0 ? 'Cr' : '');
            $closingDisplay = abs($closingSigned);
            $closingSide = $closingSigned < 0 ? 'Db' : ($closingSigned > 0 ? 'Cr' : '');

            if ($relativeToOb && in_array($groupCode, ['SUNDB', 'SUNCR'], true)) {
                if ($groupCode === 'SUNDB') {
                    $openingDisplay = $openingSigned < 0 ? abs($openingSigned) : 0.0;
                    $openingSide = $openingDisplay > 0 ? 'Db' : '';
                    $closingDisplay = $closingSigned < 0 ? abs($closingSigned) : 0.0;
                    $closingSide = $closingDisplay > 0 ? 'Db' : '';
                } else {
                    $openingDisplay = $openingSigned > 0 ? abs($openingSigned) : 0.0;
                    $openingSide = $openingDisplay > 0 ? 'Cr' : '';
                    $closingDisplay = $closingSigned > 0 ? abs($closingSigned) : 0.0;
                    $closingSide = $closingDisplay > 0 ? 'Cr' : '';
                }
            }

            $address = implode(', ', array_filter([
                trim((string) $account->addr1),
                trim((string) $account->addr2),
                trim((string) $account->addr3),
                trim((string) $account->city),
                trim((string) $account->telephone) !== '' ? 'Ph: ' . trim((string) $account->telephone) : '',
            ]));

            return [
                'accode' => $code,
                'account_name' => trim((string) $account->account_name),
                'address' => $address,
                'opening_signed' => $openingSigned,
                'opening_balance' => round($openingDisplay, 2),
                'opening_side' => $openingSide,
                'debit' => $debit,
                'credit' => $credit,
                'closing_signed' => $closingSigned,
                'closing_balance' => round($closingDisplay, 2),
                'closing_side' => $closingSide,
            ];
        })->filter(fn ($row) => $this->matchesType($row, $type))->values()->all();

        usort($rows, fn ($a, $b) => $this->compareRows($a, $b, $sort));

        return $rows;
    }

    private function matchesType(array $row, string $type): bool
    {
        return match ($type) {
            'receivable' => $row['closing_signed'] < 0,
            'payable' => $row['closing_signed'] > 0,
            'with_balance' => abs($row['closing_signed']) > 0.0001,
            'with_transaction' => ($row['debit'] + $row['credit']) > 0.0001,
            'with_balance_or_transactions' => abs($row['opening_signed']) > 0.0001
                || abs($row['closing_signed']) > 0.0001
                || ($row['debit'] + $row['credit']) > 0.0001,
            default => true,
        };
    }

    private function compareRows(array $a, array $b, string $sort): int
    {
        return match ($sort) {
            'code' => strcmp($a['accode'], $b['accode']),
            'balance' => $a['closing_balance'] <=> $b['closing_balance'],
            default => strcmp($a['account_name'], $b['account_name']),
        };
    }

    private function exportCsv(string $groupCode, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Name', 'Address', 'Opening Balance', 'Opening Side', 'Debit', 'Credit', 'Closing Balance', 'Closing Side']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['accode'],
                    $row['account_name'],
                    $row['address'],
                    $row['opening_balance'],
                    $row['opening_side'],
                    $row['debit'],
                    $row['credit'],
                    $row['closing_balance'],
                    $row['closing_side'],
                ]);
            }
            fclose($handle);
        }, 'group_ac_summary_' . strtolower($groupCode ?: 'group') . '_' . date('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
