# Jewelry Sales Management System - PHP Conversion

## Overview
Complete PHP conversion of the **PowerBuilder w_sales window** - a comprehensive jewelry shop sales management system.

## Converted From
- **Original:** PowerBuilder DataWindow application (5,000+ lines)
- **Target:** PHP 8+ with MySQL, vanilla HTML/CSS/JS frontend

## File Structure

```
jewel_sales/
├── index.html                  # Main sales window UI (replaces w_sales)
├── database_schema.sql         # MySQL schema for all tables
├── config/
│   └── ../../storage/app/software-settings.ini  # Single shared configuration
├── includes/
│   ├── config.php              # DB connection, session, general lookups
│   └── helpers.php             # Utility functions (ftrans, amountToWords, etc.)
├── classes/
│   ├── SalesManager.php        # Core business logic class
│   └── PrintManager.php        # Bill printing (A4, Thermal, Dot Matrix)
├── ajax/
│   └── sales_api.php           # All AJAX endpoints (JSON API)
├── css/
│   └── sales.css               # UI Stylesheet
└── js/
    └── sales.js                # Client-side logic & event handlers
```

## Feature Mapping (PowerBuilder → PHP)

| PowerBuilder Feature | PHP Equivalent |
|---|---|
| `w_sales` window | `index.html` + `sales.js` |
| `saveproc()` function | `SalesManager::save()` |
| `balcalc()` function | `SalesManager::balCalc()` |
| `filldetails()` function | `SalesManager::loadBill()` |
| `initsalemodule()` function | `SalesManager::initNewSale()` |
| `bcodechk()` function | `SalesManager::scanBarcode()` |
| `calcbcharge()` function | `SalesManager::calcBankCharge()` |
| `evacalc()` function | `SalesManager::vaCalc()` |
| `qtnprint*()` functions | `PrintManager` class |
| `dw_2` DataWindow (items grid) | HTML table + JavaScript `renderItemsGrid()` |
| Instance variables | `SalesManager` properties |
| Global variables | Session variables + `config.php` |
| `ProfileString()` calls | `profileString()` helper |
| `generali/generals/generald` | `getGeneralI/S/D()` helpers |
| Event handlers | JavaScript event listeners |
| `gs_inifile` INI file | `storage/app/software-settings.ini` |

## Key Features Preserved

### Sales Management
- **Bill/Estimate** entry with auto bill number generation
- **Barcode scanning** with stock validation
- **Item details**: weight, stone weight, wastage, making charges, rate per gram
- **Manual item entry** with inline editing

### Tax Calculations
- **GST**: SGST + CGST (intrastate) or IGST (interstate)
- **TCS** with configurable threshold
- **Cess** support
- **VAT** (legacy mode)
- **Tax-after-discount** option

### Exchange (Old Gold/Silver)
- Gold exchange: weight × touch% × rate
- Silver exchange: same calculation
- Other exchange amount

### Sales Returns
- Return amount deducted from bill total

### Payments
- Cash, Bank transfer, Card, Cheque
- Bank charge calculation on card payments
- Balance due tracking

### Stock Management
- Stock update on bill save
- Barcode status tracking (Y=stock, S=sold)
- Multi-stock-type support
- WAC (Weighted Average Cost) tracking
- Branch database synchronization

### Accounting
- Automatic daybook entries on save
- Sales, Cash, Bank, Tax, Exchange, Return, Discount, Round-off entries
- Customer balance tracking

### Printing
- **A4/Laser** format with full details
- **Thermal** (80mm) receipt format
- Configurable columns (HSN, HUID, wastage, MC, etc.)
- Amount in words
- Company header with GSTIN

## Setup Instructions

1. **Database**: Run `database_schema.sql` in MySQL
2. **Config**: Edit `storage/app/software-settings.ini` with your company details
3. **DB Connection**: Edit `includes/config.php` with your MySQL credentials
4. **Web Server**: Point Apache/Nginx to the `jewel_sales` directory
5. **Access**: Open `index.html` in browser

## Keyboard Shortcuts
| Key | Action |
|---|---|
| Ctrl+S | Save sale |
| Ctrl+N | New sale |
| Ctrl+P | Print bill |
| Ctrl+F | Find/search bills |
| F2 | Add blank row |
| Ctrl+Del | Delete selected row |
| Enter (in barcode) | Scan barcode |

## API Endpoints (ajax/sales_api.php)

| Action | Method | Description |
|---|---|---|
| `init` | GET | Initialize new sale |
| `scan_barcode` | GET | Scan barcode & get item |
| `calc_item` | GET | Calculate line item |
| `calc_totals` | POST | Calculate bill totals |
| `save` | POST | Save complete sale |
| `load_bill` | GET | Load existing bill |
| `delete_bill` | GET | Cancel a bill |
| `search_customer` | GET | Search customers |
| `get_customer` | GET | Get customer details |
| `search_items` | GET | Search items |
| `search_bills` | GET | Search existing bills |
| `get_dropdowns` | GET | Load dropdown data |
| `calc_exchange` | GET | Calculate exchange amount |
| `print_bill` | GET | Generate print HTML |
| `get_rates` | GET | Get current metal rates |

## Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- PDO extension
- Web server (Apache/Nginx)
