<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class WhatsAppGatewayController extends Controller
{
    // ── Pages ────────────────────────────────────────────────────────────

    public function settings(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $settings = $this->loadWaSettings();
        return view('whatsapp.settings', compact('settings'));
    }

    public function log(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('whatsapp.log');
    }

    // ── API ───────────────────────────────────────────────────────────────

    public function saveSettings(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $keys = [
            'WA_ENABLED', 'WA_PROVIDER',
            'SMS_ENABLED', 'SMS_PROVIDER',
            'FAST2SMS_KEY',
            'WATI_API_URL', 'WATI_TOKEN',
            'TWILIO_SID', 'TWILIO_TOKEN', 'TWILIO_FROM',
            'MSG91_KEY', 'MSG91_SENDER', 'MSG91_TEMPLATE',
            'WEBHOOK_URL',
            'WA_AUTO_SALE', 'WA_AUTO_PAYMENT_DUE', 'WA_AUTO_REPAIR_READY',
            'WA_AUTO_SCHEME', 'WA_AUTO_GOLD_RATE',
        ];

        try {
            foreach ($keys as $key) {
                $value = (string) ($request->input($key) ?? '');
                $this->upsertGeneral($key, $value);
            }
            return response()->json(['ok' => true, 'message' => 'Settings saved']);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function testSend(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $mobile  = trim((string) $request->input('mobile', ''));
        $message = trim((string) $request->input('message', 'Test message from GoldApp'));
        $channel = trim((string) $request->input('channel', 'whatsapp'));

        if ($mobile === '') {
            return response()->json(['ok' => false, 'message' => 'Mobile number required']);
        }

        $svc = new WhatsAppService();
        $result = $channel === 'sms'
            ? $svc->sendSMS($mobile, $message, 'test')
            : $svc->sendWhatsApp($mobile, $message, 'test');

        return response()->json($result);
    }

    public function logData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $rows = DB::table('message_log')
                ->orderByDesc('sent_at')
                ->limit(200)
                ->get();
            return response()->json(['ok' => true, 'rows' => $rows]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function bulkSend(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $recipients = $request->input('recipients', []);
        $message    = trim((string) $request->input('message', ''));
        $channel    = trim((string) $request->input('channel', 'whatsapp'));

        if (empty($recipients) || $message === '') {
            return response()->json(['ok' => false, 'message' => 'Recipients and message required']);
        }

        $svc     = new WhatsAppService();
        $sent    = 0;
        $failed  = 0;

        foreach ($recipients as $r) {
            $mobile = trim((string) ($r['mobile'] ?? ''));
            if ($mobile === '') {
                continue;
            }
            $result = $channel === 'sms'
                ? $svc->sendSMS($mobile, $message, 'campaign')
                : $svc->sendWhatsApp($mobile, $message, 'campaign');

            ($result['ok'] ?? false) ? $sent++ : $failed++;
        }

        return response()->json(['ok' => true, 'sent' => $sent, 'failed' => $failed]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function loadWaSettings(): array
    {
        $settings = [];
        try {
            $rows = DB::table('generals')
                ->whereRaw("code LIKE 'WA_%' OR code LIKE 'SMS_%' OR code LIKE 'WATI_%' OR code LIKE 'TWILIO_%' OR code LIKE 'MSG91_%' OR code LIKE 'FAST2SMS_%' OR code = 'WEBHOOK_URL'")
                ->get(['code', 'cvalue']);
            foreach ($rows as $row) {
                $settings[(string) $row->code] = (string) ($row->cvalue ?? '');
            }
        } catch (Throwable) {
        }
        return $settings;
    }

    private function upsertGeneral(string $code, string $value): void
    {
        $exists = DB::table('generals')->where('code', $code)->exists();
        if ($exists) {
            DB::table('generals')->where('code', $code)->update(['cvalue' => $value]);
        } else {
            DB::table('generals')->insert(['code' => $code, 'cvalue' => $value]);
        }
    }
}
