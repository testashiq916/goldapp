<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DayBookController extends Controller
{
    private int $gilevel = 1;

    // ─── Main entry point ────────────────────────────────────────────────

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = $this->resolveLevel($request);

        $dateFrom = $request->query('date1', date('Y-m-d'));
        $dateTo   = $request->query('date2', date('Y-m-d'));
        $formType = $request->query('form', 'form1');
        $breakDay = $request->has('breakday');
        $withDiff = $request->has('withdiff');
        $showData = $request->has('show');

        $rows    = [];
        $groups  = [];
        $opbal   = 0.0;
        $clbal   = 0.0;

        if ($showData && $this->hasTable('daybook')) {
            ['rows' => $rows, 'groups' => $groups, 'opbal' => $opbal, 'clbal' => $clbal] =
                $this->loadReportData($formType, $dateFrom, $dateTo, $withDiff);

            // Practical fallback: if level-1 shows nothing but higher-control entries exist, show level-2 report.
            if (empty($rows) && empty($groups) && $this->gilevel < 2) {
                $hasLevel2Rows = DB::table('daybook')
                    ->whereBetween('tdate', [$dateFrom, $dateTo])
                    ->where('control', '<=', 2)
                    ->exists();

                if ($hasLevel2Rows) {
                    $this->gilevel = 2;
                    ['rows' => $rows, 'groups' => $groups, 'opbal' => $opbal, 'clbal' => $clbal] =
                        $this->loadReportData($formType, $dateFrom, $dateTo, $withDiff);
                }
            }
        }

        $companyName = DB::table('generals')->where('code', 'SHOPNM')->value('cvalue') ?? '';
        $companyAddr = DB::table('generals')->where('code', 'COADR1')->value('cvalue') ?? '';

        return view('daybook.index', compact(
            'dateFrom', 'dateTo', 'formType', 'breakDay', 'withDiff',
            'showData', 'rows', 'groups', 'opbal', 'clbal',
            'companyName', 'companyAddr'
        ));
    }

    private function loadReportData(string $formType, string $dateFrom, string $dateTo, bool $withDiff): array
    {
        $rows = [];
        $groups = [];
        $opbal = 0.0;
        $clbal = 0.0;

        switch ($formType) {
            case 'form1':
                $bal   = $this->getCashBalances($dateFrom, $dateTo);
                $opbal = $bal['opbal'];
                $clbal = $bal['clbal'];
                $rows  = $this->getForm1Data($dateFrom, $dateTo);
                break;

            case 'form2':
                $rows   = $this->getForm2Data($dateFrom, $dateTo);
                $groups = $this->groupForm2BySlno($rows);
                if ($withDiff) {
                    $groups = array_filter(
                        $groups,
                        fn ($g) => round($g['db'], 2) !== round($g['cr'], 2)
                    );
                }
                break;

            case 'form3':
                $rows = $this->getForm3Data($dateFrom, $dateTo);
                break;

            case 'form4':
                $rows   = $this->getForm4Data($dateFrom, $dateTo);
                $groups = $this->groupForm4ByDate($rows);
                break;
        }

        return ['rows' => $rows, 'groups' => $groups, 'opbal' => $opbal, 'clbal' => $clbal];
    }

    private function resolveLevel(Request $request): int
    {
        // Program-level standardization: always view daybook at level 1.
        return 1;
    }

    // ─── Opening Balance Helpers ─────────────────────────────────────────

    private function getOpeningBalance(): float
    {
        $col = ($this->gilevel === 1) ? 'opbal' : 'opbalb';
        $val = DB::table('accountm')->where('accode', 'CASH')->value($col);
        return $val !== null ? (float) $val : 0.0;
    }

    private function getSumBefore(string $beforeDate): float
    {
        $val = DB::table('daybook')
            ->where('accode', 'CASH')
            ->where('control', '<=', $this->gilevel)
            ->where('tdate', '<', $beforeDate)
            ->sum('amount');
        return (float) ($val ?? 0);
    }

    private function getSumUpTo(string $toDate): float
    {
        $val = DB::table('daybook')
            ->where('accode', 'CASH')
            ->where('control', '<=', $this->gilevel)
            ->where('tdate', '<=', $toDate)
            ->sum('amount');
        return (float) ($val ?? 0);
    }

    private function getCashBalances(string $dateFrom, string $dateTo): array
    {
        $base  = $this->getOpeningBalance();
        $opbal = $base + $this->getSumBefore($dateFrom);
        $clbal = $base + $this->getSumUpTo($dateTo);
        return ['opbal' => $opbal, 'clbal' => $clbal];
    }

    // ─── Form 1 (d_daybook3) — Cash Book with running balance ───────────

    private function getForm1Data(string $dateFrom, string $dateTo): array
    {
        $gl = $this->gilevel;

        $rows = DB::select("
            SELECT DISTINCT
                daybook.tdate,
                daybook.accode,
                accountm.name AS accountm_name,
                daybookpart.vchno,
                (SELECT SUM(db2.amount)
                 FROM daybook db2
                 JOIN daybookpart dbp2 ON db2.slno = dbp2.slno
                 WHERE db2.tdate     = daybook.tdate
                   AND db2.accode    = daybook.accode
                   AND dbp2.vchno    = daybookpart.vchno
                   AND db2.control  <= ?
                ) AS daybook_amount
            FROM accountm
            JOIN daybook     ON accountm.accode  = daybook.accode
            JOIN daybookpart ON daybook.slno      = daybookpart.slno
            WHERE daybook.control <= ?
              AND daybook.tdate   >= ?
              AND daybook.tdate   <= ?
              AND accountm.accode <> 'CASH'
            ORDER BY daybook.tdate ASC
        ", [$gl, $gl, $dateFrom, $dateTo]);

        $rows = array_map(fn ($r) => (array) $r, $rows);

        foreach ($rows as &$row) {
            $row['part'] = $this->getPartDescription(
                $row['accode'],
                $row['tdate'],
                $row['vchno'] ?? null
            );
        }
        unset($row);

        usort($rows, function ($a, $b) {
            $cmp = strcmp($a['tdate'], $b['tdate']);
            if ($cmp !== 0) return $cmp;
            $cmp = strcmp($a['vchno'] ?? '', $b['vchno'] ?? '');
            if ($cmp !== 0) return $cmp;
            return ($b['daybook_amount'] ?? 0) <=> ($a['daybook_amount'] ?? 0);
        });

        return $rows;
    }

    // ─── Form 2 (d_daybook2) — Grouped by slno ──────────────────────────

    private function getForm2Data(string $dateFrom, string $dateTo): array
    {
        $rows = DB::select("
            SELECT
                daybook.slno    AS daybook_slno,
                daybook.tdate   AS daybook_tdate,
                daybook.accode  AS daybook_accode,
                daybook.amount  AS daybook_amount,
                accountm.name   AS accountm_name,
                daybookpart.particular AS daybookpart_particular,
                daybookpart.vchno      AS daybookpart_vchno
            FROM accountm
            JOIN daybook     ON accountm.accode = daybook.accode
            JOIN daybookpart ON daybook.slno     = daybookpart.slno
            WHERE daybook.control <= ?
              AND daybook.tdate BETWEEN ? AND ?
            ORDER BY daybook.tdate ASC, daybook.slno ASC
        ", [$this->gilevel, $dateFrom, $dateTo]);

        return array_map(fn ($r) => (array) $r, $rows);
    }

    private function groupForm2BySlno(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $slno = $row['daybook_slno'];
            if (!isset($groups[$slno])) {
                $groups[$slno] = [
                    'rows'       => [],
                    'db'         => 0.0,
                    'cr'         => 0.0,
                    'particular' => '',
                    'vchno'      => '',
                ];
            }
            $groups[$slno]['rows'][] = $row;
            $amt = (float) $row['daybook_amount'];
            if ($amt < 0) {
                $groups[$slno]['db'] += abs($amt);
            } else {
                $groups[$slno]['cr'] += $amt;
            }
            $part = $row['daybookpart_particular'] ?? '';
            if ($part > $groups[$slno]['particular']) {
                $groups[$slno]['particular'] = $part;
            }
            $vch = $row['daybookpart_vchno'] ?? '';
            if ($vch > $groups[$slno]['vchno']) {
                $groups[$slno]['vchno'] = $vch;
            }
        }
        return $groups;
    }

    // ─── Form 3 (d_daybook) — Detailed with A/C Code ────────────────────

    private function getForm3Data(string $dateFrom, string $dateTo): array
    {
        $rows = DB::select("
            SELECT
                daybook.slno    AS daybook_slno,
                daybook.tdate   AS daybook_tdate,
                daybook.accode  AS daybook_accode,
                daybook.amount  AS daybook_amount,
                accountm.name   AS accountm_name,
                daybookpart.particular AS daybookpart_particular,
                daybookpart.vchno      AS daybookpart_vchno
            FROM accountm
            JOIN daybook     ON accountm.accode = daybook.accode
            JOIN daybookpart ON daybook.slno     = daybookpart.slno
            WHERE daybook.control <= ?
              AND daybook.tdate   >= ?
              AND daybook.tdate   <= ?
            ORDER BY daybook.tdate ASC, daybook.slno ASC
        ", [$this->gilevel, $dateFrom, $dateTo]);

        return array_map(fn ($r) => (array) $r, $rows);
    }

    // ─── Form 4 (d_daybook4) — Receipt/Payment grouped by date ──────────

    private function getForm4Data(string $dateFrom, string $dateTo): array
    {
        $bal   = $this->getCashBalances($dateFrom, $dateTo);
        $dopbal = $bal['opbal'];

        $cursorRows = DB::select("
            SELECT
                db.tdate,
                db.accode,
                am.name AS acname,
                dbp.vchno,
                SUM(db.amount) AS amount
            FROM daybook db
            JOIN daybookpart dbp ON db.slno   = dbp.slno
            JOIN accountm    am  ON db.accode = am.accode
            WHERE db.tdate   >= ?
              AND db.tdate   <= ?
              AND db.accode <> 'CASH'
            GROUP BY db.tdate, db.accode, am.name, dbp.vchno
            ORDER BY db.tdate, am.name
        ", [$dateFrom, $dateTo]);

        $result   = [];
        $sno      = 0;
        $prevDate = null;

        foreach ($cursorRows as $crow) {
            $crow    = (array) $crow;
            $dtdate  = $crow['tdate'];
            $saccode = $crow['accode'];
            $sacname = $crow['acname'];
            $svchno  = $crow['vchno'];
            $damt    = -(float) $crow['amount'];

            if ($prevDate !== $dtdate) {
                $sno++;
                $result[] = [
                    'tdate'      => $dtdate,
                    'sno'        => $sno,
                    'acname'     => ($dopbal < 0) ? 'By Balance B/D' : 'To Balance B/D',
                    'dbamt'      => ($dopbal < 0) ? abs($dopbal) : 0,
                    'cramt'      => ($dopbal >= 0) ? abs($dopbal) : 0,
                    'particular' => '',
                ];
                $prevDate = $dtdate;
            }

            if ($damt != 0) {
                $sno++;
                $particular = $this->getForm4PartDescription($saccode, $dtdate, $svchno);
                $result[] = [
                    'tdate'      => $dtdate,
                    'sno'        => $sno,
                    'acname'     => ($damt < 0) ? "By {$sacname}" : "To {$sacname}",
                    'dbamt'      => ($damt < 0) ? abs($damt) : 0,
                    'cramt'      => ($damt >= 0) ? abs($damt) : 0,
                    'particular' => $particular,
                ];
                $dopbal += $damt;
            }
        }

        usort($result, function ($a, $b) {
            $cmp = strcmp($a['tdate'], $b['tdate']);
            return $cmp !== 0 ? $cmp : $a['sno'] <=> $b['sno'];
        });

        return $result;
    }

    private function groupForm4ByDate(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $dt = $row['tdate'];
            if (!isset($groups[$dt])) {
                $groups[$dt] = ['rows' => [], 'totdb' => 0.0, 'totcr' => 0.0];
            }
            $groups[$dt]['rows'][]  = $row;
            $groups[$dt]['totdb']  += (float) $row['dbamt'];
            $groups[$dt]['totcr']  += (float) $row['cramt'];
        }
        return $groups;
    }

    // ─── Part Description Helpers ────────────────────────────────────────

    private function getPartDescription(string $accode, string $tdate, ?string $vchno): string
    {
        if ($accode === 'RS') return $this->getSalesRange($tdate);
        if ($accode === 'EP') return $this->getPurchaseRange($tdate);
        return $this->getGeneralParticular($accode, $tdate, $vchno);
    }

    private function getForm4PartDescription(string $accode, string $tdate, ?string $vchno): string
    {
        $salesCodes = ['RS', 'DISC', 'TAX', 'VA'];
        $purchCodes = ['EP', 'DISC', 'TAX', 'VA'];

        if (in_array($accode, $salesCodes, true) && empty($vchno)) {
            return 'S.No.' . $this->getSalesRange($tdate);
        }
        if (in_array($accode, $purchCodes, true) && empty($vchno)) {
            return 'S.No.' . $this->getPurchaseRange($tdate);
        }
        return $this->getGeneralParticular($accode, $tdate, $vchno);
    }

    private function getSalesRange(string $tdate): string
    {
        if (!$this->hasTable('salesm')) return '';
        $row = DB::select("
            SELECT MIN(billno) AS mn, MAX(billno) AS mx
            FROM salesm
            WHERE tdate = ? AND control <= ?
        ", [$tdate, $this->gilevel]);
        $r = $row[0] ?? null;
        return ($r && $r->mn) ? "{$r->mn} - {$r->mx}" : '';
    }

    private function getPurchaseRange(string $tdate): string
    {
        if (!$this->hasTable('purchasem')) return '';
        $row = DB::select("
            SELECT MIN(docno) AS mn, MAX(docno) AS mx
            FROM purchasem
            WHERE tdate = ? AND control <= ?
        ", [$tdate, $this->gilevel]);
        $r = $row[0] ?? null;
        return ($r && $r->mn) ? "{$r->mn} - {$r->mx}" : '';
    }

    private function getGeneralParticular(string $accode, string $tdate, ?string $vchno): string
    {
        $cnt = DB::table('daybook as db')
            ->join('daybookpart as dbp', 'db.slno', '=', 'dbp.slno')
            ->where('db.tdate', $tdate)
            ->where('db.accode', $accode)
            ->where('dbp.vchno', $vchno ?? '')
            ->where('db.control', '<=', $this->gilevel)
            ->distinct()
            ->count('db.slno');

        if ($cnt === 1) {
            return (string) DB::table('daybook as db')
                ->join('daybookpart as dbp', 'db.slno', '=', 'dbp.slno')
                ->where('db.tdate', $tdate)
                ->where('db.accode', $accode)
                ->where('dbp.vchno', $vchno ?? '')
                ->where('db.control', '<=', $this->gilevel)
                ->max('dbp.particular') ?? '';
        }

        return 'Day Total';
    }
}
