<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait LogsDelpartAudit
{
    protected function logDelpart(Request $request, string $part, array $options = []): void
    {
        static $hasDelpart = null;
        if ($hasDelpart === null) $hasDelpart = Schema::hasTable('delpart');
        if (!$hasDelpart) {
            return;
        }

        $part = trim($part);
        if ($part === '') {
            return;
        }

        $control = $options['control'] ?? $request->session()->get('semi');
        $control = $control ?? $request->session()->get('control');
        $control = $control ?? $request->session()->get('gilevel');
        $control = is_numeric($control) ? (int) $control : 1;

        $uid = trim((string) ($options['uid'] ?? $request->session()->get('user_id', $request->session()->get('user_code', ''))));
        $ic = trim((string) ($options['ic'] ?? $request->session()->get('user_code', $uid)));
        $tdate = $this->normalizeDelpartDate($options['tdate'] ?? date('Y-m-d'));

        $payload = [
            'tdate' => $tdate,
            'part' => mb_substr($part, 0, 60),
            'control' => $control,
            'slno' => isset($options['slno']) ? (int) $options['slno'] : null,
            'utype' => strtoupper(trim((string) ($options['utype'] ?? 'E'))),
            'ttype' => strtoupper(trim((string) ($options['ttype'] ?? 'M'))),
            'updtdate' => date('Y-m-d'),
            'updttime' => date('H:i:s'),
            'uid' => $uid,
            'ic' => $ic,
        ];

        static $delpartCols = null;
        if ($delpartCols === null) $delpartCols = array_map('strtolower', Schema::getColumnListing('delpart'));
        $cols = array_flip($delpartCols);
        $filtered = array_filter(
            $payload,
            static fn ($value, $key) => $value !== null && isset($cols[strtolower((string) $key)]),
            ARRAY_FILTER_USE_BOTH
        );

        if ($filtered !== []) {
            DB::table('delpart')->insert($filtered);
        }
    }

    private function normalizeDelpartDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) $value);
        if ($text === '') {
            return date('Y-m-d');
        }

        $time = strtotime($text);
        return $time === false ? date('Y-m-d') : date('Y-m-d', $time);
    }
}
