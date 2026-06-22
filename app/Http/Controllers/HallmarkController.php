<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class HallmarkController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('hallmark.index', [
            'today' => date('Y-m-d'),
        ]);
    }

    // ── API ───────────────────────────────────────────────────────────────

    public function load(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $search  = trim((string) $request->query('search', ''));
        $batch   = trim((string) $request->query('batch', ''));
        $dateFrom = (string) $request->query('date1', date('Y-m-01'));
        $dateTo   = (string) $request->query('date2', date('Y-m-d'));

        try {
            $q = DB::table('hallmark_records')
                ->whereBetween('hallmark_date', [$dateFrom, $dateTo]);

            if ($batch !== '') {
                $q->where('batch_no', $batch);
            }
            if ($search !== '') {
                $like = '%' . $search . '%';
                $q->where(function ($inner) use ($like) {
                    $inner->where('huid', 'like', $like)
                        ->orWhere('item_code', 'like', $like)
                        ->orWhere('barcode', 'like', $like)
                        ->orWhere('certificate_no', 'like', $like);
                });
            }

            $rows = $q->orderByDesc('hallmark_date')->orderBy('batch_no')->limit(500)->get();
            return response()->json(['ok' => true, 'rows' => $rows]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $records = $request->input('records', []);
        if (empty($records)) {
            return response()->json(['ok' => false, 'message' => 'No records to save']);
        }

        try {
            $saved = 0;
            foreach ($records as $rec) {
                $id = (int) ($rec['id'] ?? 0);
                $data = [
                    'batch_no'       => trim((string) ($rec['batch_no'] ?? '')),
                    'item_code'      => trim((string) ($rec['item_code'] ?? '')),
                    'barcode'        => trim((string) ($rec['barcode'] ?? '')),
                    'huid'           => trim((string) ($rec['huid'] ?? '')) ?: null,
                    'bis_centre'     => trim((string) ($rec['bis_centre'] ?? '')),
                    'purity_grade'   => trim((string) ($rec['purity_grade'] ?? '')),
                    'purity_name'    => trim((string) ($rec['purity_name'] ?? '')),
                    'weight'         => (float) ($rec['weight'] ?? 0),
                    'certificate_no' => trim((string) ($rec['certificate_no'] ?? '')),
                    'hallmark_date'  => (string) ($rec['hallmark_date'] ?? date('Y-m-d')),
                    'article_desc'   => trim((string) ($rec['article_desc'] ?? '')),
                    'notes'          => trim((string) ($rec['notes'] ?? '')),
                    'updated_at'     => now(),
                ];

                if ($id > 0) {
                    DB::table('hallmark_records')->where('id', $id)->update($data);
                } else {
                    DB::table('hallmark_records')->insert(array_merge($data, ['created_at' => now()]));
                }
                $saved++;
            }

            return response()->json(['ok' => true, 'saved' => $saved]);
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
            DB::table('hallmark_records')->delete($id);
            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function checkHuid(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $huid = trim((string) $request->query('huid', ''));
        $id   = (int) $request->query('id', 0);

        if ($huid === '') {
            return response()->json(['ok' => true, 'exists' => false]);
        }

        try {
            $q = DB::table('hallmark_records')->where('huid', $huid);
            if ($id > 0) {
                $q->where('id', '<>', $id);
            }
            $exists = $q->exists();
            return response()->json(['ok' => true, 'exists' => $exists]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function batches(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        try {
            $rows = DB::table('hallmark_records')
                ->selectRaw('batch_no, COUNT(*) as item_count, SUM(weight) as total_weight, MIN(hallmark_date) as batch_date')
                ->whereNotNull('batch_no')
                ->where('batch_no', '<>', '')
                ->groupBy('batch_no')
                ->orderByDesc('batch_date')
                ->get();
            return response()->json(['ok' => true, 'rows' => $rows]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function bisCentres(): JsonResponse
    {
        // Common BIS assaying and hallmarking centres in India
        $centres = [
            'BIS Delhi', 'BIS Mumbai', 'BIS Chennai', 'BIS Kolkata',
            'BIS Ahmedabad', 'BIS Bengaluru', 'BIS Hyderabad', 'BIS Jaipur',
            'BIS Kochi', 'BIS Coimbatore', 'BIS Surat', 'BIS Thrissur',
            'BIS Madurai', 'BIS Chandigarh', 'BIS Lucknow',
        ];
        return response()->json(['ok' => true, 'centres' => $centres]);
    }
}
