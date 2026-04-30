<?php
/**
 * Sales AJAX API
 * All AJAX endpoints for the sales window
 * Replaces PowerBuilder event handlers with JSON API
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../classes/SalesManager.php';
require_once __DIR__ . '/../classes/PrintManager.php';

header('Content-Type: application/json');

$action = inputStr('action');
$sm = new SalesManager();

try {
    switch ($action) {

        // ========================
        // INIT NEW SALE
        // ========================
        case 'init':
            jsonResponse($sm->initNewSale());
            break;

        // ========================
        // SCAN BARCODE (replaces bcodechk)
        // ========================
        case 'scan_barcode':
            $bcode = inputInt('bcode');
            if ($bcode <= 0) {
                jsonResponse(['error' => 'Invalid barcode'], 400);
            }
            $result = $sm->scanBarcode($bcode);
            if (!$result) {
                jsonResponse(['error' => 'Barcode not found'], 404);
            }
            if (isset($result['error'])) {
                jsonResponse(['error' => $result['error']], 400);
            }
            jsonResponse($result);
            break;

        // ========================
        // CALCULATE LINE ITEM
        // ========================
        case 'calc_item':
            $item = [
                'netwgt'   => inputFloat('netwgt'),
                'grosswgt' => inputFloat('grosswgt'),
                'stonewgt' => inputFloat('stonewgt'),
                'wastage'  => inputFloat('wastage'),
                'mc'       => inputFloat('mc'),
                'mcrate'   => inputFloat('mcrate'),
                'rate'     => inputFloat('rate'),
                'qty'      => inputInt('qty', 1),
                'taxperc'  => inputFloat('taxperc'),
                'vap'      => inputFloat('vap'),
                'stprice'  => inputFloat('stprice'),
                'dmdamt'   => inputFloat('dmdamt'),
                'qtype'    => inputStr('qtype', 'G'),
            ];
            jsonResponse($sm->calcLineItem($item));
            break;

        // ========================
        // CALCULATE TOTALS (replaces balcalc)
        // ========================
        case 'calc_totals':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                jsonResponse(['error' => 'Invalid data'], 400);
            }

            $sm->items = $data['items'] ?? [];
            $sm->discount = (float)($data['discount'] ?? 0);
            $sm->discperc = (float)($data['discperc'] ?? 0);
            $sm->addless = (float)($data['addless'] ?? 0);
            $sm->advance = (float)($data['advance'] ?? 0);
            $sm->exchgoldwt = (float)($data['exchgoldwt'] ?? 0);
            $sm->exchgoldamt = (float)($data['exchgoldamt'] ?? 0);
            $sm->exchsilveramt = (float)($data['exchsilveramt'] ?? 0);
            $sm->excholdamt = (float)($data['excholdamt'] ?? 0);
            $sm->sreturnamt = (float)($data['sreturnamt'] ?? 0);
            $sm->cashamt = (float)($data['cashamt'] ?? 0);
            $sm->bankamt = (float)($data['bankamt'] ?? 0);
            $sm->cardamt = (float)($data['cardamt'] ?? 0);
            $sm->chequeamt = (float)($data['chequeamt'] ?? 0);
            $sm->isInterstateGST = (bool)($data['isInterstate'] ?? false);
            $sm->custcode = $data['custcode'] ?? '';

            jsonResponse($sm->balCalc());
            break;

        // ========================
        // SAVE SALE (replaces saveproc)
        // ========================
        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                jsonResponse(['error' => 'Invalid data'], 400);
            }

            // Populate SalesManager from submitted data
            $sm->billno       = $data['billno'] ?? '';
            $sm->billdate     = $data['billdate'] ?? date('d/m/Y');
            $sm->custcode     = $data['custcode'] ?? '';
            $sm->custname     = $data['custname'] ?? '';
            $sm->custaddr     = $data['custaddr'] ?? '';
            $sm->custphone    = $data['custphone'] ?? '';
            $sm->custgstin    = $data['custgstin'] ?? '';
            $sm->statecd      = $data['statecd'] ?? '';
            $sm->eb           = $data['eb'] ?? 'E';
            $sm->billtype     = $data['billtype'] ?? '';
            $sm->salestype    = $data['salestype'] ?? '';
            $sm->salesman     = $data['salesman'] ?? '';
            $sm->counter      = $data['counter'] ?? '';
            $sm->scheme       = $data['scheme'] ?? '';
            $sm->remark       = $data['remark'] ?? '';
            $sm->pandession   = $data['pandession'] ?? '';
            $sm->stktype      = $data['stktype'] ?? '';

            // Financial
            $sm->discount     = (float)($data['discount'] ?? 0);
            $sm->discperc     = (float)($data['discperc'] ?? 0);
            $sm->addless      = (float)($data['addless'] ?? 0);
            $sm->advance      = (float)($data['advance'] ?? 0);

            // Exchange
            $sm->exchgoldwt      = (float)($data['exchgoldwt'] ?? 0);
            $sm->exchgoldamt     = (float)($data['exchgoldamt'] ?? 0);
            $sm->exchgoldtouch   = (float)($data['exchgoldtouch'] ?? 0);
            $sm->exchgoldrate    = (float)($data['exchgoldrate'] ?? 0);
            $sm->exchsilverwt    = (float)($data['exchsilverwt'] ?? 0);
            $sm->exchsilveramt   = (float)($data['exchsilveramt'] ?? 0);
            $sm->exchsilvertouch = (float)($data['exchsilvertouch'] ?? 0);
            $sm->exchsilverrate  = (float)($data['exchsilverrate'] ?? 0);
            $sm->excholdamt      = (float)($data['excholdamt'] ?? 0);

            // Return
            $sm->sreturnamt   = (float)($data['sreturnamt'] ?? 0);

            // Payment
            $sm->cashamt      = (float)($data['cashamt'] ?? 0);
            $sm->bankamt      = (float)($data['bankamt'] ?? 0);
            $sm->cardamt      = (float)($data['cardamt'] ?? 0);
            $sm->chequeamt    = (float)($data['chequeamt'] ?? 0);
            $sm->pdcamt       = (float)($data['pdcamt'] ?? 0);
            $sm->walletamt    = (float)($data['walletamt'] ?? 0);
            $sm->onlineamt    = (float)($data['onlineamt'] ?? 0);
            $sm->bankcharge   = (float)($data['bankcharge'] ?? 0);

            // Items
            $sm->items          = $data['items'] ?? [];
            $sm->exchangeItems  = $data['exchangeItems'] ?? [];
            $sm->returnItems    = $data['returnItems'] ?? [];

            // Edit mode
            if (!empty($data['slno'])) {
                $sm->slno = (int) $data['slno'];
                $sm->isEdit = true;
            }

            $sm->isInterstateGST = (bool)($data['isInterstate'] ?? false);

            $result = $sm->save();
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;

        // ========================
        // LOAD BILL (replaces filldetails)
        // ========================
        case 'load_bill':
            $slno = inputInt('slno');
            if ($slno <= 0) {
                jsonResponse(['error' => 'Invalid slno'], 400);
            }
            $bill = $sm->loadBill($slno);
            if (!$bill) {
                jsonResponse(['error' => 'Bill not found'], 404);
            }
            jsonResponse($bill);
            break;

        // ========================
        // DELETE / CANCEL BILL
        // ========================
        case 'delete_bill':
            $slno = inputInt('slno');
            $result = $sm->deleteBill($slno);
            jsonResponse($result, $result['success'] ? 200 : 400);
            break;

        // ========================
        // SEARCH CUSTOMERS
        // ========================
        case 'search_customer':
            $term = inputStr('term');
            $results = $sm->searchCustomers($term);
            jsonResponse($results);
            break;

        // ========================
        // GET CUSTOMER DETAILS
        // ========================
        case 'get_customer':
            $code = inputStr('code');
            $cust = $sm->getCustomer($code);
            if (!$cust) {
                jsonResponse(['error' => 'Customer not found'], 404);
            }
            jsonResponse($cust);
            break;

        // ========================
        // SEARCH ITEMS
        // ========================
        case 'search_items':
            $term = inputStr('term');
            jsonResponse($sm->searchItems($term));
            break;

        // ========================
        // GET ITEM DETAILS
        // ========================
        case 'get_item':
            $code = inputStr('code');
            $item = $sm->getItem($code);
            if (!$item) {
                jsonResponse(['error' => 'Item not found'], 404);
            }
            jsonResponse($item);
            break;

        // ========================
        // SEARCH BILLS
        // ========================
        case 'search_bills':
            $term = inputStr('term');
            $eb = inputStr('eb');
            jsonResponse($sm->searchBills($term, $eb));
            break;

        // ========================
        // GET DROPDOWNS
        // ========================
        case 'get_dropdowns':
            jsonResponse([
                'salesTypes'  => $sm->getSalesTypes(),
                'salesmen'    => $sm->getSalesmen(),
                'counters'    => $sm->getCounters(),
                'schemes'     => $sm->getSchemes(),
                'stockTypes'  => $sm->getStockTypes(),
                'states'      => $sm->getStates(),
            ]);
            break;

        // ========================
        // EXCHANGE CALC
        // ========================
        case 'calc_exchange':
            $type = inputStr('type'); // gold or silver
            if ($type === 'gold') {
                $sm->exchgoldwt    = inputFloat('weight');
                $sm->exchgoldtouch = inputFloat('touch');
                $sm->exchgoldrate  = inputFloat('rate');
                $sm->calcExchangeGold();
                jsonResponse(['amount' => $sm->exchgoldamt]);
            } else {
                $sm->exchsilverwt    = inputFloat('weight');
                $sm->exchsilvertouch = inputFloat('touch');
                $sm->exchsilverrate  = inputFloat('rate');
                $sm->calcExchangeSilver();
                jsonResponse(['amount' => $sm->exchsilveramt]);
            }
            break;

        // ========================
        // BANK CHARGE CALC
        // ========================
        case 'calc_bankcharge':
            $sm->cardamt = inputFloat('cardamt');
            $sm->bankchargeperc = inputFloat('perc');
            $sm->calcBankCharge();
            jsonResponse(['bankcharge' => $sm->bankcharge]);
            break;

        // ========================
        // PRINT BILL
        // ========================
        case 'print_bill':
            $slno = inputInt('slno');
            $pm = new PrintManager();
            $html = $pm->generateBillHTML($slno);
            header('Content-Type: text/html');
            echo $html;
            exit;

        // ========================
        // GET RATES
        // ========================
        case 'get_rates':
            jsonResponse(getRates());
            break;

        default:
            jsonResponse(['error' => 'Unknown action: ' . $action], 400);
    }

} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
