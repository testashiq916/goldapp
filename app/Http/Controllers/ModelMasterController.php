<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveModelsRequest;
use App\Http\Controllers\Concerns\LogsDelpartAudit;
use App\Http\Resources\ProductModelResource;
use App\Models\ProductModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ModelMasterController extends Controller
{
    use LogsDelpartAudit;

    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $type = strtoupper(trim((string) $request->query('type', 'M')));
        if ($type === '') {
            $type = 'M';
        }

        $title = $type === 'S' ? 'Sub Model Master' : 'Model Master';
        $models = ProductModel::getByType($type);

        return view('models.index', compact('models', 'type', 'title'));
    }

    public function getByType(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $type = strtoupper(trim((string) $request->input('type', 'M')));
        $models = ProductModel::getByType($type);

        return response()->json([
            'success' => true,
            'data' => ProductModelResource::collection($models),
        ]);
    }

    public function save(SaveModelsRequest $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            DB::beginTransaction();

            $type = strtoupper(trim((string) $request->input('type')));
            $names = $request->input('names', []);

            ProductModel::bulkUpdateByType($type, $names);

            DB::commit();

            $this->logDelpart($request, ($type === 'S' ? 'Sub Models' : 'Models') . ' Saved', ['utype' => 'E', 'ttype' => 'R']);
            return response()->json([
                'success' => true,
                'message' => 'Models saved successfully',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error saving models: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'id' => 'required|integer',
        ]);

        try {
            $model = ProductModel::findOrFail((int) $request->input('id'));

            if ($model->isUsedInPmcTable()) {
                return response()->json([
                    'success' => false,
                    'message' => "You can't delete this entry as it's being used in PMC table.",
                ], 400);
            }

            $model->delete();

            $this->logDelpart($request, 'Model(' . $model->name . ') Deleted', ['utype' => 'D', 'ttype' => 'R']);
            return response()->json([
                'success' => true,
                'message' => 'Model deleted successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting model: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function checkDuplicate(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['duplicate' => false], 401);
        }

        $name = strtoupper(trim((string) $request->input('name', '')));
        $type = strtoupper(trim((string) $request->input('type', 'M')));
        $excludeId = (int) $request->input('exclude_id', 0);

        if ($name === '') {
            return response()->json(['duplicate' => false]);
        }

        $query = ProductModel::where('mtype', $type)->where('name', $name);
        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        return response()->json([
            'duplicate' => $query->exists(),
        ]);
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
        if ($raw === '' || !str_contains($raw, 'user_code|')) {
            return false;
        }

        return $this->hasTable('models');
    }
}
