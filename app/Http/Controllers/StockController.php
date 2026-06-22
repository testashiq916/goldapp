<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        if (!$this->hasTable('items')) {
            return view('stock.opening-stock', [
                'items' => [],
                'filters' => $this->filters($request),
                'message' => 'Items table not found.',
                'stockTypes' => [],
                'groupCodes' => [],
            ]);
        }

        $filters = $this->filters($request);
        $itemsColumns = array_map('strtolower', $this->columnList('items'));
        $stockTypes = $this->stockTypeOptions();
        $groupCodes = $this->groupCodeOptions($itemsColumns);
        $items = DB::table('items')
            ->when(in_array('disabled', $itemsColumns, true), fn ($q) => $q->where(function ($qq) {
                $qq->where('disabled', '!=', 1)->orWhereNull('disabled');
            }))
            ->when($filters['grpcode'] !== '' && in_array('grpcode', $itemsColumns, true), fn ($q) => $q->where('grpcode', $filters['grpcode']))
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $s = '%' . $filters['search'] . '%';
                $q->where(function ($qq) use ($s) {
                    $qq->where('code', 'like', $s)->orWhere('name', 'like', $s);
                });
            })
            ->when($filters['itype'] !== 'All' && in_array('itype', $itemsColumns, true), fn ($q) => $q->where('itype', substr($filters['itype'], 0, 1)))
            ->orderBy('name')
            ->orderBy('code')
            ->limit(500)
            ->get()
            ->map(function ($row) use ($filters, $itemsColumns) {
                $code = (string) ($row->code ?? '');
                $touch = in_array('touch', $itemsColumns, true) ? (float) ($row->touch ?? 0) : 0.0;
                $current = $this->currentValues($code, $filters, $itemsColumns);

                return (object) [
                    'code' => $code,
                    'name' => (string) ($row->name ?? ''),
                    'touch' => $touch,
                    'cqty' => $current['qty'],
                    'cweight' => $current['weight'],
                    'cstwgt' => $current['stonewgt'],
                    'cstoneamt' => $current['stoneamt'],
                    'cdmdwgt' => $current['dmdwgt'],
                ];
            });

        return view('stock.opening-stock', [
            'items' => $items,
            'filters' => $filters,
            'message' => '',
            'stockTypes' => $stockTypes,
            'groupCodes' => $groupCodes,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'stktype' => 'nullable|string|max:10',
            'level' => 'required|integer',
            'stock_view' => 'required|in:opening,closing',
            'update_closing_also' => 'nullable|boolean',
            'same_stock' => 'nullable|boolean',
            'items' => 'required|array',
            'items.*.code' => 'required|string|max:20',
            'items.*.qty' => 'nullable|numeric',
            'items.*.weight' => 'nullable|numeric',
            'items.*.stonewgt' => 'nullable|numeric',
            'items.*.stoneamt' => 'nullable|numeric',
            'items.*.dmdwgt' => 'nullable|numeric',
        ]);

        if (!$this->hasTable('items')) {
            return response()->json(['success' => false, 'message' => 'Items table not found'], 500);
        }

        $itemsColumns = array_map('strtolower', $this->columnList('items'));
        $stockColumns = $this->hasTable('itemsstk')
            ? array_map('strtolower', $this->columnList('itemsstk'))
            : [];

        $stktype = strtoupper(trim((string) ($validated['stktype'] ?? '')));
        $level = (int) $validated['level'];
        $isOpening = $validated['stock_view'] === 'opening';
        $updateClosingAlso = (bool) ($validated['update_closing_also'] ?? false);
        $sameStock = (bool) ($validated['same_stock'] ?? false);

        DB::beginTransaction();
        try {
            foreach ($validated['items'] as $row) {
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                if ($code === '') {
                    continue;
                }

                $qty = (float) ($row['qty'] ?? 0);
                $weight = (float) ($row['weight'] ?? 0);
                $stonewgt = (float) ($row['stonewgt'] ?? 0);
                $stoneamt = (float) ($row['stoneamt'] ?? 0);
                $dmdwgt = (float) ($row['dmdwgt'] ?? 0);

                if ($stktype !== '' && $this->hasTable('itemsstk')) {
                    $this->saveTypedStock($code, $stktype, $level, $isOpening, $qty, $weight, $stonewgt, $stoneamt, $dmdwgt, $updateClosingAlso, $sameStock, $stockColumns);
                    $this->aggregateTypedStockToItems($code, $itemsColumns, $stockColumns);
                } else {
                    $this->saveDirectToItems($code, $level, $isOpening, $qty, $weight, $stonewgt, $stoneamt, $dmdwgt, $updateClosingAlso, $sameStock, $itemsColumns);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating stock: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function ledger(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        if (!$this->hasTable('items')) {
            return view('stock.ledger', [
                'rows' => [],
                'filters' => $this->filters($request),
                'message' => 'Items table not found.',
                'stockTypes' => [],
                'groupCodes' => [],
            ]);
        }

        $payload = $this->buildLedgerPayload($request);

        return view('stock.ledger', [
            'rows' => $payload['rows'],
            'filters' => $payload['filters'],
            'message' => '',
            'stockTypes' => $payload['stockTypes'],
            'groupCodes' => $payload['groupCodes'],
        ]);
    }

    public function stockList(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $itemsColumns = $this->hasTable('items')
            ? array_map('strtolower', $this->columnList('items'))
            : [];

        return view('stock.list', [
            'moduleId' => (string) $request->query('module', 'stock-list-items'),
            'title' => (string) $request->query('title', 'Stock List'),
            'stockTypes' => $this->stockTypeOptions(),
            'groupCodes' => $this->groupCodeOptions($itemsColumns),
        ]);
    }

    public function stockListData(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->blankStockListTotals(), 'groups' => [], 'stock_types' => []]);
        }

        $itemsColumns = array_map('strtolower', $this->columnList('items'));
        $grpCode = strtoupper(trim((string) $request->query('grpcode', '')));
        $filterByGroup = $request->boolean('filter_group', true);
        $metalType = (string) $request->query('metal_type', 'All');
        $ornamentFilter = (string) $request->query('ornament_filter', 'All');
        $stockFilter = (string) $request->query('stock_filter', 'With Not Zero Stock');
        $sortOnCode = $request->boolean('sort_on_code');
        $noWeight = $request->boolean('no_weight');
        $valueMode = strtolower(trim((string) $request->query('value_mode', 'rate')));
        $stktype = strtoupper(trim((string) $request->query('stktype', '')));

        $query = DB::table('items');
        $selectColumns = ['code', 'name'];
        foreach (['itype', 'grpcode', 'subgrpcode', 'ornament', 'orn', 'srate', 'prate', 'crate', 'cost', 'pos', 'disabled', 'showinstkrep'] as $column) {
            if (in_array($column, $itemsColumns, true)) {
                $selectColumns[] = $column;
            }
        }
        $query->select($selectColumns);

        if (in_array('disabled', $itemsColumns, true)) {
            $query->where(function ($inner) {
                $inner->where('disabled', '!=', 1)->orWhereNull('disabled');
            });
        }
        if (in_array('showinstkrep', $itemsColumns, true)) {
            $query->where(function ($inner) {
                $inner->where('showinstkrep', '!=', 'N')->orWhereNull('showinstkrep');
            });
        }
        if ($metalType !== 'All' && in_array('itype', $itemsColumns, true)) {
            $query->where('itype', substr($metalType, 0, 1));
        }

        $ornamentColumn = in_array('ornament', $itemsColumns, true) ? 'ornament' : (in_array('orn', $itemsColumns, true) ? 'orn' : null);
        if ($ornamentColumn !== null) {
            if ($ornamentFilter === 'Ornaments Only') {
                $query->where($ornamentColumn, 'Y');
            } elseif ($ornamentFilter === 'Not Ornaments') {
                $query->where(function ($inner) use ($ornamentColumn) {
                    $inner->where($ornamentColumn, '!=', 'Y')->orWhereNull($ornamentColumn);
                });
            }
        }

        if ($filterByGroup && $grpCode !== '' && in_array('grpcode', $itemsColumns, true)) {
            $query->where('grpcode', $grpCode);
        }

        if ($sortOnCode) {
            $query->orderBy('code')->orderBy('name');
        } else {
            if (in_array('pos', $itemsColumns, true)) {
                $query->orderBy('pos');
            }
            $query->orderBy('name')->orderBy('code');
        }

        $baseFilters = [
            'stktype' => $stktype,
            'stock_view' => 'closing',
            'level' => 1,
        ];
        $openingFilters = [
            'stktype' => $stktype,
            'stock_view' => 'opening',
            'level' => 1,
        ];

        $rows = $query->get()->map(function ($row) use ($baseFilters, $openingFilters, $itemsColumns, $valueMode, $stockFilter, $noWeight, $ornamentColumn) {
            $code = (string) ($row->code ?? '');
            $current = $this->currentValues($code, $baseFilters, $itemsColumns);
            $opening = $this->currentValues($code, $openingFilters, $itemsColumns);

            $weight = (float) ($current['weight'] ?? 0);
            $qty = (float) ($current['qty'] ?? 0);
            $stonewgt = (float) ($current['stonewgt'] ?? 0);
            $netwgt = $weight - $stonewgt;
            $opweight = (float) ($opening['weight'] ?? 0);
            $opqty = (float) ($opening['qty'] ?? 0);

            if (!$this->passesStockListFilter($qty, $weight, $stockFilter, $noWeight)) {
                return null;
            }

            $rate = $valueMode === 'cost'
                ? (float) ($row->cost ?? $row->crate ?? 0)
                : (float) ($row->crate ?? $row->cost ?? 0);
            if ($rate == 0.0) {
                $rate = (float) ($row->srate ?? 0);
            }
            $valueBase = $noWeight ? $qty : $netwgt;

            return [
                'code' => $code,
                'name' => (string) ($row->name ?? ''),
                'grpcode' => (string) ($row->grpcode ?? ''),
                'subgrpcode' => (string) ($row->subgrpcode ?? ''),
                'itype' => (string) ($row->itype ?? ''),
                'ornament' => $ornamentColumn !== null ? (string) ($row->{$ornamentColumn} ?? '') : '',
                'opqty' => $opqty,
                'opweight' => $opweight,
                'qty' => $qty,
                'weight' => $weight,
                'stonewgt' => $stonewgt,
                'netwgt' => $netwgt,
                'srate' => (float) ($row->srate ?? 0),
                'prate' => (float) ($row->prate ?? 0),
                'crate' => (float) ($row->crate ?? $row->cost ?? 0),
                'rate' => $rate,
                'value' => round($valueBase * $rate, 2),
            ];
        })->filter()->values()->all();

        $totals = $this->blankStockListTotals();
        foreach ($rows as $row) {
            $totals['opqty'] += (float) $row['opqty'];
            $totals['opweight'] += (float) $row['opweight'];
            $totals['qty'] += (float) $row['qty'];
            $totals['weight'] += (float) $row['weight'];
            $totals['stonewgt'] += (float) $row['stonewgt'];
            $totals['netwgt'] += (float) $row['netwgt'];
            $totals['value'] += (float) $row['value'];
            if (($row['itype'] ?? '') === 'G') {
                $totals['gold_qty'] += (float) $row['qty'];
                $totals['gold_weight'] += (float) $row['weight'];
            } elseif (($row['itype'] ?? '') === 'S') {
                $totals['silver_qty'] += (float) $row['qty'];
                $totals['silver_weight'] += (float) $row['weight'];
            } else {
                $totals['other_qty'] += (float) $row['qty'];
                $totals['other_weight'] += (float) $row['weight'];
            }
        }

        return response()->json([
            'ok' => true,
            'rows' => $rows,
            'totals' => $totals,
            'groups' => $this->groupCodeOptions($itemsColumns),
            'stock_types' => $this->stockTypeOptions(),
        ]);
    }

    public function ledgerExport(Request $request): StreamedResponse|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        if (!$this->hasTable('items')) {
            return redirect('/stock/ledger');
        }

        $payload = $this->buildLedgerPayload($request);
        $rows = $payload['rows'];
        $filters = $payload['filters'];
        $filename = 'stock_ledger_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows, $filters): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['Stock Ledger']);
            fputcsv($out, ['Basis', ucfirst((string) $filters['basis'])]);
            fputcsv($out, ['View', ucfirst((string) $filters['stock_view'])]);
            fputcsv($out, ['Level', (int) $filters['level'] === 1 ? '1' : 'B']);
            fputcsv($out, ['Stock Type', (string) $filters['stktype']]);
            fputcsv($out, []);
            fputcsv($out, ['RowType', 'Code', 'Name', 'Group', 'SubGroup', 'Qty', 'Weight', 'StoneWgt', 'DmdWgt', 'NetWgt']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['row_type'],
                    $row['code'],
                    $row['name'],
                    $row['grpcode'],
                    $row['subgrpcode'],
                    number_format((float) $row['qty'], 3, '.', ''),
                    number_format((float) $row['weight'], 3, '.', ''),
                    number_format((float) $row['stonewgt'], 3, '.', ''),
                    number_format((float) $row['dmdwgt'], 3, '.', ''),
                    number_format((float) $row['netwgt'], 3, '.', ''),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function currentValues(string $code, array $filters, array $itemsColumns): array
    {
        $level = (int) $filters['level'];
        $isOpening = $filters['stock_view'] === 'opening';
        $stktype = $filters['stktype'];
        $base = ['qty' => 0.0, 'weight' => 0.0, 'stonewgt' => 0.0, 'stoneamt' => 0.0, 'dmdwgt' => 0.0];

        if ($stktype !== '' && $this->hasTable('itemsstk')) {
            $stockColumns = array_map('strtolower', $this->columnList('itemsstk'));
            $qtyCol = $isOpening ? ($level === 1 ? 'opqty' : 'opqtyb') : ($level === 1 ? 'qty' : 'qtyb');
            $wgtCol = $isOpening ? ($level === 1 ? 'opweight' : 'opweightb') : ($level === 1 ? 'weight' : 'weightb');
            $stCol = $isOpening ? ($level === 1 ? 'opstonewgt' : 'opstonewgtb') : ($level === 1 ? 'stonewgt' : 'stonewgtb');
            $amtCol = $isOpening ? ($level === 1 ? 'opstoneamt' : 'opstoneamtb') : '';
            $dmdCol = $isOpening ? 'opdmdwgt' : '';

            $q = DB::table('itemsstk')->where('code', $code)->where('stktype', $stktype);
            $base['qty'] = in_array($qtyCol, $stockColumns, true) ? (float) ($q->sum($qtyCol) ?? 0) : 0.0;
            $base['weight'] = in_array($wgtCol, $stockColumns, true) ? (float) (DB::table('itemsstk')->where('code', $code)->where('stktype', $stktype)->sum($wgtCol) ?? 0) : 0.0;
            $base['stonewgt'] = in_array($stCol, $stockColumns, true) ? (float) (DB::table('itemsstk')->where('code', $code)->where('stktype', $stktype)->sum($stCol) ?? 0) : 0.0;
            $base['stoneamt'] = ($amtCol !== '' && in_array($amtCol, $stockColumns, true)) ? (float) (DB::table('itemsstk')->where('code', $code)->where('stktype', $stktype)->sum($amtCol) ?? 0) : 0.0;
            $base['dmdwgt'] = ($dmdCol !== '' && in_array($dmdCol, $stockColumns, true)) ? (float) (DB::table('itemsstk')->where('code', $code)->where('stktype', $stktype)->sum($dmdCol) ?? 0) : 0.0;

            return $base;
        }

        $qtyCol = $isOpening ? ($level === 1 ? 'opqty' : 'opqtyb') : ($level === 1 ? 'qty' : 'qtyb');
        $wgtCol = $isOpening ? ($level === 1 ? 'opweight' : 'opweightb') : ($level === 1 ? 'weight' : 'weightb');
        $stCol = $isOpening ? ($level === 1 ? 'opstonewgt' : 'opstonewgtb') : ($level === 1 ? 'stonewgt' : 'stonewgtb');
        $amtCol = $isOpening ? ($level === 1 ? 'opstoneamt' : 'opstoneamtb') : '';
        $dmdCol = $isOpening ? 'opdmdwgt' : '';

        $row = DB::table('items')->select('*')->where('code', $code)->first();
        $base['qty'] = in_array($qtyCol, $itemsColumns, true) ? (float) ($row->{$qtyCol} ?? 0) : 0.0;
        $base['weight'] = in_array($wgtCol, $itemsColumns, true) ? (float) ($row->{$wgtCol} ?? 0) : 0.0;
        $base['stonewgt'] = in_array($stCol, $itemsColumns, true) ? (float) ($row->{$stCol} ?? 0) : 0.0;
        $base['stoneamt'] = ($amtCol !== '' && in_array($amtCol, $itemsColumns, true)) ? (float) ($row->{$amtCol} ?? 0) : 0.0;
        $base['dmdwgt'] = ($dmdCol !== '' && in_array($dmdCol, $itemsColumns, true)) ? (float) ($row->{$dmdCol} ?? 0) : 0.0;

        // Some datasets keep authoritative stock detail in itemsstk while the
        // aggregate opening columns on items stay empty. Fall back to the
        // typed totals when no specific stock type is selected so old opening
        // rows do not disappear from the stock screens.
        if ($this->hasTable('itemsstk')) {
            $stockColumns = array_map('strtolower', $this->columnList('itemsstk'));
            $typedQty = in_array($qtyCol, $stockColumns, true) ? (float) (DB::table('itemsstk')->where('code', $code)->sum($qtyCol) ?? 0) : 0.0;
            $typedWeight = in_array($wgtCol, $stockColumns, true) ? (float) (DB::table('itemsstk')->where('code', $code)->sum($wgtCol) ?? 0) : 0.0;
            $typedStoneWgt = in_array($stCol, $stockColumns, true) ? (float) (DB::table('itemsstk')->where('code', $code)->sum($stCol) ?? 0) : 0.0;
            $typedStoneAmt = ($amtCol !== '' && in_array($amtCol, $stockColumns, true)) ? (float) (DB::table('itemsstk')->where('code', $code)->sum($amtCol) ?? 0) : 0.0;
            $typedDmdWgt = ($dmdCol !== '' && in_array($dmdCol, $stockColumns, true)) ? (float) (DB::table('itemsstk')->where('code', $code)->sum($dmdCol) ?? 0) : 0.0;

            if (
                abs($base['qty']) < 0.0001 &&
                abs($base['weight']) < 0.0001 &&
                abs($base['stonewgt']) < 0.0001 &&
                abs($base['stoneamt']) < 0.0001 &&
                abs($base['dmdwgt']) < 0.0001 &&
                (
                    abs($typedQty) >= 0.0001 ||
                    abs($typedWeight) >= 0.0001 ||
                    abs($typedStoneWgt) >= 0.0001 ||
                    abs($typedStoneAmt) >= 0.0001 ||
                    abs($typedDmdWgt) >= 0.0001
                )
            ) {
                $base['qty'] = $typedQty;
                $base['weight'] = $typedWeight;
                $base['stonewgt'] = $typedStoneWgt;
                $base['stoneamt'] = $typedStoneAmt;
                $base['dmdwgt'] = $typedDmdWgt;
            }
        }

        return $base;
    }

    private function passesStockListFilter(float $qty, float $weight, string $stockFilter, bool $noWeight): bool
    {
        $measure = $noWeight ? $qty : $weight;
        return match ($stockFilter) {
            'With Stock Only' => $measure > 0,
            'With -ve Stock' => $measure < 0,
            'With Zero Stock' => abs($measure) < 0.0001,
            'With Not Zero Stock' => abs($measure) >= 0.0001,
            default => true,
        };
    }

    private function blankStockListTotals(): array
    {
        return [
            'opqty' => 0.0,
            'opweight' => 0.0,
            'qty' => 0.0,
            'weight' => 0.0,
            'stonewgt' => 0.0,
            'netwgt' => 0.0,
            'value' => 0.0,
            'gold_qty' => 0.0,
            'gold_weight' => 0.0,
            'silver_qty' => 0.0,
            'silver_weight' => 0.0,
            'other_qty' => 0.0,
            'other_weight' => 0.0,
        ];
    }

    private function buildLedgerPayload(Request $request): array
    {
        $filters = $this->filters($request);
        $itemsColumns = array_map('strtolower', $this->columnList('items'));

        $stockTypes = $this->stockTypeOptions();
        $groupCodes = $this->groupCodeOptions($itemsColumns);
        $items = $this->loadLedgerItems($filters, $itemsColumns);
        $rows = $this->shapeLedgerRows($items, $filters['basis'], (bool) $filters['include_child']);

        return [
            'rows' => $rows,
            'filters' => $filters,
            'stockTypes' => $stockTypes,
            'groupCodes' => $groupCodes,
        ];
    }

    private function loadLedgerItems(array $filters, array $itemsColumns): array
    {
        $select = ['code', 'name'];
        foreach (['grpcode', 'subgrpcode', 'itype', 'ornament', 'orn'] as $col) {
            if (in_array($col, $itemsColumns, true)) {
                $select[] = $col;
            }
        }

        $groupNames = [];
        if ($this->hasTable('itemgrp')) {
            $groupNames = DB::table('itemgrp')
                ->select('code', 'name')
                ->whereNotNull('code')
                ->get()
                ->mapWithKeys(fn ($r) => [strtoupper(trim((string) $r->code)) => (string) ($r->name ?? '')])
                ->all();
        }

        $subgroupNames = [];
        if ($this->hasTable('itemsubgrp')) {
            $subgroupNames = DB::table('itemsubgrp')
                ->select('code', 'name')
                ->whereNotNull('code')
                ->get()
                ->mapWithKeys(fn ($r) => [strtoupper(trim((string) $r->code)) => (string) ($r->name ?? '')])
                ->all();
        }

        $ornamentColumn = in_array('ornament', $itemsColumns, true) ? 'ornament' : (in_array('orn', $itemsColumns, true) ? 'orn' : null);

        return DB::table('items')
            ->select($select)
            ->when(in_array('disabled', $itemsColumns, true), fn ($q) => $q->where(function ($qq) {
                $qq->where('disabled', '!=', 1)->orWhereNull('disabled');
            }))
            ->when(in_array('showinstkrep', $itemsColumns, true), fn ($q) => $q->where(function ($qq) {
                $qq->where('showinstkrep', '!=', 'N')->orWhereNull('showinstkrep');
            }))
            ->when($filters['grpcode'] !== '' && in_array('grpcode', $itemsColumns, true), fn ($q) => $q->where('grpcode', $filters['grpcode']))
            ->when($ornamentColumn !== null && $filters['ornament_filter'] === 'Ornaments Only', function ($q) use ($ornamentColumn): void {
                $q->whereRaw("UPPER(TRIM(COALESCE({$ornamentColumn},''))) IN ('Y','YES','1','TRUE')");
            })
            ->when($ornamentColumn !== null && $filters['ornament_filter'] === 'Not Ornaments', function ($q) use ($ornamentColumn): void {
                $q->where(function ($inner) use ($ornamentColumn): void {
                    $inner->whereRaw("UPPER(TRIM(COALESCE({$ornamentColumn},''))) NOT IN ('Y','YES','1','TRUE')")
                        ->orWhereNull($ornamentColumn);
                });
            })
            ->when($filters['search'] !== '', function ($q) use ($filters): void {
                $s = '%' . $filters['search'] . '%';
                $q->where(function ($qq) use ($s): void {
                    $qq->where('code', 'like', $s)->orWhere('name', 'like', $s);
                });
            })
            ->when($filters['itype'] !== 'All' && in_array('itype', $itemsColumns, true), fn ($q) => $q->where('itype', substr($filters['itype'], 0, 1)))
            ->orderBy('grpcode')
            ->orderBy('subgrpcode')
            ->orderBy('name')
            ->orderBy('code')
            ->limit(5000)
            ->get()
            ->map(function ($row) use ($filters, $itemsColumns, $groupNames, $subgroupNames) {
                $code = strtoupper(trim((string) ($row->code ?? '')));
                $current = $this->currentValues($code, $filters, $itemsColumns);
                $grpCode = strtoupper(trim((string) ($row->grpcode ?? '')));
                $subgrpCode = strtoupper(trim((string) ($row->subgrpcode ?? '')));

                return [
                    'code' => $code,
                    'name' => (string) ($row->name ?? ''),
                    'grpcode' => $grpCode,
                    'grpname' => $groupNames[$grpCode] ?? '',
                    'subgrpcode' => $subgrpCode,
                    'subgrpname' => $subgroupNames[$subgrpCode] ?? '',
                    'qty' => (float) ($current['qty'] ?? 0),
                    'weight' => (float) ($current['weight'] ?? 0),
                    'stonewgt' => (float) ($current['stonewgt'] ?? 0),
                    'dmdwgt' => (float) ($current['dmdwgt'] ?? 0),
                    'netwgt' => (float) (($current['weight'] ?? 0) - ($current['stonewgt'] ?? 0)),
                ];
            })
            ->all();
    }

    private function shapeLedgerRows(array $items, string $basis, bool $includeChild): array
    {
        if ($basis === 'item') {
            return array_map(function (array $r): array {
                $r['row_type'] = 'item';
                return $r;
            }, $items);
        }

        $groupBy = $basis === 'subgroup' ? 'subgrpcode' : 'grpcode';
        $nameBy = $basis === 'subgroup' ? 'subgrpname' : 'grpname';
        $bucket = [];

        foreach ($items as $r) {
            $code = trim((string) ($r[$groupBy] ?? ''));
            if ($code === '') {
                $code = 'UNSPECIFIED';
            }

            if (!isset($bucket[$code])) {
                $bucket[$code] = [
                    'row_type' => 'parent',
                    'code' => $code,
                    'name' => trim((string) ($r[$nameBy] ?? '')) !== '' ? (string) $r[$nameBy] : $code,
                    'grpcode' => $basis === 'group' ? $code : (string) ($r['grpcode'] ?? ''),
                    'subgrpcode' => $basis === 'subgroup' ? $code : '',
                    'qty' => 0.0,
                    'weight' => 0.0,
                    'stonewgt' => 0.0,
                    'dmdwgt' => 0.0,
                    'netwgt' => 0.0,
                    'children' => [],
                ];
            }

            $bucket[$code]['qty'] += (float) ($r['qty'] ?? 0);
            $bucket[$code]['weight'] += (float) ($r['weight'] ?? 0);
            $bucket[$code]['stonewgt'] += (float) ($r['stonewgt'] ?? 0);
            $bucket[$code]['dmdwgt'] += (float) ($r['dmdwgt'] ?? 0);
            $bucket[$code]['netwgt'] += (float) ($r['netwgt'] ?? 0);

            if ($includeChild) {
                $child = $r;
                $child['row_type'] = 'child';
                $bucket[$code]['children'][] = $child;
            }
        }

        ksort($bucket);
        $rows = [];
        foreach ($bucket as $row) {
            $children = $row['children'];
            unset($row['children']);
            $rows[] = $row;
            foreach ($children as $child) {
                $rows[] = $child;
            }
        }

        return $rows;
    }

    private function saveTypedStock(string $code, string $stktype, int $level, bool $isOpening, float $qty, float $weight, float $stonewgt, float $stoneamt, float $dmdwgt, bool $updateClosingAlso, bool $sameStock, array $stockColumns): void
    {
        $where = ['code' => $code, 'stktype' => $stktype];
        $updates = [];

        if ($isOpening) {
            if ($level === 1) {
                $this->setIfColumn($updates, $stockColumns, 'opqty', $qty);
                $this->setIfColumn($updates, $stockColumns, 'opweight', $weight);
                $this->setIfColumn($updates, $stockColumns, 'opstonewgt', $stonewgt);
                $this->setIfColumn($updates, $stockColumns, 'opstoneamt', $stoneamt);
                $this->setIfColumn($updates, $stockColumns, 'opdmdwgt', $dmdwgt);
                if ($sameStock) {
                    $this->setIfColumn($updates, $stockColumns, 'opqtyb', $qty);
                    $this->setIfColumn($updates, $stockColumns, 'opweightb', $weight);
                    $this->setIfColumn($updates, $stockColumns, 'opstonewgtb', $stonewgt);
                    $this->setIfColumn($updates, $stockColumns, 'opstoneamtb', $stoneamt);
                }
                if ($updateClosingAlso) {
                    $this->setIfColumn($updates, $stockColumns, 'qty', $qty);
                    $this->setIfColumn($updates, $stockColumns, 'weight', $weight);
                    $this->setIfColumn($updates, $stockColumns, 'stonewgt', $stonewgt);
                    if ($sameStock) {
                        $this->setIfColumn($updates, $stockColumns, 'qtyb', $qty);
                        $this->setIfColumn($updates, $stockColumns, 'weightb', $weight);
                        $this->setIfColumn($updates, $stockColumns, 'stonewgtb', $stonewgt);
                    }
                }
            } else {
                $this->setIfColumn($updates, $stockColumns, 'opqtyb', $qty);
                $this->setIfColumn($updates, $stockColumns, 'opweightb', $weight);
                $this->setIfColumn($updates, $stockColumns, 'opstonewgtb', $stonewgt);
                $this->setIfColumn($updates, $stockColumns, 'opstoneamtb', $stoneamt);
                $this->setIfColumn($updates, $stockColumns, 'opdmdwgt', $dmdwgt);
                if ($sameStock) {
                    $this->setIfColumn($updates, $stockColumns, 'opqty', $qty);
                    $this->setIfColumn($updates, $stockColumns, 'opweight', $weight);
                    $this->setIfColumn($updates, $stockColumns, 'opstonewgt', $stonewgt);
                    $this->setIfColumn($updates, $stockColumns, 'opstoneamt', $stoneamt);
                }
                if ($updateClosingAlso) {
                    $this->setIfColumn($updates, $stockColumns, 'qtyb', $qty);
                    $this->setIfColumn($updates, $stockColumns, 'weightb', $weight);
                    $this->setIfColumn($updates, $stockColumns, 'stonewgtb', $stonewgt);
                    if ($sameStock) {
                        $this->setIfColumn($updates, $stockColumns, 'qty', $qty);
                        $this->setIfColumn($updates, $stockColumns, 'weight', $weight);
                        $this->setIfColumn($updates, $stockColumns, 'stonewgt', $stonewgt);
                    }
                }
            }
        } else {
            if ($level === 1) {
                $this->setIfColumn($updates, $stockColumns, 'qty', $qty);
                $this->setIfColumn($updates, $stockColumns, 'weight', $weight);
                $this->setIfColumn($updates, $stockColumns, 'stonewgt', $stonewgt);
                if ($sameStock) {
                    $this->setIfColumn($updates, $stockColumns, 'qtyb', $qty);
                    $this->setIfColumn($updates, $stockColumns, 'weightb', $weight);
                    $this->setIfColumn($updates, $stockColumns, 'stonewgtb', $stonewgt);
                }
            } else {
                $this->setIfColumn($updates, $stockColumns, 'qtyb', $qty);
                $this->setIfColumn($updates, $stockColumns, 'weightb', $weight);
                $this->setIfColumn($updates, $stockColumns, 'stonewgtb', $stonewgt);
                if ($sameStock) {
                    $this->setIfColumn($updates, $stockColumns, 'qty', $qty);
                    $this->setIfColumn($updates, $stockColumns, 'weight', $weight);
                    $this->setIfColumn($updates, $stockColumns, 'stonewgt', $stonewgt);
                }
            }
        }

        DB::table('itemsstk')->updateOrInsert($where, $updates);
    }

    private function aggregateTypedStockToItems(string $code, array $itemsColumns, array $stockColumns): void
    {
        $update = [];
        foreach (['qty', 'qtyb', 'weight', 'weightb', 'stonewgt', 'stonewgtb', 'opqty', 'opqtyb', 'opweight', 'opweightb', 'opstonewgt', 'opstonewgtb', 'opstoneamt', 'opstoneamtb', 'opdmdwgt'] as $col) {
            if (!in_array($col, $itemsColumns, true) || !in_array($col, $stockColumns, true)) {
                continue;
            }
            $sum = DB::table('itemsstk')->where('code', $code)->sum($col);
            $update[$col] = (float) ($sum ?? 0);
        }

        if ($update !== []) {
            DB::table('items')->where('code', $code)->update($update);
        }
    }

    private function saveDirectToItems(string $code, int $level, bool $isOpening, float $qty, float $weight, float $stonewgt, float $stoneamt, float $dmdwgt, bool $updateClosingAlso, bool $sameStock, array $itemsColumns): void
    {
        $update = [];

        if ($isOpening) {
            if ($level === 1) {
                $this->setIfColumn($update, $itemsColumns, 'opqty', $qty);
                $this->setIfColumn($update, $itemsColumns, 'opweight', $weight);
                $this->setIfColumn($update, $itemsColumns, 'opstonewgt', $stonewgt);
                $this->setIfColumn($update, $itemsColumns, 'opstoneamt', $stoneamt);
                $this->setIfColumn($update, $itemsColumns, 'opdmdwgt', $dmdwgt);
                if ($sameStock) {
                    $this->setIfColumn($update, $itemsColumns, 'opqtyb', $qty);
                    $this->setIfColumn($update, $itemsColumns, 'opweightb', $weight);
                    $this->setIfColumn($update, $itemsColumns, 'opstonewgtb', $stonewgt);
                    $this->setIfColumn($update, $itemsColumns, 'opstoneamtb', $stoneamt);
                }
                if ($updateClosingAlso) {
                    $this->setIfColumn($update, $itemsColumns, 'qty', $qty);
                    $this->setIfColumn($update, $itemsColumns, 'weight', $weight);
                    $this->setIfColumn($update, $itemsColumns, 'stonewgt', $stonewgt);
                    if ($sameStock) {
                        $this->setIfColumn($update, $itemsColumns, 'qtyb', $qty);
                        $this->setIfColumn($update, $itemsColumns, 'weightb', $weight);
                        $this->setIfColumn($update, $itemsColumns, 'stonewgtb', $stonewgt);
                    }
                }
            } else {
                $this->setIfColumn($update, $itemsColumns, 'opqtyb', $qty);
                $this->setIfColumn($update, $itemsColumns, 'opweightb', $weight);
                $this->setIfColumn($update, $itemsColumns, 'opstonewgtb', $stonewgt);
                $this->setIfColumn($update, $itemsColumns, 'opstoneamtb', $stoneamt);
                $this->setIfColumn($update, $itemsColumns, 'opdmdwgt', $dmdwgt);
                if ($sameStock) {
                    $this->setIfColumn($update, $itemsColumns, 'opqty', $qty);
                    $this->setIfColumn($update, $itemsColumns, 'opweight', $weight);
                    $this->setIfColumn($update, $itemsColumns, 'opstonewgt', $stonewgt);
                    $this->setIfColumn($update, $itemsColumns, 'opstoneamt', $stoneamt);
                }
                if ($updateClosingAlso) {
                    $this->setIfColumn($update, $itemsColumns, 'qtyb', $qty);
                    $this->setIfColumn($update, $itemsColumns, 'weightb', $weight);
                    $this->setIfColumn($update, $itemsColumns, 'stonewgtb', $stonewgt);
                    if ($sameStock) {
                        $this->setIfColumn($update, $itemsColumns, 'qty', $qty);
                        $this->setIfColumn($update, $itemsColumns, 'weight', $weight);
                        $this->setIfColumn($update, $itemsColumns, 'stonewgt', $stonewgt);
                    }
                }
            }
        } else {
            if ($level === 1) {
                $this->setIfColumn($update, $itemsColumns, 'qty', $qty);
                $this->setIfColumn($update, $itemsColumns, 'weight', $weight);
                $this->setIfColumn($update, $itemsColumns, 'stonewgt', $stonewgt);
                if ($sameStock) {
                    $this->setIfColumn($update, $itemsColumns, 'qtyb', $qty);
                    $this->setIfColumn($update, $itemsColumns, 'weightb', $weight);
                    $this->setIfColumn($update, $itemsColumns, 'stonewgtb', $stonewgt);
                }
            } else {
                $this->setIfColumn($update, $itemsColumns, 'qtyb', $qty);
                $this->setIfColumn($update, $itemsColumns, 'weightb', $weight);
                $this->setIfColumn($update, $itemsColumns, 'stonewgtb', $stonewgt);
                if ($sameStock) {
                    $this->setIfColumn($update, $itemsColumns, 'qty', $qty);
                    $this->setIfColumn($update, $itemsColumns, 'weight', $weight);
                    $this->setIfColumn($update, $itemsColumns, 'stonewgt', $stonewgt);
                }
            }
        }

        if ($update !== []) {
            DB::table('items')->where('code', $code)->update($update);
        }
    }

    private function setIfColumn(array &$update, array $available, string $column, float $value): void
    {
        if (in_array($column, $available, true)) {
            $update[$column] = $value;
        }
    }

    private function filters(Request $request): array
    {
        $basis = strtolower(trim((string) $request->input('basis', 'item')));
        if (!in_array($basis, ['item', 'group', 'subgroup'], true)) {
            $basis = 'item';
        }

        return [
            'stktype' => strtoupper(trim((string) $request->input('stktype', ''))),
            'grpcode' => strtoupper(trim((string) $request->input('grpcode', ''))),
            'itype' => (string) $request->input('itype', 'All'),
            'ornament_filter' => (string) $request->input('ornament_filter', 'All'),
            'stock_view' => (string) $request->input('stock_view', 'opening'),
            'level' => (int) $request->input('level', (int) config('stock.default_level', 1)),
            'search' => trim((string) $request->input('search', '')),
            'basis' => $basis,
            'include_child' => $request->boolean('include_child', true),
        ];
    }

    private function stockTypeOptions(): array
    {
        $options = [];

        if ($this->hasTable('stocktypes')) {
            $cols = array_map('strtolower', $this->columnList('stocktypes'));
            $codeCol = in_array('code', $cols, true) ? 'code' : (in_array('stktype', $cols, true) ? 'stktype' : null);
            if ($codeCol !== null) {
                $options = DB::table('stocktypes')
                    ->whereNotNull($codeCol)
                    ->where($codeCol, '!=', '')
                    ->orderBy($codeCol)
                    ->pluck($codeCol)
                    ->map(fn ($v) => strtoupper(trim((string) $v)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        if ($options === [] && $this->hasTable('stktype')) {
            $cols = array_map('strtolower', $this->columnList('stktype'));
            $codeCol = in_array('code', $cols, true) ? 'code' : (in_array('stktype', $cols, true) ? 'stktype' : null);
            if ($codeCol !== null) {
                $options = DB::table('stktype')
                    ->whereNotNull($codeCol)
                    ->where($codeCol, '!=', '')
                    ->orderBy($codeCol)
                    ->pluck($codeCol)
                    ->map(fn ($v) => strtoupper(trim((string) $v)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        if ($options === [] && $this->hasTable('itemsstk')) {
            $cols = array_map('strtolower', $this->columnList('itemsstk'));
            if (in_array('stktype', $cols, true)) {
                $options = DB::table('itemsstk')
                    ->whereNotNull('stktype')
                    ->where('stktype', '!=', '')
                    ->orderBy('stktype')
                    ->pluck('stktype')
                    ->map(fn ($v) => strtoupper(trim((string) $v)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return $options;
    }

    private function groupCodeOptions(array $itemsColumns): array
    {
        if (!in_array('grpcode', $itemsColumns, true)) {
            return [];
        }

        return DB::table('items')
            ->whereNotNull('grpcode')
            ->where('grpcode', '!=', '')
            ->orderBy('grpcode')
            ->pluck('grpcode')
            ->map(fn ($v) => strtoupper(trim((string) $v)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isAuthorized(Request $request): bool
    {
        if ((string) ($request->session()->get('user_code') ?? '') !== '') {
            return true;
        }

        $legacySessionId = preg_replace('/[^a-zA-Z0-9,-]/', '', (string) ($_COOKIE['PHPSESSID'] ?? ''));
        if ($legacySessionId === '') {
            return false;
        }

        $savePath = (string) ini_get('session.save_path');
        if ($savePath === '') {
            return false;
        }
        if (str_contains($savePath, ';')) {
            $parts = explode(';', $savePath);
            $savePath = (string) end($parts);
        }
        $savePath = trim($savePath);
        if ($savePath === '') {
            return false;
        }

        $sessionFile = rtrim($savePath, "/\\") . DIRECTORY_SEPARATOR . 'sess_' . $legacySessionId;
        if (!is_file($sessionFile) || !is_readable($sessionFile)) {
            return false;
        }

        $raw = (string) @file_get_contents($sessionFile);
        return $raw !== '' && str_contains($raw, 'user_code|');
    }
}
