<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class KuriCollectionController extends Controller
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

    private function getColumns(string $table): array
    {
        if (!$this->hasTable($table)) {
            return [];
        }

        return array_map('strtolower', $this->columnList($table));
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

    private function genInt(string $code): int
    {
        if (!$this->hasTable('generali')) {
            return 0;
        }
        return (int) (DB::table('generali')->where('code', $code)->value('cvalue') ?? 0);
    }

    private function ensureGenInt(string $code, int $default = 0): void
    {
        if (!$this->hasTable('generali')) {
            return;
        }
        if (!DB::table('generali')->where('code', $code)->exists()) {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => $default]);
        }
    }

    private function incrementGenInt(string $code): int
    {
        if (!$this->hasTable('generali')) {
            return 1;
        }

        $this->ensureGenInt($code, 0);
        if ($code === 'SERIALNO') {
            $current = (int) (DB::table('generali')->where('code', $code)->value('cvalue') ?? 0);
            $maxUsed = 0;
            foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
                if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                    $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
                }
            }
            $next = max($current, $maxUsed) + 1;
            DB::table('generali')->updateOrInsert(['code' => $code], ['cvalue' => $next]);
            return $next;
        }

        DB::table('generali')->where('code', $code)->increment('cvalue');
        return (int) (DB::table('generali')->where('code', $code)->value('cvalue') ?? 1);
    }

    private function generalProfile(string $code, string $default = ''): string
    {
        if (!$this->hasTable('generals')) {
            return $default;
        }
        $value = trim((string) (DB::table('generals')->where('code', $code)->value('cvalue') ?? ''));
        return $value === '' ? $default : $value;
    }

    private function control(Request $request): int
    {
        $control = (int) ($request->session()->get('gilevel', 1));
        return $control > 0 ? $control : 1;
    }

    private function modeInfo(string $mode): array
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['scheme', 'kuri'], true)) {
            $mode = 'scheme';
        }

        return [
            'mode' => $mode,
            'title' => $mode === 'scheme' ? 'Scheme Collection' : 'Kuri Collection',
            'group' => $mode === 'scheme' ? 'SCHM' : 'KC',
            'cash_key' => $mode === 'scheme' ? 'SchemeCash' : 'KuriCash',
            'label' => $mode === 'scheme' ? 'Scheme' : 'Kuri',
        ];
    }

    private function getCashBankAccounts(): array
    {
        if (!$this->hasTable('accountm')) {
            return [];
        }

        return DB::table('accountm')
            ->selectRaw('TRIM(accode) AS code, TRIM(name) AS name, TRIM(COALESCE(actype2, "")) AS type')
            ->whereRaw("TRIM(COALESCE(actype2, '')) IN ('H','B')")
            ->when($this->hasCol('accountm', 'removed'), function ($q) {
                $q->whereRaw('(removed <> 1 OR removed IS NULL)');
            })
            ->orderBy('accode')
            ->get()
            ->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'type' => $r->type])
            ->all();
    }

    private function getSalesmen(): array
    {
        if (!$this->hasTable('sman')) {
            return [];
        }

        return DB::table('sman')
            ->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => ['code' => $r->code, 'name' => $r->name])
            ->all();
    }

    private function latestGoldRate(?string $date): float
    {
        if (!$this->hasTable('ratehistory')) {
            return 0.0;
        }

        $query = DB::table('ratehistory')->select('grate');
        if ($date) {
            $query->where('tdate', '<=', $date);
        }
        $value = $query->orderByDesc('tdate')->value('grate');
        return round((float) ($value ?? 0), 2);
    }

    private function rowPartyFilter($query, string $mode): void
    {
        if (!$this->hasTable('clients')) {
            return;
        }

        $info = $this->modeInfo($mode);
        if ($this->hasCol('clients', 'grp')) {
            $query->leftJoin('clients as c', 'c.code', '=', 'kuricolln.code');
            $query->whereRaw('TRIM(COALESCE(c.grp, "")) = ?', [$info['group']]);
        }
    }

    private function getOpeningAmount(string $code, int $control, ?string $date = null, ?int $excludeSlno = null): float
    {
        if ($code === '') {
            return 0.0;
        }

        $opening = 0.0;
        if ($this->hasTable('clients')) {
            $client = DB::table('clients')->whereRaw('TRIM(code)=?', [$code])->first();
            if ($client) {
                $opening = (float) ($control === 1 ? ($client->opbalance ?? 0) : ($client->opbalanceb ?? 0));
            }
        }

        if (!$this->hasTable('daybook')) {
            return round($opening, 2);
        }

        $query = DB::table('daybook')
            ->whereRaw('TRIM(accode)=?', [$code])
            ->where('control', $control);

        if ($date) {
            $query->where('tdate', '<=', $date);
        }
        if ($excludeSlno) {
            $query->where('slno', '<>', $excludeSlno);
        }

        return round($opening + (float) $query->sum('amount'), 2);
    }

    private function getOpeningWeight(string $code, int $control, ?string $date = null, ?int $excludeSlno = null): float
    {
        if ($code === '' || !$this->hasTable('clients_kuridet')) {
            return 0.0;
        }

        $det = DB::table('clients_kuridet')->whereRaw('TRIM(code)=?', [$code])->first();
        if (!$det) {
            return 0.0;
        }

        $show = strtoupper(trim((string) ($det->showwgtdet ?? 'N')));
        if ($show !== 'Y') {
            return 0.0;
        }

        $opening = (float) ($control === 1 ? ($det->opwgt ?? 0) : ($det->opwgtb ?? 0));
        if (!$this->hasTable('kuricolln')) {
            return round($opening, 3);
        }

        $query = DB::table('kuricolln')->whereRaw('TRIM(code)=?', [$code]);
        if ($date) {
            $query->where('tdate', '<=', $date);
        }
        if ($excludeSlno) {
            $query->where('slno', '<>', $excludeSlno);
        }

        return round($opening + (float) $query->sum('wgt'), 3);
    }

    private function getMonthlyCollection(string $code, ?string $date, ?int $excludeSlno = null): float
    {
        if ($code === '' || !$date || !$this->hasTable('kuricolln')) {
            return 0.0;
        }

        $from = date('Y-m-01', strtotime($date));
        $query = DB::table('kuricolln')
            ->whereRaw('TRIM(code)=?', [$code])
            ->whereBetween('tdate', [$from, $date]);

        if ($excludeSlno) {
            $query->where('slno', '<>', $excludeSlno);
        }

        $amt = (float) $query->sum('amount');
        if ($this->hasTable('clients_kuridet')) {
            $amt += (float) (DB::table('clients_kuridet')->whereRaw('TRIM(code)=?', [$code])->value('collnopbal') ?? 0);
        }

        return round($amt, 2);
    }

    private function nextVoucherNo(int $control, float $amount): string
    {
        $isReceipt = $amount >= 0;
        $code = $isReceipt
            ? ($control === 1 ? 'VCHNORB' : 'VCHNORE')
            : ($control === 1 ? 'VCHNOPB' : 'VCHNOPE');
        $prefix = $isReceipt
            ? ($control === 1 ? 'VRB/' : 'VRE/')
            : ($control === 1 ? 'VPB/' : 'VPE/');

        return $prefix . str_pad((string) $this->incrementGenInt($code), 5, '0', STR_PAD_LEFT);
    }

    private function canEdit(string $userCode): bool
    {
        if (!$this->hasTable('userd') || trim($userCode) === '') {
            return false;
        }

        return DB::table('userd')
            ->whereRaw('TRIM(code)=?', [trim($userCode)])
            ->whereRaw('TRIM(menuitem)=?', ['KURICOLLNEDIT'])
            ->exists();
    }

    public function index(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        $info = $this->modeInfo((string) $request->query('mode', 'scheme'));

        return view('kuri-collection.index', [
            'title' => $info['title'],
            'mode' => $info['mode'],
            'modeLabel' => $info['label'],
        ]);
    }

    public function init(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $info = $this->modeInfo((string) $request->input('mode', 'scheme'));
        $cashAccounts = $this->getCashBankAccounts();
        $defaultCash = $this->generalProfile($info['cash_key'], 'CASH');
        if ($defaultCash === '' && !empty($cashAccounts)) {
            $defaultCash = $cashAccounts[0]['code'];
        }

        return $this->json([
            'ok' => true,
            'mode' => $info['mode'],
            'title' => $info['title'],
            'today' => date('d/m/Y'),
            'goldRate' => $this->latestGoldRate(date('Y-m-d')),
            'cashAccounts' => $cashAccounts,
            'defaultCash' => $defaultCash,
            'salesmen' => $this->getSalesmen(),
            'canEdit' => $this->canEdit((string) $request->session()->get('user_code', '')),
            'nextReceiptNo' => $this->genInt('KRCPTNO') + 1,
        ]);
    }

    public function partySearch(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $info = $this->modeInfo((string) $request->input('mode', 'scheme'));
        $q = trim((string) $request->input('q', ''));

        $query = DB::table('clients as c')
            ->join('clients_kuridet as k', 'k.code', '=', 'c.code')
            ->selectRaw('TRIM(c.code) as code, TRIM(c.name) as name, TRIM(COALESCE(c.mobile, "")) as mobile, TRIM(COALESCE(c.city, "")) as city, TRIM(COALESCE(c.grp, "")) as grp, TRIM(COALESCE(k.colnagent, "")) as agent, TRIM(COALESCE(k.finished, "")) as finished')
            ->whereRaw('TRIM(COALESCE(c.grp, "")) = ?', [$info['group']]);

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($inner) use ($like) {
                $inner->whereRaw('TRIM(c.code) LIKE ?', [$like])
                    ->orWhereRaw('TRIM(c.name) LIKE ?', [$like])
                    ->orWhereRaw('TRIM(COALESCE(c.mobile, "")) LIKE ?', [$like]);
            });
        }

        $rows = $query->orderBy('c.name')->limit(200)->get()->map(function ($r) {
            return [
                'code' => $r->code,
                'name' => $r->name,
                'mobile' => $r->mobile,
                'city' => $r->city,
                'grp' => $r->grp,
                'agent' => $r->agent,
                'finished' => $r->finished,
            ];
        })->all();

        return $this->json(['ok' => true, 'rows' => $rows]);
    }

    public function partyLookup(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $control = $this->control($request);
        $code = $this->trimUpper($request->input('code', ''));
        $date = $this->parseDate((string) $request->input('date', ''));
        $excludeSlno = (int) $request->input('exclude_slno', 0);

        if ($code === '') {
            return $this->json(['ok' => false, 'message' => 'Party code required'], 422);
        }

        $client = $this->hasTable('clients')
            ? DB::table('clients')->whereRaw('TRIM(code)=?', [$code])->first()
            : null;
        if (!$client) {
            return $this->json(['ok' => false, 'message' => 'Party not found'], 404);
        }

        $det = $this->hasTable('clients_kuridet')
            ? DB::table('clients_kuridet')->whereRaw('TRIM(code)=?', [$code])->first()
            : null;
        if (!$det) {
            return $this->json(['ok' => false, 'message' => 'Scheme/Kuri details not found'], 404);
        }

        $maturity = trim((string) ($det->matdate ?? ''));
        if ($maturity !== '' && strtotime($maturity) !== false && strtotime($maturity) < strtotime(date('Y-m-d'))) {
            if (strtoupper($this->generalProfile('ChkMaturityDate', 'Y')) === 'Y') {
                return $this->json(['ok' => false, 'message' => 'Maturity validation failed for this party'], 422);
            }
        }

        return $this->json([
            'ok' => true,
            'party' => [
                'code' => $code,
                'name' => trim((string) ($client->name ?? '')),
                'agent' => trim((string) ($det->colnagent ?? '')),
                'showwgt' => strtoupper(trim((string) ($det->showwgtdet ?? 'N'))),
                'finished' => strtoupper(trim((string) ($det->finished ?? 'N'))),
                'instamt' => round((float) ($det->instamt ?? 0), 2),
                'minlimit' => round((float) ($det->collnminamt ?? 0), 2),
                'maxlimit' => round((float) ($det->collnmaxamt ?? 0), 2),
                'totcolln' => $this->getMonthlyCollection($code, $date, $excludeSlno > 0 ? $excludeSlno : null),
                'ob' => $this->getOpeningAmount($code, $control, $date, $excludeSlno > 0 ? $excludeSlno : null),
                'obwgt' => $this->getOpeningWeight($code, $control, $date, $excludeSlno > 0 ? $excludeSlno : null),
            ],
        ]);
    }

    public function load(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $control = $this->control($request);
        $date = $this->parseDate((string) $request->input('date', ''));
        $mode = (string) $request->input('mode', 'scheme');
        $sortOnReceipt = filter_var($request->input('sort_on_receipt', false), FILTER_VALIDATE_BOOL);

        if (!$date) {
            return $this->json(['ok' => false, 'message' => 'Valid date required'], 422);
        }

        $query = DB::table('kuricolln')
            ->leftJoin('daybookpart as dp', 'dp.slno', '=', 'kuricolln.slno')
            ->leftJoin('clients as c', 'c.code', '=', 'kuricolln.code')
            ->selectRaw('kuricolln.slno, kuricolln.tdate, TRIM(kuricolln.code) as code, TRIM(COALESCE(c.name, "")) as name, TRIM(COALESCE(kuricolln.rcptno, "")) as rcptno, COALESCE(kuricolln.amount,0) as amount, TRIM(COALESCE(kuricolln.agent, "")) as agent, COALESCE(kuricolln.wgt,0) as wgt, TRIM(COALESCE(dp.vchno, kuricolln.docno, "")) as vchno')
            ->where('kuricolln.tdate', $date)
            ->where('kuricolln.control', $control);

        $this->rowPartyFilter($query, $mode);

        if ($sortOnReceipt) {
            $query->orderByRaw('CAST(NULLIF(TRIM(COALESCE(kuricolln.rcptno, "")), "") AS UNSIGNED), kuricolln.sno, kuricolln.slno');
        } else {
            $query->orderBy('kuricolln.sno')->orderBy('kuricolln.slno');
        }

        $rows = $query->get()->map(function ($row) use ($control, $date) {
            $slno = (int) ($row->slno ?? 0);
            $ob = $this->getOpeningAmount($row->code, $control, $date, $slno);
            $obwgt = $this->getOpeningWeight($row->code, $control, $date, $slno);
            $show = $this->hasTable('clients_kuridet')
                ? strtoupper(trim((string) (DB::table('clients_kuridet')->whereRaw('TRIM(code)=?', [$row->code])->value('showwgtdet') ?? 'N')))
                : 'N';

            return [
                'slno' => $slno,
                'tdate' => $this->formatDate($row->tdate),
                'code' => $row->code,
                'name' => $row->name,
                'rcptno' => $row->rcptno,
                'amount' => round((float) $row->amount, 2),
                'agent' => $row->agent,
                'wgt' => round((float) $row->wgt, 3),
                'vchno' => $row->vchno,
                'prnt' => 1,
                'showwgt' => $show,
                'ob' => $ob,
                'obwgt' => $obwgt,
                'cb' => round($ob + (float) $row->amount, 2),
                'cbwgt' => round($obwgt + (float) $row->wgt, 3),
            ];
        })->all();

        return $this->json([
            'ok' => true,
            'rows' => $rows,
            'goldRate' => $this->latestGoldRate($date),
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $control = $this->control($request);
        $modeInfo = $this->modeInfo((string) $request->input('mode', 'scheme'));
        $date = $this->parseDate((string) $request->input('date', ''));
        $goldRate = (float) $request->input('gold_rate', 0);
        $cashAc = $this->trimUpper($request->input('cash_account', 'CASH'));
        $note = trim((string) $request->input('note', ''));
        $autoRcpt = filter_var($request->input('auto_receipt', false), FILTER_VALIDATE_BOOL);
        $rows = $request->input('rows', []);

        if (!$date) {
            return $this->json(['ok' => false, 'message' => 'Valid date required'], 422);
        }
        if (!is_array($rows) || count($rows) === 0) {
            return $this->json(['ok' => false, 'message' => 'No rows to save'], 422);
        }

        $saved = 0;
        $nextRcptNo = $this->genInt('KRCPTNO');
        $userCode = trim((string) $request->session()->get('user_code', ''));
        $incharge = trim((string) $request->session()->get('gsincharge', ''));

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $code = $this->trimUpper($row['code'] ?? '');
                $amount = (float) ($row['amount'] ?? 0);
                $slno = (int) ($row['slno'] ?? 0);
                $agent = $this->trimUpper($row['agent'] ?? '');
                $rcptNo = trim((string) ($row['rcptno'] ?? ''));
                $existingVchNo = trim((string) ($row['vchno'] ?? ''));

                if ($code === '' || abs($amount) < 0.0001) {
                    continue;
                }

                if ($slno > 0) {
                    DB::table('kuricolln')->where('slno', $slno)->delete();
                    DB::table('daybook')->where('slno', $slno)->delete();
                    if ($this->hasTable('daybookpart')) {
                        DB::table('daybookpart')->where('slno', $slno)->delete();
                    }
                } else {
                    $slno = $this->incrementGenInt('SERIALNO');
                }

                if ($autoRcpt) {
                    $num = (int) preg_replace('/\D+/', '', $rcptNo);
                    if ($num <= 0) {
                        $nextRcptNo += 1;
                        $rcptNo = (string) $nextRcptNo;
                    } else {
                        $nextRcptNo = max($nextRcptNo, $num);
                    }
                }

                $showwgt = $this->hasTable('clients_kuridet')
                    ? strtoupper(trim((string) (DB::table('clients_kuridet')->whereRaw('TRIM(code)=?', [$code])->value('showwgtdet') ?? 'N')))
                    : 'N';
                $wgt = ($showwgt === 'Y' && $goldRate > 0) ? round($amount / $goldRate, 3) : 0.0;
                $vchNo = $existingVchNo !== '' ? $existingVchNo : $this->nextVoucherNo($control, $amount);

                $kuricollnData = [
                    'slno' => $slno,
                    'tdate' => $date,
                    'code' => $code,
                    'amount' => $amount,
                    'control' => $control,
                    'sno' => $index + 1,
                    'grate' => $goldRate,
                    'agent' => $agent,
                    'rcptno' => $rcptNo,
                    'closed' => 'N',
                    'wgt' => $wgt,
                    'docno' => $vchNo,
                    'note' => $note,
                ];
                DB::table('kuricolln')->insert(array_intersect_key($kuricollnData, array_flip($this->getColumns('kuricolln'))));

                if ($this->hasTable('daybook')) {
                    $row1 = [
                        'slno' => $slno,
                        'tdate' => $date,
                        'accode' => $code,
                        'amount' => $amount,
                        'control' => $control,
                        'opaccode' => $cashAc,
                        'sno' => $index + 1,
                    ];
                    $row2 = [
                        'slno' => $slno,
                        'tdate' => $date,
                        'accode' => $cashAc,
                        'amount' => -$amount,
                        'control' => $control,
                        'opaccode' => $code,
                        'sno' => $index + 1,
                    ];
                    $cols = array_flip($this->getColumns('daybook'));
                    DB::table('daybook')->insert(array_intersect_key($row1, $cols));
                    DB::table('daybook')->insert(array_intersect_key($row2, $cols));
                }

                if ($this->hasTable('daybookpart')) {
                    $label = $modeInfo['label'];
                    $particular = ($amount < 0 ? "By {$label} Refund - " : "By {$label} Colln - ") . $vchNo;
                    if ($rcptNo !== '') {
                        $particular .= ' - RN:' . $rcptNo;
                    }
                    if ($note !== '') {
                        $particular .= ' ' . $note;
                    }

                    $dp = [
                        'slno' => $slno,
                        'vchno' => $vchNo,
                        'particular' => trim($particular),
                        'staff' => $agent,
                        'ic' => $incharge,
                        'uid' => $userCode,
                        'ttime' => date('H:i:s'),
                    ];
                    DB::table('daybookpart')->insert(array_intersect_key($dp, array_flip($this->getColumns('daybookpart'))));
                }

                $saved += 1;
            }

            if ($autoRcpt && $this->hasTable('generali')) {
                $this->ensureGenInt('KRCPTNO', 0);
                DB::table('generali')->where('code', 'KRCPTNO')->update(['cvalue' => $nextRcptNo]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }

        return $this->json([
            'ok' => true,
            'saved' => $saved,
            'nextReceiptNo' => $nextRcptNo + 1,
        ]);
    }

    public function delete(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $slno = (int) $request->input('slno', 0);
        if ($slno <= 0) {
            return $this->json(['ok' => false, 'message' => 'Invalid row selected'], 422);
        }

        DB::beginTransaction();
        try {
            DB::table('kuricolln')->where('slno', $slno)->delete();
            if ($this->hasTable('daybook')) {
                DB::table('daybook')->where('slno', $slno)->delete();
            }
            if ($this->hasTable('daybookpart')) {
                DB::table('daybookpart')->where('slno', $slno)->delete();
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }

        return $this->json(['ok' => true]);
    }
}
