<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PartyOpWeightController extends Controller
{
    private function json(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    private function hasCol(string $table, string $col): bool
    {
        static $cache = [];
        $key = $table . ':' . strtolower($col);
        if (!isset($cache[$key])) {
            $cache[$key] = Schema::hasColumn($table, $col);
        }
        return $cache[$key];
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }
        return view('wgt-rcpt-pmnt.op-weight');
    }

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action = strtolower(trim((string) $request->input('action', '')));

        switch ($action) {
            case 'account_search':
                return $this->actionAccountSearch($request);
            case 'load':
                return $this->actionLoad($request);
            case 'save':
                return $this->actionSave($request);
            default:
                return $this->json(['success' => false, 'error' => 'Invalid action'], 400);
        }
    }

    private function actionAccountSearch(Request $request): JsonResponse
    {
        if (!$this->hasTable('accountm')) {
            return $this->json(['success' => true, 'data' => []]);
        }

        $search = trim((string) $request->input('search', ''));
        $query = DB::table('accountm')
            ->selectRaw('TRIM(accode) AS accode, TRIM(name) AS name')
            ->when($this->hasCol('accountm', 'removed'), function ($q) {
                $q->whereRaw('(removed <> 1 OR removed IS NULL)');
            });

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('accode', 'like', $like)
                  ->orWhere('name', 'like', $like);
            });
        }

        $rows = $query->orderBy('accode')->limit(200)->get()->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    private function actionLoad(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '' || !$this->hasTable('accountm')) {
            return $this->json(['success' => false, 'error' => 'Account not found'], 404);
        }

        $row = DB::table('accountm')
            ->whereRaw('TRIM(accode) = ?', [$code])
            ->first(['accode', 'name', 'opwgt', 'opwgtb']);

        if (!$row) {
            return $this->json(['success' => false, 'error' => 'This code does not exist'], 404);
        }

        return $this->json([
            'success' => true,
            'account' => [
                'accode' => trim((string) ($row->accode ?? '')),
                'name' => trim((string) ($row->name ?? '')),
                'opwgt' => round((float) ($row->opwgt ?? 0), 3),
                'opwgtb' => round((float) ($row->opwgtb ?? 0), 3),
            ],
        ]);
    }

    private function actionSave(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        $opwgt = round((float) $request->input('opwgt', 0), 3);
        $opwgtb = round((float) $request->input('opwgtb', 0), 3);

        if ($code === '') {
            return $this->json(['success' => false, 'error' => 'Party code is required'], 422);
        }

        if (!$this->hasTable('accountm')) {
            return $this->json(['success' => false, 'error' => 'accountm table not found'], 500);
        }

        $exists = DB::table('accountm')->whereRaw('TRIM(accode) = ?', [$code])->exists();
        if (!$exists) {
            return $this->json(['success' => false, 'error' => 'Account not found'], 404);
        }

        $updates = ['opwgt' => $opwgt];
        if ($this->hasCol('accountm', 'opwgtb')) {
            $updates['opwgtb'] = $opwgtb;
        }

        DB::table('accountm')
            ->whereRaw('TRIM(accode) = ?', [$code])
            ->update($updates);

        return $this->json(['success' => true, 'message' => 'Op. Weight saved for ' . $code]);
    }
}
