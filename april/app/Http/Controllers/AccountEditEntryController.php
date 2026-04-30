<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AccountEditEntryController extends Controller
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

    public function index(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('account.edit-entry');
    }

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        $action = strtolower(trim((string) $request->input('action', '')));
        return match ($action) {
            'list' => $this->actionList($request),
            'resolve' => $this->actionResolve($request),
            default => $this->json(['success' => false, 'error' => 'Invalid action'], 400),
        };
    }

    private function actionList(Request $request): JsonResponse
    {
        if (!$this->hasTable('daybookpart')) return $this->json(['success' => true, 'data' => []]);
        $q = trim((string) $request->input('search', ''));
        $rows = DB::table('daybookpart')
            ->selectRaw('slno, TRIM(vchno) AS vchno, TRIM(particular) AS particular')
            ->when($q !== '', function ($x) use ($q) {
                $like = '%' . $q . '%';
                $x->where(function ($y) use ($like) {
                    $y->whereRaw('TRIM(vchno) LIKE ?', [$like])->orWhereRaw('TRIM(particular) LIKE ?', [$like]);
                });
            })
            ->orderByDesc('slno')
            ->limit(250)
            ->get()
            ->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    private function actionResolve(Request $request): JsonResponse
    {
        $vchno = strtoupper(trim((string) $request->input('vchno', '')));
        if ($vchno === '') return $this->json(['success' => false, 'error' => 'Voucher No required'], 422);

        $level = (int) ($request->session()->get('gilevel', 1));
        if ($level <= 0) $level = 1;

        if (!$this->hasTable('daybookpart') || !$this->hasTable('daybook')) {
            return $this->json(['success' => false, 'error' => 'Required tables missing'], 500);
        }

        $slno = (int) (DB::table('daybookpart')
            ->join('daybook', 'daybook.slno', '=', 'daybookpart.slno')
            ->whereRaw('TRIM(daybookpart.vchno)=?', [$vchno])
            ->where('daybook.control', '<=', $level)
            ->max('daybookpart.slno') ?? 0);

        $pend = 'Y';
        if ($slno <= 0 && $this->hasTable('pdclist')) {
            $row = DB::table('pdclist')
                ->selectRaw('slno, TRIM(pend) AS pend')
                ->whereRaw('TRIM(docno)=?', [$vchno])
                ->where('control', '<=', $level)
                ->orderByDesc('slno')
                ->first();
            if ($row) {
                $slno = (int) ($row->slno ?? 0);
                $pend = strtoupper(trim((string) ($row->pend ?? 'Y')));
            }
        }

        if ($slno <= 0) return $this->json(['success' => false, 'error' => 'Voucher does not exist'], 404);
        if ($pend === 'N') return $this->json(['success' => false, 'error' => "This cheque is already cleared. You can't edit cleared cheques."]);

        $suspCount = $this->hasTable('suspentry')
            ? (int) DB::table('suspentry')->where('slno', $slno)->count()
            : 0;
        $advCount = $this->hasTable('advafter')
            ? (int) DB::table('advafter')->where('slno', $slno)->count()
            : 0;

        if ($suspCount > 0) {
            return $this->json([
                'success' => true,
                'target' => 'suspense',
                'url' => url('/accounts/suspense-entry') . '?mode=E&slno=' . $slno . '&vchno=' . urlencode($vchno),
            ]);
        }
        if ($advCount > 0) {
            return $this->json(['success' => false, 'error' => 'Order advance edit is not implemented yet']);
        }

        if (str_starts_with($vchno, 'VR') || str_starts_with($vchno, 'PDCR')) {
            return $this->json([
                'success' => true,
                'target' => 'receipt',
                'url' => url('/accounts/receipt') . '?mode=E&slno=' . $slno . '&vchno=' . urlencode($vchno),
            ]);
        }
        if (str_starts_with($vchno, 'VP') || str_starts_with($vchno, 'PDCP')) {
            return $this->json([
                'success' => true,
                'target' => 'payment',
                'url' => url('/accounts/payment') . '?mode=E&slno=' . $slno . '&vchno=' . urlencode($vchno),
            ]);
        }

        return $this->json(['success' => false, 'error' => "You can't edit this voucher"]);
    }
}

