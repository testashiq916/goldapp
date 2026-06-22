<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Concerns\LogsDelpartAudit;
use App\Http\Requests\StoreUserAccessRequest;
use App\Models\UserAccess;
use App\Services\PasswordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class UserAccessController extends Controller
{
    use LogsDelpartAudit;

    private function guard(Request $request): void
    {
        $userCode = strtoupper(trim((string) $request->session()->get('user_code', '')));
        if ($userCode === '') {
            redirect('/login')->send();
            exit;
        }

        $allowedCodes = ['ADMIN', 'MGR', 'DEVELOPER'];
        if (in_array($userCode, $allowedCodes, true)) {
            return;
        }

        if ($this->hasTable('userd')) {
            $hasAccess = DB::table('userd')
                ->whereRaw('UPPER(TRIM(code)) = ?', [$userCode])
                ->where(function ($q) {
                    $q->whereRaw('UPPER(TRIM(menuitem)) = ?', ['ADMINBUTTON'])
                      ->orWhereRaw('UPPER(TRIM(menuitem)) = ?', ['MDI_USER_ACCESS']);
                })
                ->exists();

            if ($hasAccess) {
                return;
            }
        }

        abort(403, 'Access Denied');
    }

    public function index(Request $request)
    {
        $this->guard($request);

        return view('user-access.index', [
            'permissions' => Permission::groupedLabels(),
            'companies' => $this->availableCompanies(),
        ]);
    }

    public function getUsers(Request $request): JsonResponse
    {
        $this->guard($request);

        $users = UserAccess::query()
            ->select(['code', 'name', 'maxdisc', 'maxcredit', 'minvaperc', 'maxdiscperc', 'maxadjwgtbc'])
            ->orderBy('code')
            ->get();

        return response()->json(['ok' => true, 'users' => $users]);
    }

    public function getUser(Request $request): JsonResponse
    {
        $this->guard($request);

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['ok' => false, 'error' => 'User code required']);
        }

        $user = UserAccess::query()
            ->select(['code', 'name', 'maxdisc', 'maxcredit', 'minvaperc', 'maxdiscperc', 'maxadjwgtbc'])
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->first();

        if (!$user) {
            return response()->json(['ok' => false, 'error' => 'User not found']);
        }

        return response()->json([
            'ok' => true,
            'user' => $user,
            'permissions' => $user->permissionList(),
            'companies' => $this->companyAccessList($code),
        ]);
    }

    public function saveUser(StoreUserAccessRequest $request): JsonResponse
    {
        $this->guard($request);

        $validated = $request->validated();
        $mode = $validated['mode'];
        $code = $validated['code'];
        $password = (string) ($validated['password'] ?? '');

        if ($mode === 'add' && $password === '') {
            return response()->json(['ok' => false, 'error' => 'Password is required for new user']);
        }

        if ($mode === 'add' && UserAccess::query()->whereRaw('UPPER(TRIM(code)) = ?', [$code])->exists()) {
            return response()->json(['ok' => false, 'error' => 'User code already exists']);
        }

        DB::transaction(function () use ($validated, $mode, $code, $password): void {
            $payload = [
                'name' => $validated['name'],
                'maxcredit' => (float) ($validated['maxcredit'] ?? 0),
                'maxdisc' => (float) ($validated['maxdisc'] ?? 0),
                'maxdiscperc' => (float) ($validated['maxdiscperc'] ?? 0),
                'minvaperc' => (float) ($validated['minvaperc'] ?? 0),
                'maxadjwgtbc' => (float) ($validated['maxadjwgtbc'] ?? 0),
            ];

            if ($mode === 'add') {
                $payload['code'] = $code;
                $payload['pcode'] = PasswordService::encrypt($password);
                $user = UserAccess::create($payload);
            } else {
                $user = UserAccess::query()
                    ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
                    ->firstOrFail();

                if ($password !== '') {
                    $payload['pcode'] = PasswordService::encrypt($password);
                }

                $user->update($payload);
            }

            $user->syncPermissions($validated['permissions'] ?? []);
            $this->syncCompanyAccess($code, $validated['companies'] ?? []);
        });

        $this->logDelpart($request, 'User Access(' . $code . ') ' . ($mode === 'add' ? 'Added' : 'Updated'), ['utype' => $mode === 'add' ? 'A' : 'E', 'ttype' => 'R']);
        return response()->json(['ok' => true, 'message' => 'User saved successfully']);
    }

    public function deleteUser(Request $request): JsonResponse
    {
        $this->guard($request);

        $code = strtoupper(trim((string) $request->input('code', '')));
        if ($code === '') {
            return response()->json(['ok' => false, 'error' => 'User code required']);
        }

        if (in_array($code, ['ADMIN', 'MGR'], true)) {
            return response()->json(['ok' => false, 'error' => 'Cannot delete protected user']);
        }

        $exists = UserAccess::query()->whereRaw('UPPER(TRIM(code)) = ?', [$code])->exists();
        if (!$exists) {
            return response()->json(['ok' => false, 'error' => 'User not found']);
        }

        DB::transaction(function () use ($code): void {
            DB::table('userd')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->delete();
            if (Schema::hasTable('user_company_access')) {
                DB::table('user_company_access')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->delete();
            }

            if ($this->hasTable('userhist')) {
                DB::table('userhist')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->delete();
            }

            UserAccess::query()->whereRaw('UPPER(TRIM(code)) = ?', [$code])->delete();
        });

        $this->logDelpart($request, 'User Access(' . $code . ') Deleted', ['utype' => 'D', 'ttype' => 'R']);
        return response()->json(['ok' => true, 'message' => 'User deleted']);
    }

    private function ensureCompanyAccessTable(): void
    {
        if (Schema::hasTable('user_company_access')) {
            return;
        }

        DB::statement("
            CREATE TABLE user_company_access (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(20) NOT NULL,
                database_name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY user_company_access_unique (code, database_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function syncCompanyAccess(string $code, array $companies): void
    {
        $this->ensureCompanyAccessTable();
        $code = strtoupper(trim($code));

        DB::table('user_company_access')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->delete();

        $rows = collect($companies)
            ->map(fn ($db) => trim((string) $db))
            ->filter()
            ->unique(fn ($db) => strtolower($db))
            ->map(fn ($db) => [
                'code' => $code,
                'database_name' => $db,
            ])
            ->values()
            ->all();

        if (!empty($rows)) {
            DB::table('user_company_access')->insert($rows);
        }
    }

    private function companyAccessList(string $code): array
    {
        $this->ensureCompanyAccessTable();

        return DB::table('user_company_access')
            ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper(trim($code))])
            ->pluck('database_name')
            ->map(fn ($db) => trim((string) $db))
            ->filter()
            ->values()
            ->all();
    }

    private function availableCompanies(): array
    {
        $companies = [];
        $registryPath = storage_path('app/company-select.json');

        if (File::exists($registryPath)) {
            $decoded = json_decode((string) File::get($registryPath), true);
            if (is_array($decoded)) {
                foreach ($decoded as $company) {
                    if (!is_array($company)) {
                        continue;
                    }
                    $database = trim((string) ($company['database'] ?? ''));
                    if ($database === '') {
                        continue;
                    }
                    $companies[$database] = [
                        'name' => trim((string) ($company['company_name'] ?? $database)),
                        'database' => $database,
                    ];
                }
            }
        }

        if (Schema::hasTable('owner_branches')) {
            DB::table('owner_branches')
                ->where('is_active', 1)
                ->orderBy('name')
                ->get()
                ->each(function ($branch) use (&$companies): void {
                    $database = trim((string) ($branch->db_name ?? ''));
                    if ($database === '') {
                        return;
                    }
                    $companies[$database] = [
                        'name' => trim((string) ($branch->name ?? $database)),
                        'database' => $database,
                    ];
                });
        }

        return collect($companies)
            ->sortBy('name')
            ->values()
            ->all();
    }
}
