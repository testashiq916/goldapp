<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class TallyExportController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('tally-export.index', [
            'dateFrom' => $request->query('date1', date('Y-m-d')),
            'dateTo'   => $request->query('date2', date('Y-m-d')),
        ]);
    }

    public function export(Request $request): Response|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $dateFrom = (string) $request->input('date1', date('Y-m-d'));
        $dateTo   = (string) $request->input('date2', date('Y-m-d'));
        $gilevel  = max(1, (int) $request->session()->get('gilevel', 1));
        $type     = (string) $request->input('export_type', 'daybook'); // daybook, accounts, masters

        $xml = match ($type) {
            'accounts' => $this->exportAccountMasters(),
            'masters'  => $this->exportItemMasters(),
            default    => $this->exportDaybook($dateFrom, $dateTo, $gilevel),
        };

        $filename = 'tally-' . $type . '-' . $dateFrom . '-to-' . $dateTo . '.xml';

        return response($xml, 200, [
            'Content-Type'        => 'text/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ── Day Book → Tally XML ─────────────────────────────────────────────

    private function exportDaybook(string $dateFrom, string $dateTo, int $gilevel): string
    {
        $vouchers = $this->fetchDaybookVouchers($dateFrom, $dateTo, $gilevel);
        $xml      = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml     .= '<ENVELOPE>' . "\n";
        $xml     .= '  <HEADER><VERSION>1</VERSION><TALLYREQUEST>Import Data</TALLYREQUEST></HEADER>' . "\n";
        $xml     .= '  <BODY><IMPORTDATA><REQUESTDESC><REPORTNAME>Vouchers</REPORTNAME>';
        $xml     .= '<STATICVARIABLES><SVCURRENTCOMPANY>##SVCURRENTCOMPANY</SVCURRENTCOMPANY></STATICVARIABLES>';
        $xml     .= '</REQUESTDESC><REQUESTDATA>' . "\n";

        foreach ($vouchers as $v) {
            $xml .= $this->buildTallyVoucher($v);
        }

        $xml .= '</REQUESTDATA></IMPORTDATA></BODY></ENVELOPE>';
        return $xml;
    }

    private function buildTallyVoucher(array $v): string
    {
        $date      = date('Ymd', strtotime($v['tdate'] ?? date('Y-m-d')));
        $vchno     = $this->esc($v['vchno'] ?? '');
        $narration = $this->esc($v['particular'] ?? '');
        $debitAc   = $this->esc($v['debit_ac'] ?? 'Cash');
        $creditAc  = $this->esc($v['credit_ac'] ?? 'Cash');
        $amount    = number_format(abs((float) ($v['amount'] ?? 0)), 2, '.', '');

        $vtype = $this->guessVoucherType($vchno);

        return "    <TALLYMESSAGE xmlns:UDF=\"TallyUDF\">\n" .
               "      <VOUCHER REMOTEID=\"GOLDAPP-{$vchno}\" VCHTYPE=\"{$vtype}\" ACTION=\"Create\">\n" .
               "        <DATE>{$date}</DATE>\n" .
               "        <VOUCHERNUMBER>{$vchno}</VOUCHERNUMBER>\n" .
               "        <NARRATION>{$narration}</NARRATION>\n" .
               "        <VOUCHERTYPENAME>{$vtype}</VOUCHERTYPENAME>\n" .
               "        <ALLLEDGERENTRIES.LIST>\n" .
               "          <LEDGERNAME>{$debitAc}</LEDGERNAME>\n" .
               "          <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>\n" .
               "          <AMOUNT>-{$amount}</AMOUNT>\n" .
               "        </ALLLEDGERENTRIES.LIST>\n" .
               "        <ALLLEDGERENTRIES.LIST>\n" .
               "          <LEDGERNAME>{$creditAc}</LEDGERNAME>\n" .
               "          <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>\n" .
               "          <AMOUNT>{$amount}</AMOUNT>\n" .
               "        </ALLLEDGERENTRIES.LIST>\n" .
               "      </VOUCHER>\n" .
               "    </TALLYMESSAGE>\n";
    }

    private function guessVoucherType(string $vchno): string
    {
        $prefix = strtoupper(substr(trim($vchno), 0, 2));
        return match ($prefix) {
            'VR' => 'Receipt',
            'VP' => 'Payment',
            'JL' => 'Journal',
            default => 'Journal',
        };
    }

    private function fetchDaybookVouchers(string $dateFrom, string $dateTo, int $gilevel): array
    {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart')) {
            return [];
        }

        $rows = DB::table('daybook as d')
            ->join('daybookpart as p', 'p.slno', '=', 'd.slno')
            ->leftJoin('accountm as a', DB::raw('TRIM(d.accode)'), '=', DB::raw('TRIM(a.accode)'))
            ->leftJoin('accountm as oa', DB::raw('TRIM(d.opaccode)'), '=', DB::raw('TRIM(oa.accode)'))
            ->whereBetween('d.tdate', [$dateFrom, $dateTo])
            ->where('d.control', '<=', $gilevel)
            ->selectRaw('
                d.slno,
                d.tdate,
                TRIM(COALESCE(p.vchno,"")) as vchno,
                TRIM(COALESCE(p.particular,"")) as particular,
                d.amount,
                TRIM(COALESCE(a.name, d.accode,"")) as debit_ac,
                TRIM(COALESCE(oa.name, d.opaccode,"")) as credit_ac
            ')
            ->orderBy('d.tdate')
            ->orderBy('d.slno')
            ->get();

        return $rows->map(fn($r) => (array) $r)->all();
    }

    // ── Account Masters ───────────────────────────────────────────────────

    private function exportAccountMasters(): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<ENVELOPE><HEADER><VERSION>1</VERSION><TALLYREQUEST>Import Data</TALLYREQUEST></HEADER>';
        $xml .= '<BODY><IMPORTDATA><REQUESTDESC><REPORTNAME>All Masters</REPORTNAME></REQUESTDESC><REQUESTDATA>';

        if ($this->hasTable('accountm')) {
            $accounts = DB::table('accountm')
                ->selectRaw('TRIM(accode) as accode, TRIM(name) as name, TRIM(COALESCE(actype2,"")) as actype2')
                ->get();

            foreach ($accounts as $ac) {
                $name  = $this->esc((string) $ac->name);
                $group = $this->mapAccountGroup((string) ($ac->actype2 ?? ''));
                $xml  .= "<TALLYMESSAGE xmlns:UDF=\"TallyUDF\">\n";
                $xml  .= "<LEDGER NAME=\"{$name}\" ACTION=\"Create\">";
                $xml  .= "<NAME>{$name}</NAME><PARENT>{$group}</PARENT></LEDGER>\n";
                $xml  .= "</TALLYMESSAGE>\n";
            }
        }

        $xml .= '</REQUESTDATA></IMPORTDATA></BODY></ENVELOPE>';
        return $xml;
    }

    private function mapAccountGroup(string $actype2): string
    {
        return match (strtoupper(trim($actype2))) {
            'C', 'CUST', 'CUSTOMER' => 'Sundry Debtors',
            'S', 'SUP', 'SUPPLIER'  => 'Sundry Creditors',
            'B', 'BANK'             => 'Bank Accounts',
            'CASH'                  => 'Cash-in-Hand',
            'EXP', 'EXPENSE'        => 'Indirect Expenses',
            default                 => 'Sundry Debtors',
        };
    }

    // ── Item Masters ──────────────────────────────────────────────────────

    private function exportItemMasters(): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<ENVELOPE><HEADER><VERSION>1</VERSION><TALLYREQUEST>Import Data</TALLYREQUEST></HEADER>';
        $xml .= '<BODY><IMPORTDATA><REQUESTDESC><REPORTNAME>All Masters</REPORTNAME></REQUESTDESC><REQUESTDATA>';

        if ($this->hasTable('items')) {
            $items = DB::table('items')
                ->selectRaw('TRIM(code) as code, TRIM(name) as name, TRIM(COALESCE(igrpcode,"")) as igrpcode')
                ->get();

            foreach ($items as $item) {
                $name  = $this->esc((string) $item->name);
                $group = $this->esc((string) ($item->igrpcode ?: 'Jewellery'));
                $xml  .= "<TALLYMESSAGE xmlns:UDF=\"TallyUDF\">\n";
                $xml  .= "<STOCKITEM NAME=\"{$name}\" ACTION=\"Create\">";
                $xml  .= "<NAME>{$name}</NAME><PARENT>{$group}</PARENT>";
                $xml  .= "<BASEUNITS>gm</BASEUNITS></STOCKITEM>\n";
                $xml  .= "</TALLYMESSAGE>\n";
            }
        }

        $xml .= '</REQUESTDATA></IMPORTDATA></BODY></ENVELOPE>';
        return $xml;
    }

    private function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
