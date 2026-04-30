<?php
/**
 * SalesManager Class
 * Core business logic for jewelry sales - converted from PowerBuilder w_sales
 * Handles: save, balance calc, bill generation, stock updates, exchanges, returns
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

class SalesManager
{
    private PDO $db;
    private array $user;
    private array $rates;
    private array $config;

    // Sale master fields (replaces w_sales instance variables)
    public string $billno = '';
    public string $billdate = '';
    public string $custcode = '';
    public string $custname = '';
    public string $custaddr = '';
    public string $custphone = '';
    public string $custgstin = '';
    public string $statecd = '';
    public string $eb = 'E';          // E=Estimate, B=Bill
    public string $billtype = '';
    public string $salestype = '';
    public string $salesman = '';
    public string $counter = '';
    public string $scheme = '';
    public string $remark = '';
    public string $pandession = '';
    public int    $slno = 0;          // salesm.slno for editing
    public int    $refno = 0;
    public string $stktype = '';

    // Financial totals
    public float $totalamt = 0;
    public float $discount = 0;
    public float $discperc = 0;
    public float $roundoff = 0;
    public float $netamt = 0;
    public float $taxamt = 0;
    public float $sgst = 0;
    public float $cgst = 0;
    public float $igst = 0;
    public float $cess = 0;
    public float $tcs = 0;
    public float $addless = 0;
    public float $advance = 0;
    public float $oldbalance = 0;
    public float $grandtotal = 0;
    public float $cashamt = 0;
    public float $bankamt = 0;
    public float $cardamt = 0;
    public float $chequeamt = 0;
    public float $pdcamt = 0;
    public float $walletamt = 0;
    public float $onlineamt = 0;

    // Exchange fields
    public float $exchgoldwt = 0;
    public float $exchgoldamt = 0;
    public float $exchsilverwt = 0;
    public float $exchsilveramt = 0;
    public float $exchgoldtouch = 0;
    public float $exchsilvertouch = 0;
    public float $exchgoldrate = 0;
    public float $exchsilverrate = 0;
    public float $excholdamt = 0;
    public float $exchangeTotal = 0;

    // Sales return fields
    public float $sreturnamt = 0;

    // Detail items array
    public array $items = [];

    // Exchange detail items
    public array $exchangeItems = [];

    // Return detail items
    public array $returnItems = [];

    // Bank charge
    public float $bankcharge = 0;
    public float $bankchargeperc = 0;

    // Flags
    public bool $isEdit = false;
    public bool $isShortBill = false;
    public bool $isInterstateGST = false;
    public string $taxMode = 'GST';   // GST or VAT
    public bool $taxAfterDiscount = true;

    public function __construct()
    {
        $this->db = getDB();
        $this->user = getCurrentUser();
        $this->rates = getRates();
        $this->loadConfig();
    }

    /**
     * Load configuration settings (replaces multiple ProfileString calls)
     */
    private function loadConfig(): void
    {
        $this->config = [
            'taxAfterDisc'       => profileString('Software', 'TaxAfterDiscount', 'Y'),
            'gstEnabled'         => profileString('Software', 'GSTEnable', 'Y'),
            'igstEnabled'        => profileString('Software', 'IGSTEnable', 'N'),
            'tcsEnabled'         => profileString('Software', 'TCSEnable', 'N'),
            'tcsPerc'            => (float) profileString('Software', 'TCSPerc', '0'),
            'tcsLimit'           => (float) profileString('Software', 'TCSLimit', '1000000'),
            'cessEnabled'        => profileString('Software', 'CessEnable', 'N'),
            'cessPerc'           => (float) profileString('Software', 'CessPerc', '0'),
            'vatEnabled'         => profileString('Software', 'VATEnable', 'N'),
            'billTypeWise'       => profileString('Software', 'BillTypewiseBillNo', 'N'),
            'salesFirstQtn'      => profileString('Software', 'SalesFirstQtnNo', 'N'),
            'autoBillNo'         => profileString('Software', 'AutoBillNo', 'Y'),
            'stkWise'            => profileString('Software', 'StktypeWise', 'N'),
            'barcodeEnabled'     => profileString('Software', 'BarcodeEnabled', 'Y'),
            'multiDB'            => profileString('Software', 'MultiDB', 'N'),
            'exchangeEnabled'    => profileString('Software', 'ExchangeEnable', 'Y'),
            'sreturnEnabled'     => profileString('Software', 'SReturnEnable', 'Y'),
            'prevCardEnabled'    => profileString('Software', 'PrevCardEnable', 'N'),
            'wacEnabled'         => profileString('Software', 'WACEnable', 'N'),
            'defStkType'         => profileString('Software', 'DefStkType', ''),
            'bankChargeEnabled'  => profileString('Software', 'BankChargeEnable', 'N'),
            'autoDiscount'       => profileString('Software', 'AutoDiscount', 'N'),
            'hsnEnabled'         => profileString('Software', 'HSNEnable', 'N'),
            'pointSystem'        => profileString('Software', 'PointSystem', 'N'),
            'diamondEnabled'     => profileString('Software', 'DiamondEnable', 'N'),
            'adjustEnabled'      => profileString('Software', 'AdjustEnable', 'N'),
            'metalRateLock'      => profileString('Software', 'MetalRateLock', 'N'),
            'defTaxPerc'         => (float) profileString('Software', 'DefTaxPerc', '3'),
            'defSGST'           => (float) profileString('Software', 'DefSGST', '1.5'),
            'defCGST'           => (float) profileString('Software', 'DefCGST', '1.5'),
            'defIGST'           => (float) profileString('Software', 'DefIGST', '3'),
            'companyState'       => profileString('Software', 'CompanyState', ''),
        ];

        $this->taxAfterDiscount = ($this->config['taxAfterDisc'] === 'Y');
        $this->taxMode = ($this->config['gstEnabled'] === 'Y') ? 'GST' : (($this->config['vatEnabled'] === 'Y') ? 'VAT' : 'NONE');
    }

    // ========================
    // INITIALIZATION
    // ========================

    /**
     * Initialize new sale module (replaces initsalemodule)
     */
    public function initNewSale(): array
    {
        $this->eb = $this->user['eb'] ?: 'E';
        $this->billdate = date('d/m/Y');
        $this->slno = 0;
        $this->isEdit = false;

        $this->billno = generateBillNo($this->eb, $this->billtype, $this->isShortBill);

        return [
            'billno'   => $this->billno,
            'billdate' => $this->billdate,
            'eb'       => $this->eb,
            'rates'    => $this->rates,
            'config'   => $this->config,
        ];
    }

    // ========================
    // ITEM CALCULATIONS
    // ========================

    /**
     * Calculate line item total (replaces inline PB item calc logic)
     */
    public function calcLineItem(array $item): array
    {
        $netwgt    = (float)($item['netwgt'] ?? 0);
        $grosswgt  = (float)($item['grosswgt'] ?? 0);
        $stonewgt  = (float)($item['stonewgt'] ?? 0);
        $wastage   = (float)($item['wastage'] ?? 0);
        $mc        = (float)($item['mc'] ?? 0);
        $rate      = (float)($item['rate'] ?? 0);
        $qty       = (int)($item['qty'] ?? 1);
        $taxperc   = (float)($item['taxperc'] ?? $this->config['defTaxPerc']);
        $vap       = (float)($item['vap'] ?? 0);
        $stprice   = (float)($item['stprice'] ?? 0);
        $mcrate    = (float)($item['mcrate'] ?? 0);
        $qtype     = $item['qtype'] ?? 'G';
        $dmdamt    = (float)($item['dmdamt'] ?? 0);

        // Net weight = gross - stone
        if ($grosswgt > 0 && $stonewgt > 0) {
            $netwgt = $grosswgt - $stonewgt;
        }

        // Wastage weight
        $wastagewgt = $netwgt * ($wastage / 100);
        $totalwgt = $netwgt + $wastagewgt;

        // Metal value
        $metalvalue = $totalwgt * $rate;

        // Making charge
        if ($mcrate > 0 && $mc == 0) {
            $mc = $totalwgt * $mcrate;
        }

        // Stone price
        $stoneamt = $stprice;

        // VA percentage
        $vaamt = 0;
        if ($vap > 0) {
            $vaamt = $metalvalue * ($vap / 100);
        }

        // Diamond amount
        $diamondamt = $dmdamt;

        // Total amount for this line
        $amount = $metalvalue + $mc + $stoneamt + $vaamt + $diamondamt;

        // Tax calculation
        $linetax = 0;
        $linesgst = 0;
        $linecgst = 0;
        $lineigst = 0;

        if ($taxperc > 0) {
            if ($this->taxMode === 'GST') {
                if ($this->isInterstateGST) {
                    $lineigst = $amount * ($taxperc / 100);
                    $linetax = $lineigst;
                } else {
                    $halfTax = $taxperc / 2;
                    $linesgst = $amount * ($halfTax / 100);
                    $linecgst = $amount * ($halfTax / 100);
                    $linetax = $linesgst + $linecgst;
                }
            } elseif ($this->taxMode === 'VAT') {
                $linetax = $amount * ($taxperc / 100);
            }
        }

        return [
            'netwgt'      => round($netwgt, 3),
            'grosswgt'    => round($grosswgt, 3),
            'stonewgt'    => round($stonewgt, 3),
            'wastagewgt'  => round($wastagewgt, 3),
            'totalwgt'    => round($totalwgt, 3),
            'wastage'     => $wastage,
            'mc'          => round($mc, 2),
            'mcrate'      => $mcrate,
            'rate'        => $rate,
            'qty'         => $qty,
            'stprice'     => round($stprice, 2),
            'vap'         => $vap,
            'vaamt'       => round($vaamt, 2),
            'dmdamt'      => round($diamondamt, 2),
            'metalvalue'  => round($metalvalue, 2),
            'amount'      => round($amount, 2),
            'taxperc'     => $taxperc,
            'linetax'     => round($linetax, 2),
            'linesgst'    => round($linesgst, 2),
            'linecgst'    => round($linecgst, 2),
            'lineigst'    => round($lineigst, 2),
            'qtype'       => $qtype,
        ];
    }

    // ========================
    // BALANCE CALCULATION (replaces balcalc)
    // ========================

    /**
     * Calculate bill totals
     */
    public function balCalc(): array
    {
        $this->totalamt = 0;
        $this->sgst = 0;
        $this->cgst = 0;
        $this->igst = 0;
        $this->taxamt = 0;

        // Sum all detail items
        foreach ($this->items as &$item) {
            $calc = $this->calcLineItem($item);
            $item = array_merge($item, $calc);
            $this->totalamt += $calc['amount'];
            $this->sgst += $calc['linesgst'];
            $this->cgst += $calc['linecgst'];
            $this->igst += $calc['lineigst'];
            $this->taxamt += $calc['linetax'];
        }
        unset($item);

        // Discount
        if ($this->discperc > 0) {
            $this->discount = round($this->totalamt * ($this->discperc / 100), 2);
        }

        // Tax after discount recalculation
        if ($this->taxAfterDiscount && $this->discount > 0) {
            $taxableAmt = $this->totalamt - $this->discount;
            if ($this->isInterstateGST) {
                $this->igst = round($taxableAmt * ($this->config['defIGST'] / 100), 2);
                $this->sgst = 0;
                $this->cgst = 0;
            } else {
                $this->sgst = round($taxableAmt * ($this->config['defSGST'] / 100), 2);
                $this->cgst = round($taxableAmt * ($this->config['defCGST'] / 100), 2);
                $this->igst = 0;
            }
            $this->taxamt = $this->sgst + $this->cgst + $this->igst;
        }

        // Cess
        if ($this->config['cessEnabled'] === 'Y') {
            $this->cess = round(($this->totalamt - $this->discount) * ($this->config['cessPerc'] / 100), 2);
        }

        // Net amount before round-off
        $this->netamt = $this->totalamt - $this->discount + $this->taxamt + $this->cess + $this->addless;

        // Exchange total
        $this->exchangeTotal = $this->exchgoldamt + $this->exchsilveramt + $this->excholdamt;

        // TCS calculation
        if ($this->config['tcsEnabled'] === 'Y') {
            $tcsBase = $this->netamt - $this->exchangeTotal - $this->sreturnamt;
            if ($tcsBase > $this->config['tcsLimit']) {
                $this->tcs = round(($tcsBase - $this->config['tcsLimit']) * ($this->config['tcsPerc'] / 100), 2);
            }
        }

        // Grand total
        $grandBeforeRound = $this->netamt + $this->tcs - $this->exchangeTotal - $this->sreturnamt - $this->advance;
        $this->roundoff = round($grandBeforeRound) - $grandBeforeRound;
        $this->grandtotal = round($grandBeforeRound);

        // Old balance
        if ($this->custcode) {
            $this->oldbalance = acBalance($this->custcode, $this->slno);
        }

        return $this->getTotals();
    }

    /**
     * Get all totals as array
     */
    public function getTotals(): array
    {
        return [
            'totalamt'      => $this->totalamt,
            'discount'      => $this->discount,
            'discperc'      => $this->discperc,
            'taxamt'        => $this->taxamt,
            'sgst'          => $this->sgst,
            'cgst'          => $this->cgst,
            'igst'          => $this->igst,
            'cess'          => $this->cess,
            'tcs'           => $this->tcs,
            'addless'       => $this->addless,
            'netamt'        => $this->netamt,
            'exchangeTotal' => $this->exchangeTotal,
            'exchgoldamt'   => $this->exchgoldamt,
            'exchsilveramt' => $this->exchsilveramt,
            'sreturnamt'    => $this->sreturnamt,
            'advance'       => $this->advance,
            'roundoff'      => $this->roundoff,
            'grandtotal'    => $this->grandtotal,
            'oldbalance'    => $this->oldbalance,
            'bankcharge'    => $this->bankcharge,
            'cashamt'       => $this->cashamt,
            'bankamt'       => $this->bankamt,
            'cardamt'       => $this->cardamt,
            'chequeamt'     => $this->chequeamt,
        ];
    }

    // ========================
    // BARCODE CHECK (replaces bcodechk)
    // ========================

    /**
     * Scan barcode and return item details
     */
    public function scanBarcode(int $bcode): ?array
    {
        $bcData = barcodeCheck($bcode, $this->config['defTaxPerc'], $this->rates['astperc']);
        if (!$bcData) return null;

        // Check if barcode already sold
        $stmt = $this->db->prepare("SELECT COUNT(*) cnt FROM barcode WHERE bcode = ? AND (stk = 'N' OR stk = 'S')");
        $stmt->execute([$bcode]);
        $row = $stmt->fetch();
        if ($row && $row['cnt'] > 0) {
            return ['error' => 'Barcode already sold or not in stock'];
        }

        // Get item info
        $stmt = $this->db->prepare("SELECT * FROM items WHERE code = ?");
        $stmt->execute([trim($bcData['icode'])]);
        $itemInfo = $stmt->fetch();

        if (!$itemInfo) {
            return ['error' => 'Item code not found: ' . $bcData['icode']];
        }

        // Get applicable rate
        $rate = $this->getItemRate($itemInfo['itype'] ?? '', $bcData['qtype'] ?? 'G', $bcData['rate'] ?? 0);

        // Build line item
        $lineItem = [
            'bcode'    => $bcode,
            'icode'    => trim($bcData['icode']),
            'iname'    => $itemInfo['name'] ?? '',
            'itype'    => $itemInfo['itype'] ?? '',
            'hsncode'  => $itemInfo['hsncode'] ?? '',
            'qty'      => max(1, (int)($bcData['qty'] ?? 1)),
            'grosswgt' => (float)($bcData['weight'] ?? 0),
            'stonewgt' => (float)($bcData['stweight'] ?? 0),
            'netwgt'   => (float)($bcData['weight'] ?? 0) - (float)($bcData['stweight'] ?? 0),
            'wastage'  => (float)($bcData['wastage'] ?? 0),
            'mc'       => (float)($bcData['mc'] ?? 0),
            'mcrate'   => (float)($bcData['mcrate'] ?? 0),
            'rate'     => $rate,
            'stprice'  => (float)($bcData['stprice'] ?? 0),
            'vap'      => (float)($bcData['vap'] ?? 0),
            'taxperc'  => (float)($itemInfo['taxinternal'] ?? $this->config['defTaxPerc']),
            'dmdamt'   => (float)($bcData['dmdamt'] ?? 0),
            'dmdwgt'   => (float)($bcData['dmdwgt'] ?? 0),
            'qtype'    => $bcData['qtype'] ?? 'G',
            'touch'    => (float)($bcData['stktouch'] ?? 0),
            'cost'     => (float)($bcData['cost'] ?? 0),
            'costmc'   => (float)($bcData['costmc'] ?? 0),
            'model'    => $bcData['model'] ?? '',
            'huid'     => $bcData['huid'] ?? '',
            'part'     => $bcData['part'] ?? '',
            'stkinnos' => $bcData['stkinnos'] ?? 'N',
        ];

        // Auto-calculate the line
        $lineItem = array_merge($lineItem, $this->calcLineItem($lineItem));

        return $lineItem;
    }

    /**
     * Get rate based on item type & quality
     */
    private function getItemRate(string $itype, string $qtype, float $barcodeRate = 0): float
    {
        if ($barcodeRate > 0 && $this->config['metalRateLock'] !== 'Y') {
            return $barcodeRate;
        }

        $itype = strtoupper(trim($itype));
        switch ($itype) {
            case 'G': case 'GOLD':
                return ($qtype === '18') ? $this->rates['g18rate'] : $this->rates['grate'];
            case 'S': case 'SILVER':
                return $this->rates['srate'];
            case 'P': case 'PLATINUM':
                return $this->rates['prate'];
            case 'J': case 'JEWEL':
                return $this->rates['jrate'];
            default:
                return $barcodeRate ?: $this->rates['grate'];
        }
    }

    // ========================
    // EXCHANGE CALCULATION (replaces exchange logic)
    // ========================

    /**
     * Calculate exchange gold amount
     */
    public function calcExchangeGold(): void
    {
        $this->exchgoldamt = round($this->exchgoldwt * $this->exchgoldtouch / 100 * $this->exchgoldrate, 2);
    }

    /**
     * Calculate exchange silver amount
     */
    public function calcExchangeSilver(): void
    {
        $this->exchsilveramt = round($this->exchsilverwt * $this->exchsilvertouch / 100 * $this->exchsilverrate, 2);
    }

    // ========================
    // BANK CHARGE CALCULATION (replaces calcbcharge)
    // ========================

    /**
     * Calculate bank charge on card payments
     */
    public function calcBankCharge(): void
    {
        if ($this->config['bankChargeEnabled'] === 'Y' && $this->bankchargeperc > 0) {
            $this->bankcharge = round($this->cardamt * ($this->bankchargeperc / 100), 2);
        } else {
            $this->bankcharge = 0;
        }
    }

    // ========================
    // CUSTOMER LOOKUP
    // ========================

    /**
     * Search customer by code or name
     */
    public function getCustomer(string $search): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM clients WHERE code = ? OR name LIKE ? LIMIT 1");
        $stmt->execute([$search, '%' . $search . '%']);
        $cust = $stmt->fetch();

        if ($cust) {
            // Get old balance
            $cust['oldbalance'] = acBalance($cust['code']);
            // Get credit limit info
            $cust['creditlimit'] = (float)($cust['creditlimit'] ?? 0);
        }

        return $cust ?: null;
    }

    /**
     * Search customers list
     */
    public function searchCustomers(string $term): array
    {
        $stmt = $this->db->prepare("SELECT code, name, phone, place, gstin FROM clients WHERE name LIKE ? OR phone LIKE ? OR code LIKE ? ORDER BY name LIMIT 20");
        $like = '%' . $term . '%';
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll();
    }

    // ========================
    // ITEM LOOKUP
    // ========================

    /**
     * Search items
     */
    public function searchItems(string $term): array
    {
        $stmt = $this->db->prepare("SELECT code, name, itype, hsncode, taxinternal FROM items WHERE name LIKE ? OR code LIKE ? ORDER BY name LIMIT 30");
        $like = '%' . $term . '%';
        $stmt->execute([$like, $like]);
        return $stmt->fetchAll();
    }

    /**
     * Get item full details
     */
    public function getItem(string $code): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM items WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }

    // ========================
    // SAVE PROCEDURE (replaces saveproc)
    // ========================

    /**
     * Save sale to database
     */
    public function save(): array
    {
        // Validate
        $errors = $this->validateSale();
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $this->db->beginTransaction();

        try {
            // Recalculate totals
            $this->balCalc();

            if ($this->isEdit && $this->slno > 0) {
                $this->updateExistingSale();
            } else {
                $this->insertNewSale();
            }

            // Save detail items
            $this->saveDetails();

            // Save exchange items
            if ($this->config['exchangeEnabled'] === 'Y') {
                $this->saveExchangeItems();
            }

            // Save return items
            if ($this->config['sreturnEnabled'] === 'Y') {
                $this->saveReturnItems();
            }

            // Update stock
            $this->updateStock();

            // Update barcode status
            $this->updateBarcodeStatus();

            // Create daybook entries
            $this->createDaybookEntries();

            // Update bill counter
            $this->updateBillCounter();

            // Multi-DB sync
            if ($this->config['multiDB'] === 'Y') {
                $this->syncBranchDB();
            }

            // Update customer points
            if ($this->config['pointSystem'] === 'Y') {
                $this->updateCustomerPoints();
            }

            $this->db->commit();

            return [
                'success' => true,
                'slno'    => $this->slno,
                'billno'  => $this->billno,
                'message' => 'Sale saved successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Validate sale data
     */
    private function validateSale(): array
    {
        $errors = [];

        if (empty($this->items)) {
            $errors[] = 'No items added to the sale';
        }

        if (empty($this->billdate)) {
            $errors[] = 'Bill date is required';
        }

        if ($this->eb === 'B' && empty($this->custcode) && empty($this->custname)) {
            // Some config may allow sales without customer for estimate
        }

        // Check payment balance
        $paymentTotal = $this->cashamt + $this->bankamt + $this->cardamt + $this->chequeamt + $this->pdcamt + $this->walletamt + $this->onlineamt;
        $balanceDue = $this->grandtotal - $paymentTotal;

        // Credit limit check
        if ($this->custcode && $balanceDue > 0) {
            $cust = $this->getCustomer($this->custcode);
            if ($cust && $cust['creditlimit'] > 0) {
                $totalDue = $cust['oldbalance'] + $balanceDue;
                if ($totalDue > $cust['creditlimit']) {
                    $errors[] = "Credit limit exceeded. Limit: {$cust['creditlimit']}, Outstanding: " . round($totalDue, 2);
                }
            }
        }

        // Barcode stock check
        foreach ($this->items as $item) {
            if (!empty($item['bcode']) && $this->eb === 'B') {
                $stmt = $this->db->prepare("SELECT stk FROM barcode WHERE bcode = ?");
                $stmt->execute([$item['bcode']]);
                $bc = $stmt->fetch();
                if ($bc && $bc['stk'] !== 'Y' && $bc['stk'] !== 'A') {
                    $errors[] = "Barcode {$item['bcode']} is not in stock";
                }
            }
        }

        return $errors;
    }

    /**
     * Insert new sale master record
     */
    private function insertNewSale(): void
    {
        // Get next slno
        $stmt = $this->db->query("SELECT COALESCE(MAX(slno), 0) + 1 AS next FROM salesm");
        $this->slno = (int) $stmt->fetch()['next'];

        // Convert date
        $dateForDB = $this->convertDate($this->billdate);

        $sql = "INSERT INTO salesm (
            slno, billno, billdate, custcode, custname, custaddr, custphone, custgstin,
            statecd, eb, billtype, salestype, salesman, counter, scheme, remark,
            totalamt, discount, discperc, taxamt, sgst, cgst, igst, cess, tcs,
            addless, netamt, exchgoldwt, exchgoldamt, exchgoldtouch, exchgoldrate,
            exchsilverwt, exchsilveramt, exchsilvertouch, exchsilverrate, excholdamt,
            sreturnamt, advance, roundoff, grandtotal, cashamt, bankamt, cardamt,
            chequeamt, pdcamt, walletamt, onlineamt, bankcharge, pandession,
            stktype, userid, entrydate, refno, status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, NOW(), ?, 'A'
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $this->slno, $this->billno, $dateForDB, $this->custcode, $this->custname,
            $this->custaddr, $this->custphone, $this->custgstin, $this->statecd,
            $this->eb, $this->billtype, $this->salestype, $this->salesman,
            $this->counter, $this->scheme, $this->remark,
            $this->totalamt, $this->discount, $this->discperc, $this->taxamt,
            $this->sgst, $this->cgst, $this->igst, $this->cess, $this->tcs,
            $this->addless, $this->netamt, $this->exchgoldwt, $this->exchgoldamt,
            $this->exchgoldtouch, $this->exchgoldrate,
            $this->exchsilverwt, $this->exchsilveramt, $this->exchsilvertouch,
            $this->exchsilverrate, $this->excholdamt,
            $this->sreturnamt, $this->advance, $this->roundoff, $this->grandtotal,
            $this->cashamt, $this->bankamt, $this->cardamt, $this->chequeamt,
            $this->pdcamt, $this->walletamt, $this->onlineamt, $this->bankcharge,
            $this->pandession, $this->stktype, $this->user['id'], $this->refno,
        ]);
    }

    /**
     * Update existing sale master record
     */
    private function updateExistingSale(): void
    {
        // Reverse old stock first
        $this->reverseOldStock();

        // Reverse old daybook entries
        $this->reverseOldDaybook();

        $dateForDB = $this->convertDate($this->billdate);

        $sql = "UPDATE salesm SET
            billno=?, billdate=?, custcode=?, custname=?, custaddr=?, custphone=?, custgstin=?,
            statecd=?, eb=?, billtype=?, salestype=?, salesman=?, counter=?, scheme=?, remark=?,
            totalamt=?, discount=?, discperc=?, taxamt=?, sgst=?, cgst=?, igst=?, cess=?, tcs=?,
            addless=?, netamt=?, exchgoldwt=?, exchgoldamt=?, exchgoldtouch=?, exchgoldrate=?,
            exchsilverwt=?, exchsilveramt=?, exchsilvertouch=?, exchsilverrate=?, excholdamt=?,
            sreturnamt=?, advance=?, roundoff=?, grandtotal=?, cashamt=?, bankamt=?, cardamt=?,
            chequeamt=?, pdcamt=?, walletamt=?, onlineamt=?, bankcharge=?, pandession=?,
            stktype=?, editdate=NOW(), edituser=?
            WHERE slno=?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $this->billno, $dateForDB, $this->custcode, $this->custname,
            $this->custaddr, $this->custphone, $this->custgstin, $this->statecd,
            $this->eb, $this->billtype, $this->salestype, $this->salesman,
            $this->counter, $this->scheme, $this->remark,
            $this->totalamt, $this->discount, $this->discperc, $this->taxamt,
            $this->sgst, $this->cgst, $this->igst, $this->cess, $this->tcs,
            $this->addless, $this->netamt, $this->exchgoldwt, $this->exchgoldamt,
            $this->exchgoldtouch, $this->exchgoldrate,
            $this->exchsilverwt, $this->exchsilveramt, $this->exchsilvertouch,
            $this->exchsilverrate, $this->excholdamt,
            $this->sreturnamt, $this->advance, $this->roundoff, $this->grandtotal,
            $this->cashamt, $this->bankamt, $this->cardamt, $this->chequeamt,
            $this->pdcamt, $this->walletamt, $this->onlineamt, $this->bankcharge,
            $this->pandession, $this->stktype, $this->user['id'], $this->slno,
        ]);

        // Delete old details
        $this->db->prepare("DELETE FROM salesd WHERE slno = ?")->execute([$this->slno]);
    }

    /**
     * Save detail line items (replaces dw_2 save logic)
     */
    private function saveDetails(): void
    {
        $sql = "INSERT INTO salesd (
            slno, srlno, icode, iname, bcode, qty, grosswgt, stonewgt, netwgt,
            wastage, wastagewgt, totalwgt, rate, mc, mcrate, stprice, vap, vaamt,
            taxperc, taxamt, sgst, cgst, igst, amount, qtype, touch, cost, costmc,
            dmdamt, dmdwgt, model, huid, hsncode, part, stkinnos, stktype
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )";

        $stmt = $this->db->prepare($sql);

        foreach ($this->items as $i => $item) {
            $srlno = $i + 1;
            $stmt->execute([
                $this->slno, $srlno,
                $item['icode'] ?? '', $item['iname'] ?? '', $item['bcode'] ?? 0,
                $item['qty'] ?? 1, $item['grosswgt'] ?? 0, $item['stonewgt'] ?? 0,
                $item['netwgt'] ?? 0, $item['wastage'] ?? 0, $item['wastagewgt'] ?? 0,
                $item['totalwgt'] ?? 0, $item['rate'] ?? 0, $item['mc'] ?? 0,
                $item['mcrate'] ?? 0, $item['stprice'] ?? 0, $item['vap'] ?? 0,
                $item['vaamt'] ?? 0, $item['taxperc'] ?? 0, $item['linetax'] ?? 0,
                $item['linesgst'] ?? 0, $item['linecgst'] ?? 0, $item['lineigst'] ?? 0,
                $item['amount'] ?? 0, $item['qtype'] ?? 'G', $item['touch'] ?? 0,
                $item['cost'] ?? 0, $item['costmc'] ?? 0,
                $item['dmdamt'] ?? 0, $item['dmdwgt'] ?? 0,
                $item['model'] ?? '', $item['huid'] ?? '', $item['hsncode'] ?? '',
                $item['part'] ?? '', $item['stkinnos'] ?? 'N', $this->stktype,
            ]);
        }
    }

    /**
     * Save exchange items
     */
    private function saveExchangeItems(): void
    {
        if (empty($this->exchangeItems)) return;

        // Delete existing
        $this->db->prepare("DELETE FROM salesexch WHERE slno = ?")->execute([$this->slno]);

        $sql = "INSERT INTO salesexch (slno, srlno, itype, weight, touch, rate, amount, icode, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);

        foreach ($this->exchangeItems as $i => $exch) {
            $stmt->execute([
                $this->slno, $i + 1,
                $exch['itype'] ?? 'G', $exch['weight'] ?? 0, $exch['touch'] ?? 0,
                $exch['rate'] ?? 0, $exch['amount'] ?? 0,
                $exch['icode'] ?? '', $exch['remark'] ?? '',
            ]);
        }
    }

    /**
     * Save sales return items
     */
    private function saveReturnItems(): void
    {
        if (empty($this->returnItems)) return;

        // Delete existing
        $this->db->prepare("DELETE FROM salesrd WHERE mslno = ?")->execute([$this->slno]);

        $sql = "INSERT INTO salesrd (mslno, srlno, icode, qty, weight, rate, amount, refbillno, taxperc, taxamt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);

        foreach ($this->returnItems as $i => $ret) {
            $stmt->execute([
                $this->slno, $i + 1,
                $ret['icode'] ?? '', $ret['qty'] ?? 1, $ret['weight'] ?? 0,
                $ret['rate'] ?? 0, $ret['amount'] ?? 0, $ret['refbillno'] ?? '',
                $ret['taxperc'] ?? 0, $ret['taxamt'] ?? 0,
            ]);
        }
    }

    // ========================
    // STOCK UPDATES (replaces PB stock update logic)
    // ========================

    /**
     * Update stock for all sold items
     */
    private function updateStock(): void
    {
        if ($this->eb !== 'B') return; // Only bills update stock

        foreach ($this->items as $item) {
            $icode = $item['icode'] ?? '';
            if (empty($icode)) continue;

            $stktype = $this->stktype ?: ($this->config['defStkType'] ?: 'MAIN');
            $stkinnos = ($item['stkinnos'] ?? 'N') === 'Y';

            checkStkType($icode, $stktype);

            // Update itemsstk: decrease qty and weight
            if ($stkinnos) {
                $sql = "UPDATE itemsstk SET qty = qty - ?, weight = weight - ? WHERE code = ? AND stktype = ?";
                $this->db->prepare($sql)->execute([
                    $item['qty'] ?? 1, $item['netwgt'] ?? 0, $icode, $stktype
                ]);
            } else {
                $sql = "UPDATE itemsstk SET weight = weight - ?, stonewgt = stonewgt - ? WHERE code = ? AND stktype = ?";
                $this->db->prepare($sql)->execute([
                    $item['netwgt'] ?? 0, $item['stonewgt'] ?? 0, $icode, $stktype
                ]);
            }

            // WAC update
            if ($this->config['wacEnabled'] === 'Y') {
                $this->updateWAC($icode, $item);
            }
        }

        // Exchange items add to stock
        foreach ($this->exchangeItems as $exch) {
            if (!empty($exch['icode'])) {
                $stktype = $this->stktype ?: 'MAIN';
                checkStkType($exch['icode'], $stktype);

                $sql = "UPDATE itemsstk SET weight = weight + ? WHERE code = ? AND stktype = ?";
                $this->db->prepare($sql)->execute([
                    $exch['weight'] ?? 0, $exch['icode'], $stktype
                ]);
            }
        }
    }

    /**
     * Update Weighted Average Cost
     */
    private function updateWAC(string $icode, array $item): void
    {
        // WAC = (Old Stock Value + New Purchase Value) / Total Stock Weight
        $stmt = $this->db->prepare("SELECT weight, wac FROM itemsstk WHERE code = ? AND stktype = ? LIMIT 1");
        $stmt->execute([$icode, $this->stktype ?: 'MAIN']);
        $stk = $stmt->fetch();

        if (!$stk) return;

        $currentWt = (float)($stk['weight'] ?? 0);
        $currentWac = (float)($stk['wac'] ?? 0);

        if ($currentWt > 0) {
            // Selling doesn't change WAC typically, but we track it
            $this->db->prepare("UPDATE itemsstk SET wac = ? WHERE code = ? AND stktype = ?")
                ->execute([$currentWac, $icode, $this->stktype ?: 'MAIN']);
        }
    }

    /**
     * Update barcode status to sold
     */
    private function updateBarcodeStatus(): void
    {
        if ($this->eb !== 'B') return;

        foreach ($this->items as $item) {
            if (!empty($item['bcode'])) {
                $this->db->prepare("UPDATE barcode SET stk = 'S', salesslno = ?, salesdate = ? WHERE bcode = ?")
                    ->execute([$this->slno, $this->convertDate($this->billdate), $item['bcode']]);
            }
        }
    }

    /**
     * Reverse old stock before editing (replaces PB reverse logic)
     */
    private function reverseOldStock(): void
    {
        // Get old detail items and reverse their stock changes
        $stmt = $this->db->prepare("SELECT * FROM salesd WHERE slno = ?");
        $stmt->execute([$this->slno]);
        $oldItems = $stmt->fetchAll();

        foreach ($oldItems as $oldItem) {
            $icode = $oldItem['icode'] ?? '';
            if (empty($icode)) continue;

            $stktype = $oldItem['stktype'] ?: ($this->config['defStkType'] ?: 'MAIN');
            checkStkType($icode, $stktype);

            // Reverse: add back sold quantity/weight
            $sql = "UPDATE itemsstk SET weight = weight + ?, stonewgt = stonewgt + ?, qty = qty + ? WHERE code = ? AND stktype = ?";
            $this->db->prepare($sql)->execute([
                $oldItem['netwgt'] ?? 0, $oldItem['stonewgt'] ?? 0, $oldItem['qty'] ?? 0,
                $icode, $stktype
            ]);

            // Reverse barcode
            if (!empty($oldItem['bcode'])) {
                $this->db->prepare("UPDATE barcode SET stk = 'Y', salesslno = 0 WHERE bcode = ?")
                    ->execute([$oldItem['bcode']]);
            }
        }
    }

    // ========================
    // DAYBOOK ENTRIES (replaces PB daybook insert)
    // ========================

    /**
     * Create accounting daybook entries
     */
    private function createDaybookEntries(): void
    {
        if ($this->eb !== 'B') return;

        $dateForDB = $this->convertDate($this->billdate);

        // Sales entry (Credit)
        $salesAc = getGeneralS('SALESAC') ?: 'SALES';
        $this->insertDaybook($salesAc, -$this->netamt, $dateForDB, 'Sales - ' . $this->billno);

        // Customer debit (if credit sale)
        $paymentTotal = $this->cashamt + $this->bankamt + $this->cardamt + $this->chequeamt;
        $balance = $this->grandtotal - $paymentTotal;

        if ($this->custcode && $balance > 0) {
            $this->insertDaybook($this->custcode, $balance, $dateForDB, 'Sales - ' . $this->billno);
        }

        // Cash entry
        if ($this->cashamt > 0) {
            $cashAc = getGeneralS('CASHAC') ?: 'CASH';
            $this->insertDaybook($cashAc, $this->cashamt, $dateForDB, 'Sales Cash - ' . $this->billno);
        }

        // Bank entry
        if ($this->bankamt > 0) {
            $bankAc = getGeneralS('BANKAC') ?: 'BANK';
            $this->insertDaybook($bankAc, $this->bankamt, $dateForDB, 'Sales Bank - ' . $this->billno);
        }

        // Card entry
        if ($this->cardamt > 0) {
            $cardAc = getGeneralS('CARDAC') ?: 'CARD';
            $this->insertDaybook($cardAc, $this->cardamt, $dateForDB, 'Sales Card - ' . $this->billno);
        }

        // Tax entries
        if ($this->sgst > 0) {
            $sgstAc = getGeneralS('SGSTAC') ?: 'SGST';
            $this->insertDaybook($sgstAc, -$this->sgst, $dateForDB, 'SGST - ' . $this->billno);
        }
        if ($this->cgst > 0) {
            $cgstAc = getGeneralS('CGSTAC') ?: 'CGST';
            $this->insertDaybook($cgstAc, -$this->cgst, $dateForDB, 'CGST - ' . $this->billno);
        }
        if ($this->igst > 0) {
            $igstAc = getGeneralS('IGSTAC') ?: 'IGST';
            $this->insertDaybook($igstAc, -$this->igst, $dateForDB, 'IGST - ' . $this->billno);
        }

        // TCS entry
        if ($this->tcs > 0) {
            $tcsAc = getGeneralS('TCSAC') ?: 'TCS';
            $this->insertDaybook($tcsAc, -$this->tcs, $dateForDB, 'TCS - ' . $this->billno);
        }

        // Exchange entry
        if ($this->exchangeTotal > 0) {
            $exchAc = getGeneralS('EXCHAC') ?: 'EXCHANGE';
            $this->insertDaybook($exchAc, $this->exchangeTotal, $dateForDB, 'Exchange - ' . $this->billno);
        }

        // Sales Return entry
        if ($this->sreturnamt > 0) {
            $sretAc = getGeneralS('SRETAC') ?: 'SALESRETURN';
            $this->insertDaybook($sretAc, $this->sreturnamt, $dateForDB, 'Sales Return - ' . $this->billno);
        }

        // Round off
        if (abs($this->roundoff) > 0) {
            $roAc = getGeneralS('ROAC') ?: 'ROUNDOFF';
            $this->insertDaybook($roAc, -$this->roundoff, $dateForDB, 'Round Off - ' . $this->billno);
        }

        // Discount entry
        if ($this->discount > 0) {
            $discAc = getGeneralS('DISCAC') ?: 'DISCOUNT';
            $this->insertDaybook($discAc, $this->discount, $dateForDB, 'Discount - ' . $this->billno);
        }
    }

    /**
     * Insert a daybook entry
     */
    private function insertDaybook(string $accode, float $amount, string $date, string $narration): void
    {
        $stmt = $this->db->query("SELECT COALESCE(MAX(slno), 0) + 1 AS next FROM daybook");
        $nextSlno = (int) $stmt->fetch()['next'];

        $sql = "INSERT INTO daybook (slno, accode, amount, ddate, narration, vtype, vno, userid)
                VALUES (?, ?, ?, ?, ?, 'SL', ?, ?)";
        $this->db->prepare($sql)->execute([
            $nextSlno, $accode, $amount, $date, $narration, $this->slno, $this->user['id']
        ]);
    }

    /**
     * Reverse old daybook entries before edit
     */
    private function reverseOldDaybook(): void
    {
        $this->db->prepare("DELETE FROM daybook WHERE vtype = 'SL' AND vno = ?")->execute([$this->slno]);
    }

    // ========================
    // BILL COUNTER UPDATE
    // ========================

    private function updateBillCounter(): void
    {
        if ($this->isEdit) return;

        if ($this->isShortBill) {
            $code = 'TSALES' . $this->eb;
        } elseif ($this->config['billTypeWise'] === 'Y' && $this->eb === 'B' && $this->billtype) {
            $stmt = $this->db->prepare("SELECT prefix FROM salestype WHERE code = ?");
            $stmt->execute([$this->billtype]);
            $row = $stmt->fetch();
            $code = 'SALES' . ($row ? $row['prefix'] : $this->eb);
        } elseif ($this->config['salesFirstQtn'] === 'Y') {
            $code = 'QTNNO';
        } else {
            $code = 'SALES' . $this->eb;
        }

        incrementGeneralI($code);
    }

    // ========================
    // MULTI-DB SYNC
    // ========================

    private function syncBranchDB(): void
    {
        $db2 = getDB2();
        if (!$db2) return;

        try {
            // Sync stock changes to branch database
            foreach ($this->items as $item) {
                if (empty($item['icode'])) continue;
                $stktype = $this->stktype ?: 'MAIN';

                $stmt = $db2->prepare("UPDATE itemsstk SET weight = weight - ?, qty = qty - ? WHERE code = ? AND stktype = ?");
                $stmt->execute([
                    $item['netwgt'] ?? 0, $item['qty'] ?? 0,
                    $item['icode'], $stktype
                ]);

                // Sync barcode
                if (!empty($item['bcode'])) {
                    $db2->prepare("UPDATE barcode SET stk = 'S' WHERE bcode = ?")->execute([$item['bcode']]);
                }
            }
        } catch (Exception $e) {
            // Log sync error but don't fail the main transaction
            error_log("Branch sync error: " . $e->getMessage());
        }
    }

    // ========================
    // CUSTOMER POINTS
    // ========================

    private function updateCustomerPoints(): void
    {
        if (empty($this->custcode)) return;

        $pointRate = getGeneralD('POINTRATE');
        if ($pointRate <= 0) return;

        $points = floor($this->netamt / $pointRate);
        if ($points > 0) {
            $this->db->prepare("UPDATE clients SET points = points + ? WHERE code = ?")
                ->execute([$points, $this->custcode]);
        }
    }

    // ========================
    // LOAD EXISTING BILL (replaces filldetails)
    // ========================

    /**
     * Load an existing bill for editing
     */
    public function loadBill(int $slno): ?array
    {
        // Load master
        $stmt = $this->db->prepare("SELECT * FROM salesm WHERE slno = ?");
        $stmt->execute([$slno]);
        $master = $stmt->fetch();

        if (!$master) return null;

        $this->slno = $slno;
        $this->isEdit = true;
        $this->billno = $master['billno'] ?? '';
        $this->billdate = date('d/m/Y', strtotime($master['billdate']));
        $this->custcode = $master['custcode'] ?? '';
        $this->custname = $master['custname'] ?? '';
        $this->custaddr = $master['custaddr'] ?? '';
        $this->custphone = $master['custphone'] ?? '';
        $this->custgstin = $master['custgstin'] ?? '';
        $this->statecd = $master['statecd'] ?? '';
        $this->eb = $master['eb'] ?? 'E';
        $this->billtype = $master['billtype'] ?? '';
        $this->salestype = $master['salestype'] ?? '';
        $this->salesman = $master['salesman'] ?? '';
        $this->counter = $master['counter'] ?? '';
        $this->scheme = $master['scheme'] ?? '';
        $this->remark = $master['remark'] ?? '';
        $this->pandession = $master['pandession'] ?? '';
        $this->stktype = $master['stktype'] ?? '';
        $this->totalamt = (float)($master['totalamt'] ?? 0);
        $this->discount = (float)($master['discount'] ?? 0);
        $this->discperc = (float)($master['discperc'] ?? 0);
        $this->taxamt = (float)($master['taxamt'] ?? 0);
        $this->sgst = (float)($master['sgst'] ?? 0);
        $this->cgst = (float)($master['cgst'] ?? 0);
        $this->igst = (float)($master['igst'] ?? 0);
        $this->cess = (float)($master['cess'] ?? 0);
        $this->tcs = (float)($master['tcs'] ?? 0);
        $this->addless = (float)($master['addless'] ?? 0);
        $this->netamt = (float)($master['netamt'] ?? 0);
        $this->exchgoldwt = (float)($master['exchgoldwt'] ?? 0);
        $this->exchgoldamt = (float)($master['exchgoldamt'] ?? 0);
        $this->exchgoldtouch = (float)($master['exchgoldtouch'] ?? 0);
        $this->exchgoldrate = (float)($master['exchgoldrate'] ?? 0);
        $this->exchsilverwt = (float)($master['exchsilverwt'] ?? 0);
        $this->exchsilveramt = (float)($master['exchsilveramt'] ?? 0);
        $this->exchsilvertouch = (float)($master['exchsilvertouch'] ?? 0);
        $this->exchsilverrate = (float)($master['exchsilverrate'] ?? 0);
        $this->excholdamt = (float)($master['excholdamt'] ?? 0);
        $this->sreturnamt = (float)($master['sreturnamt'] ?? 0);
        $this->advance = (float)($master['advance'] ?? 0);
        $this->roundoff = (float)($master['roundoff'] ?? 0);
        $this->grandtotal = (float)($master['grandtotal'] ?? 0);
        $this->cashamt = (float)($master['cashamt'] ?? 0);
        $this->bankamt = (float)($master['bankamt'] ?? 0);
        $this->cardamt = (float)($master['cardamt'] ?? 0);
        $this->chequeamt = (float)($master['chequeamt'] ?? 0);
        $this->pdcamt = (float)($master['pdcamt'] ?? 0);
        $this->walletamt = (float)($master['walletamt'] ?? 0);
        $this->onlineamt = (float)($master['onlineamt'] ?? 0);
        $this->bankcharge = (float)($master['bankcharge'] ?? 0);
        $this->refno = (int)($master['refno'] ?? 0);

        // Load detail items
        $stmt = $this->db->prepare("SELECT d.*, i.name as itemname, i.itype, i.hsncode FROM salesd d LEFT JOIN items i ON d.icode = i.code WHERE d.slno = ? ORDER BY d.srlno");
        $stmt->execute([$slno]);
        $this->items = $stmt->fetchAll();

        // Load exchange items
        $stmt = $this->db->prepare("SELECT * FROM salesexch WHERE slno = ? ORDER BY srlno");
        $stmt->execute([$slno]);
        $this->exchangeItems = $stmt->fetchAll();

        // Load return items
        $stmt = $this->db->prepare("SELECT * FROM salesrd WHERE mslno = ? ORDER BY srlno");
        $stmt->execute([$slno]);
        $this->returnItems = $stmt->fetchAll();

        $this->exchangeTotal = $this->exchgoldamt + $this->exchsilveramt + $this->excholdamt;

        return [
            'master'   => $master,
            'items'    => $this->items,
            'exchange' => $this->exchangeItems,
            'returns'  => $this->returnItems,
        ];
    }

    // ========================
    // DELETE BILL
    // ========================

    /**
     * Delete / cancel a bill
     */
    public function deleteBill(int $slno): array
    {
        $bill = $this->loadBill($slno);
        if (!$bill) {
            return ['success' => false, 'error' => 'Bill not found'];
        }

        $this->db->beginTransaction();
        try {
            // Reverse stock
            $this->reverseOldStock();

            // Reverse daybook
            $this->reverseOldDaybook();

            // Mark as cancelled
            $this->db->prepare("UPDATE salesm SET status = 'C', editdate = NOW(), edituser = ? WHERE slno = ?")
                ->execute([$this->user['id'], $slno]);

            $this->db->commit();
            return ['success' => true, 'message' => 'Bill cancelled successfully'];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ========================
    // VA CALCULATION (replaces evacalc)
    // ========================

    /**
     * Calculate value addition percentage
     */
    public function vaCalc(float $metalvalue, float $mc, float $wastageAmt): float
    {
        if ($metalvalue <= 0) return 0;
        return round(($mc + $wastageAmt) / $metalvalue * 100, 2);
    }

    // ========================
    // UTILITY METHODS
    // ========================

    private function convertDate(string $date): string
    {
        $d = DateTime::createFromFormat('d/m/Y', $date);
        return $d ? $d->format('Y-m-d') : date('Y-m-d');
    }

    /**
     * Get salestype list for dropdown
     */
    public function getSalesTypes(): array
    {
        return $this->db->query("SELECT code, name, prefix FROM salestype ORDER BY name")->fetchAll();
    }

    /**
     * Get salesman list
     */
    public function getSalesmen(): array
    {
        return $this->db->query("SELECT code, name FROM salesman ORDER BY name")->fetchAll();
    }

    /**
     * Get counter list
     */
    public function getCounters(): array
    {
        return $this->db->query("SELECT code, name FROM counter ORDER BY name")->fetchAll();
    }

    /**
     * Get scheme list
     */
    public function getSchemes(): array
    {
        return $this->db->query("SELECT code, name FROM scheme ORDER BY name")->fetchAll();
    }

    /**
     * Get stock types
     */
    public function getStockTypes(): array
    {
        return $this->db->query("SELECT DISTINCT stktype FROM itemsstk ORDER BY stktype")->fetchAll();
    }

    /**
     * Get states for GST
     */
    public function getStates(): array
    {
        return $this->db->query("SELECT code, name FROM states ORDER BY name")->fetchAll();
    }

    /**
     * Search bills for editing
     */
    public function searchBills(string $term, string $eb = ''): array
    {
        $sql = "SELECT slno, billno, billdate, custname, grandtotal, eb, status FROM salesm WHERE (billno LIKE ? OR custname LIKE ?)";
        $params = ['%' . $term . '%', '%' . $term . '%'];

        if ($eb) {
            $sql .= " AND eb = ?";
            $params[] = $eb;
        }

        $sql .= " ORDER BY slno DESC LIMIT 50";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
