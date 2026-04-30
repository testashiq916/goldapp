<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffTransactionController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('staff-transaction.index', [
            'title' => 'Staff A/c Transactions',
        ]);
    }

    // Load staff list (normal mode)
    public function loadStaff(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $level = (int) $request->session()->get('level', 1);

        $rows = DB::table('clients')
            ->where('ctype', 'S')
            ->select('code', 'name', 'salary')
            ->orderBy('code')
            ->get()
            ->map(function ($r) {
                return [
                    'code'   => trim($r->code ?? ''),
                    'name'   => trim($r->name ?? ''),
                    'salary' => (float) ($r->salary ?? 0),
                    'amount' => 0,
                    'tleave' => 0,
                    'pdays'  => 0,
                    'cutamt' => 0,
                    'addamt' => 0,
                ];
            })->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Calculate salary with leave deductions
    public function calcSalary(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $dateStr = trim($request->input('date', ''));
        $dtdate = $dateStr ? date('Y-m-d', strtotime($dateStr)) : date('Y-m-d');

        $day = (int) date('d', strtotime($dtdate));

        // Determine period (dtdate1 to dtdate2)
        if ($day < 5) {
            // Previous month
            $dtdate2 = date('Y-m-d', strtotime($dtdate . ' -' . $day . ' days'));
            $dtdate1 = date('Y-m-01', strtotime($dtdate2));
        } else {
            $dtdate1 = date('Y-m-01', strtotime($dtdate));
            $nextMonth = date('Y-m-d', strtotime($dtdate1 . ' +36 days'));
            $daysInNext = (int) date('d', strtotime($nextMonth));
            $dtdate2 = date('Y-m-d', strtotime($nextMonth . ' -' . $daysInNext . ' days'));
        }

        $monthDays = (int) ((strtotime($dtdate2) - strtotime($dtdate1)) / 86400) + 1;

        // Get staff list
        $staffRows = DB::table('clients')
            ->where('ctype', 'S')
            ->select('code', 'name', 'salary', 'cutrate')
            ->orderBy('code')
            ->get();

        $rows = [];
        foreach ($staffRows as $r) {
            $code   = trim($r->code ?? '');
            $salary = (float) ($r->salary ?? 0);
            $cutrate = (float) ($r->cutrate ?? 0);

            // Get leave days
            $leave = DB::table('staffleave')
                ->where('staff', $code)
                ->where('tdate', '>=', $dtdate1)
                ->where('tdate', '<=', $dtdate2)
                ->selectRaw('COALESCE(SUM(ldays), 0) as tldays, COALESCE(SUM(plusdays), 0) as tplusdays')
                ->first();

            $ldays    = (float) ($leave->tldays ?? 0);
            $plusdays  = (float) ($leave->tplusdays ?? 0);

            // Calculate cut/add
            if ($cutrate > 0) {
                $cutamt  = $ldays * $cutrate;
                $addamt  = $plusdays * $cutrate;
            } else {
                $itdays = $monthDays; // days in month for period
                if ($itdays > 0) {
                    $cutamt  = ($ldays * $salary) / $itdays;
                    $addamt  = ($plusdays * $salary) / $itdays;
                } else {
                    $cutamt = 0;
                    $addamt = 0;
                }
            }

            $amount = $salary - $cutamt + $addamt;

            $rows[] = [
                'code'   => $code,
                'name'   => trim($r->name ?? ''),
                'salary' => $salary,
                'amount' => round($amount, 2),
                'tleave' => $ldays,
                'pdays'  => $plusdays,
                'cutamt' => round($cutamt, 2),
                'addamt' => round($addamt, 2),
            ];
        }

        // Period label
        $monthLabel = date('F', strtotime($dtdate1));
        $yearLabel  = date('Y', strtotime($dtdate1));

        return response()->json([
            'ok'    => true,
            'rows'  => $rows,
            'period' => "Salary Allocation ({$monthLabel}-{$yearLabel})",
            'dtdate1' => $dtdate1,
            'dtdate2' => $dtdate2,
            'monthDays' => $monthDays,
        ]);
    }

    // Log-based salary calculation
    public function logBasedCalc(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $dateStr       = trim($request->input('date', ''));
        $allowedLeaves = (int) $request->input('allowedLeaves', 0);
        $wrkHours      = (float) $request->input('wrkHours', 8);

        $dtdate = $dateStr ? date('Y-m-d', strtotime($dateStr)) : date('Y-m-d');
        $day = (int) date('d', strtotime($dtdate));

        // Determine period
        if ($day < 5) {
            $dtdate2 = date('Y-m-d', strtotime($dtdate . ' -' . $day . ' days'));
            $dtdate1 = date('Y-m-01', strtotime($dtdate2));
        } else {
            $dtdate1 = date('Y-m-01', strtotime($dtdate));
            $nextMonth = date('Y-m-d', strtotime($dtdate1 . ' +36 days'));
            $daysInNext = (int) date('d', strtotime($nextMonth));
            $dtdate2 = date('Y-m-d', strtotime($nextMonth . ' -' . $daysInNext . ' days'));
        }

        $monthDays = (int) ((strtotime($dtdate2) - strtotime($dtdate1)) / 86400) + 1;
        $reqHours  = $wrkHours * ($monthDays - $allowedLeaves);

        $staffRows = DB::table('clients')
            ->where('ctype', 'S')
            ->select('code', 'name', 'salary')
            ->orderBy('code')
            ->get();

        $rows = [];
        foreach ($staffRows as $r) {
            $code   = trim($r->code ?? '');
            $salary = (float) ($r->salary ?? 0);

            // Count attendance days from staff_log
            $attendance = (int) DB::table('staff_log')
                ->where('scode', $code)
                ->where('tdate', '>=', $dtdate1)
                ->where('tdate', '<=', $dtdate2)
                ->distinct('tdate')
                ->count('tdate');

            $presHours = $attendance * $wrkHours;
            $leave = 0;
            $extraDays = 0;

            if ($monthDays - $attendance > $allowedLeaves) {
                $leave = $monthDays - $attendance - $allowedLeaves;
            } elseif ($monthDays - $attendance < $allowedLeaves) {
                $extraDays = $allowedLeaves - ($monthDays - $attendance);
            }

            $addamt = ($monthDays > 0) ? ($salary / $monthDays * $extraDays) : 0;
            $cutamt = ($monthDays > 0) ? ($salary / $monthDays * $leave) : 0;

            // Calculate total hours from staff_log
            $totalHours = $this->calcStaffHours($code, $dtdate1, $dtdate2);

            if ($totalHours > $reqHours) {
                $addamt += ($totalHours - $reqHours) * $salary / ($monthDays * $wrkHours);
            } elseif ($totalHours < $presHours && $totalHours > 0) {
                $cutamt += ($presHours - $totalHours) * $salary / ($monthDays * $wrkHours);
            }

            $amount = $salary + $addamt - $cutamt;

            $rows[] = [
                'code'       => $code,
                'name'       => trim($r->name ?? ''),
                'salary'     => $salary,
                'amount'     => round($amount, 2),
                'tleave'     => $leave,
                'pdays'      => $extraDays,
                'cutamt'     => round($cutamt, 2),
                'addamt'     => round($addamt, 2),
                'attendance' => $attendance,
                'totalHours' => round($totalHours, 2),
            ];
        }

        $monthLabel = date('F', strtotime($dtdate1));
        $yearLabel  = date('Y', strtotime($dtdate1));

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'period' => "Salary Allocation ({$monthLabel}-{$yearLabel})",
        ]);
    }

    // Calculate total work hours from staff_log
    private function calcStaffHours(string $code, string $from, string $to): float
    {
        if (!$this->hasTable('staff_log')) return 0;

        $logs = DB::table('staff_log')
            ->where('scode', $code)
            ->where('tdate', '>=', $from)
            ->where('tdate', '<=', $to)
            ->orderBy('tdate')
            ->orderBy('ttime')
            ->get();

        $totalHours = 0;
        $dayLogs = [];

        // Group by date
        foreach ($logs as $log) {
            $d = $log->tdate ?? '';
            $dayLogs[$d][] = $log->ttime ?? '';
        }

        // For each day, calc hours between first in and last out
        foreach ($dayLogs as $times) {
            if (count($times) < 2) continue;
            sort($times);
            $first = strtotime($times[0]);
            $last  = strtotime(end($times));
            if ($first && $last && $last > $first) {
                $totalHours += ($last - $first) / 3600;
            }
        }

        return $totalHours;
    }

    // Save transaction
    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $dateStr     = trim($request->input('date', ''));
        $accode      = strtoupper(trim($request->input('accode', 'CASH')));
        $particular  = trim($request->input('particular', ''));
        $isDebit     = (bool) $request->input('isDebit', true);
        $isSalary    = (bool) $request->input('isSalary', false);
        $staffItems  = (array) $request->input('items', []);
        $level       = (int) $request->session()->get('level', 1);
        $userId      = $request->session()->get('user_code', '');
        $incharge    = $request->session()->get('incharge', '');

        $dtdate = $dateStr ? date('Y-m-d', strtotime($dateStr)) : date('Y-m-d');

        if (empty($staffItems)) {
            return response()->json(['ok' => false, 'message' => 'No items to save']);
        }

        // Get next voucher number
        $vchKey = ($level === 1) ? 'VCHNOJB' : 'VCHNOJE';
        $vchPrefix = ($level === 1) ? 'JLB/' : 'JLE/';

        DB::beginTransaction();
        try {
            // Get and increment SERIALNO
            $currentSerial = (int) (DB::table('generali')->where('code', 'SERIALNO')->value('cvalue') ?? 0);
            $maxUsed = 0;
            foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
                if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                    $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
                }
            }
            $slno = max($currentSerial, $maxUsed) + 1;
            DB::table('generali')->updateOrInsert(['code' => 'SERIALNO'], ['cvalue' => $slno]);

            // Get and increment voucher number
            $lvchno = (int) DB::table('generali')->where('code', $vchKey)->value('cvalue');
            $svchno = $vchPrefix . str_pad($lvchno + 1, 5, '0', STR_PAD_LEFT);
            DB::table('generali')->where('code', $vchKey)->increment('cvalue');

            // Default particular
            if ($particular === '') {
                if ($isSalary) {
                    $d = strtotime($dtdate);
                    if ((int) date('d', $d) > 27) {
                        $particular = 'Salary Allocation (' . date('F', $d) . ')';
                    } else {
                        $prevMonth = strtotime(date('Y-m-01', $d) . ' -1 day');
                        $particular = 'Salary Allocation (' . date('F', $prevMonth) . ')';
                    }
                } else {
                    $particular = 'By Journal entry ' . $svchno;
                }
            }
            $particular = substr($particular, 0, 40);

            // Insert daybookpart
            DB::table('daybookpart')->insert([
                'slno'       => $slno,
                'vchno'      => $svchno,
                'particular' => $particular,
                'ic'         => $incharge,
                'uid'        => $userId,
            ]);

            $dtamt = 0;
            $dtcut = 0;
            $dtadd = 0;
            $sopaccode = $accode;

            foreach ($staffItems as $item) {
                $staffCode = strtoupper(trim($item['code'] ?? ''));
                $amount    = (float) ($item['amount'] ?? 0);
                $salary    = (float) ($item['salary'] ?? 0);
                $cutamt    = (float) ($item['cutamt'] ?? 0);
                $addamt    = (float) ($item['addamt'] ?? 0);

                if ($isSalary) {
                    // Salary entry
                    if ($salary != 0) {
                        $dacamt = $isDebit ? -$salary : $salary;
                        DB::table('daybook')->insert([
                            'slno'     => $slno,
                            'tdate'    => $dtdate,
                            'accode'   => $staffCode,
                            'amount'   => $dacamt,
                            'control'  => $level,
                            'opaccode' => $sopaccode,
                        ]);
                        $dtamt += $salary;
                    }

                    // Cut entry
                    if ($cutamt != 0) {
                        $dacamt = $isDebit ? $cutamt : -$cutamt;
                        DB::table('daybook')->insert([
                            'slno'     => $slno,
                            'tdate'    => $dtdate,
                            'accode'   => $staffCode,
                            'amount'   => $dacamt,
                            'control'  => $level,
                            'opaccode' => 'SALCUT',
                        ]);
                        $dtcut += $cutamt;
                    }

                    // Add entry
                    if ($addamt != 0) {
                        $dacamt = $isDebit ? -$addamt : $addamt;
                        DB::table('daybook')->insert([
                            'slno'     => $slno,
                            'tdate'    => $dtdate,
                            'accode'   => $staffCode,
                            'amount'   => $dacamt,
                            'control'  => $level,
                            'opaccode' => 'SALADD',
                        ]);
                        $dtadd += $addamt;
                    }
                } else {
                    // Normal transaction
                    if ($amount > 0) {
                        $dacamt = $isDebit ? -$amount : $amount;
                        DB::table('daybook')->insert([
                            'slno'     => $slno,
                            'tdate'    => $dtdate,
                            'accode'   => $staffCode,
                            'amount'   => $dacamt,
                            'control'  => $level,
                            'opaccode' => $sopaccode,
                        ]);
                        $dtamt += $amount;
                    }
                }
            }

            // Opposite entry for main account
            $dacamt = $isDebit ? $dtamt : -$dtamt;

            // Get max accode from daybook for this slno as opaccode
            $maxAc = DB::table('daybook')->where('slno', $slno)->max('accode') ?? '';

            DB::table('daybook')->insert([
                'slno'     => $slno,
                'tdate'    => $dtdate,
                'accode'   => $accode,
                'amount'   => $dacamt,
                'control'  => $level,
                'opaccode' => $maxAc,
            ]);

            // SALCUT opposite entry
            if ($isSalary && $dtcut > 0) {
                $dacamt = $isDebit ? -$dtcut : $dtcut;
                $maxAc2 = DB::table('daybook')
                    ->where('slno', $slno)
                    ->where('accode', '!=', $maxAc)
                    ->max('accode') ?? '';

                DB::table('daybook')->insert([
                    'slno'     => $slno,
                    'tdate'    => $dtdate,
                    'accode'   => 'SALCUT',
                    'amount'   => $dacamt,
                    'control'  => $level,
                    'opaccode' => $maxAc2,
                ]);
            }

            // SALADD opposite entry
            if ($isSalary && $dtadd > 0) {
                $dacamt = $isDebit ? $dtadd : -$dtadd;
                $maxAc3 = DB::table('daybook')
                    ->where('slno', $slno)
                    ->where('accode', '!=', $maxAc)
                    ->max('accode') ?? '';

                DB::table('daybook')->insert([
                    'slno'     => $slno,
                    'tdate'    => $dtdate,
                    'accode'   => 'SALADD',
                    'amount'   => $dacamt,
                    'control'  => $level,
                    'opaccode' => $maxAc3,
                ]);
            }

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Transaction saved', 'vchno' => $svchno]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    // Delete a row (just client-side, no DB action)
    // Lookup account code
    public function lookupAccount(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = strtoupper(trim($request->query('code', '')));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'No code']);

        $row = DB::table('clients')->where('code', $code)->first(['code', 'name']);
        if (!$row) return response()->json(['ok' => false, 'message' => 'Account not found']);

        return response()->json(['ok' => true, 'code' => trim($row->code), 'name' => trim($row->name ?? '')]);
    }
}
