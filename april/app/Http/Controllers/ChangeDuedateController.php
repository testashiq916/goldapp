<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChangeDuedateController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('accounts-change-duedate.index', [
            'title' => 'Change Duedate',
        ]);
    }

    // Lookup party — return name + old duedate
    public function loadParty(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = strtoupper(trim($request->query('code', '')));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'Code required']);

        $client = DB::table('clients')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->first();
        if (!$client) return response()->json(['ok' => false, 'message' => 'This code does not exist']);

        $duedate = $client->duedate ?? null;
        if ($duedate === '0000-00-00') $duedate = null;

        return response()->json([
            'ok'      => true,
            'name'    => trim($client->name ?? ''),
            'duedate' => $duedate,
        ]);
    }

    // Search party
    public function searchParty(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim($request->query('q', ''));
        if ($q === '') return response()->json(['ok' => true, 'rows' => []]);

        $rows = DB::table('clients')
            ->where(function ($query) use ($q) {
                $query->where('code', 'like', $q . '%')
                      ->orWhere('name', 'like', '%' . $q . '%');
            })
            ->select('code', 'name')
            ->orderBy('code')
            ->limit(30)
            ->get()
            ->map(fn($r) => ['code' => trim($r->code ?? ''), 'name' => trim($r->name ?? '')])
            ->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Save — update duedate
    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code    = strtoupper(trim($request->input('code', '')));
        $duedate = trim($request->input('duedate', ''));

        if ($code === '') return response()->json(['ok' => false, 'message' => 'Party required']);
        if ($duedate === '') return response()->json(['ok' => false, 'message' => 'New duedate required']);

        $affected = DB::table('clients')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->update(['duedate' => $duedate]);

        if ($affected > 0) {
            return response()->json(['ok' => true, 'message' => 'Updated']);
        }
        return response()->json(['ok' => false, 'message' => 'Party not found or no change']);
    }
}
