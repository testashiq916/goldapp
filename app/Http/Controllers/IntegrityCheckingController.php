<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Integrity Checking (PB: w_account_checking)
 *
 * Runs a series of data-integrity checks and returns a list of
 * {type: error|warning|info|ok, text: string} messages.
 */
class IntegrityCheckingController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.integrity-checking', [
            'title' => 'Integrity Checking',
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $results = [];

        // ── 1. Items Master Check ──
        $results[] = ['type' => 'info', 'text' => '── Items Master Check ──'];

        if ($this->hasTable('items')) {
            $items = DB::table('items')->orderBy('code')->get();
            $itemCount = 0;

            foreach ($items as $item) {
                $code = $item->code ?? '';
                $name = $item->name ?? '';

                // Check cost
                $cost = (float)($item->cost ?? 0);
                $stkqty = (float)($item->stkqty ?? 0);
                $stkwgt = (float)($item->stkwgt ?? 0);

                if (($stkqty > 0 || $stkwgt > 0) && $cost == 0) {
                    $results[] = ['type' => 'warning', 'text' => "Item [{$code}] {$name}: has stock but cost is 0"];
                }

                $itemCount++;
            }

            if ($itemCount === 0) {
                $results[] = ['type' => 'info', 'text' => 'No items found in items master'];
            } else {
                $results[] = ['type' => 'ok', 'text' => "Items master: {$itemCount} items checked"];
            }
        } else {
            $results[] = ['type' => 'warning', 'text' => 'Table [items] not found — skipped'];
        }

        // ── 2. Account Master Check ──
        $results[] = ['type' => 'info', 'text' => '── Account Master Check ──'];

        if ($this->hasTable('accountm')) {
            $acCols = array_map('strtolower', $this->columnList('accountm'));
            $accounts = DB::table('accountm')->orderBy('accode')->get();
            $acCount = 0;
            $acErrors = 0;

            // Get valid group codes (accountg.grcode)
            $validGroups = [];
            if ($this->hasTable('accountg')) {
                $agCols = array_map('strtolower', $this->columnList('accountg'));
                $grCol = in_array('grcode', $agCols) ? 'grcode' : (in_array('grpcode', $agCols) ? 'grpcode' : null);
                if ($grCol) {
                    $validGroups = DB::table('accountg')->pluck($grCol)->map(fn($v) => strtoupper($v))->toArray();
                }
            }

            // Get valid BS heads (accountgbs.hcode)
            $validBsHeads = [];
            if ($this->hasTable('accountgbs')) {
                $gbsCols = array_map('strtolower', $this->columnList('accountgbs'));
                $hCol = in_array('hcode', $gbsCols) ? 'hcode' : (in_array('bshead', $gbsCols) ? 'bshead' : null);
                if ($hCol) {
                    $validBsHeads = DB::table('accountgbs')->pluck($hCol)->map(fn($v) => strtoupper($v))->toArray();
                }
            }

            // Detect accountm column names
            $grCodeCol  = in_array('grcode', $acCols) ? 'grcode' : (in_array('grpcode', $acCols) ? 'grpcode' : null);
            $actypeCol  = in_array('actype1', $acCols) ? 'actype1' : (in_array('actype', $acCols) ? 'actype' : null);
            $acnameCol  = in_array('name', $acCols) ? 'name' : (in_array('acname', $acCols) ? 'acname' : 'accode');

            // Preload all accode's that appear in daybook (avoid N+1)
            $daybookAcCodes = [];
            if ($this->hasTable('daybook')) {
                $daybookAcCodes = DB::table('daybook')->distinct()->pluck('accode')->map(fn($v) => strtoupper($v))->toArray();
            }

            foreach ($accounts as $ac) {
                $accode  = $ac->accode ?? '';
                $acname  = $acnameCol ? ($ac->$acnameCol ?? '') : '';
                $grpcode = $grCodeCol ? strtoupper($ac->$grCodeCol ?? '') : '';
                $actype  = $actypeCol ? strtoupper($ac->$actypeCol ?? '') : '';

                // Check grcode exists in accountg
                if ($grCodeCol && !empty($validGroups) && !empty($grpcode) && !in_array($grpcode, $validGroups)) {
                    $results[] = ['type' => 'error', 'text' => "Account [{$accode}] {$acname}: group [{$grpcode}] not found in accountg"];
                    $acErrors++;
                }

                // Check bshead for A/L types
                if (in_array($actype, ['A', 'L']) && in_array('bshead', $acCols)) {
                    $bshead = strtoupper($ac->bshead ?? '');
                    if (!empty($validBsHeads) && !empty($bshead) && !in_array($bshead, $validBsHeads)) {
                        $results[] = ['type' => 'warning', 'text' => "Account [{$accode}] {$acname}: bshead [{$bshead}] not found in accountgbs"];
                    }
                }

                $acCount++;
            }

            // Accounts with no opbal and no daybook entries — report summary count only (avoid noise)
            if (!empty($daybookAcCodes)) {
                $emptyCount = 0;
                foreach ($accounts as $ac) {
                    $accode = $ac->accode ?? '';
                    $opbal  = (float)($ac->opbal ?? 0);
                    if ($opbal == 0 && !in_array(strtoupper($accode), $daybookAcCodes)) {
                        $emptyCount++;
                    }
                }
                if ($emptyCount > 0) {
                    $results[] = ['type' => 'warning', 'text' => "{$emptyCount} account(s) have no opening balance and no transactions"];
                }
            }

            if ($acErrors === 0) {
                $results[] = ['type' => 'ok', 'text' => "Account master: {$acCount} accounts checked, no group errors"];
            } else {
                $results[] = ['type' => 'error', 'text' => "Account master: {$acCount} accounts checked, {$acErrors} group errors found"];
            }
        } else {
            $results[] = ['type' => 'warning', 'text' => 'Table [accountm] not found — skipped'];
        }

        // ── 3. Opening Balances Check ──
        $results[] = ['type' => 'info', 'text' => '── Opening Balances Check ──'];

        if ($this->hasTable('accountm')) {
            $opbalSum = (float) DB::table('accountm')->sum('opbal');
            $opbalSum = round($opbalSum, 2);

            if (abs($opbalSum) < 0.01) {
                $results[] = ['type' => 'ok', 'text' => "Opening balances balanced: sum = {$opbalSum}"];
            } else {
                $results[] = ['type' => 'error', 'text' => "Opening balances NOT balanced: sum(opbal) = {$opbalSum} (should be 0)"];
            }
        }

        // ── 4. Daybook Check ──
        $results[] = ['type' => 'info', 'text' => '── Daybook Check ──'];

        if ($this->hasTable('daybook')) {
            // Check each slno sums to zero (double-entry)
            $unbalanced = DB::table('daybook')
                ->groupBy('slno')
                ->havingRaw('ABS(SUM(amount)) > 0.01')
                ->select('slno', DB::raw('SUM(amount) as diff'))
                ->orderBy('slno')
                ->limit(20)
                ->get();

            if ($unbalanced->count() === 0) {
                $results[] = ['type' => 'ok', 'text' => 'Daybook: all entries balanced (double-entry check passed)'];
            } else {
                foreach ($unbalanced as $row) {
                    $diff = round((float)$row->diff, 2);
                    $results[] = ['type' => 'error', 'text' => "Daybook slno [{$row->slno}]: unbalanced by {$diff}"];
                }
                if ($unbalanced->count() >= 20) {
                    $results[] = ['type' => 'error', 'text' => '(20+ unbalanced entries found — showing first 20)'];
                }
            }

            // Check all daybook accodes exist in accountm
            if ($this->hasTable('accountm')) {
                $orphanCount = DB::table('daybook as d')
                    ->leftJoin('accountm as a', 'd.accode', '=', 'a.accode')
                    ->whereNull('a.accode')
                    ->distinct()
                    ->count('d.accode');

                if ($orphanCount === 0) {
                    $results[] = ['type' => 'ok', 'text' => 'Daybook: all account codes exist in account master'];
                } else {
                    $orphans = DB::table('daybook as d')
                        ->leftJoin('accountm as a', 'd.accode', '=', 'a.accode')
                        ->whereNull('a.accode')
                        ->distinct()
                        ->pluck('d.accode')
                        ->take(10)
                        ->implode(', ');
                    $results[] = ['type' => 'error', 'text' => "Daybook: {$orphanCount} account code(s) not in accountm: {$orphans}"];
                }
            }
        } else {
            $results[] = ['type' => 'warning', 'text' => 'Table [daybook] not found — skipped'];
        }

        // ── 5. Sales Check ──
        $results[] = ['type' => 'info', 'text' => '── Sales Check ──'];

        if ($this->hasTable('salesm')) {
            $badSales = DB::table('salesm')->where('billamt', '<=', 0)->count();
            if ($badSales === 0) {
                $results[] = ['type' => 'ok', 'text' => 'Sales: all bills have billamt > 0'];
            } else {
                $results[] = ['type' => 'warning', 'text' => "Sales: {$badSales} bill(s) with billamt <= 0"];
            }
        } else {
            $results[] = ['type' => 'warning', 'text' => 'Table [salesm] not found — skipped'];
        }

        // ── 6. Purchases Check ──
        $results[] = ['type' => 'info', 'text' => '── Purchases Check ──'];

        if ($this->hasTable('purchasem')) {
            $badPurchases = DB::table('purchasem')->where('billamt', '<=', 0)->count();
            if ($badPurchases === 0) {
                $results[] = ['type' => 'ok', 'text' => 'Purchases: all bills have billamt > 0'];
            } else {
                $results[] = ['type' => 'warning', 'text' => "Purchases: {$badPurchases} bill(s) with billamt <= 0"];
            }
        } else {
            $results[] = ['type' => 'warning', 'text' => 'Table [purchasem] not found — skipped'];
        }

        $results[] = ['type' => 'ok', 'text' => '── Check Complete ──'];

        return response()->json([
            'ok'      => true,
            'results' => $results,
        ]);
    }
}
