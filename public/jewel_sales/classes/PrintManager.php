<?php
/**
 * PrintManager Class
 * Handles all bill printing - converted from PowerBuilder print functions
 * Supports: A4/Laser, Thermal, Dot Matrix, and PDF generation
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

class PrintManager
{
    private PDO $db;
    private array $config;

    public function __construct()
    {
        $this->db = getDB();
        $this->config = [
            'companyName'    => profileString('Company', 'Name', 'Jewelry Store'),
            'companyAddr1'   => profileString('Company', 'Address1', ''),
            'companyAddr2'   => profileString('Company', 'Address2', ''),
            'companyPhone'   => profileString('Company', 'Phone', ''),
            'companyGSTIN'   => profileString('Company', 'GSTIN', ''),
            'companyState'   => profileString('Company', 'State', ''),
            'companyPAN'     => profileString('Company', 'PAN', ''),
            'printType'      => profileString('Software', 'PrintType', 'A4'),
            'thermalWidth'   => profileInt('Software', 'ThermalWidth', 80),
            'printExchange'  => profileString('Software', 'PrintExchange', 'Y'),
            'printSReturn'   => profileString('Software', 'PrintSReturn', 'Y'),
            'bilingualPrint' => profileString('Software', 'BilingualPrint', 'N'),
            'printMC'        => profileString('Software', 'PrintMC', 'Y'),
            'printWastage'   => profileString('Software', 'PrintWastage', 'Y'),
            'printRate'      => profileString('Software', 'PrintRate', 'Y'),
            'printStonePrice'=> profileString('Software', 'PrintStonePrice', 'Y'),
            'printHSN'       => profileString('Software', 'PrintHSN', 'Y'),
            'printHUID'      => profileString('Software', 'PrintHUID', 'N'),
            'printTax'       => profileString('Software', 'PrintTax', 'Y'),
            'gstEnabled'     => profileString('Software', 'GSTEnable', 'Y'),
            'printCopies'    => profileInt('Software', 'PrintCopies', 1),
            'printLogo'      => profileString('Software', 'PrintLogo', 'N'),
            'logoPath'       => profileString('Software', 'LogoPath', ''),
            'printFooter'    => profileString('Software', 'PrintFooter', ''),
            'printTerms'     => profileString('Software', 'PrintTerms', ''),
        ];
    }

    /**
     * Generate printable HTML bill (replaces all PB print functions)
     */
    public function generateBillHTML(int $slno): string
    {
        // Load bill data
        $master = $this->loadBillData($slno);
        if (!$master) return '<p>Bill not found</p>';

        $details = $this->loadBillDetails($slno);
        $exchange = $this->loadExchangeDetails($slno);
        $returns = $this->loadReturnDetails($slno);

        $printType = $this->config['printType'];

        switch ($printType) {
            case 'THERMAL':
                return $this->thermalBill($master, $details, $exchange, $returns);
            case 'DOT':
                return $this->dotMatrixBill($master, $details, $exchange, $returns);
            default:
                return $this->a4Bill($master, $details, $exchange, $returns);
        }
    }

    /**
     * Load bill master data
     */
    private function loadBillData(int $slno): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM salesm WHERE slno = ?");
        $stmt->execute([$slno]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Load bill detail items
     */
    private function loadBillDetails(int $slno): array
    {
        $stmt = $this->db->prepare("SELECT d.*, i.name as itemname, i.itype, i.hsncode as itemhsn FROM salesd d LEFT JOIN items i ON d.icode = i.code WHERE d.slno = ? ORDER BY d.srlno");
        $stmt->execute([$slno]);
        return $stmt->fetchAll();
    }

    /**
     * Load exchange details
     */
    private function loadExchangeDetails(int $slno): array
    {
        $stmt = $this->db->prepare("SELECT * FROM salesexch WHERE slno = ? ORDER BY srlno");
        $stmt->execute([$slno]);
        return $stmt->fetchAll();
    }

    /**
     * Load return details
     */
    private function loadReturnDetails(int $slno): array
    {
        $stmt = $this->db->prepare("SELECT * FROM salesrd WHERE mslno = ? ORDER BY srlno");
        $stmt->execute([$slno]);
        return $stmt->fetchAll();
    }

    // =============================================
    // A4 / LASER PRINT (replaces qtnprint main)
    // =============================================

    private function a4Bill(array $m, array $details, array $exchange, array $returns): string
    {
        $c = $this->config;
        $isGST = ($c['gstEnabled'] === 'Y');
        $billDate = date('d/m/Y', strtotime($m['billdate']));
        $eb = ($m['eb'] === 'B') ? 'TAX INVOICE' : 'ESTIMATE';

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<title>' . $eb . ' - ' . htmlspecialchars($m['billno']) . '</title>';
        $html .= '<style>
            @page { size: A4; margin: 10mm; }
            @media print { body { margin: 0; } .no-print { display: none; } }
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; color: #333; }
            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 10px; }
            .company-name { font-size: 22px; font-weight: bold; color: #1a1a1a; }
            .company-addr { font-size: 11px; color: #555; }
            .gstin-line { font-size: 11px; font-weight: bold; margin-top: 5px; }
            .bill-type { font-size: 16px; font-weight: bold; text-align: center; margin: 8px 0; background: #f0f0f0; padding: 5px; border: 1px solid #999; }
            .bill-info { display: flex; justify-content: space-between; margin-bottom: 10px; }
            .bill-info div { flex: 1; }
            .bill-info .right { text-align: right; }
            table { width: 100%; border-collapse: collapse; margin: 8px 0; }
            th { background: #e8e8e8; border: 1px solid #999; padding: 5px; text-align: center; font-size: 11px; }
            td { border: 1px solid #ccc; padding: 4px 6px; font-size: 11px; }
            td.num { text-align: right; }
            td.center { text-align: center; }
            .totals-section { margin-top: 10px; }
            .totals-table { width: 350px; float: right; }
            .totals-table td { border: none; padding: 3px 8px; }
            .totals-table .label { text-align: right; font-weight: bold; width: 200px; }
            .totals-table .grand { font-size: 14px; border-top: 2px solid #333; }
            .amount-words { clear: both; padding: 8px; background: #f9f9f9; border: 1px solid #ddd; margin: 10px 0; font-style: italic; }
            .payment-section { margin: 10px 0; }
            .exchange-section, .return-section { margin: 10px 0; background: #fafafa; padding: 8px; border: 1px solid #ddd; }
            .section-title { font-weight: bold; font-size: 13px; margin-bottom: 5px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
            .footer { text-align: center; margin-top: 20px; font-size: 10px; color: #777; border-top: 1px solid #ccc; padding-top: 8px; }
            .signature { margin-top: 40px; display: flex; justify-content: space-between; }
            .signature div { text-align: center; border-top: 1px solid #333; padding-top: 5px; width: 200px; }
            .print-btn { background: #0066cc; color: white; border: none; padding: 10px 25px; cursor: pointer; font-size: 14px; margin: 10px; border-radius: 4px; }
            .print-btn:hover { background: #004999; }
        </style></head><body>';

        // Print button
        $html .= '<div class="no-print" style="text-align:center;margin-bottom:15px;">';
        $html .= '<button class="print-btn" onclick="window.print()">Print Bill</button>';
        $html .= '<button class="print-btn" onclick="window.close()">Close</button>';
        $html .= '</div>';

        // Header
        if ($c['printLogo'] === 'Y' && $c['logoPath']) {
            $html .= '<div style="text-align:center"><img src="' . htmlspecialchars($c['logoPath']) . '" height="60"></div>';
        }
        $html .= '<div class="header">';
        $html .= '<div class="company-name">' . htmlspecialchars($c['companyName']) . '</div>';
        $html .= '<div class="company-addr">' . htmlspecialchars($c['companyAddr1']);
        if ($c['companyAddr2']) $html .= ', ' . htmlspecialchars($c['companyAddr2']);
        if ($c['companyPhone']) $html .= ' | Phone: ' . htmlspecialchars($c['companyPhone']);
        $html .= '</div>';
        if ($c['companyGSTIN']) {
            $html .= '<div class="gstin-line">GSTIN: ' . htmlspecialchars($c['companyGSTIN']);
            if ($c['companyState']) $html .= ' | State: ' . htmlspecialchars($c['companyState']);
            $html .= '</div>';
        }
        $html .= '</div>';

        // Bill type heading
        $html .= '<div class="bill-type">' . $eb . '</div>';

        // Bill info & customer
        $html .= '<div class="bill-info">';
        $html .= '<div>';
        $html .= '<strong>Bill No:</strong> ' . htmlspecialchars($m['billno']) . '<br>';
        $html .= '<strong>Date:</strong> ' . $billDate . '<br>';
        if ($m['salesman'] ?? '') $html .= '<strong>Salesman:</strong> ' . htmlspecialchars($m['salesman']) . '<br>';
        $html .= '</div>';
        $html .= '<div class="right">';
        $html .= '<strong>Customer:</strong> ' . htmlspecialchars($m['custname'] ?? 'Cash') . '<br>';
        if ($m['custaddr'] ?? '') $html .= htmlspecialchars($m['custaddr']) . '<br>';
        if ($m['custphone'] ?? '') $html .= '<strong>Phone:</strong> ' . htmlspecialchars($m['custphone']) . '<br>';
        if ($m['custgstin'] ?? '') $html .= '<strong>GSTIN:</strong> ' . htmlspecialchars($m['custgstin']) . '<br>';
        if ($m['statecd'] ?? '') $html .= '<strong>State:</strong> ' . htmlspecialchars($m['statecd']) . '<br>';
        $html .= '</div></div>';

        // Items table
        $html .= '<table><thead><tr>';
        $html .= '<th>SN</th><th>Item</th>';
        if ($c['printHSN'] === 'Y') $html .= '<th>HSN</th>';
        if ($c['printHUID'] === 'Y') $html .= '<th>HUID</th>';
        $html .= '<th>Qty</th><th>Gross Wt</th><th>Stone Wt</th><th>Net Wt</th>';
        if ($c['printWastage'] === 'Y') $html .= '<th>Wst%</th>';
        $html .= '<th>Rate</th>';
        if ($c['printMC'] === 'Y') $html .= '<th>MC</th>';
        if ($c['printStonePrice'] === 'Y') $html .= '<th>St.Price</th>';
        if ($c['printTax'] === 'Y') $html .= '<th>Tax%</th>';
        $html .= '<th>Amount</th></tr></thead><tbody>';

        $totalQty = 0; $totalGross = 0; $totalNet = 0;
        foreach ($details as $i => $d) {
            $html .= '<tr>';
            $html .= '<td class="center">' . ($i + 1) . '</td>';
            $html .= '<td>' . htmlspecialchars($d['iname'] ?? $d['itemname'] ?? $d['icode']) . '</td>';
            if ($c['printHSN'] === 'Y') $html .= '<td class="center">' . htmlspecialchars($d['hsncode'] ?? $d['itemhsn'] ?? '') . '</td>';
            if ($c['printHUID'] === 'Y') $html .= '<td class="center">' . htmlspecialchars($d['huid'] ?? '') . '</td>';
            $html .= '<td class="num">' . ($d['qty'] ?? 1) . '</td>';
            $html .= '<td class="num">' . number_format($d['grosswgt'] ?? 0, 3) . '</td>';
            $html .= '<td class="num">' . number_format($d['stonewgt'] ?? 0, 3) . '</td>';
            $html .= '<td class="num">' . number_format($d['netwgt'] ?? 0, 3) . '</td>';
            if ($c['printWastage'] === 'Y') $html .= '<td class="num">' . number_format($d['wastage'] ?? 0, 2) . '%</td>';
            $html .= '<td class="num">' . number_format($d['rate'] ?? 0, 2) . '</td>';
            if ($c['printMC'] === 'Y') $html .= '<td class="num">' . number_format($d['mc'] ?? 0, 2) . '</td>';
            if ($c['printStonePrice'] === 'Y') $html .= '<td class="num">' . number_format($d['stprice'] ?? 0, 2) . '</td>';
            if ($c['printTax'] === 'Y') $html .= '<td class="num">' . number_format($d['taxperc'] ?? 0, 1) . '%</td>';
            $html .= '<td class="num"><strong>' . number_format($d['amount'] ?? 0, 2) . '</strong></td>';
            $html .= '</tr>';
            $totalQty += ($d['qty'] ?? 1);
            $totalGross += ($d['grosswgt'] ?? 0);
            $totalNet += ($d['netwgt'] ?? 0);
        }

        // Totals row
        $html .= '<tr style="font-weight:bold;background:#f0f0f0"><td colspan="2">Total</td>';
        if ($c['printHSN'] === 'Y') $html .= '<td></td>';
        if ($c['printHUID'] === 'Y') $html .= '<td></td>';
        $html .= '<td class="num">' . $totalQty . '</td>';
        $html .= '<td class="num">' . number_format($totalGross, 3) . '</td><td></td>';
        $html .= '<td class="num">' . number_format($totalNet, 3) . '</td>';
        $colspan = 1;
        if ($c['printWastage'] === 'Y') $colspan++;
        if ($c['printMC'] === 'Y') $colspan++;
        if ($c['printStonePrice'] === 'Y') $colspan++;
        if ($c['printTax'] === 'Y') $colspan++;
        $html .= '<td colspan="' . $colspan . '"></td>';
        $html .= '<td class="num">' . number_format($m['totalamt'] ?? 0, 2) . '</td></tr>';
        $html .= '</tbody></table>';

        // Exchange section
        if ($c['printExchange'] === 'Y' && !empty($exchange)) {
            $html .= $this->renderExchangeSection($m, $exchange);
        }

        // Return section
        if ($c['printSReturn'] === 'Y' && !empty($returns)) {
            $html .= $this->renderReturnSection($returns);
        }

        // Totals section
        $html .= '<div class="totals-section"><table class="totals-table">';
        $html .= '<tr><td class="label">Sub Total:</td><td class="num">' . number_format($m['totalamt'] ?? 0, 2) . '</td></tr>';

        if (($m['discount'] ?? 0) > 0) {
            $html .= '<tr><td class="label">Discount' . (($m['discperc'] ?? 0) > 0 ? ' (' . $m['discperc'] . '%)' : '') . ':</td><td class="num">-' . number_format($m['discount'], 2) . '</td></tr>';
        }

        if ($isGST && $c['printTax'] === 'Y') {
            if (($m['sgst'] ?? 0) > 0) $html .= '<tr><td class="label">SGST:</td><td class="num">' . number_format($m['sgst'], 2) . '</td></tr>';
            if (($m['cgst'] ?? 0) > 0) $html .= '<tr><td class="label">CGST:</td><td class="num">' . number_format($m['cgst'], 2) . '</td></tr>';
            if (($m['igst'] ?? 0) > 0) $html .= '<tr><td class="label">IGST:</td><td class="num">' . number_format($m['igst'], 2) . '</td></tr>';
        }

        if (($m['cess'] ?? 0) > 0) $html .= '<tr><td class="label">Cess:</td><td class="num">' . number_format($m['cess'], 2) . '</td></tr>';
        if (($m['tcs'] ?? 0) > 0) $html .= '<tr><td class="label">TCS:</td><td class="num">' . number_format($m['tcs'], 2) . '</td></tr>';
        if (($m['addless'] ?? 0) != 0) $html .= '<tr><td class="label">Add/Less:</td><td class="num">' . number_format($m['addless'], 2) . '</td></tr>';

        $exchTotal = ($m['exchgoldamt'] ?? 0) + ($m['exchsilveramt'] ?? 0) + ($m['excholdamt'] ?? 0);
        if ($exchTotal > 0) $html .= '<tr><td class="label">Exchange:</td><td class="num">-' . number_format($exchTotal, 2) . '</td></tr>';
        if (($m['sreturnamt'] ?? 0) > 0) $html .= '<tr><td class="label">Sales Return:</td><td class="num">-' . number_format($m['sreturnamt'], 2) . '</td></tr>';
        if (($m['advance'] ?? 0) > 0) $html .= '<tr><td class="label">Advance:</td><td class="num">-' . number_format($m['advance'], 2) . '</td></tr>';
        if (abs($m['roundoff'] ?? 0) > 0) $html .= '<tr><td class="label">Round Off:</td><td class="num">' . number_format($m['roundoff'], 2) . '</td></tr>';

        $html .= '<tr class="grand"><td class="label">GRAND TOTAL:</td><td class="num"><strong>' . number_format($m['grandtotal'] ?? 0, 2) . '</strong></td></tr>';
        $html .= '</table></div>';

        // Amount in words
        $html .= '<div class="amount-words"><strong>Amount in Words:</strong> ' . amountToWords($m['grandtotal'] ?? 0) . '</div>';

        // Payment details
        $html .= '<div class="payment-section">';
        $payParts = [];
        if (($m['cashamt'] ?? 0) > 0) $payParts[] = 'Cash: ' . number_format($m['cashamt'], 2);
        if (($m['bankamt'] ?? 0) > 0) $payParts[] = 'Bank: ' . number_format($m['bankamt'], 2);
        if (($m['cardamt'] ?? 0) > 0) $payParts[] = 'Card: ' . number_format($m['cardamt'], 2);
        if (($m['chequeamt'] ?? 0) > 0) $payParts[] = 'Cheque: ' . number_format($m['chequeamt'], 2);
        if (!empty($payParts)) {
            $html .= '<strong>Payment:</strong> ' . implode(' | ', $payParts);
        }
        $html .= '</div>';

        // Signature
        $html .= '<div class="signature">';
        $html .= '<div>Customer Signature</div>';
        $html .= '<div>For ' . htmlspecialchars($c['companyName']) . '</div>';
        $html .= '</div>';

        // Footer
        if ($c['printTerms']) {
            $html .= '<div class="footer"><strong>Terms & Conditions:</strong> ' . htmlspecialchars($c['printTerms']) . '</div>';
        }
        if ($c['printFooter']) {
            $html .= '<div class="footer">' . htmlspecialchars($c['printFooter']) . '</div>';
        }

        $html .= '</body></html>';
        return $html;
    }

    // =============================================
    // THERMAL PRINT (replaces PB thermal print)
    // =============================================

    private function thermalBill(array $m, array $details, array $exchange, array $returns): string
    {
        $c = $this->config;
        $w = $c['thermalWidth'];
        $billDate = date('d/m/Y', strtotime($m['billdate']));
        $isGST = ($c['gstEnabled'] === 'Y');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Thermal Bill</title>';
        $html .= '<style>
            @page { size: ' . $w . 'mm auto; margin: 2mm; }
            body { font-family: "Courier New", monospace; font-size: 10px; width: ' . $w . 'mm; margin: 0 auto; }
            .center { text-align: center; }
            .right { text-align: right; }
            .bold { font-weight: bold; }
            .line { border-top: 1px dashed #000; margin: 3px 0; }
            .dline { border-top: 2px solid #000; margin: 3px 0; }
            table { width: 100%; border-collapse: collapse; }
            td { padding: 1px 2px; font-size: 9px; }
            .no-print { text-align: center; margin: 10px; }
            @media print { .no-print { display: none; } body { width: auto; } }
        </style></head><body>';

        $html .= '<div class="no-print"><button onclick="window.print()">Print</button></div>';

        // Header
        $html .= '<div class="center bold" style="font-size:14px">' . htmlspecialchars($c['companyName']) . '</div>';
        $html .= '<div class="center">' . htmlspecialchars($c['companyAddr1']) . '</div>';
        if ($c['companyPhone']) $html .= '<div class="center">Ph: ' . htmlspecialchars($c['companyPhone']) . '</div>';
        if ($c['companyGSTIN']) $html .= '<div class="center">GSTIN: ' . htmlspecialchars($c['companyGSTIN']) . '</div>';

        $html .= '<div class="dline"></div>';
        $eb = ($m['eb'] === 'B') ? 'TAX INVOICE' : 'ESTIMATE';
        $html .= '<div class="center bold">' . $eb . '</div>';
        $html .= '<div class="line"></div>';

        // Bill info
        $html .= '<table><tr><td>Bill: ' . htmlspecialchars($m['billno']) . '</td><td class="right">Date: ' . $billDate . '</td></tr></table>';
        if ($m['custname'] ?? '') {
            $html .= '<div>Cust: ' . htmlspecialchars($m['custname']) . '</div>';
            if ($m['custphone'] ?? '') $html .= '<div>Ph: ' . htmlspecialchars($m['custphone']) . '</div>';
        }

        $html .= '<div class="dline"></div>';

        // Items
        foreach ($details as $i => $d) {
            $html .= '<div class="bold">' . ($i + 1) . '. ' . htmlspecialchars($d['iname'] ?? $d['icode']) . '</div>';
            $html .= '<table><tr>';
            $html .= '<td>Wt:' . number_format($d['netwgt'] ?? 0, 3) . 'g</td>';
            $html .= '<td>Rate:' . number_format($d['rate'] ?? 0, 0) . '</td>';
            $html .= '<td class="right bold">' . number_format($d['amount'] ?? 0, 2) . '</td>';
            $html .= '</tr></table>';
            if (($d['mc'] ?? 0) > 0) $html .= '<div style="padding-left:10px">MC: ' . number_format($d['mc'], 2) . '</div>';
        }

        $html .= '<div class="dline"></div>';

        // Totals
        $html .= '<table>';
        $html .= '<tr><td>Sub Total</td><td class="right bold">' . number_format($m['totalamt'] ?? 0, 2) . '</td></tr>';
        if (($m['discount'] ?? 0) > 0) $html .= '<tr><td>Discount</td><td class="right">-' . number_format($m['discount'], 2) . '</td></tr>';
        if ($isGST) {
            if (($m['sgst'] ?? 0) > 0) $html .= '<tr><td>SGST</td><td class="right">' . number_format($m['sgst'], 2) . '</td></tr>';
            if (($m['cgst'] ?? 0) > 0) $html .= '<tr><td>CGST</td><td class="right">' . number_format($m['cgst'], 2) . '</td></tr>';
            if (($m['igst'] ?? 0) > 0) $html .= '<tr><td>IGST</td><td class="right">' . number_format($m['igst'], 2) . '</td></tr>';
        }
        $exchTotal = ($m['exchgoldamt'] ?? 0) + ($m['exchsilveramt'] ?? 0) + ($m['excholdamt'] ?? 0);
        if ($exchTotal > 0) $html .= '<tr><td>Exchange</td><td class="right">-' . number_format($exchTotal, 2) . '</td></tr>';
        if (($m['sreturnamt'] ?? 0) > 0) $html .= '<tr><td>Sales Return</td><td class="right">-' . number_format($m['sreturnamt'], 2) . '</td></tr>';
        if (abs($m['roundoff'] ?? 0) > 0) $html .= '<tr><td>Round Off</td><td class="right">' . number_format($m['roundoff'], 2) . '</td></tr>';
        $html .= '<tr class="bold"><td>TOTAL</td><td class="right" style="font-size:12px">' . number_format($m['grandtotal'] ?? 0, 2) . '</td></tr>';
        $html .= '</table>';

        $html .= '<div class="dline"></div>';
        $html .= '<div class="center">Thank you! Visit again.</div>';
        $html .= '<div class="center" style="font-size:8px">Bill generated on ' . date('d/m/Y H:i') . '</div>';

        $html .= '</body></html>';
        return $html;
    }

    // =============================================
    // DOT MATRIX (simplified - replaces PB dot matrix)
    // =============================================

    private function dotMatrixBill(array $m, array $details, array $exchange, array $returns): string
    {
        // Dot matrix is similar to A4 but with simpler formatting
        return $this->a4Bill($m, $details, $exchange, $returns);
    }

    // =============================================
    // HELPER: Render exchange section
    // =============================================

    private function renderExchangeSection(array $m, array $exchange): string
    {
        $html = '<div class="exchange-section">';
        $html .= '<div class="section-title">Exchange Details (Old Ornaments)</div>';

        // Gold exchange
        if (($m['exchgoldwt'] ?? 0) > 0) {
            $html .= '<table style="width:60%"><tr>';
            $html .= '<td><strong>Old Gold:</strong></td>';
            $html .= '<td>Wt: ' . number_format($m['exchgoldwt'], 3) . 'g</td>';
            $html .= '<td>Touch: ' . number_format($m['exchgoldtouch'], 2) . '%</td>';
            $html .= '<td>Rate: ' . number_format($m['exchgoldrate'], 2) . '</td>';
            $html .= '<td class="num"><strong>' . number_format($m['exchgoldamt'], 2) . '</strong></td>';
            $html .= '</tr></table>';
        }

        // Silver exchange
        if (($m['exchsilverwt'] ?? 0) > 0) {
            $html .= '<table style="width:60%"><tr>';
            $html .= '<td><strong>Old Silver:</strong></td>';
            $html .= '<td>Wt: ' . number_format($m['exchsilverwt'], 3) . 'g</td>';
            $html .= '<td>Touch: ' . number_format($m['exchsilvertouch'], 2) . '%</td>';
            $html .= '<td>Rate: ' . number_format($m['exchsilverrate'], 2) . '</td>';
            $html .= '<td class="num"><strong>' . number_format($m['exchsilveramt'], 2) . '</strong></td>';
            $html .= '</tr></table>';
        }

        // Item-wise exchange
        if (!empty($exchange)) {
            $html .= '<table><thead><tr><th>SN</th><th>Type</th><th>Weight</th><th>Touch</th><th>Rate</th><th>Amount</th></tr></thead><tbody>';
            foreach ($exchange as $i => $ex) {
                $html .= '<tr>';
                $html .= '<td class="center">' . ($i + 1) . '</td>';
                $html .= '<td>' . htmlspecialchars($ex['itype'] ?? '') . '</td>';
                $html .= '<td class="num">' . number_format($ex['weight'] ?? 0, 3) . '</td>';
                $html .= '<td class="num">' . number_format($ex['touch'] ?? 0, 2) . '</td>';
                $html .= '<td class="num">' . number_format($ex['rate'] ?? 0, 2) . '</td>';
                $html .= '<td class="num">' . number_format($ex['amount'] ?? 0, 2) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '</div>';
        return $html;
    }

    // =============================================
    // HELPER: Render return section
    // =============================================

    private function renderReturnSection(array $returns): string
    {
        $html = '<div class="return-section">';
        $html .= '<div class="section-title">Sales Return Details</div>';
        $html .= '<table><thead><tr><th>SN</th><th>Item</th><th>Ref Bill</th><th>Qty</th><th>Weight</th><th>Rate</th><th>Amount</th></tr></thead><tbody>';

        foreach ($returns as $i => $r) {
            $html .= '<tr>';
            $html .= '<td class="center">' . ($i + 1) . '</td>';
            $html .= '<td>' . htmlspecialchars($r['icode'] ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($r['refbillno'] ?? '') . '</td>';
            $html .= '<td class="num">' . ($r['qty'] ?? 1) . '</td>';
            $html .= '<td class="num">' . number_format($r['weight'] ?? 0, 3) . '</td>';
            $html .= '<td class="num">' . number_format($r['rate'] ?? 0, 2) . '</td>';
            $html .= '<td class="num">' . number_format($r['amount'] ?? 0, 2) . '</td>';
            $html .= '</tr>';
        }

        $totalRet = array_sum(array_column($returns, 'amount'));
        $html .= '<tr style="font-weight:bold"><td colspan="6" style="text-align:right">Total Return:</td><td class="num">' . number_format($totalRet, 2) . '</td></tr>';
        $html .= '</tbody></table></div>';
        return $html;
    }
}
