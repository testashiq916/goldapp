<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ApplicationSettingsController;
use App\Http\Controllers\CountryCurrencyController;
use App\Models\UserM;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

class NativeAuthController extends Controller
{
    private string $primaryDatabase;

    public function __construct()
    {
        $this->primaryDatabase = trim((string) env('DB_DATABASE', (string) config('database.connections.mysql.database', '')));
    }

    public function showLogin(Request $request): View|RedirectResponse
    {
        $this->applySelectedDatabaseContext($request);

        if ($request->session()->has('user_code')) {
            return $this->redirectWithinApp($request, 'dashboard');
        }

        return view('native.login', [
            'shopName'  => $this->getShopName(),
            'greeting'  => $this->getGreeting(),
            'shopLogo'  => ApplicationSettingsController::getLogoUrl(),
            'shopAddr'  => DB::table('generals')->where('code', 'SHOPADDR')->value('cvalue') ?? '',
            'shopPhone' => DB::table('generals')->where('code', 'SHOPPHONE')->value('cvalue') ?? '',
            'shopGst'   => DB::table('generals')->where('code', 'GSTIN')->value('cvalue')
                        ?? DB::table('generals')->where('code', 'GSTNO')->value('cvalue') ?? '',
            'companySwitchTheme' => $this->shouldUseCompanySwitchTheme($request),
        ]);
    }

    public function login(Request $request): View|RedirectResponse
    {
        $this->applySelectedDatabaseContext($request);

        $password = trim((string) $request->input('password', ''));
        if ($password === '') {
            return $this->renderLogin($request, 'Please enter password');
        }

        if (!$this->hasTable('userm')) {
            return $this->renderLogin($request, 'Login table not found. Import database_schema.sql and verify DB_DATABASE in .env.');
        }

        try {
            $user = UserM::authenticateLegacy($password);
        } catch (Throwable) {
            return $this->renderLogin($request, 'Login table is not readable by MySQL engine. Repair/recreate userm and retry.');
        }

        if (!$user) {
            return $this->renderLogin($request, 'Invalid password');
        }

        $userCode = trim((string) $user->code);
        $userName = trim((string) $user->name);

        $request->session()->put('user_code', $userCode);
        $request->session()->put('user_name', $userName);
        $request->session()->put('gsuserid', $userCode);
        $request->session()->put('gsusername', $userName);
        $request->session()->put('login_time', time());
        $request->session()->put('selected_database', (string) config('database.connections.mysql.database', ''));

        $blockedItems = [];
        if ($this->hasTable('userd')) {
            $blockedItems = DB::table('userd')
                ->whereRaw('TRIM(code) = ?', [$userCode])
                ->pluck('menuitem')
                ->map(fn ($v) => (string) $v)
                ->values()
                ->all();
        }
        $request->session()->put('blocked_items', $blockedItems);
        $isReadonly = false;
        if ($this->hasTable('userd')) {
            $isReadonly = DB::table('userd')
                ->whereRaw('TRIM(code) = ?', [$userCode])
                ->whereRaw('TRIM(menuitem) = ?', ['READONLY'])
                ->exists();
        }
        $request->session()->put('readonly', $isReadonly ? 'Y' : 'N');

        // Load country / currency / religion config into session
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

        return $this->redirectWithinApp($request, 'dashboard')->cookie(new Cookie(
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

    public function logout(Request $request): RedirectResponse
    {
        if ($this->hasTable('userhist') && $request->session()->has('user_code')) {
            $userCode = trim((string) $request->session()->get('user_code'));
            $today = date('Y-m-d');
            $row = DB::table('userhist')
                ->whereRaw('TRIM(code) = ?', [$userCode])
                ->where('tdate', $today)
                ->where(function ($q) {
                    $q->whereNull('time2')->orWhere('time2', '');
                })
                ->orderByDesc('time1')
                ->limit(1)
                ->first();

            if ($row && isset($row->time1)) {
                DB::table('userhist')
                    ->whereRaw('TRIM(code) = ?', [$userCode])
                    ->where('tdate', $today)
                    ->where('time1', $row->time1)
                    ->update(['time2' => date('H:i:s')]);
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return $this->redirectWithinApp($request, 'login');
    }

    private function renderLogin(Request $request, ?string $error = null): View
    {
        $this->applySelectedDatabaseContext($request);

        return view('native.login', [
            'shopName'  => $this->getShopName(),
            'greeting'  => $this->getGreeting(),
            'shopLogo'  => ApplicationSettingsController::getLogoUrl(),
            'shopAddr'  => DB::table('generals')->where('code', 'SHOPADDR')->value('cvalue') ?? '',
            'shopPhone' => DB::table('generals')->where('code', 'SHOPPHONE')->value('cvalue') ?? '',
            'shopGst'   => DB::table('generals')->where('code', 'GSTIN')->value('cvalue')
                        ?? DB::table('generals')->where('code', 'GSTNO')->value('cvalue') ?? '',
            'companySwitchTheme' => $this->shouldUseCompanySwitchTheme($request),
            'error' => $error,
        ]);
    }

    private function getShopName(): string
    {
        try {
            $row = DB::table('generals')->select('cvalue')->where('code', 'SHOPNM')->first();
            return (string) ($row->cvalue ?? 'Proaims Custom Dashboard');
        } catch (\Throwable) {
            return 'Proaims Custom Dashboard';
        }
    }

    private function getGreeting(): string
    {
        $hour = (int) date('H');
        if ($hour >= 5 && $hour < 12) {
            return 'Good Morning';
        }
        if ($hour >= 12 && $hour < 17) {
            return 'Good Afternoon';
        }
        if ($hour >= 17 && $hour < 22) {
            return 'Good Evening';
        }
        return 'Welcome';
    }

    private function redirectWithinApp(Request $request, string $path): RedirectResponse
    {
        return redirect()->away(rtrim($request->root(), '/') . '/' . ltrim($path, '/'));
    }

    private function applySelectedDatabaseContext(Request $request): void
    {
        $database = $this->resolveSelectedDatabase($request);

        if ($database === '') {
            return;
        }

        if ((string) Config::get('database.connections.mysql.database', '') !== $database) {
            Config::set('database.connections.mysql.database', $database);
            DB::purge('mysql');
            DB::reconnect('mysql');
        }

        $request->session()->put('selected_database', $database);
    }

    private function shouldUseCompanySwitchTheme(Request $request): bool
    {
        $selectedDatabase = $this->resolveSelectedDatabase($request);
        if ($selectedDatabase === '') {
            $selectedDatabase = trim((string) config('database.connections.mysql.database', ''));
        }

        return $selectedDatabase !== '' && strcasecmp($selectedDatabase, $this->primaryDatabase) !== 0;
    }

    private function resolveSelectedDatabase(Request $request): string
    {
        $candidates = [
            $request->query('company_db'),
            $request->input('company_db'),
            $this->databaseFromReferer($request),
            $request->session()->get('selected_database'),
            $request->cookie('selected_company_database'),
            config('database.connections.mysql.database', ''),
        ];

        foreach ($candidates as $candidate) {
            $database = trim((string) $candidate);
            if ($database !== '') {
                return $database;
            }
        }

        return '';
    }

    private function databaseFromReferer(Request $request): string
    {
        $referer = trim((string) $request->headers->get('referer', ''));
        if ($referer === '') {
            return '';
        }

        $parts = parse_url($referer);
        if (!is_array($parts)) {
            return '';
        }

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        return trim((string) ($query['company_db'] ?? ''));
    }
}
