<?php

namespace App\Http\Controllers;

use App\Models\GiftTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GiftTableController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $rows = GiftTable::query()
            ->orderBy('points')
            ->get(['points', 'particulars']);

        return view('gift-table.index', ['rows' => $rows]);
    }

    public function save(Request $request): RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $validated = $request->validate([
            'points' => 'nullable|array',
            'points.*' => 'nullable|integer',
            'particulars' => 'nullable|array',
            'particulars.*' => 'nullable|string|max:60',
            'orig_points' => 'nullable|array',
            'orig_points.*' => 'nullable|integer',
        ]);

        $points = $validated['points'] ?? [];
        $particulars = $validated['particulars'] ?? [];
        $origPoints = $validated['orig_points'] ?? [];

        DB::transaction(function () use ($points, $particulars, $origPoints): void {
            $existing = GiftTable::query()
                ->pluck('points')
                ->map(fn ($v) => (int) $v)
                ->all();

            $submittedOrig = array_values(array_filter(
                array_map(static fn ($v) => $v === null ? null : (int) $v, $origPoints),
                static fn ($v) => $v !== null
            ));

            foreach ($existing as $dbPoints) {
                if (!in_array($dbPoints, $submittedOrig, true)) {
                    GiftTable::query()->where('points', $dbPoints)->delete();
                }
            }

            $rowCount = count($points);
            for ($i = 0; $i < $rowCount; $i++) {
                $pts = $points[$i] ?? null;
                $part = trim((string) ($particulars[$i] ?? ''));
                $orig = $origPoints[$i] ?? null;

                if ($pts === null || $pts === '') {
                    continue;
                }

                $pts = (int) $pts;
                $orig = ($orig === null || $orig === '') ? null : (int) $orig;

                if ($orig !== null && $orig !== $pts) {
                    GiftTable::query()->where('points', $orig)->delete();
                }

                GiftTable::query()->updateOrCreate(
                    ['points' => $pts],
                    ['particulars' => $part]
                );
            }
        });

        return redirect()->route('gift-table.index')->with('success', 'Changes saved successfully.');
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

