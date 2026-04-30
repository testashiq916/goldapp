<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveReorderRequest;
use App\Http\Resources\RotableResource;
use App\Models\Item;
use App\Models\ModelType;
use App\Models\Rotable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReorderController extends Controller
{
    public function index()
    {
        return view('reorder.index');
    }

    public function getItem(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('code')));

        $item = Item::where('code', $code)->first();
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'This code does not exist',
            ], 404);
        }

        $reorderLevels = Rotable::where('code', $code)
            ->orderByRaw('COALESCE(model, "")')
            ->orderByRaw('COALESCE(size, "")')
            ->orderBy('weight1')
            ->orderBy('weight2')
            ->get();

        return response()->json([
            'success' => true,
            'item' => $item,
            'reorderLevels' => RotableResource::collection($reorderLevels),
        ]);
    }

    public function save(SaveReorderRequest $request): JsonResponse
    {
        $code = $request->input('code');
        $levels = $request->input('levels', []);

        DB::beginTransaction();
        try {
            Rotable::where('code', $code)->delete();

            foreach ($levels as $level) {
                $model = strtoupper(trim((string) ($level['model'] ?? '')));
                $size = strtoupper(trim((string) ($level['size'] ?? '')));
                $weight1 = (float) ($level['weight1'] ?? 0);
                $weight2 = (float) ($level['weight2'] ?? 0);
                $minqty = (int) ($level['minqty'] ?? 0);
                $maxqty = (int) ($level['maxqty'] ?? 0);

                if ($weight1 == 0.0 && $weight2 == 0.0) {
                    continue;
                }

                Rotable::create([
                    'code' => $code,
                    'model' => $model !== '' ? $model : null,
                    'size' => $size !== '' ? $size : null,
                    'weight1' => $weight1,
                    'weight2' => $weight2,
                    'minqty' => $minqty,
                    'maxqty' => $maxqty,
                ]);

                if ($model !== '') {
                    ModelType::firstOrCreate([
                        'mtype' => 'M',
                        'name' => $model,
                    ]);
                }

                if ($size !== '') {
                    ModelType::firstOrCreate([
                        'mtype' => 'S',
                        'name' => $size,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Updated successfully',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error saving data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteAll(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('code')));
        Rotable::where('code', $code)->delete();

        return response()->json([
            'success' => true,
            'message' => 'All reorder levels deleted',
        ]);
    }

    public function searchItems(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $items = Item::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('code', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            })
            ->orderBy('code')
            ->limit(50)
            ->get(['code', 'name']);

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }
}

