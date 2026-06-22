# GoldApp Accounting Posting Logic

Generated from the project at `c:\xampp\htdocs\goldapp`.

## Scope

This note documents the account master, account grouping/tagging area, daybook, daybookpart, daybookratewgt, transaction master relationship, and posting rules found in the current Laravel project.

The user term `accotgtag` was not found as an exact table, route, controller, or symbol. In this document it is treated as the account group/tag/classification logic around `accountm`, `accountg`, `accountgbs`, account type fields, and report positions.

## Core Tables

### accountm

`accountm` is the account master. Live/demo columns include:

- `accode`: account code, primary key, char(8).
- `name`: account name.
- `actype1`: broad account class. The account master accepts `R`, `E`, `A`, `L` for revenue, expense, asset, and liability.
- `actype2`: special account type marker. Common meanings in the code include `H` for cash, `B` for bank, and linked party categories such as customer, supplier, smith, goldsmith, refinery, and staff-style accounts.
- `reserve`: reserved account marker.
- `grcode`: link to `accountg`.
- `bshead`: link to `accountgbs` for asset/liability accounts.
- `opbal`, `opbalb`: opening balance for control level 1 and secondary level.
- `control`: visibility/control flag.
- `tplpos`, `shepos`, `shedgrp`: report/schedule placement fields.
- `sp`, `removed`, `blocked`, `note`: status and reporting flags.
- `opwgt`, `opwgtb`: opening weight fields where weight-ledger logic uses them.

Opening amount sign convention in `AccountMasterController`:

- Debit opening balance is stored as negative.
- Credit opening balance is stored as positive.
- The active opening column is `opbal` when session `gilevel` is 1, otherwise `opbalb`.

Account save validation:

- `accode`, description, and group are compulsory.
- `bshead` is compulsory for asset/liability accounts.
- Existing account codes cannot be duplicated.
- Account code cannot be changed after transactions exist in `daybook`.
- Reserved accounts and linked party accounts cannot be deleted through the normal account master.
- Accounts with `daybook` transactions cannot be deleted.

### accountg

`accountg` is the account group master. Important columns are:

- `grcode`: group code.
- `name`: group name.
- `actype1`: group class.
- `reserve`: reserved group marker.
- `pos`, `position`, `grp`, `mgrp`, `bscode`: report positioning and grouping fields.

Group delete validation blocks deletion when:

- The group is reserved.
- Any `accountm` row uses the group code.

### accountgbs

`accountgbs` is the balance sheet head master. Important columns are:

- `hcode`: head code.
- `hname`: head name.
- `actype1`: asset/liability classification.
- `pos`: report position.
- `reserve`: reserved marker.
- `expand`: report expand/collapse flag.
- `amount`: optional stored amount.

Balance sheet head delete validation blocks deletion when:

- The head is reserved.
- Any `accountm.bshead` uses the head code.

### daybook

`daybook` is the ledger posting table. Live/demo columns include:

- `slno`: shared transaction serial number.
- `sno`: line number within the transaction.
- `tdate`: transaction date.
- `accode`: posted account code.
- `amount`: signed ledger amount.
- `control`: posting/control level.
- `opaccode`: opposite account code used for ledger display and navigation.

Important sign convention:

- Negative `daybook.amount` is shown as debit in ledger reports.
- Positive `daybook.amount` is shown as credit in ledger reports.
- Every complete transaction should balance to zero when grouped by `slno`.

### daybookpart

`daybookpart` is the voucher header/detail table for a `daybook.slno`. Live/demo columns include:

- `slno`: transaction serial number, shared with `daybook`.
- `vchno`: voucher/document number.
- `particular`: narration.
- `staff`: staff/salesman code.
- `chequedate`, `chequeno`, `duedate`: cheque and due date details.
- `ic`, `uid`, `ttime`: user/time metadata.
- `slno2`: used by suspense/linked allocation flows.
- `rate`, `taxperc`, `taxamt`, `interstate`, `taxreverse`, `refno`: tax/rate/reference data.

There is a migration intended to add `daybookpart.discount`, but the live/demo table inspected here does not currently show that column. The receipt controller filters insert columns dynamically, so the code can save without the column, but discount-specific persistence depends on the migration being applied.

### daybookratewgt

`daybookratewgt` stores rate and weight details tied to a daybook transaction:

- `slno`: primary key and link to the transaction.
- `rate`: rate.
- `mcp`: making-charge percent.
- `wgt`: weight.
- `code`: optional code.
- `tdate`: transaction date.
- `control`: control level.

Ledger reports read this table when the ledger type requests rate or weight details.

## Transaction Master Relationship

The project uses `slno` as the common transaction key across legacy master and posting tables.

Examples:

- Sales: `salesm.slno` plus `salesd` detail rows plus `daybook` and `daybookpart`.
- Sales return: `salesrm.slno` plus `salesrd` plus postings.
- Purchase: `purchasem.slno` plus purchase details plus postings.
- Purchase return: `purchaserm.slno` plus return details plus postings.
- Receipt/payment/journal/direct vouchers: `daybookpart.slno` plus one or more `daybook` rows.
- PDC vouchers: `pdclist.slno` until collection/posting.
- Smith/refinery/repair/order modules also share the same `slno` pattern.

New serial numbers are reserved from `generali.SERIALNO`, but controllers also scan existing transaction tables (`salesm`, `salesrm`, `purchasem`, `purchaserm`, `daybook`, `daybookpart`, `orderm`, `smithm`, `refinerym`, `repairm`) and choose max existing `slno` plus one.

On edit, most modules delete old `daybook`, `daybookpart`, and related rows for the same `slno`, then reinsert the recalculated postings.

## Voucher Number Logic

Receipt and payment vouchers use counters in `generali` and also scan `daybookpart.vchno` to avoid reusing an existing number.

Receipt prefixes:

- Level 1 default receipt: `VRB/`.
- Lower/other control receipt: `VRE/`.
- If cash/bank separate voucher numbering is enabled and selected account is bank: `VRB/`.
- If separate numbering is enabled and selected account is cash: `VRC/`.
- PDC receipt: `PDCR/` or `PDCR`.
- Secondary database prefix can override level 1 receipt prefix.

Payment prefixes:

- Level 1 default payment: `VPB/`.
- Lower/other control payment: `VPE/`.
- If cash/bank separate voucher numbering is enabled and selected account is bank: `VPB/`.
- If separate numbering is enabled and selected account is cash: `VPC/`.
- PDC payment: `PDCP/` or `PDCP`.
- Secondary database prefix can override level 1 payment prefix.

## Posting Rules

### Receipt Voucher

Controller: `ReceiptController`.

Normal receipt inserts one `daybookpart` header and two or three `daybook` rows:

- Party account: `amount + discount`, positive, `opaccode = cash/bank`.
- Cash/bank account: receipt amount, negative, `opaccode = party`.
- Discount account, when discount is present: discount amount, negative, `opaccode = party`.

The discount account comes from `generals.RDISCAC`, defaulting to `DISC`.

The total should balance:

`party credit = cash/bank debit + discount debit`

PDC receipt writes to `pdclist` instead of immediate `daybook` postings.

### Payment Voucher

Controller: `PaymentController`.

Normal payment inserts one `daybookpart` header and two `daybook` rows:

- Cash/bank account: payment amount, positive, `opaccode = party`.
- Party account: payment amount, negative, `opaccode = cash/bank`.

The total should balance to zero. PDC payment writes to `pdclist` instead of immediate `daybook` postings.

### Sales Bill

Controller: `SalesBillController`.

Sales posting builds an in-memory `entries` list, then inserts it to `daybook` with `vtype = SL` where the schema supports that column.

Common heads used:

- Customer/cash/bank settlement side.
- `RS`: sales account.
- `ESR`: exchange/sales-return side when returns are included.
- `EP`: old gold exchange purchase side, or configured old-gold purchase account.
- `DISC`: discount.
- `SGST`, `CGST`, `IGST`, or configured `STAXAC`: tax heads.
- `AST`: cess/additional sales tax.
- `PTAX`/`PTAXEXP`: purchase tax external mapping.
- `VA`: value-addition account when configured separately.
- `ROUND`: balancing/round-off row.

After all sales rows are inserted, the controller sums `daybook.amount` for the `slno`. If the sum is not zero, it inserts a `ROUND` row for the negative of the sum.

### Purchase Bill

Controller: `PurchaseBillController`.

Purchase posting inserts a `daybookpart` header and then posts purchase ledger rows. Common heads used:

- `CASH`: cash payment.
- Selected bank or `CNP`: cheque payment or cheque-not-presented.
- Supplier account.
- `EP`: purchase account.
- `ADD`: additional/other amount.
- `HMC`: making charge.
- `TCSAC`: TCS.
- `DISC`: discount.
- `SGST`, `CGST`, `IGST`, or `PTAXEXP`: tax heads.
- `ROUND`: round-off and residual balancing.

After purchase rows are inserted, the controller sums all `daybook.amount` rows for the `slno`; if the result is not zero, a `ROUND` entry is added.

### Sales Ledger Repair Logic

Controller: `AccountLedgerController`.

When viewing account ledgers, the controller can repair sales bills that exist in `salesm` but have no `daybook` rows. For each missing sale:

- It creates a `daybookpart` row with voucher number from `salesm.billno`.
- It posts customer account negative for net amount.
- It posts `RS` positive for net amount.

This is a pragmatic repair path for legacy or incomplete posting data.

## Reporting Logic

### Account Ledger

Controller: `AccountLedgerController`.

Opening balance:

- Reads `accountm.opbal` for level 1 or `opbalb` otherwise.
- Adds all prior `daybook.amount` for the account before the from-date and `control <= gilevel`.

Ledger rows:

- Join `daybook` to `daybookpart`.
- Filter by `daybook.accode`, date range, and `control <= gilevel`.
- Use `daybook.opaccode` to display the opposite account name.
- Negative amount is debit, positive amount is credit.
- Running balance is opening plus signed transaction amount.
- Optional rate/weight columns are loaded from `daybookratewgt`.

### Day Book

Controller: `DayBookController`.

The daybook report always resolves the level to 1 in this controller.

Forms:

- Form 1: cash book style report with opening and closing cash balances.
- Form 2: grouped by `slno`, showing debit/credit totals per voucher; optional filter can show only difference/unbalanced vouchers.
- Form 3: detailed daybook rows with account code and voucher data.
- Form 4: receipt/payment grouped by date with balance brought down/carried through.
- Form 5: full daily report combining sales, sales returns, purchases, purchase returns, receipts, and payments.

Cash balance:

- Starts with `accountm.opbal` for `CASH`.
- Adds `daybook.amount` for `CASH` before/from/to dates with `control <= 1`.

Particulars:

- For `RS`, the report derives sales bill range from `salesm`.
- For `EP`, it derives purchase document range from `purchasem`.
- Otherwise it uses `daybookpart.particular`, or `Day Total` when multiple matching vouchers exist.

### All Transaction Report

Controller: `AllTransReportController`.

Receipt, payment, and journal sections are derived from `daybook` plus `daybookpart`:

- Receipt mode filters `daybookpart.vchno LIKE 'VR%'` and positive `daybook.amount`.
- Payment mode filters `daybookpart.vchno LIKE 'VP%'` and negative `daybook.amount`.
- Journal mode filters `daybookpart.vchno LIKE 'JL%'`.

The report joins `accountm` for account names and applies the selected date and control filters.

## Integrity Rules

Important consistency checks implied by the code:

- A complete `slno` group in `daybook` should sum to zero.
- Every posted `daybook.accode` should exist in `accountm`.
- Most reports use `control <= gilevel`; daybook itself standardizes to level 1.
- `daybookpart` should exist for normal vouchers so reports and voucher lookup can find `vchno` and narration.
- PDC rows may exist in `pdclist` without immediate `daybook` rows.
- Deleting a voucher should delete rows from `daybook`, `daybookpart`, `pdclist`, and `daybookratewgt` where applicable.

## Main Source Files

- `app/Http/Controllers/AccountMasterController.php`
- `app/Http/Controllers/AccountLedgerController.php`
- `app/Http/Controllers/DayBookController.php`
- `app/Http/Controllers/AllTransReportController.php`
- `app/Http/Controllers/ReceiptController.php`
- `app/Http/Controllers/PaymentController.php`
- `app/Http/Controllers/SalesBillController.php`
- `app/Http/Controllers/PurchaseBillController.php`
- `app/Support/SecondaryDatabaseSync.php`
- `database/migrations/2026_03_30_143836_add_discount_to_daybookpart.php`

## Practical Notes

- The code uses schema checks and column filtering in many controllers, so it can run against slightly different legacy table layouts.
- The inspected live/demo `daybook` table is minimal and does not include every optional column used by some controllers, such as `ddate`, `vtype`, or `vno`.
- The inspected live/demo `daybookpart` table does not currently include `discount`, even though a migration exists for it.
- For debugging, start from `slno`, then inspect `daybookpart`, `daybook`, and any module master table such as `salesm` or `purchasem`.
- For balance mismatches, group `daybook` by `slno` and check `SUM(amount) <> 0`.
