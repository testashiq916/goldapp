<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class ItemCatalogueController extends Controller
{
    private string $uploadDir = 'uploads/catalogue';

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('catalogue.index');
    }

    public function gallery(Request $request): View
    {
        // Public-facing catalogue (no auth required)
        $shopName = $this->generalValue('SHOPNM');
        $shopPhone = $this->generalValue('SHOPPHONE');
        return view('catalogue.gallery', compact('shopName', 'shopPhone'));
    }

    // ── API ───────────────────────────────────────────────────────────────

    public function list(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $search    = trim((string) $request->query('search', ''));
        $itemCode  = trim((string) $request->query('item_code', ''));

        try {
            $q = DB::table('item_images as i')
                ->leftJoin('items as it', 'it.code', '=', 'i.item_code')
                ->select([
                    'i.id', 'i.item_code', 'i.barcode', 'i.filename',
                    'i.original_name', 'i.sort_order', 'i.is_primary', 'i.created_at',
                    DB::raw('COALESCE(it.name, i.item_code) as item_name'),
                ]);

            if ($itemCode !== '') {
                $q->where('i.item_code', $itemCode);
            }

            if ($search !== '') {
                $like = '%' . $search . '%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('i.item_code', 'like', $like)
                        ->orWhere('i.barcode', 'like', $like)
                        ->orWhere('it.name', 'like', $like);
                });
            }

            $rows = $q->orderBy('i.item_code')->orderBy('i.sort_order')->limit(500)->get();
            $rows = $rows->map(function ($r) {
                $arr = (array) $r;
                $arr['url'] = asset($this->uploadDir . '/' . $r->filename);
                return $arr;
            })->all();

            return response()->json(['ok' => true, 'rows' => $rows]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function upload(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $request->validate([
            'image'     => 'required|image|max:5120',
            'item_code' => 'nullable|string|max:30',
            'barcode'   => 'nullable|string|max:30',
        ]);

        $itemCode = trim((string) $request->input('item_code', ''));
        $barcode  = trim((string) $request->input('barcode', ''));

        if ($itemCode === '' && $barcode === '') {
            return response()->json(['ok' => false, 'message' => 'Item code or barcode required']);
        }

        try {
            $file     = $request->file('image');
            $ext      = $file->getClientOriginalExtension();
            $filename = uniqid('item_', true) . '.' . $ext;
            $destDir  = public_path($this->uploadDir);

            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            $file->move($destDir, $filename);

            // If first image for this item, mark as primary
            $isPrimary = !DB::table('item_images')
                ->where('item_code', $itemCode)
                ->exists();

            $id = DB::table('item_images')->insertGetId([
                'item_code'     => $itemCode ?: null,
                'barcode'       => $barcode ?: null,
                'filename'      => $filename,
                'original_name' => $file->getClientOriginalName(),
                'sort_order'    => 0,
                'is_primary'    => $isPrimary ? 1 : 0,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            return response()->json([
                'ok'  => true,
                'id'  => $id,
                'url' => asset($this->uploadDir . '/' . $filename),
            ]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function delete(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return response()->json(['ok' => false, 'message' => 'Invalid ID']);
        }

        try {
            $row = DB::table('item_images')->find($id);
            if ($row) {
                $path = public_path($this->uploadDir . '/' . $row->filename);
                if (file_exists($path)) {
                    unlink($path);
                }
                DB::table('item_images')->delete($id);
            }
            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function setPrimary(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return response()->json(['ok' => false, 'message' => 'Invalid ID']);
        }

        try {
            $row = DB::table('item_images')->find($id);
            if ($row) {
                DB::table('item_images')->where('item_code', $row->item_code)->update(['is_primary' => 0]);
                DB::table('item_images')->where('id', $id)->update(['is_primary' => 1]);
            }
            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function galleryData(Request $request): JsonResponse
    {
        $group   = trim((string) $request->query('group', ''));
        $search  = trim((string) $request->query('search', ''));

        try {
            $q = DB::table('item_images as i')
                ->join('items as it', 'it.code', '=', 'i.item_code')
                ->select([
                    'i.item_code', 'i.filename', 'i.sort_order',
                    'it.name as item_name',
                    DB::raw('COALESCE(it.igrpcode,"") as group_code'),
                ])
                ->where('i.is_primary', 1);

            if ($group !== '') {
                $q->where('it.igrpcode', $group);
            }
            if ($search !== '') {
                $like = '%' . $search . '%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('it.name', 'like', $like)->orWhere('i.item_code', 'like', $like);
                });
            }

            $rows = $q->orderBy('it.igrpcode')->orderBy('it.name')->limit(200)->get();
            $rows = $rows->map(function ($r) {
                $arr = (array) $r;
                $arr['url'] = asset($this->uploadDir . '/' . $r->filename);
                return $arr;
            })->all();

            return response()->json(['ok' => true, 'rows' => $rows]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    private function generalValue(string $code): string
    {
        try {
            return trim((string) (DB::table('generals')->where('code', $code)->value('cvalue') ?? ''));
        } catch (Throwable) {
            return '';
        }
    }
}
