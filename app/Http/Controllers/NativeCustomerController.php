<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use App\Support\SecondaryDatabaseSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class NativeCustomerController extends Controller
{
    use LogsDelpartAudit;

    /** In-process cache for Schema::hasTable / getColumnListing results */
    private static array $schemaCache = [];

    public function index(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $type = $this->normalizeType((string) $request->query('type', 'C'));
        $showList = (string) $request->query('list', '') === '1';
        if (!$showList) {
            return redirect('/customer/add?type=' . urlencode($type));
        }

        $search = trim((string) $request->query('search', ''));
        $noRemoved = (string) $request->query('noremoved', '1') !== '0';

        $customers = $this->queryCustomersByType($type, $search, $noRemoved);

        return view('native.customer.list', [
            'type' => $type,
            'typeInfo' => $this->getTypeInfo($type),
            'customers' => $customers,
            'search' => $search,
            'noRemoved' => $noRemoved,
            'message' => (string) session('flash_message', ''),
            'messageType' => (string) session('flash_type', ''),
        ]);
    }

    public function add(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $type = $this->normalizeType((string) $request->query('type', 'C'));
        $prefillName = trim((string) $request->query('name', ''));
        $prefillAddress = trim((string) $request->query('address', ''));
        $prefillMobile = trim((string) $request->query('mobile', ''));

        return view($this->formView($type), [
            'type' => $type,
            'typeInfo' => $this->getTypeInfo($type),
            'isEdit' => false,
            'data' => [
                'code' => '',
                'name' => $prefillName,
                'addr1' => $prefillAddress,
                'mobile' => $prefillMobile,
                'grp' => $type === 'D' ? 'DEP' : '',
            ],
            'lookups' => $this->loadLookups(),
        ]);
    }

    public function edit(Request $request): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $type = $this->normalizeType((string) $request->query('type', 'C'));
        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            return redirect('/customer/add?type=' . urlencode($type));
        }

        $data = $this->getCustomerByCode($code);
        if (!$data) {
            return redirect('/customer?type=' . urlencode($type) . '&list=1')
                ->with('flash_message', 'Record not found')
                ->with('flash_type', 'error');
        }

        return view($this->formView($type), [
            'type' => $type,
            'typeInfo' => $this->getTypeInfo($type),
            'isEdit' => true,
            'data' => $data,
            'lookups' => $this->loadLookups(),
        ]);
    }

    public function deletePage(Request $request): RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $type = $this->normalizeType((string) $request->query('type', 'C'));
        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            return redirect('/customer?type=' . urlencode($type) . '&list=1');
        }

        $resp = $this->delete($request->merge(['code' => $code]));
        $payload = $resp->getData(true);
        return redirect('/customer?type=' . urlencode($type) . '&list=1')
            ->with('flash_message', (string) ($payload['message'] ?? 'Done'))
            ->with('flash_type', !empty($payload['success']) ? 'success' : 'error');
    }

    public function picture(Request $request): Response
    {
        if (!$this->isAuthorized($request)) {
            return response('', 401);
        }

        $code = trim((string) $request->query('code', ''));
        if ($code === '' || !$this->schemaHasTable('clientspict')) {
            return response('', 404);
        }

        $row = DB::table('clientspict')->select(['pict'])->where('code', $code)->first();
        $blob = (string) ($row->pict ?? '');
        if ($blob === '') {
            return response('', 404);
        }

        return response($blob, 200, ['Content-Type' => 'image/jpeg']);
    }

    public function nextCode(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $type = strtoupper(trim((string) $request->query('type', 'C')));
        if (!in_array($type, ['C', 'S', 'F', 'D', 'G', 'R', 'J'], true)) {
            $type = 'C';
        }

        try {
            $code = $this->getNextAutoCode($type);
            return response()->json(['success' => true, 'code' => $code]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function get(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Code is required'], 400);
        }

        $row = $this->getCustomerByCode($code);
        if ($row === null) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $row]);
    }

    public function checkPhone(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $phone = trim((string) $request->input('phone', $request->query('phone', '')));
        $code = trim((string) $request->input('code', $request->query('code', '')));
        if ($phone === '') {
            return response()->json(['exists' => false]);
        }

        $normalizedPhone = $this->normalizeComparablePhone($phone);
        $dup = DB::table('clients')
            ->select(['code', 'name', 'addr1'])
            ->where(function ($q) use ($phone, $normalizedPhone) {
                $q->whereRaw('TRIM(COALESCE(telephone, "")) = ?', [$phone])
                    ->orWhereRaw('TRIM(COALESCE(mobile, "")) = ?', [$phone]);

                if ($normalizedPhone !== '') {
                    $q->orWhereRaw(
                        'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(telephone, "")), " ", ""), "-", ""), "(", ""), ")", ""), "+", "") = ?',
                        [$normalizedPhone]
                    )->orWhereRaw(
                        'REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(mobile, "")), " ", ""), "-", ""), "(", ""), ")", ""), "+", "") = ?',
                        [$normalizedPhone]
                    );
                }
            })
            ->when($code !== '', fn ($q) => $q->whereRaw('TRIM(COALESCE(code, "")) <> ?', [$code]))
            ->first();

        if ($dup) {
            $details = trim(sprintf('%s - %s, %s', $dup->code ?? '', $dup->name ?? '', $dup->addr1 ?? ''), ' ,');
            return response()->json(['exists' => true, 'details' => $details]);
        }

        return response()->json(['exists' => false]);
    }

    public function checkIdNo(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $idno = trim((string) $request->input('idno', $request->query('idno', '')));
        $code = trim((string) $request->input('code', $request->query('code', '')));
        if ($idno === '') {
            return response()->json(['exists' => false]);
        }

        $dup = DB::table('clients')
            ->select(['name'])
            ->whereRaw('TRIM(COALESCE(idno, "")) = ?', [$idno])
            ->when($code !== '', fn ($q) => $q->whereRaw('TRIM(COALESCE(code, "")) <> ?', [$code]))
            ->first();

        if ($dup) {
            return response()->json(['exists' => true, 'name' => (string) ($dup->name ?? '')]);
        }

        return response()->json(['exists' => false]);
    }

    private function normalizeComparablePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', trim($phone)) ?? '';
    }

    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $this->readPayload($request);
        $advancedPayload = [];
        if (isset($payload['advanced_payload'])) {
            $decoded = json_decode((string) $payload['advanced_payload'], true);
            if (is_array($decoded)) {
                $advancedPayload = $decoded;
            }
        }
        $code = strtoupper(trim((string) ($payload['code'] ?? '')));
        $originalCode = strtoupper(trim((string) ($payload['original_code'] ?? $code)));
        $type = strtoupper(trim((string) ($payload['type'] ?? $payload['ctype'] ?? 'C')));
        if (trim((string) ($payload['name'] ?? '')) === '') {
            return response()->json(['success' => false, 'message' => 'Name is required'], 400);
        }

        $isDepositor = $type === 'D';
        if ($isDepositor) {
            $type = 'C';
            $payload['grp'] = 'DEP';
        }

        if ($code === '') {
            $code = $this->reserveNextCode($type);
            $payload['code'] = $code;
            if ($originalCode === '') {
                $originalCode = $code;
            }
        }

        $isRename = $originalCode !== '' && $originalCode !== $code;
        if ($isRename) {
            if (!DB::table('clients')->where('code', $originalCode)->exists()) {
                return response()->json(['success' => false, 'message' => 'Original code not found'], 404);
            }
            if (DB::table('clients')->where('code', $code)->exists()) {
                return response()->json(['success' => false, 'message' => 'New code already exists'], 400);
            }
            if ($this->codeHasLinkedTransactions($originalCode)) {
                return response()->json(['success' => false, 'message' => 'This code already has linked transactions. Rename is blocked for safety.'], 400);
            }
        }

        $exists = DB::table('clients')->where('code', $code)->exists();

        $clientRow = $this->buildClientRow($payload, $code, $type);

        DB::beginTransaction();
        try {
            if ($isRename) {
                $this->renameCustomerCode($originalCode, $code);
                $exists = true;
            }

            if ($exists) {
                DB::table('clients')->where('code', $code)->update($clientRow);
            } else {
                DB::table('clients')->insert($clientRow);
                $this->syncCodeCounter($code, $type);
            }

            $this->upsertAccountM($clientRow, $payload);
            $this->upsertClientGs($code, $payload, $type);
            $this->upsertAdvanced($code, $advancedPayload);
            $this->upsertPhoto($request, $code);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 400);
        }

        $label = $this->getTypeInfo($isDepositor ? 'D' : $type)['title'];
        $actionWord = $isRename ? 'Renamed' : ($exists ? 'Updated' : 'Added');
        $this->logDelpart($request, $label . '(' . $code . ') ' . $actionWord, ['utype' => $exists ? 'E' : 'A', 'ttype' => 'R']);
        $saved = $this->getCustomerByCode($code);
        $message = $isRename
            ? ($label . ' code changed from ' . $originalCode . ' to ' . $code . ' successfully')
            : ($exists ? ($label . ' updated successfully') : ($label . ' added successfully'));

        if (in_array($type, ['C', 'S'], true) && SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code'))) {
            if ($this->shouldSyncCustomerFromPrimary()) {
                $this->queueCustomerSecondarySync($code, $type, $isRename ? $originalCode : null);
                $message .= '. Secondary sync started in background.';
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $saved,
        ]);
    }

    public function delete(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $this->readPayload($request);
        $code = trim((string) ($payload['code'] ?? ''));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Code is required'], 400);
        }
        $type = strtoupper(trim((string) (DB::table('clients')->where('code', $code)->value('ctype') ?? '')));

        if (DB::table('daybook')->where('accode', $code)->exists()) {
            return response()->json(['success' => false, 'message' => 'Transactions exist. Cannot delete this code.'], 400);
        }

        DB::beginTransaction();
        try {
            DB::table('clients')->where('code', $code)->delete();
            DB::table('accountm')->where('accode', $code)->delete();
            if ($this->schemaHasTable('clientsgs')) {
                DB::table('clientsgs')->where('code', $code)->delete();
            }
            if ($this->schemaHasTable('clientspict')) {
                DB::table('clientspict')->where('code', $code)->delete();
            }
            if ($this->schemaHasTable('clients_advanced')) {
                DB::table('clients_advanced')->where('code', $code)->delete();
            }
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 400);
        }

        $this->logDelpart($request, 'Customer(' . $code . ') Deleted', ['utype' => 'D', 'ttype' => 'R']);
        $message = 'Customer deleted successfully';

        if (in_array($type, ['C', 'S'], true) && SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code'))) {
            if ($this->shouldSyncCustomerFromPrimary()) {
                $this->queueCustomerSecondaryDelete($code, $type);
                $message .= ' Secondary delete sync started in background.';
            }
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $type = $this->normalizeType((string) $request->input('type', 'C'));
        if (!in_array($type, ['C', 'S', 'G'], true)) {
            return redirect('/customer?type=' . urlencode($type) . '&list=1')
                ->with('flash_message', 'Import is available only for customers, suppliers, and smiths')
                ->with('flash_type', 'error');
        }

        $file = $request->file('csv_file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return redirect('/customer?type=' . urlencode($type) . '&list=1')
                ->with('flash_message', 'Please choose a valid CSV file')
                ->with('flash_type', 'error');
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'txt'], true)) {
            return redirect('/customer?type=' . urlencode($type) . '&list=1')
                ->with('flash_message', 'Only CSV files are allowed')
                ->with('flash_type', 'error');
        }

        try {
            $summary = $this->runCsvImport($file, $type);
        } catch (Throwable $e) {
            return redirect('/customer?type=' . urlencode($type) . '&list=1')
                ->with('flash_message', 'Import failed: ' . $e->getMessage())
                ->with('flash_type', 'error');
        }

        $message = sprintf(
            '%s import completed. Imported: %d, Skipped: %d',
            $this->getTypeInfo($type)['title'],
            $summary['imported'],
            $summary['skipped']
        );
        if (!empty($summary['errors'])) {
            $message .= '. Errors: ' . implode(' | ', array_slice($summary['errors'], 0, 5));
            if (count($summary['errors']) > 5) {
                $message .= ' ...';
            }
        }

        $this->logDelpart($request, $this->getTypeInfo($type)['title'] . ' CSV Import', ['utype' => 'A', 'ttype' => 'R']);

        return redirect('/customer?type=' . urlencode($type) . '&list=1')
            ->with('flash_message', $message)
            ->with('flash_type', empty($summary['errors']) ? 'success' : 'error');
    }

    public function syncList(Request $request): RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $type = $this->normalizeType((string) $request->input('type', 'C'));
        $search = trim((string) $request->input('search', ''));
        $noRemoved = (string) $request->input('noremoved', '1') !== '0';

        if (!in_array($type, ['C', 'S'], true)) {
            return redirect('/customer?type=' . urlencode($type) . '&list=1')
                ->with('flash_message', 'Bulk secondary sync is available only for customers and suppliers')
                ->with('flash_type', 'error');
        }

        if (!SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code'))) {
            return redirect('/customer?type=' . urlencode($type) . '&list=1')
                ->with('flash_message', 'You do not have permission for secondary database sync.')
                ->with('flash_type', 'error');
        }

        if (!$this->shouldSyncCustomerFromPrimary()) {
            return redirect('/customer?type=' . urlencode($type) . '&list=1' . ($search !== '' ? '&search=' . urlencode($search) : '') . ($noRemoved ? '&noremoved=1' : ''))
                ->with('flash_message', 'Customer sync is allowed only from primary company to secondary company.')
                ->with('flash_type', 'error');
        }

        try {
            $codes = $this->queryCustomerCodesByType($type, $search, $noRemoved);
            if ($codes === []) {
                return redirect('/customer?type=' . urlencode($type) . '&list=1' . ($search !== '' ? '&search=' . urlencode($search) : '') . ($noRemoved ? '&noremoved=1' : ''))
                    ->with('flash_message', 'No records found to sync.')
                    ->with('flash_type', 'error');
            }

            $sync = $this->customerSync();
            $synced = 0;
            $errors = [];

            foreach ($codes as $code) {
                try {
                    $sync->syncParty($code, $type);
                    $synced++;
                } catch (Throwable $e) {
                    $errors[] = $code . ': ' . $e->getMessage();
                }
            }

            $message = $this->getTypeInfo($type)['plural'] . ' secondary sync completed. Synced: ' . $synced;
            if ($errors !== []) {
                $message .= '. Failed: ' . count($errors) . '. ' . implode(' | ', array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $message .= ' ...';
                }
            }

            $this->logDelpart($request, $this->getTypeInfo($type)['plural'] . ' Secondary Sync', ['utype' => 'E', 'ttype' => 'R']);

            return redirect('/customer?type=' . urlencode($type) . '&list=1' . ($search !== '' ? '&search=' . urlencode($search) : '') . ($noRemoved ? '&noremoved=1' : ''))
                ->with('flash_message', $message)
                ->with('flash_type', $errors === [] ? 'success' : 'error');
        } catch (Throwable $e) {
            return redirect('/customer?type=' . urlencode($type) . '&list=1' . ($search !== '' ? '&search=' . urlencode($search) : '') . ($noRemoved ? '&noremoved=1' : ''))
                ->with('flash_message', 'Secondary sync failed: ' . $e->getMessage())
                ->with('flash_type', 'error');
        }
    }

    private function readPayload(Request $request): array
    {
        $json = $request->json()->all();
        if (is_array($json) && !empty($json)) {
            return $json;
        }
        return $request->all();
    }

    private function isAuthorized(Request $request): bool
    {
        return (string) ($request->session()->get('user_code') ?? '') !== '';
    }

    private function formView(string $type): string
    {
        return match (true) {
            in_array($type, ['G', 'R', 'J'], true) => 'native.customer.form-smith',
            $type === 'D' => 'native.customer.form-depositor',
            default => 'native.customer.form',
        };
    }

    private function normalizeType(string $type): string
    {
        $type = strtoupper(trim($type));
        return in_array($type, ['C', 'S', 'F', 'D', 'G', 'R', 'J'], true) ? $type : 'C';
    }

    private function getTypeInfo(string $type): array
    {
        $types = [
            'C' => ['title' => 'Customer', 'plural' => 'Customers'],
            'S' => ['title' => 'Supplier', 'plural' => 'Suppliers'],
            'F' => ['title' => 'Staff', 'plural' => 'Staff'],
            'D' => ['title' => 'Depositor', 'plural' => 'Depositors'],
            'G' => ['title' => 'Goldsmith', 'plural' => 'Goldsmiths'],
            'R' => ['title' => 'Refiner', 'plural' => 'Refiners'],
            'J' => ['title' => 'Jewellery', 'plural' => 'Jewellery'],
        ];
        return $types[$type] ?? $types['C'];
    }

    private function queryCustomersByType(string $type, string $search, bool $noRemoved): array
    {
        $q = DB::table('clients')
            ->select(['code', 'name', 'mobile', 'telephone', 'city', 'addr1', 'ctype', 'grp', 'removed']);

        if ($type === 'D') {
            $q->where('ctype', 'C')->where(function ($x) {
                $x->whereRaw("LEFT(COALESCE(grp,''),3) = 'DEP'");
                if ($this->schemaHasColumn('clients', 'dcount')) {
                    $x->orWhere('dcount', '>', 0);
                }
            });
        } else {
            $q->where('ctype', $type);
        }

        if ($noRemoved && $this->schemaHasColumn('clients', 'removed')) {
            $q->where(function ($x) {
                $x->where('removed', '<>', 1)->orWhereNull('removed');
            });
        }

        if ($search !== '') {
            $s = '%' . $search . '%';
            $q->where(function ($x) use ($s) {
                $x->where('code', 'like', $s)
                    ->orWhere('name', 'like', $s)
                    ->orWhere('mobile', 'like', $s)
                    ->orWhere('telephone', 'like', $s)
                    ->orWhere('city', 'like', $s)
                    ->orWhere('addr1', 'like', $s);
            });
        }

        return $q->orderBy('name')->limit(300)->get()->map(fn ($r) => (array) $r)->all();
    }

    private function queryCustomerCodesByType(string $type, string $search, bool $noRemoved): array
    {
        $q = DB::table('clients')->select(['code']);

        if ($type === 'D') {
            $q->where('ctype', 'C')->where(function ($x) {
                $x->whereRaw("LEFT(COALESCE(grp,''),3) = 'DEP'");
                if ($this->schemaHasColumn('clients', 'dcount')) {
                    $x->orWhere('dcount', '>', 0);
                }
            });
        } else {
            $q->where('ctype', $type);
        }

        if ($noRemoved && $this->schemaHasColumn('clients', 'removed')) {
            $q->where(function ($x) {
                $x->where('removed', '<>', 1)->orWhereNull('removed');
            });
        }

        if ($search !== '') {
            $s = '%' . $search . '%';
            $q->where(function ($x) use ($s) {
                $x->where('code', 'like', $s)
                    ->orWhere('name', 'like', $s)
                    ->orWhere('mobile', 'like', $s)
                    ->orWhere('telephone', 'like', $s)
                    ->orWhere('city', 'like', $s)
                    ->orWhere('addr1', 'like', $s);
            });
        }

        return $q->orderBy('name')
            ->pluck('code')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->values()
            ->all();
    }

    private function loadLookups(): array
    {
        $salesmen = $this->schemaHasTable('sman')
            ? DB::table('sman')->select(['code', 'name'])->orderBy('name')->get()->map(fn ($r) => (array) $r)->all()
            : [];
        $accountGroups = $this->schemaHasTable('accountg')
            ? DB::table('accountg')->select(['grcode as code', 'name'])->orderBy('name')->get()->map(fn ($r) => (array) $r)->all()
            : [];
        $bsHeads = $this->schemaHasTable('accountgbs')
            ? DB::table('accountgbs')->select(['hcode as code', 'hname as name'])->orderBy('hname')->get()->map(fn ($r) => (array) $r)->all()
            : [];
        $states = [];
        // Primary source: `state` table (code, name) as requested.
        if ($this->schemaHasTable('state')) {
            $stateCols = array_keys($this->schemaTableColumns('state'));
            $codeCol = in_array('code', $stateCols, true) ? 'code' : (in_array('state', $stateCols, true) ? 'state' : null);
            $nameCol = in_array('name', $stateCols, true) ? 'name' : (in_array('state', $stateCols, true) ? 'state' : null);
            if ($codeCol && $nameCol) {
                $states = DB::table('state')
                    ->selectRaw("{$codeCol} as code, {$nameCol} as name")
                    ->whereNotNull($codeCol)
                    ->where($codeCol, '<>', '')
                    ->distinct()
                    ->orderBy($codeCol)
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->all();
            }
        }
        if (empty($states) && $this->schemaHasTable('statestate')) {
            $states = DB::table('statestate')
                ->select(['state as code', 'state as name'])
                ->whereNotNull('state')
                ->distinct()
                ->orderBy('state')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        }
        if (empty($states)) {
            $states = DB::table('clients')
                ->selectRaw("state as code, state as name")
                ->whereNotNull('state')
                ->where('state', '<>', '')
                ->distinct()
                ->orderBy('state')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        }
        $routes = $this->schemaHasTable('route')
            ? DB::table('route')->select(['code', 'name'])->orderBy('code')->get()->map(fn ($r) => (array) $r)->all()
            : [];
        $areas = $this->schemaHasTable('area')
            ? DB::table('area')->select(['code', 'name'])->orderBy('code')->get()->map(fn ($r) => (array) $r)->all()
            : [];
        return [
            'salesmen' => $salesmen,
            'accountGroups' => $accountGroups,
            'bsHeads' => $bsHeads,
            'states' => $states,
            'routes' => $routes,
            'areas' => $areas,
        ];
    }

    private function getCustomerByCode(string $code): ?array
    {
        $code = trim($code);
        $row = DB::table('clients')->whereRaw('TRIM(COALESCE(code, "")) = ?', [$code])->first();
        if (!$row) {
            return null;
        }
        $data = (array) $row;

        foreach ([
            'code', 'name', 'addr1', 'addr2', 'addr3', 'city', 'telephone', 'mobile',
            'tinno', 'idno', 'religion', 'note', 'carea', 'pcard', 'smcode', 'email',
            'state', 'grp', 'cocode', 'route', 'ctype', 'panadhar'
        ] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if ($this->schemaHasTable('accountm')) {
            $ac = DB::table('accountm')->whereRaw('TRIM(COALESCE(accode, "")) = ?', [$code])->first();
            if ($ac) {
                $acArr = (array) $ac;
                $data['acgrp'] = $acArr['grcode'] ?? null;
                $data['bshead'] = $acArr['bshead'] ?? null;
                $data['ac_blocked'] = $acArr['blocked'] ?? null;
            }
        }

        if ($this->schemaHasTable('clientsgs')) {
            $gs = DB::table('clientsgs')->whereRaw('TRIM(COALESCE(code, "")) = ?', [$code])->first();
            if ($gs) {
                $data = array_merge($data, (array) $gs);
            }
        }

        if ($this->schemaHasTable('clients_advanced')) {
            $adv = DB::table('clients_advanced')->whereRaw('TRIM(COALESCE(code, "")) = ?', [$code])->first();
            $data['advanced'] = $adv ? (array) $adv : [];
        } else {
            $data['advanced'] = [];
        }

        return $data;
    }

    private function getNextAutoCode(string $type): string
    {
        $isDepositor = $type === 'D';
        if ($isDepositor) {
            $type = 'C';
        }

        if ($type === 'C') {
            $lastNo = (int) (DB::table('generali')->where('code', 'CLASTNO')->value('cvalue') ?? 0);
            $prefix = $this->resolvePartyCodePrefix('C');
        } else {
            $lastNo = (int) (DB::table('generali')->where('code', 'SLASTNO')->value('cvalue') ?? 0);
            $prefix = $this->resolvePartyCodePrefix($type);
        }

        if ($prefix === '') {
            $prefix = $type;
        }
        if ($isDepositor) {
            $prefix = 'C';
        }

        $derived = $this->getMaxPartyCodeNumber($prefix);
        if ($derived > $lastNo) {
            $lastNo = $derived;
        }

        return $prefix . str_pad((string) ($lastNo + 1), 4, '0', STR_PAD_LEFT);
    }

    private function buildClientRow(array $data, string $code, string $type): array
    {
        $customerLike = in_array(strtoupper($type), ['C', 'D'], true);
        $opBalance = (float) ($data['opbalance'] ?? 0);
        $opBalanceType = strtolower((string) ($data['balance_type'] ?? 'debit'));
        if ($customerLike) {
            $opBalance = $opBalanceType === 'debit' ? abs($opBalance) : -abs($opBalance);
        } else {
            $opBalance = $opBalanceType === 'credit' ? abs($opBalance) : -abs($opBalance);
        }
        $opBalanceB = (float) ($data['opbalanceb'] ?? 0);
        $opBalanceTypeB = strtolower((string) ($data['balance_type_b'] ?? 'debit'));
        if ($customerLike) {
            $opBalanceB = $opBalanceTypeB === 'debit' ? abs($opBalanceB) : -abs($opBalanceB);
        } else {
            $opBalanceB = $opBalanceTypeB === 'credit' ? abs($opBalanceB) : -abs($opBalanceB);
        }
        $weight = (float) ($data['opweight'] ?? 0);
        $weight = strtolower((string) ($data['weight_type'] ?? 'debit')) === 'credit' ? abs($weight) : -abs($weight);
        $depWeight = (float) ($data['opdepwgtbal'] ?? 0);
        $depWeight = strtolower((string) ($data['depweight_type'] ?? 'debit')) === 'credit' ? abs($depWeight) : -abs($depWeight);
        [$addr1, $addr2, $addr3] = $this->normalizeAddressLines(
            (string) ($data['addr1'] ?? ''),
            (string) ($data['addr2'] ?? ''),
            (string) ($data['addr3'] ?? '')
        );

        $row = [
            'code' => $code,
            'name' => substr(trim((string) ($data['name'] ?? '')), 0, 40),
            'addr1' => $addr1,
            'addr2' => $addr2,
            'addr3' => $addr3,
            'city' => trim((string) ($data['city'] ?? '')),
            'telephone' => trim((string) ($data['telephone'] ?? '')),
            'mobile' => trim((string) ($data['mobile'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'pin' => trim((string) ($data['pin'] ?? '')),
            'state' => trim((string) ($data['state'] ?? '')),
            'panadhar' => trim((string) ($data['panadhar'] ?? '')),
            'tin' => trim((string) ($data['tin'] ?? '')),
            'cst' => trim((string) ($data['cst'] ?? '')),
            'opbalance' => $opBalance,
            'opbalanceb' => $opBalanceB,
            'ctype' => $type,
            'control' => 1,
            'removed' => isset($data['removed']) ? 1 : 0,
            'blocked' => isset($data['blocked']) ? 'Y' : 'N',
            'salary' => (float) ($data['salary'] ?? 0),
            'cocode' => trim((string) ($data['cocode'] ?? '')),
            'grp' => trim((string) ($data['grp'] ?? 'O')),
            'route' => trim((string) ($data['route'] ?? '')),
            'carea' => trim((string) ($data['carea'] ?? ($data['area'] ?? ''))),
            'adate' => $this->normalizeDate($data['adate'] ?? ''),
            'duedate' => $this->normalizeDate($data['duedate'] ?? ''),
            'cutrate' => (float) ($data['cutrate'] ?? 0),
            'colncomn' => (float) ($data['colncomn'] ?? 0),
            'opweight' => $weight,
            'opdepwgtbal' => $depWeight,
            'idno' => trim((string) ($data['idno'] ?? '')),
            'religion' => trim((string) ($data['religion'] ?? '')),
            'note' => trim((string) ($data['note'] ?? '')),
            'coparty' => isset($data['coparty']) ? 'Y' : 'N',
            'pcard' => trim((string) ($data['pcard'] ?? '')),
            'smcode' => trim((string) ($data['smcode'] ?? '')),
            'pospwd' => trim((string) ($data['pospwd'] ?? '')),
            'agent' => isset($data['agent']) ? 'Y' : 'N',
            'oppcardpoints' => (float) ($data['oppcardpoints'] ?? 0),
            'pcardno' => trim((string) ($data['pcardno'] ?? '')),
            'approval' => isset($data['approval']) ? 'Y' : 'N',
            'homemobile' => trim((string) ($data['homemobile'] ?? '')),
            'distance' => (int) ($data['distance'] ?? 0),
            'billdate' => $this->normalizeDate($data['billdate'] ?? ''),
        ];

        $columns = $this->schemaTableColumns('clients');
        return array_filter($row, static fn ($v, $k) => array_key_exists($k, $columns), ARRAY_FILTER_USE_BOTH);
    }

    private function runCsvImport(UploadedFile $file, string $type): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw new \RuntimeException('Cannot read uploaded file');
        }

        $header = fgetcsv($handle);
        if (!is_array($header) || $header === []) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty');
        }

        $normalizedHeader = array_map(fn ($value) => $this->normalizeImportHeader((string) $value), $header);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        $lineNo = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $lineNo++;
            $mapped = [];
            foreach ($normalizedHeader as $index => $name) {
                if ($name === '') {
                    continue;
                }
                $mapped[$name] = trim((string) ($row[$index] ?? ''));
            }

            if ($this->isImportRowBlank($mapped)) {
                $skipped++;
                continue;
            }

            if (strtoupper(substr((string) ($mapped['code'] ?? ''), 0, 5)) === 'TOTAL') {
                $skipped++;
                continue;
            }

            DB::beginTransaction();
            try {
                if ($this->importClientRow($mapped, $type)) {
                    $imported++;
                } else {
                    $skipped++;
                }
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                $errors[] = 'Line ' . $lineNo . ': ' . $e->getMessage();
            }
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function importClientRow(array $row, string $type): bool
    {
        $code = strtoupper(substr(trim((string) ($row['code'] ?? '')), 0, 10));
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return false;
        }

        if ($code === '') {
            $code = $this->reserveNextCode($type);
        }

        if (DB::table('clients')->where('code', $code)->exists()) {
            return false;
        }

        $openingBalance = $this->parseImportNumber($row['opening_balance'] ?? '0');
        if ($type === 'S') {
            $openingBalance = -$openingBalance;
        }

        $groupDefault = $type === 'S' ? 'SUNCR' : 'SUNDB';
        $accountGroup = strtoupper(trim((string) ($row['account_group'] ?? $groupDefault)));
        $bsHead = strtoupper(trim((string) ($row['bs_head'] ?? $accountGroup ?: $groupDefault)));
        $createdDate = $this->normalizeDate($row['creation_date'] ?? '') ?? date('Y-m-d');

        $payload = [
            'name' => $name,
            'addr1' => (string) ($row['address_1'] ?? ''),
            'addr2' => (string) ($row['address_2'] ?? ''),
            'addr3' => (string) ($row['address_3'] ?? ''),
            'mobile' => (string) ($row['mobile'] ?? ''),
            'telephone' => (string) ($row['telephone'] ?? ($row['mobile'] ?? '')),
            'opbalance' => abs($openingBalance),
            'balance_type' => $type === 'S'
                ? ($openingBalance >= 0 ? 'credit' : 'debit')
                : ($openingBalance >= 0 ? 'debit' : 'credit'),
            'opbalanceb' => abs($openingBalance),
            'balance_type_b' => $type === 'S'
                ? ($openingBalance >= 0 ? 'credit' : 'debit')
                : ($openingBalance >= 0 ? 'debit' : 'credit'),
            'grp' => $type === 'C' ? 'SCHM' : '',
            'adate' => $createdDate,
            'billdate' => $createdDate,
            'acgrp' => $accountGroup !== '' ? $accountGroup : $groupDefault,
            'bshead' => $bsHead !== '' ? $bsHead : $groupDefault,
        ];

        $clientRow = $this->buildClientRow($payload, $code, $type);
        DB::table('clients')->insert($clientRow);

        $this->upsertAccountM($clientRow, $payload);

        if ($type === 'C' && $this->schemaHasTable('clients_kuridet')) {
            $this->insertClientKuriDet($code, $createdDate, (float) ($clientRow['opbalance'] ?? 0), (string) ($row['kuri_type'] ?? ''));
        }

        if (in_array($type, ['G', 'R', 'J'], true)) {
            $this->upsertClientGs($code, $payload, $type);
        }

        return true;
    }

    private function insertClientKuriDet(string $code, string $createdDate, float $openingBalance, string $kuriType): void
    {
        $row = [
            'code' => $code,
            'startdate' => $createdDate,
            'instnos' => 0,
            'instamt' => 0,
            'totamt' => 0,
            'bonus' => 0,
            'finished' => 'N',
            'kuritype' => trim($kuriType),
            'intrate' => 0,
            'colntype' => '',
            'colnagent' => '',
            'matdate' => null,
            'wadate' => null,
            'bdate' => null,
            'nomname' => '',
            'nomaddr' => '',
            'nomrelation' => '',
            'showwgtdet' => 'N',
            'opwgt' => 0,
            'opwgtb' => 0,
            'collnopbal' => $openingBalance,
            'custlinkac' => '',
            'collnminamt' => 0,
            'collnmaxamt' => 0,
        ];

        $columns = $this->schemaTableColumns('clients_kuridet');
        $row = array_filter($row, static fn ($v, $k) => array_key_exists($k, $columns), ARRAY_FILTER_USE_BOTH);
        if (!empty($row)) {
            DB::table('clients_kuridet')->insert($row);
        }
    }

    private function reserveNextCode(string $type): string
    {
        $counterCode = $type === 'C' ? 'CLASTNO' : 'SLASTNO';
        $current = (int) (DB::table('generali')->where('code', $counterCode)->lockForUpdate()->value('cvalue') ?? 0);
        $prefix = $this->resolvePartyCodePrefix($type);

        $current = max($current, $this->getMaxPartyCodeNumber($prefix));

        $next = $current + 1;
        DB::table('generali')->updateOrInsert(['code' => $counterCode], ['cvalue' => $next]);

        return strtoupper($prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT));
    }

    private function getMaxPartyCodeNumber(string $prefix): int
    {
        $prefix = strtoupper(trim($prefix));
        if ($prefix === '') {
            return 0;
        }

        $codes = DB::table('clients')
            ->select(['code'])
            ->where('code', 'regexp', '^' . preg_quote($prefix, '/') . '[0-9]+$')
            ->pluck('code');

        $max = 0;
        foreach ($codes as $code) {
            $value = strtoupper(trim((string) $code));
            if (preg_match('/^' . preg_quote($prefix, '/') . '([0-9]+)$/', $value, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }

    private function syncCodeCounter(string $code, string $type): void
    {
        $type = strtoupper(trim($type));
        if (!in_array($type, ['C', 'S'], true)) {
            return;
        }

        $prefix = strtoupper($this->resolvePartyCodePrefix($type));
        $upperCode = strtoupper(trim($code));
        if ($prefix === '' || !str_starts_with($upperCode, $prefix)) {
            return;
        }

        $suffix = substr($upperCode, strlen($prefix));
        if ($suffix === '' || !ctype_digit($suffix)) {
            return;
        }

        $counterCode = $type === 'C' ? 'CLASTNO' : 'SLASTNO';
        $number = (int) $suffix;

        $current = (int) (DB::table('generali')->where('code', $counterCode)->value('cvalue') ?? 0);
        DB::table('generali')->updateOrInsert(
            ['code' => $counterCode],
            ['cvalue' => max($current, $number)]
        );
    }

    private function resolvePartyCodePrefix(string $type): string
    {
        $type = strtoupper(trim($type));
        if (!in_array($type, ['C', 'S', 'F', 'D', 'G', 'R', 'J'], true)) {
            $type = 'C';
        }

        $prefixCode = $type === 'C' ? 'CPREFIX' : 'SPREFIX';
        $fallbackPrefix = $type === 'C' ? 'C' : ($type !== '' ? $type : 'S');
        $prefix = trim((string) (DB::table('generals')->where('code', $prefixCode)->value('cvalue') ?? $fallbackPrefix));

        $database = strtolower(trim((string) Config::get('database.connections.mysql.database', '')));
        $overrides = $this->loadCompanyCodePrefixOverrides();
        $override = trim((string) ($overrides[$database][$type] ?? ''));
        if ($override !== '') {
            $prefix = strtoupper($override);
        }

        return strtoupper($prefix !== '' ? $prefix : $fallbackPrefix);
    }

    private function loadCompanyCodePrefixOverrides(): array
    {
        $path = storage_path('app/company-code-prefixes.json');
        if (!File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $database => $types) {
            if (!is_array($types)) {
                continue;
            }

            $databaseKey = strtolower(trim((string) $database));
            if ($databaseKey === '') {
                continue;
            }

            foreach ($types as $type => $prefix) {
                $typeKey = strtoupper(trim((string) $type));
                $prefixValue = strtoupper(trim((string) $prefix));
                if ($typeKey === '' || $prefixValue === '') {
                    continue;
                }

                $normalized[$databaseKey][$typeKey] = $prefixValue;
            }
        }

        return $normalized;
    }

    private function shouldSyncCustomerToPrimary(): bool
    {
        $currentDatabase = strtolower(trim((string) Config::get('database.connections.mysql.database', '')));
        $primaryDatabase = strtolower(trim((string) env('DB_DATABASE', '')));

        return $currentDatabase !== '' && $primaryDatabase !== '' && $currentDatabase !== $primaryDatabase;
    }

    private function customerPrimarySync(): SecondaryDatabaseSync
    {
        return (new SecondaryDatabaseSync())->useTargetDatabase((string) env('DB_DATABASE', ''));
    }

    private function customerSync(): SecondaryDatabaseSync
    {
        $currentDatabase = trim((string) Config::get('database.connections.mysql.database', ''));
        $primaryDatabase = trim((string) env('DB_DATABASE', ''));

        if ($currentDatabase !== '' && $primaryDatabase !== '' && strcasecmp($currentDatabase, $primaryDatabase) === 0) {
            return new SecondaryDatabaseSync();
        }

        return (new SecondaryDatabaseSync())->useTargetDatabase($primaryDatabase);
    }

    private function shouldSyncCustomerFromPrimary(): bool
    {
        $currentDatabase = strtolower(trim((string) Config::get('database.connections.mysql.database', '')));
        $primaryDatabase = strtolower(trim((string) env('DB_DATABASE', '')));

        return $currentDatabase !== '' && $primaryDatabase !== '' && $currentDatabase === $primaryDatabase;
    }

    private function queueCustomerSecondarySync(string $code, string $type, ?string $originalCode = null): void
    {
        $code = trim($code);
        $type = strtoupper(trim($type));
        $originalCode = $originalCode !== null ? trim($originalCode) : null;

        if ($code === '' || !in_array($type, ['C', 'S'], true)) {
            return;
        }

        app()->terminating(function () use ($code, $type, $originalCode) {
            try {
                $sync = $this->customerSync();
                if ($originalCode !== null && $originalCode !== '' && strcasecmp($originalCode, $code) !== 0) {
                    try {
                        $sync->deleteParty($originalCode, $type);
                    } catch (Throwable) {
                        // Best-effort cleanup; keep background sync moving.
                    }
                }

                $sync->syncParty($code, $type);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    private function queueCustomerSecondaryDelete(string $code, string $type): void
    {
        $code = trim($code);
        $type = strtoupper(trim($type));

        if ($code === '' || !in_array($type, ['C', 'S'], true)) {
            return;
        }

        app()->terminating(function () use ($code, $type) {
            try {
                $this->customerSync()->deleteParty($code, $type);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    private function normalizeImportHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = str_replace(['.', '-', '/', '\\'], ' ', $header);
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;

        return match ($header) {
            'code', 'customer code', 'supplier code' => 'code',
            'name', 'customer name', 'supplier name' => 'name',
            'address 1', 'address1', 'addr1' => 'address_1',
            'address 2', 'address2', 'addr2' => 'address_2',
            'address 3', 'address3', 'addr3' => 'address_3',
            'mobile number', 'mobile', 'phone', 'phone number' => 'mobile',
            'telephone', 'tel', 'telephone number' => 'telephone',
            'opening balance', 'opbalance', 'op balance' => 'opening_balance',
            'cus creation date', 'creation date', 'created date', 'date', 'adate', 'billdate' => 'creation_date',
            'account group', 'acgrp', 'grp', 'group' => 'account_group',
            'bs head', 'bshead', 'shedgrp' => 'bs_head',
            'kuri type', 'kuritype', 'scheme type' => 'kuri_type',
            default => str_replace(' ', '_', $header),
        };
    }

    private function isImportRowBlank(array $row): bool
    {
        foreach (['code', 'name', 'address_1', 'address_2', 'address_3', 'mobile', 'opening_balance', 'creation_date'] as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parseImportNumber(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        $normalized = str_replace([',', ' '], '', $value);
        if (!is_numeric($normalized)) {
            return 0.0;
        }

        return (float) $normalized;
    }

    private function normalizeAddressLines(string $addr1, string $addr2 = '', string $addr3 = ''): array
    {
        $limit = 30;
        $lines = [
            trim($addr1),
            trim($addr2),
            trim($addr3),
        ];

        if (strlen($lines[0]) <= $limit && strlen($lines[1]) <= $limit && strlen($lines[2]) <= $limit) {
            return $lines;
        }

        $combined = trim(implode(' ', array_filter($lines, static fn ($line) => $line !== '')));
        if ($combined === '') {
            return ['', '', ''];
        }

        $chunks = [];
        while ($combined !== '' && count($chunks) < 3) {
            if (strlen($combined) <= $limit) {
                $chunks[] = $combined;
                break;
            }

            $slice = substr($combined, 0, $limit + 1);
            $breakAt = strrpos($slice, ' ');
            if ($breakAt === false || $breakAt < 10) {
                $breakAt = $limit;
            }

            $chunks[] = trim(substr($combined, 0, $breakAt));
            $combined = ltrim(substr($combined, $breakAt));
        }

        while (count($chunks) < 3) {
            $chunks[] = '';
        }

        return [
            substr($chunks[0], 0, $limit),
            substr($chunks[1], 0, $limit),
            substr($chunks[2], 0, $limit),
        ];
    }

    private function upsertAccountM(array $clientData, array $payload): void
    {
        if (!$this->schemaHasTable('accountm')) {
            return;
        }

        $code = (string) $clientData['code'];
        $name = substr(((string) ($clientData['name'] ?? '')) . '(' . $code . ')', 0, 30);
        $ctype = strtoupper((string) ($clientData['ctype'] ?? 'C'));
        $actype1 = $ctype === 'C' ? 'A' : 'L';
        $defaultGroup = in_array($ctype, ['C', 'D', 'J'], true) ? 'SUNDB' : 'SUNCR';
        $grcode = trim((string) ($payload['acgrp'] ?? $clientData['grp'] ?? $defaultGroup));
        if ($grcode === '') {
            $grcode = $defaultGroup;
        }
        $bshead = trim((string) ($payload['bshead'] ?? $grcode));
        if ($bshead === '') {
            $bshead = $defaultGroup;
        }

        $row = [
            'accode' => $code,
            'name' => $name,
            'opbal' => (float) ($clientData['opbalance'] ?? 0),
            'opbalb' => (float) ($clientData['opbalanceb'] ?? 0),
            'actype1' => $actype1,
            'actype2' => $ctype,
            'control' => (int) ($clientData['control'] ?? 1),
            'grcode' => $grcode,
            'bshead' => $bshead,
            'shedgrp' => $bshead,
            'hlp' => 1,
            'sp' => 0,
            'removed' => (int) ($clientData['removed'] ?? 0),
            'blocked' => (string) ($clientData['blocked'] ?? 'N'),
        ];

        $columns = $this->schemaTableColumns('accountm');
        $row = array_filter($row, static fn ($v, $k) => array_key_exists($k, $columns), ARRAY_FILTER_USE_BOTH);

        $exists = DB::table('accountm')->where('accode', $code)->exists();
        if ($exists) {
            $updateRow = $row;
            unset($updateRow['accode']);
            if (!empty($updateRow)) {
                DB::table('accountm')->where('accode', $code)->update($updateRow);
            }
            return;
        }

        if (!empty($row)) {
            DB::table('accountm')->insert($row);
        }
    }

    private function upsertAdvanced(string $code, array $advanced): void
    {
        if ($advanced === [] || !$this->schemaHasTable('clients_advanced')) {
            return;
        }

        $base = ['code' => $code];
        $allowed = $this->schemaTableColumns('clients_advanced');

        $normalized = [];
        foreach ($advanced as $k => $v) {
            $key = trim((string) $k);
            if ($key === '' || !isset($allowed[$key])) {
                continue;
            }
            $normalized[$key] = is_string($v) ? trim($v) : $v;
        }

        $row = array_merge($base, $normalized);
        if (count($row) <= 1) {
            return;
        }

        DB::table('clients_advanced')->upsert([$row], ['code'], array_keys($normalized));
    }

    private function upsertClientGs(string $code, array $payload, string $type): void
    {
        if (!$this->schemaHasTable('clientsgs') || !in_array($type, ['G', 'R', 'J'], true)) {
            return;
        }

        $opWeight = (float) ($payload['opweight'] ?? 0);
        $opWeight = strtolower((string) ($payload['weight_type'] ?? 'debit')) === 'credit' ? abs($opWeight) : -abs($opWeight);

        $opWeightB = (float) ($payload['opweightb'] ?? 0);
        $opWeightB = strtolower((string) ($payload['weight_type_b'] ?? 'debit')) === 'credit' ? abs($opWeightB) : -abs($opWeightB);
        $roundupRaw = trim((string) ($payload['roundup'] ?? ''));
        if (strcasecmp($roundupRaw, 'Y') === 0) {
            $roundup = 1.0;
        } elseif (strcasecmp($roundupRaw, 'N') === 0 || $roundupRaw === '') {
            $roundup = 0.0;
        } else {
            $roundup = (float) $roundupRaw;
        }

        $row = [
            'code' => $code,
            'ctype' => $type,
            'wastage' => (float) ($payload['wastage'] ?? 0),
            'opweight' => $opWeight,
            'mcrate' => (float) ($payload['mcrate'] ?? 0),
            'opweightb' => $opWeightB,
            'opwgtamt' => (float) ($payload['opwgtamt'] ?? 0),
            'opwgtamtb' => (float) ($payload['opwgtamtb'] ?? 0),
            'acin24ct' => isset($payload['acin24ct']) ? 1 : 0,
            'deftouch' => (float) ($payload['deftouch'] ?? 0),
            'convtouch' => (float) ($payload['convtouch'] ?? 0),
            'email' => trim((string) ($payload['email'] ?? '')),
            'decround' => (int) ($payload['decround'] ?? 0),
            'roundup' => $roundup,
            'mcdecround' => (int) ($payload['mcdecround'] ?? 0),
            'silver' => isset($payload['silver']) ? 'Y' : 'N',
            'stocktouch' => (float) ($payload['stocktouch'] ?? 0),
            'intamt' => (float) ($payload['intamt'] ?? 0),
            'intwgt' => (float) ($payload['intwgt'] ?? 0),
        ];

        $columns = $this->schemaTableColumns('clientsgs');
        $row = array_filter($row, static fn ($v, $k) => array_key_exists($k, $columns), ARRAY_FILTER_USE_BOTH);
        if (count($row) <= 1) {
            return;
        }

        $exists = DB::table('clientsgs')->where('code', $code)->exists();
        if ($exists) {
            $updateRow = $row;
            unset($updateRow['code']);
            DB::table('clientsgs')->where('code', $code)->update($updateRow);
            return;
        }

        DB::table('clientsgs')->insert($row);
    }

    private function upsertPhoto(Request $request, string $code): void
    {
        if (!$request->hasFile('photo') || !$this->schemaHasTable('clientspict')) {
            return;
        }

        $file = $request->file('photo');
        if (!$file || !$file->isValid()) {
            return;
        }

        $blob = @file_get_contents($file->getRealPath());
        if ($blob === false || $blob === '') {
            return;
        }

        DB::table('clientspict')->upsert(
            [[
                'code' => $code,
                'pict' => $blob,
            ]],
            ['code'],
            ['pict']
        );
    }

    private function renameCustomerCode(string $oldCode, string $newCode): void
    {
        if ($oldCode === $newCode) {
            return;
        }

        DB::table('clients')->where('code', $oldCode)->update(['code' => $newCode]);

        if ($this->schemaHasTable('accountm')) {
            DB::table('accountm')->where('accode', $oldCode)->update(['accode' => $newCode]);
        }
        if ($this->schemaHasTable('clientsgs')) {
            DB::table('clientsgs')->where('code', $oldCode)->update(['code' => $newCode]);
        }
        if ($this->schemaHasTable('clientspict')) {
            DB::table('clientspict')->where('code', $oldCode)->update(['code' => $newCode]);
        }
        if ($this->schemaHasTable('clients_advanced')) {
            DB::table('clients_advanced')->where('code', $oldCode)->update(['code' => $newCode]);
        }
    }

    private function codeHasLinkedTransactions(string $code): bool
    {
        $checks = [
            ['daybook', 'accode'],
            ['daybookpart', 'accode'],
            ['daybookratewgt', 'accode'],
            ['oglist', 'accode'],
            ['salesm', 'custcode'],
            ['orderm', 'custcode'],
        ];

        foreach ($checks as [$table, $column]) {
            if (!$this->schemaHasTable($table) || !$this->schemaHasColumn($table, $column)) {
                continue;
            }
            if (DB::table($table)->where($column, $code)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function schemaHasTable(string $table): bool
    {
        return self::$schemaCache['t_' . $table] ??= parent::hasTable($table);
    }

    private function schemaTableColumns(string $table): array
    {
        return self::$schemaCache['c_' . $table] ??= array_flip(parent::columnList($table));
    }

    private function schemaHasColumn(string $table, string $col): bool
    {
        return isset($this->schemaTableColumns($table)[$col]);
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt && $dt->format($format) === $value) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }
}
