<?php

namespace App\Http\Controllers;

use App\Support\SecondaryDatabaseSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class BarcodeEntryController extends Controller
{
    // ── Public endpoints ────────────────────────────────────────────────

    /**
     * GET  /barcode-single-entry
     * Show the main barcode entry form.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $settings = $this->loadSettings();

        // Today's gold rate
        $todayRate = 0;
        if ($this->hasTable('generald')) {
            $todayRate = (float) (DB::table('generald')
                ->where('code', 'THRATE')
                ->value('cvalue') ?? 0);
        }

        // Lookups for dropdowns
        $items        = $this->safeLookup('items', ['code', 'name']);
        $qualityTypes = $this->safeLookup('itemsqtype', ['code', 'name']);
        $subgroups    = $this->safeLookup('itemsubgrp', ['code', 'name']);
        $smiths       = $this->smithLookup();
        $salesman     = $this->safeLookup('salesman', ['code', 'name']);
        $counters     = $this->safeLookup('counter', ['code', 'name']);
        $models       = $this->modelLookup();

        return view('barcode-single-entry.index', [
            'settings'     => $settings,
            'settingsJson' => json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            // Keep both keys for backwards compatibility with the blade/js.
            'todayRate'    => $todayRate,
            'thRate'       => $todayRate,
            // Keep both flat keys and grouped lookups for blade compatibility.
            'items'        => $items,
            'qualityTypes' => $qualityTypes,
            'subgroups'    => $subgroups,
            'models'       => $models,
            'smiths'       => $smiths,
            'salesman'     => $salesman,
            'counters'     => $counters,
            'lookups'      => [
                'items'        => $items,
                'qualityTypes' => $qualityTypes,
                'subgroups'    => $subgroups,
                'models'       => $models,
                'smiths'       => $smiths,
                'salesman'     => $salesman,
                'counters'     => $counters,
            ],
        ]);
    }

    /**
     * POST /barcode-single-entry/get
     * Retrieve a single barcode record with diamond summary and details.
     */
    public function get(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $bcode = (int) $request->input('bcode', 0);
        if ($bcode <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid barcode number'], 400);
        }

        try {
            if (!$this->hasTable('barcode')) {
                return response()->json(['success' => false, 'message' => 'barcode table not found'], 500);
            }

            $row = DB::table('barcode')->where('bcode', $bcode)->first();
            if (!$row) {
                return response()->json(['success' => false, 'message' => 'Barcode not found'], 404);
            }

            // Item name lookup
            $itemName = '';
            if ($this->hasTable('items') && isset($row->icode)) {
                $itemName = (string) (DB::table('items')
                    ->where('code', $row->icode)
                    ->value('name') ?? '');
            }

            // Diamond summary
            $dmdSummary = null;
            if ($this->hasTable('barcodedmd')) {
                $dmdSummary = DB::table('barcodedmd')->where('bcode', $bcode)->first();
            }

            // Diamond detail rows
            $dmdDetails = [];
            if ($this->hasTable('barcode_dmddet')) {
                $q = DB::table('barcode_dmddet')->where('bcode', $bcode);
                if (Schema::hasColumn('barcode_dmddet', 'sno')) {
                    $q->orderBy('sno');
                }
                $dmdDetails = $q->get()->toArray();
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'barcode'    => $row,
                    'itemName'   => $itemName,
                    'dmdSummary' => $dmdSummary,
                    'dmdDetails' => $dmdDetails,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /barcode-single-entry/save
     * Add or update a barcode entry (with diamond details & document).
     */
    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $data = $request->json()->all();
        if (!is_array($data) || empty($data)) {
            $data = $request->all();
        }

        $rawMode = strtoupper(trim((string) ($data['mode'] ?? '')));
        $mode  = match ($rawMode) {
            'A', 'ADD' => 'add',
            'E', 'EDIT' => 'edit',
            default => strtolower(trim((string) ($data['mode'] ?? ''))),
        };
        $bcode = (int) ($data['bcode'] ?? 0);
        $icode = trim((string) ($data['icode'] ?? ''));
        $qty   = (float) ($data['qty'] ?? 0);

        // Basic validation
        if (!in_array($mode, ['add', 'edit'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid mode. Use "add" or "edit".'], 422);
        }
        if ($bcode <= 0) {
            return response()->json(['success' => false, 'message' => 'Barcode number is required'], 422);
        }
        if ($icode === '') {
            return response()->json(['success' => false, 'message' => 'Item code is required'], 422);
        }
        if ($qty <= 0) {
            return response()->json(['success' => false, 'message' => 'Quantity must be greater than zero'], 422);
        }

        if (!$this->hasTable('barcode')) {
            return response()->json(['success' => false, 'message' => 'barcode table not found'], 500);
        }

        DB::beginTransaction();
        try {
            $exists = DB::table('barcode')->where('bcode', $bcode)->exists();

            if ($mode === 'add' && $exists) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Barcode ' . $bcode . ' already exists'], 422);
            }
            if ($mode === 'edit' && !$exists) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Barcode ' . $bcode . ' not found'], 404);
            }

            // Build and filter the barcode row
            $barcodeRow = $this->buildBarcodeRow($data, $request);

            if ($mode === 'add') {
                DB::table('barcode')->insert($barcodeRow);
            } else {
                $updateRow = $barcodeRow;
                unset($updateRow['bcode']);
                DB::table('barcode')->where('bcode', $bcode)->update($updateRow);
            }

            // Diamond details
            $dmdDetails = is_array($data['dmdDetails'] ?? null) ? $data['dmdDetails'] : [];
            if (!empty($dmdDetails)) {
                $this->saveDiamondDetails($bcode, $dmdDetails);
                $this->saveDiamondSummary($bcode, $dmdDetails);
            }

            // Document
            $docno = trim((string) ($data['docno'] ?? ''));
            if ($docno !== '') {
                $tdate    = trim((string) ($data['tdate'] ?? date('Y-m-d')));
                $smith    = trim((string) ($data['smithcode'] ?? ''));
                $totwgt   = (float) ($data['weight'] ?? 0);
                $totnos   = (int) ($data['qty'] ?? 0);
                $this->saveDocument($docno, $tdate, $smith, $totwgt, $totnos);
            }

            // Update BCNO counter on add (only when BCMaxNo is 'N')
            if ($mode === 'add') {
                $settings = $this->loadSettings();
                $bcMaxNo  = $this->setting('BCMaxNo', 'Y', $settings);
                if (strtoupper($bcMaxNo) === 'N' && $this->hasTable('generali')) {
                    DB::table('generali')
                        ->where('code', 'BCNO')
                        ->update(['cvalue' => $bcode]);
                }
            }

            DB::commit();

            $message = $mode === 'add' ? 'Barcode added successfully' : 'Barcode updated successfully';
            if (SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code'))) {
                try {
                    $secondarySync = (new SecondaryDatabaseSync())->syncBarcode($bcode);
                    $message .= ' and synced to secondary DB (' . ($secondarySync['database'] ?? '') . ')';
                } catch (\Throwable $e) {
                    $message .= '. Primary save completed, but secondary sync failed: ' . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => ['bcode' => $bcode],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error saving barcode: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /barcode-single-entry/delete
     * Delete a barcode entry (with safeguard against sold items).
     */
    public function delete(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $bcode = (int) $request->input('bcode', 0);
        if ($bcode <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid barcode number'], 400);
        }

        if (!$this->hasTable('barcode')) {
            return response()->json(['success' => false, 'message' => 'barcode table not found'], 500);
        }

        $row = DB::table('barcode')->where('bcode', $bcode)->first();
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Barcode not found'], 404);
        }
        $docno = trim((string) ($row->docno ?? ''));

        // Check for sales entries
        if ($this->hasTable('salesd')) {
            $hasSales = DB::table('salesd')->where('bcode', $bcode)->exists();
            if ($hasSales) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete. This barcode has sales entries.',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Gather info for audit log before deletion
            $partDesc = 'BC:' . $bcode
                . ' I:' . (string) ($row->icode ?? '')
                . ' W:' . (string) ($row->weight ?? 0)
                . ' Q:' . (string) ($row->qty ?? 0);
            $control = (string) ($row->control ?? '');
            $uid     = (string) ($request->session()->get('user_code') ?? '');

            // Delete diamond detail rows
            if ($this->hasTable('barcode_dmddet')) {
                DB::table('barcode_dmddet')->where('bcode', $bcode)->delete();
            }

            // Delete diamond summary
            if ($this->hasTable('barcodedmd')) {
                DB::table('barcodedmd')->where('bcode', $bcode)->delete();
            }

            // Delete the barcode itself
            DB::table('barcode')->where('bcode', $bcode)->delete();

            // Audit log in delpart table
            if ($this->hasTable('delpart')) {
                $delRow = [
                    'tdate'   => date('Y-m-d'),
                    'part'    => $partDesc,
                    'control' => $control,
                    'uid'     => $uid,
                ];
                $delColumns = $this->getColumns('delpart');
                $delRow     = $this->filterToColumns($delRow, $delColumns);
                if (!empty($delRow)) {
                    DB::table('delpart')->insert($delRow);
                }
            }

            DB::commit();

            $message = 'Barcode deleted successfully';
            if (SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code'))) {
                try {
                    $secondarySync = (new SecondaryDatabaseSync())->deleteBarcode($bcode, $docno);
                    $message .= ' and synced to secondary DB (' . ($secondarySync['database'] ?? '') . ')';
                } catch (\Throwable $e) {
                    $message .= '. Primary delete completed, but secondary sync failed: ' . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting barcode: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/barcode-entry/print
     * Generate printer command file and trigger print command.
     */
    public function printBarcode(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $bcode = (int) ($request->input('bcode', $request->input('barcode', 0)));
        if ($bcode <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid barcode number'], 400);
        }

        try {
            $result = $this->dispatchBarcodePrint($bcode, [
                'printType' => (string) $request->input('printType', 'TemplateDesigner'),
                'printMode' => (string) $request->input('printMode', 'windows_image'),
                'renderAsImage' => (string) $request->input('renderAsImage', 'Y'),
                'printerName' => (string) $request->input('printerName', ''),
                'printerShareName' => (string) $request->input('printerShareName', ''),
                'stickerMode' => (string) $request->input('stickerMode', ''),
                'designerColumns' => (string) $request->input('designerColumns', ''),
                'designerTemplate' => (string) $request->input('designerTemplate', ''),
                'pxinc' => (int) $request->input('pxinc', 0),
                'pyinc' => (int) $request->input('pyinc', 0),
                'density' => (int) $request->input('density', 0),
            ]);
            if (!(bool) ($result['commandTriggered'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Print command did not execute. Check print mode/printer settings.',
                    'data' => $result,
                ], 500);
            }
            return response()->json([
                'success' => true,
                'message' => 'Print job sent for barcode ' . $bcode,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Print failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/barcode-entry/print-sample
     * Print a sample label using current/override printer settings.
     */
    public function printSample(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $settings = $this->loadSettings();
            $sw = $settings['Software'] ?? [];

            $patch = [
                'BPrintType' => (string) $request->input('printType', ($sw['BPrintType'] ?? '')),
                'BCPrintMode' => (string) $request->input('printMode', ($sw['BCPrintMode'] ?? '')),
                'BCPrinterName' => (string) $request->input('printerName', ($sw['BCPrinterName'] ?? '')),
                'BCPrinterShareName' => (string) $request->input('printerShareName', ($sw['BCPrinterShareName'] ?? '')),
                'BCStickerMode' => (string) $request->input('stickerMode', ($sw['BCStickerMode'] ?? 'single')),
                'BCDesignerColumns' => (string) $request->input('designerColumns', ($sw['BCDesignerColumns'] ?? '1')),
                'BCPrintBat' => (string) $request->input('printBat', ($sw['BCPrintBat'] ?? '')),
                'BCPPrnPath' => (string) $request->input('prnPath', ($sw['BCPPrnPath'] ?? '')),
                'BCDensity' => (string) $request->input('density', ($sw['BCDensity'] ?? '14')),
                'BCPXINC' => (string) $request->input('pxinc', ($sw['BCPXINC'] ?? '0')),
                'BCPYINC' => (string) $request->input('pyinc', ($sw['BCPYINC'] ?? '0')),
                'BCDesignerTemplate' => (string) $request->input('designerTemplate', ($sw['BCDesignerTemplate'] ?? '')),
                'BCRenderAsImage' => (string) $request->input('renderAsImage', ($sw['BCRenderAsImage'] ?? 'Y')),
            ];
            $settings['Software'] = array_merge($sw, $patch);

            $targetDir = storage_path('app' . DIRECTORY_SEPARATOR . 'print-cache');
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }
            $prnFile = $targetDir . DIRECTORY_SEPARATOR . 'barcode-print-' . uniqid('', true) . '.prn';

            $row = (object) [
                'bcode' => 999999,
                'icode' => 'SAMPLE',
                'item_name' => (string) $request->input('sampleText', 'Sample Jewelry Tag'),
                'weight' => 12.345,
                'stweight' => 0.540,
                'tamt' => 125000.00,
                'mcrate' => 8.50,
                'mc' => 250.00,
                'vap' => 6.00,
                'rate' => 8100.00,
                'qtype' => '18K',
                'model' => 'M-01',
                'smithcode' => 'SM001',
            ];

            $printType = strtoupper(trim($this->setting('BPrintType', 'DEFAULT', $settings)));
            $density = (int) $this->setting('BCDensity', '14', $settings);
            $pxinc = (int) $this->setting('BCPXINC', '0', $settings);
            $pyinc = (int) $this->setting('BCPYINC', '0', $settings);
            $density = max(1, min(15, $density));

            $lines = $this->buildPrinterLines($row, $printType, $pxinc, $pyinc, $density, $settings);
            @file_put_contents($prnFile, implode('', $lines));
            $executed = $this->triggerPrintCommand($settings, $prnFile);

            if (!$executed) {
                $mode = (string) $this->setting('BCPrintMode', 'auto', $settings);
                $printerName = (string) $this->setting('BCPrinterName', '', $settings);
                return response()->json([
                    'success' => false,
                    'message' => 'Sample print command did not execute. Check print mode/printer settings.',
                    'data' => [
                        'prnFile' => $prnFile,
                        'printType' => $printType,
                        'printMode' => $mode,
                        'printer' => $printerName,
                        'commandTriggered' => false,
                    ],
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sample print job sent',
                'data' => [
                    'prnFile' => $prnFile,
                    'printType' => $printType,
                    'printMode' => $this->setting('BCPrintMode', 'auto', $settings),
                    'printer' => $this->setting('BCPrinterName', '', $settings),
                    'commandTriggered' => $executed,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sample print failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/barcode-entry/search
     * Search barcode list for edit popup.
     */
    public function search(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $q = trim((string) $request->input('q', ''));
            $limit = (int) $request->input('limit', 50);
            if ($limit <= 0 || $limit > 200) {
                $limit = 50;
            }

            if (!$this->hasTable('barcode')) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $query = DB::table('barcode as b');
            if ($this->hasTable('items')) {
                $query->leftJoin('items as i', 'i.code', '=', 'b.icode')
                    ->select('b.bcode', 'b.icode', 'b.weight', 'b.tamt', 'b.stk', DB::raw('COALESCE(i.name, "") as name'));
            } else {
                $query->select('b.bcode', 'b.icode', 'b.weight', 'b.tamt', 'b.stk', DB::raw('"" as name'));
            }

            if ($q !== '') {
                $query->where(function ($w) use ($q) {
                    $w->whereRaw('CAST(b.bcode AS CHAR) like ?', ['%' . $q . '%'])
                        ->orWhere('b.icode', 'like', '%' . $q . '%');
                });
            }

            $rows = $query
                ->orderByDesc('b.bcode')
                ->limit($limit)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->toArray();

            return response()->json(['success' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /barcode-single-entry/next-barcode
     * Get the next available barcode number.
     */
    public function nextBarcode(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $settings = $this->loadSettings();
            $bcMaxNo  = strtoupper($this->setting('BCMaxNo', 'Y', $settings));
            $next     = 0;

            if ($bcMaxNo === 'Y') {
                // Use MAX(bcode) from barcode table
                if ($this->hasTable('barcode')) {
                    $next = (int) (DB::table('barcode')->max('bcode') ?? 0);
                }
            } else {
                // Use counter from generali
                if ($this->hasTable('generali')) {
                    $next = (int) (DB::table('generali')
                        ->where('code', 'BCNO')
                        ->value('cvalue') ?? 0);
                }
            }

            if ($next <= 0) {
                $next = 100000;
            } else {
                $next++;
            }

            return response()->json([
                'success' => true,
                'data'    => ['nextBarcode' => $next],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /barcode-single-entry/next-docno
     * Get the next available document number (BD + 6 zero-padded digits).
     */
    public function nextDocNo(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $current = 0;
            if ($this->hasTable('generali')) {
                $current = (int) (DB::table('generali')
                    ->where('code', 'BCDOCNO')
                    ->value('cvalue') ?? 0);
            }

            $next      = $current + 1;
            $formatted = 'BD' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);

            return response()->json([
                'success' => true,
                'data'    => [
                    'nextDocNo'  => $formatted,
                    'nextDocNum' => $next,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /barcode-single-entry/load-item
     * Load item details for a given item code.
     */
    public function loadItem(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $icode = trim((string) $request->input('icode', ''));
        if ($icode === '') {
            return response()->json(['success' => false, 'message' => 'Item code is required'], 400);
        }

        if (!$this->hasTable('items')) {
            return response()->json(['success' => false, 'message' => 'items table not found'], 500);
        }

        try {
            $item = DB::table('items')->where('code', $icode)->first();
            if (!$item) {
                return response()->json(['success' => false, 'message' => 'Item not found'], 404);
            }

            $result = [
                'code' => (string) ($item->code ?? ''),
                'name' => (string) ($item->name ?? ''),
            ];

            // Safely read optional columns
            $optionalColumns = [
                'wastage', 'mcharge', 'vaperc', 'stkinnos', 'nodisc',
                'stickerwgt', 'defquality', 'minvap', 'touch', 'stktouch',
                'rate', 'cost', 'itype', 'disabled', 'defstktype',
            ];
            foreach ($optionalColumns as $col) {
                if (Schema::hasColumn('items', $col)) {
                    $result[$col] = $item->{$col} ?? '';
                }
            }

            // Look up defquality's touch value from itemsqtype
            $defQuality = (string) ($result['defquality'] ?? '');
            $qualityTouch = 0;
            if ($defQuality !== '' && $this->hasTable('itemsqtype')) {
                $qRow = DB::table('itemsqtype')->where('code', $defQuality)->first();
                if ($qRow && isset($qRow->touch)) {
                    $qualityTouch = (float) $qRow->touch;
                } elseif ($qRow && isset($qRow->cvalue)) {
                    $qualityTouch = (float) $qRow->cvalue;
                }
            }
            $result['qualityTouch'] = $qualityTouch;

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /barcode-single-entry/load-document
     * Load document info for a given document number.
     */
    public function loadDocument(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $docno = trim((string) $request->input('docno', ''));
        if ($docno === '') {
            return response()->json(['success' => false, 'message' => 'Document number is required'], 400);
        }

        try {
            $docInfo = null;
            if ($this->hasTable('barcodedoc')) {
                $docInfo = DB::table('barcodedoc')->where('docno', $docno)->first();
            }

            // Accumulated weight/nos from barcode table
            $accWeight = 0;
            $accNos    = 0;
            if ($this->hasTable('barcode') && Schema::hasColumn('barcode', 'docno')) {
                $acc = DB::table('barcode')
                    ->where('docno', $docno)
                    ->selectRaw('COALESCE(SUM(weight), 0) as total_weight, COALESCE(SUM(qty), 0) as total_nos')
                    ->first();
                if ($acc) {
                    $accWeight = (float) ($acc->total_weight ?? 0);
                    $accNos    = (int) ($acc->total_nos ?? 0);
                }
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'document'      => $docInfo,
                    'accWeight'     => $accWeight,
                    'accNos'        => $accNos,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /barcode-single-entry/lookups
     * Load all dropdown data for the form.
     */
    public function lookups(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            return response()->json([
                'success' => true,
                'data'    => [
                    'items'        => $this->safeLookup('items', ['code', 'name']),
                    'qualityTypes' => $this->safeLookup('itemsqtype', ['code', 'name']),
                    'subgroups'    => $this->safeLookup('itemsubgrp', ['code', 'name']),
                    'models'       => $this->modelLookup(),
                    'smiths'       => $this->smithLookup(),
                    'salesman'     => $this->safeLookup('salesman', ['code', 'name']),
                    'counters'     => $this->safeLookup('counter', ['code', 'name']),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Legacy action API compatibility: /api/barcode-entry/action?action=...
     */
    public function action(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action = strtolower(trim((string) $request->input('action', '')));

        try {
            return match ($action) {
                'get_next_barcode' => response()->json(['success' => true, 'barcode' => $this->computeNextBarcode()]),
                'new_doc' => response()->json(['success' => true, 'docno' => $this->computeNextDocNo()]),
                'get_thrate' => response()->json(['success' => true, 'rate' => $this->getTodayRate()]),
                'lookup_item' => $this->legacyLookupItem($request),
                'lookup_barcode' => $this->legacyLookupBarcode($request),
                'lookup_doc' => $this->legacyLookupDoc($request),
                'get_qtype_touch' => $this->legacyQtypeTouch($request),
                'get_stone_rate' => $this->legacyStoneRate($request),
                'save_setting' => $this->legacySaveSetting($request),
                'calc_stktouch' => $this->legacyCalcStkTouch($request),
                'upload_photo' => $this->legacyUploadPhoto($request),
                'search_items' => $this->legacySearchItems($request),
                'search_smiths' => $this->legacySearchSmiths($request),
                'search_qtypes' => $this->legacySearchQtypes($request),
                'search_subgrps' => $this->legacySearchSubgrps($request),
                'search_models' => $this->legacySearchModels($request),
                'search_barcodes' => $this->legacySearchBarcodes($request),
                'print_barcode' => $this->printBarcode($request),
                'save' => $this->legacySave($request),
                'delete' => $this->delete($request),
                default => response()->json(['success' => false, 'error' => 'Unknown action: ' . $action], 400),
            };
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ── Private helpers ─────────────────────────────────────────────────

    /**
     * Load barcode settings from shared INI file used by all modules.
     */
    private function loadSettings(): array
    {
        $iniPath = $this->settingsIniPath();
        if (is_file($iniPath)) {
            return $this->parseIniSettings((string) file_get_contents($iniPath));
        }

        // One-time legacy fallback: import JSON then write INI.
        $legacyJson = storage_path('app/barcode-settings.json');
        if (is_file($legacyJson)) {
            $decoded = json_decode((string) file_get_contents($legacyJson), true);
            if (is_array($decoded)) {
                $this->writeIniSettings($decoded);
                return $decoded;
            }
        }

        return ['Software' => [], 'Application' => [], 'Company' => [], 'Printer' => [], 'Rates' => []];
    }

    private function dispatchBarcodePrint(int $bcode, array $overrides = []): array
    {
        if (!$this->hasTable('barcode')) {
            throw new \RuntimeException('barcode table not found');
        }

        $query = DB::table('barcode as b')->where('b.bcode', $bcode);
        if ($this->hasTable('items')) {
            $query->leftJoin('items as i', 'i.code', '=', 'b.icode')
                ->select('b.*', DB::raw('COALESCE(i.name, "") as item_name'));
        } else {
            $query->select('b.*', DB::raw('"" as item_name'));
        }
        $row = $query->first();

        if (!$row) {
            throw new \RuntimeException('Barcode not found');
        }

        $settings = $this->loadSettings();
        $sw = $settings['Software'] ?? [];
        $patch = [];
        if (isset($overrides['printMode'])) {
            $patch['BCPrintMode'] = (string) $overrides['printMode'];
        }
        if (isset($overrides['printerName'])) {
            $patch['BCPrinterName'] = (string) $overrides['printerName'];
        }
        if (isset($overrides['printerShareName'])) {
            $patch['BCPrinterShareName'] = (string) $overrides['printerShareName'];
        }
        if (isset($overrides['renderAsImage'])) {
            $patch['BCRenderAsImage'] = (string) $overrides['renderAsImage'];
        }
        if (isset($overrides['designerTemplate'])) {
            $patch['BCDesignerTemplate'] = (string) $overrides['designerTemplate'];
        }
        if (isset($overrides['stickerMode'])) {
            $patch['BCStickerMode'] = (string) $overrides['stickerMode'];
        }
        if (isset($overrides['designerColumns'])) {
            $patch['BCDesignerColumns'] = (string) $overrides['designerColumns'];
        }
        if (!empty($patch)) {
            $settings['Software'] = array_merge($sw, $patch);
        }
        $targetDir = storage_path('app' . DIRECTORY_SEPARATOR . 'print-cache');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        $prnFile = $targetDir . DIRECTORY_SEPARATOR . 'barcode-print-' . uniqid('', true) . '.prn';
        $printType = strtoupper(trim((string) ($overrides['printType'] ?? '')));
        if ($printType === '') {
            $hasDesignerTemplate = trim((string) ($settings['Software']['BCDesignerTemplate'] ?? '')) !== '';
            if ($hasDesignerTemplate) {
                $printType = 'TEMPLATEDESIGNER';
            } else {
                $printType = strtoupper($this->setting('BPrintType', 'DEFAULT', $settings));
            }
        }

        // Keep barcode entry print aligned with sticker designer output.
        if ($printType === 'TEMPLATEDESIGNER' && empty($settings['Software']['BCRenderAsImage'])) {
            $settings['Software']['BCRenderAsImage'] = 'Y';
        }
        if ($printType === 'TEMPLATEDESIGNER' && trim((string) ($settings['Software']['BCDesignerTemplate'] ?? '')) === '') {
            $settings['Software']['BCDesignerTemplate'] = json_encode([
                'mode' => 'single',
                'columns' => 1,
                'sticker1' => [
                    ['type' => 'text', 'x' => 10, 'y' => 12, 'r' => 1, 'value' => '[Purity]'],
                    ['type' => 'text', 'x' => 28, 'y' => 14, 'r' => 0, 'value' => '[ItemCode]'],
                    ['type' => 'barcode', 'x' => 28, 'y' => 30, 'w' => 128, 'h' => 42, 'r' => 0, 'value' => '[BarCode]'],
                    ['type' => 'text', 'x' => 170, 'y' => 18, 'r' => 0, 'value' => 'GW:[Weight]'],
                    ['type' => 'text', 'x' => 170, 'y' => 40, 'r' => 0, 'value' => 'SW:[StWgt]'],
                    ['type' => 'text', 'x' => 170, 'y' => 62, 'r' => 0, 'value' => 'NW:[NetWgt]'],
                    ['type' => 'text', 'x' => 170, 'y' => 84, 'r' => 0, 'value' => 'SC:[Amount]'],
                ],
                'sticker2' => [],
            ], JSON_UNESCAPED_SLASHES);
        }
        $pxinc = (int) ($overrides['pxinc'] ?? 0);
        $pyinc = (int) ($overrides['pyinc'] ?? 0);
        $density = (int) ($overrides['density'] ?? 0);
        if ($density <= 0) {
            $density = (int) $this->setting('BCDensity', '14', $settings);
        }
        $density = max(1, min(15, $density));
        $pxinc += (int) $this->setting('BCPXINC', '0', $settings);
        $pyinc += (int) $this->setting('BCPYINC', '0', $settings);

        $lines = $this->buildPrinterLines($row, $printType, $pxinc, $pyinc, $density, $settings);
        @file_put_contents($prnFile, implode('', $lines));

        $executed = $this->triggerPrintCommand($settings, $prnFile);

        return [
            'bcode' => $bcode,
            'prnFile' => $prnFile,
            'printType' => $printType,
            'pxinc' => $pxinc,
            'pyinc' => $pyinc,
            'density' => $density,
            'printer' => (string) ($settings['Software']['BCPrinterName'] ?? ''),
            'printMode' => (string) ($settings['Software']['BCPrintMode'] ?? 'auto'),
            'commandTriggered' => $executed,
        ];
    }

    private function buildPrinterLines(object $row, string $printType, int $pxinc = 0, int $pyinc = 0, int $density = 14, array $settings = []): array
    {
        $cr = "\r\n";
        $rowData = array_change_key_case((array) $row, CASE_LOWER);
        $pick = static function (array $keys, string $default = '') use ($rowData): string {
            foreach ($keys as $k) {
                $kk = strtolower(trim((string) $k));
                if ($kk === '' || !array_key_exists($kk, $rowData)) {
                    continue;
                }
                $v = trim((string) ($rowData[$kk] ?? ''));
                if ($v !== '') {
                    return $v;
                }
            }
            return $default;
        };
        $bcode = (string) ((int) ($row->bcode ?? 0));
        $icode = (string) ($row->icode ?? '');
        $name = (string) ($row->item_name ?? '');
        $wt = (float) ($row->weight ?? 0);
        $sw = (float) ($row->stweight ?? 0);
        $stPrice = (float) ($row->stprice ?? 0);
        $nw = $wt - $sw;
        $amt = (float) ($row->tamt ?? 0);
        $mc = (float) ($row->mcrate ?? $row->mc ?? 0);
        $vap = (float) ($row->vap ?? 0);
        $rate = (float) ($row->rate ?? 0);
        $qtype = (string) ($row->qtype ?? '');
        $model = (string) ($row->model ?? '');
        $smith = (string) ($row->smithcode ?? '');
        $serialNo = (string) ($row->serialno ?? '');
        $part = (string) ($row->part ?? '');
        $huid = (string) ($row->huid ?? '');
        $qunit = (string) ($row->qunit ?? '');
        $modelPngMap = $this->decodeModelPngMap($settings);
        $modelPng = $modelPngMap[strtolower(trim($model))] ?? '';

        if ($printType === 'TEMPLATEDESIGNER') {
            $designer = (string) (($settings['Software']['BCDesignerTemplate'] ?? '') ?: '');
            $parsed = [];
            $templateSticker1 = [];
            $templateSticker2 = [];
            $templateMode = 'single';
            $templateColumns = 1;
            $isStructuredTemplate = false;
            try {
                $tmp = json_decode($designer, true);
                if (is_array($tmp)) {
                    if (array_is_list($tmp)) {
                        $parsed = $tmp;
                        $templateSticker1 = $tmp;
                    } else {
                        $isStructuredTemplate = true;
                        $templateSticker1 = is_array($tmp['sticker1'] ?? null) ? $tmp['sticker1'] : [];
                        $templateSticker2 = is_array($tmp['sticker2'] ?? null) ? $tmp['sticker2'] : [];
                        $templateMode = strtolower((string) ($tmp['mode'] ?? 'single'));
                        $templateColumns = (int) ($tmp['columns'] ?? 1);
                        $parsed = $templateSticker1;
                    }
                }
            } catch (\Throwable $e) {
                $parsed = [];
            }
            if (!empty($parsed) || !empty($templateSticker2)) {
                $stcode1 = '';
                $stcode2 = '';
                $stcode3 = '';
                $weight1 = 0.0;
                $weight2 = 0.0;
                $weight3 = 0.0;
                $stone1 = 0.0;
                $stone2 = 0.0;
                $stone3 = 0.0;
                $dmdNos = 0;
                $dmdWgt = 0.0;
                $dmdAmt = 0.0;
                $dmdUnit = '';

                if ($this->hasTable('barcode_dmddet') && isset($row->bcode)) {
                    try {
                        $stones = DB::table('barcode_dmddet')
                            ->where('bcode', (int) $row->bcode)
                            ->orderBy('sno')
                            ->limit(3)
                            ->get(['stcode', 'wgt', 'amount'])
                            ->values();
                        if (isset($stones[0])) {
                            $stcode1 = (string) ($stones[0]->stcode ?? '');
                            $weight1 = (float) ($stones[0]->wgt ?? 0);
                            $stone1 = (float) ($stones[0]->amount ?? 0);
                        }
                        if (isset($stones[1])) {
                            $stcode2 = (string) ($stones[1]->stcode ?? '');
                            $weight2 = (float) ($stones[1]->wgt ?? 0);
                            $stone2 = (float) ($stones[1]->amount ?? 0);
                        }
                        if (isset($stones[2])) {
                            $stcode3 = (string) ($stones[2]->stcode ?? '');
                            $weight3 = (float) ($stones[2]->wgt ?? 0);
                            $stone3 = (float) ($stones[2]->amount ?? 0);
                        }
                    } catch (\Throwable $e) {
                        // keep defaults
                    }
                }

                if ($this->hasTable('barcodedmd') && isset($row->bcode)) {
                    try {
                        $dmd = DB::table('barcodedmd')
                            ->where('bcode', (int) $row->bcode)
                            ->first(['dmdnos', 'dmdwgt', 'dmdamt', 'dmdunit']);
                        if ($dmd) {
                            $dmdNos = (int) ($dmd->dmdnos ?? 0);
                            $dmdWgt = (float) ($dmd->dmdwgt ?? 0);
                            $dmdAmt = (float) ($dmd->dmdamt ?? 0);
                            $dmdUnit = (string) ($dmd->dmdunit ?? '');
                        }
                    } catch (\Throwable $e) {
                        // keep defaults
                    }
                }

                $replace = [
                    '[ShopName]' => (string) (config('app.name') ?: 'Shop'),
                    '[BarCode]' => $bcode,
                    '[ItemCode]' => $icode,
                    '[ItemName]' => $name,
                    '[DesignCode]' => $pick(['designcode', 'design_code', 'descode', 'dcode', 'model'], $model !== '' ? $model : $icode),
                    '[DesignName]' => $pick(['designname', 'design_name', 'desname', 'dname', 'item_name'], $name),
                    '[DesignSize]' => $pick(['designsize', 'design_size', 'dessize', 'size']),
                    '[DesignStyle]' => $pick(['designstyle', 'design_style', 'style']),
                    '[DesignStyleCode]' => $pick(['designstylecode', 'design_style_code', 'stylecode', 'style_code']),
                    '[ItemGroup]' => $pick(['itemgroup', 'item_group', 'subgrp', 'isubgrp', 'igrp']),
                    '[HUID]' => $pick(['huid']),
                    '[CertificationNo]' => $pick(['certificationnumber', 'certification_no', 'certno', 'certificate_no']),
                    '[SKUNumber]' => $pick(['skunumber', 'sku_number', 'sku']),
                    '[ReferenceNo]' => $pick(['referencenumber', 'reference_no', 'refno', 'reference']),
                    '[MetalPurity]' => $pick(['metalpurity', 'metal_purity', 'purity', 'qtype'], $qtype),
                    '[Purity]' => $pick(['purity', 'qtype', 'metalpurity', 'metal_purity'], $qtype),
                    '[MakeCharge]' => $pick(['makecharge', 'makingcharge', 'mc_label'], 'MC ' . number_format($mc > 0 ? $mc : $amt, 2, '.', '')),
                    '[Weight]' => number_format($wt, 3, '.', ''),
                    '[StWgt]' => number_format($sw, 3, '.', ''),
                    '[NetWgt]' => number_format($nw, 3, '.', ''),
                    '[Rate]' => number_format($rate, 2, '.', ''),
                    '[MetalRate]' => number_format($rate, 2, '.', ''),
                    '[Amount]' => number_format($amt, 2, '.', ''),
                    '[Price]' => number_format($amt, 2, '.', ''),
                    '[StoneRate]' => number_format(($sw > 0 ? $stone1 / max($weight1, 0.001) : 0), 2, '.', ''),
                    '[StoneAmount]' => number_format($stone1 + $stone2 + $stone3, 2, '.', ''),
                    '[VA]' => number_format($mc > 0 ? $mc : $vap, 2, '.', '') . ($mc > 0 ? '' : '%'),
                    '[MC]' => number_format($mc, 2, '.', ''),
                    '[QType]' => $qtype,
                    '[QUnit]' => $qunit,
                    '[Model]' => $model,
                    '[ModelPng]' => $modelPng,
                    '[SmithCode]' => $smith,
                    '[Part]' => $part,
                    '[MRP]' => $part,
                    '[SerialNo]' => $serialNo,
                    '[HUID]' => $huid,
                    '[Stcode1]' => $stcode1,
                    '[Stcode2]' => $stcode2,
                    '[Stcode3]' => $stcode3,
                    '[Weight1]' => number_format($weight1, 3, '.', ''),
                    '[Weight2]' => number_format($weight2, 3, '.', ''),
                    '[Weight3]' => number_format($weight3, 3, '.', ''),
                    '[Stone1]' => number_format($stone1, 2, '.', ''),
                    '[Stone2]' => number_format($stone2, 2, '.', ''),
                    '[Stone3]' => number_format($stone3, 2, '.', ''),
                    '[StPrice]' => number_format($stPrice + $dmdAmt, 2, '.', ''),
                    '[DmdWgt]' => number_format($dmdWgt, 3, '.', ''),
                    '[DmdNos]' => (string) $dmdNos,
                    '[DmdAmt]' => number_format($dmdAmt, 2, '.', ''),
                    '[DmdUnit]' => $dmdUnit,
                    '[CHR2]' => chr(2),
                    '[CHR13]' => chr(13),
                    '[CHR27]' => chr(27),
                ];

                $templateName = trim((string) ($templateData['tplName'] ?? ''));
                if (
                    $isStructuredTemplate &&
                    strtolower((string) ($templateData['mode'] ?? 'single')) === 'single' &&
                    (
                        strcasecmp($templateName, 'Honeywell Slim Tag') === 0 ||
                        ((int) ($templateData['canvasW'] ?? 0) > 420 && (int) ($templateData['canvasH'] ?? 0) <= 120)
                    )
                ) {
                    $templateData['canvasW'] = 320;
                    $templateData['canvasH'] = 92;
                    $templateData['cols'] = 1;
                    $templateData['rows'] = 1;
                    $templateSticker1 = [
                        ['type' => 'text', 'value' => 'GW:[Weight]', 'x' => 44, 'y' => 8, 'fs' => 10, 'rot' => 0],
                        ['type' => 'text', 'value' => 'ST:[StWgt]', 'x' => 44, 'y' => 25, 'fs' => 10, 'rot' => 0],
                        ['type' => 'text', 'value' => 'NW:[NetWgt]', 'x' => 44, 'y' => 42, 'fs' => 10, 'rot' => 0],
                        ['type' => 'text', 'value' => 'RS:[MetalRate]', 'x' => 44, 'y' => 59, 'fs' => 10, 'rot' => 0],
                        ['type' => 'barcode128', 'value' => '[BarCode]', 'x' => 148, 'y' => 8, 'w' => 138, 'h' => 42],
                        ['type' => 'text', 'value' => '[BarCode]', 'x' => 178, 'y' => 55, 'fs' => 8, 'rot' => 0],
                        ['type' => 'text', 'value' => '[ItemCode]', 'x' => 174, 'y' => 69, 'fs' => 8, 'rot' => 0],
                    ];
                    $templateSticker2 = [];
                }

                $canvasWidth = max(80, (int) ($templateData['canvasW'] ?? 380));
                $canvasHeight = max(40, (int) ($templateData['canvasH'] ?? 180));
                $columns = (int) ($settings['Software']['BCDesignerColumns'] ?? $templateColumns);
                $stickerMode = strtolower((string) ($settings['Software']['BCStickerMode'] ?? $templateMode));
                if ($stickerMode === 'double' && $columns < 2) {
                    $columns = 2;
                }
                if ($columns < 1) {
                    $columns = 1;
                }
                if ($stickerMode === 'single') {
                    $pxinc = max(-20, min(20, $pxinc));
                }
                $pyinc = max(-20, min(20, $pyinc));

                $maxX = 0;
                foreach ($templateSticker1 as $el) {
                    if (!is_array($el)) {
                        continue;
                    }
                    $maxX = max($maxX, (int) ($el['x'] ?? 0));
                }
                foreach ($templateSticker2 as $el) {
                    if (!is_array($el)) {
                        continue;
                    }
                    $maxX = max($maxX, (int) ($el['x'] ?? 0));
                }
                $columnGap = max(320, $maxX + 120);
                // Physical Honeywell jewelry tag stock is much larger than the
                // on-screen design canvas. Keep the actual stock size fixed so
                // one print maps to one label, while still drawing the slim
                // design inside it.
                $labelWidthMm = $stickerMode === 'double' ? '151.00' : '75.50';
                $labelHeightMm = '25.00';

                $lines = [
                    $cr,
                    'SIZE ' . $labelWidthMm . ' mm,' . $labelHeightMm . " mm{$cr}",
                    "GAP 0 mm,0 mm{$cr}",
                    "OFFSET 0 mm{$cr}",
                    "SPEED 2{$cr}",
                    'DENSITY ' . $density . $cr,
                    "DIRECTION 0,0{$cr}",
                    "REFERENCE 0,0{$cr}",
                    "SET PEEL OFF OFF{$cr}",
                    "SET CUTTER OFF{$cr}",
                    "SET PARTIAL_CUTTER OFF{$cr}",
                    "SET TEAR ON{$cr}",
                    "CLS{$cr}",
                ];

                $renderAsImage = strtoupper((string) ($settings['Software']['BCRenderAsImage'] ?? 'Y')) === 'Y';
                if ($renderAsImage && !function_exists('imagecreatetruecolor')) {
                    $renderAsImage = false;
                }
                if ($renderAsImage) {
                    $renderStickerImage = function (array $elements, int $xShift) use (&$lines, $replace, $pxinc, $pyinc, $cr): void {
                        $bitmap = $this->renderTemplateElementsToBitmap($elements, $replace, $canvasWidth, $canvasHeight);
                        if ($bitmap === null) {
                            return;
                        }
                        [$bytesPerRow, $height, $hex] = $bitmap;
                        $x = max(0, $pxinc + $xShift);
                        $y = max(0, $pyinc);
                        $lines[] = 'BITMAP ' . $x . ',' . $y . ',' . $bytesPerRow . ',' . $height . ',0,' . $hex . $cr;
                    };
                    $renderQrOverlay = function (array $elements, int $xShift) use (&$lines, $replace, $pxinc, $pyinc, $cr): void {
                        foreach ($elements as $el) {
                            if (!is_array($el)) {
                                continue;
                            }
                            $type = strtolower((string) ($el['type'] ?? 'text'));
                            if ($type !== 'qrcode' && $type !== 'qr') {
                                continue;
                            }
                            $x = max(0, ((int) ($el['x'] ?? 0)) + $pxinc + $xShift);
                            $y = max(0, ((int) ($el['y'] ?? 0)) + $pyinc);
                            $v = (string) ($el['value'] ?? '');
                            foreach ($replace as $tag => $tagVal) {
                                $v = str_replace($tag, (string) $tagVal, $v);
                            }
                            $v = trim(str_replace('"', '', $v));
                            if ($v === '') {
                                continue;
                            }
                            $w = max(20, (int) ($el['w'] ?? 42));
                            $h = max(20, (int) ($el['h'] ?? 42));
                            $minSide = min($w, $h);
                            $size = 4;
                            if ($minSide <= 34) {
                                $size = 2;
                            } elseif ($minSide <= 44) {
                                $size = 3;
                            } elseif ($minSide <= 70) {
                                $size = 4;
                            } elseif ($minSide <= 100) {
                                $size = 5;
                            } else {
                                $size = 6;
                            }
                            $lines[] = 'QRCODE ' . $x . ',' . $y . ',L,' . $size . ',A,0,M2,S7,"' . $v . '"' . $cr;
                        }
                    };

                    if ($stickerMode === 'double') {
                        $renderStickerImage($templateSticker1, 0);
                        $renderQrOverlay($templateSticker1, 0);
                        if (!empty($templateSticker2)) {
                            $renderStickerImage($templateSticker2, $columnGap);
                            $renderQrOverlay($templateSticker2, $columnGap);
                        } else {
                            $renderStickerImage($templateSticker1, $columnGap);
                            $renderQrOverlay($templateSticker1, $columnGap);
                        }
                    } else {
                        $renderStickerImage($templateSticker1, 0);
                        $renderQrOverlay($templateSticker1, 0);
                    }

                    $lines[] = "PRINT 1{$cr}";
                    return $lines;
                }

                $renderElements = function (array $elements, int $xShift) use (&$lines, $replace, $pxinc, $pyinc, $cr): void {
                    foreach ($elements as $el) {
                        if (!is_array($el)) {
                            continue;
                        }
                        $type = strtolower((string) ($el['type'] ?? 'text'));
                        $x = ((int) ($el['x'] ?? 0)) + $pxinc + $xShift;
                        $y = ((int) ($el['y'] ?? 0)) + $pyinc;
                        $rotation = max(0, min(3, (int) ($el['r'] ?? 0)));
                        $deg = $rotation * 90;
                        $v = (string) ($el['value'] ?? '');
                        foreach ($replace as $tag => $tagVal) {
                            $v = str_replace($tag, (string) $tagVal, $v);
                        }
                        $v = str_replace('"', '', $v);
                        if ($v === '') {
                            continue;
                        }

                        if ($type === 'qrcode' || $type === 'qr') {
                            $lines[] = 'QRCODE ' . $x . ',' . $y . ',L,2,A,' . $deg . ',M2,S7,"' . $v . '"' . $cr;
                        } elseif ($type === 'barcode' || $type === 'barcode128') {
                            $lines[] = 'BARCODE ' . $x . ',' . $y . ',"128M",46,0,' . $deg . ',2,2,"' . $v . '"' . $cr;
                        } elseif ($type === 'image') {
                            $w = (int) ($el['w'] ?? 96);
                            $h = (int) ($el['h'] ?? 96);
                            foreach ($this->buildTsplBitmapLinesFromPng($x, $y, $v, $w, $h, $cr) as $imgLine) {
                                $lines[] = $imgLine;
                            }
                        } else {
                            $lines[] = 'TEXT ' . $x . ',' . $y . ',"ROMAN.TTF",' . $deg . ',1,7,"' . $v . '"' . $cr;
                        }
                    }
                };

                if ($isStructuredTemplate) {
                    if ($stickerMode === 'double') {
                        $renderElements($templateSticker1, 0);
                        if (!empty($templateSticker2)) {
                            $renderElements($templateSticker2, $columnGap);
                        } else {
                            $renderElements($templateSticker1, $columnGap);
                        }
                    } else {
                        $renderElements($templateSticker1, 0);
                    }
                } else {
                    for ($col = 0; $col < $columns; $col++) {
                        $xShift = $col * $columnGap;
                        $renderElements($parsed, $xShift);
                    }
                }
                $lines[] = "PRINT 1{$cr}";
                return $lines;
            }
        }

        // Keep printer output simple and deterministic when printer type is not mapped yet.
        $x = fn (int $base): int => $base + $pxinc;
        $y = fn (int $base): int => $base + $pyinc;
        $lines = [
            $cr,
            "Q192,019{$cr}",
            "q620{$cr}",
            "rN{$cr}",
            "S4{$cr}",
            'D' . $density . $cr,
            "ZT{$cr}",
            "N{$cr}",
            'A' . $x(450) . ',' . $y(0) . ',0,2,1,1,N,"GW:' . number_format($wt, 3, '.', '') . "\"{$cr}",
            'A' . $x(450) . ',' . $y(20) . ',0,2,1,1,N,"SW:' . number_format($sw, 3, '.', '') . "\"{$cr}",
            'A' . $x(450) . ',' . $y(40) . ',0,2,1,1,N,"NW:' . number_format($nw, 3, '.', '') . "\"{$cr}",
            'A' . $x(600) . ',' . $y(5) . ',1,1,1,1,N,"VA:' . number_format($mc > 0 ? $mc : $vap, 2, '.', '') . ($mc > 0 ? '' : '%') . "\"{$cr}",
            'A' . $x(450) . ',' . $y(55) . ',0,1,1,1,N,"' . str_replace('"', '', $name) . "\"{$cr}",
            'A' . $x(600) . ',' . $y(30) . ',1,1,1,1,N,"Amt:' . number_format($amt, 2, '.', '') . "\"{$cr}",
            'B' . $x(432) . ',' . $y(126) . ',0,1,2,6,40,N,"' . $bcode . "\"{$cr}",
            'A' . $x(460) . ',' . $y(103) . ',0,3,1,1,N,"' . str_replace('"', '', trim($icode . ' ' . $bcode)) . "\"{$cr}",
            "P1{$cr}",
        ];

        if ($printType === 'TSC') {
            return [
                $cr,
                "SIZE 50 mm,25 mm{$cr}",
                "GAP 2 mm,0 mm{$cr}",
                "DIRECTION 1{$cr}",
                "CLS{$cr}",
                'DENSITY ' . $density . $cr,
                'TEXT ' . $x(20) . ',' . $y(10) . ',"0",0,1,1,"GW:' . number_format($wt, 3, '.', '') . "\"{$cr}",
                'TEXT ' . $x(20) . ',' . $y(30) . ',"0",0,1,1,"SW:' . number_format($sw, 3, '.', '') . "\"{$cr}",
                'TEXT ' . $x(20) . ',' . $y(50) . ',"0",0,1,1,"NW:' . number_format($nw, 3, '.', '') . "\"{$cr}",
                'BARCODE ' . $x(20) . ',' . $y(75) . ',"128",40,1,0,2,2,"' . $bcode . "\"{$cr}",
                'TEXT ' . $x(20) . ',' . $y(120) . ',"0",0,1,1,"' . str_replace('"', '', trim($icode . ' ' . $bcode)) . "\"{$cr}",
                "PRINT 1{$cr}",
            ];
        }

        return $lines;
    }

    /**
     * Render template elements to monochrome bitmap payload for TSPL BITMAP command.
     *
     * @return array{0:int,1:int,2:string}|null [bytesPerRow, height, hexData]
     */
    private function renderTemplateElementsToBitmap(array $elements, array $replace, int $canvasW = 380, int $canvasH = 180): ?array
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $img = imagecreatetruecolor($canvasW, $canvasH);
        if (!$img) {
            return null;
        }
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $canvasW, $canvasH, $white);

        foreach ($elements as $el) {
            if (!is_array($el)) {
                continue;
            }
            $type = strtolower((string) ($el['type'] ?? 'text'));
            $x = max(0, (int) ($el['x'] ?? 0));
            $y = max(0, (int) ($el['y'] ?? 0));
            $v = (string) ($el['value'] ?? '');
            foreach ($replace as $tag => $tagVal) {
                $v = str_replace($tag, (string) $tagVal, $v);
            }
            $v = trim(str_replace('"', '', $v));
            if ($v === '') {
                continue;
            }

            if ($type === 'barcode' || $type === 'barcode128') {
                $h = max(28, (int) ($el['h'] ?? 52));
                $this->drawCode128B($img, $x, $y, $v, $h, 2, $black, $white);
                continue;
            }
            if ($type === 'image') {
                $w = max(8, min(240, (int) ($el['w'] ?? 96)));
                $h = max(8, min(240, (int) ($el['h'] ?? 48)));
                $srcPath = $this->resolvePngPath($v);
                if ($srcPath !== null) {
                    $raw = @file_get_contents($srcPath);
                    if ($raw !== false && $raw !== '') {
                        $src = @imagecreatefromstring($raw);
                        if ($src) {
                            imagecopyresampled($img, $src, $x, $y, 0, 0, $w, $h, imagesx($src), imagesy($src));
                            imagedestroy($src);
                        }
                    }
                }
                continue;
            }
            if ($type === 'qrcode' || $type === 'qr') {
                $w = max(24, min(240, (int) ($el['w'] ?? 42)));
                $h = max(24, min(240, (int) ($el['h'] ?? 42)));
                $qrSize = max($w, $h);
                $qrImg = $this->loadQrPngImage($v, $qrSize);
                if ($qrImg) {
                    imagecopyresampled($img, $qrImg, $x, $y, 0, 0, $w, $h, imagesx($qrImg), imagesy($qrImg));
                    imagedestroy($qrImg);
                } else {
                    // Fallback marker when QR generation is unavailable.
                    imagerectangle($img, $x, $y, $x + 40, $y + 40, $black);
                    imagestring($img, 1, $x + 4, $y + 16, 'QR', $black);
                }
                continue;
            }

            $rot = (int) ($el['r'] ?? 0);
            if ($rot === 1) {
                imagestringup($img, 2, $x, min($canvasH - 1, $y + (int) strlen($v) * 6), $v, $black);
            } else {
                imagestring($img, 2, $x, $y, $v, $black);
            }
        }

        $bytesPerRow = (int) ceil($canvasW / 8);
        $hex = '';
        for ($yy = 0; $yy < $canvasH; $yy++) {
            for ($bx = 0; $bx < $bytesPerRow; $bx++) {
                $byte = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $xx = ($bx * 8) + $bit;
                    $bitVal = 0;
                    if ($xx < $canvasW) {
                        $rgb = imagecolorat($img, $xx, $yy);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        $gray = (int) round(($r * 0.299) + ($g * 0.587) + ($b * 0.114));
                        $bitVal = $gray < 190 ? 1 : 0;
                    }
                    $byte |= ($bitVal << (7 - $bit));
                }
                $hex .= str_pad(strtoupper(dechex($byte)), 2, '0', STR_PAD_LEFT);
            }
        }
        imagedestroy($img);

        if ($hex === '') {
            return null;
        }
        return [$bytesPerRow, $canvasH, $hex];
    }

    /**
     * Simple CODE128-B renderer to draw black bars on GD canvas.
     */
    private function drawCode128B($img, int $x, int $y, string $value, int $height, int $scale, int $black, int $white): void
    {
        $patterns = [
            '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
            '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
            '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
            '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
            '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
            '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
            '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
            '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
            '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
            '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
            '114131','311141','411131','211412','211214','211232','2331112'
        ];
        $startB = 104;
        $stop = 106;

        $codes = [$startB];
        $sum = $startB;
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $ord = ord($value[$i]);
            if ($ord < 32 || $ord > 126) {
                $ord = 32;
            }
            $code = $ord - 32;
            $codes[] = $code;
            $sum += $code * ($i + 1);
        }
        $check = $sum % 103;
        $codes[] = $check;
        $codes[] = $stop;

        $cx = $x;
        imagefilledrectangle($img, $x, $y, $x + 500, $y + $height + 12, $white);
        foreach ($codes as $code) {
            $pat = $patterns[$code] ?? '';
            $bar = true;
            for ($i = 0; $i < strlen($pat); $i++) {
                $w = ((int) $pat[$i]) * $scale;
                if ($bar) {
                    imagefilledrectangle($img, $cx, $y, $cx + $w - 1, $y + $height, $black);
                }
                $cx += $w;
                $bar = !$bar;
            }
        }
    }

    /**
     * Load QR PNG from web API and return GD image resource.
     */
    private function loadQrPngImage(string $text, int $size)
    {
        if ($text === '' || !function_exists('imagecreatefromstring')) {
            return null;
        }
        static $cache = [];
        $size = max(24, min(240, $size));
        $key = $size . '|' . $text;
        if (array_key_exists($key, $cache)) {
            $raw = $cache[$key];
            return is_string($raw) && $raw !== '' ? @imagecreatefromstring($raw) : null;
        }

        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
            . '&ecc=L&margin=0&data=' . rawurlencode($text);
        $raw = '';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                $resp = curl_exec($ch);
                if (is_string($resp) && $resp !== '') {
                    $raw = $resp;
                }
                curl_close($ch);
            }
        }
        if ($raw === '' && ini_get('allow_url_fopen')) {
            $ctx = stream_context_create([
                'http' => ['timeout' => 8],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $resp = @file_get_contents($url, false, $ctx);
            if (is_string($resp) && $resp !== '') {
                $raw = $resp;
            }
        }

        $cache[$key] = $raw;
        return $raw !== '' ? @imagecreatefromstring($raw) : null;
    }

    private function decodeModelPngMap(array $settings): array
    {
        $raw = (string) ($settings['Software']['BCModelPngMap'] ?? '');
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return [];
            }
            $map = [];
            foreach ($decoded as $model => $path) {
                $modelKey = strtolower(trim((string) $model));
                $imgPath = trim((string) $path);
                if ($modelKey !== '' && $imgPath !== '') {
                    $map[$modelKey] = $imgPath;
                }
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function resolvePngPath(string $source): ?string
    {
        $candidate = trim($source);
        if ($candidate === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]:\\\\|^\//', $candidate) && is_file($candidate)) {
            return $candidate;
        }

        $candidate = ltrim(str_replace(['\\', '//'], '/', $candidate), '/');
        $paths = [
            public_path($candidate),
            storage_path('app/public/' . $candidate),
            base_path($candidate),
        ];
        foreach ($paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    private function buildTsplBitmapLinesFromPng(int $x, int $y, string $source, int $targetW, int $targetH, string $cr): array
    {
        if (!function_exists('imagecreatefromstring')) {
            return [];
        }
        $path = $this->resolvePngPath($source);
        if ($path === null) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $src = @imagecreatefromstring($raw);
        if (!$src) {
            return [];
        }

        $w = max(8, min(240, $targetW > 0 ? $targetW : imagesx($src)));
        $h = max(8, min(240, $targetH > 0 ? $targetH : imagesy($src)));
        $img = imagecreatetruecolor($w, $h);
        if (!$img) {
            imagedestroy($src);
            return [];
        }
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, $w, $h, $white);
        imagecopyresampled($img, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        imagedestroy($src);

        $bytesPerRow = (int) ceil($w / 8);
        $hex = '';
        for ($yy = 0; $yy < $h; $yy++) {
            for ($bx = 0; $bx < $bytesPerRow; $bx++) {
                $byte = 0;
                for ($bit = 0; $bit < 8; $bit++) {
                    $xx = ($bx * 8) + $bit;
                    $bitVal = 0;
                    if ($xx < $w) {
                        $rgb = imagecolorat($img, $xx, $yy);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        $gray = (int) round(($r * 0.299) + ($g * 0.587) + ($b * 0.114));
                        // TSPL bitmap: 1 means black dot.
                        $bitVal = $gray < 190 ? 1 : 0;
                    }
                    $byte |= ($bitVal << (7 - $bit));
                }
                $hex .= str_pad(strtoupper(dechex($byte)), 2, '0', STR_PAD_LEFT);
            }
        }
        imagedestroy($img);

        if ($hex === '') {
            return [];
        }

        return [
            'BITMAP ' . $x . ',' . $y . ',' . $bytesPerRow . ',' . $h . ',0,' . $hex . $cr,
        ];
    }

    private function triggerPrintCommand(array $settings, string $prnFile): bool
    {
        $mode = strtolower(trim($this->setting('BCPrintMode', 'windows_image', $settings)));
        if ($mode === 'none') {
            @unlink($prnFile);
            return false;
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            @unlink($prnFile);
            return false;
        }

        $printer = trim($this->setting('BCPrinterName', '', $settings));
        $printerShare = trim($this->setting('BCPrinterShareName', '', $settings));
        $runRawSharePrint = function () use ($printer, $printerShare, $prnFile): bool {
            $debugLog = function (string $msg) use ($printer, $printerShare, $prnFile): void {
                $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg
                    . '; printer=' . $printer
                    . '; share=' . $printerShare
                    . '; prn=' . $prnFile
                    . PHP_EOL;
                @file_put_contents(storage_path('logs/barcode-print-debug.log'), $line, FILE_APPEND);
            };

            if ($printerShare === '' || !is_file($prnFile)) {
                $debugLog('raw share skip: missing share or prn file');
                return false;
            }

            $computerName = trim((string) getenv('COMPUTERNAME'));
            $targets = [];
            if ($computerName !== '') {
                $targets[] = '\\\\' . $computerName . '\\' . $printerShare;
            }
            $targets[] = '\\\\localhost\\' . $printerShare;

            foreach (array_unique($targets) as $target) {
                $cmd = 'cmd /c copy /b ' . escapeshellarg($prnFile) . ' ' . escapeshellarg($target);
                @exec($cmd . ' 2>&1', $out, $rc);
                if ($rc === 0) {
                    $debugLog('raw share print success; target=' . $target);
                    return true;
                }
                $debugLog('raw share print failed; target=' . $target . '; rc=' . $rc . '; out=' . implode(' | ', (array) $out));
            }

            return false;
        };
        $runWindowsImagePrint = function () use ($printer, $prnFile): bool {
            $debugLog = function (string $msg) use ($printer, $prnFile): void {
                $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg
                    . '; printer=' . $printer
                    . '; prn=' . $prnFile
                    . '; gd=' . (function_exists('imagecreatetruecolor') ? 'yes' : 'no')
                    . PHP_EOL;
                @file_put_contents(storage_path('logs/barcode-print-debug.log'), $line, FILE_APPEND);
            };

            if (!function_exists('imagecreatetruecolor')) {
                $debugLog('abort: gd extension not available in web runtime');
                return false;
            }

            $imgFile = $this->extractBitmapImageFromPrn($prnFile);
            if ($imgFile === null || !is_file($imgFile)) {
                $raw = @file_get_contents($prnFile);
                $sample = is_string($raw) ? substr($raw, 0, 240) : '';
                $debugLog('abort: bitmap extraction failed; prn_sample=' . str_replace(["\r", "\n"], ['\\r', '\\n'], $sample));
                return false;
            }
            $psImg = str_replace("'", "''", $imgFile);
            $psScript = <<<'PS1'
param(
  [Parameter(Mandatory=$true)][string]$ImagePath,
  [string]$PrinterName = ""
)
$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.Drawing
$targetPrinter = $PrinterName
$installed = @([System.Drawing.Printing.PrinterSettings]::InstalledPrinters)
if ($targetPrinter -ne "" -and ($installed -notcontains $targetPrinter)) { $targetPrinter = "" }
if ($targetPrinter -eq "") {
  $targetPrinter = (Get-CimInstance Win32_Printer | Where-Object { $_.Default } | Select-Object -First 1 -ExpandProperty Name)
}
$img = [System.Drawing.Image]::FromFile($ImagePath)
$pd = New-Object System.Drawing.Printing.PrintDocument
if ($targetPrinter -ne "") { $pd.PrinterSettings.PrinterName = $targetPrinter }
$ok = $false
try {
  $pd.PrintController = New-Object System.Drawing.Printing.StandardPrintController
  $pd.DefaultPageSettings.Margins = New-Object System.Drawing.Printing.Margins(0,0,0,0)
  $pd.DefaultPageSettings.PaperSize = New-Object System.Drawing.Printing.PaperSize("Sticker",297,98)
  $pd.add_PrintPage({ param($sender,$e) $e.Graphics.DrawImage($img,0,0,$e.PageBounds.Width,$e.PageBounds.Height); $e.HasMorePages=$false; })
  $pd.Print()
  $ok = $true
} catch {
  $ok = $false
}
if (-not $ok -and $targetPrinter -ne "") {
  try {
    Start-Process -FilePath "mspaint.exe" -ArgumentList @("/pt",$ImagePath,$targetPrinter) -WindowStyle Hidden -Wait
    $ok = $true
  } catch {
    $ok = $false
  }
}
$img.Dispose()
if (-not $ok) { exit 1 }
PS1;
            $psFile = storage_path('app' . DIRECTORY_SEPARATOR . 'print-cache' . DIRECTORY_SEPARATOR . 'barcode-print-' . uniqid('', true) . '.ps1');
            @file_put_contents($psFile, $psScript);
            $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File '
                . escapeshellarg($psFile)
                . ' -ImagePath ' . escapeshellarg($imgFile)
                . ' -PrinterName ' . escapeshellarg($printer);
            @exec($cmd . ' 2>&1', $out, $rc);
            @unlink($psFile);
            if ($rc !== 0) {
                $debugLog('powershell print failed; rc=' . $rc . '; out=' . implode(' | ', (array) $out));
            } else {
                $debugLog('print success');
            }
            @unlink($imgFile);
            return $rc === 0;
        };

        $ok = false;
        if (in_array($mode, ['auto', 'windows_image', 'raw', 'share'], true)) {
            $ok = $runRawSharePrint();
        }
        if (!$ok && in_array($mode, ['auto', 'windows_image'], true)) {
            $ok = $runWindowsImagePrint();
        }
        @unlink($prnFile);
        return $ok;
    }

    /**
     * Extract first TSPL BITMAP payload from PRN and convert to PNG file.
     */
    private function extractBitmapImageFromPrn(string $prnFile): ?string
    {
        if (!is_file($prnFile) || !function_exists('imagecreatetruecolor')) {
            return null;
        }
        $raw = @file_get_contents($prnFile);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        if (!preg_match('/BITMAP\s+\d+,\d+,(\d+),(\d+),0,([0-9A-Fa-f]+)/', $raw, $m)) {
            return null;
        }
        $bytesPerRow = (int) ($m[1] ?? 0);
        $height = (int) ($m[2] ?? 0);
        $hex = strtoupper((string) ($m[3] ?? ''));
        if ($bytesPerRow <= 0 || $height <= 0 || $hex === '') {
            return null;
        }
        $width = $bytesPerRow * 8;
        $expectedHexLen = $bytesPerRow * $height * 2;
        if (strlen($hex) < $expectedHexLen) {
            return null;
        }
        $img = imagecreatetruecolor($width, $height);
        if (!$img) {
            return null;
        }
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $width, $height, $white);

        $idx = 0;
        for ($y = 0; $y < $height; $y++) {
            for ($bx = 0; $bx < $bytesPerRow; $bx++) {
                $byteHex = substr($hex, $idx, 2);
                $idx += 2;
                $byte = hexdec($byteHex);
                for ($bit = 0; $bit < 8; $bit++) {
                    $x = ($bx * 8) + $bit;
                    if ($x >= $width) {
                        continue;
                    }
                    $isBlack = (($byte >> (7 - $bit)) & 1) === 1;
                    if ($isBlack) {
                        imagesetpixel($img, $x, $y, $black);
                    }
                }
            }
        }

        $out = storage_path('app' . DIRECTORY_SEPARATOR . 'bprint-bitmap-' . uniqid('', true) . '.png');
        if (!@imagepng($img, $out)) {
            imagedestroy($img);
            return null;
        }
        imagedestroy($img);
        return $out;
    }

    /**
     * Get a Software setting by key.
     */
    private function setting(string $key, string $default = '', ?array $settings = null): string
    {
        if ($settings === null) {
            $settings = $this->loadSettings();
        }
        return (string) (($settings['Software'][$key] ?? $default) ?: $default);
    }

    private function saveSettingValue(string $key, string $value): void
    {
        if ($key === '') {
            return;
        }
        $settings = $this->loadSettings();
        if (!isset($settings['Software']) || !is_array($settings['Software'])) {
            $settings['Software'] = [];
        }
        $settings['Software'][$key] = $value;
        $this->writeIniSettings($settings);
    }

    private function settingsIniPath(): string
    {
        return storage_path('app/software-settings.ini');
    }

    private function parseIniSettings(string $raw): array
    {
        $out = [];
        $sec = '';
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === ';' || $line[0] === '#' || str_starts_with($line, '//')) {
                continue;
            }
            if (preg_match('/^\[(.+)\]$/', $line, $m)) {
                $sec = trim((string) $m[1]);
                if ($sec !== '' && !isset($out[$sec])) {
                    $out[$sec] = [];
                }
                continue;
            }
            if ($sec === '' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim((string) $k);
            if ($k === '') {
                continue;
            }
            $out[$sec][$k] = trim((string) $v);
        }
        if (!isset($out['Software'])) $out['Software'] = [];
        if (!isset($out['Application'])) $out['Application'] = [];
        if (!isset($out['Company'])) $out['Company'] = [];
        if (!isset($out['Printer'])) $out['Printer'] = [];
        if (!isset($out['Rates'])) $out['Rates'] = [];
        return $out;
    }

    private function writeIniSettings(array $settings): void
    {
        $path = $this->settingsIniPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $buf = '';
        foreach ($settings as $section => $pairs) {
            if (!is_array($pairs) || trim((string) $section) === '') {
                continue;
            }
            $buf .= '[' . trim((string) $section) . "]\n";
            foreach ($pairs as $k => $v) {
                if (!is_string($k) || trim($k) === '') {
                    continue;
                }
                $buf .= trim($k) . '=' . (string) ($v ?? '') . "\n";
            }
            $buf .= "\n";
        }
        @file_put_contents($path, $buf);
    }

    private function getTodayRate(): float
    {
        if (!$this->hasTable('generald')) {
            return 0.0;
        }
        return (float) (DB::table('generald')->where('code', 'THRATE')->value('cvalue') ?? 0);
    }

    private function computeNextDocNo(): string
    {
        $current = 0;
        if ($this->hasTable('generali')) {
            $current = (int) (DB::table('generali')->where('code', 'BCDOCNO')->value('cvalue') ?? 0);
        }
        return 'BD' . str_pad((string) ($current + 1), 6, '0', STR_PAD_LEFT);
    }

    private function computeNextBarcode(): int
    {
        $settings = $this->loadSettings();
        $bcMaxNo = strtoupper($this->setting('BCMaxNo', 'Y', $settings));
        $next = 0;
        if ($bcMaxNo === 'Y') {
            if ($this->hasTable('barcode')) {
                $next = (int) (DB::table('barcode')->max('bcode') ?? 0);
            }
        } else {
            if ($this->hasTable('generali')) {
                $next = (int) (DB::table('generali')->where('code', 'BCNO')->value('cvalue') ?? 0);
            }
        }
        return $next > 0 ? $next + 1 : 100001;
    }

    private function legacyLookupItem(Request $request): JsonResponse
    {
        $icode = strtoupper(trim((string) $request->input('code', '')));
        $request->merge(['icode' => $icode]);
        $resp = $this->loadItem($request);
        $payload = $resp->getData(true);
        if (!($payload['success'] ?? false)) {
            return response()->json(['success' => false, 'error' => $payload['message'] ?? 'Item code not found']);
        }
        $item = (array) ($payload['data'] ?? []);
        return response()->json([
            'success' => true,
            'item' => [
                'name' => (string) ($item['name'] ?? ''),
                'wastage' => (float) ($item['wastage'] ?? 0),
                'mcrate' => (float) ($item['mcharge'] ?? 0),
                'vap' => (float) ($item['vaperc'] ?? 0),
                'stkinnos' => (string) ($item['stkinnos'] ?? 'N'),
                'nodisc' => (string) ($item['nodisc'] ?? ''),
                'stickerwgt' => (float) ($item['stickerwgt'] ?? 0),
                'defquality' => (string) ($item['defquality'] ?? ''),
                'subgrpcode' => (string) ($item['subgrpcode'] ?? ''),
            ],
        ]);
    }

    private function legacyLookupBarcode(Request $request): JsonResponse
    {
        $bcode = (int) $request->input('bcode', 0);
        if ($bcode <= 0 || !$this->hasTable('barcode')) {
            return response()->json(['success' => false, 'error' => 'Barcode not found']);
        }

        $row = DB::table('barcode as b')
            ->leftJoin('barcodedmd as bd', 'bd.bcode', '=', 'b.bcode')
            ->leftJoin('items as i', 'i.code', '=', 'b.icode')
            ->leftJoin('barcodedoc as doc', 'doc.docno', '=', 'b.docno')
            ->where('b.bcode', $bcode)
            ->select('b.*', 'bd.dmdwgt', 'bd.dmdunit', 'bd.dmdamt', 'bd.dmdnos', 'i.name as item_name', 'doc.smith', 'doc.totwgt', 'doc.totnos')
            ->first();

        if (!$row) {
            return response()->json(['success' => false, 'error' => 'Barcode not found']);
        }

        $result = (array) $row;
        $dmdDetails = [];
        if ($this->hasTable('barcode_dmddet')) {
            $dmdDetails = DB::table('barcode_dmddet')->where('bcode', $bcode)->orderBy('sno')->get()->map(fn ($r) => (array) $r)->toArray();
        }
        if (!empty($result['docno'])) {
            $acc = DB::table('barcode')
                ->where('docno', $result['docno'])
                ->where('bcode', '<>', $bcode)
                ->selectRaw('COALESCE(SUM(weight),0) as acc_wgt, COALESCE(SUM(qty),0) as acc_nos')
                ->first();
            $result['acc_wgt'] = (float) ($acc->acc_wgt ?? 0);
            $result['acc_nos'] = (int) ($acc->acc_nos ?? 0);
        }
        $result['dmd_details'] = $dmdDetails;
        return response()->json(['success' => true, 'data' => $result]);
    }

    private function legacyLookupDoc(Request $request): JsonResponse
    {
        $docno = trim((string) $request->input('docno', ''));
        if ($docno === '' || !$this->hasTable('barcodedoc')) {
            return response()->json(['success' => false, 'error' => 'Document not found']);
        }
        $doc = DB::table('barcodedoc')->where('docno', $docno)->first();
        if (!$doc) {
            return response()->json(['success' => false, 'error' => 'Document not found']);
        }
        $acc = DB::table('barcode')->where('docno', $docno)
            ->selectRaw('COALESCE(SUM(weight),0) as acc_wgt, COALESCE(SUM(qty),0) as acc_nos')
            ->first();
        $result = (array) $doc;
        $result['acc_wgt'] = (float) ($acc->acc_wgt ?? 0);
        $result['acc_nos'] = (int) ($acc->acc_nos ?? 0);
        return response()->json(['success' => true, 'doc' => $result]);
    }

    private function legacyQtypeTouch(Request $request): JsonResponse
    {
        $code = trim((string) $request->input('code', ''));
        $touch = 0.0;
        if ($code !== '' && $this->hasTable('itemsqtype')) {
            $touch = (float) (DB::table('itemsqtype')->where('code', $code)->value('touch') ?? 0);
        }
        return response()->json(['success' => true, 'touch' => $touch]);
    }

    private function legacyStoneRate(Request $request): JsonResponse
    {
        $code = trim((string) $request->input('code', ''));
        $rate = 0.0;
        if ($code !== '' && $this->hasTable('items')) {
            $rate = (float) (DB::table('items')->where('code', $code)->value('rate') ?? 0);
        }
        return response()->json(['success' => true, 'rate' => $rate]);
    }

    private function legacySaveSetting(Request $request): JsonResponse
    {
        $key = trim((string) $request->input('key', ''));
        $value = (string) $request->input('value', '');
        if ($key === '') {
            return response()->json(['success' => false, 'error' => 'Invalid key']);
        }
        $this->saveSettingValue($key, $value);
        return response()->json(['success' => true]);
    }

    private function legacyCalcStkTouch(Request $request): JsonResponse
    {
        $weight = (float) $request->input('weight', 0);
        $stweight = (float) $request->input('stweight', 0);
        $grate = (float) $request->input('grate', 0);
        $cost = (float) $request->input('cost', 0);
        $costmc = (float) $request->input('costmc', 0);
        $coststone = (float) $request->input('coststone', 0);
        $transtouch = (float) $request->input('transtouch', 0);

        $net = $weight - $stweight;
        $mc = $costmc;
        if ($mc == 0.0 && $cost > 0 && $net > 0) {
            $mc = $cost / $net;
        }
        $stktouch = $transtouch;
        $adjGrate = $grate > 0 ? round(($grate * 100) / 99.5, 0) : 0;
        if ($adjGrate > 0 && $net > 0) {
            $stktouch = $transtouch + ($mc + $coststone / $net) / $adjGrate * 100;
        }
        return response()->json(['success' => true, 'stktouch' => round($stktouch, 2)]);
    }

    private function legacyUploadPhoto(Request $request): JsonResponse
    {
        $bcode = trim((string) $request->input('bcode', ''));
        if ($bcode === '' || !$request->hasFile('photo')) {
            return response()->json(['success' => false, 'error' => 'No file uploaded'], 422);
        }
        $file = $request->file('photo');
        if (!$file || !$file->isValid()) {
            return response()->json(['success' => false, 'error' => 'Upload failed'], 422);
        }
        $dir = public_path('uploads/photos');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file->move($dir, $bcode . '.jpg');
        return response()->json(['success' => true, 'path' => '/uploads/photos/' . $bcode . '.jpg']);
    }

    private function legacySearchItems(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (!$this->hasTable('items')) {
            return response()->json(['success' => true, 'items' => []]);
        }
        $rows = DB::table('items')
            ->select('code', 'name')
            ->where('code', 'like', '%' . $q . '%')
            ->orWhere('name', 'like', '%' . $q . '%')
            ->limit(20)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
        return response()->json(['success' => true, 'items' => $rows]);
    }

    private function legacySearchSmiths(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $source = $this->hasTable('smithsupplier') ? 'smithsupplier' : ($this->hasTable('clients') ? 'clients' : null);
        if ($source === null) {
            return response()->json(['success' => true, 'items' => []]);
        }
        $query = DB::table($source)->select('code', 'name');
        if ($source === 'clients' && Schema::hasColumn('clients', 'ctype')) {
            $query->where('ctype', 'G');
        }
        $rows = $query
            ->where(function ($w) use ($q) {
                $w->where('code', 'like', '%' . $q . '%')->orWhere('name', 'like', '%' . $q . '%');
            })
            ->limit(20)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
        return response()->json(['success' => true, 'items' => $rows]);
    }

    private function legacySearchQtypes(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (!$this->hasTable('itemsqtype')) {
            return response()->json(['success' => true, 'items' => []]);
        }
        $rows = DB::table('itemsqtype')
            ->select('code')
            ->where('code', 'like', '%' . $q . '%')
            ->limit(20)
            ->get()
            ->map(fn ($r) => ['code' => (string) $r->code, 'name' => (string) $r->code])
            ->toArray();
        return response()->json(['success' => true, 'items' => $rows]);
    }

    private function legacySearchSubgrps(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (!$this->hasTable('itemsubgrp')) {
            return response()->json(['success' => true, 'items' => []]);
        }
        $rows = DB::table('itemsubgrp')
            ->select('code', DB::raw((Schema::hasColumn('itemsubgrp', 'name') ? 'name' : 'code') . ' as name'))
            ->where('code', 'like', '%' . $q . '%')
            ->limit(20)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
        return response()->json(['success' => true, 'items' => $rows]);
    }

    private function legacySearchModels(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (!$this->hasTable('models')) {
            return response()->json(['success' => true, 'items' => []]);
        }
        $query = DB::table('models')->select('name');
        if (Schema::hasColumn('models', 'mtype')) {
            $query->where('mtype', 'M');
        }
        $rows = $query->where('name', 'like', '%' . $q . '%')->limit(20)->get()->map(fn ($r) => (array) $r)->toArray();
        return response()->json(['success' => true, 'items' => $rows]);
    }

    private function legacySearchBarcodes(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (!$this->hasTable('barcode')) {
            return response()->json(['success' => true, 'items' => []]);
        }
        $query = DB::table('barcode as b')
            ->leftJoin('items as i', 'i.code', '=', 'b.icode')
            ->select('b.bcode', 'b.icode', 'b.weight', 'b.stk', DB::raw('COALESCE(i.name, "") as name'));
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->whereRaw('CAST(b.bcode AS CHAR) like ?', ['%' . $q . '%'])
                    ->orWhere('b.icode', 'like', '%' . $q . '%');
            });
        }
        $rows = $query->orderByDesc('b.bcode')->limit(50)->get()->map(fn ($r) => (array) $r)->toArray();
        return response()->json(['success' => true, 'items' => $rows]);
    }

    private function legacySave(Request $request): JsonResponse
    {
        $mode = strtoupper(trim((string) $request->input('mode', 'A')));
        $request->merge([
            'mode' => $mode === 'E' ? 'edit' : 'add',
            'purchaseamt' => $request->input('purchamt', $request->input('purchaseamt', 0)),
            'dmdDetails' => json_decode((string) $request->input('dmd_details', '[]'), true) ?: [],
        ]);
        return $this->save($request);
    }

    /**
     * Build the barcode table row from form data.
     * Filters columns through $this->columnList('barcode') so only existing columns are included.
     */
    private function buildBarcodeRow(array $data, Request $request): array
    {
        $settings = $this->loadSettings();
        $tdate    = trim((string) ($data['tdate'] ?? date('Y-m-d')));
        $controlRaw = (string) ($request->session()->get('semi')
            ?? $request->session()->get('control')
            ?? '');
        $control = is_numeric($controlRaw) ? (int) $controlRaw : 0;

        $row = [
            'bcode'       => (int) ($data['bcode'] ?? 0),
            'icode'       => trim((string) ($data['icode'] ?? '')),
            'qty'         => (float) ($data['qty'] ?? 0),
            'weight'      => (float) ($data['weight'] ?? 0),
            'stweight'    => (float) ($data['stweight'] ?? 0),
            'stprice'     => (float) ($data['stprice'] ?? 0),
            'wastage'     => (float) ($data['wastage'] ?? 0),
            'mc'          => (float) ($data['mc'] ?? 0),
            'mcrate'      => (float) ($data['mcrate'] ?? 0),
            'rate'        => (float) ($data['rate'] ?? 0),
            'cost'        => (float) ($data['cost'] ?? 0),
            'vap'         => (float) ($data['vap'] ?? 0),
            'minvap'      => (float) ($data['minvap'] ?? 0),
            'docno'       => trim((string) ($data['docno'] ?? '')),
            'smithcode'   => trim((string) ($data['smithcode'] ?? '')),
            'smcode'      => trim((string) ($data['smcode'] ?? '')),
            'qtype'       => trim((string) ($data['qtype'] ?? '')),
            'qunit'       => trim((string) ($data['qunit'] ?? '')),
            'tamt'        => (float) ($data['tamt'] ?? 0),
            'costamt'     => (float) ($data['purchaseamt'] ?? ($data['costamt'] ?? 0)),
            'serialno'    => trim((string) ($data['serialno'] ?? '')),
            'huid'        => trim((string) ($data['huid'] ?? '')),
            'sizemodel'   => trim((string) ($data['size'] ?? ($data['sizemodel'] ?? ''))),
            'model'       => trim((string) ($data['model'] ?? '')),
            // PB compatibility: sold='Y' means stock='N'
            'stk'         => strtoupper(trim((string) ($data['sold'] ?? ''))) === 'Y' ? 'N' : 'Y',
            'stkinnos'    => strtoupper(trim((string) ($data['stkinnos'] ?? 'N'))) === 'Y' ? 'Y' : 'N',
            'transtouch'  => (float) ($data['transtouch'] ?? 0),
            'stktouch'    => (float) ($data['stktouch'] ?? 0),
            'grate'       => (float) ($data['grate'] ?? 0),
            'costmc'      => (float) ($data['costmc'] ?? 0),
            'coststone'   => (float) ($data['coststone'] ?? 0),
            'weight2'     => (float) ($data['stickerwgt'] ?? ($data['weight2'] ?? 0)),
            // PB compatibility: nodisc='Y' stores as 'N'
            'nodisc'      => strtoupper(trim((string) ($data['nodisc'] ?? ''))) === 'Y' ? 'N' : '',
            'subgrp'      => trim((string) ($data['subgrp'] ?? '')),
            'costperc'    => (float) ($data['costperc'] ?? 0),
            'part'        => trim((string) ($data['part'] ?? '')),
            'tdate'       => $tdate,
            'control'     => $control,
            'smithmcrate' => (float) ($data['smithmcrate'] ?? 0),
            'counter'     => trim((string) ($data['counter'] ?? '')),
            'status'      => 'N',
        ];

        // Filter to only columns that actually exist in the barcode table
        $columns = $this->getColumns('barcode');
        return $this->filterToColumns($row, $columns);
    }

    /**
     * Delete existing diamond detail rows and re-insert.
     */
    private function saveDiamondDetails(int $bcode, array $rows): void
    {
        if (!$this->hasTable('barcode_dmddet')) {
            return;
        }

        DB::table('barcode_dmddet')->where('bcode', $bcode)->delete();

        if (empty($rows)) {
            return;
        }

        $columns = $this->getColumns('barcode_dmddet');
        $sno     = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sno++;

            $insert = [
                // Some legacy schemas require slno as NOT NULL (no default).
                'slno'     => (float) ($row['slno'] ?? $bcode),
                'bcode'    => $bcode,
                'sno'      => $sno,
                // Legacy field names from PB-style API
                'sttype'   => trim((string) ($row['sttype'] ?? $row['dmdtype'] ?? '')),
                'stsize'   => trim((string) ($row['stsize'] ?? '')),
                'stcut'    => trim((string) ($row['stcut'] ?? '')),
                'stsettype'=> trim((string) ($row['stsettype'] ?? '')),
                'pcs'      => (int) ($row['pcs'] ?? $row['dmdqty'] ?? 0),
                'carats'   => (float) ($row['carats'] ?? 0),
                'rate'     => (float) ($row['rate'] ?? $row['dmdrate'] ?? 0),
                'amount'   => (float) ($row['samount'] ?? $row['dmdamt'] ?? 0),
                'mperc'    => (float) ($row['mperc'] ?? 0),
                'stcolor'  => trim((string) ($row['stcolor'] ?? '')),
                'stcode'   => trim((string) ($row['stcode'] ?? '')),
                'wgt'      => (float) ($row['wgt'] ?? $row['dmdwgt'] ?? 0),
                'prate'    => (float) ($row['prate'] ?? 0),
                'pamt'     => (float) ($row['amount'] ?? 0),
            ];

            $insert = $this->filterToColumns($insert, $columns);
            if (!empty($insert)) {
                DB::table('barcode_dmddet')->insert($insert);
            }
        }
    }

    /**
     * Aggregate diamond detail totals and upsert into barcodedmd.
     */
    private function saveDiamondSummary(int $bcode, array $rows): void
    {
        if (!$this->hasTable('barcodedmd')) {
            return;
        }

        $totalQty  = 0;
        $totalWgt  = 0.0;
        $totalAmt  = 0.0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $totalQty += (int) ($row['dmdqty'] ?? $row['pcs'] ?? 0);
            $totalWgt += (float) ($row['dmdwgt'] ?? $row['wgt'] ?? 0);
            $totalAmt += (float) ($row['dmdamt'] ?? $row['samount'] ?? 0);
        }

        $summaryRow = [
            'bcode'  => $bcode,
            'dmdqty' => $totalQty,
            'dmdwgt' => $totalWgt,
            'dmdamt' => $totalAmt,
        ];

        $columns    = $this->getColumns('barcodedmd');
        $summaryRow = $this->filterToColumns($summaryRow, $columns);

        $exists = DB::table('barcodedmd')->where('bcode', $bcode)->exists();
        if ($exists) {
            $update = $summaryRow;
            unset($update['bcode']);
            if (!empty($update)) {
                DB::table('barcodedmd')->where('bcode', $bcode)->update($update);
            }
        } else {
            if (!empty($summaryRow)) {
                DB::table('barcodedmd')->insert($summaryRow);
            }
        }
    }

    /**
     * Upsert a document row in barcodedoc.
     * On insert, also increment the BCDOCNO counter in generali.
     */
    private function saveDocument(string $docno, string $tdate, string $smith, float $totwgt, int $totnos): void
    {
        if (!$this->hasTable('barcodedoc')) {
            return;
        }

        $docRow = [
            'docno'  => $docno,
            'tdate'  => $tdate,
            'smith'  => $smith,
            'totwgt' => $totwgt,
            'totnos' => $totnos,
        ];

        $columns = $this->getColumns('barcodedoc');
        $docRow  = $this->filterToColumns($docRow, $columns);

        $exists = DB::table('barcodedoc')->where('docno', $docno)->exists();
        if ($exists) {
            $update = $docRow;
            unset($update['docno']);
            if (!empty($update)) {
                DB::table('barcodedoc')->where('docno', $docno)->update($update);
            }
        } else {
            if (!empty($docRow)) {
                DB::table('barcodedoc')->insert($docRow);
            }

            // Increment BCDOCNO counter
            if ($this->hasTable('generali')) {
                $current = (int) (DB::table('generali')
                    ->where('code', 'BCDOCNO')
                    ->value('cvalue') ?? 0);
                DB::table('generali')
                    ->updateOrInsert(
                        ['code' => 'BCDOCNO'],
                        ['cvalue' => $current + 1]
                    );
            }
        }
    }

    /**
     * Safely query a lookup table that may not exist.
     */
    private function safeLookup(string $table, array $columns = ['code', 'name']): array
    {
        if (!$this->hasTable($table)) {
            return [];
        }

        $tableCols = array_map('strtolower', $this->columnList($table));
        if (!in_array('code', $tableCols, true)) {
            return [];
        }

        $nameCol = in_array('name', $tableCols, true) ? 'name' : 'code';
        $orderCol = $nameCol;

        return DB::table($table)
            ->select(['code', DB::raw($nameCol . ' as name')])
            ->orderBy($orderCol)
            ->limit(1000)
            ->get()
            ->map(fn ($r) => ['code' => (string) $r->code, 'name' => (string) $r->name])
            ->values()
            ->toArray();
    }

    /**
     * Load smith/goldsmith records from clients where ctype='G'.
     */
    private function smithLookup(): array
    {
        if (!$this->hasTable('clients')) {
            return [];
        }

        $cols = array_map('strtolower', $this->columnList('clients'));
        if (!in_array('code', $cols, true) || !in_array('name', $cols, true)) {
            return [];
        }

        $q = DB::table('clients')->select('code', 'name');
        if (in_array('ctype', $cols, true)) {
            $q->where('ctype', 'G');
        }

        return $q->orderBy('name')
            ->limit(1000)
            ->get()
            ->map(fn ($r) => ['code' => (string) $r->code, 'name' => (string) $r->name])
            ->values()
            ->toArray();
    }

    /**
     * Load model names from models table where mtype='M'.
     */
    private function modelLookup(): array
    {
        if (!$this->hasTable('models')) {
            return [];
        }

        $cols = array_map('strtolower', $this->columnList('models'));
        if (!in_array('name', $cols, true)) {
            return [];
        }

        $q = DB::table('models')->select('name');
        if (in_array('mtype', $cols, true)) {
            $q->where('mtype', 'M');
        }

        return $q->orderBy('name')
            ->limit(1000)
            ->get()
            ->map(fn ($r) => ['name' => (string) $r->name])
            ->values()
            ->toArray();
    }

    /**
     * Get column listing for a table (cached per request).
     */
    private function getColumns(string $table): array
    {
        static $cache = [];
        if (!isset($cache[$table])) {
            try {
                $cache[$table] = array_map('strtolower', $this->columnList($table));
            } catch (\Throwable $e) {
                $cache[$table] = [];
            }
        }
        return $cache[$table];
    }

    /**
     * Filter a data array to only keys that exist as columns in the table.
     */
    private function filterToColumns(array $data, array $columns): array
    {
        $colFlipped = array_flip($columns);
        $out = [];
        foreach ($data as $k => $v) {
            if (isset($colFlipped[strtolower($k)])) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     * Legacy session authorization check.
     * Matches the exact pattern from BarcodeSettingsController.
     */
    private function isAuthorized(Request $request): bool
    {
        if ((string) ($request->session()->get('user_code') ?? '') !== '') {
            return true;
        }

        $legacySessionId = preg_replace('/[^a-zA-Z0-9,-]/', '', (string) ($_COOKIE['PHPSESSID'] ?? ''));
        if ($legacySessionId === '') {
            return false;
        }

        $savePath = (string) ini_get('session.save_path');
        if ($savePath === '') {
            return false;
        }
        if (str_contains($savePath, ';')) {
            $parts = explode(';', $savePath);
            $savePath = (string) end($parts);
        }
        $savePath = trim($savePath);
        if ($savePath === '') {
            return false;
        }

        $sessionFile = rtrim($savePath, "/\\") . DIRECTORY_SEPARATOR . 'sess_' . $legacySessionId;
        if (!is_file($sessionFile) || !is_readable($sessionFile)) {
            return false;
        }

        $raw = (string) @file_get_contents($sessionFile);
        return $raw !== '' && str_contains($raw, 'user_code|');
    }
}
