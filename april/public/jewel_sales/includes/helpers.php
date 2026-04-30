<?php
/**
 * Helper Functions
 * Replaces PowerBuilder global functions used in w_sales
 */

require_once __DIR__ . '/config.php';

/**
 * Format number for display (replaces PB ftrans)
 */
function ftrans(float $value, int $width, int $decimals): string {
    return str_pad(number_format($value, $decimals, '.', ''), $width, ' ', STR_PAD_LEFT);
}

/**
 * Convert amount to words (replaces stramount)
 */
function amountToWords(float $amount): string {
    if ($amount == 0) return 'Zero';
    
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
             'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
             'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    
    $amount = abs($amount);
    $rupees = (int) $amount;
    $paise = round(($amount - $rupees) * 100);
    
    $words = '';
    
    if ($rupees >= 10000000) {
        $words .= amountToWords(floor($rupees / 10000000)) . ' Crore ';
        $rupees %= 10000000;
    }
    if ($rupees >= 100000) {
        $words .= amountToWords(floor($rupees / 100000)) . ' Lakh ';
        $rupees %= 100000;
    }
    if ($rupees >= 1000) {
        $words .= amountToWords(floor($rupees / 1000)) . ' Thousand ';
        $rupees %= 1000;
    }
    if ($rupees >= 100) {
        $words .= $ones[floor($rupees / 100)] . ' Hundred ';
        $rupees %= 100;
    }
    if ($rupees >= 20) {
        $words .= $tens[floor($rupees / 10)] . ' ';
        $rupees %= 10;
    }
    if ($rupees > 0) {
        $words .= $ones[$rupees] . ' ';
    }
    
    $result = trim($words) . ' Rupees';
    if ($paise > 0) {
        $result .= ' and ' . amountToWords($paise) . ' Paise';
    }
    return $result . ' Only';
}

/**
 * Calculate account balance (replaces acbalance2)
 */
function acBalance(string $accode, int $excludeSlno = 0): float {
    $db = getDB();
    $sql = "SELECT COALESCE(SUM(amount), 0) AS bal FROM daybook WHERE accode = ?";
    $params = [$accode];
    
    if ($excludeSlno > 0) {
        $sql .= " AND slno != ?";
        $params[] = $excludeSlno;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ? (float) $row['bal'] : 0;
}

/**
 * Calculate co-party balance (replaces copartybal)
 */
function coPartyBalance(string $code): float {
    return acBalance($code);
}

/**
 * Check stock type exists, create if not (replaces chkstktype)
 */
function checkStkType(string $itemCode, string $stkType): void {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM itemsstk WHERE code = ? AND stktype = ?");
    $stmt->execute([$itemCode, $stkType]);
    $row = $stmt->fetch();
    
    if ($row['cnt'] == 0) {
        $stmt = $db->prepare("INSERT INTO itemsstk (code, stktype, qty, qtyb, weight, weightb, stonewgt, stonewgtb) VALUES (?, ?, 0, 0, 0, 0, 0, 0)");
        $stmt->execute([$itemCode, $stkType]);
    }
}

/**
 * Calculate MC based on item/weight/qty (replaces mccalc)
 */
function mcCalc(string $code, float $netwgt, int $qty, float $rate, string $qtype = ''): float {
    $db = getDB();
    
    // Check mctable for MC per qty
    $stmt = $db->prepare("SELECT mcperqty FROM mctable WHERE code = ? AND ? > weight1 AND ? <= weight2");
    $stmt->execute([$code, $netwgt, $netwgt]);
    $row = $stmt->fetch();
    
    if ($row && $row['mcperqty'] > 0) {
        return $row['mcperqty'] * $qty;
    }
    
    return 0;
}

/**
 * Calculate wastage based on item (replaces wastagecalc)
 */
function wastageCalc(string $code, string $qtype, float $netwgt): float {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT wastage FROM wstg WHERE ? >= llimit AND ? <= ulimit");
    $stmt->execute([$netwgt, $netwgt]);
    $row = $stmt->fetch();
    
    return $row ? (float) $row['wastage'] : 0;
}

/**
 * Calculate privilege card points (replaces prevcardcalc)
 */
function prevCardCalc(string $custCode, string $date, int $excludeSlno = 0): array {
    // Simplified privilege card calculation
    return ['points' => 0, 'amount' => 0];
}

/**
 * Check user permission (replaces userd menu check)
 */
function hasPermission(string $userId, string $menuItem): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM userd WHERE code = ? AND menuitem = ?");
    $stmt->execute([$userId, $menuItem]);
    $row = $stmt->fetch();
    return $row && $row['cnt'] > 0;
}

/**
 * Get user settings (replaces userm queries)
 */
function getUserSettings(string $userId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM userm WHERE code = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: [];
}

/**
 * Check date validity (replaces chkdate)
 */
function checkDate(string $date): bool {
    $d = DateTime::createFromFormat('d/m/Y', $date);
    if (!$d) return false;
    
    $today = new DateTime();
    $diff = $today->diff($d)->days;
    
    // Allow dates within reasonable range
    return $diff <= 365;
}

/**
 * Validate barcode and return item details (replaces bcodechk)
 */
function barcodeCheck(int $bcode, float $taxPerc = 0, float $astPerc = 0): ?array {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT icode, qty, weight, stweight, stprice, wastage, mcrate, mc, rate, vap, stk, tamt, stkinnos, part, qtype, stktouch, cost, transtouch, smithcode, costmc, model, costperc, huid FROM barcode WHERE bcode = ?");
    $stmt->execute([$bcode]);
    $bc = $stmt->fetch();
    
    if (!$bc) return null;
    
    // Get item details
    $stmt2 = $db->prepare("SELECT itype, taxinternal, defquality, vaoffer FROM items WHERE code = ?");
    $stmt2->execute([trim($bc['icode'])]);
    $item = $stmt2->fetch();
    
    if (!$item) return null;
    
    // Get diamond details
    $stmt3 = $db->prepare("SELECT dmdamt, dmdwgt, dmdunit, dmdnos FROM barcodedmd WHERE bcode = ?");
    $stmt3->execute([$bcode]);
    $dmd = $stmt3->fetch() ?: ['dmdamt' => 0, 'dmdwgt' => 0, 'dmdunit' => '', 'dmdnos' => 0];
    
    return array_merge($bc, $item, $dmd);
}

/**
 * Generate bill number based on configuration
 */
function generateBillNo(string $eb, string $billType = '', bool $isShortPrint = false): string {
    $db = getDB();
    
    if (profileString('Software', 'SalesFirstQtnNo', 'N') === 'Y') {
        $billno = getGeneralI('QTNNO');
        return 'Q/' . date('md') . '/' . str_pad($billno + 1, 3, '0', STR_PAD_LEFT);
    }
    
    if ($isShortPrint) {
        $code = 'TSALES' . $eb;
        $billno = getGeneralI($code);
        $prefix = ($eb === 'B') ? 'TSB/' : 'TSE/';
        return $prefix . str_pad($billno + 1, 5, '0', STR_PAD_LEFT);
    }
    
    if (profileString('Software', 'BillTypewiseBillNo', 'N') === 'Y' && $eb === 'B' && $billType) {
        $stmt = $db->prepare("SELECT prefix FROM salestype WHERE code = ?");
        $stmt->execute([$billType]);
        $row = $stmt->fetch();
        $prefix = $row ? $row['prefix'] : '';
        
        $code = 'SALES' . $prefix;
        $billno = getGeneralI($code);
        return $prefix . str_pad($billno + 1, 5, '0', STR_PAD_LEFT);
    }
    
    $code = 'SALES' . $eb;
    $billno = getGeneralI($code);
    
    if ($eb === 'B') {
        $prefix = getGeneralS('SBPREF') ?: 'SLB/';
        $len = (int)(getGeneralS('SBLEN') ?: '5');
    } else {
        $prefix = getGeneralS('SEPREF') ?: 'SLE/';
        $len = (int)(getGeneralS('SELEN') ?: '5');
    }
    
    return $prefix . str_pad($billno + 1, $len, '0', STR_PAD_LEFT);
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Sanitize input
 */
function sanitize($input): string {
    if (is_null($input)) return '';
    return htmlspecialchars(trim((string) $input), ENT_QUOTES, 'UTF-8');
}

/**
 * Get float from input
 */
function inputFloat(string $key, float $default = 0): float {
    $val = $_POST[$key] ?? $_GET[$key] ?? $default;
    return (float) $val;
}

/**
 * Get int from input
 */
function inputInt(string $key, int $default = 0): int {
    $val = $_POST[$key] ?? $_GET[$key] ?? $default;
    return (int) $val;
}

/**
 * Get string from input
 */
function inputStr(string $key, string $default = ''): string {
    $val = $_POST[$key] ?? $_GET[$key] ?? $default;
    return trim((string) $val);
}
