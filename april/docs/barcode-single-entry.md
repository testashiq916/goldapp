# Barcode Single Entry Module - Complete Documentation

## Overview

The **Barcode Single Entry** module is a jewelry item barcode registration and management system for the GoldApp ERP. It allows users to create, edit, delete, search, and print barcode labels for individual jewelry items. The module was converted from a legacy PowerBuilder application (`w_barcode_entry`) to a Laravel 12 web module.

**URL:** `http://localhost:8080/goldapp/barcode-single-entry`

---

## Architecture

| Layer | File | Description |
|-------|------|-------------|
| Controller | `app/Http/Controllers/BarcodeEntryController.php` | Main CRUD + print + search + legacy action API |
| Controller | `app/Http/Controllers/BarcodeSettingsController.php` | Settings JSON editor + printer list |
| View | `resources/views/barcode-single-entry/index.blade.php` | Main barcode entry form (standalone Blade, no layout) |
| View | `resources/views/barcode-single-entry/settings.blade.php` | Settings editor page |
| Routes | `routes/web.php` (lines 114-138) | All barcode entry + settings routes |
| Settings | `storage/app/barcode-settings.json` | Persistent settings (JSON) |

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `barcode` | Main barcode records (one row per item) |
| `barcodedmd` | Diamond/stone summary per barcode (totals) |
| `barcode_dmddet` | Diamond/stone detail rows per barcode |
| `barcodedoc` | Barcode document header (groups of barcodes) |
| `items` | Item master (code, name, wastage, mcharge, etc.) |
| `itemsqtype` | Quality types (18K, 22K, etc.) with touch values |
| `itemsubgrp` | Item sub-groups |
| `clients` | Client/smith master (ctype='G' for goldsmiths) |
| `models` | Model master (mtype='M' for models) |
| `salesman` | Salesman master |
| `counter` | Counter/location master |
| `generali` | Integer counters (BCNO = barcode counter, BCDOCNO = doc counter) |
| `generald` | Decimal settings (THRATE = today's gold rate) |
| `delpart` | Deletion audit log |
| `salesd` | Sales detail (checked before delete to prevent sold-item deletion) |

---

## API Endpoints

### Page Routes

| Method | URL | Controller Method | Description |
|--------|-----|-------------------|-------------|
| GET | `/barcode-single-entry` | `index()` | Main barcode entry form |
| GET | `/barcode-single-entry/settings` | `BarcodeSettingsController@index` | Settings JSON editor |

### Barcode CRUD API

| Method | URL | Controller Method | Description |
|--------|-----|-------------------|-------------|
| GET | `/api/barcode-entry/get?bcode=123` | `get()` | Retrieve barcode + diamond details |
| POST | `/api/barcode-entry/save` | `save()` | Add or update barcode entry |
| POST | `/api/barcode-entry/delete` | `delete()` | Delete barcode (with safeguards) |
| GET | `/api/barcode-entry/search?q=text` | `search()` | Search barcodes for list popup |
| GET | `/api/barcode-entry/next-barcode` | `nextBarcode()` | Get next available barcode number |
| GET | `/api/barcode-entry/next-docno` | `nextDocNo()` | Get next document number (BD000001 format) |
| GET | `/api/barcode-entry/load-item?icode=GOLD` | `loadItem()` | Load item details by item code |
| GET | `/api/barcode-entry/load-document?docno=BD000001` | `loadDocument()` | Load document totals |
| GET | `/api/barcode-entry/lookups` | `lookups()` | All dropdown data (items, quality, smiths, etc.) |

### Print API

| Method | URL | Controller Method | Description |
|--------|-----|-------------------|-------------|
| POST | `/api/barcode-entry/print` | `printBarcode()` | Print barcode label for a specific barcode |
| POST | `/api/barcode-entry/print-sample` | `printSample()` | Print a sample/test label |

### Legacy Action API

| Method | URL | Controller Method | Description |
|--------|-----|-------------------|-------------|
| GET/POST | `/api/barcode-entry/action?action=...` | `action()` | Legacy compatibility endpoint |

**Supported actions:** `get_next_barcode`, `new_doc`, `get_thrate`, `lookup_item`, `lookup_barcode`, `lookup_doc`, `get_qtype_touch`, `get_stone_rate`, `save_setting`, `calc_stktouch`, `upload_photo`, `search_items`, `search_smiths`, `search_qtypes`, `search_subgrps`, `search_models`, `search_barcodes`, `print_barcode`, `save`, `delete`

### Settings API

| Method | URL | Controller Method | Description |
|--------|-----|-------------------|-------------|
| GET | `/barcode-single-entry/settings` | `BarcodeSettingsController@index` | Settings page |
| GET | `/barcode-single-entry/settings/get` | `BarcodeSettingsController@get` | Get all settings (JSON) |
| POST | `/barcode-single-entry/settings/save` | `BarcodeSettingsController@save` | Save all settings |
| GET | `/barcode-single-entry/settings/printers` | `BarcodeSettingsController@printers` | List Windows printers |
| POST | `/barcode-single-entry/settings/default-printer` | `BarcodeSettingsController@setDefaultPrinter` | Set default printer |

---

## Form Fields

### Document Section

| Field | ID | Type | Description |
|-------|----|------|-------------|
| Barcode# | `fBcode` | text | Barcode number (auto-generated or manual) |
| Date | `fTdate` | date | Transaction date (defaults to today) |
| DocNo | `fDocno` | text | Document number (BD000001 format, auto-generated) |
| Smith | `fSmithcode` | select | Goldsmith (from clients where ctype='G') |
| Tot.Wgt | `fTotWgt` | number | Total document weight (readonly, calculated) |
| Tot.Nos | `fTotNos` | number | Total document items (readonly, calculated) |

### Options Panel

| Field | ID | Setting Key | Description |
|-------|----|-------------|-------------|
| Stk In Nos | `fStkinnos` | - | Stock counted in numbers (not weight) |
| Print this BCode | `fPrintThisBc` | `PrintThisBCode` | Print label immediately after save |
| Print after 2 items | `fPrintAfter2` | `BCodePrintAfter2Entry` | Pair-print mode (print two labels together) |

### Item Section

| Field | ID | Type | Description |
|-------|----|------|-------------|
| Item Code | `fIcode` | text + datalist | Item code with autocomplete from items table |
| Item Name | `fIname` | text | Item name (readonly, auto-filled from item code) |
| SubGroup | `fSubgrp` | select | Item sub-group |
| Quality | `fQtype` | select | Quality type (18K, 22K, etc.) |
| Model | `fModel` | select | Model name |
| Size | `fSizemodel` | text | Size/model detail |

### Weight & Quantity Section

| Field | ID | Type | Default Key | Description |
|-------|----|------|-------------|-------------|
| Qty | `fQty` | number | `BCDefQty` | Quantity (default: 1) |
| Weight | `fWeight` | number | - | Gross weight in grams |
| Stone Wgt | `fStweight` | number | `BCDefStWgt` | Stone weight in grams |
| Net Wgt | `fNetWgt` | number | - | Net weight = Weight - Stone Wgt (readonly) |
| Sticker Wgt | `fStickerWgt` | number | `BCStickerWgt` | Weight printed on sticker |
| QUnit | `fQunit` | text | - | Quantity unit |

### Rate & Making Charge Section

| Field | ID | Type | Default Key | Description |
|-------|----|------|-------------|-------------|
| Rate | `fRate` | number | `BCDefRate` | Gold rate per gram |
| Wastage% | `fWastage` | number | - | Wastage percentage |
| MC Rate | `fMcrate` | number | `BCDefMcPerGm` | Making charge rate per gram |
| MC Amt | `fMc` | number | `BCDefMCA` | Making charge amount |
| Smith MC | `fSmithmcrate` | number | - | Smith making charge rate |

### Cost & Touch Section

| Field | ID | Type | Default Key | Description |
|-------|----|------|-------------|-------------|
| Trans Touch | `fTranstouch` | number | `BCTransTouch` | Transaction touch (purity) |
| Stk Touch | `fStktouch` | number | - | Stock touch (readonly, calculated) |
| Cost | `fCost` | number | `BCDefCost` | Cost price per gram |
| Cost MC | `fCostmc` | number | `BCDefCostMcPerGm` | Cost making charge per gram |
| Cost Stone | `fCoststone` | number | `BCDefCostStone` | Cost of stones |
| Cost% | `fCostperc` | number | - | Cost percentage |

### Value Section

| Field | ID | Type | Default Key | Description |
|-------|----|------|-------------|-------------|
| Stone Price | `fStprice` | number | `BCDefStPrice` | Stone selling price |
| VAP% | `fVap` | number | `BCDefVAP` | Value addition percentage |
| Min VAP | `fMinvap` | number | `BCDefMinVAP` | Minimum VAP amount |
| Total Amt | `fTamt` | number | - | Total amount (readonly, calculated) |
| Purch Amt | `fCostamt` | number | - | Purchase/cost amount (readonly) |
| G.Rate | `fGrate` | number | - | Gold rate used (readonly, from THRATE) |

### Other Details Section

| Field | ID | Type | Description |
|-------|----|------|-------------|
| Serial No | `fSerialno` | text | Serial number |
| HUID | `fHuid` | text | Hallmark Unique ID (BIS hallmarking) |
| Counter | `fCounter` | select | Counter/location |
| Salesman | `fSmcode` | select | Salesman code |
| Part/MRP | `fPart` | text | Part number or MRP |

### Checkboxes

| Field | ID | Description |
|-------|----|-------------|
| Sold | `fStk` | Item is sold (stk='N' in DB) |
| Manual BCode | `fBcManual` | Manually enter barcode number |
| No Discount | `fNodisc` | Item has no discount |

---

## Diamond / Stone Details Table

Each barcode can have multiple stone/diamond detail rows stored in `barcode_dmddet`.

| Column | Type | Description |
|--------|------|-------------|
| # | auto | Row serial number |
| Type | text (`sttype`) | Stone type (Diamond, Ruby, etc.) |
| Size | text (`stsize`) | Stone size |
| Cut | text (`stcut`) | Cut type |
| SetType | text (`stsettype`) | Setting type |
| Pcs | number (`pcs`) | Number of pieces |
| Carats | number (`carats`) | Weight in carats |
| Rate | number (`rate`) | Rate per carat |
| Amount | number (`amount`) | Auto-calculated: Carats x Rate |
| Color | text (`stcolor`) | Stone color |
| StCode | text (`stcode`) | Stone code |

---

## Keyboard Shortcuts

| Key | Action | Description |
|-----|--------|-------------|
| **F4** | Delete | Prompt to delete a barcode |
| **F5** | Save | Save current barcode entry |
| **F7** | List | Open barcode search/list popup |
| **Escape** | Cancel | Reset form and return to Ready mode |

---

## Workflow / Modes

The form operates in three modes:

### 1. Add Mode (default on load)
- Form auto-loads next barcode number and document number
- All fields are editable
- Default values applied from settings
- Today's date auto-filled
- Gold rate (THRATE) auto-filled
- **Save** creates a new barcode record

### 2. Edit Mode
- Triggered when loading an existing barcode (via List popup or entering barcode#)
- All fields editable except readonly computed fields
- **Save** updates the existing barcode record

### 3. Ready Mode
- Idle state after Cancel or Delete
- Fields are locked
- Enter a barcode# and press Enter to load it for editing

### Typical Flow:
1. Form opens in **Add** mode with auto-generated barcode# and doc#
2. Enter item code → auto-fills item name, wastage, MC rate, VAP, quality
3. Enter weight → auto-calculates net weight, amounts
4. Add diamond/stone rows if applicable
5. Press **F5** (Save) → barcode saved, label printed (if enabled), form resets for next entry
6. Press **F7** (List) to search and edit existing barcodes
7. Press **F4** (Delete) to delete a barcode (blocks if sold)

---

## Auto-Calculations

When `AutoSAmtInBCodeEntry` setting is `'Y'`:

```
Net Weight = Weight - Stone Weight

MC Amount = MC Rate × Net Weight   (when BCFreshMC = 'Y')

Metal Value = Net Weight × Rate × (1 + Wastage% / 100)
Base Amount = Metal Value + MC Amount + Stone Price
Total Amount = Base Amount × (1 + VAP% / 100)

Cost Amount = (Net Weight × Stk Touch / 100 × Gold Rate) + Cost MC + Cost Stone
```

If `RoundOffAllAmt = 'Y'`, Total Amount is rounded to nearest integer.

---

## Print System

### Print Modes
Configured via `BCPrintMode` setting:

| Mode | Description |
|------|-------------|
| `auto` | Try Windows printer first, then BAT fallback, then `lp` (Linux) |
| `windows_print` | Direct Windows printer via `copy /B`, `print`, or PowerShell |
| `bat` | Execute `print.bat` (or custom BAT file from `BCPrintBat`) |
| `none` | Disable printing |

### Print Types
Configured via `BPrintType` setting:

| Type | Description |
|------|-------------|
| `DEFAULT` | EPL2 format (Eltron/Zebra) |
| `TSC` | TSPL format (TSC printers) |
| `TEMPLATEDESIGNER` | Custom JSON template with placeholders |

### Template Designer Placeholders

| Placeholder | Value |
|-------------|-------|
| `[BarCode]` | Barcode number |
| `[ItemCode]` | Item code |
| `[ItemName]` | Item name |
| `[Weight]` | Gross weight |
| `[StWgt]` | Stone weight |
| `[NetWgt]` | Net weight |
| `[Rate]` | Gold rate |
| `[Amount]` | Total amount |
| `[VA]` | Value addition (MC or VAP%) |
| `[QType]` | Quality type |
| `[Model]` | Model name |
| `[SmithCode]` | Smith code |
| `[Stcode1]` / `[Stcode2]` / `[Stcode3]` | Stone codes |
| `[Weight1]` / `[Weight2]` / `[Weight3]` | Stone weights |
| `[Stone1]` / `[Stone2]` / `[Stone3]` | Stone amounts |

### Sticker Modes
- **Single:** One label per barcode
- **Double:** Two labels side-by-side (pair print after 2 entries)

### Print Flow
1. On save, if `PrintThisBCode = 'Y'`: immediately print
2. If `BCodePrintAfter2Entry = 'Y'`: collect 2 barcodes, then print both
3. PRN file generated at `BCPPrnPath` (or `storage/app/bprint.prn`)
4. Print command executed based on `BCPrintMode`

---

## Settings Reference

Settings stored in `storage/app/barcode-settings.json` under `Software` section:

### Default Values

| Key | Description | Default |
|-----|-------------|---------|
| `BCDefQty` | Default quantity | `1` |
| `BCDefRate` | Default gold rate | empty |
| `BCDefCost` | Default cost | empty |
| `BCDefVAP` | Default VAP% | empty |
| `BCDefMCA` | Default MC amount | empty |
| `BCDefMcPerGm` | Default MC rate per gram | empty |
| `BCDefStWgt` | Default stone weight | empty |
| `BCDefStPrice` | Default stone price | empty |
| `BCDefMinVAP` | Default minimum VAP | empty |
| `BCDefCostMcPerGm` | Default cost MC per gram | empty |
| `BCDefQType` | Default quality type | empty |

### Behavior Settings

| Key | Description | Default |
|-----|-------------|---------|
| `BCFreshMC` | Auto-calculate MC from MC rate × weight | `Y` |
| `BCMaxNo` | Use MAX(bcode) for next barcode (Y) or BCNO counter (N) | `Y` |
| `BCEncrypt` | Encrypt barcode number | `N` |
| `MCEncrypt` | Encrypt MC value | `N` |
| `BCBalWgtCheck` | Balance weight check | `Y` |
| `RoundOffAllAmt` | Round all amounts to integer | `N` |
| `BCSubGrpMust` | Sub-group is mandatory | `N` |
| `ShowBCListInEdit` | Show barcode list popup when editing | `Y` |
| `PrintThisBCode` | Print label after each save | `N` |
| `BCodePrintAfter2Entry` | Print after every 2 entries | `Y` |
| `BCPrintSmith` | Print smith name on label | `Y` |
| `BPrintQty` | Print quantity on label | `N` |
| `GoToDmdDetails` | Auto-navigate to diamond details | `N` |
| `StWgtInfo` | Stone weight info type | `Aprox` |
| `BCForm` | Barcode form number | `1` |
| `AutoSAmtInBCodeEntry` | Auto-calculate sale amount | `N` |

### Print Settings

| Key | Description | Default |
|-----|-------------|---------|
| `BPrintType` | Printer type (DEFAULT, TSC, TEMPLATEDESIGNER) | empty |
| `BCPrintMode` | Print mode (auto, windows_print, bat, none) | `windows_print` |
| `BCPrinterName` | Windows printer name | empty |
| `BCDensity` | Print density (1-15) | `14` |
| `BCPXINC` | X-axis offset | `0` |
| `BCPYINC` | Y-axis offset | `0` |
| `BCPPrnPath` | PRN file output path | empty |
| `BCPrintBat` | BAT file for print command | empty |
| `BCDesignerTemplate` | JSON template for TEMPLATEDESIGNER mode | empty |
| `BCStickerMode` | Sticker layout (single, double) | `single` |
| `BCDesignerColumns` | Number of columns for template | `1` |

---

## Authentication

Both controllers use `isAuthorized()` which checks:

1. **Laravel session:** `$request->session()->get('user_code')` is non-empty
2. **Legacy PHP session fallback:** Reads `PHPSESSID` cookie, finds the session file in `session.save_path`, and checks for `user_code|` in the raw session data

Unauthorized requests redirect to `/login` (page routes) or return 401 JSON (API routes).

---

## Safety Features

- **Column filtering:** All INSERT/UPDATE operations filter data through `Schema::getColumnListing()` to prevent SQL errors on missing columns
- **Transaction wrapping:** Save and Delete operations use `DB::beginTransaction()` / `commit()` / `rollBack()`
- **Sold item protection:** Cannot delete a barcode that has entries in the `salesd` table
- **Audit logging:** Deletions are logged in the `delpart` table with date, description, control, and user
- **Table existence checks:** All table operations check `Schema::hasTable()` first for defensive coding

---

## File Structure

```
app/
  Http/
    Controllers/
      BarcodeEntryController.php      # 1876 lines - main CRUD + print + legacy API
      BarcodeSettingsController.php    # 224 lines - settings + printers

resources/
  views/
    barcode-single-entry/
      index.blade.php                  # ~1113 lines - main form (standalone HTML)
      settings.blade.php               # Settings editor page

routes/
  web.php                              # Lines 114-138 - all barcode routes

storage/
  app/
    barcode-settings.json              # Persistent settings (created on first access)
    bprint.prn                         # Generated print file (default location)
```

---

## Save Payload Format (POST /api/barcode-entry/save)

```json
{
    "mode": "add",
    "bcode": 100001,
    "tdate": "2026-02-18",
    "docno": "BD000001",
    "smithcode": "SM001",
    "icode": "GOLD22",
    "subgrp": "RING",
    "qtype": "22K",
    "model": "M-01",
    "sizemodel": "18",
    "qty": 1,
    "weight": 12.500,
    "stweight": 0.540,
    "stickerwgt": 12.500,
    "qunit": "GM",
    "rate": 8100.00,
    "wastage": 3.50,
    "mcrate": 8.50,
    "mc": 250.00,
    "smithmcrate": 6.00,
    "transtouch": 91.60,
    "stktouch": 91.60,
    "cost": 7500.00,
    "costmc": 150.00,
    "coststone": 0.00,
    "costperc": 0.00,
    "stprice": 2500.00,
    "vap": 6.00,
    "minvap": 500.00,
    "tamt": 125000.00,
    "costamt": 95000.00,
    "grate": 8100.00,
    "serialno": "",
    "huid": "ABCD1234",
    "counter": "C1",
    "smcode": "S01",
    "part": "",
    "sold": "N",
    "nodisc": "N",
    "stkinnos": "N",
    "dmdDetails": [
        {
            "sttype": "Diamond",
            "stsize": "1.5mm",
            "stcut": "Round",
            "stsettype": "Prong",
            "pcs": 4,
            "carats": 0.120,
            "rate": 15000.00,
            "amount": 1800.00,
            "stcolor": "D",
            "stcode": "DIA001"
        }
    ]
}
```

---

## Response Format

### Success
```json
{
    "success": true,
    "message": "Barcode added successfully",
    "data": {
        "bcode": 100001
    }
}
```

### Error
```json
{
    "success": false,
    "message": "Item code is required"
}
```

---

## Barcode Number Generation

Two modes controlled by `BCMaxNo` setting:

| BCMaxNo | Method | Description |
|---------|--------|-------------|
| `Y` (default) | `MAX(bcode) + 1` | Takes the highest bcode from `barcode` table and adds 1 |
| `N` | `generali.BCNO + 1` | Uses a sequential counter stored in `generali` table |

If no barcodes exist, starts from `100001`.

---

## Document Number Generation

- Format: `BD` + 6-digit zero-padded number (e.g., `BD000001`, `BD000042`)
- Counter stored in `generali` table with `code = 'BCDOCNO'`
- Auto-incremented when a new document is created in `barcodedoc`
