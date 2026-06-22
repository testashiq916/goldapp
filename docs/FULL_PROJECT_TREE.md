# GoldApp Full Project Tree

This is the practical module tree for the complete jewellery ERP project. It shows the full application areas, CRUD modules, double-entry accounting flow, stock management flow, reports, and integration points.

## Root Structure

```text
goldapp/
+-- app/
|   +-- Enums/
|   |   +-- Permission.php
|   +-- Http/
|   |   +-- Controllers/
|   |   |   +-- NativeAuthController.php
|   |   |   +-- NativeDashboardController.php
|   |   |   +-- NativeCustomerController.php
|   |   |   +-- AccountMasterController.php
|   |   |   +-- SalesBillController.php
|   |   |   +-- PurchaseBillController.php
|   |   |   +-- ReceiptController.php
|   |   |   +-- PaymentController.php
|   |   |   +-- JournalController.php
|   |   |   +-- StockController.php
|   |   |   +-- BarcodeEntryController.php
|   |   |   +-- ItemMasterController.php
|   |   |   +-- CustomerReportsController.php
|   |   |   +-- SupplierReportsController.php
|   |   |   +-- StockRegisterController.php
|   |   |   +-- FinancialStatementController.php
|   |   |   +-- RateController.php
|   |   |   +-- EInvoiceRegisterController.php
|   |   |   +-- AdministrationController.php
|   |   +-- Middleware/
|   |   +-- Requests/
|   |   +-- Resources/
|   +-- Models/
|   +-- Services/
|   +-- Support/
+-- config/
+-- database/
|   +-- migrations/
|   +-- seeders/
|   +-- factories/
+-- public/
|   +-- css/
|   +-- js/
|   +-- uploads/
+-- resources/
|   +-- views/
|   +-- css/
|   +-- js/
+-- routes/
|   +-- web.php
|   +-- console.php
+-- storage/
+-- tests/
+-- docs/
```

## Authentication And Dashboard

```text
Auth & Shell
├── Login / logout
├── Company select / database switch
├── User session
├── User permission access
├── Native dashboard
├── Sidebar and top menu
├── Startup popups
│   ├── Gold rate update popup
│   └── Today due pending accounts popup
└── Quick search / phone lookup
```

Main files:

```text
app/Http/Controllers/NativeAuthController.php
app/Http/Controllers/NativeDashboardController.php
app/Http/Controllers/UserAccessController.php
resources/views/native/login.blade.php
resources/views/native/dashboard.blade.php
resources/views/user-access/index.blade.php
```

## Master CRUD Modules

```text
Masters
├── Customer CRUD
├── Supplier CRUD
├── Goldsmith CRUD
├── Staff CRUD
├── Refiner CRUD
├── Jewellery party CRUD
├── Depositor CRUD
├── Account master CRUD
├── Account group CRUD
├── Item master CRUD
├── Item group CRUD
├── Item subgroup CRUD
├── Stock type CRUD
├── Purity type CRUD
├── Model CRUD
├── MC table CRUD
├── Party MC table CRUD
├── Wastage table CRUD
├── Counter CRUD
├── Salesman CRUD
├── Denomination master CRUD
├── Gift table CRUD
├── Point card CRUD
├── Bill prefix CRUD
├── Country / currency settings
└── Application settings
```

Main files:

```text
app/Http/Controllers/NativeCustomerController.php
app/Http/Controllers/AccountMasterController.php
app/Http/Controllers/ItemMasterController.php
app/Http/Controllers/ItemGroupController.php
app/Http/Controllers/ItemSubGroupController.php
app/Http/Controllers/StockTypeController.php
app/Http/Controllers/ModelMasterController.php
app/Http/Controllers/MCTableController.php
app/Http/Controllers/PartyMCTableController.php
app/Http/Controllers/WastageTableController.php
app/Http/Controllers/CountersController.php
app/Http/Controllers/DenominationMasterController.php
app/Http/Controllers/GiftTableController.php
app/Http/Controllers/PointCardController.php
app/Http/Controllers/BillPrefixController.php
resources/views/native/customer/
resources/views/account/
resources/views/items/
resources/views/groups/
resources/views/subgroups/
resources/views/stocktype/
resources/views/models/
resources/views/mctable/
resources/views/partymctable/
resources/views/wastage-table/
```

## Transaction CRUD Modules

```text
Transactions
├── Sales bill
│   ├── Add
│   ├── Edit
│   ├── Cancel
│   ├── Reprint
│   ├── Confirmation
│   └── E-invoice
├── Sales return
├── Purchase bill
├── Purchase return
├── Diamond purchase
├── Diamond purchase return
├── Receipt
├── Payment
├── Journal entry
├── Debit / credit note
├── Expense voucher
├── Amount / weight transfer
├── Rate difference adjustment
├── PDC collection
├── Denomination entry
├── Stock adjustment
├── Barcode entry
├── Barcode multi-entry
├── Counter issue
├── Goldsmith transaction
├── Jewellery transaction
├── Repair receipt memo party
├── Repair return
├── Remake issue memo party
├── Order bill
├── Order advance after
├── Order sale
├── Order update
├── Order block / cancel
├── Order rate fix
├── Refinery bill
├── Kuri collection
├── Kuri finish
└── Staff transactions
```

Main files:

```text
app/Http/Controllers/SalesBillController.php
app/Http/Controllers/SalesReturnController.php
app/Http/Controllers/PurchaseBillController.php
app/Http/Controllers/PurchaseReturnController.php
app/Http/Controllers/DiamondPurchaseBillController.php
app/Http/Controllers/DiamondPurchaseReturnController.php
app/Http/Controllers/ReceiptController.php
app/Http/Controllers/PaymentController.php
app/Http/Controllers/JournalController.php
app/Http/Controllers/DebitCreditNoteController.php
app/Http/Controllers/ExpenseVoucherEntryController.php
app/Http/Controllers/AmtWgtTransferController.php
app/Http/Controllers/RateDiffAdjustmentController.php
app/Http/Controllers/BarcodeEntryController.php
app/Http/Controllers/BarcodeMultiEntryController.php
app/Http/Controllers/GoldsmithTransactionController.php
app/Http/Controllers/RepairReceiptMemoPartyController.php
app/Http/Controllers/RepairReturnController.php
app/Http/Controllers/RemakeIssueMemoPartyController.php
app/Http/Controllers/OrderBillController.php
app/Http/Controllers/OrderAdvanceAfterController.php
app/Http/Controllers/OrderSaleController.php
app/Http/Controllers/RefineryBillController.php
resources/views/sales-bill/
resources/views/sales-return/
resources/views/purchase-bill/
resources/views/purchase-return/
resources/views/diamond-purchase/
resources/views/diamond-purchase-return/
resources/views/account/receipt.blade.php
resources/views/account/payment.blade.php
resources/views/account/journal.blade.php
resources/views/goldsmith-transactions/
resources/views/repair-receipt-memo-party/
resources/views/repair-return/
resources/views/order-bill/
resources/views/order-advance-after/
resources/views/order-sale/
resources/views/refinery-bill/
```

## Double-Entry Accounting Tree

```text
Double Entry Accounting
├── Chart of accounts
│   ├── Account groups
│   ├── Account master
│   ├── Customer / supplier ledger accounts
│   ├── Cash / bank accounts
│   ├── Sales / purchase accounts
│   ├── Tax accounts
│   └── Round-off / discount accounts
├── Posting engine
│   ├── Sales bill posting
│   ├── Sales return posting
│   ├── Purchase bill posting
│   ├── Purchase return posting
│   ├── Receipt posting
│   ├── Payment posting
│   ├── Journal posting
│   ├── Debit / credit note posting
│   ├── Expense posting
│   └── Repair / order / refinery posting
├── Ledger tables
│   ├── daybook
│   ├── daybookpart
│   └── daybookratewgt
├── Balance rules
│   ├── Debit amount
│   ├── Credit amount
│   ├── To receive
│   ├── To give
│   ├── Opening balance
│   ├── Closing balance
│   └── Control level
└── Accounting reports
    ├── Account ledger
    ├── Cash book
    ├── Bank book
    ├── Daybook
    ├── Trial balance
    ├── Profit and loss
    ├── Balance sheet
    ├── Cash flow
    ├── Receivable / payable
    └── Group summary
```

Main files:

```text
app/Http/Controllers/AccountLedgerController.php
app/Http/Controllers/DayBookController.php
app/Http/Controllers/CashBookController.php
app/Http/Controllers/BankBookController.php
app/Http/Controllers/JournalController.php
app/Http/Controllers/ReceiptController.php
app/Http/Controllers/PaymentController.php
app/Http/Controllers/FinancialStatementController.php
app/Http/Controllers/AcReceivablePayableSummaryController.php
app/Http/Controllers/GroupLedgerController.php
app/Http/Controllers/GroupAccountSummaryController.php
resources/views/reports/account-ledger.blade.php
resources/views/reports/cash-book.blade.php
resources/views/reports/bank-book.blade.php
resources/views/reports/day-summary.blade.php
resources/views/reports/financial-statements.blade.php
resources/views/reports/ac-receivable-payable-summary.blade.php
```

## Stock Management Tree

```text
Stock Management
├── Item master
├── Opening stock
├── Barcode stock
├── Counter stock
├── Purchase inward
├── Sales outward
├── Sales return inward
├── Purchase return outward
├── Stock adjustment
├── Add / less stock
├── Suspense stock
├── Stock verification
├── Diamond / stone stock
├── Other item stock
├── Weight ledger
├── Rate-wise summary
├── Cost-wise summary
├── Stock register
├── Item history
└── Reorder level
```

Main files:

```text
app/Http/Controllers/StockController.php
app/Http/Controllers/StockPeriodLedgerController.php
app/Http/Controllers/StockRegisterController.php
app/Http/Controllers/StockSummaryCostWiseController.php
app/Http/Controllers/StockSummaryRateWiseController.php
app/Http/Controllers/StockSuspenseEntryController.php
app/Http/Controllers/StockVerificationController.php
app/Http/Controllers/ItemAdjustmentController.php
app/Http/Controllers/ItemMovementController.php
app/Http/Controllers/BarcodeEntryController.php
app/Http/Controllers/BarcodeListController.php
app/Http/Controllers/DiamondStoneStockController.php
app/Http/Controllers/ReorderController.php
resources/views/stock/
resources/views/stock-verification/
resources/views/stock-suspense-entry/
resources/views/stock-summary-cost-wise/
resources/views/stock-summary-rate-wise/
resources/views/item-adjustment/
resources/views/barcode-list/
resources/views/reorder/
```

## Reports Tree

```text
Reports
├── Sales reports
│   ├── Sales book
│   ├── Sales register
│   ├── Sales checklist
│   ├── Sales return register
│   ├── Monthly sales
│   ├── Net sales
│   └── Salesman category
├── Purchase reports
│   ├── Purchase book
│   ├── Purchase register
│   ├── Purchase checklist
│   └── Purchase return register
├── Account reports
│   ├── Ledger
│   ├── Cash book
│   ├── Bank book
│   ├── Daybook
│   ├── Trial balance
│   ├── P&L
│   ├── Balance sheet
│   ├── Receivable / payable
│   └── Tax outstanding
├── Customer reports
│   ├── Customer list
│   ├── Credit bill details
│   ├── Due date report
│   ├── Billwise details
│   ├── Received details
│   └── Party history
├── Supplier reports
├── Stock reports
├── Barcode reports
├── Goldsmith reports
├── Smith reports
├── Repair / remake reports
├── Order reports
├── Refinery reports
├── Deposit reports
├── Staff reports
├── Integrity checking
└── Export / print / PDF
```

## Rate And E-Invoice

```text
Rate & Compliance
├── Gold rate update
├── Rate history
│   ├── 22K
│   ├── 18K
│   ├── 14K
│   ├── 9K
│   ├── 4K
│   ├── Old gold
│   ├── Silver
│   ├── Old silver
│   └── TH / platinum / bullion
├── Gold rate story maker
├── E-invoice register
├── E-invoice JSON service
└── GST / tax reports
```

Main files:

```text
app/Http/Controllers/RateController.php
app/Http/Controllers/GoldRateStoryController.php
app/Http/Controllers/EInvoiceRegisterController.php
app/Support/LegacyEInvoiceJsonService.php
resources/views/rate/
resources/views/e-invoice-register/
```

## Admin And System Tools

```text
System Tools
├── User management
├── User permissions
├── Backup
├── Local backup
├── Data transfer
├── Administration tools
├── SQL update
├── Stock update
├── Re-arrange document numbers
├── Change document numbers
├── Initialise document numbers
├── Year-end account close
├── Application settings
├── Country / currency settings
├── AI insights
└── Reminders
```

## Minimum Full CRUD Pattern

Every full CRUD module should follow this shape:

```text
ModuleName
├── index/list
├── create/add
├── store/save
├── edit/load
├── update
├── delete/cancel
├── search/help
├── print
├── export
├── permission check
├── validation
├── audit log
└── API endpoints when needed
```

## Minimum Double Entry Posting Pattern

Every financial transaction should post like this:

```text
Transaction Save
├── Validate header
├── Validate details
├── Generate document number
├── Save master row
├── Save detail rows
├── Delete old posting if edit
├── Insert debit row in daybook
├── Insert credit row in daybook
├── Insert narration in daybookpart
├── Insert weight posting if required
├── Update stock if item movement exists
├── Update party balance if maintained
├── Audit log
└── Commit transaction
```

## Minimum Stock Posting Pattern

Every stock transaction should post like this:

```text
Stock Transaction
├── Read item / barcode / purity
├── Calculate gross weight
├── Calculate less weight
├── Calculate net weight
├── Calculate touch / purity
├── Calculate fine weight
├── Calculate stone / diamond values
├── Save item detail
├── Update barcode stock
├── Update item stock
├── Update counter stock
├── Update stock ledger
├── Link accounting posting
└── Audit log
```

## Route Entry Point

```text
routes/web.php
├── Authentication routes
├── Dashboard routes
├── Master CRUD routes
├── Transaction CRUD routes
├── Accounting routes
├── Stock routes
├── Report routes
├── API routes
├── Admin routes
└── Integration routes
```

This project currently has more than 1000 route definitions, so route grouping should be gradually refactored into separate route files when the app is stabilized.
