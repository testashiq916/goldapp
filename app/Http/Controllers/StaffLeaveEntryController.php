<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffLeaveEntryController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('staff-leave-entry.index', [
            'title' => 'Staff Leave Entry',
        ]);
    }

    // Load staff list with leave data for a specific date
    public function loadData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $date = $request->query('date', date('Y-m-d'));

        // Staff list: ctype=F, removed=0
        $staff = DB::table('clients')
            ->where('ctype', 'F')
            ->where('removed', 0)
            ->select('code', 'name', 'salary')
            ->orderBy('name')
            ->get();

        // Existing leave entries for this date
        $leaves = DB::table('staffleave')
            ->where('tdate', $date)
            ->get()
            ->keyBy('staff');

        $rows = [];
        foreach ($staff as $s) {
            $code = trim($s->code ?? '');
            $leave = $leaves[$code] ?? null;

            $rows[] = [
                'code'     => $code,
                'name'     => trim($s->name ?? ''),
                'salary'   => (float) ($s->salary ?? 0),
                'ldays'    => $leave ? (float) ($leave->ldays ?? 0) : 0,
                'plusdays'  => $leave ? (float) ($leave->plusdays ?? 0) : 0,
                'reason'   => $leave ? trim($leave->reason ?? '') : '',
                'note'     => $leave ? trim($leave->note ?? '') : '',
            ];
        }

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Save leave entries for a date
    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $date  = $request->input('date', '');
        $items = (array) $request->input('items', []);

        if ($date === '') return response()->json(['ok' => false, 'message' => 'Date required']);

        DB::beginTransaction();
        try {
            // Delete all existing entries for this date
            DB::table('staffleave')->where('tdate', $date)->delete();

            $saved = 0;
            foreach ($items as $item) {
                $code    = trim($item['code'] ?? '');
                $ldays   = (float) ($item['ldays'] ?? 0);
                $plusdays = (float) ($item['plusdays'] ?? 0);
                $reason  = trim($item['reason'] ?? '');
                $note    = trim($item['note'] ?? '');

                if ($code === '') continue;
                if ($ldays <= 0 && $plusdays <= 0) continue;

                DB::table('staffleave')->insert([
                    'tdate'    => $date,
                    'staff'    => $code,
                    'ldays'    => $ldays,
                    'reason'   => $reason,
                    'note'     => $note,
                    'plusdays'  => $plusdays,
                ]);
                $saved++;
            }

            DB::commit();
            return response()->json(['ok' => true, 'message' => "Saved {$saved} leave entries"]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
