<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class StaffPayrollController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('staff-payroll.index', [
            'month' => $request->query('month', date('Y-m')),
        ]);
    }

    public function salarySlip(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $id    = (int) $request->query('id', 0);
        $month = (string) $request->query('month', date('Y-m'));

        return view('staff-payroll.salary-slip', compact('id', 'month'));
    }

    // ── API ───────────────────────────────────────────────────────────────

    public function staffList(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        try {
            $staffTable = $this->hasTable('staff') ? 'staff' : null;
            if (!$staffTable) {
                return response()->json(['ok' => true, 'rows' => []]);
            }

            $cols = $this->columnList('staff');
            $nameCol = in_array('sname', $cols) ? 'sname' : (in_array('name', $cols) ? 'name' : 'scode');

            $rows = DB::table('staff')
                ->selectRaw("TRIM(scode) as scode, TRIM({$nameCol}) as sname, TRIM(COALESCE(dept,'')) as dept")
                ->orderBy($nameCol)
                ->get();

            return response()->json(['ok' => true, 'rows' => $rows]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getStructure(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $staffCode = trim((string) $request->query('staff_code', ''));

        try {
            $row = DB::table('salary_structure')
                ->where('staff_code', $staffCode)
                ->where('is_active', 1)
                ->orderByDesc('effective_from')
                ->first();

            return response()->json(['ok' => true, 'structure' => $row ? (array) $row : null]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function saveStructure(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $staffCode = trim((string) $request->input('staff_code', ''));
        if ($staffCode === '') {
            return response()->json(['ok' => false, 'message' => 'Staff code required']);
        }

        try {
            // Deactivate previous
            DB::table('salary_structure')
                ->where('staff_code', $staffCode)
                ->update(['is_active' => 0]);

            $id = DB::table('salary_structure')->insertGetId([
                'staff_code'       => $staffCode,
                'basic_salary'     => (float) $request->input('basic_salary', 0),
                'hra'              => (float) $request->input('hra', 0),
                'da'               => (float) $request->input('da', 0),
                'other_allowances' => (float) $request->input('other_allowances', 0),
                'pf_percent'       => (float) $request->input('pf_percent', 12),
                'esi_percent'      => (float) $request->input('esi_percent', 0.75),
                'tds_monthly'      => (float) $request->input('tds_monthly', 0),
                'effective_from'   => (string) $request->input('effective_from', date('Y-m-01')),
                'is_active'        => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return response()->json(['ok' => true, 'id' => $id]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function calculateSalary(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $staffCode   = trim((string) $request->input('staff_code', ''));
        $month       = (string) $request->input('month', date('Y-m'));
        $daysPresent = (int) $request->input('days_present', 26);
        $workingDays = (int) $request->input('working_days', 26);

        if ($staffCode === '') {
            return response()->json(['ok' => false, 'message' => 'Staff code required']);
        }

        try {
            $structure = DB::table('salary_structure')
                ->where('staff_code', $staffCode)
                ->where('is_active', 1)
                ->orderByDesc('effective_from')
                ->first();

            if (!$structure) {
                return response()->json(['ok' => false, 'message' => 'No salary structure found for this staff']);
            }

            $ratio         = $workingDays > 0 ? ($daysPresent / $workingDays) : 1;
            $basicEarned   = round((float) $structure->basic_salary * $ratio, 2);
            $hraEarned     = round((float) $structure->hra * $ratio, 2);
            $daEarned      = round((float) $structure->da * $ratio, 2);
            $otherAllowances = round((float) $structure->other_allowances, 2);
            $gross         = $basicEarned + $hraEarned + $daEarned + $otherAllowances;
            $pfDed         = round($basicEarned * ((float) $structure->pf_percent / 100), 2);
            $esiDed        = $gross <= 21000
                ? round($gross * ((float) $structure->esi_percent / 100), 2)
                : 0;
            $tdsDed        = (float) $structure->tds_monthly;
            $net           = max(0, $gross - $pfDed - $esiDed - $tdsDed);

            // Get advance deductions from staff transactions
            $advanceDed = 0;
            if ($this->hasTable('stafftrans')) {
                $monthStart = $month . '-01';
                $monthEnd   = date('Y-m-t', strtotime($monthStart));
                $advanceDed = (float) DB::table('stafftrans')
                    ->where('scode', $staffCode)
                    ->where('ttype', 'A') // advance type
                    ->whereBetween('tdate', [$monthStart, $monthEnd])
                    ->sum('amount');
            }

            $net -= $advanceDed;

            return response()->json([
                'ok' => true,
                'calc' => [
                    'staff_code'       => $staffCode,
                    'month'            => $month,
                    'working_days'     => $workingDays,
                    'days_present'     => $daysPresent,
                    'basic_earned'     => $basicEarned,
                    'hra_earned'       => $hraEarned,
                    'da_earned'        => $daEarned,
                    'other_allowances' => $otherAllowances,
                    'gross_salary'     => $gross,
                    'pf_deduction'     => $pfDed,
                    'esi_deduction'    => $esiDed,
                    'tds_deduction'    => $tdsDed,
                    'advance_deduction'=> $advanceDed,
                    'other_deductions' => 0,
                    'net_salary'       => max(0, $net),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function saveSalary(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $data = $request->input('salary', []);
        if (empty($data)) {
            return response()->json(['ok' => false, 'message' => 'No salary data']);
        }

        try {
            $key = ['staff_code' => $data['staff_code'], 'month' => $data['month']];
            $exists = DB::table('salary_register')->where($key)->exists();

            $row = [
                'staff_code'       => $data['staff_code'],
                'month'            => $data['month'],
                'working_days'     => (int) ($data['working_days'] ?? 26),
                'days_present'     => (int) ($data['days_present'] ?? 26),
                'basic_earned'     => (float) ($data['basic_earned'] ?? 0),
                'hra_earned'       => (float) ($data['hra_earned'] ?? 0),
                'da_earned'        => (float) ($data['da_earned'] ?? 0),
                'other_allowances' => (float) ($data['other_allowances'] ?? 0),
                'gross_salary'     => (float) ($data['gross_salary'] ?? 0),
                'pf_deduction'     => (float) ($data['pf_deduction'] ?? 0),
                'esi_deduction'    => (float) ($data['esi_deduction'] ?? 0),
                'tds_deduction'    => (float) ($data['tds_deduction'] ?? 0),
                'advance_deduction'=> (float) ($data['advance_deduction'] ?? 0),
                'other_deductions' => (float) ($data['other_deductions'] ?? 0),
                'net_salary'       => (float) ($data['net_salary'] ?? 0),
                'payment_mode'     => (string) ($data['payment_mode'] ?? 'cash'),
                'notes'            => (string) ($data['notes'] ?? ''),
                'status'           => 'finalized',
                'updated_at'       => now(),
            ];

            if ($exists) {
                DB::table('salary_register')->where($key)->update($row);
            } else {
                $row['created_at'] = now();
                DB::table('salary_register')->insert($row);
            }

            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function monthlyRegister(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $month = (string) $request->query('month', date('Y-m'));

        try {
            $staffTable = $this->hasTable('staff') ? 'staff' : null;
            $nameCol    = 'scode';

            if ($staffTable) {
                $cols    = $this->columnList('staff');
                $nameCol = in_array('sname', $cols) ? 'sname' : (in_array('name', $cols) ? 'name' : 'scode');
            }

            $rows = DB::table('salary_register as r')
                ->when($staffTable, function ($q) use ($nameCol) {
                    $q->leftJoin('staff as s', 's.scode', '=', 'r.staff_code')
                      ->addSelect(DB::raw("TRIM(COALESCE(s.{$nameCol}, r.staff_code)) as staff_name"));
                })
                ->where('r.month', $month)
                ->select('r.*')
                ->orderBy('r.staff_code')
                ->get();

            $totals = [
                'gross_salary' => $rows->sum('gross_salary'),
                'net_salary'   => $rows->sum('net_salary'),
                'pf_deduction' => $rows->sum('pf_deduction'),
                'esi_deduction'=> $rows->sum('esi_deduction'),
                'count'        => $rows->count(),
            ];

            return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function slipData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $staffCode = trim((string) $request->query('staff_code', ''));
        $month     = (string) $request->query('month', date('Y-m'));

        try {
            $reg = DB::table('salary_register')
                ->where('staff_code', $staffCode)
                ->where('month', $month)
                ->first();

            if (!$reg) {
                return response()->json(['ok' => false, 'message' => 'Salary not found']);
            }

            // Get staff details
            $staff = null;
            if ($this->hasTable('staff')) {
                $staff = DB::table('staff')->where('scode', $staffCode)->first();
            }

            $shopName  = $this->generalValue('SHOPNM');
            $shopAddr  = $this->generalValue('SHOPADDR');
            $monthLabel = date('F Y', strtotime($month . '-01'));

            return response()->json([
                'ok'        => true,
                'slip'      => (array) $reg,
                'staff'     => $staff ? (array) $staff : [],
                'shop_name' => $shopName,
                'shop_addr' => $shopAddr,
                'month_label' => $monthLabel,
            ]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    private function generalValue(string $code): string
    {
        try {
            return trim((string) (DB::table('generals')->where('code', $code)->value('cvalue') ?? ''));
        } catch (Throwable) {
            return '';
        }
    }
}
