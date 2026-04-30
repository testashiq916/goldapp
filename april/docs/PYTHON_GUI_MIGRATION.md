# GoldApp Laravel to Python GUI Migration

## Current project facts

- Laravel project path: `C:\xampp\htdocs\goldapp`
- Database driver: MySQL from XAMPP
- Database from `.env`: `demo`
- Controllers found: `201`
- Route declarations found in `routes/web.php`: `883`

This is too large for a safe single-step conversion. The correct approach is to migrate module by module while keeping the same MySQL database.

## Python desktop target

- GUI toolkit: `customtkinter`
- Database access: `mysql-connector-python`
- Config source: reuse Laravel `.env`
- Authentication: reuse `userm`, `userd`, `userhist`
- Reporting and master data: query the same legacy tables directly

## Phase plan

### Phase 1

- Create Python app shell
- Connect to XAMPP MySQL
- Implement legacy login
- Build dashboard
- Build module navigation

Status: completed in starter form under `python_gui/`

### Phase 2

- Company selection
- Application settings
- User access
- Basic masters:
  - Item master
  - Counter master
  - Bill prefix
  - Denomination master

### Phase 3

- Core accounts:
  - Account master
  - Account ledger
  - Cash book
  - Bank book
  - Receipt
  - Payment

### Phase 4

- Sales workflow:
  - Sales bill
  - Sales return
  - Sales register
  - Customer reports
  - Bill print / reprint

### Phase 5

- Purchase workflow:
  - Purchase bill
  - Purchase return
  - Purchase register
  - Supplier reports

### Phase 6

- Inventory workflow:
  - Stock register
  - Stock verification
  - Barcode entry
  - Barcode stock and history reports

### Phase 7

- Order workflow:
  - Order entry
  - Order update
  - Order pending reports
  - Order profit analysis

### Phase 8

- Goldsmith and scheme modules:
  - Smith master and reports
  - Goldsmith transactions
  - Kuri details
  - Kuri collection
  - Passbook print

## Suggested conversion rule

For each Laravel module:

1. Identify controller, routes, views, and tables.
2. Extract database queries and business rules.
3. Recreate those rules in Python service classes.
4. Build one desktop window for that module.
5. Test CRUD and reports on the real XAMPP database.
6. Only then move to the next module.

## Important warning

Do not stop Laravel before the Python version reaches feature parity for the modules you actually use in production. During migration, both apps can point to the same MySQL database.
