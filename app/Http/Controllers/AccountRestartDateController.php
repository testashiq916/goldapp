<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountRestartDateController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('accounts-restart-date.index', [
            'title' => 'A/c Restart Date',
        ]);
    }

    // Lookup account — return name + old opdate
    public function loadAccount(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = strtoupper(trim($request->query('code', '')));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'Code required']);

        $row = DB::table('accountm')->whereRaw('UPPER(TRIM(accode)) = ?', [$code])->first();
        if (!$row) return response()->json(['ok' => false, 'message' => 'This code does not exist']);

        $opdate = $row->opdate ?? null;
        if ($opdate === '0000-00-00') $opdate = null;

        return response()->json([
            'ok'     => true,
            'name'   => trim($row->name ?? ''),
            'opdate' => $opdate,
        ]);
    }

    // Search account
    public function searchAccount(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim($request->query('q', ''));
        if ($q === '') return response()->json(['ok' => true, 'rows' => []]);

        $rows = DB::table('accountm')
            ->where(function ($query) use ($q) {
                $query->where('accode', 'like', $q . '%')
                      ->orWhere('name', 'like', '%' . $q . '%');
            })
            ->select('accode', 'name')
            ->orderBy('accode')
            ->limit(30)
            ->get()
            ->map(fn($r) => ['code' => trim($r->accode ?? ''), 'name' => trim($r->name ?? '')])
            ->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Save — update opdate
    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code   = strtoupper(trim($request->input('code', '')));
        $opdate = trim($request->input('opdate', ''));

        if ($code === '') return response()->json(['ok' => false, 'message' => 'Account required']);
        if ($opdate === '') return response()->json(['ok' => false, 'message' => 'Restart date required']);

        $affected = DB::table('accountm')
            ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
            ->update(['opdate' => $opdate]);

        if ($affected > 0) {
            return response()->json(['ok' => true, 'message' => 'Updated']);
        }
        return response()->json(['ok' => false, 'message' => 'Account not found or no change']);
    }
}
