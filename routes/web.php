<?php

use App\Http\Controllers\AccountMasterController;
use App\Http\Controllers\AccountLedgerController;
use App\Http\Controllers\DayBookController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\BillPrefixController;
use App\Http\Controllers\BarcodeEntryController;
use App\Http\Controllers\BarcodeMultiEntryController;
use App\Http\Controllers\BarcodeSettingsController;
use App\Http\Controllers\CountersController;
use App\Http\Controllers\CashBookController;
use App\Http\Controllers\CashBalanceController;
use App\Http\Controllers\CompanySelectController;
use App\Http\Controllers\BankBookController;
use App\Http\Controllers\YearlyCashBalanceController;
use App\Http\Controllers\PdcReportController;
use App\Http\Controllers\JournalReportController;
use App\Http\Controllers\AllTransReportController;
use App\Http\Controllers\ChartOfAccountsController;
use App\Http\Controllers\NonTransactionalDaysReportController;
use App\Http\Controllers\AcReceivablePayableSummaryController;
use App\Http\Controllers\GroupAccountSummaryController;
use App\Http\Controllers\GroupwiseExpandedListController;
use App\Http\Controllers\ReceiptPaymentReportController;
use App\Http\Controllers\CustomerOpBillsController;
use App\Http\Controllers\CustomerPopupController;
use App\Http\Controllers\DenominationMasterController;
use App\Http\Controllers\GiftTableController;
use App\Http\Controllers\ItemGroupController;
use App\Http\Controllers\ItemHelpController;
use App\Http\Controllers\ItemMasterController;
use App\Http\Controllers\ItemPurityTypeController;
use App\Http\Controllers\ItemSubGroupController;
use App\Http\Controllers\ItemTempController;
use App\Http\Controllers\MCTableController;
use App\Http\Controllers\CounterIssueController;
use App\Http\Controllers\StockVerificationController;
use App\Http\Controllers\StaffTransactionController;
use App\Http\Controllers\StaffLogUpdateController;
use App\Http\Controllers\StaffLeaveEntryController;
use App\Http\Controllers\StaffWgtTransactionController;
use App\Http\Controllers\ExpenseVoucherEntryController;
use App\Http\Controllers\SupplierBillwisePaymentController;
use App\Http\Controllers\RateDiffAdjustmentController;
use App\Http\Controllers\SuspenseLedgerController;
use App\Http\Controllers\GroupAmtAllocationController;
use App\Http\Controllers\GroupLedgerController;
use App\Http\Controllers\ChangeDuedateController;
use App\Http\Controllers\AccountRestartDateController;
use App\Http\Controllers\AmtWgtTransferController;
use App\Http\Controllers\PaymentConfirmationController;
use App\Http\Controllers\DenominationEntryController;
use App\Http\Controllers\PurchaseBillConfirmationController;
use App\Http\Controllers\SalesBillConfirmationController;
use App\Http\Controllers\StockSummaryCostWiseController;
use App\Http\Controllers\StockSummaryRateWiseController;
use App\Http\Controllers\ModelMasterController;
use App\Http\Controllers\NativeAuthController;
use App\Http\Controllers\NativeCustomerController;
use App\Http\Controllers\NativeDashboardController;
use App\Http\Controllers\NativeStatesController;
use App\Http\Controllers\OtherItemsController;
use App\Http\Controllers\PartyMCTableController;
use App\Http\Controllers\PointCardController;
use App\Http\Controllers\PhoneBookController;
use App\Http\Controllers\YearEndAccountCloseController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\RegionalHelpController;
use App\Http\Controllers\ReorderController;
use App\Http\Controllers\RepairComplaintsController;
use App\Http\Controllers\RepairReceiptMemoPartyController;
use App\Http\Controllers\RepairReturnController;
use App\Http\Controllers\SalesBillController;
use App\Http\Controllers\SalesBillPrintController;
use App\Http\Controllers\SalesBookReportController;
use App\Http\Controllers\SmithController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockPeriodLedgerController;
use App\Http\Controllers\StockTypeController;
use App\Http\Controllers\PurchaseBillController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\DiamondPurchaseBillController;
use App\Http\Controllers\DiamondPurchaseReturnController;
use App\Http\Controllers\GoldsmithTransactionController;
use App\Http\Controllers\GoldsmithNewWorkNoteController;
use App\Http\Controllers\GoldRateStoryController;
use App\Http\Controllers\ItemAdjustmentController;
use App\Http\Controllers\ItemMovementController;
use App\Http\Controllers\ItemwiseProfitController;
use App\Http\Controllers\ItemReportsController;
use App\Http\Controllers\OrderBillController;
use App\Http\Controllers\OrderUpdateController;
use App\Http\Controllers\OrderBlockController;
use App\Http\Controllers\OrderAdvanceAfterController;
use App\Http\Controllers\OrderSaleController;
use App\Http\Controllers\OrderRateFixController;
use App\Http\Controllers\OrderReprintController;
use App\Http\Controllers\OrderCancelController;
use App\Http\Controllers\OrderAdvanceReportController;
use App\Http\Controllers\OrderEnquiryController;
use App\Http\Controllers\OrderEntryReportController;
use App\Http\Controllers\OrderPendingDetailsController;
use App\Http\Controllers\OrderPendingRegisterController;
use App\Http\Controllers\OrderProfitAnalysisController;
use App\Http\Controllers\OrderReturnsController;
use App\Http\Controllers\OrderSampleStockController;
use App\Http\Controllers\OrderNosListController;
use App\Http\Controllers\OrderProcessController;
use App\Http\Controllers\OrderSendMailController;
use App\Http\Controllers\BarcodeListController;
use App\Http\Controllers\BarcodeStockVerifyController;
use App\Http\Controllers\BarcodeSamtListController;
use App\Http\Controllers\BarcodeHistoryController;
use App\Http\Controllers\BarcodeStockListController;
use App\Http\Controllers\BarcodeEntryComparisonController;
use App\Http\Controllers\DiamondStoneStockController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\RefineryBillController;
use App\Http\Controllers\RefineryReportController;
use App\Http\Controllers\DepositReportController;
use App\Http\Controllers\OtherItemReportController;
use App\Http\Controllers\RemakeReportController;
use App\Http\Controllers\ApplicationSettingsController;
use App\Http\Controllers\CustomerBillwiseRcptController;
use App\Http\Controllers\CustomerReportsController;
use App\Http\Controllers\SupplierReportsController;
use App\Http\Controllers\CustomerCampaignController;
use App\Http\Controllers\DebitCreditNoteController;
use App\Http\Controllers\UserAccessController;
use App\Http\Controllers\WastageTableController;
use App\Http\Controllers\WgtRcptPmntController;
use App\Http\Controllers\PartyOpWeightController;
use App\Http\Controllers\DepositorsIntPostController;
use App\Http\Controllers\KuriIntPostController;
use App\Http\Controllers\KuriFinishController;
use App\Http\Controllers\OtherItemTransController;
use App\Http\Controllers\StockSuspenseEntryController;
use App\Http\Controllers\PurityTestingController;
use App\Http\Controllers\PurityCertificateController;
use App\Http\Controllers\RuffWorkController;
use App\Http\Controllers\CountryCurrencyController;
use App\Http\Controllers\AiInsightsController;
use App\Http\Controllers\StaffReportsController;
use App\Http\Controllers\TermSummaryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth & Dashboard
|--------------------------------------------------------------------------
*/
Route::get('/', function (Request $request) {
    return redirect()->away(rtrim($request->root(), '/') . '/login');
});
Route::get('/company-select', [CompanySelectController::class, 'index']);
Route::post('/company-select/save', [CompanySelectController::class, 'save']);
Route::post('/company-select/delete', [CompanySelectController::class, 'delete']);
Route::post('/company-select/switch', [CompanySelectController::class, 'switch']);
Route::post('/company-select/set-default', [CompanySelectController::class, 'setDefault']);
Route::get('/company-select/close', [CompanySelectController::class, 'close']);

/*
|--------------------------------------------------------------------------
| Application Settings
|--------------------------------------------------------------------------
*/
Route::get('/application-settings', [ApplicationSettingsController::class, 'index']);
Route::get('/api/application-settings/load', [ApplicationSettingsController::class, 'load']);
Route::post('/api/application-settings/save', [ApplicationSettingsController::class, 'save']);
Route::post('/api/application-settings/upload-logo', [ApplicationSettingsController::class, 'uploadLogo']);
Route::post('/api/application-settings/remove-logo', [ApplicationSettingsController::class, 'removeLogo']);
/*
|--------------------------------------------------------------------------
| Country / Currency / Religion Settings
|--------------------------------------------------------------------------
*/
Route::get('/country-currency', [CountryCurrencyController::class, 'index']);
Route::get('/api/country-currency/load', [CountryCurrencyController::class, 'load']);
Route::post('/api/country-currency/save', [CountryCurrencyController::class, 'save']);
Route::get('/api/country-currency/config', [CountryCurrencyController::class, 'configApi']);
/*
|--------------------------------------------------------------------------
| AI Insights
|--------------------------------------------------------------------------
*/
Route::get('/ai-insights', [AiInsightsController::class, 'index']);
Route::get('/api/ai/fraud-alerts', [AiInsightsController::class, 'fraudAlerts']);
Route::get('/api/ai/customer-analytics', [AiInsightsController::class, 'customerAnalytics']);
Route::get('/api/ai/inventory-prediction', [AiInsightsController::class, 'inventoryPrediction']);
Route::get('/api/ai/business-insights', [AiInsightsController::class, 'businessInsights']);
Route::get('/api/ai/sales-cashflow-forecast', [AiInsightsController::class, 'salesCashFlowForecast']);
Route::get('/api/ai/sales-assistant', [AiInsightsController::class, 'salesAssistant']);
Route::get('/api/ai/order-predictions', [AiInsightsController::class, 'orderPredictions']);
Route::get('/api/ai/customer-followup', [AiInsightsController::class, 'customerFollowup']);
Route::get('/api/ai/stock-recommendations', [AiInsightsController::class, 'stockRecommendations']);
Route::post('/api/ai/report-assistant', [AiInsightsController::class, 'reportAssistant']);
Route::post('/api/ai/chatbot', [AiInsightsController::class, 'chatbot']);
/*
|--------------------------------------------------------------------------
| Staff Reports
|--------------------------------------------------------------------------
*/
Route::get('/staff-reports',                         [StaffReportsController::class, 'index']);
Route::get('/api/staff-reports/salesman-book',       [StaffReportsController::class, 'salesmanBook']);
Route::get('/api/staff-reports/salesman-summary',    [StaffReportsController::class, 'salesmanSummary']);
Route::get('/api/staff-reports/counter-sales',       [StaffReportsController::class, 'counterSales']);
Route::get('/api/staff-reports/incharge-report',     [StaffReportsController::class, 'inchargeReport']);
Route::get('/api/staff-reports/commission-report',   [StaffReportsController::class, 'commissionReport']);
Route::get('/api/staff-reports/performance',         [StaffReportsController::class, 'performance']);
Route::get('/api/staff-reports/term-summary',        [StaffReportsController::class, 'termSummary']);
Route::get('/api/staff-reports/attendance',          [StaffReportsController::class, 'attendanceReport']);
Route::get('/api/staff-reports/leave',               [StaffReportsController::class, 'leaveReport']);
Route::get('/api/staff-reports/staff-log',           [StaffReportsController::class, 'staffLog']);
Route::get('/api/staff-reports/ledger',              [StaffReportsController::class, 'ledger']);
Route::get('/api/staff-reports/wgt-trans',           [StaffReportsController::class, 'wgtTrans']);
Route::get('/term-summary', [TermSummaryController::class, 'index']);
Route::get('/api/term-summary', [TermSummaryController::class, 'data']);

Route::get('/administration', [AdministrationController::class, 'index']);
Route::get('/administration/initialise', [AdministrationController::class, 'initialise']);
Route::get('/administration/initialise-docno', [AdministrationController::class, 'initialiseDocNo']);
Route::get('/administration/add-slno', [AdministrationController::class, 'addSlno']);
Route::get('/administration/sql-update', [AdministrationController::class, 'sqlUpdate']);
Route::get('/administration/stock-update', [AdministrationController::class, 'stockUpdate']);
Route::get('/administration/data-transfer', [AdministrationController::class, 'dataTransfer']);
Route::get('/administration/update-log', [AdministrationController::class, 'updateLog']);
Route::get('/administration/change-docno', [AdministrationController::class, 'changeDocNo']);
Route::get('/administration/rearrange-docnos', [AdministrationController::class, 'rearrangeDocNos']);
Route::post('/api/administration/initialise', [AdministrationController::class, 'runInitialise']);
Route::post('/api/administration/initialise-docno', [AdministrationController::class, 'runInitialiseDocNo']);
Route::post('/api/administration/add-slno', [AdministrationController::class, 'runAddSlno']);
Route::post('/api/administration/sql-update', [AdministrationController::class, 'runSqlUpdate']);
Route::post('/api/administration/data-transfer/preview', [AdministrationController::class, 'dataTransferPreview']);
Route::post('/api/administration/data-transfer/run', [AdministrationController::class, 'dataTransferRun']);
Route::get('/api/administration/update-log', [AdministrationController::class, 'updateLogData']);
Route::post('/api/administration/update-log/clear', [AdministrationController::class, 'clearUpdateLog']);
Route::post('/api/administration/change-docno', [AdministrationController::class, 'runChangeDocNo']);
Route::post('/api/administration/rearrange-docnos', [AdministrationController::class, 'runRearrangeDocNos']);
Route::get('/customer-campaign', [CustomerCampaignController::class, 'index']);
Route::get('/api/customer-campaign/recipients', [CustomerCampaignController::class, 'recipients']);
Route::get('/phone-book', [PhoneBookController::class, 'index']);
Route::get('/api/phone-book/contacts', [PhoneBookController::class, 'contacts']);
Route::get('/cash-balance', [CashBalanceController::class, 'index']);
Route::get('/year-end-account-close', [YearEndAccountCloseController::class, 'index']);
Route::post('/api/year-end-account-close/init-doc-nos', [YearEndAccountCloseController::class, 'initDocNos']);
Route::post('/api/year-end-account-close/close', [YearEndAccountCloseController::class, 'closeAccounts']);

/*
|--------------------------------------------------------------------------
| Customer Billwise Receipt
|--------------------------------------------------------------------------
*/
Route::get('/accounts/customer-billwise-rcpt', [CustomerBillwiseRcptController::class, 'index']);
Route::post('/api/accounts/customer-billwise-rcpt', [CustomerBillwiseRcptController::class, 'api']);

Route::get('/accounts/supplier-billwise-payment', [SupplierBillwisePaymentController::class, 'index']);
Route::post('/api/accounts/supplier-billwise-payment', [SupplierBillwisePaymentController::class, 'api']);

/*
|--------------------------------------------------------------------------
| Debit / Credit Note
|--------------------------------------------------------------------------
*/
Route::get('/accounts/debit-credit-note', [DebitCreditNoteController::class, 'index']);
Route::post('/api/accounts/debit-credit-note', [DebitCreditNoteController::class, 'api']);

Route::get('/accounts/rate-diff-adjustment', [RateDiffAdjustmentController::class, 'index']);
Route::get('/api/rate-diff-adjustment/init', [RateDiffAdjustmentController::class, 'init']);
Route::get('/api/rate-diff-adjustment/load-party', [RateDiffAdjustmentController::class, 'loadParty']);
Route::get('/api/rate-diff-adjustment/search-party', [RateDiffAdjustmentController::class, 'searchParty']);
Route::get('/api/rate-diff-adjustment/load-bill', [RateDiffAdjustmentController::class, 'loadBill']);
Route::post('/api/rate-diff-adjustment/save', [RateDiffAdjustmentController::class, 'save']);

Route::get('/accounts/group-amt-allocation', [GroupAmtAllocationController::class, 'index']);
Route::get('/api/group-amt-allocation/groups', [GroupAmtAllocationController::class, 'groups']);
Route::get('/api/group-amt-allocation/search-account', [GroupAmtAllocationController::class, 'searchAccount']);
Route::get('/api/group-amt-allocation/show', [GroupAmtAllocationController::class, 'show']);
Route::post('/api/group-amt-allocation/set-def', [GroupAmtAllocationController::class, 'setDef']);
Route::post('/api/group-amt-allocation/save', [GroupAmtAllocationController::class, 'save']);

Route::get('/accounts/change-duedate', [ChangeDuedateController::class, 'index']);
Route::get('/api/change-duedate/load-party', [ChangeDuedateController::class, 'loadParty']);
Route::get('/api/change-duedate/search-party', [ChangeDuedateController::class, 'searchParty']);
Route::post('/api/change-duedate/save', [ChangeDuedateController::class, 'save']);

Route::get('/accounts/restart-date', [AccountRestartDateController::class, 'index']);
Route::get('/api/restart-date/load-account', [AccountRestartDateController::class, 'loadAccount']);
Route::get('/api/restart-date/search-account', [AccountRestartDateController::class, 'searchAccount']);
Route::post('/api/restart-date/save', [AccountRestartDateController::class, 'save']);
Route::get('/accounts/ac-ledger', [AccountLedgerController::class, 'index']);
Route::post('/accounts/ac-ledger/repair-sales', [AccountLedgerController::class, 'repairSalesLedger']);
Route::get('/api/accounts/customer-ledger', [AccountLedgerController::class, 'customerLedgerApi']);
Route::get('/accounts/group-ledger', [GroupLedgerController::class, 'index']);
Route::get('/accounts/cash-book', [CashBookController::class, 'index']);
Route::get('/accounts/bank-book', [BankBookController::class, 'index']);
Route::get('/accounts/yearly-cash-balance', [YearlyCashBalanceController::class, 'index']);
Route::get('/accounts/pdc-report', [PdcReportController::class, 'index']);
Route::post('/accounts/pdc-report/delete/{slno}', [PdcReportController::class, 'destroy']);
Route::get('/accounts/journal-report', [JournalReportController::class, 'index']);
Route::get('/accounts/all-trans-report', [AllTransReportController::class, 'index']);
Route::get('/accounts/chart-of-accounts', [ChartOfAccountsController::class, 'index']);
Route::get('/accounts/non-transactional-days-report', [NonTransactionalDaysReportController::class, 'index']);
Route::get('/accounts/ac-receivable-payable-summary', [AcReceivablePayableSummaryController::class, 'index']);

// Customer Reports
Route::get('/customers/credit-bill-details',    [CustomerReportsController::class, 'creditBillIndex']);
Route::get('/api/customers/credit-bill-details',[CustomerReportsController::class, 'creditBillData']);
Route::get('/customers/summary',                [CustomerReportsController::class, 'summaryIndex']);
Route::get('/api/customers/summary',            [CustomerReportsController::class, 'summaryData']);
Route::get('/customers/received-details',       [CustomerReportsController::class, 'receivedIndex']);
Route::get('/api/customers/received-details',   [CustomerReportsController::class, 'receivedData']);
Route::get('/customers/payment-details',        [CustomerReportsController::class, 'paymentIndex']);
Route::get('/api/customers/payment-details',    [CustomerReportsController::class, 'paymentData']);
Route::get('/customers/sales-summary',          [CustomerReportsController::class, 'salesSummaryIndex']);
Route::get('/api/customers/sales-summary',      [CustomerReportsController::class, 'salesSummaryData']);
Route::get('/customers/billwise-details',       [CustomerReportsController::class, 'billwiseDetailsIndex']);
Route::get('/api/customers/billwise-details',   [CustomerReportsController::class, 'billwiseDetailsData']);
Route::get('/customers/bill-collection-details',[CustomerReportsController::class, 'billCollectionIndex']);
Route::get('/api/customers/bill-collection-details',[CustomerReportsController::class, 'billCollectionData']);
Route::get('/customers/duedate-report',         [CustomerReportsController::class, 'duedateIndex']);
Route::get('/api/customers/duedate-report',     [CustomerReportsController::class, 'duedateData']);
Route::get('/customers/party-history',          [CustomerReportsController::class, 'partyHistoryIndex']);
Route::get('/api/customers/party-history',      [CustomerReportsController::class, 'partyHistoryData']);
Route::get('/customers/visit-report',           [CustomerReportsController::class, 'visitReportIndex']);
Route::get('/api/customers/visit-report',       [CustomerReportsController::class, 'visitReportData']);
Route::get('/customers/list',                   [CustomerReportsController::class, 'customerListIndex']);
Route::get('/api/customers/list',               [CustomerReportsController::class, 'customerListData']);

// Supplier Reports
Route::get('/suppliers/duedate-report',         [SupplierReportsController::class, 'duedateIndex']);
Route::get('/api/suppliers/duedate-report',     [SupplierReportsController::class, 'duedateData']);
Route::get('/suppliers/list',                   [SupplierReportsController::class, 'supplierListIndex']);
Route::get('/api/suppliers/list',               [SupplierReportsController::class, 'supplierListData']);
Route::get('/accounts/group-ac-summary', [GroupAccountSummaryController::class, 'index']);
Route::get('/accounts/groupwise-expanded-list', [GroupwiseExpandedListController::class, 'index']);
Route::get('/accounts/rcpt-pmnt-report', [ReceiptPaymentReportController::class, 'index']);
Route::get('/accounts/suspense-ac-ledger', [SuspenseLedgerController::class, 'index']);

Route::get('/accounts/amt-wgt-transfer-entry', [AmtWgtTransferController::class, 'index']);
Route::get('/api/amt-wgt-transfer/init', [AmtWgtTransferController::class, 'init']);
Route::get('/api/amt-wgt-transfer/load-party', [AmtWgtTransferController::class, 'loadParty']);
Route::get('/api/amt-wgt-transfer/search-party', [AmtWgtTransferController::class, 'searchParty']);
Route::post('/api/amt-wgt-transfer/save', [AmtWgtTransferController::class, 'save']);

Route::get('/accounts/payment-confirmation', [PaymentConfirmationController::class, 'index']);
Route::get('/api/payment-confirmation/load', [PaymentConfirmationController::class, 'load']);
Route::post('/api/payment-confirmation/confirm', [PaymentConfirmationController::class, 'confirm']);
Route::post('/api/payment-confirmation/cancel', [PaymentConfirmationController::class, 'cancel']);

Route::get('/stock-summary-cost-wise', [StockSummaryCostWiseController::class, 'index']);
Route::get('/stock-summary-cost-wise/show', [StockSummaryCostWiseController::class, 'show']);

Route::get('/stock-summary-rate-wise', [StockSummaryRateWiseController::class, 'index']);
Route::get('/stock-summary-rate-wise/show', [StockSummaryRateWiseController::class, 'show']);

Route::get('/sales-bill-confirmation', [SalesBillConfirmationController::class, 'index']);
Route::get('/sales-bill-confirmation/load', [SalesBillConfirmationController::class, 'load']);
Route::post('/sales-bill-confirmation/confirm', [SalesBillConfirmationController::class, 'confirm']);
Route::post('/sales-bill-confirmation/delete', [SalesBillConfirmationController::class, 'delete']);
Route::get('/sales-bill-confirmation/view', [SalesBillConfirmationController::class, 'view']);

Route::get('/purchase-bill-confirmation', [PurchaseBillConfirmationController::class, 'index']);
Route::get('/purchase-bill-confirmation/load', [PurchaseBillConfirmationController::class, 'load']);
Route::post('/purchase-bill-confirmation/confirm', [PurchaseBillConfirmationController::class, 'confirm']);
Route::post('/purchase-bill-confirmation/delete', [PurchaseBillConfirmationController::class, 'delete']);
Route::get('/purchase-bill-confirmation/view', [PurchaseBillConfirmationController::class, 'view']);

Route::get('/accounts/denomination-entry', [DenominationEntryController::class, 'index']);
Route::get('/api/denomination-entry/load', [DenominationEntryController::class, 'load']);
Route::post('/api/denomination-entry/save', [DenominationEntryController::class, 'save']);

Route::get('/login', [NativeAuthController::class, 'showLogin']);
Route::post('/login', [NativeAuthController::class, 'login']);
Route::get('/logout', [NativeAuthController::class, 'logout']);
Route::get('/dashboard', [NativeDashboardController::class, 'index']);
Route::get('/goldsmith-transactions-print', [GoldsmithTransactionController::class, 'printView'])->name('goldsmith-transactions.print');
Route::get('/goldsmith-transactions', [GoldsmithTransactionController::class, 'index'])->name('goldsmith-transactions.index');
Route::get('/goldsmith-transactions/{mode}', [GoldsmithTransactionController::class, 'index'])->name('goldsmith-transactions.mode');
Route::get('/goldsmith-transactions-picker/{action?}', [GoldsmithTransactionController::class, 'picker'])->name('goldsmith-transactions.picker');
Route::get('/api/goldsmith-transactions/next-number', [GoldsmithTransactionController::class, 'nextNumber']);
Route::get('/api/goldsmith-transactions/get',  [GoldsmithTransactionController::class, 'get']);
Route::get('/api/goldsmith-transactions/prev', [GoldsmithTransactionController::class, 'prev']);
Route::get('/api/goldsmith-transactions/next', [GoldsmithTransactionController::class, 'next']);
Route::get('/api/goldsmith-transactions/picker-search', [GoldsmithTransactionController::class, 'pickerSearch']);
Route::post('/api/goldsmith-transactions/picker-resolve', [GoldsmithTransactionController::class, 'pickerResolve']);
Route::post('/api/goldsmith-transactions/save', [GoldsmithTransactionController::class, 'save']);
Route::post('/api/goldsmith-transactions/delete', [GoldsmithTransactionController::class, 'delete']);
Route::get('/api/goldsmith-transactions/balance', [GoldsmithTransactionController::class, 'balance']);
Route::get('/api/goldsmith-transactions/item-help', [GoldsmithTransactionController::class, 'itemHelp']);
Route::get('/api/goldsmith-transactions/item-info', [GoldsmithTransactionController::class, 'itemInfo']);
Route::get('/api/goldsmith-transactions/client-help', [GoldsmithTransactionController::class, 'clientHelp']);
Route::get('/api/goldsmith-transactions/barcode-info', [GoldsmithTransactionController::class, 'barcodeInfo']);
Route::get('/api/goldsmith-transactions/interest-posting/show', [GoldsmithTransactionController::class, 'interestPostingShow']);
Route::post('/api/goldsmith-transactions/interest-posting/save', [GoldsmithTransactionController::class, 'interestPostingSave']);
Route::get('/api/goldsmith-new-work-note/list', [GoldsmithNewWorkNoteController::class, 'list']);
Route::post('/api/goldsmith-new-work-note/save', [GoldsmithNewWorkNoteController::class, 'save']);
Route::post('/api/goldsmith-new-work-note/delete', [GoldsmithNewWorkNoteController::class, 'delete']);
Route::get('/item-stock-adjustment', [ItemAdjustmentController::class, 'index'])->name('item-stock-adjustment.index');
Route::get('/item-stock-adjustment-multi', [ItemAdjustmentController::class, 'multi'])->name('item-stock-adjustment.multi');
Route::get('/item-stock-add-less', [ItemAdjustmentController::class, 'addLess'])->name('item-stock-adjustment.add-less');
Route::get('/item-add-less-report', [ItemAdjustmentController::class, 'addLessReport'])->name('item-add-less-report.index');
Route::get('/item-stock-adjustment-compare', [ItemAdjustmentController::class, 'stockAdjustment'])->name('item-stock-adjustment.compare');
Route::get('/api/item-stock-adjustment/item', [ItemAdjustmentController::class, 'item']);
Route::get('/api/item-stock-adjustment/item-search', [ItemAdjustmentController::class, 'itemSearch']);
Route::get('/api/item-stock-adjustment/barcode', [ItemAdjustmentController::class, 'barcode']);
Route::get('/api/item-add-less-report/data', [ItemAdjustmentController::class, 'addLessReportData']);
Route::get('/api/item-stock-adjustment/compare', [ItemAdjustmentController::class, 'stockAdjustmentData']);
Route::post('/api/item-stock-adjustment/save', [ItemAdjustmentController::class, 'save']);
Route::get('/api/item-stock-adjustment-multi/bc-summary', [ItemAdjustmentController::class, 'bcSummary']);
Route::post('/api/item-stock-adjustment-multi/save', [ItemAdjustmentController::class, 'saveMulti']);
Route::post('/api/item-stock-add-less/save', [ItemAdjustmentController::class, 'saveAddLess']);
Route::post('/api/item-stock-adjustment/compare/save', [ItemAdjustmentController::class, 'saveStockAdjustment']);
Route::get('/item-stock-adjustment-edit-cancel', [ItemAdjustmentController::class, 'editCancel'])->name('item-stock-adjustment.edit-cancel');
Route::get('/item-adjustment-report', [ItemAdjustmentController::class, 'report'])->name('item-adjustment-report.index');
Route::get('/item-movement', [ItemMovementController::class, 'index'])->name('item-movement.index');
Route::get('/api/item-movement/data', [ItemMovementController::class, 'data']);
Route::get('/itemwise-profit', [ItemwiseProfitController::class, 'index'])->name('itemwise-profit.index');
Route::get('/item-rate-report', [ItemReportsController::class, 'itemRate']);
Route::get('/cost-list', [ItemReportsController::class, 'costList']);
Route::get('/rate-history', [ItemReportsController::class, 'rateHistory']);
Route::get('/model-transfer-report', [ItemReportsController::class, 'modelTransfer']);
Route::get('/stone-trans-analysis', [ItemReportsController::class, 'stoneTransAnalysis']);
Route::get('/trans-ra-report', [ItemReportsController::class, 'transRa']);
Route::get('/item-stock-party-wgt-report', [ItemReportsController::class, 'itemStockPartyWgt']);
Route::get('/api/item-rate-report/data',          [ItemReportsController::class, 'itemRateData']);
Route::get('/api/cost-list/data',                 [ItemReportsController::class, 'costListData']);
Route::get('/api/rate-history/data',              [ItemReportsController::class, 'rateHistoryData']);
Route::get('/api/model-transfer-report/data',     [ItemReportsController::class, 'modelTransferData']);
Route::get('/api/stone-trans-analysis/data',      [ItemReportsController::class, 'stoneTransData']);
Route::get('/api/trans-ra-report/data',           [ItemReportsController::class, 'transRaData']);
Route::get('/api/item-stock-party-wgt-report/data',[ItemReportsController::class, 'itemStockPartyWgtData']);
Route::get('/api/itemwise-profit/data',           [ItemwiseProfitController::class, 'data']);
Route::get('/api/item-adjustment-report/data',    [ItemAdjustmentController::class, 'reportData']);
Route::get('/api/item-stock-adjustment/list', [ItemAdjustmentController::class, 'listAdjustments']);
Route::post('/api/item-stock-adjustment/update', [ItemAdjustmentController::class, 'updateAdjustment']);
Route::post('/api/item-stock-adjustment/cancel', [ItemAdjustmentController::class, 'cancelAdjustment']);

/*
|--------------------------------------------------------------------------
| Deposit Weight Receipt / Payment
|--------------------------------------------------------------------------
*/
Route::get('/wgt-rcpt-pmnt', [WgtRcptPmntController::class, 'index'])->name('wgt-rcpt-pmnt.index');
Route::post('/api/wgt-rcpt-pmnt', [WgtRcptPmntController::class, 'api']);
Route::get('/party-op-weight', [PartyOpWeightController::class, 'index'])->name('party-op-weight.index');
Route::post('/api/party-op-weight', [PartyOpWeightController::class, 'api']);
Route::get('/depositors-int-post', [DepositorsIntPostController::class, 'index'])->name('depositors-int-post.index');
Route::post('/api/depositors-int-post', [DepositorsIntPostController::class, 'api']);
Route::get('/kuri-int-post', [KuriIntPostController::class, 'index'])->name('kuri-int-post.index');
Route::post('/api/kuri-int-post', [KuriIntPostController::class, 'api']);
Route::get('/kuri-finish/close-scheme', [KuriFinishController::class, 'closeScheme']);
Route::get('/kuri-finish/draw', [KuriFinishController::class, 'draw']);
Route::get('/kuri-finish/refund', [KuriFinishController::class, 'refund']);
Route::post('/api/kuri-finish', [KuriFinishController::class, 'api']);

// Other Items Transaction (Sales / Purchase)
Route::get('/other-item-trans', [OtherItemTransController::class, 'index'])->name('other-item-trans.index');
Route::get('/other-item-trans/picker', [OtherItemTransController::class, 'picker'])->name('other-item-trans.picker');
Route::post('/api/other-item-trans', [OtherItemTransController::class, 'api']);

// Stock Suspense (Model) Entry — Issue / Receipt / Cancel / Reprint
Route::get('/stock-suspense-entry', [StockSuspenseEntryController::class, 'index'])->name('stock-suspense-entry.index');
Route::get('/stock-suspense-entry/picker', [StockSuspenseEntryController::class, 'picker'])->name('stock-suspense-entry.picker');
Route::post('/api/stock-suspense-entry', [StockSuspenseEntryController::class, 'api']);

// Purity Testing
Route::get('/purity-testing', [PurityTestingController::class, 'index'])->name('purity-testing.index');
Route::get('/purity-testing/picker', [PurityTestingController::class, 'picker'])->name('purity-testing.picker');
Route::post('/api/purity-testing', [PurityTestingController::class, 'api']);

// Purity Certificate
Route::get('/purity-certificate', [PurityCertificateController::class, 'index'])->name('purity-certificate.index');
Route::post('/api/purity-certificate', [PurityCertificateController::class, 'api']);

// Ruff Work
Route::get('/ruff-work', [RuffWorkController::class, 'index'])->name('ruff-work.index');
Route::post('/api/ruff-work', [RuffWorkController::class, 'api']);

/*
|--------------------------------------------------------------------------
| Sales Bill
|--------------------------------------------------------------------------
*/
Route::get('/sales-bill/edit-picker', [SalesBillController::class, 'editPicker'])->name('sales-bill.edit-picker');
Route::get('/sales-bill/reprint-picker', [SalesBillController::class, 'reprintPicker'])->name('sales-bill.reprint-picker');
Route::get('/sales-bill/cancel-picker', [SalesBillController::class, 'cancelPicker'])->name('sales-bill.cancel-picker');
Route::get('/api/sales-bill/edit-search', [SalesBillController::class, 'searchEditBills'])->name('sales-bill.edit-search');
Route::post('/api/sales-bill/edit-resolve', [SalesBillController::class, 'resolveEditBill'])->name('sales-bill.edit-resolve');
Route::post('/api/sales-bill/action-resolve', [SalesBillController::class, 'resolveBillAction'])->name('sales-bill.action-resolve');
Route::get('/sales-bill/{mode?}', [SalesBillController::class, 'index'])->name('sales-bill.index');
Route::get('/sales-bill-print', [SalesBillPrintController::class, 'show'])->name('sales-bill.print');
Route::post('/api/sales-bill/save-setting', [SalesBillPrintController::class, 'saveSetting']);
Route::get('/api/sales-bill/next-number', [SalesBillController::class, 'nextBillNo'])->name('sales-bill.next-number');
Route::get('/api/sales-bill/check-bill-no', [SalesBillController::class, 'checkBillNo'])->name('sales-bill.check-bill-no');
Route::get('/api/sales-bill/get', [SalesBillController::class, 'get'])->name('sales-bill.get');
Route::get('/api/sales-bill/customer-details', [SalesBillController::class, 'customerDetails'])->name('sales-bill.customer-details');
Route::get('/api/sales-bill/customer-by-mobile', [SalesBillController::class, 'customerByMobile'])->name('sales-bill.customer-by-mobile');
Route::get('/api/sales-bill/prev', [SalesBillController::class, 'prevBill'])->name('sales-bill.prev');
Route::get('/api/sales-bill/next', [SalesBillController::class, 'nextBill'])->name('sales-bill.next');
Route::get('/api/sales-bill/search', [SalesBillController::class, 'search'])->name('sales-bill.search');
Route::get('/api/sales-bill/quotation-list', [SalesBillController::class, 'quotationList'])->name('sales-bill.quotation-list');
Route::get('/api/sales-bill/item-lookup', [SalesBillController::class, 'itemLookup'])->name('sales-bill.item-lookup');
Route::post('/api/sales-bill/recalc', [SalesBillController::class, 'recalc'])->name('sales-bill.recalc');
Route::post('/api/sales-bill/save', [SalesBillController::class, 'save'])->name('sales-bill.save');
Route::post('/api/sales-bill/einvoice', [SalesBillController::class, 'generateEInvoice'])->name('sales-bill.einvoice');
Route::post('/api/sales-bill/cancel', [SalesBillController::class, 'cancelBill'])->name('sales-bill.cancel');
Route::post('/api/sales-bill/confirm', [SalesBillController::class, 'confirmBill'])->name('sales-bill.confirm');
Route::post('/api/sales-bill/reprint', [SalesBillController::class, 'reprint'])->name('sales-bill.reprint');
Route::get('/api/sales-bill/customer-search', [SalesBillController::class, 'customerSearch'])->name('sales-bill.customer-search');

/*
|--------------------------------------------------------------------------
| Rate Management
|--------------------------------------------------------------------------
*/
Route::get('/rate/update', [RateController::class, 'index']);
Route::post('/api/rate/save', [RateController::class, 'save']);
Route::get('/api/rate/current', [RateController::class, 'current']);
Route::get('/rate/history', [RateController::class, 'history']);
Route::get('/gold-rate-story', [GoldRateStoryController::class, 'index']);
Route::get('/api/gold-rate-story/data', [GoldRateStoryController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Day Book Report
|--------------------------------------------------------------------------
*/
Route::get('/daybook', [DayBookController::class, 'index'])->name('daybook.index');
Route::get('/sales-book-report', [SalesBookReportController::class, 'index'])->name('sales-book-report.index');
Route::get('/api/sales-book-report/data', [SalesBookReportController::class, 'data'])->name('sales-book-report.data');
Route::get('/api/sales-book-report/lookups', [SalesBookReportController::class, 'lookups']);
Route::post('/api/sales-book-report/einvoice-json-bulk', [SalesBookReportController::class, 'bulkEInvoiceJson']);
Route::post('/api/sales-book-report/rearrange', [SalesBookReportController::class, 'rearrangeBillNos']);
Route::get('/sales-book-report/einvoice-download/{file}', [SalesBookReportController::class, 'downloadEInvoiceArchive'])->where('file', '.*');
Route::get('/monthly-sales-report', [App\Http\Controllers\MonthlySalesReportController::class, 'index']);
Route::get('/api/monthly-sales-report/data', [App\Http\Controllers\MonthlySalesReportController::class, 'data']);
Route::get('/sales-register', [App\Http\Controllers\SalesRegisterController::class, 'index']);
Route::get('/api/sales-register/lookups', [App\Http\Controllers\SalesRegisterController::class, 'lookups']);
Route::get('/api/sales-register/data', [App\Http\Controllers\SalesRegisterController::class, 'data']);
Route::get('/sales-check-list', [App\Http\Controllers\SalesCheckListController::class, 'index']);
Route::get('/api/sales-check-list/data', [App\Http\Controllers\SalesCheckListController::class, 'data']);
Route::get('/sales-return-register', [App\Http\Controllers\SalesReturnRegisterController::class, 'index']);
Route::get('/api/sales-return-register/data', [App\Http\Controllers\SalesReturnRegisterController::class, 'data']);
Route::get('/net-sales', [App\Http\Controllers\NetSalesReportController::class, 'index']);
Route::get('/api/net-sales/lookups', [App\Http\Controllers\NetSalesReportController::class, 'lookups']);
Route::get('/api/net-sales/data', [App\Http\Controllers\NetSalesReportController::class, 'data']);
Route::get('/barcode-register', [App\Http\Controllers\BarcodeRegisterController::class, 'index']);
Route::get('/api/barcode-register/data', [App\Http\Controllers\BarcodeRegisterController::class, 'data']);
Route::get('/barcode-profit', [App\Http\Controllers\BarcodeProfitReportController::class, 'index']);
Route::get('/api/barcode-profit/lookups', [App\Http\Controllers\BarcodeProfitReportController::class, 'lookups']);
Route::get('/api/barcode-profit/data', [App\Http\Controllers\BarcodeProfitReportController::class, 'data']);
Route::get('/marked-list', [App\Http\Controllers\MarkedListController::class, 'index']);
Route::get('/api/marked-list/lookups', [App\Http\Controllers\MarkedListController::class, 'lookups']);
Route::get('/api/marked-list/data', [App\Http\Controllers\MarkedListController::class, 'data']);
Route::get('/mc-profit', [App\Http\Controllers\MCProfitReportController::class, 'index']);
Route::get('/api/mc-profit/lookups', [App\Http\Controllers\MCProfitReportController::class, 'lookups']);
Route::get('/api/mc-profit/data', [App\Http\Controllers\MCProfitReportController::class, 'data']);
Route::get('/va-check-list', [App\Http\Controllers\VACheckListController::class, 'index']);
Route::get('/api/va-check-list/data', [App\Http\Controllers\VACheckListController::class, 'data']);
Route::get('/va-check-report', [App\Http\Controllers\VACheckReportController::class, 'index']);
Route::get('/api/va-check-report/lookups', [App\Http\Controllers\VACheckReportController::class, 'lookups']);
Route::get('/api/va-check-report/data', [App\Http\Controllers\VACheckReportController::class, 'data']);
Route::get('/delivery-status', [App\Http\Controllers\DeliveryStatusReportController::class, 'index']);
Route::get('/api/delivery-status/lookups', [App\Http\Controllers\DeliveryStatusReportController::class, 'lookups']);
Route::get('/api/delivery-status/data', [App\Http\Controllers\DeliveryStatusReportController::class, 'data']);
Route::post('/api/delivery-status/update', [App\Http\Controllers\DeliveryStatusReportController::class, 'updateStatus']);
Route::get('/point-card-points', [App\Http\Controllers\PointCardPointsReportController::class, 'index']);
Route::get('/api/point-card-points/lookups', [App\Http\Controllers\PointCardPointsReportController::class, 'lookups']);
Route::get('/api/point-card-points/data', [App\Http\Controllers\PointCardPointsReportController::class, 'data']);
Route::get('/purchase-check-list', [App\Http\Controllers\PurchaseCheckListController::class, 'index']);
Route::get('/api/purchase-check-list/data', [App\Http\Controllers\PurchaseCheckListController::class, 'data']);
Route::get('/purchase-book', [App\Http\Controllers\PurchaseBookController::class, 'index']);
Route::get('/api/purchase-book/lookups', [App\Http\Controllers\PurchaseBookController::class, 'lookups']);
Route::get('/api/purchase-book/data', [App\Http\Controllers\PurchaseBookController::class, 'data']);
Route::get('/purchase-register', [App\Http\Controllers\PurchaseRegisterController::class, 'index']);
Route::get('/api/purchase-register/lookups', [App\Http\Controllers\PurchaseRegisterController::class, 'lookups']);
Route::get('/api/purchase-register/data', [App\Http\Controllers\PurchaseRegisterController::class, 'data']);
Route::get('/purchase-return-register', [App\Http\Controllers\PurchaseReturnRegisterController::class, 'index']);
Route::get('/api/purchase-return-register/data', [App\Http\Controllers\PurchaseReturnRegisterController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Sales Return Bill
|--------------------------------------------------------------------------
*/
Route::get('/sales-return/picker/{action?}', [SalesReturnController::class, 'picker'])->name('sales-return.picker');
Route::get('/sales-return/{mode?}', [SalesReturnController::class, 'index'])->name('sales-return.index');
Route::get('/api/sales-return/picker-search', [SalesReturnController::class, 'pickerSearch']);
Route::post('/api/sales-return/picker-resolve', [SalesReturnController::class, 'pickerResolve']);

/*
|--------------------------------------------------------------------------
| Purchase Bill
|--------------------------------------------------------------------------
*/
Route::get('/purchase-bill/picker/{action?}',       [PurchaseBillController::class, 'picker'])->name('purchase-bill.picker');
Route::get('/purchase-bill-print',                  [App\Http\Controllers\PurchaseBillPrintController::class, 'show'])->name('purchase-bill.print');
Route::get('/purchase-bill/{mode?}',                [PurchaseBillController::class, 'index'])->name('purchase-bill.index');
Route::get('/api/purchase-bill/next-number',        [PurchaseBillController::class, 'nextBillNo']);
Route::get('/api/purchase-bill/supplier-search',    [PurchaseBillController::class, 'supplierSearch']);
Route::get('/api/purchase-bill/supplier-details',   [PurchaseBillController::class, 'supplierDetails']);
Route::get('/api/purchase-bill/item-lookup',        [PurchaseBillController::class, 'itemLookup']);
Route::get('/api/purchase-bill/item-search',        [PurchaseBillController::class, 'itemSearch']);
Route::post('/api/purchase-bill/recalc',            [PurchaseBillController::class, 'recalc']);
Route::post('/api/purchase-bill/save',              [PurchaseBillController::class, 'save']);
Route::get('/api/purchase-bill/get',                [PurchaseBillController::class, 'get']);
Route::get('/api/purchase-bill/prev',               [PurchaseBillController::class, 'prevBill']);
Route::get('/api/purchase-bill/next',               [PurchaseBillController::class, 'nextBill']);
Route::get('/api/purchase-bill/search',             [PurchaseBillController::class, 'search']);
Route::get('/api/purchase-bill/picker-search',      [PurchaseBillController::class, 'pickerSearch']);
Route::post('/api/purchase-bill/picker-resolve',    [PurchaseBillController::class, 'pickerResolve']);
Route::post('/api/purchase-bill/cancel',            [PurchaseBillController::class, 'cancelBill']);
Route::post('/api/purchase-bill/reprint',            [PurchaseBillController::class, 'reprint']);
Route::post('/api/purchase-bill/create-supplier',   [PurchaseBillController::class, 'createSupplier']);
Route::post('/api/purchase-bill/rebuild-daybook',   [PurchaseBillController::class, 'rebuildAllDaybook']);

/*
|--------------------------------------------------------------------------
| Diamond Purchase Bill
|--------------------------------------------------------------------------
*/
Route::get('/diamond-purchase/picker/{action?}',       [DiamondPurchaseBillController::class, 'picker']);
Route::get('/diamond-purchase/{mode?}',                [DiamondPurchaseBillController::class, 'index']);
Route::get('/api/diamond-purchase/next-number',        [DiamondPurchaseBillController::class, 'nextBillNo']);
Route::get('/api/diamond-purchase/supplier-search',    [DiamondPurchaseBillController::class, 'supplierSearch']);
Route::get('/api/diamond-purchase/supplier-details',   [DiamondPurchaseBillController::class, 'supplierDetails']);
Route::get('/api/diamond-purchase/item-lookup',        [DiamondPurchaseBillController::class, 'itemLookup']);
Route::get('/api/diamond-purchase/item-search',        [DiamondPurchaseBillController::class, 'itemSearch']);
Route::post('/api/diamond-purchase/recalc',            [DiamondPurchaseBillController::class, 'recalc']);
Route::post('/api/diamond-purchase/save',              [DiamondPurchaseBillController::class, 'save']);
Route::get('/api/diamond-purchase/get',                [DiamondPurchaseBillController::class, 'get']);
Route::get('/api/diamond-purchase/prev',               [DiamondPurchaseBillController::class, 'prevBill']);
Route::get('/api/diamond-purchase/next',               [DiamondPurchaseBillController::class, 'nextBill']);
Route::get('/api/diamond-purchase/search',             [DiamondPurchaseBillController::class, 'search']);
Route::get('/api/diamond-purchase/picker-search',      [DiamondPurchaseBillController::class, 'pickerSearch']);
Route::post('/api/diamond-purchase/picker-resolve',    [DiamondPurchaseBillController::class, 'pickerResolve']);
Route::post('/api/diamond-purchase/cancel',            [DiamondPurchaseBillController::class, 'cancelBill']);
Route::post('/api/diamond-purchase/reprint',           [DiamondPurchaseBillController::class, 'reprint']);
Route::post('/api/diamond-purchase/create-supplier',   [DiamondPurchaseBillController::class, 'createSupplier']);
Route::post('/api/diamond-purchase/rebuild-daybook',   [DiamondPurchaseBillController::class, 'rebuildAllDaybook']);
Route::get('/api/diamond-purchase/barcode-lookup',     [DiamondPurchaseBillController::class, 'barcodeLookup']);
Route::get('/api/diamond-purchase/next-barcode',       [DiamondPurchaseBillController::class, 'nextBarcode']);

/*
|--------------------------------------------------------------------------
| Diamond Purchase Return
|--------------------------------------------------------------------------
*/
Route::get('/diamond-purchase-return/picker/{action?}',           [DiamondPurchaseReturnController::class, 'picker']);
Route::get('/diamond-purchase-return/{mode?}',                    [DiamondPurchaseReturnController::class, 'index']);
Route::get('/api/diamond-purchase-return/next-number',            [DiamondPurchaseReturnController::class, 'nextBillNo']);
Route::get('/api/diamond-purchase-return/supplier-search',        [DiamondPurchaseReturnController::class, 'supplierSearch']);
Route::get('/api/diamond-purchase-return/supplier-details',       [DiamondPurchaseReturnController::class, 'supplierDetails']);
Route::get('/api/diamond-purchase-return/item-lookup',            [DiamondPurchaseReturnController::class, 'itemLookup']);
Route::get('/api/diamond-purchase-return/item-search',            [DiamondPurchaseReturnController::class, 'itemSearch']);
Route::post('/api/diamond-purchase-return/save',                  [DiamondPurchaseReturnController::class, 'save']);
Route::get('/api/diamond-purchase-return/get',                    [DiamondPurchaseReturnController::class, 'get']);
Route::get('/api/diamond-purchase-return/prev',                   [DiamondPurchaseReturnController::class, 'prevBill']);
Route::get('/api/diamond-purchase-return/next',                   [DiamondPurchaseReturnController::class, 'nextBill']);
Route::get('/api/diamond-purchase-return/search',                 [DiamondPurchaseReturnController::class, 'search']);
Route::get('/api/diamond-purchase-return/picker-search',          [DiamondPurchaseReturnController::class, 'pickerSearch']);
Route::post('/api/diamond-purchase-return/picker-resolve',        [DiamondPurchaseReturnController::class, 'pickerResolve']);
Route::post('/api/diamond-purchase-return/cancel',                [DiamondPurchaseReturnController::class, 'cancelBill']);
Route::get('/api/diamond-purchase-return/barcode-lookup',         [DiamondPurchaseReturnController::class, 'barcodeLookup']);
Route::get('/api/diamond-purchase-return/next-barcode',           [DiamondPurchaseReturnController::class, 'nextBarcode']);

/*
|--------------------------------------------------------------------------
| Purchase Return
|--------------------------------------------------------------------------
*/
Route::get('/purchase-return/picker/{action?}',       [PurchaseReturnController::class, 'picker'])->name('purchase-return.picker');
Route::get('/purchase-return/{mode?}',                [PurchaseReturnController::class, 'index'])->name('purchase-return.index');
Route::get('/api/purchase-return/next-number',        [PurchaseReturnController::class, 'nextBillNo']);
Route::get('/api/purchase-return/supplier-search',    [PurchaseReturnController::class, 'supplierSearch']);
Route::get('/api/purchase-return/supplier-details',   [PurchaseReturnController::class, 'supplierDetails']);
Route::get('/api/purchase-return/item-lookup',        [PurchaseReturnController::class, 'itemLookup']);
Route::get('/api/purchase-return/item-search',        [PurchaseReturnController::class, 'itemSearch']);
Route::post('/api/purchase-return/recalc',            [PurchaseReturnController::class, 'recalc']);
Route::post('/api/purchase-return/save',              [PurchaseReturnController::class, 'save']);
Route::get('/api/purchase-return/get',                [PurchaseReturnController::class, 'get']);
Route::get('/api/purchase-return/prev',               [PurchaseReturnController::class, 'prevBill']);
Route::get('/api/purchase-return/next',               [PurchaseReturnController::class, 'nextBill']);
Route::get('/api/purchase-return/search',             [PurchaseReturnController::class, 'search']);
Route::get('/api/purchase-return/picker-search',      [PurchaseReturnController::class, 'pickerSearch']);
Route::post('/api/purchase-return/picker-resolve',    [PurchaseReturnController::class, 'pickerResolve']);
Route::post('/api/purchase-return/cancel',            [PurchaseReturnController::class, 'cancelBill']);
Route::post('/api/purchase-return/reprint',           [PurchaseReturnController::class, 'reprint']);

/*
|--------------------------------------------------------------------------
| Order Bill
|--------------------------------------------------------------------------
*/
Route::get('/order-bill/edit-picker',             [OrderBillController::class, 'editPicker']);
Route::get('/api/order-bill/edit-search',        [OrderBillController::class, 'searchEditOrders']);
Route::post('/api/order-bill/action-resolve',    [OrderBillController::class, 'resolveOrderAction']);
Route::get('/order-bill/print',                  [OrderBillController::class, 'printView']);
Route::get('/order-bill/{mode?}',                [OrderBillController::class, 'index'])->name('order-bill.index');
Route::get('/api/order-bill/next-number',        [OrderBillController::class, 'nextBillNo']);
Route::get('/api/order-bill/customer-search',    [OrderBillController::class, 'customerSearch']);
Route::get('/api/order-bill/customer-details',   [OrderBillController::class, 'customerDetails']);
Route::get('/api/order-bill/item-lookup',        [OrderBillController::class, 'itemLookup']);
Route::get('/api/order-bill/item-search',        [OrderBillController::class, 'itemSearch']);
Route::post('/api/order-bill/recalc',            [OrderBillController::class, 'recalc']);
Route::post('/api/order-bill/save',              [OrderBillController::class, 'save']);
Route::get('/api/order-bill/get',                [OrderBillController::class, 'get']);
Route::get('/api/order-bill/prev',               [OrderBillController::class, 'prevBill']);
Route::get('/api/order-bill/next',               [OrderBillController::class, 'nextBill']);
Route::get('/api/order-bill/search',             [OrderBillController::class, 'search']);
Route::post('/api/order-bill/cancel',            [OrderBillController::class, 'cancelBill']);

/*
|--------------------------------------------------------------------------
| Order Advance After
|--------------------------------------------------------------------------
*/
Route::get('/order-advance-after/{mode?}',                   [OrderAdvanceAfterController::class, 'index']);
Route::get('/api/order-advance-after/order-search',          [OrderAdvanceAfterController::class, 'orderSearch']);
Route::get('/api/order-advance-after/order-lookup',          [OrderAdvanceAfterController::class, 'orderLookup']);
Route::get('/api/order-advance-after/item-lookup',           [OrderAdvanceAfterController::class, 'itemLookup']);
Route::get('/api/order-advance-after/item-search',           [OrderAdvanceAfterController::class, 'itemSearch']);
Route::post('/api/order-advance-after/save',                 [OrderAdvanceAfterController::class, 'save']);
Route::get('/api/order-advance-after/get',                   [OrderAdvanceAfterController::class, 'get']);
Route::get('/api/order-advance-after/search',                [OrderAdvanceAfterController::class, 'search']);
Route::post('/api/order-advance-after/delete',               [OrderAdvanceAfterController::class, 'delete']);
Route::get('/api/order-advance-after/prev',                  [OrderAdvanceAfterController::class, 'prevBill']);
Route::get('/api/order-advance-after/next',                  [OrderAdvanceAfterController::class, 'nextBill']);

/*
|--------------------------------------------------------------------------
| Order Sale
|--------------------------------------------------------------------------
*/
Route::get('/order-sale/{mode?}',                   [OrderSaleController::class, 'index']);
Route::get('/api/order-sale/load-order',            [OrderSaleController::class, 'loadOrder']);
Route::get('/api/order-sale/order-search',          [OrderSaleController::class, 'orderSearch']);
Route::get('/api/order-sale/customer-details',      [OrderSaleController::class, 'customerDetails']);
Route::get('/api/order-sale/item-lookup',           [OrderSaleController::class, 'itemLookup']);
Route::get('/api/order-sale/item-search',           [OrderSaleController::class, 'itemSearch']);
Route::post('/api/order-sale/recalc',               [OrderSaleController::class, 'recalc']);
Route::post('/api/order-sale/save',                 [OrderSaleController::class, 'save']);
Route::get('/api/order-sale/get',                   [OrderSaleController::class, 'get']);
Route::get('/api/order-sale/search',                [OrderSaleController::class, 'search']);
Route::get('/api/order-sale/prev',                  [OrderSaleController::class, 'prevBill']);
Route::get('/api/order-sale/next',                  [OrderSaleController::class, 'nextBill']);
Route::post('/api/order-sale/cancel',               [OrderSaleController::class, 'cancelBill']);
Route::get('/order-rate-fix',                       [OrderRateFixController::class, 'index']);
Route::get('/api/order-rate-fix/search',            [OrderRateFixController::class, 'search']);
Route::post('/api/order-rate-fix/apply',            [OrderRateFixController::class, 'apply']);
Route::get('/order-reprint',                        [OrderReprintController::class, 'index']);
Route::get('/api/order-reprint/search',             [OrderReprintController::class, 'search']);
Route::get('/api/order-reprint/resolve',            [OrderReprintController::class, 'resolve']);

Route::get('/order-cancel',                         [OrderCancelController::class, 'index']);
Route::get('/api/order-cancel/search',              [OrderCancelController::class, 'search']);
Route::post('/api/order-cancel/apply',              [OrderCancelController::class, 'apply']);

// Order Reports
Route::get('/order-entry-report',                   [OrderEntryReportController::class, 'index']);
Route::get('/order-pending-register',               [OrderPendingRegisterController::class, 'index']);
Route::get('/order-enquiry',                        [OrderEnquiryController::class, 'index']);
Route::get('/order-pending-details',                [OrderPendingDetailsController::class, 'index']);
Route::get('/order-returns',                        [OrderReturnsController::class, 'index']);
Route::get('/order-advance-report',                 [OrderAdvanceReportController::class, 'index']);
Route::get('/order-profit-analysis',                [OrderProfitAnalysisController::class, 'index']);
Route::get('/order-sample-stock',                   [OrderSampleStockController::class, 'index']);
Route::get('/order-nos-list',                       [OrderNosListController::class, 'index']);
Route::get('/order-process-report',                 [OrderProcessController::class, 'index']);
Route::get('/order-send-mail',                      [OrderSendMailController::class, 'index']);
Route::get('/order-send-mail/export',               [OrderSendMailController::class, 'exportCsv']);

// Barcode Reports
Route::get('/barcode-list',                         [BarcodeListController::class, 'index']);
Route::get('/barcode-stock-verify',                 [BarcodeStockVerifyController::class, 'index']);
Route::get('/barcode-samt-list',                    [BarcodeSamtListController::class, 'index']);
Route::get('/barcode-history',                      [BarcodeHistoryController::class, 'index']);
Route::get('/barcode-stock-list',                   [BarcodeStockListController::class, 'index']);
Route::get('/barcode-entry-comparison',             [BarcodeEntryComparisonController::class, 'index']);
Route::get('/diamond-stone-stock',                  [DiamondStoneStockController::class, 'index']);

// Backup
Route::get('/backup',                               [BackupController::class, 'index']);
Route::post('/backup/run',                           [BackupController::class, 'runBackup']);
Route::post('/backup/local-disk',                    [BackupController::class, 'runLocalDiskBackup']);
Route::post('/backup/local-disk/browser-copy',       [BackupController::class, 'runBrowserLocalDiskBackup']);
Route::post('/backup/local-disk/browse',             [BackupController::class, 'browseLocalDiskPath']);
Route::post('/backup/local-disk/save-path',          [BackupController::class, 'saveLocalDiskPath']);
Route::get('/backup/download/{file}',               [BackupController::class, 'download'])->where('file', '.*');
Route::post('/backup/delete',                        [BackupController::class, 'delete']);
Route::post('/backup/auto-settings',                 [BackupController::class, 'saveAutoSettings']);

// Refinery Bill
Route::get('/refinery-bill/all-in-one',                 [RefineryBillController::class, 'allInOne']);
Route::get('/refinery-bill/picker/{action?}',          [RefineryBillController::class, 'picker']);
Route::get('/refinery-bill/{mode?}',                   [RefineryBillController::class, 'index']);
Route::get('/api/refinery-bill/refiner-lookup',        [RefineryBillController::class, 'refinerLookup']);
Route::get('/api/refinery-bill/refiner-search',        [RefineryBillController::class, 'refinerSearch']);
Route::get('/api/refinery-bill/item-lookup',           [RefineryBillController::class, 'itemLookup']);
Route::get('/api/refinery-bill/item-search',           [RefineryBillController::class, 'itemSearch']);
Route::get('/api/refinery-bill/picker-search',         [RefineryBillController::class, 'pickerSearch']);
Route::post('/api/refinery-bill/picker-resolve',       [RefineryBillController::class, 'pickerResolve']);
Route::post('/api/refinery-bill/save',                 [RefineryBillController::class, 'save']);
Route::get('/api/refinery-bill/get',                   [RefineryBillController::class, 'get']);
Route::get('/api/refinery-bill/search',                [RefineryBillController::class, 'search']);
Route::get('/api/refinery-bill/prev',                  [RefineryBillController::class, 'prevBill']);
Route::get('/api/refinery-bill/next',                  [RefineryBillController::class, 'nextBill']);
Route::post('/api/refinery-bill/cancel',               [RefineryBillController::class, 'cancelBill']);

// Refinery Reports
Route::get('/refinery-reports/entry-report',              [RefineryReportController::class, 'entryReport']);
Route::get('/api/refinery-reports/entry-report/data',     [RefineryReportController::class, 'entryReportData']);
Route::get('/refinery-reports/refiners-summary',          [RefineryReportController::class, 'refinersSummary']);
Route::get('/api/refinery-reports/refiners-summary/data', [RefineryReportController::class, 'refinersSummaryData']);
Route::get('/refinery-reports/analysis',                  [RefineryReportController::class, 'analysis']);
Route::get('/api/refinery-reports/analysis/data',         [RefineryReportController::class, 'analysisData']);
Route::get('/refinery-reports/ac-summary',                [RefineryReportController::class, 'acSummary']);
Route::get('/api/refinery-reports/ac-summary/data',       [RefineryReportController::class, 'acSummaryData']);
Route::get('/refinery-reports/less-comparison',           [RefineryReportController::class, 'lessComparison']);
Route::get('/api/refinery-reports/less-comparison/data',  [RefineryReportController::class, 'lessComparisonData']);

// Deposit Reports — Party Wgt Deposit & Partners Deposit(Wgt)
Route::get('/deposit-reports/transactions',                   [DepositReportController::class, 'transactions']);
Route::get('/api/deposit-reports/transactions/data',          [DepositReportController::class, 'transactionsData']);
Route::get('/deposit-reports/depositer-ledger',               [DepositReportController::class, 'depositerLedger']);
Route::get('/api/deposit-reports/depositer-ledger/data',      [DepositReportController::class, 'depositerLedgerData']);
Route::get('/deposit-reports/wgt-amt-summary',                [DepositReportController::class, 'wgtAmtSummary']);
Route::get('/api/deposit-reports/wgt-amt-summary/data',       [DepositReportController::class, 'wgtAmtSummaryData']);
Route::get('/deposit-reports/deposit-book',                   [DepositReportController::class, 'depositBook']);
Route::get('/api/deposit-reports/deposit-book/data',          [DepositReportController::class, 'depositBookData']);
Route::get('/deposit-reports/wgt-balance-summary',            [DepositReportController::class, 'wgtBalanceSummary']);
Route::get('/api/deposit-reports/wgt-balance-summary/data',   [DepositReportController::class, 'wgtBalanceSummaryData']);

/*
|--------------------------------------------------------------------------
| Remake Reports
|--------------------------------------------------------------------------
*/
Route::get('/remake-reports/remake-reports',              [RemakeReportController::class, 'remakeReports']);
Route::get('/api/remake-reports/remake-reports/data',     [RemakeReportController::class, 'remakeReportsData']);
Route::get('/remake-reports/remake-pending',              [RemakeReportController::class, 'remakePending']);
Route::get('/api/remake-reports/remake-pending/data',     [RemakeReportController::class, 'remakePendingData']);
Route::get('/remake-reports/remake-returns',              [RemakeReportController::class, 'remakeReturns']);
Route::get('/api/remake-reports/remake-returns/data',     [RemakeReportController::class, 'remakeReturnsData']);

/*
|--------------------------------------------------------------------------
| Other Item Reports
|--------------------------------------------------------------------------
*/
Route::get('/other-item-reports/sales-book',                     [OtherItemReportController::class, 'salesBook']);
Route::get('/api/other-item-reports/sales-book/data',            [OtherItemReportController::class, 'salesBookData']);
Route::get('/other-item-reports/sales-register',                 [OtherItemReportController::class, 'salesRegister']);
Route::get('/api/other-item-reports/sales-register/data',        [OtherItemReportController::class, 'salesRegisterData']);
Route::get('/other-item-reports/purchase-book',                  [OtherItemReportController::class, 'purchaseBook']);
Route::get('/api/other-item-reports/purchase-book/data',         [OtherItemReportController::class, 'purchaseBookData']);
Route::get('/other-item-reports/purchase-register',              [OtherItemReportController::class, 'purchaseRegister']);
Route::get('/api/other-item-reports/purchase-register/data',     [OtherItemReportController::class, 'purchaseRegisterData']);
Route::get('/other-item-reports/stock-ledger',                   [OtherItemReportController::class, 'stockLedger']);
Route::get('/api/other-item-reports/stock-ledger/data',          [OtherItemReportController::class, 'stockLedgerData']);
Route::get('/other-item-reports/stock-list',                     [OtherItemReportController::class, 'stockList']);
Route::get('/api/other-item-reports/stock-list/data',            [OtherItemReportController::class, 'stockListData']);
Route::get('/api/other-item-reports/items-lookup',               [OtherItemReportController::class, 'itemsLookup']);

// Refinery Returns
Route::get('/refinery-return/{mode?}',                 [RefineryBillController::class, 'returnIndex']);
Route::get('/api/refinery-return/load',                [RefineryBillController::class, 'loadForReturn']);
Route::get('/api/refinery-return/get',                 [RefineryBillController::class, 'getReturn']);
Route::get('/api/refinery-return/search',              [RefineryBillController::class, 'searchForReturn']);
Route::get('/api/refinery-return/search-returns',      [RefineryBillController::class, 'searchReturn']);
Route::post('/api/refinery-return/save',               [RefineryBillController::class, 'saveReturn']);
Route::get('/api/refinery-return/prev',                [RefineryBillController::class, 'prevReturn']);
Route::get('/api/refinery-return/next',                [RefineryBillController::class, 'nextReturn']);

// Sales Return API
Route::get('/api/sales-return/customers',       [SalesReturnController::class, 'customers']);
Route::get('/api/sales-return/salesmen',        [SalesReturnController::class, 'salesmen']);
Route::get('/api/sales-return/cash-banks',      [SalesReturnController::class, 'cashBanks']);
Route::get('/api/sales-return/items',           [SalesReturnController::class, 'items']);
Route::get('/api/sales-return/bill-types',      [SalesReturnController::class, 'billTypes']);
Route::get('/api/sales-return/gold-rate',       [SalesReturnController::class, 'goldRate']);
Route::get('/api/sales-return/next-number',     [SalesReturnController::class, 'nextNumber']);
Route::get('/api/sales-return/list',            [SalesReturnController::class, 'getList']);
Route::get('/api/sales-return/get',             [SalesReturnController::class, 'get']);
Route::get('/api/sales-return/search-sale-bill',[SalesReturnController::class, 'searchSaleBill']);
Route::get('/api/sales-return/search-sale-bills',[SalesReturnController::class, 'searchSaleBills']);
Route::get('/api/sales-return/item-details',    [SalesReturnController::class, 'itemDetails']);
Route::get('/api/sales-return/customer-balance',[SalesReturnController::class, 'customerBalance']);
Route::post('/api/sales-return/save',           [SalesReturnController::class, 'save']);
Route::post('/api/sales-return/delete',         [SalesReturnController::class, 'delete']);

/*
|--------------------------------------------------------------------------
| Admin / User Management
|--------------------------------------------------------------------------
*/
Route::get('/admin/users', [AdminController::class, 'users']);
Route::match(['GET', 'POST'], '/admin/users/add', [AdminController::class, 'addUser']);
Route::match(['GET', 'POST'], '/admin/users/{code}/edit', [AdminController::class, 'editUser']);
Route::post('/admin/users/{code}/delete', [AdminController::class, 'deleteUser']);
Route::match(['GET', 'POST'], '/admin/users/{code}/permissions', [AdminController::class, 'userPermissions']);
Route::get('/admin/users/{code}/history', [AdminController::class, 'userHistory']);

/*
|--------------------------------------------------------------------------
| User Access Control (iframe-friendly)
|--------------------------------------------------------------------------
*/
Route::get('/user-access', [UserAccessController::class, 'index']);
Route::get('/api/user-access/users', [UserAccessController::class, 'getUsers']);
Route::get('/api/user-access/user', [UserAccessController::class, 'getUser']);
Route::post('/api/user-access/save', [UserAccessController::class, 'saveUser']);
Route::post('/api/user-access/delete', [UserAccessController::class, 'deleteUser']);

/*
|--------------------------------------------------------------------------
| Customer / Depositor Module
|--------------------------------------------------------------------------
*/
Route::get('/customer', [NativeCustomerController::class, 'index']);
Route::get('/customer/add', [NativeCustomerController::class, 'add']);
Route::get('/customer/edit', [NativeCustomerController::class, 'edit']);
Route::get('/customer/delete', [NativeCustomerController::class, 'deletePage']);
Route::get('/customer/picture', [NativeCustomerController::class, 'picture']);
Route::match(['GET', 'POST'], '/api/customer/check-phone', [NativeCustomerController::class, 'checkPhone']);
Route::match(['GET', 'POST'], '/api/customer/check-idno', [NativeCustomerController::class, 'checkIdNo']);
Route::get('/api/customer/next-code', [NativeCustomerController::class, 'nextCode']);
Route::get('/api/customer/get', [NativeCustomerController::class, 'get']);
Route::post('/api/customer/save', [NativeCustomerController::class, 'save']);
Route::post('/api/customer/delete', [NativeCustomerController::class, 'delete']);
Route::post('/customer/import', [NativeCustomerController::class, 'importCsv']);
Route::post('/customer/sync-list', [NativeCustomerController::class, 'syncList']);
Route::get('/customer/popup/routes', [CustomerPopupController::class, 'routesPage']);
Route::post('/customer/popup/routes/retrieve', [CustomerPopupController::class, 'routesRetrieve']);
Route::post('/customer/popup/routes/save', [CustomerPopupController::class, 'routesSave']);
Route::get('/customer/popup/area', [CustomerPopupController::class, 'areaPage']);
Route::post('/customer/popup/area/retrieve', [CustomerPopupController::class, 'areaRetrieve']);
Route::post('/customer/popup/area/save', [CustomerPopupController::class, 'areaSave']);
Route::get('/customer/popup/pcard', [CustomerPopupController::class, 'pcardPage']);
Route::post('/customer/popup/pcard/retrieve', [CustomerPopupController::class, 'pcardRetrieve']);
Route::post('/customer/popup/pcard/save', [CustomerPopupController::class, 'pcardSave']);
Route::get('/customer/popup/advanced', [CustomerPopupController::class, 'advancedPage']);

/*
|--------------------------------------------------------------------------
| Barcode Single Entry
|--------------------------------------------------------------------------
*/
Route::get('/barcode-single-entry', [BarcodeEntryController::class, 'index'])->name('barcode-entry.index');
Route::get('/api/barcode-entry/get', [BarcodeEntryController::class, 'get'])->name('barcode-entry.get');
Route::post('/api/barcode-entry/save', [BarcodeEntryController::class, 'save'])->name('barcode-entry.save');
Route::post('/api/barcode-entry/delete', [BarcodeEntryController::class, 'delete'])->name('barcode-entry.delete');
Route::post('/api/barcode-entry/print', [BarcodeEntryController::class, 'printBarcode'])->name('barcode-entry.print');
Route::post('/api/barcode-entry/print-sample', [BarcodeEntryController::class, 'printSample'])->name('barcode-entry.print-sample');
Route::get('/api/barcode-entry/search', [BarcodeEntryController::class, 'search'])->name('barcode-entry.search');
Route::get('/api/barcode-entry/next-barcode', [BarcodeEntryController::class, 'nextBarcode'])->name('barcode-entry.next-barcode');
Route::get('/api/barcode-entry/next-docno', [BarcodeEntryController::class, 'nextDocNo'])->name('barcode-entry.next-docno');
Route::get('/api/barcode-entry/load-item', [BarcodeEntryController::class, 'loadItem'])->name('barcode-entry.load-item');
Route::get('/api/barcode-entry/load-document', [BarcodeEntryController::class, 'loadDocument'])->name('barcode-entry.load-document');
Route::get('/api/barcode-entry/lookups', [BarcodeEntryController::class, 'lookups'])->name('barcode-entry.lookups');
Route::match(['GET', 'POST'], '/api/barcode-entry/action', [BarcodeEntryController::class, 'action'])->name('barcode-entry.action');

// Barcode Settings (JSON editor)
Route::get('/barcode-single-entry/settings', [BarcodeSettingsController::class, 'index'])->name('barcode-settings.index');
Route::get('/barcode-single-entry/settings/get', [BarcodeSettingsController::class, 'get'])->name('barcode-settings.get');
Route::get('/barcode-single-entry/settings/printers', [BarcodeSettingsController::class, 'printers'])->name('barcode-settings.printers');
Route::get('/barcode-single-entry/settings/images', [BarcodeSettingsController::class, 'images'])->name('barcode-settings.images');
Route::post('/barcode-single-entry/settings/upload-image', [BarcodeSettingsController::class, 'uploadImage'])->name('barcode-settings.upload-image');
Route::post('/barcode-single-entry/settings/default-printer', [BarcodeSettingsController::class, 'setDefaultPrinter'])->name('barcode-settings.default-printer');
Route::post('/barcode-single-entry/settings/save', [BarcodeSettingsController::class, 'save'])->name('barcode-settings.save');

// Barcode Multi Entry
Route::get('/barcode-multi-entry',                     [BarcodeMultiEntryController::class, 'index']);
Route::get('/api/barcode-multi/next-barcode',          [BarcodeMultiEntryController::class, 'nextBarcode']);
Route::get('/api/barcode-multi/next-docno',            [BarcodeMultiEntryController::class, 'nextDocNo']);
Route::get('/api/barcode-multi/load-item',             [BarcodeMultiEntryController::class, 'loadItem']);
Route::post('/api/barcode-multi/save',                 [BarcodeMultiEntryController::class, 'save']);
Route::post('/api/barcode-multi/delete',               [BarcodeMultiEntryController::class, 'deleteBarcode']);
Route::get('/api/barcode-multi/load-document',         [BarcodeMultiEntryController::class, 'loadDocument']);

// Counter Issue
Route::get('/counter-issue',                          [CounterIssueController::class, 'index']);
Route::get('/api/counter-issue/items',                [CounterIssueController::class, 'loadItems']);
Route::get('/api/counter-issue/lookup',               [CounterIssueController::class, 'lookupBarcode']);
Route::post('/api/counter-issue/update',              [CounterIssueController::class, 'updateCounter']);
Route::post('/api/counter-issue/refresh',             [CounterIssueController::class, 'refreshCounter']);

// Stock Verification
Route::get('/stock-verification',                      [StockVerificationController::class, 'index']);
Route::get('/api/stock-verification/lookup',           [StockVerificationController::class, 'lookupBarcode']);
Route::post('/api/stock-verification/delete',          [StockVerificationController::class, 'deleteItem']);
Route::post('/api/stock-verification/fresh-mark',      [StockVerificationController::class, 'freshMark']);
Route::post('/api/stock-verification/finished',        [StockVerificationController::class, 'finished']);
Route::post('/api/stock-verification/update-correct',  [StockVerificationController::class, 'updateAsCorrect']);
Route::post('/api/stock-verification/summary',         [StockVerificationController::class, 'summary']);
Route::post('/api/stock-verification/import-file',     [StockVerificationController::class, 'importFile']);

// Staff Transaction
Route::get('/staff-transaction',                        [StaffTransactionController::class, 'index']);
Route::get('/api/staff-transaction/load',               [StaffTransactionController::class, 'loadStaff']);
Route::post('/api/staff-transaction/calc-salary',       [StaffTransactionController::class, 'calcSalary']);
Route::post('/api/staff-transaction/log-based',         [StaffTransactionController::class, 'logBasedCalc']);
Route::post('/api/staff-transaction/save',              [StaffTransactionController::class, 'save']);
Route::get('/api/staff-transaction/lookup-account',     [StaffTransactionController::class, 'lookupAccount']);

// Staff Log Update
Route::get('/staff-log-update',                         [StaffLogUpdateController::class, 'index']);
Route::get('/api/staff-log-update/users',               [StaffLogUpdateController::class, 'loadUsers']);
Route::get('/api/staff-log-update/logs',                [StaffLogUpdateController::class, 'loadLogs']);
Route::post('/api/staff-log-update/update',             [StaffLogUpdateController::class, 'updateLogs']);
Route::post('/api/staff-log-update/delete',             [StaffLogUpdateController::class, 'deleteLog']);
Route::post('/api/staff-log-update/clear',              [StaffLogUpdateController::class, 'clearLogs']);
Route::post('/api/staff-log-update/connect',            [StaffLogUpdateController::class, 'connectDevice']);
Route::post('/api/staff-log-update/download-users',     [StaffLogUpdateController::class, 'downloadUsers']);
Route::post('/api/staff-log-update/download-logs',      [StaffLogUpdateController::class, 'downloadLogs']);
Route::post('/api/staff-log-update/clear-device',       [StaffLogUpdateController::class, 'clearDeviceLogs']);

// Staff Leave Entry
Route::get('/staff-leave-entry',                        [StaffLeaveEntryController::class, 'index']);
Route::get('/api/staff-leave-entry/load',               [StaffLeaveEntryController::class, 'loadData']);
Route::post('/api/staff-leave-entry/save',              [StaffLeaveEntryController::class, 'save']);

// Staff Weight Transaction
Route::get('/staff-wgt-transaction',                        [StaffWgtTransactionController::class, 'index']);
Route::get('/api/staff-wgt-transaction/next-doc',           [StaffWgtTransactionController::class, 'nextDoc']);
Route::get('/api/staff-wgt-transaction/sman',               [StaffWgtTransactionController::class, 'loadSman']);
Route::get('/api/staff-wgt-transaction/lookup-item',        [StaffWgtTransactionController::class, 'lookupItem']);
Route::get('/api/staff-wgt-transaction/search-items',       [StaffWgtTransactionController::class, 'searchItems']);
Route::get('/api/staff-wgt-transaction/search',             [StaffWgtTransactionController::class, 'search']);
Route::get('/api/staff-wgt-transaction/get',                [StaffWgtTransactionController::class, 'get']);
Route::post('/api/staff-wgt-transaction/save',              [StaffWgtTransactionController::class, 'save']);
Route::post('/api/staff-wgt-transaction/cancel',            [StaffWgtTransactionController::class, 'cancel']);

// Expense Voucher Entry
Route::get('/accounts/expense-voucher-entry',               [ExpenseVoucherEntryController::class, 'index']);
Route::get('/api/expense-voucher/bill-types',               [ExpenseVoucherEntryController::class, 'loadBillTypes']);
Route::get('/api/expense-voucher/defaults',                 [ExpenseVoucherEntryController::class, 'defaults']);
Route::get('/api/expense-voucher/next-doc',                 [ExpenseVoucherEntryController::class, 'nextDoc']);
Route::get('/api/expense-voucher/lookup-party',             [ExpenseVoucherEntryController::class, 'lookupParty']);
Route::get('/api/expense-voucher/search-party',             [ExpenseVoucherEntryController::class, 'searchParty']);
Route::get('/api/expense-voucher/search-account',           [ExpenseVoucherEntryController::class, 'searchAccount']);
Route::get('/api/expense-voucher/search',                   [ExpenseVoucherEntryController::class, 'search']);
Route::get('/api/expense-voucher/get',                      [ExpenseVoucherEntryController::class, 'get']);
Route::post('/api/expense-voucher/save',                    [ExpenseVoucherEntryController::class, 'save']);

/*
|--------------------------------------------------------------------------
| Accounts Master Module
|--------------------------------------------------------------------------
*/
Route::get('/accounts/master/account', [AccountMasterController::class, 'account']);
Route::post('/api/accounts/master/account', [AccountMasterController::class, 'apiAccount']);
Route::get('/accounts/master/groups', [AccountMasterController::class, 'groups']);
Route::post('/api/accounts/master/groups', [AccountMasterController::class, 'apiGroups']);
Route::get('/accounts/master/bs-heads', [AccountMasterController::class, 'bsHeads']);
Route::post('/api/accounts/master/bs-heads', [AccountMasterController::class, 'apiBSHeads']);
Route::get('/accounts/master/positions', [AccountMasterController::class, 'positionSettings']);
Route::post('/api/accounts/master/positions', [AccountMasterController::class, 'apiPositions']);
Route::match(['GET', 'POST'], '/accounts/master/book-stock', [AccountMasterController::class, 'bookStock']);
Route::match(['GET', 'POST'], '/accounts/master/op-stock-value-set', [AccountMasterController::class, 'opStockValueSet']);
Route::get('/accounts/master/co-party-limit', [AccountMasterController::class, 'coPartyLimit']);
Route::post('/api/accounts/master/co-party-limit', [AccountMasterController::class, 'apiCoPartyLimit']);
Route::get('/accounts/master/sales-man', [AccountMasterController::class, 'salesMan']);
Route::post('/api/accounts/master/sales-man', [AccountMasterController::class, 'apiSalesMan']);

/*
|--------------------------------------------------------------------------
| Account Receipt / Payment
|--------------------------------------------------------------------------
*/
Route::get('/accounts/receipt', [App\Http\Controllers\ReceiptController::class, 'index']);
Route::post('/api/accounts/receipt', [App\Http\Controllers\ReceiptController::class, 'api']);
Route::get('/accounts/receipt-print', [App\Http\Controllers\ReceiptController::class, 'print']);
Route::get('/accounts/party-code-merge', [App\Http\Controllers\PartyCodeMergeController::class, 'index']);
Route::post('/api/accounts/party-code-merge/preview', [App\Http\Controllers\PartyCodeMergeController::class, 'preview']);
Route::post('/api/accounts/party-code-merge/merge', [App\Http\Controllers\PartyCodeMergeController::class, 'merge']);
Route::get('/accounts/payment', [App\Http\Controllers\PaymentController::class, 'index']);
Route::post('/api/accounts/payment', [App\Http\Controllers\PaymentController::class, 'api']);
Route::get('/accounts/payment-print', [App\Http\Controllers\PaymentController::class, 'print']);
Route::get('/accounts/suspense-entry', [App\Http\Controllers\SuspenseEntryController::class, 'index']);
Route::post('/api/accounts/suspense-entry', [App\Http\Controllers\SuspenseEntryController::class, 'api']);
Route::get('/accounts/suspense-master', [App\Http\Controllers\SuspenseMasterController::class, 'index']);
Route::post('/api/accounts/suspense-master', [App\Http\Controllers\SuspenseMasterController::class, 'api']);
Route::get('/accounts/edit-entry', [App\Http\Controllers\AccountEditEntryController::class, 'index']);
Route::post('/api/accounts/edit-entry', [App\Http\Controllers\AccountEditEntryController::class, 'api']);
Route::get('/accounts/journal', [App\Http\Controllers\JournalController::class, 'index']);
Route::post('/api/accounts/journal', [App\Http\Controllers\JournalController::class, 'api']);
Route::get('/accounts/pdc-collection', [App\Http\Controllers\PdcCollectionController::class, 'index']);
Route::post('/api/accounts/pdc-collection', [App\Http\Controllers\PdcCollectionController::class, 'api']);
Route::get('/accounts/daily-statement', [App\Http\Controllers\DailyStatementController::class, 'index']);
Route::post('/api/accounts/daily-statement', [App\Http\Controllers\DailyStatementController::class, 'api']);

/*
|--------------------------------------------------------------------------
| Financial Statements (P&L, Balance Sheet, Cash Flow)
|--------------------------------------------------------------------------
*/
Route::get('/reports/financial-statements', [App\Http\Controllers\FinancialStatementController::class, 'index']);
Route::post('/api/reports/financial-statements', [App\Http\Controllers\FinancialStatementController::class, 'api']);

/*
|--------------------------------------------------------------------------
| States Adding
|--------------------------------------------------------------------------
*/
Route::get('/states-adding', [NativeStatesController::class, 'index']);
Route::match(['GET', 'POST'], '/api/states-adding', [NativeStatesController::class, 'api']);

/*
|--------------------------------------------------------------------------
| ReOrder Level Table
|--------------------------------------------------------------------------
*/
Route::prefix('/reorder')->name('reorder.')->group(function () {
    Route::get('/', [ReorderController::class, 'index'])->name('index');
    Route::post('/get-item', [ReorderController::class, 'getItem'])->name('getItem');
    Route::post('/save', [ReorderController::class, 'save'])->name('save');
    Route::post('/delete-all', [ReorderController::class, 'deleteAll'])->name('deleteAll');
    Route::get('/search-items', [ReorderController::class, 'searchItems'])->name('searchItems');
});

/*
|--------------------------------------------------------------------------
| Opening Stock Entry
|--------------------------------------------------------------------------
*/
Route::get('/stock/opening-stock', [StockController::class, 'index'])->name('stock.index');
Route::post('/stock/opening-stock/update', [StockController::class, 'update'])->name('stock.update');
Route::get('/stock/list', [StockController::class, 'stockList'])->name('stock.list');
Route::get('/api/stock/list/data', [StockController::class, 'stockListData'])->name('stock.list.data');
Route::get('/stock/ledger', [StockController::class, 'ledger'])->name('stock.ledger');
Route::get('/stock/ledger/export', [StockController::class, 'ledgerExport'])->name('stock.ledger.export');
Route::get('/stock/period-ledger', [StockPeriodLedgerController::class, 'index'])->name('stock.period-ledger');
Route::get('/stock/item-history',  [StockPeriodLedgerController::class, 'itemHistory'])->name('stock.item-history');

/*
|--------------------------------------------------------------------------
| Models Master
|--------------------------------------------------------------------------
*/
Route::prefix('models')->name('models.')->group(function () {
    Route::get('/', [ModelMasterController::class, 'index'])->name('index');
    Route::post('/save', [ModelMasterController::class, 'save'])->name('save');
    Route::post('/delete', [ModelMasterController::class, 'delete'])->name('delete');
    Route::post('/check-duplicate', [ModelMasterController::class, 'checkDuplicate'])->name('check-duplicate');
    Route::post('/get-by-type', [ModelMasterController::class, 'getByType'])->name('get-by-type');
});

/*
|--------------------------------------------------------------------------
| Stock Type Master
|--------------------------------------------------------------------------
*/
Route::prefix('stocktype')->name('stocktype.')->group(function () {
    Route::get('/', [StockTypeController::class, 'index'])->name('index');
    Route::get('/list', [StockTypeController::class, 'getList'])->name('list');
    Route::post('/get-by-code', [StockTypeController::class, 'getByCode'])->name('get-by-code');
    Route::post('/check-code', [StockTypeController::class, 'checkCode'])->name('check-code');
    Route::get('/check-usage/{code}', [StockTypeController::class, 'checkUsage'])->name('check-usage');
    Route::get('/default', [StockTypeController::class, 'getDefault'])->name('default');
    Route::post('/', [StockTypeController::class, 'store'])->name('store');
    Route::put('/{code}', [StockTypeController::class, 'update'])->name('update');
    Route::delete('/{code}', [StockTypeController::class, 'destroy'])->name('destroy');
    Route::get('/module-settings', [StockTypeController::class, 'moduleSettings'])->name('module-settings');
});

/*
|--------------------------------------------------------------------------
| Party MC Table
|--------------------------------------------------------------------------
*/
Route::prefix('partymctable')->name('partymctable.')->group(function () {
    Route::get('/', [PartyMCTableController::class, 'index'])->name('index');
    Route::post('/get-party', [PartyMCTableController::class, 'getParty'])->name('get-party');
    Route::post('/get-mctable', [PartyMCTableController::class, 'getMCTable'])->name('get-mctable');
    Route::get('/items', [PartyMCTableController::class, 'getItems'])->name('items');
    Route::get('/models', [PartyMCTableController::class, 'getModels'])->name('models');
    Route::get('/parties', [PartyMCTableController::class, 'getParties'])->name('parties');
    Route::post('/save', [PartyMCTableController::class, 'save'])->name('save');
    Route::post('/delete-entry', [PartyMCTableController::class, 'deleteEntry'])->name('delete-entry');
});

/*
|--------------------------------------------------------------------------
| MC Table
|--------------------------------------------------------------------------
*/
Route::prefix('mctable')->name('mctable.')->group(function () {
    Route::get('/', [MCTableController::class, 'index'])->name('index');
    Route::post('/get-item', [MCTableController::class, 'getItem'])->name('get-item');
    Route::post('/get-mctable', [MCTableController::class, 'getMCTable'])->name('get-mctable');
    Route::post('/item-types', [MCTableController::class, 'getItemTypes'])->name('item-types');
    Route::post('/save', [MCTableController::class, 'save'])->name('save');
    Route::post('/delete-all', [MCTableController::class, 'deleteAll'])->name('delete-all');
    Route::post('/find-mc', [MCTableController::class, 'findMCForWeight'])->name('find-mc');
    Route::get('/items-list', [MCTableController::class, 'getItemsList'])->name('items-list');
});

/*
|--------------------------------------------------------------------------
| Gift Table
|--------------------------------------------------------------------------
*/
Route::prefix('gift-table')->name('gift-table.')->group(function () {
    Route::get('/', [GiftTableController::class, 'index'])->name('index');
    Route::post('/save', [GiftTableController::class, 'save'])->name('save');
});

/*
|--------------------------------------------------------------------------
| Point Card
|--------------------------------------------------------------------------
*/
Route::prefix('point-card')->name('point-card.')->group(function () {
    Route::get('/', [PointCardController::class, 'index'])->name('index');
    Route::post('/retrieve', [PointCardController::class, 'retrieve'])->name('retrieve');
    Route::post('/save', [PointCardController::class, 'save'])->name('save');
});

/*
|--------------------------------------------------------------------------
| Denomination Master
|--------------------------------------------------------------------------
*/
Route::prefix('denomination-master')->name('denomination-master.')->group(function () {
    Route::get('/', [DenominationMasterController::class, 'index'])->name('index');
    Route::post('/retrieve', [DenominationMasterController::class, 'retrieve'])->name('retrieve');
    Route::post('/save', [DenominationMasterController::class, 'save'])->name('save');
    Route::post('/check-ref', [DenominationMasterController::class, 'checkRef'])->name('check-ref');
});

/*
|--------------------------------------------------------------------------
| Op Bill Creation - Customers
|--------------------------------------------------------------------------
*/
Route::prefix('op-bill-creation/customers')->name('op-bill-creation.customers.')->group(function () {
    Route::get('/', [CustomerOpBillsController::class, 'index'])->name('index');
    Route::post('/lookup-customer', [CustomerOpBillsController::class, 'lookupCustomer'])->name('lookup-customer');
    Route::post('/retrieve-bills', [CustomerOpBillsController::class, 'retrieveBills'])->name('retrieve-bills');
    Route::post('/search-customers', [CustomerOpBillsController::class, 'searchCustomers'])->name('search-customers');
    Route::post('/save', [CustomerOpBillsController::class, 'save'])->name('save');
});

/*
|--------------------------------------------------------------------------
| Item Add/Edit
|--------------------------------------------------------------------------
*/
Route::prefix('item-master')->name('item-master.')->group(function () {
    Route::get('/', [ItemMasterController::class, 'index'])->name('index');
    Route::get('/options', [ItemMasterController::class, 'options'])->name('options');
    Route::get('/search', [ItemMasterController::class, 'search'])->name('search');
    Route::post('/get-item', [ItemMasterController::class, 'getItem'])->name('get-item');
    Route::post('/save', [ItemMasterController::class, 'save'])->name('save');
    Route::post('/delete', [ItemMasterController::class, 'delete'])->name('delete');
});

/*
|--------------------------------------------------------------------------
| Item Groups + Sub Groups
|--------------------------------------------------------------------------
*/
Route::get('/groups', [ItemGroupController::class, 'index'])->name('groups.index');
Route::post('/groups/save', [ItemGroupController::class, 'save'])->name('groups.save');

Route::get('/sub-groups', [ItemSubGroupController::class, 'index'])->name('subgroups.index');
Route::post('/sub-groups/save', [ItemSubGroupController::class, 'save'])->name('subgroups.save');
Route::post('/sub-groups/delete', [ItemSubGroupController::class, 'delete'])->name('subgroups.delete');

/*
|--------------------------------------------------------------------------
| Purity Type + Regional Help
|--------------------------------------------------------------------------
*/
Route::get('/purity-type', [ItemPurityTypeController::class, 'index'])->name('purity-type.index');
Route::post('/purity-type/save', [ItemPurityTypeController::class, 'save'])->name('purity-type.save');
Route::post('/purity-type/check-delete', [ItemPurityTypeController::class, 'checkDelete'])->name('purity-type.check-delete');

Route::get('/regional-help', [RegionalHelpController::class, 'index'])->name('regional-help.index');

/*
|--------------------------------------------------------------------------
| Item Temp + Item Help
|--------------------------------------------------------------------------
*/
Route::get('/item-temp', [ItemTempController::class, 'index'])->name('item-temp.index');
Route::post('/item-temp/save', [ItemTempController::class, 'save'])->name('item-temp.save');
Route::post('/item-temp/delete', [ItemTempController::class, 'delete'])->name('item-temp.delete');
Route::post('/item-temp/item-details', [ItemTempController::class, 'itemDetails'])->name('item-temp.item-details');
Route::post('/item-temp/check-duplicate', [ItemTempController::class, 'checkDuplicate'])->name('item-temp.check-duplicate');
Route::post('/item-temp/check-delete', [ItemTempController::class, 'checkDelete'])->name('item-temp.check-delete');

Route::get('/item-help', [ItemHelpController::class, 'index'])->name('item-help.index');

/*
|--------------------------------------------------------------------------
| Other Items
|--------------------------------------------------------------------------
*/
Route::get('/other-items', [OtherItemsController::class, 'index'])->name('other-items.index');
Route::get('/other-items/help', [OtherItemsController::class, 'help'])->name('other-items.help');
Route::post('/other-items/lookup', [OtherItemsController::class, 'lookup'])->name('other-items.lookup');
Route::post('/other-items/add', [OtherItemsController::class, 'add'])->name('other-items.add');
Route::post('/other-items/edit', [OtherItemsController::class, 'edit'])->name('other-items.edit');
Route::post('/other-items/delete', [OtherItemsController::class, 'delete'])->name('other-items.delete');

/*
|--------------------------------------------------------------------------
| Counters
|--------------------------------------------------------------------------
*/
Route::get('/counters', [CountersController::class, 'index'])->name('counters.index');
Route::post('/counters/save', [CountersController::class, 'save'])->name('counters.save');
Route::post('/counters/delete', [CountersController::class, 'delete'])->name('counters.delete');

/*
|--------------------------------------------------------------------------
| Wastage Table
|--------------------------------------------------------------------------
*/
Route::get('/wastage-table', [WastageTableController::class, 'index'])->name('wastage-table.index');
Route::post('/wastage-table/validate-item', [WastageTableController::class, 'validateItem'])->name('wastage-table.validate-item');
Route::post('/wastage-table/show', [WastageTableController::class, 'showRows'])->name('wastage-table.show');
Route::post('/wastage-table/save', [WastageTableController::class, 'save'])->name('wastage-table.save');

/*
|--------------------------------------------------------------------------
| Bill Prefix
|--------------------------------------------------------------------------
*/
Route::get('/bill-prefix', [BillPrefixController::class, 'index'])->name('bill-prefix.index');
Route::post('/bill-prefix/retrieve', [BillPrefixController::class, 'retrieve'])->name('bill-prefix.retrieve');
Route::post('/bill-prefix/save', [BillPrefixController::class, 'save'])->name('bill-prefix.save');
Route::post('/bill-prefix/check-delete', [BillPrefixController::class, 'checkDelete'])->name('bill-prefix.check-delete');

/*
|--------------------------------------------------------------------------
| Repair Complaints
|--------------------------------------------------------------------------
*/
Route::get('/repair-complaints', [RepairComplaintsController::class, 'index'])->name('repair-complaints.index');
Route::get('/remake-rcpt-memo-to-party/{mode?}', [RepairReceiptMemoPartyController::class, 'index']);
Route::get('/api/remake-rcpt-memo-to-party/next', [RepairReceiptMemoPartyController::class, 'nextDoc']);
Route::get('/api/remake-rcpt-memo-to-party/search', [RepairReceiptMemoPartyController::class, 'search']);
Route::get('/api/remake-rcpt-memo-to-party/get', [RepairReceiptMemoPartyController::class, 'get']);
Route::post('/api/remake-rcpt-memo-to-party/save', [RepairReceiptMemoPartyController::class, 'save']);
Route::post('/api/remake-rcpt-memo-to-party/cancel', [RepairReceiptMemoPartyController::class, 'cancel']);
Route::get('/api/remake-rcpt-memo-to-party/item', [RepairReceiptMemoPartyController::class, 'lookupItem']);
Route::get('/api/remake-rcpt-memo-to-party/items', [RepairReceiptMemoPartyController::class, 'searchItems']);
Route::get('/api/remake-rcpt-memo-to-party/customer', [RepairReceiptMemoPartyController::class, 'lookupCustomer']);
Route::get('/api/remake-rcpt-memo-to-party/customers', [RepairReceiptMemoPartyController::class, 'searchCustomers']);
// Repair Return (RM4)
Route::get('/repair-return/picker/{action?}', [RepairReturnController::class, 'picker']);
Route::get('/repair-return/{mode?}', [RepairReturnController::class, 'index']);
Route::get('/api/repair-return/next', [RepairReturnController::class, 'nextDoc']);
Route::get('/api/repair-return/search', [RepairReturnController::class, 'search']);
Route::get('/api/repair-return/picker-search', [RepairReturnController::class, 'pickerSearch']);
Route::post('/api/repair-return/picker-resolve', [RepairReturnController::class, 'pickerResolve']);
Route::get('/api/repair-return/get', [RepairReturnController::class, 'get']);
Route::get('/api/repair-return/load-receipt', [RepairReturnController::class, 'loadReceipt']);
Route::get('/api/repair-return/search-receipts', [RepairReturnController::class, 'searchReceipts']);
Route::post('/api/repair-return/save', [RepairReturnController::class, 'save']);
Route::post('/api/repair-return/cancel', [RepairReturnController::class, 'cancel']);
Route::get('/api/repair-return/customer', [RepairReturnController::class, 'lookupCustomer']);

Route::post('/repair-complaints/retrieve', [RepairComplaintsController::class, 'retrieve'])->name('repair-complaints.retrieve');
Route::post('/repair-complaints/save', [RepairComplaintsController::class, 'save'])->name('repair-complaints.save');
Route::post('/repair-complaints/lookup-item', [RepairComplaintsController::class, 'lookupItem'])->name('repair-complaints.lookup-item');
Route::post('/repair-complaints/help-list', [RepairComplaintsController::class, 'helpList'])->name('repair-complaints.help-list');

/*
|--------------------------------------------------------------------------
| Smith / Goldsmith API
|--------------------------------------------------------------------------
*/
Route::get('/api/smith', [SmithController::class, 'index']);
Route::get('/api/smith/photo', [SmithController::class, 'photo']);
Route::get('/api/smith/groups', [SmithController::class, 'groups']);
Route::get('/api/smith/states', [SmithController::class, 'states']);
Route::get('/api/smith/search', [SmithController::class, 'search']);
Route::get('/api/smith/{code}', [SmithController::class, 'show']);
Route::post('/api/smith', [SmithController::class, 'store']);
Route::put('/api/smith/{code}', [SmithController::class, 'update']);
Route::delete('/api/smith/{code}', [SmithController::class, 'destroy']);

/*
|--------------------------------------------------------------------------
| Order Update from Jewelleries
|--------------------------------------------------------------------------
*/
Route::get('/order-update',                      [OrderUpdateController::class, 'index']);
Route::get('/api/order-update/list',             [OrderUpdateController::class, 'listOrders']);
Route::post('/api/order-update/import-csv',      [OrderUpdateController::class, 'importCsv']);
Route::post('/api/order-update/save-import',     [OrderUpdateController::class, 'saveImport']);
Route::post('/api/order-update/save-manual',     [OrderUpdateController::class, 'saveManual']);
Route::post('/api/order-update/delete',          [OrderUpdateController::class, 'deleteOrder']);

/*
|--------------------------------------------------------------------------
| Order Block/Unblock
|--------------------------------------------------------------------------
*/
Route::get('/order-block',                       [OrderBlockController::class, 'index']);
Route::get('/api/order-block/search',            [OrderBlockController::class, 'searchOrders']);
Route::get('/api/order-block/get',               [OrderBlockController::class, 'getOrder']);
Route::post('/api/order-block/toggle',           [OrderBlockController::class, 'toggleBlock']);

/*
|--------------------------------------------------------------------------
| Kuri / Scheme Details
|--------------------------------------------------------------------------
*/
Route::get('/kuri-details',                      [App\Http\Controllers\KuriDetailsController::class, 'index']);
Route::post('/api/kuri-details/search',          [App\Http\Controllers\KuriDetailsController::class, 'search']);
Route::post('/api/kuri-details/load',            [App\Http\Controllers\KuriDetailsController::class, 'load']);
Route::post('/api/kuri-details/next-code',       [App\Http\Controllers\KuriDetailsController::class, 'nextCode']);
Route::post('/api/kuri-details/save',            [App\Http\Controllers\KuriDetailsController::class, 'save']);
Route::post('/api/kuri-details/delete',          [App\Http\Controllers\KuriDetailsController::class, 'delete']);

/*
|--------------------------------------------------------------------------
| Kuri / Scheme Collection
|--------------------------------------------------------------------------
*/
Route::get('/kuri-collection',                   [App\Http\Controllers\KuriCollectionController::class, 'index']);
Route::post('/api/kuri-collection/init',         [App\Http\Controllers\KuriCollectionController::class, 'init']);
Route::post('/api/kuri-collection/party-search', [App\Http\Controllers\KuriCollectionController::class, 'partySearch']);
Route::post('/api/kuri-collection/party-lookup', [App\Http\Controllers\KuriCollectionController::class, 'partyLookup']);
Route::post('/api/kuri-collection/load',         [App\Http\Controllers\KuriCollectionController::class, 'load']);
Route::post('/api/kuri-collection/save',         [App\Http\Controllers\KuriCollectionController::class, 'save']);
Route::post('/api/kuri-collection/delete',       [App\Http\Controllers\KuriCollectionController::class, 'delete']);

/*
|--------------------------------------------------------------------------
| Passbook Print
|--------------------------------------------------------------------------
*/
Route::get('/passbook-print',                    [App\Http\Controllers\PassbookPrintController::class, 'index']);
Route::post('/api/passbook-print/init',          [App\Http\Controllers\PassbookPrintController::class, 'init']);
Route::post('/api/passbook-print/party-search',  [App\Http\Controllers\PassbookPrintController::class, 'partySearch']);
Route::post('/api/passbook-print/party-lookup',  [App\Http\Controllers\PassbookPrintController::class, 'partyLookup']);
Route::post('/api/passbook-print/build',         [App\Http\Controllers\PassbookPrintController::class, 'build']);
Route::post('/api/passbook-print/address',       [App\Http\Controllers\PassbookPrintController::class, 'address']);

/*
|--------------------------------------------------------------------------
| Kuri Type Master
|--------------------------------------------------------------------------
*/
Route::get('/kuri-type-master',                  [App\Http\Controllers\KuriTypeMasterController::class, 'index']);
Route::get('/api/kuri-type-master/load',         [App\Http\Controllers\KuriTypeMasterController::class, 'load']);
Route::post('/api/kuri-type-master/save',        [App\Http\Controllers\KuriTypeMasterController::class, 'save']);
Route::post('/api/kuri-type-master/check-usage', [App\Http\Controllers\KuriTypeMasterController::class, 'checkUsage']);
Route::post('/api/kuri-type-master/get-type',    [App\Http\Controllers\KuriTypeMasterController::class, 'getType']);

/*
|--------------------------------------------------------------------------
| Day Summary
|--------------------------------------------------------------------------
*/
Route::get('/day-summary',                [App\Http\Controllers\DaySummaryController::class, 'index']);
Route::get('/api/day-summary/data',       [App\Http\Controllers\DaySummaryController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Stock Register
|--------------------------------------------------------------------------
*/
Route::get('/stock-register',             [App\Http\Controllers\StockRegisterController::class, 'index']);
Route::get('/api/stock-register/lookups', [App\Http\Controllers\StockRegisterController::class, 'lookups']);
Route::get('/api/stock-register/data',    [App\Http\Controllers\StockRegisterController::class, 'data']);
Route::get('/stock-register-summary',     [App\Http\Controllers\StockRegisterController::class, 'summaryIndex']);
Route::get('/api/stock-register-summary/data', [App\Http\Controllers\StockRegisterController::class, 'summaryData']);

/*
|--------------------------------------------------------------------------
| Smith Book (Tax Reports)
|--------------------------------------------------------------------------
*/
Route::get('/smith-book',                 [App\Http\Controllers\SmithBookController::class, 'index']);
Route::get('/api/smith-book/data',        [App\Http\Controllers\SmithBookController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Goldsmith / Jewellery Wgt/Amt Summary
|--------------------------------------------------------------------------
*/
Route::get('/smith-wa-summary',           [App\Http\Controllers\SmithWaSummaryController::class, 'index']);
Route::get('/api/smith-wa-summary/data',  [App\Http\Controllers\SmithWaSummaryController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Goldsmith / Jewellery Wgt/Amt Analysis (date-range)
|--------------------------------------------------------------------------
*/
Route::get('/smith-wa-analysis',           [App\Http\Controllers\SmithWaAnalysisController::class, 'index']);
Route::get('/api/smith-wa-analysis/data',  [App\Http\Controllers\SmithWaAnalysisController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Goldsmith / Jewellery Ageing Report
|--------------------------------------------------------------------------
*/
Route::get('/smith-ageing-report',           [App\Http\Controllers\SmithAgeingReportController::class, 'index']);
Route::get('/api/smith-ageing-report/data',  [App\Http\Controllers\SmithAgeingReportController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Goldsmith / Jewellery Transaction Summary
|--------------------------------------------------------------------------
*/
Route::get('/smith-trans-summary',           [App\Http\Controllers\SmithTransSummaryController::class, 'index']);
Route::get('/api/smith-trans-summary/data',  [App\Http\Controllers\SmithTransSummaryController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Smith/Jewl Lot Report
|--------------------------------------------------------------------------
*/
Route::get('/smith-lot-report',           [App\Http\Controllers\SmithLotReportController::class, 'index']);
Route::get('/api/smith-lot-report/data',  [App\Http\Controllers\SmithLotReportController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Smith/Jewl Fix/Unfix Report
|--------------------------------------------------------------------------
*/
Route::get('/smith-fixunfix-report',           [App\Http\Controllers\SmithFixUnfixReportController::class, 'index']);
Route::get('/api/smith-fixunfix-report/data',  [App\Http\Controllers\SmithFixUnfixReportController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Smith List
|--------------------------------------------------------------------------
*/
Route::get('/smith-list',           [App\Http\Controllers\SmithListController::class, 'index']);
Route::get('/api/smith-list/data',  [App\Http\Controllers\SmithListController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Sales/Purchase Item Report (Jewel Summary)
|--------------------------------------------------------------------------
*/
Route::get('/jewel-summary',           [App\Http\Controllers\JewelSummaryController::class, 'index']);
Route::get('/api/jewel-summary/data',  [App\Http\Controllers\JewelSummaryController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Jewellery Profit Report
|--------------------------------------------------------------------------
*/
Route::get('/jewl-profit-report',           [App\Http\Controllers\JewlProfitReportController::class, 'index']);
Route::get('/api/jewl-profit-report/data',  [App\Http\Controllers\JewlProfitReportController::class, 'data']);

/*
|--------------------------------------------------------------------------
| TDS Report
|--------------------------------------------------------------------------
*/
Route::get('/tds-report',           [App\Http\Controllers\TdsReportController::class, 'index']);
Route::get('/api/tds-report/data',  [App\Http\Controllers\TdsReportController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Extra Amt Report
|--------------------------------------------------------------------------
*/
Route::get('/extra-amt-report',           [App\Http\Controllers\ExtraAmtReportController::class, 'index']);
Route::get('/api/extra-amt-report/data',  [App\Http\Controllers\ExtraAmtReportController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Account Summary (A/c Summary)
|--------------------------------------------------------------------------
*/
Route::get('/ac-summary',           [App\Http\Controllers\AcSummaryController::class, 'index']);
Route::get('/api/ac-summary/data',  [App\Http\Controllers\AcSummaryController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Jewellery Transactions Report (Send Mail To Jewl)
|--------------------------------------------------------------------------
*/
Route::get('/jewl-rep-email',              [App\Http\Controllers\JewlRepEmailController::class, 'index']);
Route::get('/api/jewl-rep-email/data',     [App\Http\Controllers\JewlRepEmailController::class, 'data']);
Route::get('/api/jewl-rep-email/lookup',   [App\Http\Controllers\JewlRepEmailController::class, 'lookup']);

/*
|--------------------------------------------------------------------------
| Cash Flow Statement
|--------------------------------------------------------------------------
*/
Route::get('/cash-flow-report',           [App\Http\Controllers\CashFlowReportController::class, 'index']);
Route::get('/api/cash-flow-report/data',  [App\Http\Controllers\CashFlowReportController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Integrity Checking
|--------------------------------------------------------------------------
*/
Route::get('/integrity-checking',              [App\Http\Controllers\IntegrityCheckingController::class, 'index']);
Route::get('/api/integrity-checking/check',   [App\Http\Controllers\IntegrityCheckingController::class, 'check']);

/*
|--------------------------------------------------------------------------
| Tax Purchase Book
|--------------------------------------------------------------------------
*/
Route::get('/tax-purchase-book',                 [App\Http\Controllers\TaxPurchaseBookController::class, 'index']);
Route::get('/api/tax-purchase-book/lookups',     [App\Http\Controllers\TaxPurchaseBookController::class, 'lookups']);
Route::get('/api/tax-purchase-book/data',        [App\Http\Controllers\TaxPurchaseBookController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Jewllery Book (Tax Reports)
|--------------------------------------------------------------------------
*/
Route::get('/jewllery-book',                 [App\Http\Controllers\JewlleryBookController::class, 'index']);
Route::get('/api/jewllery-book/lookups',     [App\Http\Controllers\JewlleryBookController::class, 'lookups']);
Route::get('/api/jewllery-book/data',        [App\Http\Controllers\JewlleryBookController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Outstanding Tax Report
|--------------------------------------------------------------------------
*/
Route::get('/outstanding-tax',           [App\Http\Controllers\OutstandingTaxReportController::class, 'index']);
Route::get('/api/outstanding-tax/data',  [App\Http\Controllers\OutstandingTaxReportController::class, 'data']);

/*
|--------------------------------------------------------------------------
| Goldsmith Transactions
|--------------------------------------------------------------------------
*/
Route::get('/goldsmith-transactions-report',       [App\Http\Controllers\GoldsmithTransactionsController::class, 'index']);
Route::get('/api/goldsmith-transactions/lookups',  [App\Http\Controllers\GoldsmithTransactionsController::class, 'lookups']);
Route::get('/api/goldsmith-transactions/data',     [App\Http\Controllers\GoldsmithTransactionsController::class, 'data']);

Route::get('/reports/avg-rate-profit', [App\Http\Controllers\AvgRateProfitController::class, 'index']);
