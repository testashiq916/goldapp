<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class YearEndAccountCloseController extends Controller
{
    use LogsDelpartAudit;

    private array $columnCache = [];

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('year-end-account-close.index', [
            'payload' => [
                'today' => now()->format('Y-m-d'),
                'keep_bills_default' => true,
                'sections' => $this->sections(),
            ],
        ]);
    }

    public function initDocNos(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        DB::beginTransaction();
        try {
            if ($this->tableCount('salesm') === 0) {
                foreach (['SALESE', 'SALESB', 'SOPBILLNO'] as $code) {
                    $this->upsertGeneralInt($code, 0);
                }
            }

            if ($this->tableCount('purchasem') === 0) {
                foreach (['PURCHASEE', 'PURCHASEB'] as $code) {
                    $this->upsertGeneralInt($code, 0);
                }
            }

            if ($this->tableCount('orderm') === 0) {
                $this->upsertGeneralInt('ORDERB', (int) $this->generalValue('generals', 'ORDBSTART', '0'));
                $this->upsertGeneralInt('ORDERE', (int) $this->generalValue('generals', 'ORDESTART', '0'));
            }

            if ($this->tableCount('repairm') === 0) {
                foreach (['REPAIRE', 'REPAIRB'] as $code) {
                    $this->upsertGeneralInt($code, 0);
                }
            }

            if ($this->tableCount('refinerym') === 0) {
                foreach (['REFINERE', 'REFINERB'] as $code) {
                    $this->upsertGeneralInt($code, 0);
                }
            }

            if ($this->tableCount('smithm') === 0) {
                foreach (['SMITHE', 'SMITHB'] as $code) {
                    $this->upsertGeneralInt($code, 0);
                }
            }

            if ($this->tableCount('daybook') === 0) {
                foreach (['RCPTE', 'RCPTB', 'PMNTE', 'PMNTB', 'VCHNOJB', 'VCHNOJE', 'VCHNOPB', 'VCHNOPE', 'VCHNORB', 'VCHNORE'] as $code) {
                    $this->upsertGeneralInt($code, 0);
                }
            }

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Document counters initialised where matching transaction tables are empty.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Initialise failed: ' . $e->getMessage()], 500);
        }
    }

    public function closeAccounts(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        set_time_limit(0);
        ini_set('max_execution_time', '0');

        $closeDate = $this->normalizeDate((string) $request->input('close_date', ''));
        if ($closeDate === null) {
            return response()->json(['ok' => false, 'message' => 'Invalid closing date.'], 422);
        }

        $flags = $this->readFlags($request);

        DB::beginTransaction();
        try {
            $summary = [];

            $summary['cash_sales'] = $flags['chsales'] ? $this->deleteSalesByStatus($closeDate, true, $flags['keepbills'], false) : 0;
            $summary['credit_sales'] = $flags['crsales'] ? $this->deleteSalesByStatus($closeDate, false, $flags['keepbills'], $flags['keeppendbills']) : 0;
            $summary['sales_return'] = $flags['sret'] ? $this->deleteSimpleHeaderDetail('salesrm', 'salesrd', $closeDate, $flags['keepbills'], "sr = 'R'") : 0;
            $summary['cash_purchase'] = $flags['chpurchase'] ? $this->deletePurchaseByStatus($closeDate, true, $flags['keepbills']) : 0;
            $summary['credit_purchase'] = $flags['crpurchase'] ? $this->deletePurchaseByStatus($closeDate, false, $flags['keepbills']) : 0;
            $summary['purchase_return'] = $flags['purchaseret'] ? $this->deleteSimpleHeaderDetail('purchaserm', 'purchaserd', $closeDate, $flags['keepbills'], "pr = 'R'") : 0;
            $summary['other_item'] = $flags['otheritemtran'] ? $this->deleteOtherItems($closeDate) : 0;
            $summary['pending_order'] = $flags['orderpend'] ? $this->deleteOrderByStatus($closeDate, true, $flags['keepbills']) : 0;
            $summary['closed_order'] = $flags['order'] ? $this->deleteOrderByStatus($closeDate, false, $flags['keepbills']) : 0;
            $summary['pending_repair'] = $flags['reppend'] ? $this->deleteSimpleHeaderDetail('repairm', 'repaird', $closeDate, $flags['keepbills'], "status = 1") : 0;
            $summary['repair'] = $flags['repr'] ? $this->deleteSimpleHeaderDetail('repairm', 'repaird', $closeDate, $flags['keepbills'], "status <> 1") : 0;
            $summary['smith'] = $flags['smith'] ? $this->deleteSimpleHeaderDetail('smithm', 'smithd', $closeDate, $flags['keepbills'], null) : 0;
            $summary['pending_refinery'] = $flags['refnpend'] ? $this->deleteSimpleHeaderDetail('refinerym', 'refineryd', $closeDate, $flags['keepbills'], "status = 1") : 0;
            $summary['refinery'] = $flags['refn'] ? $this->deleteSimpleHeaderDetail('refinerym', 'refineryd', $closeDate, $flags['keepbills'], "status <> 1") : 0;
            $summary['adjustment'] = $flags['adjustment'] ? $this->deleteByDateAndControl('itemadj', 'tdate', $closeDate, $flags['keepbills']) : 0;
            $summary['partners_deposit'] = $flags['partnersdeposit'] ? $this->deleteByDateAndControl('wgtrcptpmnt', 'tdate', $closeDate, $flags['keepbills']) : 0;
            $summary['kuri'] = $flags['kuricolln'] ? $this->deleteByDateAndControl('kuricolln', 'tdate', $closeDate, $flags['keepbills']) : 0;

            if (!$flags['kuricolln'] && $this->hasTable('kuricolln') && Schema::hasColumn('kuricolln', 'closed')) {
                DB::table('kuricolln')->whereDate('tdate', '<=', $closeDate)->update(['closed' => 'Y']);
            }

            if ($flags['deldaybookentries']) {
                $summary['daybook'] = $this->rollForwardDaybook($closeDate, $flags['keepbills'], $flags['dontsp']);
            }

            if ($flags['otheritemtran']) {
                $this->rollForwardOtherItems($closeDate);
            }

            if (!$flags['initopstock']) {
                $this->rollForwardStock($closeDate, $flags);
            }

            if ($flags['initopbalie']) {
                $this->resetAccountTypeBalances(['R', 'E']);
            }

            if ($flags['initopbalal']) {
                $this->resetAccountTypeBalances(['A', 'L']);
                if ($this->hasTable('clients')) {
                    DB::table('clients')->update(['opbalance' => 0, 'opbalanceb' => 0]);
                }
            }

            if ($flags['initopstock']) {
                $this->resetOpeningStock();
            }

            if ($flags['initpartyopwgt'] && $this->hasTable('clientsgs')) {
                DB::table('clientsgs')->update(['opweight' => 0, 'opweightb' => 0]);
            }

            if ($flags['initsmithjewlopbal']) {
                if ($this->hasTable('clientsgs')) {
                    DB::table('clientsgs')->update(['opweight' => 0, 'opweightb' => 0]);
                }
                if ($this->hasTable('clients')) {
                    DB::table('clients')->whereIn('ctype', ['J', 'G'])->update(['opbalance' => 0, 'opbalanceb' => 0]);
                }
                if ($this->hasTable('accountm')) {
                    DB::table('accountm')->whereIn('actype2', ['J', 'G'])->update(['opbal' => 0, 'opbalb' => 0]);
                }
            }

            if ($flags['removeaddr'] && $this->hasTable('clients')) {
                DB::table('clients')->update([
                    'addr1' => '', 'addr2' => '', 'addr3' => '', 'city' => '', 'telephone' => '', 'mobile' => '',
                ]);
            }

            if (!$flags['orderpend'] && $this->hasTable('orderm') && Schema::hasColumn('orderm', 'closed')) {
                DB::table('orderm')->whereDate('tdate', '<=', $closeDate)->update(['closed' => 1]);
            }

            if ($flags['deloutstockbarcode']) {
                $summary['barcode'] = $this->deleteOutStockBarcodes();
            }

            DB::commit();

            $this->logDelpart($request, 'Year End Account Close', [
                'utype' => 'E',
                'ttype' => 'A',
                'tdate' => $closeDate,
                'control' => $flags['keepbills'] ? 2 : 1,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Year-end close completed for the selected options.',
                'summary' => $summary,
                'note' => 'Stock roll-forward is now included. Use "Initialise Op. Stock Details" only when you want a full opening-stock reset instead of transaction-based carry-forward.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Year-end close failed: ' . $e->getMessage()], 500);
        }
    }

    private function sections(): array
    {
        return [
            [
                'title' => 'Delete Transactions',
                'items' => [
                    ['key' => 'chsales', 'label' => 'Delete Cash Sales'],
                    ['key' => 'crsales', 'label' => 'Delete Credit Sales'],
                    ['key' => 'sret', 'label' => 'Delete Sales Return'],
                    ['key' => 'chpurchase', 'label' => 'Delete Cash Purchase'],
                    ['key' => 'crpurchase', 'label' => 'Delete Credit Purchase'],
                    ['key' => 'purchaseret', 'label' => 'Delete Purchase Return'],
                    ['key' => 'otheritemtran', 'label' => 'Delete Other Item Transactions'],
                    ['key' => 'order', 'label' => 'Delete Order'],
                    ['key' => 'orderpend', 'label' => 'Delete Pending Order'],
                    ['key' => 'reppend', 'label' => 'Delete Pending Repair'],
                    ['key' => 'repr', 'label' => 'Delete Repair'],
                    ['key' => 'smith', 'label' => 'Delete Smith/Jewl/Dep Entries'],
                    ['key' => 'refn', 'label' => 'Delete Refinery Entries (closed)'],
                    ['key' => 'refnpend', 'label' => 'Delete Refinery Entries (pending)'],
                    ['key' => 'adjustment', 'label' => 'Delete Item Adjustment Entries'],
                    ['key' => 'kuricolln', 'label' => 'Delete Kuri/Scheme Colln Entries'],
                    ['key' => 'partnersdeposit', 'label' => 'Delete Partners Deposit Entries'],
                ],
            ],
            [
                'title' => 'Close / Reset',
                'items' => [
                    ['key' => 'deldaybookentries', 'label' => 'Delete Daybook Entries'],
                    ['key' => 'keepbills', 'label' => 'Keep Bills'],
                    ['key' => 'keeppendbills', 'label' => 'Keep Pending Bills'],
                    ['key' => 'dontsp', 'label' => "Don't Close Sp. Accounts"],
                    ['key' => 'initopbalie', 'label' => 'Initialise Op. Balance of Income/Expense A/cs'],
                    ['key' => 'initopbalal', 'label' => 'Initialise Op. Balance of Assets/Liabilities'],
                    ['key' => 'initopstock', 'label' => 'Initialise Op. Stock Details'],
                    ['key' => 'initpartyopwgt', 'label' => 'Initialise Party Op. Wgt Details'],
                    ['key' => 'initsmithjewlopbal', 'label' => 'Initialise Smith/Jewl Op. Bal Details'],
                    ['key' => 'removeaddr', 'label' => 'Remove Addr+Phone from Clients'],
                    ['key' => 'deloutstockbarcode', 'label' => 'Delete Outstock Barcodes'],
                ],
            ],
        ];
    }

    private function readFlags(Request $request): array
    {
        $keys = [
            'chsales', 'crsales', 'sret', 'chpurchase', 'crpurchase', 'purchaseret', 'otheritemtran',
            'order', 'orderpend', 'reppend', 'repr', 'smith', 'refn', 'refnpend', 'adjustment',
            'kuricolln', 'partnersdeposit', 'deldaybookentries', 'keepbills', 'keeppendbills', 'dontsp',
            'initopbalie', 'initopbalal', 'initopstock', 'initpartyopwgt', 'initsmithjewlopbal',
            'removeaddr', 'deloutstockbarcode',
        ];

        $flags = [];
        foreach ($keys as $key) {
            $flags[$key] = $request->boolean($key);
        }

        return $flags;
    }

    private function deleteSalesByStatus(string $date, bool $cash, bool $keepBills, bool $keepPendingBills): int
    {
        if (!$this->hasTable('salesm')) {
            return 0;
        }

        $header = DB::table('salesm')->whereDate('tdate', '<=', $date);
        $cash ? $header->where('status', 3) : $header->where('status', '<>', 3);

        if ($keepBills && Schema::hasColumn('salesm', 'control')) {
            $header->where('control', 2);
        }

        if ($keepPendingBills && Schema::hasColumn('salesm', 'opbill')) {
            $header->where('opbill', '<>', 1);
        }

        $slnos = $header->pluck('slno')->map(fn ($v) => (int) $v)->all();
        if (!$slnos) {
            return 0;
        }

        foreach (['salesd', 'salesrd', 'salesrm', 'purchased', 'purchasem'] as $table) {
            if ($this->hasTable($table)) {
                DB::table($table)->whereIn('slno', $slnos)->delete();
            }
        }

        if ($this->hasTable('collection') && Schema::hasColumn('collection', 'slno')) {
            DB::table('collection')->whereIn('slno', $slnos)->delete();
        }

        return DB::table('salesm')->whereIn('slno', $slnos)->delete();
    }

    private function deletePurchaseByStatus(string $date, bool $cash, bool $keepBills): int
    {
        if (!$this->hasTable('purchasem')) {
            return 0;
        }

        $header = DB::table('purchasem')->whereDate('tdate', '<=', $date)->where('pr', '<>', 'E');
        $cash ? $header->where('status', 3) : $header->where('status', '<>', 3);

        if ($keepBills && Schema::hasColumn('purchasem', 'control')) {
            $header->where('control', 2);
        }

        $slnos = $header->pluck('slno')->map(fn ($v) => (int) $v)->all();
        if (!$slnos) {
            return 0;
        }

        foreach (['purchased', 'purchaserd', 'purchaserm'] as $table) {
            if ($this->hasTable($table)) {
                DB::table($table)->whereIn('slno', $slnos)->delete();
            }
        }

        return DB::table('purchasem')->whereIn('slno', $slnos)->delete();
    }

    private function deleteOrderByStatus(string $date, bool $pending, bool $keepBills): int
    {
        if (!$this->hasTable('orderm')) {
            return 0;
        }

        $header = DB::table('orderm')->whereDate('tdate', '<=', $date);
        $pending ? $header->where('status', 1) : $header->where('status', '<>', 1);

        if ($keepBills && Schema::hasColumn('orderm', 'control')) {
            $header->where('control', 2);
        }

        $rows = $header->select('slno', 'ordno')->get();
        if ($rows->isEmpty()) {
            return 0;
        }

        $slnos = $rows->pluck('slno')->map(fn ($v) => (int) $v)->all();
        $ordnos = $rows->pluck('ordno')->filter()->map(fn ($v) => trim((string) $v))->all();

        foreach (['orderd', 'orderdga', 'salesrd', 'salesrm', 'purchased', 'purchasem'] as $table) {
            if ($this->hasTable($table)) {
                DB::table($table)->whereIn('slno', $slnos)->delete();
            }
        }

        if ($ordnos && $this->hasTable('advafter')) {
            DB::table('advafter')->whereIn('ordno', $ordnos)->delete();
        }

        return DB::table('orderm')->whereIn('slno', $slnos)->delete();
    }

    private function deleteSimpleHeaderDetail(string $headerTable, string $detailTable, string $date, bool $keepBills, ?string $rawCondition): int
    {
        if (!$this->hasTable($headerTable)) {
            return 0;
        }

        $header = DB::table($headerTable)->whereDate('tdate', '<=', $date);
        if ($rawCondition !== null) {
            $header->whereRaw($rawCondition);
        }
        if ($keepBills && Schema::hasColumn($headerTable, 'control')) {
            $header->where('control', 2);
        }

        $slnos = $header->pluck('slno')->map(fn ($v) => (int) $v)->all();
        if (!$slnos) {
            return 0;
        }

        if ($this->hasTable($detailTable)) {
            DB::table($detailTable)->whereIn('slno', $slnos)->delete();
        }

        return DB::table($headerTable)->whereIn('slno', $slnos)->delete();
    }

    private function deleteOtherItems(string $date): int
    {
        $deleted = $this->deleteChildByParent('oitemtrand', 'slno', 'oitemtranm', 'slno', 'tdate', $date);
        $deleted += $this->deleteByDate('oitemtranm', 'tdate', $date);
        return $deleted;
    }

    private function deleteByDate(string $table, string $dateColumn, string $date): int
    {
        if (!$this->hasTable($table)) {
            return 0;
        }

        return DB::table($table)->whereDate($dateColumn, '<=', $date)->delete();
    }

    private function deleteByDateAndControl(string $table, string $dateColumn, string $date, bool $keepBills): int
    {
        if (!$this->hasTable($table)) {
            return 0;
        }

        $query = DB::table($table)->whereDate($dateColumn, '<=', $date);
        if ($keepBills && Schema::hasColumn($table, 'control')) {
            $query->where('control', 2);
        }
        return $query->delete();
    }

    private function deleteChildByParent(string $childTable, string $childColumn, string $parentTable, string $parentColumn, string $dateColumn, string $date): int
    {
        if (!$this->hasTable($childTable) || !$this->hasTable($parentTable)) {
            return 0;
        }

        $ids = DB::table($parentTable)->whereDate($dateColumn, '<=', $date)->pluck($parentColumn)->all();
        if (!$ids) {
            return 0;
        }

        return DB::table($childTable)->whereIn($childColumn, $ids)->delete();
    }

    private function rollForwardDaybook(string $date, bool $keepBills, bool $dontSp): int
    {
        if (!$this->hasTable('accountm') || !$this->hasTable('daybook')) {
            return 0;
        }

        $accounts = DB::table('accountm')->select('accode', 'opbal', 'opbalb')->get();
        foreach ($accounts as $row) {
            $base = DB::table('daybook')->where('accode', $row->accode)->whereDate('tdate', '<=', $date);
            $opbal = (float) ($row->opbal ?? 0);
            $opbalb = (float) ($row->opbalb ?? 0);

            $sumControl1 = Schema::hasColumn('daybook', 'control')
                ? (float) (clone $base)->where('control', 1)->sum('amount')
                : 0.0;
            $sumControl2 = Schema::hasColumn('daybook', 'control')
                ? (float) (clone $base)->where('control', 2)->sum('amount')
                : (float) (clone $base)->sum('amount');

            DB::table('accountm')->where('accode', $row->accode)->update([
                'opbal' => $opbal + $sumControl1,
                'opbalb' => $opbalb + $sumControl2,
            ]);
        }

        if ($this->hasTable('clients')) {
            DB::statement("UPDATE clients SET opbalance = COALESCE((SELECT accountm.opbal FROM accountm WHERE accountm.accode = clients.code), opbalance)");
            DB::statement("UPDATE clients SET opbalanceb = COALESCE((SELECT accountm.opbalb FROM accountm WHERE accountm.accode = clients.code), opbalanceb)");
        }

        $slnoQuery = DB::table('daybook')->whereDate('tdate', '<=', $date);
        if ($keepBills && Schema::hasColumn('daybook', 'control')) {
            $slnoQuery->where('control', 2);
        }
        if ($dontSp && $this->hasTable('accountm') && Schema::hasColumn('accountm', 'sp')) {
            $slnoQuery->whereNotIn('slno', function ($sub) {
                $sub->from('daybook as db')
                    ->join('accountm', 'accountm.accode', '=', 'db.accode')
                    ->select('db.slno')
                    ->where('accountm.sp', 1);
            });
        }

        $slnos = $slnoQuery->pluck('slno')->all();
        if (!$slnos) {
            return 0;
        }

        if ($this->hasTable('daybookpart')) {
            DB::table('daybookpart')->whereIn('slno', $slnos)->delete();
        }
        if ($this->hasTable('daybookratewgt')) {
            DB::table('daybookratewgt')->whereIn('slno', $slnos)->delete();
        }

        return DB::table('daybook')->whereIn('slno', $slnos)->delete();
    }

    private function rollForwardOtherItems(string $date): void
    {
        if (!$this->hasTable('itemsothers') || !$this->hasTable('oitemtranm') || !$this->hasTable('oitemtrand')) {
            return;
        }

        $rows = DB::table('itemsothers')->select('code', 'opstock')->get();
        foreach ($rows as $row) {
            $sold = (float) DB::table('oitemtrand as d')
                ->join('oitemtranm as m', 'm.slno', '=', 'd.slno')
                ->where('d.code', $row->code)
                ->where('m.tr', 'S')
                ->whereDate('m.tdate', '<=', $date)
                ->sum('d.qty');

            $purchased = (float) DB::table('oitemtrand as d')
                ->join('oitemtranm as m', 'm.slno', '=', 'd.slno')
                ->where('d.code', $row->code)
                ->where('m.tr', 'P')
                ->whereDate('m.tdate', '<=', $date)
                ->sum('d.qty');

            DB::table('itemsothers')->where('code', $row->code)->update([
                'opstock' => ((float) ($row->opstock ?? 0)) - $sold + $purchased,
            ]);
        }
    }

    private function rollForwardStock(string $date, array $flags): void
    {
        $sameStock = $this->softwareFlag('SameStock', 'N');
        $stktypeStrict = $this->softwareFlag('StktypeStrict', 'N');

        if ($stktypeStrict && $this->hasTable('itemsstk') && Schema::hasColumn('itemsstk', 'stktype')) {
            $this->rollForwardTypedOpeningStock($date, $flags, $sameStock);
            return;
        }

        $this->rollForwardOpeningStockLevel($date, $flags, 2, $sameStock, true);

        if (!$sameStock) {
            $this->rollForwardOpeningStockLevel($date, $flags, 1, false, false);
        }
    }

    private function rollForwardTypedOpeningStock(string $date, array $flags, bool $sameStock): void
    {
        if (!$this->hasTable('itemsstk')) {
            return;
        }

        $rows = DB::table('itemsstk as s')
            ->leftJoin('items as i', 'i.code', '=', 's.code')
            ->select(
                's.code',
                's.stktype',
                DB::raw('COALESCE(s.opqtyb, 0) as opqtyb'),
                DB::raw('COALESCE(s.opweightb, 0) as opweightb'),
                DB::raw('COALESCE(s.opstonewgtb, 0) as opstonewgtb'),
                DB::raw('COALESCE(s.opstoneamtb, 0) as opstoneamtb'),
                DB::raw('COALESCE(i.stonemarg, 0) as stonemarg')
            )
            ->orderBy('s.code')
            ->orderBy('s.stktype')
            ->get();

        foreach ($rows as $row) {
            $state = [
                'qty' => (float) $row->opqtyb,
                'weight' => (float) $row->opweightb,
                'stonewgt' => (float) $row->opstonewgtb,
                'stoneamt' => (float) $row->opstoneamtb,
                'dmdwgt' => 0.0,
            ];

            $this->applyStockFlags($state, trim((string) $row->code), $date, $flags, 2, (string) $row->stktype, (float) $row->stonemarg, false);

            $update = [
                'opqtyb' => $state['qty'],
                'opweightb' => $state['weight'],
                'opstonewgtb' => $state['stonewgt'],
            ];

            if ($this->hasCol('itemsstk', 'opstoneamtb')) {
                $update['opstoneamtb'] = $state['stoneamt'];
            }

            if ($sameStock) {
                if ($this->hasCol('itemsstk', 'opqty')) {
                    $update['opqty'] = $state['qty'];
                }
                if ($this->hasCol('itemsstk', 'opweight')) {
                    $update['opweight'] = $state['weight'];
                }
                if ($this->hasCol('itemsstk', 'opstonewgt')) {
                    $update['opstonewgt'] = $state['stonewgt'];
                }
                if ($this->hasCol('itemsstk', 'opstoneamt')) {
                    $update['opstoneamt'] = $state['stoneamt'];
                }
            }

            DB::table('itemsstk')
                ->where('code', $row->code)
                ->where('stktype', $row->stktype)
                ->update($update);
        }

        if (!$this->hasTable('items')) {
            return;
        }

        $itemCodes = DB::table('itemsstk')->distinct()->pluck('code');
        foreach ($itemCodes as $code) {
            $update = [
                'opqtyb' => (float) DB::table('itemsstk')->where('code', $code)->sum('opqtyb'),
                'opweightb' => (float) DB::table('itemsstk')->where('code', $code)->sum('opweightb'),
                'opstonewgtb' => (float) DB::table('itemsstk')->where('code', $code)->sum('opstonewgtb'),
            ];

            if ($this->hasCol('itemsstk', 'opstoneamtb') && $this->hasCol('items', 'opstoneamtb')) {
                $update['opstoneamtb'] = (float) DB::table('itemsstk')->where('code', $code)->sum('opstoneamtb');
            }

            if ($sameStock) {
                if ($this->hasCol('items', 'opqty')) {
                    $update['opqty'] = $update['opqtyb'];
                }
                if ($this->hasCol('items', 'opweight')) {
                    $update['opweight'] = $update['opweightb'];
                }
                if ($this->hasCol('items', 'opstonewgt')) {
                    $update['opstonewgt'] = $update['opstonewgtb'];
                }
                if (isset($update['opstoneamtb']) && $this->hasCol('items', 'opstoneamt')) {
                    $update['opstoneamt'] = $update['opstoneamtb'];
                }
            }

            DB::table('items')->where('code', $code)->update($update);
        }
    }

    private function rollForwardOpeningStockLevel(string $date, array $flags, int $controlMode, bool $sameStock, bool $includeDmd): void
    {
        if (!$this->hasTable('items')) {
            return;
        }

        $qtyCol = $controlMode === 1 ? 'opqty' : 'opqtyb';
        $weightCol = $controlMode === 1 ? 'opweight' : 'opweightb';
        $stoneWgtCol = $controlMode === 1 ? 'opstonewgt' : 'opstonewgtb';
        $stoneAmtCol = $controlMode === 1 ? 'opstoneamt' : 'opstoneamtb';

        $selects = [
            'code',
            DB::raw('COALESCE(' . $qtyCol . ', 0) as base_qty'),
            DB::raw('COALESCE(' . $weightCol . ', 0) as base_weight'),
            DB::raw('COALESCE(' . $stoneWgtCol . ', 0) as base_stonewgt'),
            DB::raw('COALESCE(stonemarg, 0) as stonemarg'),
        ];
        $selects[] = $this->hasCol('items', $stoneAmtCol)
            ? DB::raw('COALESCE(' . $stoneAmtCol . ', 0) as base_stoneamt')
            : DB::raw('0 as base_stoneamt');
        $selects[] = ($includeDmd && $this->hasCol('items', 'opdmdwgt'))
            ? DB::raw('COALESCE(opdmdwgt, 0) as base_dmdwgt')
            : DB::raw('0 as base_dmdwgt');

        $rows = DB::table('items')->select($selects)->orderBy('code')->get();
        foreach ($rows as $row) {
            $state = [
                'qty' => (float) $row->base_qty,
                'weight' => (float) $row->base_weight,
                'stonewgt' => (float) $row->base_stonewgt,
                'stoneamt' => (float) $row->base_stoneamt,
                'dmdwgt' => (float) $row->base_dmdwgt,
            ];

            $this->applyStockFlags($state, trim((string) $row->code), $date, $flags, $controlMode, null, (float) $row->stonemarg, $includeDmd);

            $update = [
                $qtyCol => $state['qty'],
                $weightCol => $state['weight'],
                $stoneWgtCol => $state['stonewgt'],
            ];

            if ($this->hasCol('items', $stoneAmtCol)) {
                $update[$stoneAmtCol] = $state['stoneamt'];
            }
            if ($includeDmd && $this->hasCol('items', 'opdmdwgt')) {
                $update['opdmdwgt'] = $state['dmdwgt'];
            }
            if ($sameStock && $controlMode === 2) {
                if ($this->hasCol('items', 'opqty')) {
                    $update['opqty'] = $state['qty'];
                }
                if ($this->hasCol('items', 'opweight')) {
                    $update['opweight'] = $state['weight'];
                }
                if ($this->hasCol('items', 'opstonewgt')) {
                    $update['opstonewgt'] = $state['stonewgt'];
                }
                if ($this->hasCol('items', 'opstoneamt')) {
                    $update['opstoneamt'] = $state['stoneamt'];
                }
            }

            DB::table('items')->where('code', $row->code)->update($update);
        }
    }

    private function applyStockFlags(array &$state, string $code, string $date, array $flags, int $controlMode, ?string $stockType, float $stoneMarg, bool $includeDmd): void
    {
        if ($flags['chsales']) {
            $this->applyMovement($state, $this->movementSales($code, $date, true, $controlMode, $stockType, $stoneMarg, $includeDmd));
        }
        if ($flags['crsales']) {
            $this->applyMovement($state, $this->movementSales($code, $date, false, $controlMode, $stockType, $stoneMarg, $includeDmd));
        }
        if ($flags['sret']) {
            $this->applyMovement($state, $this->movementSalesReturn($code, $date, $controlMode, $stockType, $stoneMarg, $includeDmd));
        }
        if ($flags['chpurchase']) {
            $this->applyMovement($state, $this->movementPurchase($code, $date, true, $controlMode, $stockType, $includeDmd));
        }
        if ($flags['crpurchase']) {
            $this->applyMovement($state, $this->movementPurchase($code, $date, false, $controlMode, $stockType, $includeDmd));
        }
        if ($flags['purchaseret']) {
            $this->applyMovement($state, $this->movementPurchaseReturn($code, $date, $controlMode, $stockType));
        }
        if ($flags['orderpend']) {
            $this->applyMovement($state, $this->movementOrder($code, $date, true, $controlMode, $stockType));
        }
        if ($flags['order']) {
            $this->applyMovement($state, $this->movementOrder($code, $date, false, $controlMode, $stockType));
        }
        if ($flags['repr']) {
            $this->applyMovement($state, $this->movementRepair($code, $date, false, $controlMode, $stockType));
        }
        if ($flags['reppend']) {
            $this->applyMovement($state, $this->movementRepair($code, $date, true, $controlMode, $stockType));
        }
        if ($flags['smith']) {
            $this->applyMovement($state, $this->movementSmith($code, $date, $controlMode, $stockType));
        }
        if ($flags['refn']) {
            $this->applyMovement($state, $this->movementRefinery($code, $date, false, $controlMode, $stockType));
        }
        if ($flags['refnpend']) {
            $this->applyMovement($state, $this->movementRefinery($code, $date, true, $controlMode, $stockType));
        }
        if ($flags['adjustment']) {
            $this->applyMovement($state, $this->movementItemAdjustment($code, $date, $controlMode, $stockType));
        }
    }

    private function movementSales(string $code, string $date, bool $cash, int $controlMode, ?string $stockType, float $stoneMarg, bool $includeDmd): array
    {
        $movement = $this->zeroMovement();
        $statusOp = $cash ? '= 3' : '<> 3';
        $jcodeFilter = $this->hasCol('salesd', 'jcode') ? " AND (TRIM(COALESCE(sd.jcode, '')) = '')" : '';
        $stockFilterSalesd = $this->stockTypeFilter('salesd', 'sd', $stockType);
        $stockFilterSalesrd = $this->stockTypeFilter('salesrd', 'srd', $stockType);
        $stockFilterPurchased = $this->stockTypeFilter('purchased', 'pd', $stockType);

        if ($this->tablesExist(['salesd', 'salesm'])) {
            $row = $this->sumRow("
                SELECT SUM(COALESCE(sd.qty,0)) qty, SUM(COALESCE(sd.weight,0)) weight,
                    SUM(COALESCE(" . $this->colExpr('salesd', 'stonewgt', 'sd') . ",0)) stonewgt,
                    SUM(COALESCE(" . $this->colExpr('salesd', 'stoneprice', 'sd') . ",0)) stoneamt,
                    SUM(COALESCE(" . $this->colExpr('salesd', 'dmdwgt', 'sd') . ",0)) dmdwgt
                FROM salesd sd
                JOIN salesm sm ON sm.slno = sd.slno
                WHERE sd.code = ? AND sm.status {$statusOp} AND sm.tdate <= ?{$jcodeFilter}
                    AND {$this->controlCondition('salesm', 'sm', $controlMode)}{$stockFilterSalesd}
            ", [$code, $date]);
            $this->applyMovement($movement, $this->signedMovement($row, -1, -1, -1, -1, -1, $stoneMarg));
        }

        if ($this->tablesExist(['salesrd', 'salesm'])) {
            $row = $this->sumRow("
                SELECT SUM(COALESCE(srd.qty,0)) qty, SUM(COALESCE(srd.weight,0)) weight,
                    SUM(COALESCE(" . $this->colExpr('salesrd', 'stonewgt', 'srd') . ",0)) stonewgt,
                    SUM(COALESCE(" . $this->colExpr('salesrd', 'stoneprice', 'srd') . ",0)) stoneamt,
                    SUM(COALESCE(" . $this->colExpr('salesrd', 'dmdwgt', 'srd') . ",0)) dmdwgt
                FROM salesrd srd
                JOIN salesm sm ON sm.slno = srd.slno
                WHERE srd.code = ? AND sm.status {$statusOp} AND sm.tdate <= ?
                    AND {$this->controlCondition('salesm', 'sm', $controlMode)}{$stockFilterSalesrd}
            ", [$code, $date]);
            $this->applyMovement($movement, $this->signedMovement($row, 1, 1, 1, 1, 1, $stoneMarg));
        }

        if ($this->tablesExist(['purchased', 'salesm'])) {
            $row = $this->sumRow("
                SELECT SUM(COALESCE(pd.qty,0)) qty, SUM(COALESCE(pd.weight,0)) weight,
                    SUM(COALESCE(" . $this->colExpr('purchased', 'stwgt', 'pd') . ",0)) stonewgt,
                    SUM(COALESCE(" . $this->colExpr('purchased', 'stprice', 'pd') . ",0)) stoneamt,
                    0 dmdwgt
                FROM purchased pd
                JOIN salesm sm ON sm.slno = pd.slno
                WHERE pd.code = ? AND sm.status {$statusOp} AND sm.tdate <= ?
                    AND {$this->controlCondition('salesm', 'sm', $controlMode)}{$stockFilterPurchased}
            ", [$code, $date]);
            $this->applyMovement($movement, $this->signedMovement($row, 1, 1, 1, 1, 0, 0));
        }

        if ($includeDmd && $this->tablesExist(['purchased_dmddet', 'purchasem', 'salesm']) && $this->hasCol('purchased_dmddet', 'carats')) {
            $carats = (float) ($this->sumValue("
                SELECT SUM(COALESCE(pdd.carats,0)) total
                FROM purchased_dmddet pdd
                JOIN purchasem pm ON pm.slno = pdd.slno
                JOIN salesm sm ON sm.slno = pm.slno
                WHERE pdd.code = ? AND sm.status {$statusOp} AND sm.tdate <= ?
                    AND {$this->controlCondition('salesm', 'sm', $controlMode)}
            ", [$code, $date]) ?? 0);
            $movement['dmdwgt'] += $carats;
        }

        return $movement;
    }

    private function movementSalesReturn(string $code, string $date, int $controlMode, ?string $stockType, float $stoneMarg, bool $includeDmd): array
    {
        if (!$this->tablesExist(['salesrd', 'salesrm'])) {
            return $this->zeroMovement();
        }

        $stockFilter = $this->stockTypeFilter('salesrd', 'srd', $stockType);
        $row = $this->sumRow("
            SELECT SUM(COALESCE(srd.qty,0)) qty, SUM(COALESCE(srd.weight,0)) weight,
                SUM(COALESCE(" . $this->colExpr('salesrd', 'stonewgt', 'srd') . ",0)) stonewgt,
                SUM(COALESCE(" . $this->colExpr('salesrd', 'stoneprice', 'srd') . ",0)) stoneamt,
                SUM(COALESCE(" . $this->colExpr('salesrd', 'dmdwgt', 'srd') . ",0)) dmdwgt
            FROM salesrd srd
            JOIN salesrm srm ON srm.slno = srd.slno
            WHERE srd.code = ? AND srm.tdate <= ? AND srm.sr = 'R'
                AND {$this->controlCondition('salesrm', 'srm', $controlMode)}{$stockFilter}
        ", [$code, $date]);

        return $this->signedMovement($row, 1, 1, 1, 1, $includeDmd ? 1 : 0, $stoneMarg);
    }

    private function movementPurchase(string $code, string $date, bool $cash, int $controlMode, ?string $stockType, bool $includeDmd): array
    {
        if (!$this->tablesExist(['purchasem'])) {
            return $this->zeroMovement();
        }

        $movement = $this->zeroMovement();
        $statusOp = $cash ? '= 3' : '<> 3';
        $stockFilterPurchased = $this->stockTypeFilter('purchased', 'pd', $stockType);
        $stockFilterPurchaserd = $this->stockTypeFilter('purchaserd', 'prd', $stockType);

        if ($this->tablesExist(['purchased', 'purchasem'])) {
            $row = $this->sumRow("
                SELECT SUM(COALESCE(pd.qty,0)) qty, SUM(COALESCE(pd.weight,0)) weight,
                    SUM(COALESCE(" . $this->colExpr('purchased', 'stwgt', 'pd') . ",0)) stonewgt,
                    SUM(COALESCE(" . $this->colExpr('purchased', 'stprice', 'pd') . ",0)) stoneamt,
                    0 dmdwgt
                FROM purchased pd
                JOIN purchasem pm ON pm.slno = pd.slno
                WHERE pd.code = ? AND pm.status {$statusOp} AND pm.tdate <= ? AND pm.pr <> 'E'
                    AND {$this->controlCondition('purchasem', 'pm', $controlMode)}{$stockFilterPurchased}
            ", [$code, $date]);
            $this->applyMovement($movement, $this->signedMovement($row, 1, 1, 1, 1, 0, 0));
        }

        if ($this->tablesExist(['purchaserd', 'purchasem'])) {
            $row = $this->sumRow("
                SELECT SUM(COALESCE(prd.qty,0)) qty, SUM(COALESCE(prd.weight,0)) weight,
                    SUM(COALESCE(" . $this->colExpr('purchaserd', 'stwgt', 'prd') . ",0)) stonewgt,
                    SUM(COALESCE(" . $this->colExpr('purchaserd', 'stprice', 'prd') . ",0)) stoneamt,
                    0 dmdwgt
                FROM purchaserd prd
                JOIN purchasem pm ON pm.slno = prd.slno
                WHERE prd.code = ? AND pm.status {$statusOp} AND pm.tdate <= ? AND pm.pr <> 'E'
                    AND {$this->controlCondition('purchasem', 'pm', $controlMode)}{$stockFilterPurchaserd}
            ", [$code, $date]);
            $this->applyMovement($movement, $this->signedMovement($row, -1, -1, -1, -1, 0, 0));
        }

        if ($includeDmd && $this->tablesExist(['purchased_dmddet', 'purchasem']) && $this->hasCol('purchased_dmddet', 'carats')) {
            $carats = (float) ($this->sumValue("
                SELECT SUM(COALESCE(pdd.carats,0)) total
                FROM purchased_dmddet pdd
                JOIN purchasem pm ON pm.slno = pdd.slno
                WHERE pdd.code = ? AND pm.status {$statusOp} AND pm.tdate <= ? AND pm.pr <> 'E'
                    AND {$this->controlCondition('purchasem', 'pm', $controlMode)}
            ", [$code, $date]) ?? 0);
            $movement['dmdwgt'] += $carats;
        }

        return $movement;
    }

    private function movementPurchaseReturn(string $code, string $date, int $controlMode, ?string $stockType): array
    {
        if (!$this->tablesExist(['purchaserd', 'purchaserm'])) {
            return $this->zeroMovement();
        }

        $stockFilter = $this->stockTypeFilter('purchaserd', 'prd', $stockType);
        $row = $this->sumRow("
            SELECT SUM(COALESCE(prd.qty,0)) qty, SUM(COALESCE(prd.weight,0)) weight,
                SUM(COALESCE(" . $this->colExpr('purchaserd', 'stwgt', 'prd') . ",0)) stonewgt,
                SUM(COALESCE(" . $this->colExpr('purchaserd', 'stprice', 'prd') . ",0)) stoneamt,
                0 dmdwgt
            FROM purchaserd prd
            JOIN purchaserm prm ON prm.slno = prd.slno
            WHERE prd.code = ? AND prm.tdate <= ? AND prm.pr = 'R'
                AND {$this->controlCondition('purchaserm', 'prm', $controlMode)}{$stockFilter}
        ", [$code, $date]);

        return $this->signedMovement($row, -1, -1, -1, -1, 0, 0);
    }

    private function movementOrder(string $code, string $date, bool $pending, int $controlMode, ?string $stockType): array
    {
        if (!$this->tablesExist(['orderm'])) {
            return $this->zeroMovement();
        }

        $movement = $this->zeroMovement();
        $statusOp = $pending ? '= 1' : '<> 1';
        $stockFilterPurchased = $this->stockTypeFilter('purchased', 'pd', $stockType);
        $stockFilterOrderdga = $this->stockTypeFilter('orderdga', 'odg', $stockType);
        $stockFilterSalesrd = $this->stockTypeFilter('salesrd', 'srd', $stockType);

        if ($this->tablesExist(['purchased', 'orderm'])) {
            $row = $this->sumRow("
                SELECT SUM(COALESCE(pd.qty,0)) qty, SUM(COALESCE(pd.weight,0)) weight,
                    SUM(COALESCE(" . $this->colExpr('purchased', 'stwgt', 'pd') . ",0)) stonewgt,
                    SUM(COALESCE(" . $this->colExpr('purchased', 'stprice', 'pd') . ",0)) stoneamt,
                    0 dmdwgt
                FROM purchased pd
                JOIN orderm om ON om.slno = pd.slno
                WHERE pd.code = ? AND om.status {$statusOp} AND om.tdate <= ?
                    AND {$this->controlCondition('orderm', 'om', $controlMode)}{$stockFilterPurchased}
            ", [$code, $date]);
            $this->applyMovement($movement, $this->signedMovement($row, 1, 1, 1, 1, 0, 0));
        }

        if ($this->tablesExist(['orderdga', 'orderm'])) {
            $row = $this->sumRow("
                SELECT SUM(COALESCE(odg.qty,0)) qty, SUM(COALESCE(odg.weight,0)) weight,
                    SUM(COALESCE(" . $this->colExpr('orderdga', 'stonewgt', 'odg') . ",0)) stonewgt,
                    0 stoneamt, 0 dmdwgt
                FROM orderdga odg
                JOIN orderm om ON om.slno = odg.slno
                WHERE odg.code = ? AND om.status {$statusOp} AND om.tdate <= ?
                    AND {$this->controlCondition('orderm', 'om', $controlMode)}{$stockFilterOrderdga}
            ", [$code, $date]);
            $this->applyMovement($movement, $this->signedMovement($row, 1, 1, 1, 0, 0, 0));
        }

        if ($this->tablesExist(['orderdga', 'orderm', 'advafter'])) {
            $row = $this->sumRow("
                SELECT SUM(COALESCE(odg.qty,0)) qty, SUM(COALESCE(odg.weight,0)) weight,
                    SUM(COALESCE(" . $this->colExpr('orderdga', 'stonewgt', 'odg') . ",0)) stonewgt,
                    0 stoneamt, 0 dmdwgt
                FROM orderdga odg
                JOIN advafter aa ON aa.slno = odg.slno
                JOIN orderm om ON om.ordno = aa.ordno
                WHERE odg.code = ? AND om.status {$statusOp} AND aa.tdate <= ?
                    AND {$this->controlCondition('advafter', 'aa', $controlMode)}{$stockFilterOrderdga}
            ", [$code, $date]);
            $this->applyMovement($movement, $this->signedMovement($row, 1, 1, 1, 0, 0, 0));
        }

        if ($this->tablesExist(['salesrd', 'orderm'])) {
            $row = $this->sumRow("
                SELECT SUM(COALESCE(srd.qty,0)) qty, SUM(COALESCE(srd.weight,0)) weight,
                    SUM(COALESCE(" . $this->colExpr('salesrd', 'stonewgt', 'srd') . ",0)) stonewgt,
                    SUM(COALESCE(" . $this->colExpr('salesrd', 'stoneprice', 'srd') . ",0)) stoneamt,
                    0 dmdwgt
                FROM salesrd srd
                JOIN orderm om ON om.slno = srd.slno
                WHERE srd.code = ? AND om.status {$statusOp} AND om.tdate <= ?
                    AND {$this->controlCondition('orderm', 'om', $controlMode)}{$stockFilterSalesrd}
            ", [$code, $date]);
            $this->applyMovement($movement, $this->signedMovement($row, 1, 1, 1, 1, 0, 0));
        }

        return $movement;
    }

    private function movementRepair(string $code, string $date, bool $pending, int $controlMode, ?string $stockType): array
    {
        if (!$this->tablesExist(['repaird', 'repairm'])) {
            return $this->zeroMovement();
        }

        $movement = $this->zeroMovement();
        $statusOp = $pending ? '= 1' : '<> 1';
        $stockFilter = $this->stockTypeFilter('repaird', 'rd', $stockType);

        foreach ([['R', 1], ['G', -1]] as [$givrec, $sign]) {
            $row = $this->sumRow("
                SELECT SUM(COALESCE(rd.qty,0)) qty, SUM(COALESCE(rd.weight,0)) weight,
                    SUM(COALESCE(" . $this->colExpr('repaird', 'stonewgt', 'rd') . ",0)) stonewgt,
                    SUM(COALESCE(" . $this->colExpr('repaird', 'stoneprice', 'rd') . ",0)) stoneamt,
                    0 dmdwgt
                FROM repaird rd
                JOIN repairm rm ON rm.slno = rd.slno
                WHERE rd.code = ? AND rd.givrec = ? AND rm.status {$statusOp} AND rm.tdate <= ?
                    AND {$this->controlCondition('repairm', 'rm', $controlMode)}{$stockFilter}
            ", [$code, $givrec, $date]);
            $this->applyMovement($movement, $this->signedMovement($row, $sign, $sign, $sign, $sign, 0, 0));
        }

        return $movement;
    }

    private function movementSmith(string $code, string $date, int $controlMode, ?string $stockType): array
    {
        if (!$this->tablesExist(['smithd', 'smithm'])) {
            return $this->zeroMovement();
        }

        $movement = $this->zeroMovement();
        $stockFilter = $this->stockTypeFilter('smithd', 'sd', $stockType);

        foreach ([['G', -1], ['R', 1]] as [$givrec, $sign]) {
            $row = $this->sumRow("
                SELECT SUM(COALESCE(sd.qty,0)) qty, SUM(COALESCE(sd.weight,0)) weight,
                    SUM(COALESCE(" . $this->colExpr('smithd', 'stonewgt', 'sd') . ",0)) stonewgt,
                    SUM(COALESCE(" . $this->colExpr('smithd', 'stoneprice', 'sd') . ",0)) stoneamt,
                    0 dmdwgt
                FROM smithd sd
                JOIN smithm sm ON sm.slno = sd.slno
                WHERE sd.code = ? AND sd.givrec = ? AND sm.tdate <= ?
                    AND {$this->controlCondition('smithm', 'sm', $controlMode)}{$stockFilter}
            ", [$code, $givrec, $date]);
            $this->applyMovement($movement, $this->signedMovement($row, $sign, $sign, $sign, $sign, 0, 0));
        }

        return $movement;
    }

    private function movementRefinery(string $code, string $date, bool $pending, int $controlMode, ?string $stockType): array
    {
        if (!$this->tablesExist(['refineryd', 'refinerym'])) {
            return $this->zeroMovement();
        }

        $movement = $this->zeroMovement();
        $statusOp = $pending ? '= 1' : '<> 1';
        $stockFilter = $this->stockTypeFilter('refineryd', 'rd', $stockType);

        $row = $this->sumRow("
            SELECT SUM(COALESCE(" . $this->colExpr('refineryd', 'issuedqty', 'rd') . ",0)) qty,
                SUM(COALESCE(" . $this->colExpr('refineryd', 'issuedwgt', 'rd') . ",0)) weight,
                SUM(COALESCE(" . $this->colExpr('refineryd', 'issuedstwgt', 'rd') . ",0)) stonewgt,
                0 stoneamt, 0 dmdwgt
            FROM refineryd rd
            JOIN refinerym rm ON rm.slno = rd.slno
            WHERE rd.code = ? AND rm.status {$statusOp} AND rm.tdate <= ?
                AND {$this->controlCondition('refinerym', 'rm', $controlMode)}{$stockFilter}
        ", [$code, $date]);
        $this->applyMovement($movement, $this->signedMovement($row, -1, -1, -1, 0, 0, 0));

        $row = $this->sumRow("
            SELECT SUM(COALESCE(" . $this->colExpr('refineryd', 'rcvdqty', 'rd') . ",0)) qty,
                SUM(COALESCE(" . $this->colExpr('refineryd', 'rcvdwgt', 'rd') . ",0) - COALESCE(" . $this->colExpr('refineryd', 'bottlestk', 'rd') . ",0) - COALESCE(" . $this->colExpr('refineryd', 'testpcs', 'rd') . ",0)) weight,
                0 stonewgt, 0 stoneamt, 0 dmdwgt
            FROM refineryd rd
            JOIN refinerym rm ON rm.slno = rd.slno
            WHERE rd.code = ? AND rm.status {$statusOp} AND rm.tdate <= ?
                AND {$this->controlCondition('refinerym', 'rm', $controlMode)}{$stockFilter}
        ", [$code, $date]);
        $this->applyMovement($movement, $this->signedMovement($row, 1, 1, 0, 0, 0, 0));

        if (trim($code) === 'BS') {
            $movement['weight'] += (float) ($this->sumValue("
                SELECT SUM(COALESCE(" . $this->colExpr('refineryd', 'bottlestk', 'rd') . ",0)) total
                FROM refineryd rd
                JOIN refinerym rm ON rm.slno = rd.slno
                WHERE rm.status {$statusOp} AND rm.tdate <= ?
                    AND {$this->controlCondition('refinerym', 'rm', $controlMode)}{$stockFilter}
            ", [$date]) ?? 0);
        }

        if (trim($code) === 'TP') {
            $movement['weight'] += (float) ($this->sumValue("
                SELECT SUM(COALESCE(" . $this->colExpr('refineryd', 'testpcs', 'rd') . ",0)) total
                FROM refineryd rd
                JOIN refinerym rm ON rm.slno = rd.slno
                WHERE rm.status {$statusOp} AND rm.tdate <= ?
                    AND {$this->controlCondition('refinerym', 'rm', $controlMode)}{$stockFilter}
            ", [$date]) ?? 0);
        }

        return $movement;
    }

    private function movementItemAdjustment(string $code, string $date, int $controlMode, ?string $stockType): array
    {
        if (!$this->hasTable('itemadj')) {
            return $this->zeroMovement();
        }

        $movement = $this->zeroMovement();
        $fromStockFilter = $this->stockTypeWhere('itemadj', 'fromstktype', $stockType);
        $toStockFilter = $this->stockTypeWhere('itemadj', 'tostktype', $stockType);

        $row = $this->sumRow("
            SELECT SUM(COALESCE(" . $this->colExpr('itemadj', 'fromqty') . ",0)) qty,
                SUM(COALESCE(" . $this->colExpr('itemadj', 'fromwgt') . ",0)) weight,
                SUM(COALESCE(" . $this->colExpr('itemadj', 'fromstwgt') . ",0)) stonewgt,
                SUM(COALESCE(" . $this->colExpr('itemadj', 'fromstamt') . ",0)) stoneamt,
                0 dmdwgt
            FROM itemadj
            WHERE fromcode = ? AND tdate <= ? AND {$this->controlCondition('itemadj', '', $controlMode)}{$fromStockFilter}
        ", [$code, $date]);
        $this->applyMovement($movement, $this->signedMovement($row, -1, -1, -1, -1, 0, 0));

        $row = $this->sumRow("
            SELECT SUM(COALESCE(" . $this->colExpr('itemadj', 'toqty') . ",0)) qty,
                SUM(COALESCE(" . $this->colExpr('itemadj', 'towgt') . ",0)) weight,
                SUM(COALESCE(" . $this->colExpr('itemadj', 'tostwgt') . ",0)) stonewgt,
                SUM(COALESCE(" . $this->colExpr('itemadj', 'tostamt') . ",0)) stoneamt,
                0 dmdwgt
            FROM itemadj
            WHERE tocode = ? AND tdate <= ? AND {$this->controlCondition('itemadj', '', $controlMode)}{$toStockFilter}
        ", [$code, $date]);
        $this->applyMovement($movement, $this->signedMovement($row, 1, 1, 1, 1, 0, 0));

        return $movement;
    }

    private function zeroMovement(): array
    {
        return ['qty' => 0.0, 'weight' => 0.0, 'stonewgt' => 0.0, 'stoneamt' => 0.0, 'dmdwgt' => 0.0];
    }

    private function signedMovement(object $row, int $qtySign, int $weightSign, int $stoneWgtSign, int $stoneAmtSign, int $dmdSign, float $stoneMarg): array
    {
        $stoneAmt = (float) ($row->stoneamt ?? 0);
        if ($stoneMarg !== 0.0 && $stoneAmt !== 0.0) {
            $stoneAmt -= ($stoneAmt * $stoneMarg) / 100;
        }

        return [
            'qty' => ((float) ($row->qty ?? 0)) * $qtySign,
            'weight' => ((float) ($row->weight ?? 0)) * $weightSign,
            'stonewgt' => ((float) ($row->stonewgt ?? 0)) * $stoneWgtSign,
            'stoneamt' => $stoneAmt * $stoneAmtSign,
            'dmdwgt' => ((float) ($row->dmdwgt ?? 0)) * $dmdSign,
        ];
    }

    private function applyMovement(array &$state, array $movement): void
    {
        foreach (['qty', 'weight', 'stonewgt', 'stoneamt', 'dmdwgt'] as $key) {
            $state[$key] = (float) ($state[$key] ?? 0) + (float) ($movement[$key] ?? 0);
        }
    }

    private function controlCondition(string $table, string $alias, int $controlMode): string
    {
        if (!$this->hasCol($table, 'control')) {
            return '1=1';
        }

        $prefix = $alias !== '' ? $alias . '.' : '';
        return $controlMode === 1 ? "{$prefix}control = 1" : "{$prefix}control <= 2";
    }

    private function stockTypeFilter(string $table, string $alias, ?string $stockType): string
    {
        if ($stockType === null || trim($stockType) === '' || !$this->hasCol($table, 'stktype')) {
            return '';
        }

        return " AND {$alias}.stktype = " . DB::getPdo()->quote($stockType);
    }

    private function stockTypeWhere(string $table, string $column, ?string $stockType): string
    {
        if ($stockType === null || trim($stockType) === '' || !$this->hasCol($table, $column)) {
            return '';
        }

        return " AND {$column} = " . DB::getPdo()->quote($stockType);
    }

    private function tablesExist(array $tables): bool
    {
        foreach ($tables as $table) {
            if (!$this->hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    private function hasCol(string $table, string $column): bool
    {
        $table = strtolower($table);
        if (!array_key_exists($table, $this->columnCache)) {
            $this->columnCache[$table] = $this->hasTable($table)
                ? array_map('strtolower', $this->columnList($table))
                : [];
        }

        return in_array(strtolower($column), $this->columnCache[$table], true);
    }

    private function colExpr(string $table, string $column, string $alias = ''): string
    {
        if (!$this->hasCol($table, $column)) {
            return '0';
        }

        return ($alias !== '' ? $alias . '.' : '') . $column;
    }

    private function sumRow(string $sql, array $bindings = []): object
    {
        return DB::selectOne($sql, $bindings) ?? (object) ['qty' => 0, 'weight' => 0, 'stonewgt' => 0, 'stoneamt' => 0, 'dmdwgt' => 0];
    }

    private function sumValue(string $sql, array $bindings = []): mixed
    {
        $row = DB::selectOne($sql, $bindings);
        return $row?->total;
    }

    private function softwareFlag(string $code, string $default = 'N'): bool
    {
        return strtoupper(trim($this->generalValue('generals', $code, $default))) === 'Y';
    }

    private function resetAccountTypeBalances(array $types): void
    {
        if ($this->hasTable('accountm')) {
            DB::table('accountm')->whereIn('actype1', $types)->update(['opbal' => 0, 'opbalb' => 0]);
        }
    }

    private function resetOpeningStock(): void
    {
        if ($this->hasTable('items')) {
            DB::table('items')->update([
                'opweight' => 0, 'opweightb' => 0, 'opqty' => 0, 'opqtyb' => 0,
                'opstonewgt' => 0, 'opstonewgtb' => 0, 'weight' => 0, 'weightb' => 0, 'qty' => 0, 'qtyb' => 0,
            ]);
        }
        if ($this->hasTable('itemsstk')) {
            DB::table('itemsstk')->update([
                'opweight' => 0, 'opweightb' => 0, 'opqty' => 0, 'opqtyb' => 0,
                'opstonewgt' => 0, 'opstonewgtb' => 0, 'weight' => 0, 'weightb' => 0, 'qty' => 0, 'qtyb' => 0,
            ]);
        }
    }

    private function deleteOutStockBarcodes(): int
    {
        if (!$this->hasTable('barcode')) {
            return 0;
        }

        $query = DB::table('barcode')->where('stk', 'N');
        if ($this->hasTable('modelm')) {
            $query->whereNotIn('bcode', function ($sub) {
                $sub->from('modelm')->select('bcode')->where('ir', 'I')->where('pend', 'Y');
            });
        }

        $bcodes = $query->pluck('bcode')->all();
        if (!$bcodes) {
            return 0;
        }

        if ($this->hasTable('barcodedmd')) {
            DB::table('barcodedmd')->whereIn('bcode', $bcodes)->delete();
        }

        return DB::table('barcode')->whereIn('bcode', $bcodes)->delete();
    }

    private function tableCount(string $table): int
    {
        return $this->hasTable($table) ? (int) DB::table($table)->count() : 0;
    }

    private function generalValue(string $table, string $code, string $default): string
    {
        if (!$this->hasTable($table)) {
            return $default;
        }

        $row = DB::table($table)->where('code', $code)->value('cvalue');
        return $row === null ? $default : trim((string) $row);
    }

    private function upsertGeneralInt(string $code, int $value): void
    {
        if (!$this->hasTable('generali')) {
            return;
        }

        if (DB::table('generali')->where('code', $code)->exists()) {
            DB::table('generali')->where('code', $code)->update(['cvalue' => $value]);
        } else {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => $value]);
        }
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
