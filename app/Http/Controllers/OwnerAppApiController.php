<?php

namespace App\Http\Controllers;

use App\Models\UserM;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OwnerAppApiController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    //  AUTH
    // ══════════════════════════════════════════════════════════════

    /** POST /api/owner/login  {user_code, password, database?} */
    public function login(Request $request): JsonResponse
    {
        $requestedTenantDomain = $this->tenantDomainFromRequest($request);
        $tenant = $this->resolveTenant($request);
        if ($requestedTenantDomain !== '' && !$tenant) {
            return response()->json(['ok' => false, 'message' => 'Tenant not found or inactive'], 404);
        }

        $this->switchDatabase((string) ($tenant['database_name'] ?? $request->input('database') ?? ''));

        $userCode = strtoupper(trim((string) $request->input('user_code', '')));
        $password  = trim((string) $request->input('password', ''));

        if ($password === '') {
            return response()->json(['ok' => false, 'message' => 'Password required'], 400);
        }
        if ($tenant) {
            if (!password_verify($password, (string) $tenant['password_hash'])) {
                return response()->json(['ok' => false, 'message' => 'Invalid credentials'], 401);
            }

            $user = (object) [
                'code' => $userCode !== '' ? $userCode : 'OWNER',
                'name' => $tenant['shop_name'],
            ];
        } else {
            if (!Schema::hasTable('userm')) {
                return response()->json(['ok' => false, 'message' => 'User table not found'], 500);
            }

            try {
                $user = $userCode !== ''
                    ? UserM::authenticateForCode($userCode, $password)
                    : UserM::authenticateLegacy($password);
            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'message' => 'Auth error: ' . $e->getMessage()], 500);
            }
        }

        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'Invalid credentials'], 401);
        }

        $db    = (string) config('database.connections.mysql.database', '');
        $token = Str::random(64);
        $expiresAt = now()->addDays(7);

        Cache::put("owner_token:{$token}", [
            'user_code' => trim((string) $user->code),
            'user_name' => trim((string) $user->name),
            'database'  => $db,
            'tenant_domain' => (string) ($tenant['domain'] ?? ''),
            'shop_name' => (string) ($tenant['shop_name'] ?? ''),
        ], $expiresAt);

        $this->storeOwnerSession($token, [
            'user_code' => trim((string) $user->code),
            'user_name' => trim((string) $user->name),
            'database'  => $db,
            'tenant_domain' => (string) ($tenant['domain'] ?? ''),
            'shop_name' => (string) ($tenant['shop_name'] ?? ''),
        ], $expiresAt);

        // Get shop info
        $shopName = (string) ($tenant['shop_name'] ?? '') ?: ($this->getGeneral('SHOPNM') ?: 'Gold Plus');

        return response()->json([
            'ok'        => true,
            'token'     => $token,
            'user_code' => trim((string) $user->code),
            'user_name' => trim((string) $user->name),
            'database'  => $db,
            'shop_name' => $shopName,
            'tenant_domain' => (string) ($tenant['domain'] ?? ''),
        ]);
    }

    /** GET /api/owner/tenant?domain= */
    public function tenantInfo(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        if (!$tenant) {
            return response()->json(['ok' => false, 'message' => 'Tenant not found'], 404);
        }

        return response()->json([
            'ok' => true,
            'domain' => $tenant['domain'],
            'shop_name' => $tenant['shop_name'],
            'database' => $tenant['database_name'],
        ]);
    }

    /** POST /api/owner/logout */
    public function logout(Request $request): JsonResponse
    {
        $token = $this->extractToken($request);
        if ($token) {
            Cache::forget("owner_token:{$token}");
            $this->deleteOwnerSession($token);
        }
        return response()->json(['ok' => true]);
    }

    /** GET /api/owner/users?database= */
    public function users(Request $request): JsonResponse
    {
        $this->switchDatabase((string) ($request->query('database') ?? ''));

        if (!Schema::hasTable('userm')) {
            return response()->json(['ok' => true, 'users' => []]);
        }

        $cols = ['code', 'name'];
        foreach (['email', 'mobile'] as $c) {
            if (Schema::hasColumn('userm', $c)) $cols[] = $c;
        }

        $users = DB::table('userm')
            ->select($cols)
            ->orderBy('code')
            ->get()
            ->map(fn ($u) => [
                'code' => trim((string) ($u->code ?? '')),
                'name' => trim((string) ($u->name ?? '')),
            ])
            ->values()
            ->toArray();

        return response()->json(['ok' => true, 'users' => $users]);
    }

    /** GET /api/owner/shop-info?database= */
    public function shopInfo(Request $request): JsonResponse
    {
        $this->switchDatabase((string) ($request->query('database') ?? ''));

        return response()->json([
            'ok'        => true,
            'shop_name' => $this->getGeneral('SHOPNM'),
            'address'   => $this->getGeneral('SHOPADDR'),
            'phone'     => $this->getGeneral('SHOPPHONE'),
            'gstin'     => $this->getGeneral('GSTIN') ?: $this->getGeneral('GSTNO'),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  DASHBOARD
    // ══════════════════════════════════════════════════════════════

    /** GET /api/owner/companies */
    public function companies(Request $request): JsonResponse
    {
        $this->ensureBranchTable();
        $auth = $this->authenticate($request);
        $allowedDatabases = $this->allowedCompanyDatabases((string) ($auth['user_code'] ?? ''));

        $companies = DB::table('owner_branches')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->filter(function ($r) use ($allowedDatabases): bool {
                if ($allowedDatabases === null) {
                    return true;
                }
                return in_array(strtolower(trim((string) ($r->db_name ?? ''))), $allowedDatabases, true);
            })
            ->map(fn ($r) => [
                'id'        => (int)    $r->id,
                'name'      => (string) $r->name,
                'api_url'   => (string) $r->api_url,
                'db_name'   => (string) $r->db_name,
                'is_active' => (bool)   $r->is_active,
                'note'      => (string) ($r->note ?? ''),
                'created_at'=> (string) $r->created_at,
            ])
            ->values()
            ->toArray();

        return response()->json([
            'ok' => true,
            'companies' => $companies,
            'message' => empty($companies) ? 'No company found' : '',
        ]);
    }

    /** GET /api/owner/dashboard */
    public function dashboard(Request $request): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        $this->applyAuthDatabase($auth);
        $this->relaxGroupBy();

        $today = date('Y-m-d');
        $month = date('Y-m-01');

        $todaySales = 0.0; $monthSales = 0.0; $todayBills = 0;
        $todaySalesReturn = 0.0;
        $todaySalesWgt = 0.0;
        $todaySalesReturnWgt = 0.0;
        $todayOgPurchaseWgt = 0.0;
        $todayOgPurchaseAmt = 0.0;
        $todayOgReturnWgt = 0.0;
        $todayOgReturnAmt = 0.0;
        $todayCash = 0.0;
        $todayBank = 0.0;

        if (Schema::hasTable('salesm')) {
            // status is smallint: 1=active, 0=cancelled
            $statusCol = Schema::hasColumn('salesm', 'status') ? 'status' : null;
            $r = DB::table('salesm')
                ->selectRaw("COALESCE(SUM(netamt),0) as amt, COUNT(*) as cnt")
                ->where('tdate', $today)
                ->when($statusCol, fn ($q) => $q->where('status', '!=', 0))
                ->first();
            $todaySales = (float) ($r->amt ?? 0);
            $todayBills = (int) ($r->cnt ?? 0);

            if (Schema::hasTable('salesd') && Schema::hasColumn('salesd', 'weight')) {
                $wgt = DB::table('salesd as d')
                    ->join('salesm as m', 'm.slno', '=', 'd.slno')
                    ->selectRaw('COALESCE(SUM(d.weight),0) as wgt')
                    ->where('m.tdate', $today)
                    ->when($statusCol, fn ($q) => $q->where('m.status', '!=', 0))
                    ->first();
                $todaySalesWgt = (float) ($wgt->wgt ?? 0);
            }

            $mr = DB::table('salesm')
                ->selectRaw("COALESCE(SUM(netamt),0) as amt")
                ->whereBetween('tdate', [$month, $today])
                ->when($statusCol, fn ($q) => $q->where('status', '!=', 0))
                ->first();
            $monthSales = (float) ($mr->amt ?? 0);
        }

        if (Schema::hasTable('salesrm')) {
            $statusCol = Schema::hasColumn('salesrm', 'status') ? 'status' : null;
            $amountExpr = Schema::hasColumn('salesrm', 'netamt')
                ? 'COALESCE(netamt,0)'
                : '(COALESCE(billamt,0) - COALESCE(discount,0) + COALESCE(staxamt,0) + COALESCE(astamt,0))';

            $sr = DB::table('salesrm')
                ->selectRaw("COALESCE(SUM({$amountExpr}),0) as amt")
                ->where('tdate', $today)
                ->when($statusCol, fn ($q) => $q->where('status', '!=', 0))
                ->first();
            $todaySalesReturn = (float) ($sr->amt ?? 0);

            if (Schema::hasTable('salesrd') && Schema::hasColumn('salesrd', 'weight')) {
                $rwgt = DB::table('salesrd as d')
                    ->join('salesrm as m', 'm.slno', '=', 'd.slno')
                    ->selectRaw('COALESCE(SUM(d.weight),0) as wgt')
                    ->where('m.tdate', $today)
                    ->when($statusCol, fn ($q) => $q->where('m.status', '!=', 0))
                    ->first();
                $todaySalesReturnWgt = (float) ($rwgt->wgt ?? 0);
            }
        }

        if (Schema::hasTable('purchased') && Schema::hasTable('purchasem')) {
            $statusCol = Schema::hasColumn('purchasem', 'status') ? 'm.status' : null;
            $amountCol = Schema::hasColumn('purchased', 'amount') ? 'd.amount' : '0';
            $og = DB::table('purchased as d')
                ->join('purchasem as m', 'm.slno', '=', 'd.slno')
                ->selectRaw("COALESCE(SUM(COALESCE(d.weight,0)),0) as wgt, COALESCE(SUM(COALESCE({$amountCol},0)),0) as amt")
                ->where('m.tdate', $today)
                ->whereRaw("UPPER(TRIM(d.code)) = 'OG'")
                ->when($statusCol, fn ($q) => $q->where($statusCol, '!=', 0))
                ->first();
            $todayOgPurchaseWgt = (float) ($og->wgt ?? 0);
            $todayOgPurchaseAmt = (float) ($og->amt ?? 0);
        }

        if (Schema::hasTable('salesrd') && Schema::hasTable('salesrm')) {
            $statusCol = Schema::hasColumn('salesrm', 'status') ? 'm.status' : null;
            $og = DB::table('salesrd as d')
                ->join('salesrm as m', 'm.slno', '=', 'd.slno')
                ->selectRaw('COALESCE(SUM(COALESCE(d.weight,0)),0) as wgt, COALESCE(SUM(COALESCE(d.amount,0)),0) as amt')
                ->where('m.tdate', $today)
                ->whereRaw("UPPER(TRIM(d.code)) = 'OG'")
                ->when($statusCol, fn ($q) => $q->where($statusCol, '!=', 0))
                ->first();
            $todayOgReturnWgt = (float) ($og->wgt ?? 0);
            $todayOgReturnAmt = (float) ($og->amt ?? 0);
        }

        // Today's cash and bank movement from daybook. Account type H = cash in hand, B = bank.
        $todayReceipts = 0.0;
        if (Schema::hasTable('daybook')) {
            $rr = DB::table('daybook')
                ->selectRaw("COALESCE(SUM(CASE WHEN amount>0 THEN amount ELSE 0 END),0) as a")
                ->where('tdate', $today)
                ->where('control', 1)
                ->whereIn('accode', ['CASH', 'BANK', 'HDFC', 'SBI', 'AXIS'])
                ->first();
            $todayReceipts = (float) ($rr->a ?? 0);

            if (Schema::hasTable('accountm') && Schema::hasColumn('accountm', 'actype2')) {
                $cashBankRows = DB::table('daybook as d')
                    ->join('accountm as a', DB::raw('TRIM(a.accode)'), '=', DB::raw('TRIM(d.accode)'))
                    ->selectRaw("
                        COALESCE(SUM(CASE WHEN UPPER(TRIM(a.actype2))='H' THEN d.amount ELSE 0 END),0) as cash_amt,
                        COALESCE(SUM(CASE WHEN UPPER(TRIM(a.actype2))='B' THEN d.amount ELSE 0 END),0) as bank_amt
                    ")
                    ->where('d.tdate', $today)
                    ->where('d.control', '<=', 1)
                    ->first();
                $todayCash = -(float) ($cashBankRows->cash_amt ?? 0);
                $todayBank = -(float) ($cashBankRows->bank_amt ?? 0);
            } else {
                $cashRow = DB::table('daybook')
                    ->selectRaw("COALESCE(SUM(amount),0) as amt")
                    ->where('tdate', $today)
                    ->where('control', '<=', 1)
                    ->where('accode', 'CASH')
                    ->first();
                $todayCash = -(float) ($cashRow->amt ?? 0);
            }
        }

        // Total receivable = sum of positive customer balances from daybook
        $totalReceivable = 0.0;
        if (Schema::hasTable('daybook')) {
            $br = DB::table('daybook')
                ->selectRaw("accode, SUM(amount) as bal")
                ->where('control', 1)
                ->whereRaw("accode NOT IN ('CASH','RS','SLNA','WC-19015','CARA','BANK')")
                ->groupBy('accode')
                ->havingRaw("SUM(amount) > 0")
                ->get();
            $totalReceivable = (float) $br->sum('bal');
        }

        return response()->json([
            'ok'               => true,
            'today'            => $today,
            'today_sales'        => $todaySales,
            'today_sales_wgt'    => $todaySalesWgt,
            'today_bills'        => $todayBills,
            'today_sales_return' => $todaySalesReturn,
            'today_sales_return_wgt' => $todaySalesReturnWgt,
            'today_og_purchase_wgt' => $todayOgPurchaseWgt,
            'today_og_purchase_amt' => $todayOgPurchaseAmt,
            'today_og_return_wgt' => $todayOgReturnWgt,
            'today_og_return_amt' => $todayOgReturnAmt,
            'today_cash'       => $todayCash,
            'today_bank'       => $todayBank,
            'month_sales'      => $monthSales,
            'today_receipts'   => $todayReceipts,
            'total_receivable' => $totalReceivable,
            'rates'            => $this->getCurrentRates(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  RECEIVABLE / PAYABLE
    // ══════════════════════════════════════════════════════════════

    /** GET /api/owner/receivable-payable?type=receivable&from=&to= */
    public function receivablePayable(Request $request): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        $this->applyAuthDatabase($auth);
        $this->relaxGroupBy();

        $type = trim((string) $request->query('type', 'receivable'));
        $from = $this->safeDate($request->query('from', ''), date('Y') . '-04-01');
        $to   = $this->safeDate($request->query('to', ''),   date('Y-m-d'));

        if (!Schema::hasTable('clients')) {
            return response()->json(['ok' => true, 'type' => $type, 'rows' => [], 'total' => 0, 'count' => 0]);
        }

        // Build balances from daybook (positive = receivable, negative = payable)
        $balances = [];
        if (Schema::hasTable('daybook')) {
            $dbRows = DB::table('daybook')
                ->selectRaw('TRIM(accode) as accode, SUM(amount) as bal')
                ->where('control', 1)
                ->groupBy('accode')
                ->havingRaw($type === 'receivable' ? 'SUM(amount) > 0.01' : 'SUM(amount) < -0.01')
                ->get();
            foreach ($dbRows as $dbr) {
                $balances[trim((string) $dbr->accode)] = (float) $dbr->bal;
            }
        }

        if (empty($balances)) {
            return response()->json(['ok' => true, 'type' => $type, 'rows' => [], 'total' => 0, 'count' => 0]);
        }

        // Fetch client names for those codes
        $codes = array_keys($balances);
        $clientMap = DB::table('clients')
            ->whereIn(DB::raw('TRIM(code)'), $codes)
            ->get(['code', 'name', 'mobile', 'carea'])
            ->keyBy(fn ($c) => trim((string) $c->code))
            ->toArray();

        $rows = [];
        foreach ($balances as $code => $bal) {
            $c = $clientMap[$code] ?? null;
            if (!$c && strlen($code) > 8) continue; // skip non-client codes (CASH, RS, etc.)
            $rows[] = [
                'code'      => $code,
                'name'      => $c ? trim((string) $c->name) : $code,
                'mobile'    => $c ? trim((string) ($c->mobile ?? '')) : '',
                'area'      => $c ? trim((string) ($c->carea ?? '')) : '',
                'balance'   => $bal,
                'last_bill' => null,
            ];
        }

        // Sort by absolute balance descending
        usort($rows, fn ($a, $b) => abs($b['balance']) <=> abs($a['balance']));
        $rows = array_slice($rows, 0, 300);

        $total = array_sum(array_column($rows, 'balance'));

        return response()->json([
            'ok'    => true,
            'type'  => $type,
            'rows'  => $rows,
            'total' => $total,
            'count' => count($rows),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  STOCK SUMMARY
    // ══════════════════════════════════════════════════════════════

    /** GET /api/owner/stock-summary */
    public function stockSummary(Request $request): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        $this->applyAuthDatabase($auth);
        $this->relaxGroupBy();

        if (!Schema::hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        // items table uses: weight (current stock wgt), qty (current stock qty), cost
        $hasGrp  = Schema::hasTable('itemgrp');
        $hasCost = Schema::hasColumn('items', 'cost');
        $hasWgt  = Schema::hasColumn('items', 'weight');
        $hasQty  = Schema::hasColumn('items', 'qty');

        $q = DB::table('items as i');
        if ($hasGrp) {
            $q->leftJoin('itemgrp as g', DB::raw('TRIM(g.code)'), '=', DB::raw('TRIM(i.grpcode)'));
        }

        $selects = [
            DB::raw('TRIM(i.grpcode) as grpcode'),
            $hasGrp
                ? DB::raw('COALESCE(TRIM(g.name), TRIM(i.grpcode)) as grpname')
                : DB::raw('TRIM(i.grpcode) as grpname'),
            $hasQty  ? DB::raw('SUM(COALESCE(i.qty,0)) as qty')      : DB::raw('0 as qty'),
            $hasWgt  ? DB::raw('SUM(COALESCE(i.weight,0)) as wgt')   : DB::raw('0 as wgt'),
            ($hasWgt && $hasCost)
                ? DB::raw('SUM(COALESCE(i.weight,0)*COALESCE(i.cost,0)) as cost_amt')
                : DB::raw('0 as cost_amt'),
        ];

        $rows = $q->select($selects)
            ->when($hasWgt || $hasQty, function ($q2) use ($hasWgt, $hasQty) {
                if ($hasWgt && $hasQty) {
                    $q2->whereRaw('COALESCE(i.weight,0)+COALESCE(i.qty,0) > 0');
                } elseif ($hasWgt) {
                    $q2->whereRaw('COALESCE(i.weight,0) > 0');
                } else {
                    $q2->whereRaw('COALESCE(i.qty,0) > 0');
                }
            })
            ->groupBy('i.grpcode')
            ->orderByDesc('wgt')
            ->get()
            ->map(fn ($r) => [
                'grpcode'  => (string) $r->grpcode,
                'grpname'  => (string) $r->grpname,
                'qty'      => (float) $r->qty,
                'wgt'      => (float) $r->wgt,
                'cost_amt' => (float) $r->cost_amt,
            ])
            ->toArray();

        $totals = [
            'qty'      => array_sum(array_column($rows, 'qty')),
            'wgt'      => array_sum(array_column($rows, 'wgt')),
            'cost_amt' => array_sum(array_column($rows, 'cost_amt')),
        ];

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    // ══════════════════════════════════════════════════════════════
    //  GOLDSMITH / SMITH SUMMARY
    // ══════════════════════════════════════════════════════════════

    /** GET /api/owner/goldsmith-summary?from=&to= */
    public function goldsmithSummary(Request $request): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        $this->applyAuthDatabase($auth);
        $this->relaxGroupBy();

        $from = $this->safeDate($request->query('from', ''), date('Y') . '-04-01');
        $to   = $this->safeDate($request->query('to', ''),   date('Y-m-d'));

        $rows = [];

        // Try goldsmith transactions table (GoldsmithTransactionsController data source)
        if (Schema::hasTable('goldsmithtransm')) {
            $rows = $this->smithFromGoldsmithtransm($from, $to);
        } elseif (Schema::hasTable('smithm')) {
            $rows = $this->smithFromSmithm($from, $to);
        }

        // Fallback: try clients with a type flag
        if (empty($rows) && Schema::hasTable('clients')) {
            $rows = $this->smithFromClients();
        }

        $totals = [
            'issued_wgt'   => array_sum(array_column($rows, 'issued_wgt')),
            'received_wgt' => array_sum(array_column($rows, 'received_wgt')),
            'balance_wgt'  => array_sum(array_column($rows, 'balance_wgt')),
            'amount'       => array_sum(array_column($rows, 'amount')),
        ];

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    // ══════════════════════════════════════════════════════════════
    //  ITEM MOVEMENT
    // ══════════════════════════════════════════════════════════════

    /** GET /api/owner/item-movement?from=&to= */
    public function itemMovement(Request $request): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        $this->applyAuthDatabase($auth);
        $this->relaxGroupBy();

        $from = $this->safeDate($request->query('from', ''), date('Y-m-01'));
        $to   = $this->safeDate($request->query('to', ''),   date('Y-m-d'));

        if (!Schema::hasTable('salesd') || !Schema::hasTable('salesm')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $hasItems = Schema::hasTable('items');
        $hasGrp   = Schema::hasTable('itemgrp');

        $q = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno');

        if ($hasItems) {
            $q->leftJoin('items as i', DB::raw('TRIM(i.code)'), '=', DB::raw('TRIM(d.code)'));
            if ($hasGrp) {
                $q->leftJoin('itemgrp as g', DB::raw('TRIM(g.code)'), '=', DB::raw('TRIM(i.grpcode)'));
            }
        }

        $select = [
            DB::raw('TRIM(d.code) as item_code'),
            $hasItems
                ? DB::raw('COALESCE(TRIM(i.name), TRIM(d.code)) as item_name')
                : DB::raw('TRIM(d.code) as item_name'),
            ($hasItems && $hasGrp)
                ? DB::raw('COALESCE(TRIM(g.name), TRIM(i.grpcode),"—") as grp_name')
                : DB::raw('"—" as grp_name'),
            DB::raw('COUNT(DISTINCT m.slno) as bill_count'),
            DB::raw('SUM(COALESCE(d.qty,0)) as qty'),
            DB::raw('SUM(COALESCE(d.weight,0)) as wgt'),
            DB::raw('SUM(COALESCE(d.amount,0)) as amount'),
        ];

        $rows = $q->select($select)
            ->whereBetween('m.tdate', [$from, $to])
            ->where('m.status', '!=', 0)
            ->groupBy('d.code')
            ->orderByDesc(DB::raw('SUM(COALESCE(d.weight,0))'))
            ->limit(300)
            ->get()
            ->map(fn ($r) => [
                'item_code'  => (string) $r->item_code,
                'item_name'  => (string) $r->item_name,
                'grp_name'   => (string) $r->grp_name,
                'bill_count' => (int) $r->bill_count,
                'qty'        => (float) $r->qty,
                'wgt'        => (float) $r->wgt,
                'amount'     => (float) $r->amount,
            ])
            ->toArray();

        $totals = [
            'qty'    => array_sum(array_column($rows, 'qty')),
            'wgt'    => array_sum(array_column($rows, 'wgt')),
            'amount' => array_sum(array_column($rows, 'amount')),
        ];

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    // ══════════════════════════════════════════════════════════════
    //  STAFF / SALESMAN SALES
    // ══════════════════════════════════════════════════════════════

    /** GET /api/owner/staff-sales?from=&to= */
    public function staffSales(Request $request): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        $this->applyAuthDatabase($auth);
        $this->relaxGroupBy();

        $from = $this->safeDate($request->query('from', ''), date('Y-m-01'));
        $to   = $this->safeDate($request->query('to', ''),   date('Y-m-d'));

        if (!Schema::hasTable('salesm')) {
            return response()->json(['ok' => true, 'rows' => [], 'categories' => [], 'totals' => []]);
        }

        $hasSman = Schema::hasTable('sman');

        $q = DB::table('salesm as m');
        if ($hasSman) {
            $q->leftJoin('sman as s', DB::raw('TRIM(s.code)'), '=', DB::raw('TRIM(m.smcode)'));
        }

        $select = [
            DB::raw('TRIM(m.smcode) as sm_code'),
            $hasSman
                ? DB::raw('COALESCE(TRIM(s.name), TRIM(m.smcode), "—") as sm_name')
                : DB::raw('COALESCE(TRIM(m.smcode),"—") as sm_name'),
            $hasSman
                ? DB::raw('COALESCE(TRIM(s.cat),"") as category')
                : DB::raw('"" as category'),
            DB::raw('COUNT(*) as bill_count'),
            DB::raw('SUM(COALESCE(m.billamt,0)) as bill_amt'),
            DB::raw('SUM(COALESCE(m.discount,0)) as discount'),
            DB::raw('SUM(COALESCE(m.netamt,0)) as net_amt'),
            DB::raw('AVG(COALESCE(m.netamt,0)) as avg_bill'),
            $hasSman
                ? DB::raw('SUM(COALESCE(m.billamt,0)) * MAX(COALESCE(s.comn,0)) / 100 as commission')
                : DB::raw('0 as commission'),
        ];

        $rows = $q->select($select)
            ->whereBetween('m.tdate', [$from, $to])
            ->where('m.status', '!=', 0)
            ->groupBy('m.smcode')
            ->orderByDesc(DB::raw('SUM(COALESCE(m.netamt,0))'))
            ->get()
            ->map(fn ($r) => [
                'sm_code'    => (string) $r->sm_code,
                'sm_name'    => (string) $r->sm_name,
                'category'   => (string) $r->category,
                'bill_count' => (int) $r->bill_count,
                'bill_amt'   => (float) $r->bill_amt,
                'discount'   => (float) $r->discount,
                'net_amt'    => (float) $r->net_amt,
                'avg_bill'   => (float) $r->avg_bill,
                'commission' => (float) $r->commission,
            ])
            ->toArray();

        if (Schema::hasTable('salesd') && Schema::hasTable('items')) {
            $amountRows = collect($rows)->keyBy(fn ($r) => strtoupper(trim((string) $r['sm_code'])));
            $rows = array_map(function ($r) use ($amountRows) {
                $key = strtoupper(trim((string) $r['sm_code']));
                if (!$amountRows->has($key)) return $r;
                $base = $amountRows[$key];
                foreach (['sm_name', 'category', 'bill_count', 'bill_amt', 'discount', 'net_amt', 'avg_bill', 'commission'] as $field) {
                    $r[$field] = $base[$field];
                }
                return $r;
            }, $this->staffSalesDetailRows($from, $to, $hasSman, Schema::hasTable('salestype')));
        } else {
            $rows = array_map(fn ($r) => array_merge($this->emptyStaffSalesMetrics(), $r), $rows);
        }

        // Category totals
        $catMap = [];
        foreach ($rows as $r) {
            $cat = $r['category'] ?: 'General';
            if (!isset($catMap[$cat])) {
                $catMap[$cat] = $this->emptyStaffSalesCategory($cat);
            }
            $catMap[$cat]['bill_count'] += $r['bill_count'];
            $catMap[$cat]['net_amt']    += $r['net_amt'];
            $catMap[$cat]['discount'] += $r['discount'];
            $catMap[$cat]['commission'] += $r['commission'];
            $catMap[$cat]['gold_net_weight'] += $r['gold_net_weight'];
            $catMap[$cat]['gold18_net_weight'] += $r['gold18_net_weight'];
            $catMap[$cat]['silver_net_weight'] += $r['silver_net_weight'];
            $catMap[$cat]['diamond_net_amount'] += $r['diamond_net_amount'];
            $catMap[$cat]['sm_count']++;
        }
        usort($catMap, fn ($a, $b) => $b['net_amt'] <=> $a['net_amt']);

        $totals = $this->staffSalesTotals($rows);

        return response()->json([
            'ok'         => true,
            'rows'       => $rows,
            'categories' => array_values($catMap),
            'totals'     => $totals,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════

    private function staffSalesDetailRows(string $from, string $to, bool $hasSman, bool $hasSalesType): array
    {
        $billType = "UPPER(TRIM(COALESCE(m.billtype,'')))";
        $billTypeName = $hasSalesType ? "UPPER(TRIM(COALESCE(bt.name,'')))" : "''";
        $billPrefix = $hasSalesType ? "UPPER(TRIM(COALESCE(bt.prefix,'')))" : "''";
        $billNo = "UPPER(TRIM(COALESCE(m.billno,'')))";
        $itemCode = "UPPER(TRIM(COALESCE(d.code,'')))";
        $itemName = "UPPER(TRIM(COALESCE(i.name,'')))";
        $itemGroup = "UPPER(TRIM(COALESCE(i.grpcode,'')))";
        $itemBillType = "UPPER(TRIM(COALESCE(i.billtype,'')))";
        $isB2b = "(($billType LIKE 'B2%') OR ($billTypeName LIKE '%B2B%') OR ($billPrefix LIKE 'B2B/%') OR ($billNo LIKE 'B2B/%'))";
        $is18k = "(($billType = 'G18') OR ($billType = 'B2B18') OR ($itemGroup = '18K') OR ($itemCode LIKE '18K%') OR ($itemName LIKE '18K%'))";
        $isDiamond = "(($billType IN ('D','B2D')) OR ($itemGroup = 'DM') OR ($itemBillType = 'D') OR ($itemCode LIKE 'DM%') OR ($itemName LIKE '%DIAMOND%') OR ($itemName LIKE '%DIMOND%'))";
        $isSilver = "(($billType IN ('S','B2BSIL')) OR ($itemGroup IN ('S','SIL','OS')) OR ($itemBillType = 'S') OR ($itemName LIKE '%SILVER%'))";
        $isPerfume = "(($billType IN ('P','B2BP')) OR ($itemGroup = 'PF') OR ($itemCode LIKE 'PF%') OR ($itemName LIKE '%PERFUME%'))";
        $isWatch = "(($billType IN ('W','B2BW')) OR ($itemGroup = 'WT') OR ($itemCode LIKE 'WT%') OR ($itemName LIKE '%WATCH%'))";
        $isGold = "((($billType IN ('G','GOLD1','B2G')) OR ($itemGroup = 'GO') OR ($itemBillType = 'G')) AND NOT $is18k AND NOT $isDiamond AND NOT $isSilver AND NOT $isPerfume AND NOT $isWatch)";
        $active = "COALESCE(m.status, 1) <> 0 AND UPPER(TRIM(COALESCE(m.status,''))) <> 'C'";
        $diamondSaleAmount = Schema::hasColumn('salesd', 'stoneprice') ? 'COALESCE(d.stoneprice,0)' : 'COALESCE(d.amount,0)';
        $commissionExpr = $hasSman ? 'COALESCE(MAX(s.comn),0)' : '0';

        $q = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->leftJoin('items as i', 'i.code', '=', 'd.code');

        if ($hasSman) {
            $q->leftJoin('sman as s', DB::raw('TRIM(s.code)'), '=', DB::raw('TRIM(m.smcode)'));
        }
        if ($hasSalesType) {
            $q->leftJoin('salestype as bt', 'bt.code', '=', 'm.billtype');
        }

        $rows = $q->select(
                DB::raw('TRIM(m.smcode) as sm_code'),
                $hasSman
                    ? DB::raw('COALESCE(MAX(NULLIF(TRIM(s.name),"")), TRIM(m.smcode), "-") as sm_name')
                    : DB::raw('COALESCE(TRIM(m.smcode), "-") as sm_name'),
                $hasSman ? DB::raw('COALESCE(MAX(TRIM(s.cat)), "") as category') : DB::raw('"" as category'),
                DB::raw("COUNT(DISTINCT CASE WHEN $active THEN m.slno END) as bill_count"),
                DB::raw("SUM(CASE WHEN $active THEN COALESCE(m.billamt,0) ELSE 0 END) as bill_amt"),
                DB::raw("SUM(CASE WHEN $active THEN COALESCE(m.discount,0) ELSE 0 END) as discount"),
                DB::raw("SUM(CASE WHEN $active THEN COALESCE(m.netamt,0) ELSE 0 END) as net_amt"),
                DB::raw("AVG(CASE WHEN $active THEN COALESCE(m.netamt,0) ELSE NULL END) as avg_bill"),
                DB::raw("SUM(CASE WHEN $active AND $isGold THEN COALESCE(d.weight,0) ELSE 0 END) as gold_weight"),
                DB::raw("SUM(CASE WHEN $active AND $is18k THEN COALESCE(d.weight,0) ELSE 0 END) as gold18_weight"),
                DB::raw("SUM(CASE WHEN $active AND $isSilver THEN COALESCE(d.weight,0) ELSE 0 END) as silver_weight"),
                DB::raw("SUM(CASE WHEN $active AND $isDiamond THEN $diamondSaleAmount ELSE 0 END) as diamond_amount"),
                DB::raw("SUM(CASE WHEN $active AND $isB2b THEN COALESCE(d.weight,0) ELSE 0 END) as b2b_weight"),
                DB::raw("SUM(CASE WHEN $active THEN COALESCE(m.billamt,0) ELSE 0 END) * $commissionExpr / 100 as commission")
            )
            ->whereBetween('m.tdate', [$from, $to])
            ->groupBy('m.smcode')
            ->orderByDesc(DB::raw('SUM(COALESCE(m.netamt,0))'))
            ->get()
            ->keyBy(fn ($r) => strtoupper(trim((string) $r->sm_code)));

        if (Schema::hasTable('salesrm') && Schema::hasTable('salesrd')) {
            $diamondReturnAmount = Schema::hasColumn('salesrd', 'stoneprice') ? 'COALESCE(d.stoneprice,0)' : 'COALESCE(d.amount,0)';
            $rq = DB::table('salesrd as d')
                ->join('salesrm as m', 'm.slno', '=', 'd.slno')
                ->leftJoin('items as i', 'i.code', '=', 'd.code');

            if ($hasSman) {
                $rq->leftJoin('sman as s', DB::raw('TRIM(s.code)'), '=', DB::raw('TRIM(m.smcode)'));
            }
            if ($hasSalesType) {
                $rq->leftJoin('salestype as bt', 'bt.code', '=', 'm.billtype');
            }

            $returnRows = $rq->select(
                    DB::raw('TRIM(m.smcode) as sm_code'),
                    DB::raw("SUM(CASE WHEN $active AND UPPER(TRIM(COALESCE(m.sr,''))) = 'R' AND $isGold THEN COALESCE(d.weight,0) ELSE 0 END) as gold_return_weight"),
                    DB::raw("SUM(CASE WHEN $active AND UPPER(TRIM(COALESCE(m.sr,''))) = 'R' AND $is18k THEN COALESCE(d.weight,0) ELSE 0 END) as gold18_return_weight"),
                    DB::raw("SUM(CASE WHEN $active AND UPPER(TRIM(COALESCE(m.sr,''))) = 'R' AND $isSilver THEN COALESCE(d.weight,0) ELSE 0 END) as silver_return_weight"),
                    DB::raw("SUM(CASE WHEN $active AND UPPER(TRIM(COALESCE(m.sr,''))) = 'R' AND $isDiamond THEN $diamondReturnAmount ELSE 0 END) as diamond_return_amount")
                )
                ->whereBetween('m.tdate', [$from, $to])
                ->groupBy('m.smcode')
                ->get();

            foreach ($returnRows as $ret) {
                $key = strtoupper(trim((string) $ret->sm_code));
                if (!$rows->has($key)) continue;
                $rows[$key]->gold_return_weight = (float) $ret->gold_return_weight;
                $rows[$key]->gold18_return_weight = (float) $ret->gold18_return_weight;
                $rows[$key]->silver_return_weight = (float) $ret->silver_return_weight;
                $rows[$key]->diamond_return_amount = (float) $ret->diamond_return_amount;
            }
        }

        return $rows->values()->map(function ($r) {
            $base = [
                'sm_code' => (string) $r->sm_code,
                'sm_name' => (string) $r->sm_name,
                'category' => (string) $r->category,
                'bill_count' => (int) $r->bill_count,
                'bill_amt' => (float) $r->bill_amt,
                'discount' => (float) $r->discount,
                'net_amt' => (float) $r->net_amt,
                'avg_bill' => (float) $r->avg_bill,
                'commission' => (float) ($r->commission ?? 0),
            ];
            $metrics = $this->emptyStaffSalesMetrics();
            $metrics['gold_weight'] = (float) ($r->gold_weight ?? 0);
            $metrics['gold_return_weight'] = (float) ($r->gold_return_weight ?? 0);
            $metrics['gold_net_weight'] = $metrics['gold_weight'] - $metrics['gold_return_weight'];
            $metrics['gold18_weight'] = (float) ($r->gold18_weight ?? 0);
            $metrics['gold18_return_weight'] = (float) ($r->gold18_return_weight ?? 0);
            $metrics['gold18_net_weight'] = $metrics['gold18_weight'] - $metrics['gold18_return_weight'];
            $metrics['silver_weight'] = (float) ($r->silver_weight ?? 0);
            $metrics['silver_return_weight'] = (float) ($r->silver_return_weight ?? 0);
            $metrics['silver_net_weight'] = $metrics['silver_weight'] - $metrics['silver_return_weight'];
            $metrics['diamond_amount'] = (float) ($r->diamond_amount ?? 0);
            $metrics['diamond_return_amount'] = (float) ($r->diamond_return_amount ?? 0);
            $metrics['diamond_net_amount'] = $metrics['diamond_amount'] - $metrics['diamond_return_amount'];
            $metrics['b2b_weight'] = (float) ($r->b2b_weight ?? 0);
            return array_merge($base, $metrics);
        })->toArray();
    }

    private function emptyStaffSalesMetrics(): array
    {
        return [
            'commission' => 0.0,
            'gold_weight' => 0.0,
            'gold_return_weight' => 0.0,
            'gold_net_weight' => 0.0,
            'gold18_weight' => 0.0,
            'gold18_return_weight' => 0.0,
            'gold18_net_weight' => 0.0,
            'silver_weight' => 0.0,
            'silver_return_weight' => 0.0,
            'silver_net_weight' => 0.0,
            'diamond_amount' => 0.0,
            'diamond_return_amount' => 0.0,
            'diamond_net_amount' => 0.0,
            'b2b_weight' => 0.0,
        ];
    }

    private function emptyStaffSalesCategory(string $category): array
    {
        return [
            'category' => $category,
            'bill_count' => 0,
            'net_amt' => 0.0,
            'discount' => 0.0,
            'commission' => 0.0,
            'gold_net_weight' => 0.0,
            'gold18_net_weight' => 0.0,
            'silver_net_weight' => 0.0,
            'diamond_net_amount' => 0.0,
            'sm_count' => 0,
        ];
    }

    private function staffSalesTotals(array $rows): array
    {
        $totals = [
            'bill_count' => array_sum(array_column($rows, 'bill_count')),
            'bill_amt' => array_sum(array_column($rows, 'bill_amt')),
            'discount' => array_sum(array_column($rows, 'discount')),
            'net_amt' => array_sum(array_column($rows, 'net_amt')),
        ];

        foreach (array_keys($this->emptyStaffSalesMetrics()) as $key) {
            $totals[$key] = array_sum(array_column($rows, $key));
        }

        return $totals;
    }

    /** GET /api/owner/financial-report?type=balance|profit|trial&to= */
    public function financialReport(Request $request): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        $this->applyAuthDatabase($auth);
        $this->relaxGroupBy();

        $type = strtolower(trim((string) $request->query('type', 'balance')));
        $to = $this->safeDate($request->query('to', ''), date('Y-m-d'));

        if (!Schema::hasTable('accountm')) {
            return response()->json(['ok' => true, 'type' => $type, 'rows' => [], 'sections' => [], 'totals' => []]);
        }

        $rows = $this->accountStatementRows($to);
        if ($type === 'profit') {
            $rows = array_values(array_filter($rows, fn ($r) => in_array($r['type'], ['R', 'E'], true)));
            $sections = $this->sectionTotals($rows, ['R' => 'Income', 'E' => 'Expenses']);
            $income = abs($sections['R']['amount'] ?? 0);
            $expense = abs($sections['E']['amount'] ?? 0);
            $totals = ['income' => $income, 'expense' => $expense, 'net_profit' => $income - $expense];
        } elseif ($type === 'trial') {
            $sections = $this->sectionTotals($rows, ['A' => 'Assets', 'L' => 'Liabilities', 'R' => 'Income', 'E' => 'Expenses']);
            $debit = 0.0;
            $credit = 0.0;
            foreach ($rows as $row) {
                if (($row['balance'] ?? 0) >= 0) $debit += (float) $row['balance'];
                else $credit += abs((float) $row['balance']);
            }
            $totals = ['debit' => $debit, 'credit' => $credit, 'difference' => $debit - $credit];
        } else {
            $type = 'balance';
            $rows = array_values(array_filter($rows, fn ($r) => in_array($r['type'], ['A', 'L'], true)));
            $sections = $this->sectionTotals($rows, ['A' => 'Assets', 'L' => 'Liabilities']);
            $assets = abs($sections['A']['amount'] ?? 0);
            $liabilities = abs($sections['L']['amount'] ?? 0);
            $totals = ['assets' => $assets, 'liabilities' => $liabilities, 'net' => $assets - $liabilities];
        }

        usort($rows, fn ($a, $b) => abs($b['balance']) <=> abs($a['balance']));

        return response()->json([
            'ok' => true,
            'type' => $type,
            'to' => $to,
            'rows' => array_slice($rows, 0, 500),
            'sections' => array_values($sections),
            'totals' => $totals,
        ]);
    }

    /** GET /api/owner/term-summary?from=&to= */
    public function termSummary(Request $request): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        $this->applyAuthDatabase($auth);

        $from = $this->safeDate($request->query('from', ''), date('Y-m-01'));
        $to = $this->safeDate($request->query('to', ''), date('Y-m-d'));

        $sales = $this->sumTableBetween('salesm', 'tdate', ['billamt', 'netamt', 'discount', 'staxamt'], $from, $to);
        $salesReturn = $this->sumTableBetween('salesrm', 'tdate', ['billamt', 'netamt', 'discount', 'staxamt'], $from, $to);
        $purchase = $this->sumTableBetween('purchasem', 'tdate', ['billamt', 'netamt', 'discount', 'staxamt', 'pamt'], $from, $to);
        $purchaseReturn = $this->sumTableBetween('purchaserm', 'tdate', ['billamt', 'netamt', 'discount', 'staxamt'], $from, $to);
        $smith = $this->smithTotals($from, $to);

        $cash = 0.0;
        if (Schema::hasTable('daybook')) {
            $cash = (float) DB::table('daybook')
                ->whereBetween('tdate', [$from, $to])
                ->whereIn(DB::raw('UPPER(TRIM(accode))'), ['CASH', 'BANK', 'HDFC', 'SBI', 'AXIS'])
                ->sum('amount');
        }

        return response()->json([
            'ok' => true,
            'from' => $from,
            'to' => $to,
            'overview' => [
                'sales_amount' => $sales['netamt'] ?: $sales['billamt'],
                'sales_return' => $salesReturn['netamt'] ?: $salesReturn['billamt'],
                'purchase_amount' => $purchase['netamt'] ?: $purchase['billamt'],
                'purchase_return' => $purchaseReturn['netamt'] ?: $purchaseReturn['billamt'],
                'cash_movement' => $cash,
                'smith_balance_wgt' => $smith['balance_wgt'],
            ],
            'sections' => [
                ['title' => 'Sales', 'items' => $this->summaryItems($sales)],
                ['title' => 'Sales Return', 'items' => $this->summaryItems($salesReturn)],
                ['title' => 'Purchase', 'items' => $this->summaryItems($purchase)],
                ['title' => 'Purchase Return', 'items' => $this->summaryItems($purchaseReturn)],
                ['title' => 'Smith', 'items' => [
                    ['label' => 'Issued Weight', 'value' => $smith['issued_wgt'], 'format' => 'wgt'],
                    ['label' => 'Received Weight', 'value' => $smith['received_wgt'], 'format' => 'wgt'],
                    ['label' => 'Balance Weight', 'value' => $smith['balance_wgt'], 'format' => 'wgt'],
                    ['label' => 'Amount', 'value' => $smith['amount'], 'format' => 'amt'],
                ]],
            ],
        ]);
    }

    /** GET /api/owner/pending-advances */
    public function pendingAdvances(Request $request): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        $this->applyAuthDatabase($auth);

        if (!Schema::hasTable('orderm')) {
            return response()->json([
                'ok' => true,
                'rows' => [],
                'totals' => ['orders' => 0, 'advance_amount' => 0, 'given_weight' => 0, 'order_weight' => 0],
            ]);
        }

        $weightSub = Schema::hasTable('orderd') && Schema::hasColumn('orderd', 'weight')
            ? DB::table('orderd')
                ->selectRaw('slno, COALESCE(SUM(weight),0) as order_weight, COALESCE(SUM(qty),0) as qty')
                ->groupBy('slno')
            : null;

        $advAfterWeightExpr = Schema::hasTable('advafter') && Schema::hasColumn('advafter', 'wgt') ? 'wgt' : '0';
        $advAfterSub = Schema::hasTable('advafter') && Schema::hasColumn('advafter', 'amount')
            ? DB::table('advafter')
                ->selectRaw('slno, COALESCE(SUM(amount),0) as adv_after_amount, COALESCE(SUM(' . $advAfterWeightExpr . '),0) as adv_after_weight')
                ->groupBy('slno')
            : null;

        $q = DB::table('orderm as m')
            ->selectRaw('m.slno, TRIM(COALESCE(m.ordno,"")) as ordno, m.tdate, m.duedate')
            ->selectRaw('TRIM(COALESCE(m.custcode,"")) as customer_code, TRIM(COALESCE(m.custname,"")) as customer_name')
            ->selectRaw('COALESCE(m.billamt,0) as bill_amount, COALESCE(m.advance,0) as advance, COALESCE(m.eamt,0) as exchange_amount, COALESCE(m.sretamt,0) as sales_return_amount, COALESCE(m.gadvance,0) as gold_advance_weight');

        if (Schema::hasColumn('orderm', 'phone')) {
            $q->addSelect(DB::raw('TRIM(COALESCE(m.phone,"")) as phone'));
        } else {
            $q->addSelect(DB::raw('"" as phone'));
        }

        if ($weightSub) {
            $q->leftJoinSub($weightSub, 'd', 'd.slno', '=', 'm.slno')
                ->addSelect(DB::raw('COALESCE(d.order_weight,0) as order_weight, COALESCE(d.qty,0) as qty'));
        } else {
            $q->addSelect(DB::raw('0 as order_weight, 0 as qty'));
        }

        if ($advAfterSub) {
            $q->leftJoinSub($advAfterSub, 'aa', 'aa.slno', '=', 'm.slno')
                ->addSelect(DB::raw('COALESCE(aa.adv_after_amount,0) as adv_after_amount, COALESCE(aa.adv_after_weight,0) as adv_after_weight'));
        } else {
            $q->addSelect(DB::raw('0 as adv_after_amount, 0 as adv_after_weight'));
        }

        if (Schema::hasColumn('orderm', 'status')) {
            $q->where('m.status', 1);
        }
        if (Schema::hasColumn('orderm', 'control')) {
            $q->where('m.control', '<=', 9999);
        }

        $rows = $q->orderBy('m.duedate')->orderByDesc('m.slno')->limit(300)->get()->map(function ($r) {
            $advanceAmount = (float) ($r->advance ?? 0)
                + (float) ($r->exchange_amount ?? 0)
                + (float) ($r->sales_return_amount ?? 0)
                + (float) ($r->adv_after_amount ?? 0);
            $givenWeight = (float) ($r->gold_advance_weight ?? 0) + (float) ($r->adv_after_weight ?? 0);

            return [
                'order_no' => $this->jsonSafeString($r->ordno ?? ''),
                'date' => $this->jsonSafeString($r->tdate ?? ''),
                'due_date' => $this->jsonSafeString($r->duedate ?? ''),
                'customer_code' => $this->jsonSafeString($r->customer_code ?? ''),
                'customer_name' => $this->jsonSafeString($r->customer_name ?? ''),
                'phone' => $this->jsonSafeString($r->phone ?? ''),
                'bill_amount' => round((float) ($r->bill_amount ?? 0), 2),
                'advance_amount' => round($advanceAmount, 2),
                'given_weight' => round($givenWeight, 3),
                'order_weight' => round((float) ($r->order_weight ?? 0), 3),
                'qty' => round((float) ($r->qty ?? 0), 3),
            ];
        })->filter(fn ($r) => abs((float) $r['advance_amount']) > 0.0001 || abs((float) $r['given_weight']) > 0.0001)->values()->all();

        return response()->json([
            'ok' => true,
            'rows' => $rows,
            'totals' => [
                'orders' => count($rows),
                'advance_amount' => round((float) array_sum(array_column($rows, 'advance_amount')), 2),
                'given_weight' => round((float) array_sum(array_column($rows, 'given_weight')), 3),
                'order_weight' => round((float) array_sum(array_column($rows, 'order_weight')), 3),
            ],
        ]);
    }

    /** GET /api/owner/log-report?from=&to= */
    public function logReport(Request $request): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        $this->applyAuthDatabase($auth);

        $from = $this->safeDate($request->query('from', ''), date('Y-m-01'));
        $to = $this->safeDate($request->query('to', ''), date('Y-m-d'));
        $rows = [];

        if (Schema::hasTable('userhist')) {
            $query = DB::table('userhist')->whereBetween('tdate', [$from, $to])->orderByDesc('tdate');
            if (Schema::hasColumn('userhist', 'time1')) $query->orderByDesc('time1');
            $rows = array_merge($rows, $query->limit(250)->get()->map(fn ($r) => [
                'type' => 'Login',
                'date' => (string) ($r->tdate ?? ''),
                'time' => (string) ($r->time1 ?? ''),
                'user' => (string) ($r->code ?? ''),
                'details' => trim((string) (($r->ip ?? '') . ' ' . ($r->useragent ?? ''))),
            ])->toArray());
        }

        if (Schema::hasTable('delpart')) {
            $query = DB::table('delpart')->whereBetween('tdate', [$from, $to])->orderByDesc('tdate');
            if (Schema::hasColumn('delpart', 'updttime')) $query->orderByDesc('updttime');
            $rows = array_merge($rows, $query->limit(250)->get()->map(fn ($r) => [
                'type' => 'Update',
                'date' => (string) ($r->tdate ?? ''),
                'time' => (string) ($r->updttime ?? $r->time1 ?? ''),
                'user' => (string) ($r->uid ?? $r->code ?? ''),
                'details' => trim('Doc ' . (string) ($r->slno ?? '') . ' ' . (string) ($r->part ?? '') . ' ' . (string) ($r->utype ?? '')),
            ])->toArray());
        }

        if (Schema::hasTable('cancelled_bill_audits')) {
            $cancelRows = DB::table('cancelled_bill_audits')
                ->whereBetween(DB::raw('DATE(cancelled_at)'), [$from, $to])
                ->orderByDesc('cancelled_at')
                ->limit(250)
                ->get()
                ->map(function ($r) {
                    $bits = array_filter([
                        'Bill ' . trim((string) ($r->bill_no ?? '')),
                        'Customer ' . trim((string) ($r->customer_code ?? '')) . ' ' . trim((string) ($r->customer_name ?? '')),
                        'Wgt ' . number_format((float) ($r->gross_weight ?? 0), 3),
                        'Amt ' . number_format((float) ($r->net_amount ?? $r->bill_amount ?? 0), 2),
                        trim((string) ($r->reason ?? '')) !== '' ? 'Reason ' . trim((string) ($r->reason ?? '')) : '',
                    ]);

                    return [
                        'type' => 'Cancel',
                        'date' => substr((string) ($r->cancelled_at ?? ''), 0, 10),
                        'time' => substr((string) ($r->cancelled_at ?? ''), 11, 8),
                        'user' => (string) ($r->cancelled_by ?? ''),
                        'details' => implode(' | ', $bits),
                        'bill_no' => (string) ($r->bill_no ?? ''),
                        'customer_code' => (string) ($r->customer_code ?? ''),
                        'customer_name' => (string) ($r->customer_name ?? ''),
                        'weight' => (float) ($r->gross_weight ?? 0),
                        'amount' => (float) ($r->net_amount ?? $r->bill_amount ?? 0),
                    ];
                })
                ->toArray();

            $rows = array_merge($rows, $cancelRows);
        }

        usort($rows, fn ($a, $b) => strcmp(($b['date'] ?? '') . ($b['time'] ?? ''), ($a['date'] ?? '') . ($a['time'] ?? '')));

        return response()->json([
            'ok' => true,
            'from' => $from,
            'to' => $to,
            'rows' => array_slice($rows, 0, 400),
            'totals' => [
                'login' => count(array_filter($rows, fn ($r) => $r['type'] === 'Login')),
                'update' => count(array_filter($rows, fn ($r) => $r['type'] === 'Update')),
                'cancel' => count(array_filter($rows, fn ($r) => $r['type'] === 'Cancel')),
            ],
        ]);
    }

    private function extractToken(Request $request): ?string
    {
        $auth = (string) $request->header('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) return substr($auth, 7);
        return $request->input('token') ?: null;
    }

    private function authenticate(Request $request): ?array
    {
        $token = $this->extractToken($request);
        if (!$token) return null;
        $data = Cache::get("owner_token:{$token}");
        if (is_array($data)) return $data;
        return $this->ownerSession($token);
    }

    private function ensureOwnerSessionTable(): void
    {
        if (Schema::hasTable('owner_app_sessions')) return;

        DB::statement("
            CREATE TABLE owner_app_sessions (
                token_hash CHAR(64) PRIMARY KEY,
                user_code VARCHAR(40) NOT NULL DEFAULT '',
                user_name VARCHAR(120) NOT NULL DEFAULT '',
                database_name VARCHAR(80) NOT NULL DEFAULT '',
                expires_at DATETIME NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function storeOwnerSession(string $token, array $data, \DateTimeInterface $expiresAt): void
    {
        try {
            $this->ensureOwnerSessionTable();
            DB::table('owner_app_sessions')->updateOrInsert(
                ['token_hash' => hash('sha256', $token)],
                [
                    'user_code' => (string) ($data['user_code'] ?? ''),
                    'user_name' => (string) ($data['user_name'] ?? ''),
                    'database_name' => (string) ($data['database'] ?? ''),
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable) {
            // Cache token is enough if the legacy DB cannot create this table.
        }
    }

    private function ownerSession(string $token): ?array
    {
        try {
            $this->ensureOwnerSessionTable();
            $row = DB::table('owner_app_sessions')
                ->where('token_hash', hash('sha256', $token))
                ->where('expires_at', '>', now()->format('Y-m-d H:i:s'))
                ->first();
            if (!$row) return null;

            $data = [
                'user_code' => (string) $row->user_code,
                'user_name' => (string) $row->user_name,
                'database' => (string) $row->database_name,
            ];
            Cache::put("owner_token:{$token}", $data, now()->addMinutes(30));
            return $data;
        } catch (\Throwable) {
            return null;
        }
    }

    private function deleteOwnerSession(string $token): void
    {
        try {
            if (Schema::hasTable('owner_app_sessions')) {
                DB::table('owner_app_sessions')->where('token_hash', hash('sha256', $token))->delete();
            }
        } catch (\Throwable) {
        }
    }

    private function allowedCompanyDatabases(string $userCode): ?array
    {
        $userCode = strtoupper(trim($userCode));
        if ($userCode === '' || in_array($userCode, ['ADMIN', 'MGR', 'DEVELOPER'], true)) {
            return null;
        }
        if (!Schema::hasTable('user_company_access')) {
            return null;
        }

        $rows = DB::table('user_company_access')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$userCode])
            ->pluck('database_name')
            ->map(fn ($db) => strtolower(trim((string) $db)))
            ->filter()
            ->values()
            ->all();

        return empty($rows) ? null : $rows;
    }

    private function resolveTenant(Request $request): ?array
    {
        $domain = $this->tenantDomainFromRequest($request);
        if ($domain === '') {
            return null;
        }

        $originalDatabase = (string) Config::get('database.connections.mysql.database', '');

        try {
            $this->switchDatabase($this->primaryDatabaseName());
            $this->ensureSaasTenantTable();

            $tenant = DB::table('saas_tenants')
                ->where('domain', $domain)
                ->where('is_active', 1)
                ->first();
        } catch (\Throwable) {
            $tenant = null;
        } finally {
            if ($originalDatabase !== '') {
                $this->switchDatabase($originalDatabase);
            }
        }

        if (!$tenant) {
            return null;
        }

        return [
            'domain' => (string) $tenant->domain,
            'shop_name' => (string) $tenant->shop_name,
            'database_name' => (string) $tenant->database_name,
            'password_hash' => (string) $tenant->password_hash,
        ];
    }

    private function tenantDomainFromRequest(Request $request): string
    {
        return $this->normalizeTenantDomain((string) (
            $request->input('domain')
            ?? $request->input('domain_address')
            ?? $request->input('tenant_domain')
            ?? $request->query('domain')
            ?? ''
        ));
    }

    private function ensureSaasTenantTable(): void
    {
        if (Schema::hasTable('saas_tenants')) {
            return;
        }

        DB::statement("
            CREATE TABLE saas_tenants (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                domain VARCHAR(190) NOT NULL,
                shop_name VARCHAR(190) NOT NULL,
                database_name VARCHAR(120) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY saas_tenants_domain_unique (domain)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function normalizeTenantDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;

        return trim($domain);
    }

    private function primaryDatabaseName(): string
    {
        return trim((string) env('DB_DATABASE', (string) config('database.connections.mysql.database', '')));
    }

    private function switchDatabase(string $db): void
    {
        $db = trim($db);
        if ($db === '' || $db === config('database.connections.mysql.database', '')) return;
        Config::set('database.connections.mysql.database', $db);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private function applyAuthDatabase(array $auth): void
    {
        $this->switchDatabase((string) ($auth['database'] ?? ''));
    }

    private function relaxGroupBy(): void
    {
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@SESSION.sql_mode,'ONLY_FULL_GROUP_BY',''))");
    }

    private function getGeneral(string $code): string
    {
        try {
            return (string) (DB::table('generals')->where('code', $code)->value('cvalue') ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    private function getCurrentRates(): array
    {
        if (!Schema::hasTable('ratehistory')) return [];

        // ratehistory stores one row per date with rates as separate columns
        $row = DB::table('ratehistory')->orderByDesc('tdate')->first();
        if (!$row) return [];

        $map = [
            'grate'   => '22K Gold',
            'g18rate' => '18K Gold',
            'g14rate' => '14K Gold',
            'srate'   => 'Silver',
            'thrate'  => '24K TH',
            'prate'   => 'Platinum',
            'bulrate' => 'Bullion',
            'jrate'   => 'Smith Rate',
        ];

        $rates = [];
        foreach ($map as $col => $label) {
            if (!Schema::hasColumn('ratehistory', $col)) continue;
            $val = (float) ($row->$col ?? 0);
            if ($val > 0) {
                $rates[] = ['code' => strtoupper($col), 'label' => $label, 'rate' => $val];
            }
        }

        return $rates;
    }

    private function safeDate(mixed $val, string $fallback): string
    {
        $s = substr(trim((string) $val), 0, 10);
        return (strlen($s) === 10 && strtotime($s)) ? $s : $fallback;
    }

    private function accountStatementRows(string $to): array
    {
        $opbalCol = Schema::hasColumn('accountm', 'opbal') ? 'opbal' : null;
        $hasGroups = Schema::hasTable('accountg');
        $query = DB::table('accountm as a');
        if ($hasGroups) {
            $query->leftJoin('accountg as g', DB::raw('TRIM(g.grcode)'), '=', DB::raw('TRIM(a.grcode)'));
        }

        $accounts = $query
            ->selectRaw('TRIM(a.accode) as code')
            ->selectRaw('TRIM(a.name) as name')
            ->selectRaw($hasGroups ? 'UPPER(TRIM(COALESCE(a.actype1, g.actype1, ""))) as type' : 'UPPER(TRIM(COALESCE(a.actype1, ""))) as type')
            ->selectRaw($hasGroups ? 'TRIM(COALESCE(g.name, a.grcode, "")) as group_name' : 'TRIM(COALESCE(a.grcode, "")) as group_name')
            ->when($opbalCol, fn ($q) => $q->selectRaw("COALESCE(a.{$opbalCol},0) as opbal"), fn ($q) => $q->selectRaw('0 as opbal'))
            ->when(Schema::hasColumn('accountm', 'removed'), fn ($q) => $q->whereRaw('(a.removed <> 1 OR a.removed IS NULL)'))
            ->get();

        $txn = [];
        if (Schema::hasTable('daybook')) {
            $txnRows = DB::table('daybook')
                ->selectRaw('TRIM(accode) as code, SUM(COALESCE(amount,0)) as amount')
                ->where('tdate', '<=', $to)
                ->groupBy(DB::raw('TRIM(accode)'))
                ->get();
            foreach ($txnRows as $row) {
                $txn[(string) $row->code] = (float) $row->amount;
            }
        }

        return $accounts->map(function ($row) use ($txn) {
            $code = (string) $row->code;
            $balance = (float) ($row->opbal ?? 0) + (float) ($txn[$code] ?? 0);
            return [
                'code' => $code,
                'name' => trim((string) $row->name) ?: $code,
                'group' => trim((string) $row->group_name),
                'type' => trim((string) $row->type),
                'balance' => round($balance, 2),
            ];
        })->filter(fn ($row) => abs((float) $row['balance']) > 0.005)->values()->toArray();
    }

    private function sectionTotals(array $rows, array $labels): array
    {
        $sections = [];
        foreach ($labels as $type => $label) {
            $sections[$type] = ['type' => $type, 'title' => $label, 'amount' => 0.0, 'count' => 0];
        }
        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? '');
            if (!isset($sections[$type])) continue;
            $sections[$type]['amount'] += (float) ($row['balance'] ?? 0);
            $sections[$type]['count']++;
        }
        foreach ($sections as &$section) {
            $section['amount'] = round((float) $section['amount'], 2);
        }
        unset($section);
        return $sections;
    }

    private function sumTableBetween(string $table, string $dateColumn, array $columns, string $from, string $to): array
    {
        $result = [];
        foreach ($columns as $column) $result[$column] = 0.0;
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $dateColumn)) return $result;

        $select = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $select[] = "SUM(COALESCE({$column},0)) as {$column}";
            }
        }
        if (!$select) return $result;

        $row = DB::table($table)->whereBetween($dateColumn, [$from, $to])->selectRaw(implode(',', $select))->first();
        foreach ($columns as $column) {
            $result[$column] = round((float) ($row->$column ?? 0), 2);
        }
        return $result;
    }

    private function summaryItems(array $values): array
    {
        $labels = [
            'billamt' => 'Bill Amount',
            'netamt' => 'Net Amount',
            'discount' => 'Discount',
            'staxamt' => 'Tax Amount',
            'pamt' => 'Paid Amount',
        ];
        $items = [];
        foreach ($values as $key => $value) {
            $items[] = ['label' => $labels[$key] ?? ucwords(str_replace('_', ' ', $key)), 'value' => (float) $value, 'format' => 'amt'];
        }
        return $items;
    }

    private function smithTotals(string $from, string $to): array
    {
        $rows = [];
        if (Schema::hasTable('goldsmithtransm')) {
            $rows = $this->smithFromGoldsmithtransm($from, $to);
        } elseif (Schema::hasTable('smithm')) {
            $rows = $this->smithFromSmithm($from, $to);
        }

        return [
            'issued_wgt' => round((float) array_sum(array_column($rows, 'issued_wgt')), 3),
            'received_wgt' => round((float) array_sum(array_column($rows, 'received_wgt')), 3),
            'balance_wgt' => round((float) array_sum(array_column($rows, 'balance_wgt')), 3),
            'amount' => round((float) array_sum(array_column($rows, 'amount')), 2),
        ];
    }

    private function smithFromGoldsmithtransm(string $from, string $to): array
    {
        $hasPn = Schema::hasColumn('goldsmithtransm', 'partyname');
        $hasIw = Schema::hasColumn('goldsmithtransm', 'issuedwgt');
        $hasRw = Schema::hasColumn('goldsmithtransm', 'receivedwgt');

        return DB::table('goldsmithtransm as m')
            ->leftJoin('clients as c', DB::raw('TRIM(c.code)'), '=', DB::raw('TRIM(m.partycode)'))
            ->select(
                DB::raw('TRIM(m.partycode) as code'),
                DB::raw('COALESCE(TRIM(c.name),' . ($hasPn ? 'TRIM(m.partyname),' : '') . 'TRIM(m.partycode)) as name'),
                DB::raw('COALESCE(TRIM(c.mobile),"") as mobile'),
                $hasIw ? DB::raw('COALESCE(m.issuedwgt,0) as issued_wgt')   : DB::raw('0 as issued_wgt'),
                $hasRw ? DB::raw('COALESCE(m.receivedwgt,0) as received_wgt') : DB::raw('0 as received_wgt'),
                ($hasIw && $hasRw)
                    ? DB::raw('(COALESCE(m.issuedwgt,0)-COALESCE(m.receivedwgt,0)) as balance_wgt')
                    : DB::raw('0 as balance_wgt'),
                DB::raw('0 as amount')
            )
            ->whereBetween('m.tdate', [$from, $to])
            ->orderByDesc('balance_wgt')
            ->get()
            ->map(fn ($r) => [
                'code'         => (string) $r->code,
                'name'         => (string) $r->name,
                'mobile'       => (string) $r->mobile,
                'issued_wgt'   => (float) $r->issued_wgt,
                'received_wgt' => (float) $r->received_wgt,
                'balance_wgt'  => (float) $r->balance_wgt,
                'amount'       => (float) $r->amount,
            ])
            ->toArray();
    }

    private function smithFromSmithm(string $from, string $to): array
    {
        if (!Schema::hasTable('smithd')) {
            return [];
        }

        $weightCol = Schema::hasColumn('smithd', 'netwgt')
            ? 'd.netwgt'
            : (Schema::hasColumn('smithd', 'weight') ? 'd.weight' : '0');
        $amountCol = Schema::hasColumn('smithm', 'pamt')
            ? 'm.pamt'
            : (Schema::hasColumn('smithm', 'netamt') ? 'm.netamt' : '0');

        return DB::table('smithm as m')
            ->join('smithd as d', 'd.slno', '=', 'm.slno')
            ->leftJoin('clients as c', DB::raw('TRIM(c.code)'), '=', DB::raw('TRIM(m.smithcode)'))
            ->select(
                DB::raw('TRIM(m.smithcode) as code'),
                DB::raw('COALESCE(MAX(TRIM(c.name)), TRIM(m.smithcode)) as name'),
                DB::raw('COALESCE(MAX(TRIM(c.mobile)),"") as mobile'),
                DB::raw("SUM(CASE WHEN UPPER(TRIM(d.givrec))='G' THEN COALESCE({$weightCol},0) ELSE 0 END) as issued_wgt"),
                DB::raw("SUM(CASE WHEN UPPER(TRIM(d.givrec))='R' THEN COALESCE({$weightCol},0) ELSE 0 END) as received_wgt"),
                DB::raw("SUM(CASE WHEN UPPER(TRIM(d.givrec))='G' THEN COALESCE({$weightCol},0) ELSE -COALESCE({$weightCol},0) END) as balance_wgt"),
                DB::raw("SUM(COALESCE({$amountCol},0)) as amount")
            )
            ->whereBetween('m.tdate', [$from, $to])
            ->groupBy('m.smithcode')
            ->orderByDesc('balance_wgt')
            ->get()
            ->map(fn ($r) => [
                'code'         => (string) $r->code,
                'name'         => (string) $r->name,
                'mobile'       => (string) $r->mobile,
                'issued_wgt'   => (float) $r->issued_wgt,
                'received_wgt' => (float) $r->received_wgt,
                'balance_wgt'  => (float) $r->balance_wgt,
                'amount'       => (float) $r->amount,
            ])
            ->toArray();
    }

    private function smithFromClients(): array
    {
        $balCol = Schema::hasColumn('clients', 'wgtbalance') ? 'wgtbalance'
            : (Schema::hasColumn('clients', 'wgtbal') ? 'wgtbal' : null);

        if (!$balCol) return [];

        return DB::table('clients')
            ->select(
                DB::raw('TRIM(code) as code'),
                DB::raw('TRIM(name) as name'),
                DB::raw('COALESCE(TRIM(mobile),"") as mobile'),
                DB::raw('0 as issued_wgt'),
                DB::raw('0 as received_wgt'),
                DB::raw("COALESCE({$balCol},0) as balance_wgt"),
                DB::raw('0 as amount')
            )
            ->whereRaw("UPPER(TRIM(COALESCE(type,''))) IN ('G','GOLDSMITH','SMITH')")
            ->whereRaw("COALESCE({$balCol},0) != 0")
            ->orderByDesc('balance_wgt')
            ->get()
            ->map(fn ($r) => [
                'code'         => (string) $r->code,
                'name'         => (string) $r->name,
                'mobile'       => (string) $r->mobile,
                'issued_wgt'   => 0.0,
                'received_wgt' => 0.0,
                'balance_wgt'  => (float) $r->balance_wgt,
                'amount'       => 0.0,
            ])
            ->toArray();
    }

    // ══════════════════════════════════════════════════════════════
    //  BRANCH MANAGEMENT  (admin only)
    // ══════════════════════════════════════════════════════════════

    private function ensureBranchTable(): void
    {
        if (!Schema::hasTable('owner_branches')) {
            DB::statement("
                CREATE TABLE owner_branches (
                    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name        VARCHAR(120) NOT NULL,
                    api_url     VARCHAR(255) NOT NULL,
                    db_name     VARCHAR(80)  NOT NULL DEFAULT '',
                    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
                    note        VARCHAR(255)          DEFAULT NULL,
                    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }

    private function isAdmin(array $auth): bool
    {
        $code = strtoupper(trim((string) ($auth['user_code'] ?? '')));
        return in_array($code, ['ADMIN', 'MGR', 'DEVELOPER', 'OWNER'], true);
    }

    private function jsonSafeString(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        return preg_replace('/[[:cntrl:]]+/u', ' ', $text) ?? '';
    }

    /** GET /api/owner/admin/branches */
    public function branchList(Request $request): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        $this->applyAuthDatabase($auth);
        $this->ensureBranchTable();

        $branches = DB::table('owner_branches')
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => [
                'id'        => (int)    $r->id,
                'name'      => (string) $r->name,
                'api_url'   => (string) $r->api_url,
                'db_name'   => (string) $r->db_name,
                'is_active' => (bool)   $r->is_active,
                'note'      => (string) ($r->note ?? ''),
                'created_at'=> (string) $r->created_at,
            ])
            ->toArray();

        return response()->json([
            'ok'       => true,
            'branches' => $branches,
            'is_admin' => $this->isAdmin($auth),
        ]);
    }

    /** POST /api/owner/admin/branches  {name, api_url, db_name?, note?, is_active?} */
    public function branchCreate(Request $request): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        if (!$this->isAdmin($auth)) {
            return response()->json(['ok' => false, 'message' => 'Admin access required'], 403);
        }
        $this->applyAuthDatabase($auth);
        $this->ensureBranchTable();

        $name    = trim((string) ($request->input('name') ?? ''));
        $apiUrl  = trim((string) ($request->input('api_url') ?? ''));
        $dbName  = trim((string) ($request->input('db_name') ?? ''));
        $note    = trim((string) ($request->input('note') ?? ''));
        $active  = $request->boolean('is_active', true);

        if ($name === '' || $apiUrl === '') {
            return response()->json(['ok' => false, 'message' => 'Name and API URL are required'], 400);
        }

        $id = DB::table('owner_branches')->insertGetId([
            'name'      => $name,
            'api_url'   => rtrim($apiUrl, '/'),
            'db_name'   => $dbName,
            'note'      => $note ?: null,
            'is_active' => $active ? 1 : 0,
        ]);

        return response()->json(['ok' => true, 'id' => $id, 'message' => 'Branch created']);
    }

    /** PUT /api/owner/admin/branches/{id}  {name?, api_url?, db_name?, note?, is_active?} */
    public function branchUpdate(Request $request, int $id): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        if (!$this->isAdmin($auth)) {
            return response()->json(['ok' => false, 'message' => 'Admin access required'], 403);
        }
        $this->applyAuthDatabase($auth);
        $this->ensureBranchTable();

        $row = DB::table('owner_branches')->where('id', $id)->first();
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Branch not found'], 404);
        }

        $data = [];
        if ($request->has('name'))      $data['name']      = trim((string) $request->input('name'));
        if ($request->has('api_url'))   $data['api_url']   = rtrim(trim((string) $request->input('api_url')), '/');
        if ($request->has('db_name'))   $data['db_name']   = trim((string) $request->input('db_name'));
        if ($request->has('note'))      $data['note']      = trim((string) $request->input('note')) ?: null;
        if ($request->has('is_active')) $data['is_active'] = $request->boolean('is_active') ? 1 : 0;

        if (empty($data)) {
            return response()->json(['ok' => false, 'message' => 'Nothing to update'], 400);
        }

        DB::table('owner_branches')->where('id', $id)->update($data);

        return response()->json(['ok' => true, 'message' => 'Branch updated']);
    }

    /** DELETE /api/owner/admin/branches/{id} */
    public function branchDelete(Request $request, int $id): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }
        if (!$this->isAdmin($auth)) {
            return response()->json(['ok' => false, 'message' => 'Admin access required'], 403);
        }
        $this->applyAuthDatabase($auth);
        $this->ensureBranchTable();

        $deleted = DB::table('owner_branches')->where('id', $id)->delete();

        if (!$deleted) {
            return response()->json(['ok' => false, 'message' => 'Branch not found'], 404);
        }

        return response()->json(['ok' => true, 'message' => 'Branch deleted']);
    }

    /** GET /api/owner/admin/branches/test-connection?api_url= */
    public function branchTestConnection(Request $request): JsonResponse
    {
        if (!$auth = $this->authenticate($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorised'], 401);
        }

        $apiUrl = rtrim(trim((string) ($request->query('api_url') ?? '')), '/');
        if ($apiUrl === '') {
            return response()->json(['ok' => false, 'message' => 'api_url required'], 400);
        }

        try {
            $ch = curl_init("{$apiUrl}/api/owner/shop-info");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $body   = curl_exec($ch);
            $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error  = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return response()->json(['ok' => false, 'message' => "Connection failed: {$error}"]);
            }
            if ($code !== 200) {
                return response()->json(['ok' => false, 'message' => "Server returned HTTP {$code}"]);
            }
            $data = json_decode($body, true);
            $shopName = $data['shop_name'] ?? 'Unknown';

            return response()->json(['ok' => true, 'message' => "Connected — {$shopName}", 'shop_name' => $shopName]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
