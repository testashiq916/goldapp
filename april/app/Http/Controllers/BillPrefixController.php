<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class BillPrefixController extends Controller
{
    use LogsDelpartAudit;

    private const FIELD_LIMITS = [
        'code' => 10,
        'name' => 30,
        'formno' => 20,
        'prefix' => 20,
        'pprefix' => 20,
        'srprefix' => 20,
        'prprefix' => 20,
    ];

    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        return view('bill-prefix.index', [
            'salesTypeMissing' => !$this->hasTable('salestype'),
            'generaliMissing' => !$this->hasTable('generali'),
            'salesmMissing' => !$this->hasTable('salesm'),
        ]);
    }

    public function retrieve(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('salestype')) {
            return response()->json(['success' => false, 'message' => '`salestype` table not found.']);
        }

        $rows = DB::table('salestype')->orderBy('code')->get()->map(function ($row) {
            $prefix = trim((string) ($row->prefix ?? ''));
            $pprefix = trim((string) ($row->pprefix ?? ''));
            $srprefix = trim((string) ($row->srprefix ?? ''));
            $prprefix = trim((string) ($row->prprefix ?? ''));

            // Use the higher of: generali counter OR max actual bill number from salesm.
            // This auto-heals stale counters so Invoice Settings always reflects reality.
            $startno = $this->resolveEffectiveCounter('SALES' . $prefix, $prefix, 'salesm', 'billno', (int) ($row->startno ?? 0));
            $srstartno = $this->resolveEffectiveCounter('SRET' . $srprefix, $srprefix, 'salesrm', 'billno', (int) ($row->srstartno ?? 0));
            $pstartno = $this->resolveEffectiveCounter('PURCH' . $pprefix, $pprefix, 'purchasem', 'billno', (int) ($row->pstartno ?? 0));
            $prstartno = $this->resolveEffectiveCounter('PRET' . $prprefix, $prprefix, 'purchaserm', 'billno', (int) ($row->prstartno ?? 0));

            return [
                'code' => (string) ($row->code ?? ''),
                'name' => (string) ($row->name ?? ''),
                'taxperc' => (float) ($row->taxperc ?? 0),
                'formno' => (string) ($row->formno ?? ''),
                'prefix' => $prefix,
                'startno' => $startno,
                'srprefix' => $srprefix,
                'srstartno' => $srstartno,
                'pprefix' => $pprefix,
                'pstartno' => $pstartno,
                'prprefix' => $prprefix,
                'prstartno' => $prstartno,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('salestype')) {
            return response()->json(['success' => false, 'message' => '`salestype` table not found.']);
        }
        if (!$this->hasTable('generali')) {
            return response()->json(['success' => false, 'message' => '`generali` table not found.']);
        }

        $rows = $request->input('rows', []);
        if (!is_array($rows)) {
            return response()->json(['success' => false, 'message' => 'Invalid data']);
        }

        $normalized = [];
        $validationErrors = [];
        foreach ($rows as $r) {
            $code = strtoupper(trim((string) ($r['code'] ?? '')));
            if ($code === '') {
                continue;
            }
            $rowNo = (int) ($r['_row_no'] ?? 0);
            $name = strtoupper(trim((string) ($r['name'] ?? '')));
            $formno = strtoupper(trim((string) ($r['formno'] ?? '')));
            $prefix = strtoupper(trim((string) ($r['prefix'] ?? '')));
            $srprefix = strtoupper(trim((string) ($r['srprefix'] ?? '')));
            $pprefix = strtoupper(trim((string) ($r['pprefix'] ?? '')));
            $prprefix = strtoupper(trim((string) ($r['prprefix'] ?? '')));

            foreach ([
                'code' => $code,
                'name' => $name,
                'formno' => $formno,
                'prefix' => $prefix,
                'pprefix' => $pprefix,
                'srprefix' => $srprefix,
                'prprefix' => $prprefix,
            ] as $field => $value) {
                $limit = self::FIELD_LIMITS[$field] ?? null;
                if ($limit !== null && mb_strlen($value) > $limit) {
                    $rowLabel = $rowNo > 0 ? 'row ' . $rowNo : 'code ' . $code;
                    $validationErrors[] = strtoupper($field) . ' max ' . $limit . ' characters in ' . $rowLabel . '.';
                }
            }

            $safeCode = $this->fitText($code, self::FIELD_LIMITS['code']);
            $normalized[$safeCode] = [
                'code' => $safeCode,
                'name' => $this->fitText($name, self::FIELD_LIMITS['name']),
                'taxperc' => (float) ($r['taxperc'] ?? 0),
                'formno' => $this->fitText($formno, self::FIELD_LIMITS['formno']),
                'prefix' => $this->fitText($prefix, self::FIELD_LIMITS['prefix']),
                'startno' => (int) ($r['startno'] ?? 0),
                'srprefix' => $this->fitText($srprefix, self::FIELD_LIMITS['srprefix']),
                'srstartno' => (int) ($r['srstartno'] ?? 0),
                'pprefix' => $this->fitText($pprefix, self::FIELD_LIMITS['pprefix']),
                'pstartno' => (int) ($r['pstartno'] ?? 0),
                'prprefix' => $this->fitText($prprefix, self::FIELD_LIMITS['prprefix']),
                'prstartno' => (int) ($r['prstartno'] ?? 0),
            ];
        }

        if ($validationErrors !== []) {
            return response()->json(['success' => false, 'message' => $validationErrors[0]], 422);
        }

        // Snapshot existing prefixes BEFORE updating so we can clean up stale generali keys
        $oldPrefixes = [];
        if ($this->hasTable('generali')) {
            foreach (DB::table('salestype')->whereIn('code', array_keys($normalized))->get() as $old) {
                $c = strtoupper(trim((string) ($old->code ?? '')));
                $oldPrefixes[$c] = [
                    'prefix'   => strtoupper(trim((string) ($old->prefix ?? ''))),
                    'srprefix' => strtoupper(trim((string) ($old->srprefix ?? ''))),
                    'pprefix'  => strtoupper(trim((string) ($old->pprefix ?? ''))),
                    'prprefix' => strtoupper(trim((string) ($old->prprefix ?? ''))),
                ];
            }
        }

        $skippedDeletes = 0;
        DB::transaction(function () use ($normalized, $oldPrefixes, &$skippedDeletes): void {
            foreach ($normalized as $row) {
                DB::table('salestype')->updateOrInsert(
                    ['code' => $row['code']],
                    [
                        'name' => $row['name'],
                        'taxperc' => $row['taxperc'],
                        'formno' => $row['formno'],
                        'prefix' => $row['prefix'],
                        'startno' => $row['startno'],
                        'srprefix' => $row['srprefix'],
                        'srstartno' => $row['srstartno'],
                        'pprefix' => $row['pprefix'],
                        'pstartno' => $row['pstartno'],
                        'prprefix' => $row['prprefix'],
                        'prstartno' => $row['prstartno'],
                    ]
                );

                // Remove stale generali keys when prefix changed
                $old = $oldPrefixes[$row['code']] ?? null;
                if ($old !== null) {
                    $staleKeys = [];
                    if ($old['prefix'] !== $row['prefix'] && $old['prefix'] !== '')
                        $staleKeys[] = 'SALES' . $old['prefix'];
                    if ($old['srprefix'] !== $row['srprefix'] && $old['srprefix'] !== '')
                        $staleKeys[] = 'SRET' . $old['srprefix'];
                    if ($old['pprefix'] !== $row['pprefix'] && $old['pprefix'] !== '')
                        $staleKeys[] = 'PURCH' . $old['pprefix'];
                    if ($old['prprefix'] !== $row['prprefix'] && $old['prprefix'] !== '')
                        $staleKeys[] = 'PRET' . $old['prprefix'];
                    if ($staleKeys !== []) {
                        DB::table('generali')->whereIn('code', $staleKeys)->delete();
                    }
                }

                $this->upsertGenerali('SALES' . $row['prefix'], $row['startno']);
                $this->upsertGenerali('SRET' . $row['srprefix'], $row['srstartno']);
                $this->upsertGenerali('PURCH' . $row['pprefix'], $row['pstartno']);
                $this->upsertGenerali('PRET' . $row['prprefix'], $row['prstartno']);
            }

            $incomingCodes = array_keys($normalized);
            $existingCodes = DB::table('salestype')->pluck('code')->map(fn ($x) => strtoupper(trim((string) $x)))->all();
            foreach ($existingCodes as $code) {
                if (in_array($code, $incomingCodes, true)) {
                    continue;
                }
                // Do not delete types already used in salesm.
                if ($this->hasTable('salesm')) {
                    $salesTypeCol = $this->resolveSalesTypeColumn();
                    if ($salesTypeCol === null) {
                        $skippedDeletes++;
                        continue;
                    }
                    $used = (int) DB::table('salesm')
                        ->whereRaw('UPPER(TRIM(' . $salesTypeCol . ')) = ?', [$code])
                        ->count('slno');
                    if ($used > 0) {
                        $skippedDeletes++;
                        continue;
                    }
                }
                DB::table('salestype')->where('code', $code)->delete();
            }
        });

        $msg = 'Saved successfully';
        if ($skippedDeletes > 0) {
            $msg .= ' (' . $skippedDeletes . ' in-use type(s) not deleted)';
        }
        $this->logDelpart($request, 'Bill Prefix Settings Saved', ['utype' => 'E', 'ttype' => 'R']);
        return response()->json(['success' => true, 'message' => $msg]);
    }

    public function checkDelete(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (!$this->hasTable('salesm')) {
            return response()->json([
                'success' => true,
                'in_use' => false,
                'count' => 0,
            ]);
        }

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json([
                'success' => true,
                'in_use' => false,
                'count' => 0,
            ]);
        }

        $salesTypeCol = $this->resolveSalesTypeColumn();
        if ($salesTypeCol === null) {
            return response()->json([
                'success' => true,
                'in_use' => false,
                'count' => 0,
            ]);
        }

        $count = (int) DB::table('salesm')
            ->whereRaw('UPPER(TRIM(' . $salesTypeCol . ')) = ?', [$code])
            ->count('slno');

        return response()->json([
            'success' => true,
            'in_use' => $count > 0,
            'count' => $count,
        ]);
    }

    private function getGeneraliValue(string $code, int $fallback): int
    {
        if (!$this->hasTable('generali')) {
            return $fallback;
        }

        $value = DB::table('generali')->where('code', trim($code))->value('cvalue');
        if ($value === null) {
            return $fallback;
        }

        return (int) $value;
    }

    /**
     * Return the effective counter for a bill prefix — the maximum of:
     *  1. The stored generali counter value.
     *  2. The highest numeric suffix found in the given legacy table (e.g. salesm.billno).
     * Also writes the corrected value back to generali so future reads are fast.
     */
    private function resolveEffectiveCounter(
        string $genCode,
        string $prefix,
        string $table,
        string $column,
        int $fallback
    ): int {
        $generaliVal = $this->getGeneraliValue($genCode, $fallback);

        if ($prefix === '' || !$this->hasTable($table) || !Schema::hasColumn($table, $column)) {
            return $generaliVal;
        }

        // Find the max numeric suffix among rows whose bill number starts with the prefix.
        $maxBillNo = DB::table($table)
            ->where($column, 'like', $prefix . '%')
            ->orderByRaw('LENGTH(' . $column . ') DESC, ' . $column . ' DESC')
            ->value($column);

        $maxFromTable = 0;
        if ($maxBillNo !== null) {
            $suffix = substr((string) $maxBillNo, strlen($prefix));
            $maxFromTable = (int) filter_var($suffix, FILTER_SANITIZE_NUMBER_INT);
        }

        $effective = max($generaliVal, $maxFromTable);

        // Write corrected value back so the next call is instant.
        if ($effective > $generaliVal && $this->hasTable('generali') && trim($genCode) !== '') {
            $this->upsertGenerali($genCode, $effective);
        }

        return $effective;
    }

    private function upsertGenerali(string $code, int $value): void
    {
        $code = trim($code);
        if ($code === '') {
            return;
        }

        DB::table('generali')->upsert(
            [[
                'code' => $code,
                'cvalue' => $value,
            ]],
            ['code'],
            ['cvalue']
        );
    }

    private function resolveSalesTypeColumn(): ?string
    {
        if (!$this->hasTable('salesm')) {
            return null;
        }
        $cols = array_map('strtolower', $this->columnList('salesm'));
        foreach (['saletype', 'salestype', 'billtype'] as $candidate) {
            if (in_array($candidate, $cols, true)) {
                return $candidate;
            }
        }
        return null;
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

    private function fitText(string $value, int $limit): string
    {
        return mb_substr(trim($value), 0, $limit);
    }
}
