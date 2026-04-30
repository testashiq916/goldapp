<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OtherItemsController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $groups = collect();
        if ($this->hasTable('itemgroups')) {
            $groups = DB::table('itemgroups')
                ->select('grpcode', 'grpdesc')
                ->orderBy('grpdesc')
                ->get();
        }

        return view('other-items.index', [
            'groups' => $groups,
            'tableMissing' => !$this->hasTable('itemsothers'),
        ]);
    }

    public function help(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $items = collect();
        if ($this->hasTable('itemsothers')) {
            $items = DB::table('itemsothers')
                ->select('code', 'name')
                ->orderBy('name')
                ->limit(2000)
                ->get();
        }

        return view('other-items.help', [
            'items' => $items,
            'initialSearch' => (string) $request->query('search', ''),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('itemsothers')) {
            return response()->json(['success' => false, 'message' => '`itemsothers` table not found.']);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => true, 'found' => false]);
        }

        $row = DB::table('itemsothers')
            ->select('name', 'grp', 'srate', 'prate', 'opstock', 'stock', 'opcost', 'cost', 'keepstk')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->first();

        if (!$row) {
            return response()->json(['success' => true, 'found' => false]);
        }

        return response()->json(['success' => true, 'found' => true, 'data' => $row]);
    }

    public function add(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('itemsothers')) {
            return response()->json(['success' => false, 'message' => '`itemsothers` table not found.']);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Item code is empty.']);
        }

        $exists = DB::table('itemsothers')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->exists();
        if ($exists) {
            return response()->json(['success' => false, 'message' => 'This item already exists.']);
        }

        DB::table('itemsothers')->insert([
            'code' => $code,
            'name' => strtoupper(trim((string) $request->input('name', ''))),
            'grp' => trim((string) $request->input('grp', '')),
            'srate' => (float) $request->input('srate', 0),
            'prate' => (float) $request->input('prate', 0),
            'cost' => (float) $request->input('cost', 0),
            'opcost' => (float) $request->input('opcost', 0),
            'opstock' => (int) $request->input('opstock', 0),
            'stock' => (int) $request->input('stock', 0),
            'keepstk' => (int) $request->input('keepstk', 1),
        ]);

        return response()->json(['success' => true, 'message' => 'Item added successfully.']);
    }

    public function edit(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('itemsothers')) {
            return response()->json(['success' => false, 'message' => '`itemsothers` table not found.']);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Item code is empty.']);
        }

        DB::table('itemsothers')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->update([
                'name' => strtoupper(trim((string) $request->input('name', ''))),
                'grp' => trim((string) $request->input('grp', '')),
                'srate' => (float) $request->input('srate', 0),
                'prate' => (float) $request->input('prate', 0),
                'cost' => (float) $request->input('cost', 0),
                'opcost' => (float) $request->input('opcost', 0),
                'opstock' => (int) $request->input('opstock', 0),
                'stock' => (int) $request->input('stock', 0),
                'keepstk' => (int) $request->input('keepstk', 1),
            ]);

        return response()->json(['success' => true, 'message' => 'Item updated successfully.']);
    }

    public function delete(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('itemsothers')) {
            return response()->json(['success' => false, 'message' => '`itemsothers` table not found.']);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Item code is empty.']);
        }

        $usage = 0;
        if ($this->hasTable('otheritemtrans') && Schema::hasColumn('otheritemtrans', 'qty')) {
            $usage = (int) DB::table('otheritemtrans')
                ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                ->sum('qty');
        }

        if ($usage > 0) {
            return response()->json(['success' => false, 'message' => "Some entries exist with this item. You can't delete it."]);
        }

        DB::table('itemsothers')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->delete();
        return response()->json(['success' => true, 'message' => 'Item deleted successfully.']);
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

