<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcReceivablePayableSummaryController extends Controller
{
    public function index(Request $request): View|RedirectResponse|StreamedResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $sessionStart = $this->normalizeDate((string) $request->session()->get('gdtstartdate', '')) ?: date('Y') . '-04-01';
        $mode = trim((string) $request->query('mode', 'C'));
        $mode = $mode === 'S' ? 'S' : 'C';
        $customerBalanceOnly = $mode !== 'S';

        $date1 = $this->normalizeDate((string) $request->query('date1', '')) ?: date('Y-m-d');
        $date2 = $this->normalizeDate((string) $request->query('date2', '')) ?: date('Y-m-d');
        $basedOnDueDate = $request->boolean('based_on_duedate');
        $type = (string) $request->query('type', $mode === 'S' ? 'payable' : 'receivable');
        $partyType = (string) $request->query('party_type', $mode === 'S' ? 'suppliers' : 'customers');
        $sort = (string) $request->query('sort', 'name');
        $showWeight = $request->boolean('show_wgt');
        if ($customerBalanceOnly) {
            $basedOnDueDate = false;
            if (!in_array($type, ['all', 'receivable', 'payable'], true)) {
                $type = 'receivable';
            }
            $partyType = 'customers';
            $showWeight = false;
            if (!in_array($sort, ['name', 'code', 'balance', 'duedate'], true)) {
                $sort = 'name';
            }
        }
        $selectedOnly = $customerBalanceOnly ? false : $request->boolean('selected_only');
        $selectedCodes = collect((array) $request->query('selected', []))
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->values()
            ->all();
        $coCode = $customerBalanceOnly ? '' : strtoupper(trim((string) $request->query('cocode', '')));
        $groupCode = strtoupper(trim((string) $request->query('group', '')));
        $routeCode = strtoupper(trim((string) $request->query('route', '')));
        $areaCode = strtoupper(trim((string) $request->query('area', '')));
        $nameSearch = trim((string) $request->query('name_search', ''));
        $minAmt = (float) $request->query('min_amt', 0);
        $maxAmt = (float) $request->query('max_amt', 0);
        $show = $request->boolean('show');
        $export = strtolower(trim((string) $request->query('export', '')));

        $title = match ($mode) {
            'S' => 'Account Payable Summary',
            'C' => 'Customer Balance',
            default => 'Customer Balance',
        };

        $filters = [
            'basedOnDueDate' => $basedOnDueDate,
            'type' => $type,
            'partyType' => $partyType,
            'sort' => $sort,
            'showWeight' => $showWeight,
            'selectedOnly' => $selectedOnly,
            'selectedCodes' => $selectedCodes,
            'coCode' => $coCode,
            'groupCode' => $groupCode,
            'routeCode' => $routeCode,
            'areaCode' => $areaCode,
            'nameSearch' => $nameSearch,
            'minAmt' => $minAmt,
            'maxAmt' => $maxAmt,
            'date1' => $date1,
            'date2' => $date2,
        ];

        $lookups = $this->lookups();
        $rows = [];
        $totals = ['to_get' => 0.0, 'to_give' => 0.0, 'net' => 0.0, 'wgt' => 0.0, 'count' => 0];

        if ($show) {
            $rows = $this->loadRows($sessionStart, $date1, $date2, $gilevel, $mode, $filters);
            foreach ($rows as $row) {
                $totals['to_get'] += $row['status'] === '(TR)' ? $row['balance_abs'] : 0.0;
                $totals['to_give'] += $row['status'] === '(TG)' ? $row['balance_abs'] : 0.0;
                $totals['net'] += $row['netbal'];
                $totals['wgt'] += $row['wgtbal'];
            }
            $totals['count'] = count($rows);
        }

        if ($show && $export === 'csv') {
            return $this->exportCsv($rows, $customerBalanceOnly);
        }

        if ($show && $export === 'excel') {
            return $this->exportExcel($rows, $title, $date1, $date2, $customerBalanceOnly);
        }

        return view('reports.ac-receivable-payable-summary', [
            'title' => $title,
            'gilevel' => $gilevel,
            'date1' => $date1,
            'date2' => $date2,
            'sessionStart' => $sessionStart,
            'mode' => $mode,
            'show' => $show,
            'filters' => $filters,
            'lookups' => $lookups,
            'rows' => $rows,
            'totals' => $totals,
            'customerBalanceOnly' => $customerBalanceOnly,
        ]);
    }

    public function updateDuedate(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Login required'], 401);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        $duedate = $this->normalizeDate((string) $request->input('duedate', ''));

        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'Party code required'], 422);
        }
        if ($duedate === null) {
            return response()->json(['ok' => false, 'message' => 'Valid due date required'], 422);
        }
        if (!$this->hasTable('clients') || !Schema::hasColumn('clients', 'duedate')) {
            return response()->json(['ok' => false, 'message' => 'Due date column not found'], 500);
        }

        $client = DB::table('clients')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->first();

        if (!$client) {
            return response()->json(['ok' => false, 'message' => 'Party not found'], 404);
        }

        DB::table('clients')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->update(['duedate' => $duedate]);

        return response()->json([
            'ok' => true,
            'message' => 'Due date updated',
            'duedate' => $duedate,
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

    private function lookups(): array
    {
        return [
            'cocodes' => $this->simpleOptions('accountm', 'accode', 'name'),
            'groups' => $this->simpleOptions('clientsgrp', 'code', 'name'),
            'routes' => $this->simpleOptions('clientsroute', 'code', 'name'),
            'areas' => $this->simpleOptions('clientsarea', 'code', 'name'),
        ];
    }

    private function simpleOptions(string $table, string $codeColumn, string $nameColumn): array
    {
        if (!$this->hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->selectRaw('TRIM(' . $codeColumn . ') as code, TRIM(COALESCE(' . $nameColumn . ', ' . $codeColumn . ')) as name')
            ->orderBy('name')
            ->limit(1000)
            ->get()
            ->map(fn ($row) => ['code' => (string) $row->code, 'name' => (string) $row->name])
            ->all();
    }

    private function loadRows(string $sessionStart, string $date1, string $date2, int $gilevel, string $mode, array $filters): array
    {
        if (!$this->hasTable('accountm') || !$this->hasTable('clients')) {
            return [];
        }

        $uptoDate = $filters['basedOnDueDate'] ? $date2 : $date1;
        $opColumn = $gilevel === 1 ? 'opbal' : 'opbalb';
        $hasDaybook = $this->hasTable('daybook');
        $hasSales = $this->hasTable('salesm');
        $hasPurchase = $this->hasTable('purchasem');
        $hasKuri = $this->hasTable('clients_kuridet');
        $hasWeight = $this->hasTable('daybookratewgt');

        $baseQuery = DB::table('accountm')
            ->join('clients', 'accountm.accode', '=', 'clients.code')
            ->selectRaw("
                TRIM(accountm.accode) as accode,
                TRIM(COALESCE(accountm.name, clients.name, '')) as account_name,
                TRIM(COALESCE(accountm.actype2, '')) as actype2,
                COALESCE(accountm.$opColumn, 0) as opbal,
                TRIM(COALESCE(clients.name, '')) as client_name,
                TRIM(COALESCE(clients.addr1, '')) as addr1,
                TRIM(COALESCE(clients.addr2, '')) as addr2,
                TRIM(COALESCE(clients.addr3, '')) as addr3,
                TRIM(COALESCE(clients.city, '')) as city,
                TRIM(COALESCE(clients.telephone, '')) as telephone,
                TRIM(COALESCE(clients.mobile, '')) as mobile,
                TRIM(COALESCE(clients.cocode, '')) as cocode,
                TRIM(COALESCE(clients.grp, '')) as grp,
                TRIM(COALESCE(clients.route, '')) as route,
                TRIM(COALESCE(clients.carea, '')) as carea,
                COALESCE(clients.billdate, null) as billdate,
                COALESCE(clients.duedate, null) as client_duedate,
                COALESCE(clients.opweight, 0) as opweight
            ")
            ->where('accountm.control', '<=', $gilevel)
            ->when($mode === 'C', fn ($query) => $query->where('accountm.actype2', 'C'))
            ->when($mode === 'S', fn ($query) => $query->where('accountm.actype2', 'S'))
            ->when($filters['partyType'] === 'customers', fn ($query) => $query->where('accountm.actype2', 'C'))
            ->when($filters['partyType'] === 'suppliers', fn ($query) => $query->where('accountm.actype2', 'S'))
            ->when($filters['partyType'] === 'goldsmith', fn ($query) => $query->where('accountm.actype2', 'G'))
            ->when($filters['partyType'] === 'jewelery', fn ($query) => $query->where('accountm.actype2', 'J'))
            ->when($filters['partyType'] === 'refiners', fn ($query) => $query->where('accountm.actype2', 'R'))
            ->when($filters['partyType'] === 'staffs', fn ($query) => $query->where('accountm.actype2', 'F'))
            ->when($filters['coCode'] !== '', fn ($query) => $query->where('clients.cocode', $filters['coCode']))
            ->when($filters['groupCode'] !== '', fn ($query) => $query->where('clients.grp', $filters['groupCode']))
            ->when($filters['routeCode'] !== '', fn ($query) => $query->where('clients.route', $filters['routeCode']))
            ->when($filters['areaCode'] !== '', fn ($query) => $query->where('clients.carea', $filters['areaCode']))
            ->when($filters['nameSearch'] !== '', function ($query) use ($filters) {
                $needle = '%' . strtoupper($filters['nameSearch']) . '%';
                $query->where(function ($sub) use ($needle) {
                    $sub->whereRaw('UPPER(COALESCE(clients.name, "")) like ?', [$needle])
                        ->orWhereRaw('UPPER(COALESCE(clients.addr1, "")) like ?', [$needle])
                        ->orWhereRaw('UPPER(COALESCE(clients.addr2, "")) like ?', [$needle])
                        ->orWhereRaw('UPPER(COALESCE(clients.addr3, "")) like ?', [$needle])
                        ->orWhereRaw('UPPER(COALESCE(clients.city, "")) like ?', [$needle]);
                });
            });

        $baseRows = $baseQuery->get();
        if ($baseRows->isEmpty()) {
            return [];
        }

        $codes = $baseRows->pluck('accode')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->values()
            ->all();

        $coCodes = $baseRows->pluck('cocode')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $daybookStats = [];
        if ($hasDaybook && count($codes) > 0) {
            $daybookStats = DB::table('daybook')
                ->selectRaw("
                    TRIM(accode) as code,
                    COALESCE(SUM(CASE WHEN tdate <= ? THEN amount ELSE 0 END), 0) as tran_amount,
                    COALESCE(SUM(CASE WHEN tdate between ? and ? AND amount < 0 THEN ABS(amount) ELSE 0 END), 0) as period_debit,
                    COALESCE(SUM(CASE WHEN tdate between ? and ? AND amount > 0 THEN amount ELSE 0 END), 0) as period_credit,
                    MAX(CASE WHEN amount > 0 THEN tdate ELSE NULL END) as lcdate
                ", [$uptoDate, $sessionStart, $uptoDate, $sessionStart, $uptoDate])
                ->whereIn('accode', $codes)
                ->where('control', '<=', $gilevel)
                ->groupBy('accode')
                ->get()
                ->keyBy(fn ($row) => trim((string) $row->code))
                ->all();
        }

        $salesDue = [];
        if ($hasSales && count($codes) > 0) {
            $salesDue = DB::table('salesm')
                ->selectRaw('TRIM(custcode) as code, MAX(duedate) as due')
                ->whereIn('custcode', $codes)
                ->groupBy('custcode')
                ->get()
                ->keyBy(fn ($row) => trim((string) $row->code))
                ->all();
        }

        $purchaseDue = [];
        if ($hasPurchase && count($codes) > 0) {
            $purchaseDue = DB::table('purchasem')
                ->selectRaw('TRIM(suppcode) as code, MAX(duedate) as due')
                ->whereIn('suppcode', $codes)
                ->groupBy('suppcode')
                ->get()
                ->keyBy(fn ($row) => trim((string) $row->code))
                ->all();
        }

        $kuriCounts = [];
        if ($hasKuri && count($codes) > 0) {
            $kuriCounts = DB::table('clients_kuridet')
                ->selectRaw('TRIM(code) as code, COUNT(*) as kuri_count')
                ->whereIn('code', $codes)
                ->groupBy('code')
                ->get()
                ->keyBy(fn ($row) => trim((string) $row->code))
                ->all();
        }

        $weightSums = [];
        if ($hasWeight && count($codes) > 0) {
            $weightSums = DB::table('daybookratewgt')
                ->selectRaw('TRIM(code) as code, COALESCE(SUM(wgt), 0) as tran_wgt')
                ->whereIn('code', $codes)
                ->where('tdate', '<=', $uptoDate)
                ->where('control', '<=', $gilevel)
                ->groupBy('code')
                ->get()
                ->keyBy(fn ($row) => trim((string) $row->code))
                ->all();
        }

        $coNames = [];
        if (count($coCodes) > 0) {
            $coNames = DB::table('accountm')
                ->selectRaw('TRIM(accode) as code, TRIM(COALESCE(name, "")) as name')
                ->whereIn('accode', $coCodes)
                ->get()
                ->mapWithKeys(fn ($row) => [trim((string) $row->code) => trim((string) $row->name)])
                ->all();
        }

        $rows = $baseRows
            ->map(function ($row) use ($filters, $daybookStats, $salesDue, $purchaseDue, $kuriCounts, $weightSums, $coNames) {
                $code = trim((string) $row->accode);
                $tranAmount = (float) ($daybookStats[$code]->tran_amount ?? 0);
                $periodDebit = (float) ($daybookStats[$code]->period_debit ?? 0);
                $periodCredit = (float) ($daybookStats[$code]->period_credit ?? 0);
                $lcDate = $daybookStats[$code]->lcdate ?? null;
                $salesDueDate = $salesDue[$code]->due ?? null;
                $purchaseDueDate = $purchaseDue[$code]->due ?? null;
                $coName = $coNames[trim((string) $row->cocode)] ?? '';
                $kuri = (int) ($kuriCounts[$code]->kuri_count ?? 0);
                $tranWgt = (float) ($weightSums[$code]->tran_wgt ?? 0);

                $clientDueDate = $row->client_duedate && $row->client_duedate !== '0000-00-00'
                    ? (string) $row->client_duedate
                    : '';
                $maxDue = collect([$clientDueDate, $salesDueDate, $purchaseDueDate])
                    ->filter(fn ($value) => $value && $value !== '0000-00-00')
                    ->sort()
                    ->last();
                $netBal = round((float) $row->opbal + $tranAmount, 2);
                $wgtBal = round(-((float) $row->opweight + $tranWgt), 3);

                return [
                    'accode' => $code,
                    'actype2' => trim((string) $row->actype2),
                    'name' => trim((string) $row->client_name),
                    'addr1' => trim((string) $row->addr1),
                    'addr2' => trim((string) $row->addr2),
                    'addr3' => trim((string) $row->addr3),
                    'city' => trim((string) $row->city),
                    'address_only' => trim(implode(', ', array_filter([
                        trim((string) $row->addr1),
                        trim((string) $row->addr2),
                        trim((string) $row->addr3),
                        trim((string) $row->city),
                    ]))),
                    'address' => trim(implode(', ', array_filter([
                        trim((string) $row->client_name),
                        trim((string) $row->telephone) !== '' ? 'Ph : ' . trim((string) $row->telephone) : '',
                        trim((string) $row->mobile) !== '' ? 'Mob : ' . trim((string) $row->mobile) : '',
                        trim((string) $row->addr1),
                        trim((string) $row->addr2),
                        trim((string) $row->addr3),
                        trim((string) $row->city),
                        $coName !== '' ? 'C/o ' . $coName : '',
                    ]))),
                    'telephone' => trim((string) $row->telephone),
                    'mobile' => trim((string) $row->mobile),
                    'cocode' => trim((string) $row->cocode),
                    'grp' => trim((string) $row->grp),
                    'route' => trim((string) $row->route),
                    'carea' => trim((string) $row->carea),
                    'billdate' => $row->billdate ? (string) $row->billdate : '',
                    'lcdate' => $lcDate ? (string) $lcDate : '',
                    'cduedate' => $clientDueDate !== '' ? $clientDueDate : ($maxDue ? (string) $maxDue : ''),
                    'netbal' => $netBal,
                    'balance_abs' => abs($netBal),
                    'status' => $netBal > 0 ? '(TG)' : ($netBal < 0 ? '(TR)' : ''),
                    'debit' => abs($periodDebit),
                    'credit' => $periodCredit,
                    'kuri' => $kuri,
                    'wgtbal' => $wgtBal,
                    'selected' => in_array(strtoupper($code), $filters['selectedCodes'], true) ? 1 : 0,
                    'co_name' => $coName,
                ];
            })
            ->filter(fn ($row) => $this->matchesMode($row, $mode))
            ->filter(fn ($row) => $this->matchesFilters($row, $filters))
            ->values()
            ->all();

        usort($rows, fn ($a, $b) => $this->compareRows($a, $b, $filters['sort']));

        return $rows;
    }

    private function matchesMode(array $row, string $mode): bool
    {
        if ($mode === 'S') {
            return $row['actype2'] === 'S';
        }
        if ($mode === 'C') {
            return $row['actype2'] === 'C';
        }
        return true;
    }

    private function matchesFilters(array $row, array $filters): bool
    {
        $type = strtolower($filters['type']);
        if ($type === 'receivable' && !($row['netbal'] < 0 || ($filters['showWeight'] && $row['wgtbal'] > 0))) {
            return false;
        }
        if ($type === 'payable' && !($row['netbal'] > 0)) {
            return false;
        }
        if ($type === 'with_balance' && (abs($row['netbal']) <= 0.0001)) {
            return false;
        }
        if ($type === 'with_transaction' && (($row['debit'] + $row['credit']) <= 0.0001)) {
            return false;
        }

        $partyType = strtolower($filters['partyType']);
        if ($partyType === 'customers' && $row['actype2'] !== 'C') return false;
        if ($partyType === 'suppliers' && $row['actype2'] !== 'S') return false;
        if ($partyType === 'goldsmith' && $row['actype2'] !== 'G') return false;
        if ($partyType === 'jewelery' && $row['actype2'] !== 'J') return false;
        if ($partyType === 'refiners' && $row['actype2'] !== 'R') return false;
        if ($partyType === 'staffs' && $row['actype2'] !== 'F') return false;
        if ($partyType === 'kuri' && $row['kuri'] <= 0) return false;

        if ($filters['coCode'] !== '' && $row['cocode'] !== $filters['coCode']) return false;
        if ($filters['groupCode'] !== '' && strtoupper($row['grp']) !== $filters['groupCode']) return false;
        if ($filters['routeCode'] !== '' && strtoupper($row['route']) !== $filters['routeCode']) return false;
        if ($filters['areaCode'] !== '' && strtoupper($row['carea']) !== $filters['areaCode']) return false;
        if ($filters['selectedOnly'] && !$row['selected']) return false;
        if ($filters['minAmt'] > 0 && $row['balance_abs'] < $filters['minAmt']) return false;
        if ($filters['maxAmt'] > 0 && $row['balance_abs'] > $filters['maxAmt']) return false;
        if ($filters['nameSearch'] !== '' && !str_contains(strtoupper($row['address']), strtoupper($filters['nameSearch']))) return false;

        if ($filters['basedOnDueDate']) {
            if ($row['cduedate'] === '') return false;
            $due = $row['cduedate'];
            if ($due < $filters['date1'] || $due > $filters['date2']) return false;
        }

        return true;
    }

    private function compareRows(array $a, array $b, string $sort): int
    {
        return match ($sort) {
            'code' => strcmp($a['accode'], $b['accode']),
            'balance' => $a['balance_abs'] <=> $b['balance_abs'],
            'duedate' => strcmp($a['cduedate'], $b['cduedate']) ?: strcmp($a['name'], $b['name']),
            'lbdate' => strcmp($a['billdate'], $b['billdate']) ?: strcmp($a['name'], $b['name']),
            'lcdate' => strcmp($a['lcdate'], $b['lcdate']) ?: strcmp($a['name'], $b['name']),
            default => strcmp($a['name'], $b['name']),
        };
    }

    private function exportCsv(array $rows, bool $customerBalanceOnly = false): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $customerBalanceOnly) {
            $handle = fopen('php://output', 'w');
            if ($customerBalanceOnly) {
                fputcsv($handle, ['Code', 'Name', 'Mobile', 'Phone', 'Address', 'DueDate', 'Balance', 'Status']);
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row['accode'],
                        $row['name'],
                        $row['mobile'],
                        $row['telephone'],
                        $row['address_only'],
                        $row['cduedate'],
                        $row['balance_abs'],
                        $row['status'],
                    ]);
                }
                fclose($handle);
                return;
            }

            fputcsv($handle, ['Code', 'Name/Address', 'LBDate', 'LCDate', 'DueDate', 'Balance', 'Status', 'WgtBal']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['accode'],
                    $row['address'],
                    $row['billdate'],
                    $row['lcdate'],
                    $row['cduedate'],
                    $row['balance_abs'],
                    $row['status'],
                    $row['wgtbal'],
                ]);
            }
            fclose($handle);
        }, 'ac_receivable_payable_summary_' . date('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── Excel (XLSX) export ───────────────────────────────────────────────────

    private function exportExcel(array $rows, string $title, string $date1, string $date2, bool $customerBalanceOnly = false): StreamedResponse
    {
        $filename = 'ac_receivable_payable_' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($rows, $title, $date1, $date2, $customerBalanceOnly) {
            $tmp = tempnam(sys_get_temp_dir(), 'xlrp_');
            $zip = new \ZipArchive();
            $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            $zip->addFromString('[Content_Types].xml',        $this->xlContentTypes());
            $zip->addFromString('_rels/.rels',                $this->xlRels());
            $zip->addFromString('xl/workbook.xml',            $this->xlWorkbook());
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlWorkbookRels());
            $zip->addFromString('xl/styles.xml',              $this->xlStyles());
            $zip->addFromString('xl/worksheets/sheet1.xml',   $this->xlSheet($rows, $title, $date1, $date2, $customerBalanceOnly));
            $zip->close();

            echo file_get_contents($tmp);
            unlink($tmp);
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
            'Pragma'              => 'public',
        ]);
    }

    private function xlContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"           ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"  ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"             ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    }

    private function xlRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function xlWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Summary" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
    }

    private function xlWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"    Target="styles.xml"/>
</Relationships>';
    }

    private function xlStyles(): string
    {
        // numFmtId 164 = #,##0.00  165 = #,##0.000
        // xf index:
        //  0 = default
        //  1 = header (bold, dark blue bg, white text)
        //  2 = title  (bold, larger, merged)
        //  3 = TR row (light green bg)
        //  4 = TG row (light red bg)
        //  5 = totals (bold, yellow bg)
        //  6 = number 2dp
        //  7 = number 2dp TR
        //  8 = number 2dp TG
        //  9 = number 2dp totals bold
        // 10 = number 3dp (weight)
        // 11 = center text
        // 12 = date string
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="2">
    <numFmt numFmtId="164" formatCode="#,##0.00"/>
    <numFmt numFmtId="165" formatCode="#,##0.000"/>
  </numFmts>
  <fonts count="4">
    <font><sz val="10"/><name val="Calibri"/></font>
    <font><sz val="10"/><b/><name val="Calibri"/></font>
    <font><sz val="13"/><b/><name val="Calibri"/><color rgb="FF1A3A6B"/></font>
    <font><sz val="10"/><b/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
  </fonts>
  <fills count="7">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1A3A6B"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFE8F5E9"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFCE4EC"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFFFF9C4"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFECEFF1"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border><left style="thin"><color rgb="FFD0D0D0"/></left><right style="thin"><color rgb="FFD0D0D0"/></right><top style="thin"><color rgb="FFD0D0D0"/></top><bottom style="thin"><color rgb="FFD0D0D0"/></bottom><diagonal/></border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="13">
    <xf numFmtId="0"   fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0"   fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0"   fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="left" vertical="center"/></xf>
    <xf numFmtId="0"   fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>
    <xf numFmtId="0"   fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>
    <xf numFmtId="0"   fontId="1" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
    <xf numFmtId="164" fontId="0" fillId="6" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="164" fontId="0" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="164" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="164" fontId="1" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="165" fontId="0" fillId="6" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="0"   fontId="0" fillId="6" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment horizontal="center"/></xf>
    <xf numFmtId="0"   fontId="0" fillId="6" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>
  </cellXfs>
</styleSheet>';
    }

    private function xlCell(string $col, int $row, mixed $value, int $style = 0): string
    {
        $ref = $col . $row;
        if (is_numeric($value) && $value !== '') {
            return '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
        }
        $esc = htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t>' . $esc . '</t></is></c>';
    }

    private function xlSheet(array $rows, string $title, string $date1, string $date2, bool $customerBalanceOnly = false): string
    {
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $heads = $customerBalanceOnly
            ? ['Code', 'Name', 'Mobile', 'Phone', 'Address', 'Due Date', 'Balance', 'Status']
            : ['Code', 'Name / Address', 'LB Date', 'LC Date', 'Due Date', 'Balance (₹)', 'Status', 'Wgt Bal'];
        $widths = $customerBalanceOnly ? [12, 26, 16, 16, 36, 12, 18, 8] : [12, 38, 12, 12, 12, 18, 8, 12];
        $lastCol = 'H';
        $colCount = count($cols);

        $r = 1; // current row
        $xml = '';

        // Row 1: Title
        $xml .= '<row r="' . $r . '">';
        $xml .= $this->xlCell('A', $r, $title, 2);
        for ($i = 1; $i < $colCount; $i++) $xml .= '<c r="' . $cols[$i] . $r . '" s="2"/>';
        $xml .= '</row>';
        $r++;

        // Row 2: Date range info
        $xml .= '<row r="' . $r . '">';
        $xml .= $this->xlCell('A', $r, 'Period: ' . $date1 . ' to ' . $date2 . '  |  Generated: ' . date('d-m-Y H:i'), 0);
        $xml .= '</row>';
        $r++;

        // Row 3: blank
        $xml .= '<row r="' . $r . '"/>';
        $r++;

        // Row 4: Headers
        $xml .= '<row r="' . $r . '" ht="22" customHeight="1">';
        foreach ($heads as $i => $h) {
            $xml .= $this->xlCell($cols[$i], $r, $h, 1);
        }
        $xml .= '</row>';
        $r++;

        $totalTR = 0.0; $totalTG = 0.0; $count = 0;

        foreach ($rows as $row) {
            $isTR  = ($row['status'] === '(TR)');
            $isTG  = ($row['status'] === '(TG)');
            $txtSt = $isTR ? 3 : ($isTG ? 4 : 0);   // text style
            $numSt = $isTR ? 7 : ($isTG ? 8 : 6);   // number style

            $xml .= '<row r="' . $r . '">';
            $xml .= $this->xlCell('A', $r, $row['accode'],      $txtSt);
            if ($customerBalanceOnly) {
                $xml .= $this->xlCell('B', $r, $row['name'],         $txtSt);
                $xml .= $this->xlCell('C', $r, $row['mobile'],       $txtSt);
                $xml .= $this->xlCell('D', $r, $row['telephone'],    $txtSt);
                $xml .= $this->xlCell('E', $r, $row['address_only'], $txtSt);
                $xml .= $this->xlCell('F', $r, $row['cduedate'],     11);
                $xml .= $this->xlCell('G', $r, $row['balance_abs'],  $numSt);
                $xml .= $this->xlCell('H', $r, $row['status'],       11);
                $xml .= '</row>';

                if ($isTR) $totalTR += (float) $row['balance_abs'];
                if ($isTG) $totalTG += (float) $row['balance_abs'];
                $count++;
                $r++;
                continue;
            }
            $xml .= $this->xlCell('B', $r, $row['address'],     $txtSt);
            $xml .= $this->xlCell('C', $r, $row['billdate'],    11);
            $xml .= $this->xlCell('D', $r, $row['lcdate'],      11);
            $xml .= $this->xlCell('E', $r, $row['cduedate'],    11);
            $xml .= $this->xlCell('F', $r, $row['balance_abs'], $numSt);
            $xml .= $this->xlCell('G', $r, $row['status'],      11);
            $xml .= $this->xlCell('H', $r, $row['wgtbal'],      10);
            $xml .= '</row>';

            if ($isTR) $totalTR += (float) $row['balance_abs'];
            if ($isTG) $totalTG += (float) $row['balance_abs'];
            $count++;
            $r++;
        }

        // Totals row
        $xml .= '<row r="' . $r . '" ht="18" customHeight="1">';
        if ($customerBalanceOnly) {
            $xml .= $this->xlCell('A', $r, 'TOTAL (' . $count . ' customers)', 5);
            $xml .= '<c r="B' . $r . '" s="5"/>';
            $xml .= '<c r="C' . $r . '" s="5"/>';
            $xml .= '<c r="D' . $r . '" s="5"/>';
            $xml .= '<c r="E' . $r . '" s="5"/>';
            $xml .= '<c r="F' . $r . '" s="5"/>';
            $xml .= $this->xlCell('G', $r, round($totalTR + $totalTG, 2), 9);
            $xml .= '<c r="H' . $r . '" s="5"/>';
        } else {
            $xml .= $this->xlCell('A', $r, 'TOTAL (' . $count . ' accounts)', 5);
            $xml .= '<c r="B' . $r . '" s="5"/>';
            $xml .= '<c r="C' . $r . '" s="5"/>';
            $xml .= '<c r="D' . $r . '" s="5"/>';
            $xml .= $this->xlCell('E', $r, 'To Receive (TR):', 5);
            $xml .= $this->xlCell('F', $r, round($totalTR, 2), 9);
            $xml .= $this->xlCell('G', $r, 'To Give (TG):', 5);
            $xml .= $this->xlCell('H', $r, round($totalTG, 2), 9);
        }
        $xml .= '</row>';

        // Column widths
        $colDefs = '';
        foreach ($widths as $i => $w) {
            $colDefs .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetViews><sheetView workbookViewId="0" showGridLines="1"><selection activeCell="A1"/></sheetView></sheetViews>
  <sheetFormatPr defaultRowHeight="15"/>
  <cols>' . $colDefs . '</cols>
  <sheetData>' . $xml . '</sheetData>
  <mergeCells count="1"><mergeCell ref="A1:' . $lastCol . '1"/></mergeCells>
  <pageSetup orientation="landscape" fitToPage="1" fitToWidth="1" fitToHeight="0"/>
</worksheet>';
    }
}
