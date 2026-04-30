<?php
/**
 * Database Configuration & Application Settings
 * Jewelry Sales Management System
 * Converted from PowerBuilder w_sales window
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'jewel_sales');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Secondary database (for branch sync)
define('DB2_HOST', 'localhost');
define('DB2_NAME', 'jewel_sales_branch');
define('DB2_USER', 'root');
define('DB2_PASS', '');

// Application Settings
define('APP_NAME', 'Jewelry Sales Management');
define('APP_VERSION', '2.0');
define('INI_FILE', __DIR__ . '/../../../storage/app/software-settings.ini');

// Session configuration
session_start();

/**
 * PDO Database Connection
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

/**
 * Secondary DB Connection (Branch)
 */
function getDB2(): ?PDO {
    static $pdo2 = null;
    if ($pdo2 === null) {
        try {
            $dsn = "mysql:host=" . DB2_HOST . ";dbname=" . DB2_NAME . ";charset=" . DB_CHARSET;
            $pdo2 = new PDO($dsn, DB2_USER, DB2_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            $pdo2 = null;
        }
    }
    return $pdo2;
}

/**
 * Read INI-style configuration (replaces ProfileString)
 */
function profileString(string $section, string $key, string $default = ''): string {
    static $config = null;
    if ($config === null) {
        if (file_exists(INI_FILE)) {
            $config = parse_ini_file(INI_FILE, true);
        } else {
            $config = [];
        }
    }
    return $config[$section][$key] ?? $default;
}

function profileInt(string $section, string $key, int $default = 0): int {
    return (int) profileString($section, $key, (string) $default);
}

/**
 * Get general string value from generals table
 */
function getGeneralS(string $code): string {
    $db = getDB();
    $stmt = $db->prepare("SELECT cvalue FROM generals WHERE TRIM(code) = ?");
    $stmt->execute([trim($code)]);
    $row = $stmt->fetch();
    return $row ? trim($row['cvalue']) : '';
}

/**
 * Get general decimal value from generald table
 */
function getGeneralD(string $code): float {
    $db = getDB();
    $stmt = $db->prepare("SELECT cvalue FROM generald WHERE TRIM(code) = ?");
    $stmt->execute([trim($code)]);
    $row = $stmt->fetch();
    return $row ? (float) $row['cvalue'] : 0;
}

/**
 * Get general integer value from generali table
 */
function getGeneralI(string $code): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT cvalue FROM generali WHERE TRIM(code) = ?");
    $stmt->execute([trim($code)]);
    $row = $stmt->fetch();
    return $row ? (int) $row['cvalue'] : 0;
}

/**
 * Update generali counter
 */
function updateGeneralI(string $code, int $value): bool {
    $db = getDB();
    $stmt = $db->prepare("UPDATE generali SET cvalue = ? WHERE TRIM(code) = ?");
    return $stmt->execute([$value, trim($code)]);
}

/**
 * Increment generali counter and return new value
 */
function incrementGeneralI(string $code): int {
    $db = getDB();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("SELECT cvalue FROM generali WHERE TRIM(code) = ? FOR UPDATE");
        $stmt->execute([trim($code)]);
        $row = $stmt->fetch();
        $val = $row ? (int) $row['cvalue'] : 0;
        $newVal = $val + 1;
        $stmt = $db->prepare("UPDATE generali SET cvalue = ? WHERE TRIM(code) = ?");
        $stmt->execute([$newVal, trim($code)]);
        $db->commit();
        return $newVal;
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Global Session Variables (replaces PowerBuilder global variables)
 */
function getSessionVar(string $key, $default = null) {
    return $_SESSION[$key] ?? $default;
}

function setSessionVar(string $key, $value): void {
    $_SESSION[$key] = $value;
}

// Current user info
function getCurrentUser(): array {
    return [
        'id'        => getSessionVar('user_id', ''),
        'name'      => getSessionVar('user_name', ''),
        'level'     => getSessionVar('user_level', 1),
        'incharge'  => getSessionVar('incharge', ''),
        'semi'      => getSessionVar('semi', 1),
        'account'   => getSessionVar('account', 1),    // giaccount
        'fupdt'     => getSessionVar('fupdt', 0),      // gifupdt
        'sysupdt'   => getSessionVar('sysupdt', 0),    // gisysupdt
        'strictchk' => getSessionVar('strictchk', 'N'),
        'ids'       => getSessionVar('ids', ''),        // gsids
        'ro'        => getSessionVar('ro', 'N'),
        'ro2'       => getSessionVar('ro2', 'N'),
        'eb'        => getSessionVar('eb', 'E'),        // gseb
        'aded'      => getSessionVar('aded', 'A'),      // gsaded
        'defstktype'=> getSessionVar('defstktype', ''), // gsdefstktype
    ];
}

// Rate globals
function getRates(): array {
    return [
        'grate'   => getSessionVar('grate', 0),    // gdgrate
        'srate'   => getSessionVar('srate', 0),     // gdsrate
        'prate'   => getSessionVar('prate', 0),     // gdprate
        'jrate'   => getSessionVar('jrate', 0),     // gdjrate
        'ograte'  => getSessionVar('ograte', 0),    // gdograte
        'osrate'  => getSessionVar('osrate', 0),    // gdosrate
        'thrate'  => getSessionVar('thrate', 0),
        'g18rate' => getSessionVar('g18rate', 0),   // gdg18rate
        'stax'    => getSessionVar('stax', 0),      // gdstax
        'ptax'    => getSessionVar('ptax', 0),       // gdptax
        'astperc' => getSessionVar('astperc', 0),   // gdastperc
        'wastage' => getSessionVar('wastage', 0),   // gdwastage
    ];
}
