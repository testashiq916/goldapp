<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PassbookPrintController extends Controller
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

    private function trimUpper($value): string
    {
        return strtoupper(trim((string) $value));
    }

    private function parseDate(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '00/00/0000') {
            return null;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw)) {
            return $raw;
        }
        return null;
    }

    private function formatDate(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts === false ? $value : date('d/m/Y', $ts);
    }

    private function hasCol(string $table, string $col): bool
    {
        static $cache = [];
        $key = $table . ':' . strtolower($col);
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = $this->hasTable($table) && Schema::hasColumn($table, $col);
        }
        return $cache[$key];
    }

    private function modeInfo(string $mode): array
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['scheme', 'kuri'], true)) {
            $mode = 'scheme';
        }

        return [
            'mode' => $mode,
            'title' => $mode === 'scheme' ? 'Passbook Print' : 'Kuri Passbook Print',
            'group' => $mode === 'scheme' ? 'SCHM' : 'KC',
            'help' => $mode === 'scheme' ? 'SCHEME' : 'KURICUST',
        ];
    }

    public function index(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        $info = $this->modeInfo((string) $request->query('mode', 'scheme'));

        return view('passbook-print.index', [
            'title' => $info['title'],
            'mode' => $info['mode'],
        ]);
    }

    public function init(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        return $this->json([
            'ok' => true,
            'today' => date('d/m/Y'),
            'defaults' => [
                'lines_per_page' => 0,
                'lines_half_page' => 0,
                'skip_middle' => 0,
                'top_margin' => 0,
                'left_margin' => 0,
                'footer_margin' => 0,
            ],
        ]);
    }

    public function partySearch(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $info = $this->modeInfo((string) $request->input('mode', 'scheme'));
        $q = trim((string) $request->input('q', ''));

        if (!$this->hasTable('clients')) {
            return $this->json(['ok' => true, 'rows' => []]);
        }

        $query = DB::table('clients')
            ->selectRaw('TRIM(code) as code, TRIM(COALESCE(name, "")) as name, TRIM(COALESCE(addr1, "")) as addr1, TRIM(COALESCE(addr2, "")) as addr2, TRIM(COALESCE(addr3, "")) as addr3')
            ->whereRaw('TRIM(COALESCE(grp, "")) = ?', [$info['group']]);

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($inner) use ($like) {
                $inner->whereRaw('TRIM(code) LIKE ?', [$like])
                    ->orWhereRaw('TRIM(COALESCE(name, "")) LIKE ?', [$like]);
            });
        }

        $rows = $query->orderBy('name')->limit(200)->get()->map(function ($row) {
            return [
                'code' => $row->code,
                'name' => $row->name,
                'address' => trim(implode(' ', array_filter([$row->addr1, $row->addr2, $row->addr3]))),
            ];
        })->all();

        return $this->json(['ok' => true, 'rows' => $rows]);
    }

    public function partyLookup(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $code = $this->trimUpper($request->input('code', ''));
        if ($code === '') {
            return $this->json(['ok' => false, 'message' => 'Party code required'], 422);
        }

        $client = $this->hasTable('clients')
            ? DB::table('clients')->whereRaw('TRIM(code)=?', [$code])->first()
            : null;

        if (!$client) {
            return $this->json(['ok' => false, 'message' => 'Party not found'], 404);
        }

        $lastSlno = (int) ($client->lpslno ?? 0);
        $lastLine = (int) ($client->lpline ?? 0);
        $lastNo = (int) ($client->lpsno ?? 0);

        $nextDocNo = '';
        if ($this->hasTable('kuricolln')) {
            $nextDocNo = (string) (DB::table('kuricolln')
                ->whereRaw('TRIM(code)=?', [$code])
                ->where('slno', '>', $lastSlno)
                ->orderBy('slno')
                ->value('docno') ?? '');
        }

        return $this->json([
            'ok' => true,
            'party' => [
                'code' => $code,
                'address' => trim(implode(' ', array_filter([
                    trim((string) ($client->addr1 ?? '')),
                    trim((string) ($client->addr2 ?? '')),
                    trim((string) ($client->addr3 ?? '')),
                ]))),
                'start_line' => $lastLine + 1,
                'start_docno' => $nextDocNo,
                'start_slno' => $lastNo + 1,
                'last_print_slno' => $lastSlno,
            ],
        ]);
    }

    public function build(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $code = $this->trimUpper($request->input('code', ''));
        $fromDate = $this->parseDate((string) $request->input('date1', ''));
        $toDate = $this->parseDate((string) $request->input('date2', ''));
        $startLine = max(1, (int) $request->input('start_line', 1));
        $startDocNo = trim((string) $request->input('start_docno', ''));
        $startSlnoNo = max(1, (int) $request->input('start_slno', 1));
        $reset = filter_var($request->input('reset', false), FILTER_VALIDATE_BOOL);
        $linesPerPage = max(0, (int) $request->input('lines_per_page', 0));
        $linesHalfPage = max(0, (int) $request->input('lines_half_page', 0));
        $skipMiddle = max(0, (int) $request->input('skip_middle', 0));

        if ($code === '') {
            return $this->json(['ok' => false, 'message' => 'Party code required'], 422);
        }

        $client = $this->hasTable('clients')
            ? DB::table('clients')->whereRaw('TRIM(code)=?', [$code])->first()
            : null;
        if (!$client) {
            return $this->json(['ok' => false, 'message' => 'Party not found'], 404);
        }

        $startSlno = $reset ? 0 : (int) ($client->lpslno ?? 0) + 1;
        if ($startDocNo !== '' && $this->hasTable('kuricolln')) {
            $docSlno = (int) (DB::table('kuricolln')
                ->whereRaw('TRIM(code)=?', [$code])
                ->whereRaw('TRIM(COALESCE(docno, "")) = ?', [$startDocNo])
                ->value('slno') ?? 0);
            if ($docSlno > 0) {
                $startSlno = $docSlno;
            }
        }

        if (!$reset && $startSlno <= 0) {
            $startSlno = max(1, (int) ($client->lpslno ?? 0) + 1);
        }

        if (!$this->hasTable('kuricolln')) {
            return $this->json(['ok' => false, 'message' => 'Collection table not found'], 500);
        }

        $query = DB::table('kuricolln')
            ->selectRaw('slno, tdate, TRIM(COALESCE(docno, "")) as docno, TRIM(COALESCE(rcptno, "")) as rcptno, COALESCE(amount,0) as amount, COALESCE(grate,0) as grate, COALESCE(wgt,0) as wgt, TRIM(COALESCE(agent, "")) as agent')
            ->whereRaw('TRIM(code)=?', [$code]);

        if ($startSlno > 0) {
            $query->where('slno', '>=', $startSlno);
        }
        if ($fromDate) {
            $query->where('tdate', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('tdate', '<=', $toDate);
        }

        $entries = $query->orderBy('tdate')->orderBy('slno')->get();
        if ($entries->isEmpty()) {
            return $this->json(['ok' => false, 'message' => 'Nothing to print more'], 422);
        }

        $rows = [];
        for ($i = 1; $i < $startLine; $i++) {
            $rows[] = ['blank' => true];
        }

        $tno = $startSlnoNo;
        $lastSlno = 0;
        foreach ($entries as $entry) {
            $rows[] = [
                'blank' => false,
                'tdate' => $this->formatDate($entry->tdate),
                'docno' => $entry->docno,
                'rcptno' => $entry->rcptno,
                'rate' => round((float) $entry->grate, 2),
                'amount' => round((float) $entry->amount, 2),
                'wgt' => round((float) $entry->wgt, 3),
                'slno' => (int) $entry->slno,
                'tno' => $tno,
                'agent' => $entry->agent,
            ];
            $lastSlno = (int) $entry->slno;
            $tno++;

            $lineCount = count($rows);
            if ($linesHalfPage > 0 && $linesPerPage > 0 && $skipMiddle > 0) {
                if (($lineCount % $linesHalfPage) === 0 && ($lineCount % $linesPerPage) <= $linesHalfPage) {
                    for ($j = 0; $j < $skipMiddle; $j++) {
                        $rows[] = ['blank' => true, 'slno' => $lastSlno];
                    }
                }
            }
        }

        $lastLineNo = count($rows) > 0 && $linesPerPage > 0 ? (count($rows) % $linesPerPage) : count($rows);
        $lastPassbookNo = $tno - 1;

        DB::table('clients')->whereRaw('TRIM(code)=?', [$code])->update([
            'lpline' => $lastLineNo,
            'lpslno' => $lastSlno,
            'lpsno' => $lastPassbookNo,
        ]);

        return $this->json([
            'ok' => true,
            'party' => [
                'code' => $code,
                'address' => trim(implode(' ', array_filter([
                    trim((string) ($client->addr1 ?? '')),
                    trim((string) ($client->addr2 ?? '')),
                    trim((string) ($client->addr3 ?? '')),
                ]))),
            ],
            'rows' => $rows,
            'summary' => [
                'last_line' => $lastLineNo,
                'last_slno' => $lastSlno,
                'last_passbook_no' => $lastPassbookNo,
            ],
        ]);
    }

    public function address(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $code = $this->trimUpper($request->input('code', ''));
        if ($code === '') {
            return $this->json(['ok' => false, 'message' => 'Party code required'], 422);
        }

        $client = $this->hasTable('clients')
            ? DB::table('clients')->whereRaw('TRIM(code)=?', [$code])->first()
            : null;
        if (!$client) {
            return $this->json(['ok' => false, 'message' => 'Party not found'], 404);
        }

        $kuriTypeCode = $this->hasTable('clients_kuridet')
            ? trim((string) (DB::table('clients_kuridet')->whereRaw('TRIM(code)=?', [$code])->value('kuritype') ?? ''))
            : '';
        $kuriTypeName = $kuriTypeCode !== '' && $this->hasTable('kuritype')
            ? trim((string) (DB::table('kuritype')->whereRaw('TRIM(code)=?', [$kuriTypeCode])->value('name') ?? ''))
            : '';

        return $this->json([
            'ok' => true,
            'rows' => array_values(array_filter([
                ['note' => 'Name', 'part' => trim((string) ($client->name ?? ''))],
                ['note' => 'Address', 'part' => trim((string) ($client->addr1 ?? ''))],
                ['note' => '', 'part' => trim((string) ($client->addr2 ?? ''))],
                ['note' => '', 'part' => trim((string) ($client->addr3 ?? ''))],
                trim((string) ($client->telephone ?? '')) !== '' ? ['note' => 'Phone', 'part' => trim((string) ($client->telephone ?? ''))] : null,
                trim((string) ($client->mobile ?? '')) !== '' ? ['note' => 'Mobile', 'part' => trim((string) ($client->mobile ?? ''))] : null,
                ['note' => 'Scheme Type', 'part' => $kuriTypeName],
                ['note' => 'Code', 'part' => $code],
            ])),
        ]);
    }
}
