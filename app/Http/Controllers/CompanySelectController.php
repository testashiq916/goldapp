<?php

namespace App\Http\Controllers;

use App\Models\UserM;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

class CompanySelectController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $companies = $this->availableCompanies();
        $switchTargetDatabase = trim((string) ($request->query('switch_target') ?? $request->session()->get('company_switch_target', '')));
        $switchTargetCompany = '';

        if ($switchTargetDatabase !== '') {
            foreach ($companies as $company) {
                if (strcasecmp((string) ($company['database'] ?? ''), $switchTargetDatabase) === 0) {
                    $switchTargetCompany = (string) ($company['company_name'] ?? $switchTargetDatabase);
                    break;
                }
            }
        }

        return view('native.company-select', [
            'companies' => $companies,
            'currentDatabase' => (string) Config::get('database.connections.mysql.database', ''),
            'currentCompanyName' => $this->currentCompanyName(),
            'dbstrf' => $this->readDbstrf(),
            'switchTargetDatabase' => $switchTargetDatabase,
            'switchTargetCompany' => $switchTargetCompany,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $password = trim((string) $request->input('edit_password', ''));
        $companyName = trim((string) $request->input('company_name', ''));
        $database = trim((string) $request->input('database', ''));
        $originalDatabase = trim((string) $request->input('original_database', ''));

        if (strcasecmp($password, 'PRO') !== 0) {
            return redirect()->to(url('/company-select'))->with('company_select_error', 'Invalid edit password.');
        }

        if ($companyName === '' || $database === '') {
            return redirect()->to(url('/company-select'))->with('company_select_error', 'Company name and database name are required.');
        }

        $companies = collect($this->loadCompanyRegistry());
        $matchDatabase = $originalDatabase !== '' ? $originalDatabase : $database;

        $updated = false;
        $companies = $companies->map(function (array $company) use ($matchDatabase, $companyName, $database, &$updated) {
            if (strcasecmp((string) ($company['database'] ?? ''), $matchDatabase) === 0) {
                $updated = true;
                return [
                    'company_name' => $companyName,
                    'database' => $database,
                ];
            }
            return [
                'company_name' => (string) ($company['company_name'] ?? ''),
                'database' => (string) ($company['database'] ?? ''),
            ];
        });

        if (!$updated) {
            $companies->push([
                'company_name' => $companyName,
                'database' => $database,
            ]);
        }

        $normalized = $companies
            ->filter(fn (array $company) => trim((string) ($company['company_name'] ?? '')) !== '' && trim((string) ($company['database'] ?? '')) !== '')
            ->unique(fn (array $company) => strtolower(trim((string) $company['database'])))
            ->map(fn (array $company) => [
                'company_name' => trim((string) $company['company_name']),
                'database' => trim((string) $company['database']),
            ])
            ->values()
            ->all();

        $this->writeCompanyRegistry($normalized);

        return redirect()->to(url('/company-select'))->with('company_select_success', 'Company list updated.');
    }

    public function delete(Request $request): RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $password = trim((string) $request->input('edit_password', ''));
        $database = trim((string) $request->input('database', ''));
        $currentDatabase = (string) Config::get('database.connections.mysql.database', '');

        if (strcasecmp($password, 'PRO') !== 0) {
            return redirect()->to(url('/company-select'))->with('company_select_error', 'Invalid edit password.');
        }

        if ($database === '') {
            return redirect()->to(url('/company-select'))->with('company_select_error', 'Please choose a company to delete.');
        }

        if (strcasecmp($database, $currentDatabase) === 0) {
            return redirect()->to(url('/company-select'))->with('company_select_error', 'Active company cannot be deleted.');
        }

        $companies = collect($this->loadCompanyRegistry());
        $before = $companies->count();

        $normalized = $companies
            ->reject(fn (array $company) => strcasecmp((string) ($company['database'] ?? ''), $database) === 0)
            ->values()
            ->all();

        if ($before === count($normalized)) {
            return redirect()->to(url('/company-select'))->with('company_select_error', 'Selected company was not found.');
        }

        $this->writeCompanyRegistry($normalized);

        if (strcasecmp($this->readDbstrf(), $database) === 0) {
            $this->writeDbstrf('');
        }

        return redirect()->to(url('/company-select'))->with('company_select_success', 'Company entry deleted.');
    }

    public function switch(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $currentDatabase = (string) Config::get('database.connections.mysql.database', '');
        $targetDatabase = trim((string) $request->input('database', ''));
        $password = trim((string) $request->input('current_password', ''));
        $currentUserCode = trim((string) $request->session()->get('user_code', ''));
        if ($targetDatabase === '') {
            return redirect()->to(url('/company-select'))->with('company_select_error', 'Please choose a company.');
        }
        if ($currentUserCode === '') {
            return redirect('/login');
        }
        if ($password === '') {
            return redirect()->to(url('/company-select'))
                ->with('company_select_error', 'Please enter password.')
                ->with('company_switch_target', $targetDatabase);
        }

        $available = collect($this->availableCompanies())->pluck('database')->all();
        if (!in_array($targetDatabase, $available, true)) {
            return redirect()->to(url('/company-select'))
                ->with('company_select_error', 'Selected company is not available.')
                ->with('company_switch_target', $targetDatabase);
        }

        try {
            if ($targetDatabase !== $currentDatabase) {
                Config::set('database.connections.mysql.database', $targetDatabase);
                DB::purge('mysql');
                DB::reconnect('mysql');
            }

            if (!$this->hasTable('userm')) {
                throw new \RuntimeException('Login table not found.');
            }

            $user = UserM::authenticateLegacy($password);
            if (!$user) {
                return $this->switchFailure($currentDatabase, $targetDatabase, 'Invalid password for selected company.');
            }

            $this->populateAuthenticatedSession($request, $user, $targetDatabase);
            Artisan::call('config:clear');
        } catch (Throwable) {
            Config::set('database.connections.mysql.database', $currentDatabase);
            DB::purge('mysql');
            DB::reconnect('mysql');

            return redirect()->to(url('/company-select'))
                ->with('company_select_error', 'Unable to switch company with this password.')
                ->with('company_switch_target', $targetDatabase);
        }

        return response(
            '<!doctype html><html><head><meta charset="utf-8"><title>Switching Company</title></head><body>' .
            '<script>window.top.location.href=' . json_encode(url('/dashboard') . '?company_switched=' . time()) . ';</script>' .
            '</body></html>'
        )->cookie(new Cookie(
            'selected_company_database',
            $targetDatabase,
            30,
            '/',
            null,
            false,
            false,
            false,
            Cookie::SAMESITE_LAX
        ));
    }

    private function switchFailure(string $currentDatabase, string $targetDatabase, string $message): RedirectResponse
    {
        Config::set('database.connections.mysql.database', $currentDatabase);
        DB::purge('mysql');
        DB::reconnect('mysql');

        return redirect()->to(url('/company-select'))
            ->with('company_select_error', $message)
            ->with('company_switch_target', $targetDatabase);
    }

    private function populateAuthenticatedSession(Request $request, object $user, string $database): void
    {
        $userCode = trim((string) ($user->code ?? ''));
        $userName = trim((string) ($user->name ?? ''));

        $request->session()->regenerate();
        $request->session()->put('user_code', $userCode);
        $request->session()->put('user_name', $userName);
        $request->session()->put('gsuserid', $userCode);
        $request->session()->put('gsusername', $userName);
        $request->session()->put('login_time', time());
        $request->session()->put('selected_database', $database);
        $request->session()->put('gilevel', 1);
        $request->session()->put('user_level', 1);

        $blockedItems = [];
        if ($this->hasTable('userd')) {
            $blockedItems = DB::table('userd')
                ->whereRaw('TRIM(code) = ?', [$userCode])
                ->pluck('menuitem')
                ->map(fn ($value) => (string) $value)
                ->values()
                ->all();
        }

        $isReadonly = false;
        if ($this->hasTable('userd')) {
            $isReadonly = DB::table('userd')
                ->whereRaw('TRIM(code) = ?', [$userCode])
                ->whereRaw('TRIM(menuitem) = ?', ['READONLY'])
                ->exists();
        }

        $request->session()->put('blocked_items', $blockedItems);
        $request->session()->put('readonly', $isReadonly ? 'Y' : 'N');
        $request->session()->put('currency_config', CountryCurrencyController::getConfig());

        if ($this->hasTable('userhist')) {
            $history = ['code' => $userCode];
            if (Schema::hasColumn('userhist', 'tdate')) {
                $history['tdate'] = date('Y-m-d');
            }
            if (Schema::hasColumn('userhist', 'time1')) {
                $history['time1'] = date('H:i:s');
            }
            DB::table('userhist')->insert($history);
        }
    }

    public function close(Request $request)
    {
        $request->session()->forget([
            'user_code',
            'user_name',
            'gsuserid',
            'gsusername',
            'login_time',
            'blocked_items',
            'readonly',
            'currency_config',
            'selected_database',
            'gilevel',
            'user_level',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        try {
            // Ignore cache-clear failures in browser context; runtime reconnect already happened.
            Artisan::call('config:clear');
        } catch (Throwable) {
        }

        return redirect()->away(url('/login') . '?refresh=' . time())->cookie(new Cookie(
            'selected_company_database',
            '',
            -1,
            '/',
            null,
            false,
            false,
            false,
            Cookie::SAMESITE_LAX
        ));
    }

    private function availableCompanies(): array
    {
        $currentDatabase = (string) Config::get('database.connections.mysql.database', '');
        $userCode = strtoupper(trim((string) request()->session()->get('user_code', '')));
        $allowedDatabases = $this->allowedCompanyDatabases($userCode);

        $registry = collect($this->loadCompanyRegistry());

        // Always include the active database even if not in registry
        if ($currentDatabase !== '' && $registry->every(fn($c) => strcasecmp((string)($c['database'] ?? ''), $currentDatabase) !== 0)) {
            $registry->prepend([
                'company_name' => $this->companyNameForDatabase($currentDatabase),
                'database' => $currentDatabase,
            ]);
        }

        return $registry
            ->map(function (array $company) use ($currentDatabase) {
                $database = trim((string) ($company['database'] ?? ''));
                $companyName = trim((string) ($company['company_name'] ?? ''));
                return [
                    'database' => $database,
                    'company_name' => $companyName !== '' ? $companyName : $this->companyNameForDatabase($database),
                    'is_current' => strcasecmp($database, $currentDatabase) === 0,
                ];
            })
            ->filter(fn (array $company) => $company['database'] !== '')
            ->filter(function (array $company) use ($allowedDatabases): bool {
                if ($allowedDatabases === null) {
                    return true;
                }
                return in_array(strtolower($company['database']), $allowedDatabases, true);
            })
            ->sortBy([
                ['is_current', 'desc'],
                ['company_name', 'asc'],
                ['database', 'asc'],
            ])
            ->values()
            ->all();
    }

    private function allowedCompanyDatabases(string $userCode): ?array
    {
        if ($userCode === '' || in_array($userCode, ['ADMIN', 'MGR', 'DEVELOPER'], true)) {
            return null;
        }
        if (!Schema::hasTable('user_company_access')) {
            return null;
        }

        $rows = DB::table('user_company_access')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$userCode])
            ->pluck('database_name')
            ->map(fn ($db) => strtolower(trim((string) $db)))
            ->filter()
            ->values()
            ->all();

        return empty($rows) ? null : $rows;
    }

    private function companyNameForDatabase(string $database): string
    {
        if ($database === '') {
            return '';
        }

        try {
            $safeDatabase = str_replace('`', '``', $database);
            $rows = DB::select("SELECT cvalue FROM `{$safeDatabase}`.`generals` WHERE code = 'SHOPNM' LIMIT 1");
            $name = trim((string) ($rows[0]->cvalue ?? ''));
            return $name !== '' ? $name : $database;
        } catch (\Throwable) {
            return $database;
        }
    }

    private function currentCompanyName(): string
    {
        return $this->companyNameForDatabase((string) Config::get('database.connections.mysql.database', ''));
    }

    private function companyRegistryPath(): string
    {
        return storage_path('app/company-select.json');
    }

    private function loadCompanyRegistry(): array
    {
        $path = $this->companyRegistryPath();
        if (!File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $companies = collect($decoded)
            ->filter(fn ($company) => is_array($company))
            ->map(fn (array $company) => [
                'company_name' => trim((string) ($company['company_name'] ?? '')),
                'database' => trim((string) ($company['database'] ?? '')),
            ])
            ->filter(fn (array $company) => $company['database'] !== '')
            ->values()
            ->all();

        return $companies;
    }

    private function writeCompanyRegistry(array $companies): void
    {
        $path = $this->companyRegistryPath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(array_values($companies), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function setDefault(Request $request): RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $database = trim((string) $request->input('database', ''));
        if ($database === '') {
            return redirect()->to(url('/company-select'))->with('company_select_error', 'Please choose a default secondary company database.');
        }

        $this->writeDbstrf($database);

        return redirect()->to(url('/company-select'))->with('company_select_success', 'Default secondary company database updated.');
    }

    private function dbstrfPath(): string
    {
        return storage_path('app/company-dbstrf.json');
    }

    private function readDbstrf(): string
    {
        $path = $this->dbstrfPath();
        if (!File::exists($path)) return '';
        $decoded = json_decode((string) File::get($path), true);
        return is_array($decoded) ? trim((string) ($decoded['dbstrf'] ?? '')) : '';
    }

    private function writeDbstrf(string $database): void
    {
        $path = $this->dbstrfPath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(['dbstrf' => $database], JSON_PRETTY_PRINT));
    }

    private function writeEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');
        if (!is_file($envPath)) {
            return;
        }

        $content = (string) file_get_contents($envPath);
        $escapedValue = preg_match('/\s/', $value) ? '"' . addslashes($value) . '"' : $value;
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $content)) {
            $content = (string) preg_replace($pattern, $key . '=' . $escapedValue, $content);
        } else {
            $content .= PHP_EOL . $key . '=' . $escapedValue;
        }

        file_put_contents($envPath, $content);
    }
}
