<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PhoneBookController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('phone-book.index', [
            'payload' => $this->buildPayload(),
        ]);
    }

    public function contacts(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'ok' => true,
            'filters' => [
                'type' => strtoupper(trim((string) $request->query('type', 'C'))),
                'q' => trim((string) $request->query('q', '')),
                'contact_status' => trim((string) $request->query('contact_status', 'all')),
            ],
            'summary' => $this->summary(
                strtoupper(trim((string) $request->query('type', 'C'))),
                trim((string) $request->query('q', ''))
            ),
            'results' => $this->loadContacts(
                strtoupper(trim((string) $request->query('type', 'C'))),
                trim((string) $request->query('q', '')),
                trim((string) $request->query('contact_status', 'all'))
            ),
        ]);
    }

    public function quickLookup(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $needle = trim((string) $request->query('phone', ''));
        if ($needle === '' || !$this->hasTable('clients')) {
            return response()->json(['ok' => true, 'results' => []]);
        }

        $digits = preg_replace('/\D+/', '', $needle) ?: '';
        $like = '%' . $needle . '%';
        $digitLike = $digits !== '' ? '%' . $digits . '%' : '';

        $rows = DB::table('clients as c')
            ->select([
                'c.code',
                'c.name',
                DB::raw("TRIM(COALESCE(c.ctype, '')) as ctype"),
                DB::raw("TRIM(COALESCE(c.mobile, '')) as mobile"),
                DB::raw("TRIM(COALESCE(c.telephone, '')) as telephone"),
                DB::raw("TRIM(COALESCE(c.city, '')) as city"),
            ])
            ->where(function ($q) use ($like, $digitLike) {
                $q->where('c.mobile', 'like', $like)
                    ->orWhere('c.telephone', 'like', $like)
                    ->orWhere('c.code', 'like', $like)
                    ->orWhere('c.name', 'like', $like);
                if ($digitLike !== '') {
                    $q->orWhereRaw("REGEXP_REPLACE(COALESCE(c.mobile, ''), '[^0-9]', '') LIKE ?", [$digitLike])
                        ->orWhereRaw("REGEXP_REPLACE(COALESCE(c.telephone, ''), '[^0-9]', '') LIKE ?", [$digitLike]);
                }
            })
            ->orderBy('c.name')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                $ctype = strtoupper(trim((string) ($row->ctype ?? '')));
                $mobile = trim((string) ($row->mobile ?? ''));
                $telephone = trim((string) ($row->telephone ?? ''));
                return [
                    'code' => trim((string) ($row->code ?? '')),
                    'name' => trim((string) ($row->name ?? '')),
                    'type' => $ctype,
                    'type_label' => $this->typeLabel($ctype),
                    'mobile' => $mobile,
                    'telephone' => $telephone,
                    'contact' => $mobile !== '' ? $mobile : $telephone,
                    'city' => trim((string) ($row->city ?? '')),
                ];
            })
            ->values()
            ->all();

        return response()->json(['ok' => true, 'results' => $rows]);
    }

    private function buildPayload(): array
    {
        return [
            'types' => [
                ['code' => 'C', 'label' => 'Customers'],
                ['code' => 'S', 'label' => 'Suppliers'],
                ['code' => 'G', 'label' => 'Goldsmiths'],
                ['code' => 'J', 'label' => 'Jewellery'],
                ['code' => 'R', 'label' => 'Refiners'],
                ['code' => 'F', 'label' => 'Staff'],
                ['code' => 'D', 'label' => 'Depositors'],
                ['code' => 'ALL', 'label' => 'All Parties'],
            ],
            'initialSummary' => $this->summary('C', ''),
        ];
    }

    private function summary(string $type, string $search): array
    {
        $rows = collect($this->loadContacts($type, $search, 'all'));

        return [
            'total' => $rows->count(),
            'with_mobile' => $rows->filter(fn ($row) => trim((string) ($row['mobile'] ?? '')) !== '')->count(),
            'with_phone' => $rows->filter(fn ($row) => trim((string) ($row['telephone'] ?? '')) !== '')->count(),
            'with_email' => $rows->filter(fn ($row) => trim((string) ($row['email'] ?? '')) !== '')->count(),
        ];
    }

    private function loadContacts(string $type, string $search, string $contactStatus): array
    {
        if (!$this->hasTable('clients')) {
            return [];
        }

        $cols = array_flip($this->columnList('clients'));
        $hasAdvanced = $this->hasTable('clients_advanced');
        $advancedCols = $hasAdvanced ? array_flip($this->columnList('clients_advanced')) : [];

        $query = DB::table('clients as c')->select([
            'c.code',
            'c.name',
            DB::raw("TRIM(COALESCE(c.addr1, '')) as addr1"),
            DB::raw("TRIM(COALESCE(c.addr2, '')) as addr2"),
            DB::raw("TRIM(COALESCE(c.addr3, '')) as addr3"),
            DB::raw("TRIM(COALESCE(c.city, '')) as city"),
            DB::raw("TRIM(COALESCE(c.telephone, '')) as telephone"),
            DB::raw("TRIM(COALESCE(c.mobile, '')) as mobile"),
            DB::raw("TRIM(COALESCE(c.email, '')) as email"),
            DB::raw("TRIM(COALESCE(c.ctype, '')) as ctype"),
            DB::raw("TRIM(COALESCE(c.grp, '')) as grp"),
            DB::raw("TRIM(COALESCE(c.route, '')) as route"),
            DB::raw("TRIM(COALESCE(c.carea, '')) as carea"),
        ]);

        if (isset($cols['removed'])) {
            $query->where(function ($q) {
                $q->whereNull('c.removed')->orWhere('c.removed', '<>', 1);
            });
        }

        if ($type !== 'ALL') {
            $query->where('c.ctype', $type);
        }

        if ($hasAdvanced) {
            $query->leftJoin('clients_advanced as a', 'a.code', '=', 'c.code');

            foreach (['dtbirthday', 'dtengagement', 'dtmarriage'] as $column) {
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

        $contactStatus = strtolower(trim($contactStatus));
        if ($contactStatus === 'mobile-only') {
            $query->whereRaw("TRIM(COALESCE(c.mobile, '')) <> ''");
        } elseif ($contactStatus === 'missing-mobile') {
            $query->whereRaw("TRIM(COALESCE(c.mobile, '')) = ''");
        } elseif ($contactStatus === 'any-contact') {
            $query->where(function ($q) {
                $q->whereRaw("TRIM(COALESCE(c.mobile, '')) <> ''")
                    ->orWhereRaw("TRIM(COALESCE(c.telephone, '')) <> ''")
                    ->orWhereRaw("TRIM(COALESCE(c.email, '')) <> ''");
            });
        }

        return $query
            ->orderBy('c.name')
            ->limit(800)
            ->get()
            ->map(function ($row) {
                $telephone = trim((string) ($row->telephone ?? ''));
                $mobile = trim((string) ($row->mobile ?? ''));
                $email = trim((string) ($row->email ?? ''));

                return [
                    'code' => trim((string) ($row->code ?? '')),
                    'name' => trim((string) ($row->name ?? '')),
                    'ctype' => trim((string) ($row->ctype ?? '')),
                    'type_label' => $this->typeLabel(trim((string) ($row->ctype ?? ''))),
                    'addr1' => trim((string) ($row->addr1 ?? '')),
                    'addr2' => trim((string) ($row->addr2 ?? '')),
                    'addr3' => trim((string) ($row->addr3 ?? '')),
                    'city' => trim((string) ($row->city ?? '')),
                    'telephone' => $telephone,
                    'mobile' => $mobile,
                    'email' => $email,
                    'contact' => $mobile !== '' ? $mobile : $telephone,
                    'grp' => trim((string) ($row->grp ?? '')),
                    'route' => trim((string) ($row->route ?? '')),
                    'carea' => trim((string) ($row->carea ?? '')),
                    'dtbirthday' => (string) ($row->dtbirthday ?? ''),
                    'dtengagement' => (string) ($row->dtengagement ?? ''),
                    'dtmarriage' => (string) ($row->dtmarriage ?? ''),
                    'whatsapp_url' => $mobile !== '' ? 'https://wa.me/' . preg_replace('/\D+/', '', $mobile) : '',
                    'tel_url' => ($mobile !== '' || $telephone !== '') ? 'tel:' . ($mobile !== '' ? $mobile : $telephone) : '',
                    'mail_url' => $email !== '' ? 'mailto:' . $email : '',
                ];
            })
            ->all();
    }

    private function typeLabel(string $ctype): string
    {
        return match (strtoupper($ctype)) {
            'C' => 'Customer',
            'S' => 'Supplier',
            'G' => 'Goldsmith',
            'J' => 'Jewellery',
            'R' => 'Refiner',
            'F' => 'Staff',
            'D' => 'Depositor',
            default => $ctype !== '' ? $ctype : 'Party',
        };
    }
}
