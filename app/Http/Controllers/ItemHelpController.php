<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ItemHelpController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $search = trim((string) $request->query('search', ''));
        $items = collect();
        $tableMissing = !$this->hasTable('items');

        if (!$tableMissing) {
            $q = DB::table('items')
                ->select('code', 'name', DB::raw('regionalname as mname'))
                ->orderBy('name');

            if ($search !== '') {
                $like = '%' . $search . '%';
                $q->where(function ($w) use ($like): void {
                    $w->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like);
                });
            }

            $items = $q->limit(600)->get();
        }

        return view('item-help.index', [
            'search' => $search,
            'items' => $items,
            'tableMissing' => $tableMissing,
        ]);
    }

    private function isAuthorized(Request $request): bool
    {
        return (string) ($request->session()->get('user_code') ?? '') !== '';
    }
}

