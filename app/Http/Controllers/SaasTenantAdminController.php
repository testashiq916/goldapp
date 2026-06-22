<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SaasTenantAdminController extends Controller
{
    private const ADMIN_PASSWORD = 'GoldplusAdmin';

    public function index(Request $request): View
    {
        $this->usePrimaryDatabase();
        $this->ensureTenantTable();

        $unlocked = $this->isUnlocked($request);

        return view('admin.saas-tenants', [
            'unlocked' => $unlocked,
            'tenants' => $unlocked ? $this->tenants() : [],
            'message' => $request->session()->pull('saas_admin_message'),
            'error' => $request->session()->pull('saas_admin_error'),
        ]);
    }

    public function unlock(Request $request): RedirectResponse
    {
        if (!$this->validAdminPassword((string) $request->input('admin_password', ''))) {
            return redirect('/goldplus-admin')->with('saas_admin_error', 'Invalid admin password.');
        }

        $request->session()->put('saas_admin_unlocked', true);

        return redirect('/goldplus-admin');
    }

    public function save(Request $request): RedirectResponse
    {
        if (!$this->isUnlocked($request)) {
            return redirect('/goldplus-admin')->with('saas_admin_error', 'Admin password required.');
        }

        $this->usePrimaryDatabase();
        $this->ensureTenantTable();

        $id = (int) $request->input('id', 0);
        $domain = $this->normalizeDomain((string) $request->input('domain', ''));
        $shopName = trim((string) $request->input('shop_name', ''));
        $database = trim((string) $request->input('database_name', ''));
        $password = trim((string) $request->input('password', ''));
        $active = $request->boolean('is_active', true);

        if ($domain === '' || $shopName === '' || $database === '') {
            return redirect('/goldplus-admin')->with('saas_admin_error', 'Domain, shop name, and database are required.');
        }

        if ($id <= 0 && $password === '') {
            return redirect('/goldplus-admin')->with('saas_admin_error', 'Password is required for a new tenant.');
        }

        $duplicate = DB::table('saas_tenants')
            ->where('domain', $domain)
            ->when($id > 0, fn ($q) => $q->where('id', '!=', $id))
            ->exists();

        if ($duplicate) {
            return redirect('/goldplus-admin')->with('saas_admin_error', 'This domain is already assigned.');
        }

        $data = [
            'domain' => $domain,
            'shop_name' => $shopName,
            'database_name' => $database,
            'is_active' => $active ? 1 : 0,
            'updated_at' => now(),
        ];

        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($id > 0) {
            DB::table('saas_tenants')->where('id', $id)->update($data);
            return redirect('/goldplus-admin')->with('saas_admin_message', 'Tenant updated.');
        }

        $data['created_at'] = now();
        DB::table('saas_tenants')->insert($data);

        return redirect('/goldplus-admin')->with('saas_admin_message', 'Tenant created.');
    }

    public function delete(Request $request, int $id): RedirectResponse
    {
        if (!$this->isUnlocked($request)) {
            return redirect('/goldplus-admin')->with('saas_admin_error', 'Admin password required.');
        }

        $this->usePrimaryDatabase();
        $this->ensureTenantTable();
        DB::table('saas_tenants')->where('id', $id)->delete();

        return redirect('/goldplus-admin')->with('saas_admin_message', 'Tenant deleted.');
    }

    private function validAdminPassword(string $password): bool
    {
        return hash_equals(self::ADMIN_PASSWORD, trim($password));
    }

    private function isUnlocked(Request $request): bool
    {
        return (bool) $request->session()->get('saas_admin_unlocked', false);
    }

    private function tenants(): array
    {
        return DB::table('saas_tenants')
            ->orderBy('shop_name')
            ->get()
            ->map(fn ($tenant) => [
                'id' => (int) $tenant->id,
                'domain' => (string) $tenant->domain,
                'shop_name' => (string) $tenant->shop_name,
                'database_name' => (string) $tenant->database_name,
                'is_active' => (bool) $tenant->is_active,
                'created_at' => (string) $tenant->created_at,
            ])
            ->all();
    }

    private function ensureTenantTable(): void
    {
        if (Schema::hasTable('saas_tenants')) {
            return;
        }

        DB::statement("
            CREATE TABLE saas_tenants (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                domain VARCHAR(190) NOT NULL,
                shop_name VARCHAR(190) NOT NULL,
                database_name VARCHAR(120) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY saas_tenants_domain_unique (domain)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;

        return trim($domain);
    }

    private function usePrimaryDatabase(): void
    {
        $primary = trim((string) env('DB_DATABASE', (string) config('database.connections.mysql.database', '')));
        if ($primary === '' || (string) Config::get('database.connections.mysql.database', '') === $primary) {
            return;
        }

        Config::set('database.connections.mysql.database', $primary);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }
}
