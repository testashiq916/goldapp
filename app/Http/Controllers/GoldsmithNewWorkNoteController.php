<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GoldsmithNewWorkNoteController extends Controller
{
    public function list(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('smithnewwrk')) {
            return response()->json(['success' => false, 'message' => 'Table smithnewwrk not found'], 422);
        }

        $smithCode = strtoupper(trim((string) $request->query('smithcode', '')));
        $type = strtolower(trim((string) $request->query('type', 'pending')));

        $q = DB::table('smithnewwrk as s')
            ->selectRaw('s.sno, s.smithcode, s.tdate, s.ordno, s.icode, s.qty, s.weight, s.part, s.status');

        if ($this->hasTable('clients')) {
            $q->leftJoin('clients as c', 'c.code', '=', 's.smithcode')
                ->addSelect(DB::raw('c.name as smithname'));
        } else {
            $q->addSelect(DB::raw("'' as smithname"));
        }

        if ($this->hasTable('items')) {
            $q->leftJoin('items as i', 'i.code', '=', 's.icode')
                ->addSelect(DB::raw('i.name as itemname'));
        } else {
            $q->addSelect(DB::raw("'' as itemname"));
        }

        if ($type === 'new-work') {
            $q->where('s.status', 1);
        } elseif ($type === 'work-in-progress') {
            $q->where('s.status', 2);
        } elseif ($type === 'work-finished') {
            $q->where('s.status', 3);
        } elseif ($type === 'pending') {
            $q->where('s.status', '<>', 3);
        }

        if ($smithCode !== '') {
            $q->whereRaw("TRIM(COALESCE(s.smithcode, '')) = ?", [$smithCode]);
        }

        $rows = $q->orderBy('s.tdate')->orderBy('s.smithcode')->orderBy('s.icode')->get();

        return response()->json(['success' => true, 'rows' => $rows]);
    }

    public function save(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('smithnewwrk')) {
            return response()->json(['success' => false, 'message' => 'Table smithnewwrk not found'], 422);
        }

        $rows = $request->input('rows', []);
        if (is_string($rows)) {
            $rows = json_decode($rows, true) ?: [];
        }
        if (!is_array($rows)) {
            $rows = [];
        }

        $deletedIds = $request->input('deleted_ids', []);
        if (is_string($deletedIds)) {
            $deletedIds = json_decode($deletedIds, true) ?: [];
        }
        if (!is_array($deletedIds)) {
            $deletedIds = [];
        }

        DB::beginTransaction();
        try {
            $deletedIds = array_values(array_filter(array_map('intval', $deletedIds), fn ($v) => $v > 0));
            if ($deletedIds) {
                DB::table('smithnewwrk')->whereIn('sno', $deletedIds)->delete();
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $sno = (int) ($row['sno'] ?? 0);
                $smithCode = strtoupper(trim((string) ($row['smithcode'] ?? '')));
                $data = [
                    'smithcode' => $smithCode,
                    'tdate' => $this->normDate((string) ($row['tdate'] ?? date('Y-m-d'))),
                    'ordno' => strtoupper(trim((string) ($row['ordno'] ?? ''))),
                    'icode' => strtoupper(trim((string) ($row['icode'] ?? ''))),
                    'qty' => (int) ($row['qty'] ?? 0),
                    'weight' => (float) ($row['weight'] ?? 0),
                    'part' => trim((string) ($row['part'] ?? '')),
                    'status' => $this->statusValue((int) ($row['status'] ?? 1)),
                ];

                if ($sno > 0) {
                    if ($smithCode === '') {
                        DB::table('smithnewwrk')->where('sno', $sno)->delete();
                    } else {
                        DB::table('smithnewwrk')->where('sno', $sno)->update($data);
                    }
                    continue;
                }

                if ($smithCode !== '') {
                    DB::table('smithnewwrk')->insert($data);
                }
            }

            DB::table('smithnewwrk')
                ->whereNull('smithcode')
                ->orWhereRaw("TRIM(COALESCE(smithcode, '')) = ''")
                ->delete();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Updation Completed...']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function delete(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('smithnewwrk')) {
            return response()->json(['success' => false, 'message' => 'Table smithnewwrk not found'], 422);
        }

        $sno = (int) $request->input('sno', 0);
        if ($sno <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid sno'], 422);
        }

        DB::table('smithnewwrk')->where('sno', $sno)->delete();
        return response()->json(['success' => true, 'message' => 'Deleted']);
    }

    private function normDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return date('Y-m-d');
        }

        try {
            return date('Y-m-d', strtotime($value));
        } catch (\Throwable $e) {
            return date('Y-m-d');
        }
    }

    private function statusValue(int $status): int
    {
        if (!in_array($status, [1, 2, 3], true)) {
            return 1;
        }
        return $status;
    }
}
