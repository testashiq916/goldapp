<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class GoldLoanController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('gold-loan.index', [
            'today' => date('Y-m-d'),
        ]);
    }

    public function report(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('gold-loan.report', [
            'dateFrom' => $request->query('date1', date('Y-m-01')),
            'dateTo'   => $request->query('date2', date('Y-m-d')),
        ]);
    }

    // ── API ───────────────────────────────────────────────────────────────

    public function load(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $slno = (int) $request->query('slno', 0);
        if ($slno > 0 && $this->hasTable('loan')) {
            try {
                $loan = DB::table('loan')->where('slno', $slno)->first();
                if (!$loan) {
                    return response()->json(['ok' => false, 'message' => 'Loan not found']);
                }
                $items = $this->hasTable('loan_items')
                    ? DB::table('loan_items')->where('slno', $slno)->get()->map(fn($r) => (array) $r)->all()
                    : [];
                $collns = $this->hasTable('loancolln')
                    ? DB::table('loancolln')->where('slno', $slno)->orderBy('tdate')->get()->map(fn($r) => (array) $r)->all()
                    : [];
                return response()->json(['ok' => true, 'loan' => (array) $loan, 'items' => $items, 'collections' => $collns]);
            } catch (Throwable $e) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()]);
            }
        }

        return response()->json(['ok' => true, 'loan' => null, 'items' => [], 'collections' => []]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        if (!$this->hasTable('loan')) {
            return response()->json(['ok' => false, 'message' => 'Loan table not found in database']);
        }

        $slno    = (int) $request->input('slno', 0);
        $ccode   = trim((string) $request->input('ccode', ''));
        $cname   = trim((string) $request->input('cname', ''));
        $loanAmt = (float) $request->input('loan_amount', 0);
        $rate    = (float) $request->input('interest_rate', 12); // % per annum
        $tdate   = (string) $request->input('tdate', date('Y-m-d'));
        $dueDate = (string) $request->input('due_date', '');
        $remarks = trim((string) $request->input('remarks', ''));
        $items   = $request->input('items', []);

        if ($ccode === '' || $loanAmt <= 0) {
            return response()->json(['ok' => false, 'message' => 'Customer and loan amount required']);
        }

        try {
            DB::beginTransaction();

            $loanData = [
                'ccode'         => $ccode,
                'cname'         => $cname,
                'tdate'         => $tdate,
                'loanamt'       => $loanAmt,
                'intrate'       => $rate,
                'duedate'       => $dueDate ?: null,
                'remarks'       => $remarks,
                'status'        => 'open',
            ];

            if ($slno > 0) {
                DB::table('loan')->where('slno', $slno)->update($loanData);
                if ($this->hasTable('loan_items')) {
                    DB::table('loan_items')->where('slno', $slno)->delete();
                }
            } else {
                $slno = DB::table('loan')->insertGetId(array_merge($loanData, ['tdate' => $tdate]));
            }

            if ($this->hasTable('loan_items') && !empty($items)) {
                foreach ($items as $item) {
                    DB::table('loan_items')->insert([
                        'slno'       => $slno,
                        'icode'      => trim((string) ($item['icode'] ?? '')),
                        'idesc'      => trim((string) ($item['idesc'] ?? '')),
                        'purity'     => (float) ($item['purity'] ?? 0),
                        'grosswgt'   => (float) ($item['grosswgt'] ?? 0),
                        'netwgt'     => (float) ($item['netwgt'] ?? 0),
                        'finewgt'    => (float) ($item['finewgt'] ?? 0),
                        'grate'      => (float) ($item['grate'] ?? 0),
                        'value'      => (float) ($item['value'] ?? 0),
                    ]);
                }
            }

            DB::commit();
            return response()->json(['ok' => true, 'slno' => $slno]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function addCollection(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        if (!$this->hasTable('loancolln')) {
            return response()->json(['ok' => false, 'message' => 'loancolln table not found']);
        }

        $slno      = (int) $request->input('slno', 0);
        $tdate     = (string) $request->input('tdate', date('Y-m-d'));
        $principal = (float) $request->input('principal', 0);
        $interest  = (float) $request->input('interest', 0);
        $total     = $principal + $interest;

        if ($slno <= 0 || $total <= 0) {
            return response()->json(['ok' => false, 'message' => 'Invalid data']);
        }

        try {
            DB::table('loancolln')->insert([
                'slno'      => $slno,
                'tdate'     => $tdate,
                'principal' => $principal,
                'interest'  => $interest,
                'total'     => $total,
            ]);

            // Update balance in loan table
            if ($this->hasTable('loan')) {
                $paid = DB::table('loancolln')->where('slno', $slno)->sum('principal');
                $loan = DB::table('loan')->where('slno', $slno)->first();
                if ($loan) {
                    $balance = (float) ($loan->loanamt ?? 0) - $paid;
                    $status  = $balance <= 0 ? 'closed' : 'open';
                    DB::table('loan')->where('slno', $slno)->update([
                        'paidamt' => $paid,
                        'balance' => max(0, $balance),
                        'status'  => $status,
                    ]);
                }
            }

            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function interestCalc(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $loanAmt  = (float) $request->input('loan_amount', 0);
        $rate     = (float) $request->input('interest_rate', 12);
        $fromDate = (string) $request->input('from_date', date('Y-m-d'));
        $toDate   = (string) $request->input('to_date', date('Y-m-d'));

        try {
            $days     = max(0, (int) (new \DateTime($fromDate))->diff(new \DateTime($toDate))->days);
            $interest = round($loanAmt * ($rate / 100) * ($days / 365), 2);
            return response()->json(['ok' => true, 'days' => $days, 'interest' => $interest]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function reportData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        if (!$this->hasTable('loan')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $dateFrom = (string) $request->query('date1', date('Y-m-01'));
        $dateTo   = (string) $request->query('date2', date('Y-m-d'));
        $status   = (string) $request->query('status', 'all');
        $search   = trim((string) $request->query('search', ''));

        try {
            $q = DB::table('loan')
                ->whereBetween('tdate', [$dateFrom, $dateTo]);

            if ($status !== 'all') {
                $q->where('status', $status);
            }
            if ($search !== '') {
                $like = '%' . $search . '%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('ccode', 'like', $like)->orWhere('cname', 'like', $like);
                });
            }

            $rows = $q->orderBy('tdate')->get()->map(fn($r) => (array) $r)->all();
            $totals = [
                'loan_amount'  => array_sum(array_column($rows, 'loanamt')),
                'paid_amount'  => array_sum(array_column($rows, 'paidamt')),
                'balance'      => array_sum(array_column($rows, 'balance')),
                'count'        => count($rows),
            ];

            return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function customerSearch(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q === '' || !$this->hasTable('clients')) {
            return response()->json(['ok' => true, 'results' => []]);
        }

        $like = '%' . $q . '%';
        $rows = DB::table('clients')
            ->where('ctype', 'C')
            ->where(function ($inner) use ($like) {
                $inner->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('mobile', 'like', $like);
            })
            ->limit(20)
            ->get(['code', 'name', 'mobile', 'addr1', 'city'])
            ->map(fn($r) => (array) $r)
            ->all();

        return response()->json(['ok' => true, 'results' => $rows]);
    }
}
