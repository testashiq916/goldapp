<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\View\View;

class ReminderController extends Controller
{
    private const TABLE = 'task_reminders';

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->ensureTable();

        return view('reminders.index', [
            'today' => Carbon::today()->toDateString(),
            'csrf' => csrf_token(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $this->ensureTable();

        $status = strtolower(trim((string) $request->query('status', 'pending')));
        $range = strtolower(trim((string) $request->query('range', 'all')));
        $q = trim((string) $request->query('q', ''));

        $rows = collect($this->manualReminders($status, $range, $q))
            ->merge($status === 'done' ? [] : $this->dueDateReminders($range, $q))
            ->sortBy([
                ['is_done', 'asc'],
                ['due_date', 'asc'],
                ['priority_weight', 'desc'],
                ['title', 'asc'],
            ])
            ->values()
            ->map(function (array $row) {
                unset($row['priority_weight']);
                return $row;
            })
            ->all();

        $today = Carbon::today()->toDateString();
        $summary = [
            'total' => count($rows),
            'overdue' => collect($rows)->filter(fn ($r) => !$r['is_done'] && $r['due_date'] < $today)->count(),
            'today' => collect($rows)->filter(fn ($r) => !$r['is_done'] && $r['due_date'] === $today)->count(),
            'upcoming' => collect($rows)->filter(fn ($r) => !$r['is_done'] && $r['due_date'] > $today)->count(),
        ];

        return response()->json(['ok' => true, 'rows' => $rows, 'summary' => $summary]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $this->ensureTable();

        $title = trim((string) $request->input('title', ''));
        $dueDate = $this->normalizeDate((string) $request->input('due_date', ''));
        if ($title === '' || $dueDate === null) {
            return response()->json(['ok' => false, 'message' => 'Enter reminder title and valid due date'], 422);
        }

        DB::table(self::TABLE)->insert([
            'title' => mb_substr($title, 0, 160),
            'category' => mb_substr(trim((string) $request->input('category', 'Other')) ?: 'Other', 0, 40),
            'party_name' => mb_substr(trim((string) $request->input('party_name', '')), 0, 160),
            'reference_no' => mb_substr(trim((string) $request->input('reference_no', '')), 0, 80),
            'amount' => $this->money($request->input('amount')),
            'due_date' => $dueDate,
            'priority' => mb_substr(trim((string) $request->input('priority', 'Normal')) ?: 'Normal', 0, 20),
            'notes' => trim((string) $request->input('notes', '')),
            'created_by' => mb_substr((string) $request->session()->get('user_code', ''), 0, 40),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'message' => 'Reminder created']);
    }

    public function customers(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!$this->hasTable('clients')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $type = strtoupper(trim((string) $request->query('type', '')));
        $cols = $this->columnList('clients');

        $query = DB::table('clients')
            ->select([
                DB::raw($this->selectExpr($cols, 'code', "''") . ' as code'),
                DB::raw($this->selectExpr($cols, 'name', "''") . ' as name'),
                DB::raw($this->selectExpr($cols, 'ctype', "''") . ' as ctype'),
                DB::raw($this->selectExpr($cols, 'mobile', "''") . ' as mobile'),
                DB::raw($this->selectExpr($cols, 'city', "''") . ' as city'),
            ]);

        if (in_array('removed', $cols, true)) {
            $query->where(function ($sub) {
                $sub->whereNull('removed')->orWhere('removed', '<>', 1);
            });
        }

        if ($type !== '' && $type !== 'ALL' && in_array('ctype', $cols, true)) {
            $query->where('ctype', $type);
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($sub) use ($like, $cols) {
                foreach (['code', 'name', 'mobile', 'telephone', 'city'] as $col) {
                    if (in_array($col, $cols, true)) {
                        $sub->orWhere($col, 'like', $like);
                    }
                }
            });
        }

        $rows = $query
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->map(fn ($row) => [
                'code' => trim((string) ($row->code ?? '')),
                'name' => trim((string) ($row->name ?? '')),
                'ctype' => trim((string) ($row->ctype ?? '')),
                'mobile' => trim((string) ($row->mobile ?? '')),
                'city' => trim((string) ($row->city ?? '')),
            ])
            ->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function bills(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $code = trim((string) $request->query('code', ''));
        $q = trim((string) $request->query('q', ''));
        if ($code === '' && $q === '') {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $rows = collect()
            ->merge($this->billOptions('salesm', 'Sales Bill', ['billno', 'docno'], ['custcode', 'code'], ['custname', 'name'], ['netamt', 'billamt', 'amount'], $code, $q))
            ->merge($this->billOptions('orderm', 'Order Due', ['ordno', 'billno'], ['custcode', 'code'], ['custname', 'name'], ['billamt', 'amount', 'netamt'], $code, $q))
            ->merge($this->billOptions('purchasem', 'Purchase Bill', ['billno', 'docno'], ['suppcode', 'code'], ['suppname', 'name'], ['netamt', 'billamt', 'amount'], $code, $q))
            ->sortBy([['due_date', 'asc'], ['reference_no', 'asc']])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $this->ensureTable();

        $done = $request->boolean('done');
        DB::table(self::TABLE)->where('id', $id)->update([
            'is_done' => $done,
            'completed_at' => $done ? now() : null,
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $this->ensureTable();
        DB::table(self::TABLE)->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    private function manualReminders(string $status, string $range, string $q): array
    {
        $query = DB::table(self::TABLE);

        if ($status === 'done') {
            $query->where('is_done', true);
        } elseif ($status === 'pending') {
            $query->where('is_done', false);
        }

        $this->applyRange($query, 'due_date', $range);
        $this->applySearch($query, $q, ['title', 'category', 'party_name', 'reference_no', 'notes']);

        return $query->limit(500)->get()->map(function ($row) {
            return [
                'id' => 'manual-' . $row->id,
                'manual_id' => (int) $row->id,
                'source' => 'manual',
                'category' => (string) $row->category,
                'title' => (string) $row->title,
                'party_name' => (string) $row->party_name,
                'reference_no' => (string) $row->reference_no,
                'amount' => (float) $row->amount,
                'due_date' => (string) $row->due_date,
                'priority' => (string) $row->priority,
                'notes' => (string) $row->notes,
                'is_done' => (bool) $row->is_done,
                'editable' => true,
                'priority_weight' => $this->priorityWeight((string) $row->priority),
            ];
        })->all();
    }

    private function dueDateReminders(string $range, string $q): array
    {
        return collect()
            ->merge($this->sourceRows('salesm', 'Sales Bill', ['billno', 'docno'], ['custname', 'cname', 'name'], ['custcode', 'code'], ['netamt', 'billamt', 'amount']))
            ->merge($this->sourceRows('purchasem', 'Purchase Bill', ['billno', 'docno'], ['suppname', 'sname', 'name'], ['suppcode', 'code'], ['netamt', 'billamt', 'amount']))
            ->merge($this->sourceRows('orderm', 'Order Due', ['ordno', 'billno'], ['custname', 'cname', 'name'], ['custcode', 'code'], ['billamt', 'amount', 'netamt']))
            ->filter(function ($row) use ($range, $q) {
                return $this->rowInRange($row['due_date'], $range) && $this->rowMatches($row, $q);
            })
            ->values()
            ->all();
    }

    private function sourceRows(string $table, string $category, array $refCols, array $partyCols, array $codeCols, array $amountCols): array
    {
        if (!$this->hasTable($table) || !in_array('duedate', $this->columnList($table), true)) {
            return [];
        }

        $cols = $this->columnList($table);
        $slno = in_array('slno', $cols, true) ? 'slno' : null;
        $tdate = in_array('tdate', $cols, true) ? 'tdate' : null;
        $ref = $this->firstCol($cols, $refCols);
        $party = $this->firstCol($cols, $partyCols);
        $code = $this->firstCol($cols, $codeCols);
        $amount = $this->firstCol($cols, $amountCols);

        $select = [
            DB::raw($slno ? "{$slno} as source_id" : "'' as source_id"),
            DB::raw($ref ? "{$ref} as reference_no" : "'' as reference_no"),
            DB::raw($party ? "{$party} as party_name" : "'' as party_name"),
            DB::raw($code ? "{$code} as party_code" : "'' as party_code"),
            DB::raw($amount ? "{$amount} as amount" : "0 as amount"),
            DB::raw($tdate ? "{$tdate} as txn_date" : "NULL as txn_date"),
            'duedate',
        ];

        return DB::table($table)
            ->select($select)
            ->whereNotNull('duedate')
            ->where('duedate', '<>', '0000-00-00')
            ->orderBy('duedate')
            ->limit(300)
            ->get()
            ->map(function ($row) use ($table, $category) {
                $ref = trim((string) ($row->reference_no ?? ''));
                $party = trim((string) ($row->party_name ?? ''));
                $code = trim((string) ($row->party_code ?? ''));
                return [
                    'id' => $table . '-' . trim((string) ($row->source_id ?? $ref)),
                    'manual_id' => null,
                    'source' => $table,
                    'category' => $category,
                    'title' => $category . ($ref !== '' ? ' ' . $ref : ''),
                    'party_name' => trim($party . ($code !== '' ? ' (' . $code . ')' : '')),
                    'reference_no' => $ref,
                    'amount' => (float) ($row->amount ?? 0),
                    'due_date' => Carbon::parse((string) $row->duedate)->toDateString(),
                    'priority' => 'Auto',
                    'notes' => 'Auto reminder from due date',
                    'is_done' => false,
                    'editable' => false,
                    'priority_weight' => 1,
                ];
            })
            ->all();
    }

    private function billOptions(string $table, string $category, array $refCols, array $codeCols, array $partyCols, array $amountCols, string $code, string $q): array
    {
        if (!$this->hasTable($table) || !in_array('duedate', $this->columnList($table), true)) {
            return [];
        }

        $cols = $this->columnList($table);
        $ref = $this->firstCol($cols, $refCols);
        $codeCol = $this->firstCol($cols, $codeCols);
        $party = $this->firstCol($cols, $partyCols);
        $amount = $this->firstCol($cols, $amountCols);

        if (!$ref && !$codeCol) {
            return [];
        }

        $query = DB::table($table)
            ->select([
                DB::raw($ref ? "{$ref} as reference_no" : "'' as reference_no"),
                DB::raw($codeCol ? "{$codeCol} as party_code" : "'' as party_code"),
                DB::raw($party ? "{$party} as party_name" : "'' as party_name"),
                DB::raw($amount ? "{$amount} as amount" : "0 as amount"),
                'duedate',
            ])
            ->whereNotNull('duedate')
            ->where('duedate', '<>', '0000-00-00');

        if ($code !== '' && $codeCol) {
            $query->where($codeCol, $code);
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($sub) use ($like, $ref, $party, $codeCol) {
                if ($ref) $sub->orWhere($ref, 'like', $like);
                if ($party) $sub->orWhere($party, 'like', $like);
                if ($codeCol) $sub->orWhere($codeCol, 'like', $like);
            });
        }

        return $query
            ->orderBy('duedate')
            ->limit(100)
            ->get()
            ->map(function ($row) use ($category) {
                $ref = trim((string) ($row->reference_no ?? ''));
                $partyName = trim((string) ($row->party_name ?? ''));
                $partyCode = trim((string) ($row->party_code ?? ''));
                return [
                    'category' => $category,
                    'reference_no' => $ref,
                    'party_code' => $partyCode,
                    'party_name' => $partyName,
                    'amount' => (float) ($row->amount ?? 0),
                    'due_date' => Carbon::parse((string) $row->duedate)->toDateString(),
                    'label' => trim($category . ' ' . $ref . ' - ' . $partyName),
                ];
            })
            ->all();
    }

    private function ensureTable(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('category', 40)->default('Other');
            $table->string('party_name', 160)->nullable();
            $table->string('reference_no', 80)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('due_date');
            $table->string('priority', 20)->default('Normal');
            $table->text('notes')->nullable();
            $table->boolean('is_done')->default(false);
            $table->string('created_by', 40)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['due_date', 'is_done']);
            $table->index('category');
        });
    }

    private function applyRange($query, string $column, string $range): void
    {
        $today = Carbon::today();
        if ($range === 'overdue') {
            $query->where($column, '<', $today->toDateString());
        } elseif ($range === 'today') {
            $query->where($column, $today->toDateString());
        } elseif ($range === 'week') {
            $query->whereBetween($column, [$today->toDateString(), $today->copy()->addDays(7)->toDateString()]);
        } elseif ($range === 'month') {
            $query->whereBetween($column, [$today->toDateString(), $today->copy()->addDays(30)->toDateString()]);
        }
    }

    private function applySearch($query, string $q, array $columns): void
    {
        if ($q === '') {
            return;
        }

        $like = '%' . $q . '%';
        $query->where(function ($sub) use ($columns, $like) {
            foreach ($columns as $column) {
                $sub->orWhere($column, 'like', $like);
            }
        });
    }

    private function rowInRange(string $date, string $range): bool
    {
        $today = Carbon::today()->toDateString();
        if ($range === 'overdue') return $date < $today;
        if ($range === 'today') return $date === $today;
        if ($range === 'week') return $date >= $today && $date <= Carbon::today()->addDays(7)->toDateString();
        if ($range === 'month') return $date >= $today && $date <= Carbon::today()->addDays(30)->toDateString();
        return true;
    }

    private function rowMatches(array $row, string $q): bool
    {
        if ($q === '') {
            return true;
        }

        $haystack = strtolower(implode(' ', [
            $row['category'] ?? '',
            $row['title'] ?? '',
            $row['party_name'] ?? '',
            $row['reference_no'] ?? '',
            $row['notes'] ?? '',
        ]));

        return str_contains($haystack, strtolower($q));
    }

    private function firstCol(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array(strtolower($candidate), $columns, true)) {
                return strtolower($candidate);
            }
        }
        return null;
    }

    private function selectExpr(array $columns, string $column, string $fallback): string
    {
        return in_array($column, $columns, true) ? "COALESCE({$column}, '')" : $fallback;
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function money(mixed $value): float
    {
        return round((float) preg_replace('/[^0-9.\-]/', '', (string) $value), 2);
    }

    private function priorityWeight(string $priority): int
    {
        return match (strtolower(trim($priority))) {
            'high' => 3,
            'low' => 1,
            default => 2,
        };
    }
}
