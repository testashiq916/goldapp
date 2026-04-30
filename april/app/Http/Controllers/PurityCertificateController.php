<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Purity Certificate — print-only certificate with item list.
 * Legacy PB window: w_puritycertificate
 * No persistent storage — certificate number kept in generali (PURITYCERTNO).
 */
class PurityCertificateController extends Controller
{
    private function json(array $p, int $s = 200): JsonResponse { return response()->json($p, $s); }

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('purity-testing.certificate');
    }

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code'))
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);

        $action = strtolower(trim((string)$request->input('action', '')));

        return match ($action) {
            'init'        => $this->actionInit(),
            'item_search' => $this->actionItemSearch($request),
            'increment'   => $this->actionIncrement(),
            default       => $this->json(['success' => false, 'error' => 'Invalid action'], 400),
        };
    }

    private function actionInit(): JsonResponse
    {
        $certNo = 1;
        if ($this->hasTable('generali')) {
            $cur = (int)(DB::table('generali')->where('code', 'PURITYCERTNO')->value('cvalue') ?? 0);
            $certNo = $cur + 1;
        }
        return $this->json(['success' => true, 'certNo' => $certNo, 'today' => date('Y-m-d')]);
    }

    private function actionItemSearch(Request $request): JsonResponse
    {
        if (!$this->hasTable('items')) return $this->json(['success' => true, 'data' => []]);
        $s = strtoupper(trim((string)$request->input('search', '')));
        if ($s === '') return $this->json(['success' => true, 'data' => []]);
        $like = '%' . $s . '%';
        $rows = DB::table('items')
            ->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
            ->where(fn($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like))
            ->orderBy('code')->limit(50)->get()->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    private function actionIncrement(): JsonResponse
    {
        if (!$this->hasTable('generali'))
            return $this->json(['success' => false, 'error' => 'generali not found'], 500);

        if (!DB::table('generali')->where('code', 'PURITYCERTNO')->exists())
            DB::table('generali')->insert(['code' => 'PURITYCERTNO', 'cvalue' => 0]);

        DB::table('generali')->where('code', 'PURITYCERTNO')->update(['cvalue' => DB::raw('cvalue + 1')]);
        $next = (int)(DB::table('generali')->where('code', 'PURITYCERTNO')->value('cvalue') ?? 0) + 1;

        return $this->json(['success' => true, 'nextCertNo' => $next]);
    }
}
