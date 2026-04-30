<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockTypeRequest;
use App\Http\Controllers\Concerns\LogsDelpartAudit;
use App\Models\StockType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class StockTypeController extends Controller
{
    use LogsDelpartAudit;

    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $giaccount = (int) config('app.giaccount', 1);
        $tableMissing = !$this->hasTable('stktype');
        return view('stocktype.index', compact('giaccount', 'tableMissing'));
    }

    public function getByCode(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('stktype')) {
            return response()->json(['success' => false, 'message' => 'stktype table not found'], 500);
        }

        $code = (string) $request->input('code', '');
        $stockType = StockType::getByCode($code);

        if ($stockType) {
            return response()->json(['success' => true, 'data' => $stockType]);
        }

        return response()->json(['success' => false, 'message' => 'Stock type not found'], 404);
    }

    public function getList(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('stktype')) {
            return response()->json(['success' => false, 'message' => 'stktype table not found'], 500);
        }

        $stockTypes = StockType::orderBy('code')->get();
        return response()->json(['success' => true, 'data' => $stockTypes]);
    }

    public function checkCode(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['exists' => false], 401);
        }
        if (!$this->hasTable('stktype')) {
            return response()->json(['exists' => false], 500);
        }

        $code = (string) $request->input('code', '');
        return response()->json(['exists' => StockType::codeExists($code)]);
    }

    public function store(StoreStockTypeRequest $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('stktype')) {
            return response()->json(['success' => false, 'message' => 'stktype table not found'], 500);
        }

        try {
            DB::beginTransaction();

            $stockType = StockType::create([
                'code' => strtoupper(trim((string) $request->input('code'))),
                'name' => trim((string) $request->input('name')),
                'def' => $request->boolean('def') ? 1 : 0,
                'compare' => $request->boolean('compare') ? 1 : 0,
            ]);

            if ($request->boolean('def')) {
                $stockType->setAsDefault();
            }

            DB::commit();
            $this->logDelpart($request, 'Stock Type(' . $stockType->code . ') Added', ['utype' => 'A', 'ttype' => 'R']);
            return response()->json(['success' => true, 'message' => 'Stock type created successfully', 'data' => $stockType]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error creating stock type: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, string $code): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('stktype')) {
            return response()->json(['success' => false, 'message' => 'stktype table not found'], 500);
        }

        $request->validate([
            'name' => 'required|string|max:30',
            'def' => 'nullable|boolean',
            'compare' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $stockType = StockType::where('code', strtoupper(trim($code)))->firstOrFail();
            $stockType->update([
                'name' => trim((string) $request->input('name')),
                'def' => $request->boolean('def') ? 1 : 0,
                'compare' => $request->boolean('compare') ? 1 : 0,
            ]);

            if ($request->boolean('def')) {
                $stockType->setAsDefault();
            }

            DB::commit();
            $this->logDelpart($request, 'Stock Type(' . $stockType->code . ') Updated', ['utype' => 'E', 'ttype' => 'R']);
            return response()->json(['success' => true, 'message' => 'Stock type updated successfully', 'data' => $stockType]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error updating stock type: ' . $e->getMessage()], 500);
        }
    }

    public function checkUsage(Request $request, string $code): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('stktype')) {
            return response()->json(['success' => false, 'message' => 'stktype table not found'], 500);
        }

        $stockType = StockType::where('code', strtoupper(trim($code)))->firstOrFail();
        return response()->json(['success' => true, 'usage' => $stockType->isInUse()]);
    }

    public function destroy(Request $request, string $code): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('stktype')) {
            return response()->json(['success' => false, 'message' => 'stktype table not found'], 500);
        }

        try {
            DB::beginTransaction();
            $stockType = StockType::where('code', strtoupper(trim($code)))->firstOrFail();
            $forceDelete = $request->boolean('force_delete');
            $usage = $stockType->isInUse();

            if ($usage['total'] > 0 && !$forceDelete) {
                if ($usage['total'] === $usage['itemsonly']) {
                    return response()->json([
                        'success' => false,
                        'confirm_required' => true,
                        'message' => "Items exist with this stock type.\nDo you want to delete this code?",
                        'usage' => $usage,
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'message' => "Items exist with this entry.\nYou can't delete this code.",
                    'usage' => $usage,
                ], 400);
            }

            $stockType->deleteWithItems();
            DB::commit();
            $this->logDelpart($request, 'Stock Type(' . $stockType->code . ') Deleted', ['utype' => 'D', 'ttype' => 'R']);
            return response()->json(['success' => true, 'message' => 'Stock type deleted successfully']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error deleting stock type: ' . $e->getMessage()], 500);
        }
    }

    public function getDefault(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('stktype')) {
            return response()->json(['success' => false, 'message' => 'stktype table not found'], 500);
        }

        return response()->json(['success' => true, 'data' => StockType::getDefault()]);
    }

    public function moduleSettings(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        return view('stocktype.module-settings');
    }

    private function isAuthorized(Request $request): bool
    {
        if ((string) ($request->session()->get('user_code') ?? '') !== '') {
            return true;
        }

        $legacySessionId = preg_replace('/[^a-zA-Z0-9,-]/', '', (string) ($_COOKIE['PHPSESSID'] ?? ''));
        if ($legacySessionId === '') {
            return false;
        }

        $savePath = (string) ini_get('session.save_path');
        if ($savePath === '') {
            return false;
        }
        if (str_contains($savePath, ';')) {
            $parts = explode(';', $savePath);
            $savePath = (string) end($parts);
        }
        $savePath = trim($savePath);
        if ($savePath === '') {
            return false;
        }

        $sessionFile = rtrim($savePath, "/\\") . DIRECTORY_SEPARATOR . 'sess_' . $legacySessionId;
        if (!is_file($sessionFile) || !is_readable($sessionFile)) {
            return false;
        }

        $raw = (string) @file_get_contents($sessionFile);
        return $raw !== '' && str_contains($raw, 'user_code|');
    }
}
