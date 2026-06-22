<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class EInvoiceRegisterController extends Controller
{
    use LogsDelpartAudit;

    public function index(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('e-invoice-register.index', [
            'title' => 'E-Invoice Register',
            'date1' => date('Y-m-d', strtotime('-30 days')),
            'date2' => date('Y-m-d'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        if (!$this->hasTable('e_invoices')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $date1 = $this->parseDate((string) $request->query('date1', ''), true);
        $date2 = $this->parseDate((string) $request->query('date2', ''), true);
        $status = strtolower(trim((string) $request->query('status', '')));
        $search = trim((string) $request->query('q', ''));

        $query = DB::table('e_invoices')->orderByDesc('generated_at')->orderByDesc('id');

        if ($date1) $query->whereDate('bill_date', '>=', $date1);
        if ($date2) $query->whereDate('bill_date', '<=', $date2);
        if (in_array($status, ['generated', 'cancelled', 'failed'], true)) {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $query->where(function ($w) use ($search) {
                $w->where('bill_no', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhere('irn', 'like', '%' . $search . '%')
                    ->orWhere('ack_no', 'like', '%' . $search . '%');
            });
        }

        $rows = $query->get()->map(function ($r) {
            $resp = json_decode((string) ($r->response_payload ?? ''), true);
            return [
                'id' => (int) $r->id,
                'bill_no' => trim((string) $r->bill_no),
                'bill_date' => $r->bill_date ? Carbon::parse($r->bill_date)->format('d/m/Y') : '',
                'customer_code' => trim((string) ($r->customer_code ?? '')),
                'customer_name' => trim((string) ($r->customer_name ?? '')),
                'gst_no' => trim((string) ($r->gst_no ?? '')),
                'net_total' => (float) ($r->net_total ?? 0),
                'status' => trim((string) ($r->status ?? '')),
                'irn' => trim((string) ($r->irn ?? '')),
                'ack_no' => trim((string) ($r->ack_no ?? '')),
                'ack_date' => trim((string) ($r->ack_date ?? '')),
                'generated_at' => $r->generated_at ? Carbon::parse($r->generated_at)->format('d/m/Y H:i') : '',
                'cancelled_at' => $r->cancelled_at ? Carbon::parse($r->cancelled_at)->format('d/m/Y H:i') : '',
                'has_pdf' => trim((string) data_get($resp, 'results.message.EinvoicePdf', '')) !== '',
            ];
        })->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function details(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $billNo = trim((string) $request->query('bill_no', ''));
        if ($billNo === '' || !$this->hasTable('e_invoices')) {
            return response()->json(['ok' => false, 'message' => 'E-invoice not found.'], 404);
        }

        $row = DB::table('e_invoices')->where('bill_no', $billNo)->orderByDesc('id')->first();
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'E-invoice not found.'], 404);
        }

        $request_decoded = json_decode((string) ($row->request_payload ?? ''), true);
        $response_decoded = json_decode((string) ($row->response_payload ?? ''), true);
        $cancel_decoded = json_decode((string) ($row->cancel_response ?? ''), true);

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => (int) $row->id,
                'bill_no' => trim((string) $row->bill_no),
                'bill_type' => trim((string) ($row->bill_type ?? 'S')),
                'bill_date' => $row->bill_date ? Carbon::parse($row->bill_date)->format('d/m/Y') : '',
                'customer_code' => trim((string) ($row->customer_code ?? '')),
                'customer_name' => trim((string) ($row->customer_name ?? '')),
                'gst_no' => trim((string) ($row->gst_no ?? '')),
                'net_total' => (float) ($row->net_total ?? 0),
                'status' => trim((string) ($row->status ?? '')),
                'irn' => trim((string) ($row->irn ?? '')),
                'ack_no' => trim((string) ($row->ack_no ?? '')),
                'ack_date' => trim((string) ($row->ack_date ?? '')),
                'signed_qr_code' => (string) ($row->signed_qr_code ?? ''),
                'provider' => trim((string) ($row->provider ?? '')),
                'generated_by' => trim((string) ($row->generated_by ?? '')),
                'generated_at' => $row->generated_at ? Carbon::parse($row->generated_at)->format('d/m/Y H:i:s') : '',
                'cancel_reason' => trim((string) ($row->cancel_reason ?? '')),
                'cancel_remark' => trim((string) ($row->cancel_remark ?? '')),
                'cancelled_by' => trim((string) ($row->cancelled_by ?? '')),
                'cancelled_at' => $row->cancelled_at ? Carbon::parse($row->cancelled_at)->format('d/m/Y H:i:s') : '',
                'request_payload' => is_array($request_decoded) ? $request_decoded : (string) ($row->request_payload ?? ''),
                'response_payload' => is_array($response_decoded) ? $response_decoded : (string) ($row->response_payload ?? ''),
                'cancel_response' => is_array($cancel_decoded) ? $cancel_decoded : (string) ($row->cancel_response ?? ''),
            ],
        ]);
    }

    public function reprint(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $billNo = trim((string) $request->query('bill_no', ''));
        if ($billNo === '') {
            return response()->json(['ok' => false, 'message' => 'Bill number required.'], 422);
        }

        if (!$this->hasTable('e_invoices')) {
            return response()->json(['ok' => false, 'message' => 'E-invoice table missing.'], 500);
        }

        $row = DB::table('e_invoices')->where('bill_no', $billNo)->orderByDesc('id')->first();
        if (!$row || trim((string) $row->irn) === '') {
            return response()->json(['ok' => false, 'message' => 'E-invoice not generated for this bill.'], 404);
        }

        return response()->json([
            'ok' => true,
            'url' => url('/e-invoice-print?bill_no=' . urlencode($billNo)),
        ]);
    }

    public function lookupIrn(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $irn = strtolower(preg_replace('/\s+/', '', trim((string) $request->query('irn', ''))));
        if (!preg_match('/^[a-f0-9]{64}$/', $irn)) {
            return response()->json(['ok' => false, 'message' => 'Enter a valid 64-character IRN.'], 422);
        }

        try {
            $lookup = $this->fetchMastersIndiaEInvoiceByIrn($irn);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'IRN lookup failed: ' . $e->getMessage()], 502);
        }

        $decoded = $lookup['response'];
        $message = is_array($decoded) ? data_get($decoded, 'results.message') : null;
        $status = is_array($decoded) ? (string) data_get($decoded, 'results.status', '') : '';
        $code = is_array($decoded) ? (string) data_get($decoded, 'results.code', '') : '';
        $error = is_array($decoded) ? (string) data_get($decoded, 'results.errorMessage', '') : '';
        if ($status !== 'Success' || !is_array($message)) {
            return response()->json([
                'ok' => false,
                'message' => $error !== '' ? $error : 'Masters India did not return IRN details.',
                'provider_status' => $lookup['http_status'],
                'provider_response' => $decoded,
            ], 422);
        }

        $jsonPath = $this->saveIrnLookupResponse($irn, $decoded);

        return response()->json([
            'ok' => true,
            'message' => 'IRN details fetched successfully.',
            'provider_status' => $lookup['http_status'],
            'json_saved' => $jsonPath,
            'data' => [
                'irn' => trim((string) ($message['Irn'] ?? $irn)),
                'ack_no' => trim((string) ($message['AckNo'] ?? '')),
                'ack_date' => trim((string) ($message['AckDt'] ?? '')),
                'status' => trim((string) ($message['Status'] ?? '')),
                'eway_bill_no' => trim((string) ($message['EwbNo'] ?? '')),
                'eway_bill_date' => trim((string) ($message['EwbDt'] ?? '')),
                'eway_valid_till' => trim((string) ($message['EwbValidTill'] ?? '')),
                'signed_invoice' => trim((string) ($message['SignedInvoice'] ?? '')),
                'signed_qr_code' => trim((string) ($message['SignedQRCode'] ?? '')),
                'response_payload' => $decoded,
                'request_payload' => $lookup['request'],
                'result_code' => $code,
            ],
        ]);
    }

    public function pdf(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $billNo = trim((string) $request->query('bill_no', ''));
        if ($billNo === '' || !$this->hasTable('e_invoices')) {
            abort(404, 'E-invoice not found.');
        }

        $row = DB::table('e_invoices')->where('bill_no', $billNo)->orderByDesc('id')->first();
        if (!$row) {
            abort(404, 'E-invoice not found.');
        }

        $response = json_decode((string) ($row->response_payload ?? ''), true);
        $pdfUrl = trim((string) data_get($response, 'results.message.EinvoicePdf', ''));
        if ($pdfUrl === '') {
            abort(404, 'Provider did not return a PDF URL for this e-invoice.');
        }

        try {
            $resp = Http::timeout(60)->get($pdfUrl);
        } catch (\Throwable $e) {
            abort(502, 'Unable to fetch PDF from provider: ' . $e->getMessage());
        }

        if (!$resp->successful()) {
            abort($resp->status() ?: 502, 'Provider PDF fetch failed.');
        }

        $body = $resp->body();
        $contentType = $resp->header('Content-Type') ?: 'application/pdf';
        if (stripos($contentType, 'pdf') === false && stripos($contentType, 'octet-stream') === false) {
            $contentType = 'application/pdf';
        }
        $safeName = preg_replace('/[^A-Z0-9_.-]+/i', '_', $billNo) ?: 'einvoice';
        $filename = 'einvoice-' . $safeName . '.pdf';
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($body, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) strlen($body),
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function printView(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $billNo = trim((string) $request->query('bill_no', ''));
        if ($billNo === '' || !$this->hasTable('e_invoices')) {
            abort(404, 'E-invoice not found.');
        }

        $row = DB::table('e_invoices')->where('bill_no', $billNo)->orderByDesc('id')->first();
        if (!$row) {
            abort(404, 'E-invoice not found.');
        }

        $bill = null;
        $items = [];
        if ($this->hasTable('salesm')) {
            $bill = DB::table('salesm')->where('billno', $billNo)->first();
            if ($bill && $this->hasTable('salesd')) {
                $items = DB::table('salesd')->where('slno', $bill->slno)->orderBy('sno')->get()->all();
            }
        }

        $settings = $this->loadSettingsPayload();
        $company = (array) ($settings['Company'] ?? []);
        $apiSettings = (array) ($settings['API'] ?? []);
        $response = json_decode((string) ($row->response_payload ?? ''), true);
        $request_payload = json_decode((string) ($row->request_payload ?? ''), true);
        $msg = is_array($response) ? (array) data_get($response, 'results.message', []) : [];

        return view('e-invoice-register.print', [
            'einv' => $row,
            'bill' => $bill,
            'items' => $items,
            'company' => $company,
            'api_settings' => $apiSettings,
            'request_payload' => is_array($request_payload) ? $request_payload : [],
            'qr_url' => trim((string) ($msg['QRCodeUrl'] ?? '')),
            'einv_pdf_url' => trim((string) ($msg['EinvoicePdf'] ?? '')),
            'signed_invoice' => trim((string) ($msg['SignedInvoice'] ?? '')),
            'signed_qr' => trim((string) ($row->signed_qr_code ?? '')),
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $payload = $request->validate([
            'bill_no' => 'required|string|max:40',
            'reason' => 'required|string|max:255',
            'remark' => 'nullable|string|max:255',
        ]);

        $billNo = trim((string) $payload['bill_no']);
        $reason = trim((string) $payload['reason']);
        $remark = trim((string) ($payload['remark'] ?? ''));

        if (!$this->hasTable('e_invoices')) {
            return response()->json(['ok' => false, 'message' => 'E-invoice table is not available.'], 500);
        }

        $invoice = DB::table('e_invoices')->where('bill_no', $billNo)->orderByDesc('id')->first();
        if (!$invoice) {
            return response()->json(['ok' => false, 'message' => 'E-invoice not found for this bill.'], 404);
        }
        if (strtolower((string) ($invoice->status ?? '')) === 'cancelled') {
            return response()->json(['ok' => false, 'message' => 'E-invoice is already cancelled.'], 422);
        }
        $irn = trim((string) ($invoice->irn ?? ''));
        if ($irn === '') {
            return response()->json(['ok' => false, 'message' => 'IRN not available — nothing to cancel.'], 422);
        }

        $settings = $this->loadSettingsPayload();
        $apiSettings = (array) ($settings['API'] ?? []);
        $provider = strtolower(preg_replace('/[^a-z0-9]+/', '', trim((string) ($apiSettings['EINVOICEPROVIDER'] ?? ''))));
        $isMastersIndia = $provider === 'mastersindia'
            || str_contains(strtolower((string) ($apiSettings['EINVOICEAPIURL'] ?? '')), 'mastersindia.co');

        $cancelUrl = trim((string) ($apiSettings['EINVOICECANCELURL'] ?? ''));
        if ($cancelUrl === '' && $isMastersIndia) {
            $cancelUrl = 'https://prod-api.mastersindia.co/api/v1/cancel-einvoice/';
        }
        if ($cancelUrl === '') {
            return response()->json(['ok' => false, 'message' => 'Set EInvoice cancel URL in Application Settings before cancelling.'], 422);
        }

        $username = trim((string) ($apiSettings['EINVOICEUSERNAME'] ?? ''));
        if ($username === '' && $isMastersIndia) {
            $username = (string) env('MASTERSINDIA_USERNAME', '');
        }
        $password = (string) ($apiSettings['EINVOICEPASSWORD'] ?? '');
        if ($password === '' && $isMastersIndia) {
            $password = (string) env('MASTERSINDIA_PASSWORD', '');
        }
        $reasonCode = $this->mapCancelReasonCode($reason);

        $cancelPayload = [
            'irn' => $irn,
            'CnlRsn' => $reasonCode,
            'cancel_reason' => $reasonCode,
            'CnlRem' => $remark !== '' ? $remark : $reason,
            'cancel_remarks' => $remark !== '' ? $remark : $reason,
        ];

        $decoded = null;
        $providerStatus = 0;
        $responseBody = '';
        try {
            if ($isMastersIndia) {
                $token = $this->fetchMastersIndiaToken($apiSettings, $username, $password);
                $response = $this->mastersIndiaHttp(20)
                    ->acceptJson()
                    ->asJson()
                    ->withHeaders(['Authorization' => 'JWT ' . $token])
                    ->post($cancelUrl, $cancelPayload);
            } else {
                $response = Http::timeout(45)
                    ->acceptJson()
                    ->asJson()
                    ->withBasicAuth($username, $password)
                    ->post($cancelUrl, $cancelPayload);
            }
            $providerStatus = $response->status();
            $responseBody = $response->body();
            $decoded = $response->json();
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Cancel request failed: ' . $e->getMessage(),
            ], 502);
        }

        if ($providerStatus < 200 || $providerStatus >= 300) {
            $msg = is_array($decoded)
                ? (string) ($decoded['message'] ?? $decoded['msg'] ?? data_get($decoded, 'results.errorMessage') ?? 'Cancel request rejected.')
                : trim($responseBody);
            return response()->json([
                'ok' => false,
                'message' => $msg !== '' ? $msg : 'Cancel request rejected.',
                'provider_status' => $providerStatus,
                'provider_response' => $decoded ?? $responseBody,
            ], 422);
        }

        $userCode = (string) $request->session()->get('user_code', '');
        $now = now();
        $responseText = is_array($decoded)
            ? (json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '')
            : (string) $responseBody;

        DB::transaction(function () use ($invoice, $billNo, $reason, $remark, $userCode, $now, $responseText) {
            DB::table('e_invoices')->where('id', $invoice->id)->update([
                'status' => 'cancelled',
                'cancel_reason' => mb_substr($reason, 0, 255),
                'cancel_remark' => mb_substr($remark, 0, 255),
                'cancelled_by' => mb_substr($userCode, 0, 20),
                'cancelled_at' => $now,
                'cancel_response' => mb_substr($responseText, 0, 65000),
                'updated_at' => $now,
            ]);

            if ($this->hasTable('sales_bills')) {
                $this->updateCols('sales_bills', 'bill_no', $billNo, [
                    'e_invoice_status' => 'cancelled',
                    'cancel_reason' => mb_substr($reason, 0, 255),
                    'cancel_remark' => mb_substr($remark, 0, 255),
                    'cancelled_at' => $now,
                ]);
            }
            if ($this->hasTable('salesm')) {
                $this->updateCols('salesm', 'billno', $billNo, [
                    'einvoice_status' => 'cancelled',
                    'cancel_reason' => mb_substr($reason, 0, 255),
                    'cancel_remark' => mb_substr($remark, 0, 255),
                    'cancelled_at' => $now,
                ]);
            }
        });

        $this->logDelpart($request, 'E-Invoice(' . $billNo . ') Cancelled — ' . $reason, ['utype' => 'E', 'ttype' => 'T']);

        return response()->json([
            'ok' => true,
            'message' => 'E-invoice cancelled successfully.',
            'provider_status' => $providerStatus,
            'provider_response' => $decoded ?? $responseBody,
        ]);
    }

    private function updateCols(string $table, string $whereCol, string $whereVal, array $updates): void
    {
        $cols = $this->columnList($table);
        $payload = [];
        foreach ($updates as $col => $val) {
            if (in_array(strtolower($col), $cols, true)) {
                $payload[$col] = $val;
            }
        }
        if ($payload === [] || !in_array(strtolower($whereCol), $cols, true)) return;
        DB::table($table)->where($whereCol, $whereVal)->update($payload);
    }

    private function parseDate(string $value, bool $nullable = false): ?string
    {
        $value = trim($value);
        if ($value === '') return $nullable ? null : date('Y-m-d');
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        $t = strtotime($value);
        return $t ? date('Y-m-d', $t) : ($nullable ? null : date('Y-m-d'));
    }

    private function mapCancelReasonCode(string $reason): string
    {
        $r = strtolower($reason);
        if (str_contains($r, 'duplicate')) return '1';
        if (str_contains($r, 'data') && str_contains($r, 'entry')) return '2';
        if (str_contains($r, 'order') && str_contains($r, 'cancel')) return '3';
        return '4';
    }

    private function loadSettingsPayload(): array
    {
        $iniPath = storage_path('app/software-settings.ini');
        if (File::exists($iniPath)) {
            return $this->parseLegacyIni((string) File::get($iniPath));
        }
        return ['Software' => [], 'API' => []];
    }

    private function parseLegacyIni(string $raw): array
    {
        $result = [];
        $section = '';
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#')) continue;
            if (preg_match('/^\[(.+)\]$/', $line, $m) === 1) {
                $section = trim($m[1]);
                if ($section !== '' && !isset($result[$section])) $result[$section] = [];
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) continue;
            $k = trim(substr($line, 0, $eq));
            $v = trim(substr($line, $eq + 1));
            if ($k === '') continue;
            if ($section === '') {
                $section = 'Software';
                if (!isset($result[$section])) $result[$section] = [];
            }
            $result[$section][$k] = $v;
        }
        return $result + ['Software' => [], 'API' => []];
    }

    private function fetchMastersIndiaToken(array $apiSettings, string $username, string $password): string
    {
        $url = trim((string) ($apiSettings['EINVOICETOKENURL'] ?? '')) ?: 'https://prod-api.mastersindia.co/api/v1/token-auth/';
        $response = $this->mastersIndiaHttp(15)->acceptJson()->asJson()->post($url, [
            'username' => $username,
            'password' => $password,
        ]);
        $decoded = $response->json();
        $token = is_array($decoded) ? trim((string) ($decoded['token'] ?? '')) : '';
        if (!$response->successful() || $token === '') {
            $msg = is_array($decoded)
                ? (string) ($decoded['error'] ?? $decoded['message'] ?? 'Unable to login to Masters India.')
                : trim($response->body());
            throw new \RuntimeException($msg !== '' ? $msg : 'Unable to login to Masters India.');
        }
        return $token;
    }

    private function fetchMastersIndiaEInvoiceByIrn(string $irn): array
    {
        $settings = $this->loadSettingsPayload();
        $apiSettings = (array) ($settings['API'] ?? []);
        $company = (array) ($settings['Company'] ?? []);

        $username = trim((string) ($apiSettings['EINVOICEUSERNAME'] ?? ''));
        if ($username === '') {
            $username = (string) env('MASTERSINDIA_USERNAME', '');
        }

        $password = trim((string) ($apiSettings['EINVOICEPASSWORD'] ?? ''));
        if ($password === '') {
            $password = (string) env('MASTERSINDIA_PASSWORD', '');
        }

        $gstin = strtoupper(trim((string) (
            $apiSettings['EINVOICEUSERGSTIN']
            ?? $apiSettings['EINVOICESELLERGSTIN']
            ?? $company['KGST']
            ?? ''
        )));

        if ($username === '' || $password === '') {
            throw new \RuntimeException('Set EInvoice username and password in Application Settings.');
        }
        if (!preg_match('/^\d{2}[A-Z0-9]{13}$/', $gstin)) {
            throw new \RuntimeException('Set a valid EInvoice GSTIN in Application Settings.');
        }

        $token = $this->fetchMastersIndiaToken($apiSettings, $username, $password);
        $url = trim((string) ($apiSettings['EINVOICELOOKUPURL'] ?? ''));
        if ($url === '') {
            $url = 'https://prod-api.mastersindia.co/api/v1/get-einvoice/';
        }

        $requestPayload = [
            'gstin' => $gstin,
            'irn' => $irn,
            'username' => $username,
            'password' => $password,
        ];

        $response = $this->mastersIndiaHttp(30)
            ->acceptJson()
            ->withHeaders(['Authorization' => 'JWT ' . $token])
            ->get($url, $requestPayload);

        $decoded = null;
        try {
            $decoded = $response->json();
        } catch (\Throwable) {
            $decoded = $response->body();
        }

        $safeRequest = $requestPayload;
        $safeRequest['password'] = $safeRequest['password'] !== '' ? '********' : '';

        return [
            'http_status' => $response->status(),
            'request' => $safeRequest,
            'response' => $decoded,
        ];
    }

    private function saveIrnLookupResponse(string $irn, array $decoded): string
    {
        $dir = storage_path('app/einvoice-reprints');
        File::ensureDirectoryExists($dir);
        $path = $dir . DIRECTORY_SEPARATOR . 'irn-' . substr($irn, 0, 12) . '.json';
        File::put($path, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    private function mastersIndiaHttp(int $timeout = 20): \Illuminate\Http\Client\PendingRequest
    {
        $curlOptions = [];
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        return Http::connectTimeout(5)
            ->timeout($timeout)
            ->retry(0, 0)
            ->withOptions($curlOptions !== [] ? ['curl' => $curlOptions] : []);
    }
}
