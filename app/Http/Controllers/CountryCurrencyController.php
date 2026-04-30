<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CountryCurrencyController extends Controller
{
    use LogsDelpartAudit;

    /* ── Country Presets ─────────────────────────────────────── */
    public static array $profiles = [
        'IN' => [
            'country_name' => 'India',          'religion'        => 'Hindu',
            'currency_code' => 'INR',           'currency_symbol' => '₹',
            'currency_name' => 'Indian Rupee',  'exchange_rate'   => 1.0,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'gregorian',
            'weight_unit'   => 'gram',          'purity_system'   => 'millesimal',
            'decimal_places'=> 2,               'tax_label'       => 'GST',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri,Sat',
            'number_format' => 'indian',        'flag'            => '🇮🇳',
        ],
        'AE' => [
            'country_name' => 'UAE',            'religion'        => 'Islamic',
            'currency_code' => 'AED',           'currency_symbol' => 'د.إ',
            'currency_name' => 'UAE Dirham',    'exchange_rate'   => 22.68,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'both',
            'weight_unit'   => 'gram',          'purity_system'   => 'karat',
            'decimal_places'=> 2,               'tax_label'       => 'VAT',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri',
            'number_format' => 'western',       'flag'            => '🇦🇪',
        ],
        'SA' => [
            'country_name' => 'Saudi Arabia',   'religion'        => 'Islamic',
            'currency_code' => 'SAR',           'currency_symbol' => 'ر.س',
            'currency_name' => 'Saudi Riyal',   'exchange_rate'   => 22.30,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'both',
            'weight_unit'   => 'gram',          'purity_system'   => 'karat',
            'decimal_places'=> 2,               'tax_label'       => 'VAT',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri',
            'number_format' => 'western',       'flag'            => '🇸🇦',
        ],
        'QA' => [
            'country_name' => 'Qatar',          'religion'        => 'Islamic',
            'currency_code' => 'QAR',           'currency_symbol' => 'ر.ق',
            'currency_name' => 'Qatari Riyal',  'exchange_rate'   => 22.94,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'gregorian',
            'weight_unit'   => 'gram',          'purity_system'   => 'karat',
            'decimal_places'=> 2,               'tax_label'       => 'VAT',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri',
            'number_format' => 'western',       'flag'            => '🇶🇦',
        ],
        'KW' => [
            'country_name' => 'Kuwait',         'religion'        => 'Islamic',
            'currency_code' => 'KWD',           'currency_symbol' => 'د.ك',
            'currency_name' => 'Kuwaiti Dinar', 'exchange_rate'   => 272.0,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'gregorian',
            'weight_unit'   => 'gram',          'purity_system'   => 'karat',
            'decimal_places'=> 3,               'tax_label'       => 'VAT',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri',
            'number_format' => 'western',       'flag'            => '🇰🇼',
        ],
        'BH' => [
            'country_name' => 'Bahrain',        'religion'        => 'Islamic',
            'currency_code' => 'BHD',           'currency_symbol' => 'BD',
            'currency_name' => 'Bahraini Dinar','exchange_rate'   => 221.0,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'gregorian',
            'weight_unit'   => 'gram',          'purity_system'   => 'karat',
            'decimal_places'=> 3,               'tax_label'       => 'VAT',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri',
            'number_format' => 'western',       'flag'            => '🇧🇭',
        ],
        'OM' => [
            'country_name' => 'Oman',           'religion'        => 'Islamic',
            'currency_code' => 'OMR',           'currency_symbol' => 'ر.ع',
            'currency_name' => 'Omani Rial',    'exchange_rate'   => 217.0,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'gregorian',
            'weight_unit'   => 'gram',          'purity_system'   => 'karat',
            'decimal_places'=> 3,               'tax_label'       => 'VAT',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri',
            'number_format' => 'western',       'flag'            => '🇴🇲',
        ],
        'US' => [
            'country_name' => 'USA',            'religion'        => 'Christian',
            'currency_code' => 'USD',           'currency_symbol' => '$',
            'currency_name' => 'US Dollar',     'exchange_rate'   => 83.5,
            'date_format'   => 'MM/DD/YYYY',    'calendar_type'   => 'gregorian',
            'weight_unit'   => 'troy_oz',       'purity_system'   => 'karat',
            'decimal_places'=> 2,               'tax_label'       => 'Tax',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri',
            'number_format' => 'western',       'flag'            => '🇺🇸',
        ],
        'GB' => [
            'country_name' => 'United Kingdom', 'religion'        => 'Christian',
            'currency_code' => 'GBP',           'currency_symbol' => '£',
            'currency_name' => 'British Pound', 'exchange_rate'   => 106.0,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'gregorian',
            'weight_unit'   => 'gram',          'purity_system'   => 'millesimal',
            'decimal_places'=> 2,               'tax_label'       => 'VAT',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri',
            'number_format' => 'western',       'flag'            => '🇬🇧',
        ],
        'SG' => [
            'country_name' => 'Singapore',      'religion'        => 'Christian',
            'currency_code' => 'SGD',           'currency_symbol' => 'S$',
            'currency_name' => 'Singapore Dollar','exchange_rate' => 62.5,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'gregorian',
            'weight_unit'   => 'gram',          'purity_system'   => 'millesimal',
            'decimal_places'=> 2,               'tax_label'       => 'GST',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri',
            'number_format' => 'western',       'flag'            => '🇸🇬',
        ],
        'MY' => [
            'country_name' => 'Malaysia',       'religion'        => 'Islamic',
            'currency_code' => 'MYR',           'currency_symbol' => 'RM',
            'currency_name' => 'Malaysian Ringgit','exchange_rate'=> 19.0,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'gregorian',
            'weight_unit'   => 'gram',          'purity_system'   => 'millesimal',
            'decimal_places'=> 2,               'tax_label'       => 'SST',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri,Sat',
            'number_format' => 'western',       'flag'            => '🇲🇾',
        ],
        'NP' => [
            'country_name' => 'Nepal',          'religion'        => 'Hindu',
            'currency_code' => 'NPR',           'currency_symbol' => 'Rs',
            'currency_name' => 'Nepalese Rupee','exchange_rate'   => 0.63,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'gregorian',
            'weight_unit'   => 'tola',          'purity_system'   => 'karat',
            'decimal_places'=> 2,               'tax_label'       => 'VAT',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri,Sat',
            'number_format' => 'indian',        'flag'            => '🇳🇵',
        ],
        'LK' => [
            'country_name' => 'Sri Lanka',      'religion'        => 'Buddhist',
            'currency_code' => 'LKR',           'currency_symbol' => 'Rs',
            'currency_name' => 'Sri Lankan Rupee','exchange_rate' => 0.28,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'gregorian',
            'weight_unit'   => 'gram',          'purity_system'   => 'millesimal',
            'decimal_places'=> 2,               'tax_label'       => 'VAT',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri,Sat',
            'number_format' => 'western',       'flag'            => '🇱🇰',
        ],
        'BD' => [
            'country_name' => 'Bangladesh',     'religion'        => 'Islamic',
            'currency_code' => 'BDT',           'currency_symbol' => '৳',
            'currency_name' => 'Bangladeshi Taka','exchange_rate' => 0.76,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'gregorian',
            'weight_unit'   => 'gram',          'purity_system'   => 'karat',
            'decimal_places'=> 2,               'tax_label'       => 'VAT',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri,Sat',
            'number_format' => 'western',       'flag'            => '🇧🇩',
        ],
        'AU' => [
            'country_name' => 'Australia',      'religion'        => 'Christian',
            'currency_code' => 'AUD',           'currency_symbol' => 'A$',
            'currency_name' => 'Australian Dollar','exchange_rate'=> 54.0,
            'date_format'   => 'DD/MM/YYYY',    'calendar_type'   => 'gregorian',
            'weight_unit'   => 'gram',          'purity_system'   => 'millesimal',
            'decimal_places'=> 2,               'tax_label'       => 'GST',
            'rtl'           => 0,               'working_days'    => 'Mon,Tue,Wed,Thu,Fri',
            'number_format' => 'western',       'flag'            => '🇦🇺',
        ],
    ];

    /* ── Table bootstrap ──────────────────────────────────────── */
    private function ensureTable(): void
    {
        if ($this->hasTable('country_currency_config')) return;

        DB::statement("CREATE TABLE country_currency_config (
            id           TINYINT UNSIGNED NOT NULL DEFAULT 1,
            country_code VARCHAR(5)   NOT NULL DEFAULT 'IN',
            country_name VARCHAR(100) NOT NULL DEFAULT 'India',
            religion     VARCHAR(50)  NOT NULL DEFAULT 'Hindu',
            currency_code   VARCHAR(10)  NOT NULL DEFAULT 'INR',
            currency_symbol VARCHAR(20)  NOT NULL DEFAULT '₹',
            currency_name   VARCHAR(100) NOT NULL DEFAULT 'Indian Rupee',
            exchange_rate   DECIMAL(15,6) NOT NULL DEFAULT 1.000000,
            base_currency   VARCHAR(10)  NOT NULL DEFAULT 'INR',
            date_format     VARCHAR(20)  NOT NULL DEFAULT 'DD/MM/YYYY',
            calendar_type   VARCHAR(20)  NOT NULL DEFAULT 'gregorian',
            weight_unit     VARCHAR(20)  NOT NULL DEFAULT 'gram',
            purity_system   VARCHAR(20)  NOT NULL DEFAULT 'millesimal',
            decimal_places  TINYINT UNSIGNED NOT NULL DEFAULT 2,
            tax_label       VARCHAR(20)  NOT NULL DEFAULT 'GST',
            number_format   VARCHAR(20)  NOT NULL DEFAULT 'indian',
            rtl             TINYINT(1)   NOT NULL DEFAULT 0,
            working_days    VARCHAR(100) NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri,Sat',
            updated_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $row = array_merge(['id' => 1, 'country_code' => 'IN', 'base_currency' => 'INR'], self::$profiles['IN']);
        unset($row['flag']); // flag is display-only, not stored in DB
        DB::table('country_currency_config')->insert($row);
    }

    /* ── Static helper — used by every controller to get config ── */
    public static function getConfig(): array
    {
        try {
            if (!$this->hasTable('country_currency_config')) return self::defaultConfig();
            $row = DB::table('country_currency_config')->where('id', 1)->first();
            return $row ? (array) $row : self::defaultConfig();
        } catch (\Throwable) {
            return self::defaultConfig();
        }
    }

    public static function defaultConfig(): array
    {
        return array_merge(
            ['id' => 1, 'country_code' => 'IN', 'base_currency' => 'INR'],
            self::$profiles['IN']
        );
    }

    /* ── Page ─────────────────────────────────────────────────── */
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        $this->ensureTable();
        return view('country-currency.index', [
            'title'    => 'Country, Currency & Religion Settings',
            'moduleId' => (string) $request->query('module', 'country-currency'),
            'today'    => date('Y-m-d'),
        ]);
    }

    /* ── API: load ────────────────────────────────────────────── */
    public function load(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);
        $this->ensureTable();
        return response()->json([
            'ok'       => true,
            'config'   => self::getConfig(),
            'profiles' => self::$profiles,
        ]);
    }

    /* ── API: save ────────────────────────────────────────────── */
    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);
        $this->ensureTable();

        $data = [
            'country_code'    => strtoupper(trim($request->input('country_code', 'IN'))),
            'country_name'    => trim($request->input('country_name', 'India')),
            'religion'        => trim($request->input('religion', 'Hindu')),
            'currency_code'   => strtoupper(trim($request->input('currency_code', 'INR'))),
            'currency_symbol' => trim($request->input('currency_symbol', '₹')),
            'currency_name'   => trim($request->input('currency_name', 'Indian Rupee')),
            'exchange_rate'   => max(0.000001, (float) $request->input('exchange_rate', 1)),
            'base_currency'   => strtoupper(trim($request->input('base_currency', 'INR'))),
            'date_format'     => trim($request->input('date_format', 'DD/MM/YYYY')),
            'calendar_type'   => trim($request->input('calendar_type', 'gregorian')),
            'weight_unit'     => trim($request->input('weight_unit', 'gram')),
            'purity_system'   => trim($request->input('purity_system', 'millesimal')),
            'decimal_places'  => min(4, max(0, (int) $request->input('decimal_places', 2))),
            'tax_label'       => trim($request->input('tax_label', 'GST')),
            'number_format'   => trim($request->input('number_format', 'indian')),
            'rtl'             => (int)(bool) $request->input('rtl', 0),
            'working_days'    => trim($request->input('working_days', 'Mon,Tue,Wed,Thu,Fri,Sat')),
        ];

        if (DB::table('country_currency_config')->where('id', 1)->exists()) {
            DB::table('country_currency_config')->where('id', 1)->update($data);
        } else {
            DB::table('country_currency_config')->insert(array_merge(['id' => 1], $data));
        }

        // Refresh session so all modules pick up changes immediately
        $request->session()->put('currency_config', array_merge(['id' => 1], $data));

        $this->logDelpart($request, 'Country/Currency Settings Saved', ['utype' => 'E', 'ttype' => 'R']);
        return response()->json(['ok' => true, 'message' => 'Settings saved. All modules will now use the new currency & format.']);
    }

    /* ── API: public config (used by iframe modules) ─────────── */
    public function configApi(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);
        return response()->json(array_merge(['ok' => true], self::getConfig()));
    }
}
