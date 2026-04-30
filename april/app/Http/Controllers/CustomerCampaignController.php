<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CustomerCampaignController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('customer-campaign.index', [
            'payload' => $this->buildPayload(),
        ]);
    }

    public function recipients(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'ok' => true,
            'results' => $this->loadRecipients(
                (string) $request->query('campaign', 'all-customers'),
                (string) $request->query('on_date', now()->format('Y-m-d')),
                (string) $request->query('channel', 'whatsapp'),
                (string) $request->query('search', '')
            ),
        ]);
    }

    private function buildPayload(): array
    {
        return [
            'shop' => $this->shopData(),
            'rates' => $this->rateData(),
            'templates' => $this->templates(),
            'links' => $this->linkSettings(),
            'today' => now()->format('Y-m-d'),
        ];
    }

    private function loadRecipients(string $campaign, string $onDate, string $channel, string $search): array
    {
        if (!$this->hasTable('clients')) {
            return [];
        }

        $clientCols = array_flip($this->columnList('clients'));
        $hasAdvanced = $this->hasTable('clients_advanced');
        $advancedCols = $hasAdvanced ? array_flip($this->columnList('clients_advanced')) : [];

        $query = DB::table('clients as c')
            ->select([
                'c.code',
                'c.name',
                'c.mobile',
                'c.telephone',
                'c.email',
                'c.addr1',
                'c.city',
            ])
            ->where('c.ctype', 'C');

        if (isset($clientCols['removed'])) {
            $query->where(function ($q) {
                $q->whereNull('c.removed')->orWhere('c.removed', '<>', 1);
            });
        }

        if ($hasAdvanced) {
            $query->leftJoin('clients_advanced as a', 'a.code', '=', 'c.code');

            foreach (['dtbirthday', 'dtengagement', 'dtmarriage', 'grateinform'] as $column) {
                if (isset($advancedCols[$column])) {
                    $query->addSelect('a.' . $column);
                }
            }
        }

        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('c.code', 'like', $like)
                    ->orWhere('c.name', 'like', $like)
                    ->orWhere('c.mobile', 'like', $like)
                    ->orWhere('c.telephone', 'like', $like)
                    ->orWhere('c.email', 'like', $like)
                    ->orWhere('c.city', 'like', $like);
            });
        }

        $rows = $query->orderBy('c.name')->limit(1000)->get();
        $targetMd = $this->monthDay($onDate);
        $campaign = strtolower(trim($campaign));
        $channel = strtolower(trim($channel));

        $results = [];
        foreach ($rows as $row) {
            $mobile = trim((string) ($row->mobile ?? ''));
            $telephone = trim((string) ($row->telephone ?? ''));
            $email = trim((string) ($row->email ?? ''));
            $contact = $mobile !== '' ? $mobile : $telephone;

            if (in_array($channel, ['whatsapp', 'sms'], true) && $contact === '') {
                continue;
            }
            if ($channel === 'email' && $email === '') {
                continue;
            }

            $birthday = (string) ($row->dtbirthday ?? '');
            $engagement = (string) ($row->dtengagement ?? '');
            $marriage = (string) ($row->dtmarriage ?? '');
            $goldRateInform = strtoupper(trim((string) ($row->grateinform ?? 'N'))) === 'Y';

            if ($campaign === 'birthday' && ($targetMd === '' || $this->monthDay($birthday) !== $targetMd)) {
                continue;
            }
            if ($campaign === 'engagement' && ($targetMd === '' || $this->monthDay($engagement) !== $targetMd)) {
                continue;
            }
            if ($campaign === 'wedding' && ($targetMd === '' || $this->monthDay($marriage) !== $targetMd)) {
                continue;
            }
            if ($campaign === 'gold-rate' && $hasAdvanced && !$goldRateInform) {
                continue;
            }
            if (!in_array($campaign, ['all-customers', 'birthday', 'engagement', 'wedding', 'gold-rate', 'scheme', 'registration', 'feedback', 'offer'], true)) {
                continue;
            }

            $results[] = [
                'code' => trim((string) ($row->code ?? '')),
                'name' => trim((string) ($row->name ?? '')),
                'mobile' => $mobile,
                'telephone' => $telephone,
                'email' => $email,
                'addr1' => trim((string) ($row->addr1 ?? '')),
                'city' => trim((string) ($row->city ?? '')),
                'dtbirthday' => $birthday,
                'dtengagement' => $engagement,
                'dtmarriage' => $marriage,
                'grateinform' => $goldRateInform ? 'Y' : 'N',
            ];
        }

        return $results;
    }

    private function shopData(): array
    {
        $settings = $this->generalValues(['SHOPNM', 'SHOPADDR', 'SHOPPHONE', 'SHOPLOGO']);

        return [
            'name' => trim((string) ($settings['SHOPNM'] ?? '')),
            'address' => trim((string) ($settings['SHOPADDR'] ?? '')),
            'phone' => trim((string) ($settings['SHOPPHONE'] ?? '')),
            'logo_url' => $this->logoUrl((string) ($settings['SHOPLOGO'] ?? '')),
        ];
    }

    private function rateData(): array
    {
        $rates = [];
        if ($this->hasTable('generald')) {
            try {
                $rows = DB::table('generald')
                    ->select(['code', 'cvalue'])
                    ->whereIn('code', ['GRATE', 'G18RATE', 'SRATE'])
                    ->get();
                foreach ($rows as $row) {
                    $rates[(string) $row->code] = (float) ($row->cvalue ?? 0);
                }
            } catch (\Throwable) {
                $rates = [];
            }
        }

        return [
            'gold_22k' => (float) ($rates['GRATE'] ?? 0),
            'gold_18k' => (float) ($rates['G18RATE'] ?? 0),
            'silver' => (float) ($rates['SRATE'] ?? 0),
        ];
    }

    private function linkSettings(): array
    {
        $settings = $this->generalValues(['SCHEMELINK', 'REGISTRATIONLINK', 'FEEDBACKLINK', 'OFFERTITLE']);

        return [
            'scheme_link' => trim((string) ($settings['SCHEMELINK'] ?? '')),
            'registration_link' => trim((string) ($settings['REGISTRATIONLINK'] ?? '')),
            'feedback_link' => trim((string) ($settings['FEEDBACKLINK'] ?? '')),
            'offer_title' => trim((string) ($settings['OFFERTITLE'] ?? '')),
        ];
    }

    private function generalValues(array $codes): array
    {
        $values = [];
        if (!$this->hasTable('generals')) {
            return $values;
        }

        try {
            $rows = DB::table('generals')
                ->select(['code', 'cvalue'])
                ->whereIn('code', $codes)
                ->get();

            foreach ($rows as $row) {
                $values[(string) $row->code] = (string) ($row->cvalue ?? '');
            }
        } catch (\Throwable) {
            return [];
        }

        return $values;
    }

    private function logoUrl(string $filename): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            return '';
        }

        $path = public_path('uploads/logo/' . $filename);
        return file_exists($path) ? url('uploads/logo/' . $filename) : '';
    }

    private function monthDay(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            return (new \DateTime($value))->format('m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    private function templates(): array
    {
        return [
            'all-customers' => [
                ['id' => 'all-1', 'name' => 'General Greeting', 'subject' => 'Greetings from {{shop_name}}', 'body' => "Dear {{name}},\nWarm greetings from {{shop_name}}.\nWe invite you to visit us for our latest collections and current live rates.\n{{shop_address}}\n{{shop_phone}}"],
                ['id' => 'all-2', 'name' => 'Visit Our Showroom', 'subject' => 'A Special Invite for {{name}}', 'body' => "Hello {{name}},\nThank you for being with {{shop_name}}.\nWe would be happy to welcome you to our showroom and assist you with your next jewellery purchase.\nCall {{shop_phone}}."],
            ],
            'birthday' => [
                ['id' => 'birthday-1', 'name' => 'Birthday Warm Wish', 'subject' => 'Happy Birthday {{name}}', 'body' => "Dear {{name}},\nWishing you a wonderful birthday from {{shop_name}}. May your day shine as bright as gold.\nVisit us for today’s special collections.\n{{shop_phone}}"],
                ['id' => 'birthday-2', 'name' => 'Birthday Offer', 'subject' => 'Birthday Offer for {{name}}', 'body' => "Happy Birthday {{name}}.\nCelebrate with {{shop_name}} and enjoy our latest jewellery choices and festive service.\n{{offer_title}}\n{{shop_address}}"],
            ],
            'engagement' => [
                ['id' => 'engagement-1', 'name' => 'Engagement Greeting', 'subject' => 'Engagement Wishes', 'body' => "Dear {{name}},\nWarm wishes on your engagement anniversary from {{shop_name}}.\nWe would love to help you mark the occasion with something special.\nCall {{shop_phone}}."],
            ],
            'wedding' => [
                ['id' => 'wedding-1', 'name' => 'Wedding Anniversary Wish', 'subject' => 'Happy Wedding Anniversary', 'body' => "Dear {{name}},\nHappy wedding anniversary from {{shop_name}}.\nMay your day be filled with happiness, love, and beautiful memories.\nVisit us at {{shop_address}}."],
            ],
            'gold-rate' => [
                ['id' => 'gold-rate-1', 'name' => 'Daily Gold Rate', 'subject' => 'Today Gold Rate', 'body' => "Dear {{name}},\nToday’s live rate from {{shop_name}}:\n22K: {{gold_22k}}\n18K: {{gold_18k}}\nSilver: {{silver}}\nFor booking and enquiries contact {{shop_phone}}."],
                ['id' => 'gold-rate-2', 'name' => 'Gold Rate + Visit', 'subject' => 'Live Gold Rate Update', 'body' => "{{name}}, today’s rate update from {{shop_name}}:\n22K {{gold_22k}} | 18K {{gold_18k}} | Silver {{silver}}\nCome visit us at {{shop_address}}.\n{{shop_phone}}"],
            ],
            'scheme' => [
                ['id' => 'scheme-1', 'name' => 'Scheme Invite', 'subject' => 'Join Our Scheme', 'body' => "Dear {{name}},\nJoin our savings scheme with {{shop_name}} and plan your next purchase smartly.\nRegister here: {{scheme_link}}\nFor details call {{shop_phone}}."],
            ],
            'registration' => [
                ['id' => 'registration-1', 'name' => 'Customer Registration', 'subject' => 'Complete Registration', 'body' => "Dear {{name}},\nComplete your customer registration with {{shop_name}} using the link below:\n{{registration_link}}\nThank you for choosing us."],
            ],
            'feedback' => [
                ['id' => 'feedback-1', 'name' => 'Feedback Request', 'subject' => 'Share Your Feedback', 'body' => "Dear {{name}},\nThank you for visiting {{shop_name}}.\nPlease share your feedback here:\n{{feedback_link}}\nYour opinion helps us serve you better."],
            ],
            'offer' => [
                ['id' => 'offer-1', 'name' => 'Festive Offer', 'subject' => '{{offer_title}}', 'body' => "Dear {{name}},\n{{offer_title}}\nVisit {{shop_name}} to explore our latest collections.\n{{shop_address}}\n{{shop_phone}}"],
                ['id' => 'offer-2', 'name' => 'Limited Period Offer', 'subject' => 'Special Offer for You', 'body' => "Hello {{name}},\nA limited period offer is now live at {{shop_name}}.\n{{offer_title}}\nContact us on {{shop_phone}}."],
            ],
        ];
    }
}
