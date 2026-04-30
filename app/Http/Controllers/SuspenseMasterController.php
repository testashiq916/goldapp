<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SuspenseMasterController extends Controller
{
    private function requireLogin(Request $request): void
    {
        if (!$request->session()->has('user_code')) {
            abort(redirect('/login'));
        }
    }

    private function json(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    private function ensureGeneraliCode(string $code): void
    {
        if (!$this->hasTable('generali')) return;
        $exists = DB::table('generali')->where('code', $code)->exists();
        if (!$exists) DB::table('generali')->insert(['code' => $code, 'cvalue' => 0]);
    }

    private function nextCode(): string
    {
        if (!$this->hasTable('generali')) return '0001';
        $this->ensureGeneraliCode('SUSPCODE');
        $n = (int) (DB::table('generali')->where('code', 'SUSPCODE')->value('cvalue') ?? 0);
        return str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('account.suspense-master');
    }

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('suspac')) {
            return $this->json(['success' => false, 'error' => 'Table suspac not found'], 500);
        }

        $action = strtolower(trim((string) $request->input('action', '')));
        return match ($action) {
            'init' => $this->json(['success' => true, 'nextCode' => $this->nextCode()]),
            'list' => $this->actionList($request),
            'get' => $this->actionGet($request),
            'save' => $this->actionSave($request),
            'delete' => $this->actionDelete($request),
            default => $this->json(['success' => false, 'error' => 'Invalid action'], 400),
        };
    }

    private function actionList(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $q = DB::table('suspac')->selectRaw('TRIM(code) AS code, TRIM(name) AS name');
        if ($search !== '') {
            $like = '%' . $search . '%';
            $q->where(function ($x) use ($like) {
                $x->whereRaw('TRIM(code) LIKE ?', [$like])->orWhereRaw('TRIM(name) LIKE ?', [$like]);
            });
        }
        return $this->json(['success' => true, 'data' => $q->orderBy('code')->limit(300)->get()->all()]);
    }

    private function actionGet(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') return $this->json(['success' => false, 'error' => 'Code required'], 422);
        $row = DB::table('suspac')->whereRaw('TRIM(code)=?', [$code])->first();
        if (!$row) return $this->json(['success' => false, 'error' => 'Code not found'], 404);
        return $this->json(['success' => true, 'data' => ['code' => trim((string) $row->code), 'name' => trim((string) $row->name)]]);
    }

    private function actionSave(Request $request): JsonResponse
    {
        $mode = strtoupper(substr(trim((string) $request->input('mode', 'A')), 0, 1));
        $code = strtoupper(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));
        if ($code === '') return $this->json(['success' => false, 'error' => 'Code is empty'], 422);
        if ($name === '') return $this->json(['success' => false, 'error' => 'Name is empty'], 422);

        try {
            DB::beginTransaction();
            if ($mode === 'E') {
                $updated = DB::table('suspac')->whereRaw('TRIM(code)=?', [$code])->update(['name' => $name]);
                if ($updated === 0) {
                    DB::rollBack();
                    return $this->json(['success' => false, 'error' => 'Code not found'], 404);
                }
            } else {
                $exists = DB::table('suspac')->whereRaw('TRIM(code)=?', [$code])->exists();
                if ($exists) {
                    DB::rollBack();
                    return $this->json(['success' => false, 'error' => 'Duplicate code'], 422);
                }
                DB::table('suspac')->insert(['code' => $code, 'name' => $name]);
                if ($this->hasTable('generali')) {
                    $this->ensureGeneraliCode('SUSPCODE');
                    DB::table('generali')->where('code', 'SUSPCODE')->update(['cvalue' => DB::raw('cvalue + 1')]);
                }
            }
            DB::commit();
            return $this->json(['success' => true, 'message' => 'Saved', 'code' => $code, 'nextCode' => $this->nextCode()]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Save failed: ' . $e->getMessage()], 500);
        }
    }

    private function actionDelete(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') return $this->json(['success' => false, 'error' => 'Code required'], 422);

        $used = 0;
        if ($this->hasTable('suspentry')) {
            $used = (int) DB::table('suspentry')->whereRaw('TRIM(scode)=?', [$code])->count();
        }
        if ($used > 0) {
            return $this->json(['success' => false, 'error' => 'Code already used in suspense entry']);
        }

        $deleted = DB::table('suspac')->whereRaw('TRIM(code)=?', [$code])->delete();
        if ($deleted === 0) return $this->json(['success' => false, 'error' => 'Code not found'], 404);
        return $this->json(['success' => true, 'message' => 'Deleted', 'nextCode' => $this->nextCode()]);
    }
}

