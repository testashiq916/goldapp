<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppService
{
    private array $settings;

    public function __construct()
    {
        $this->settings = $this->loadSettings();
    }

    // ── Public API ──────────────────────────────────────────────────────────

    public function sendWhatsApp(string $mobile, string $message, string $type = 'general'): array
    {
        $mobile = $this->normalizeMobile($mobile);
        if ($mobile === '') {
            return ['ok' => false, 'message' => 'Invalid mobile number'];
        }

        $provider = strtolower(trim($this->settings['WA_PROVIDER'] ?? 'fast2sms'));
        try {
            $result = match ($provider) {
                'wati'      => $this->sendViaWati($mobile, $message),
                'twilio'    => $this->sendViaTwilio($mobile, $message),
                'msg91'     => $this->sendViaMSG91($mobile, $message, 'whatsapp'),
                'webhook'   => $this->sendViaWebhook($mobile, $message, 'whatsapp'),
                default     => $this->sendViaFast2SMS($mobile, $message, 'whatsapp'),
            };
        } catch (Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        $this->log('whatsapp', $mobile, '', $type, $message, $result);
        return $result;
    }

    public function sendSMS(string $mobile, string $message, string $type = 'general'): array
    {
        $mobile = $this->normalizeMobile($mobile);
        if ($mobile === '') {
            return ['ok' => false, 'message' => 'Invalid mobile number'];
        }

        $provider = strtolower(trim($this->settings['SMS_PROVIDER'] ?? 'fast2sms'));
        try {
            $result = match ($provider) {
                'msg91'   => $this->sendViaMSG91($mobile, $message, 'sms'),
                'webhook' => $this->sendViaWebhook($mobile, $message, 'sms'),
                default   => $this->sendViaFast2SMS($mobile, $message, 'sms'),
            };
        } catch (Throwable $e) {
            $result = ['ok' => false, 'message' => $e->getMessage()];
        }

        $this->log('sms', $mobile, '', $type, $message, $result);
        return $result;
    }

    public function buildMessage(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $template;
    }

    public function isEnabled(string $channel = 'whatsapp'): bool
    {
        $key = strtoupper($channel) === 'SMS' ? 'SMS_ENABLED' : 'WA_ENABLED';
        return strtoupper(trim($this->settings[$key] ?? 'N')) === 'Y';
    }

    // ── Provider implementations ─────────────────────────────────────────

    private function sendViaFast2SMS(string $mobile, string $message, string $channel): array
    {
        $apiKey = trim($this->settings['FAST2SMS_KEY'] ?? '');
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'Fast2SMS API key not configured'];
        }

        $route = $channel === 'whatsapp' ? 'whatsapp' : 'v3/bulk';
        $response = Http::withHeaders(['authorization' => $apiKey])
            ->timeout(15)
            ->post('https://www.fast2sms.com/dev/' . $route, [
                'message'    => $message,
                'language'   => 'english',
                'route'      => 'q',
                'numbers'    => $mobile,
            ]);

        $body = $response->json();
        $ok   = ($body['return'] ?? false) === true;
        return ['ok' => $ok, 'message' => $body['message'][0] ?? 'Unknown response', 'raw' => $body];
    }

    private function sendViaWati(string $mobile, string $message): array
    {
        $apiUrl   = rtrim(trim($this->settings['WATI_API_URL'] ?? ''), '/');
        $apiToken = trim($this->settings['WATI_TOKEN'] ?? '');
        if ($apiUrl === '' || $apiToken === '') {
            return ['ok' => false, 'message' => 'WATI API URL or token not configured'];
        }

        $response = Http::withToken($apiToken)
            ->timeout(15)
            ->post($apiUrl . '/api/v1/sendSessionMessage/' . $mobile, [
                'messageText' => $message,
            ]);

        $body = $response->json();
        $ok   = ($body['result'] ?? false) === true;
        return ['ok' => $ok, 'message' => $body['info'] ?? 'Sent', 'raw' => $body];
    }

    private function sendViaTwilio(string $mobile, string $message): array
    {
        $sid    = trim($this->settings['TWILIO_SID'] ?? '');
        $token  = trim($this->settings['TWILIO_TOKEN'] ?? '');
        $from   = trim($this->settings['TWILIO_FROM'] ?? '');
        if ($sid === '' || $token === '' || $from === '') {
            return ['ok' => false, 'message' => 'Twilio credentials not configured'];
        }

        $response = Http::withBasicAuth($sid, $token)
            ->timeout(15)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => 'whatsapp:' . $from,
                'To'   => 'whatsapp:+91' . $mobile,
                'Body' => $message,
            ]);

        $body = $response->json();
        $ok   = isset($body['sid']);
        return ['ok' => $ok, 'message' => $body['sid'] ?? ($body['message'] ?? 'Unknown'), 'raw' => $body];
    }

    private function sendViaMSG91(string $mobile, string $message, string $channel): array
    {
        $authKey  = trim($this->settings['MSG91_KEY'] ?? '');
        $senderId = trim($this->settings['MSG91_SENDER'] ?? '');
        if ($authKey === '') {
            return ['ok' => false, 'message' => 'MSG91 auth key not configured'];
        }

        $response = Http::withHeaders(['authkey' => $authKey])
            ->timeout(15)
            ->post('https://api.msg91.com/api/v5/flow/', [
                'template_id' => $this->settings['MSG91_TEMPLATE'] ?? '',
                'short_url'   => '0',
                'mobiles'     => '91' . $mobile,
                'VAR1'        => $message,
            ]);

        $body = $response->json();
        $ok   = ($body['type'] ?? '') === 'success';
        return ['ok' => $ok, 'message' => $body['message'] ?? 'Sent', 'raw' => $body];
    }

    private function sendViaWebhook(string $mobile, string $message, string $channel): array
    {
        $url = trim($this->settings['WEBHOOK_URL'] ?? '');
        if ($url === '') {
            return ['ok' => false, 'message' => 'Webhook URL not configured'];
        }

        $response = Http::timeout(15)->post($url, [
            'channel' => $channel,
            'mobile'  => $mobile,
            'message' => $message,
        ]);

        return ['ok' => $response->successful(), 'message' => $response->body()];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile);
        // Strip country code prefix
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 13 && str_starts_with($digits, '091')) {
            $digits = substr($digits, 3);
        }
        return strlen($digits) === 10 ? $digits : '';
    }

    private function log(string $channel, string $recipient, string $name, string $type, string $message, array $result): void
    {
        try {
            DB::table('message_log')->insert([
                'channel'        => $channel,
                'recipient'      => $recipient,
                'recipient_name' => $name,
                'message_type'   => $type,
                'message'        => $message,
                'status'         => ($result['ok'] ?? false) ? 'sent' : 'failed',
                'provider'       => $this->settings['WA_PROVIDER'] ?? 'fast2sms',
                'api_response'   => json_encode($result['raw'] ?? $result['message'] ?? ''),
                'sent_at'        => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('WhatsAppService log error: ' . $e->getMessage());
        }
    }

    private function loadSettings(): array
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
}
