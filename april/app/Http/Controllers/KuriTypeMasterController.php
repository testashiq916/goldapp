<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KuriTypeMasterController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('kuri-type-master.index');
    }

    public function load(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        if (!$this->hasTable('kuritype')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $rows = DB::table('kuritype')->orderBy('code')->get();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    /**
     * Save all rows — PB logic: DELETE ALL then INSERT ALL
     */
    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $rows = $request->input('rows', []);
        if (!is_array($rows)) return response()->json(['ok' => false, 'message' => 'Invalid data']);

        $cols = array_map('strtolower', $this->columnList('kuritype'));

        DB::beginTransaction();
        try {
            DB::table('kuritype')->delete();

            foreach ($rows as $r) {
                $code = strtoupper(trim($r['code'] ?? ''));
                $name = trim($r['name'] ?? '');
                if ($code === '' || $name === '') continue;

                $data = [
                    'code'       => $code,
                    'name'       => $name,
                    'instnos'    => (int) ($r['instnos'] ?? 0),
                    'instamt'    => (float) ($r['instamt'] ?? 0),
                    'totamt'     => (float) ($r['totamt'] ?? 0),
                    'bonus'      => (float) ($r['bonus'] ?? 0),
                    'colntype'   => strtoupper(trim($r['colntype'] ?? 'M')),
                    'prefix'     => strtoupper(trim($r['prefix'] ?? '')),
                    'lastno'     => (int) ($r['lastno'] ?? 0),
                    'collnlimit' => (float) ($r['collnlimit'] ?? 0),
                    'collnmin'   => (float) ($r['collnmin'] ?? 0),
                    'comnrate'   => (float) ($r['comnrate'] ?? 0),
                ];

                // Only insert columns that exist
                $filtered = [];
                foreach ($data as $k => $v) {
                    if (in_array($k, $cols)) $filtered[$k] = $v;
                }

                DB::table('kuritype')->insert($filtered);
            }

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Saved ' . count($rows) . ' type(s)']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Get a single kuritype by code (for auto-fill in customer form)
     */
    public function getType(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'Code required']);

        if (!$this->hasTable('kuritype')) {
            return response()->json(['ok' => false, 'message' => 'Table not found']);
        }

        $row = DB::table('kuritype')->where('code', $code)->first();
        if (!$row) return response()->json(['ok' => false, 'message' => 'Type not found']);

        return response()->json(['ok' => true, 'row' => $row]);
    }

    /**
     * Check if a kuritype code is in use before allowing delete
     */
    public function checkUsage(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') return response()->json(['ok' => true, 'count' => 0]);

        $count = 0;
        if ($this->hasTable('clients_kuridet')) {
            $count = DB::table('clients_kuridet')->where('kuritype', $code)->count();
        }

        return response()->json(['ok' => true, 'count' => $count]);
    }
}
