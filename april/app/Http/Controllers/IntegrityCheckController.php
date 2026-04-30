<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IntegrityCheckController extends Controller
{
    private int $gilevel = 2;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }
        return view('integrity-check.index');
    }

    public function run(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'error' => 'Not authenticated'], 401);
        }
        $messages = []; $warnings = 0; $errors = 0; $msgCount = 0;
        $add = function (string $type, string $text) use (&$messages, &$warnings, &$errors, &$msgCount) {
            $messages[] = ['type' => $type, 'text' => $text];
            if ($type === 'warning') $warnings++;
            elseif ($type === 'error') $errors++;
            elseif ($type === 'message') $msgCount++;
        };
        $messages[] = ['type' => 'section', 'text' => 'Checking Item master...'];
        if ($this->hasTable('items')) {
            $itemIssues = 0;
            $cols = array_map('strtolower', $this->columnList('items'));
            $qtyCol    = in_array('qty',    $cols) ? 'qty'    : null;
            $weightCol = in_array('weight', $cols) ? 'weight' : null;
            $costCol   = in_array('cost',   $cols) ? 'cost'   : null;
            $itemRows  = DB::table('items')->select(array_values(array_filter(['code','name',$costCol,$weightCol,$qtyCol])))->get();
            foreach ($itemRows as $item) {
                $cost = $costCol ? ($item->$costCol ?? 0) : 0;
                $weight = $weightCol ? ($item->$weightCol ?? 0) : 0;
                if ($cost == 0 && $weight > 0) { $add('warning', "Warning. Items {$item->code} - {$item->name} has no cost..."); $itemIssues++; }
            }
            if ($this->hasTable('itemsstk')) {
                $stkCols = array_map('strtolower', $this->columnList('itemsstk'));
                $hasQty = in_array('qty', $stkCols); $hasWgt = in_array('weight', $stkCols);
                if ($hasQty || $hasWgt) {
                    $stkTotals = DB::table('itemsstk')->selectRaw('code'.($hasQty?',sum(qty) as stkqty':', 0 as stkqty').($hasWgt?',sum(weight) as stkweight':', 0 as stkweight'))->groupBy('code')->get()->keyBy('code');
                    foreach ($itemRows as $item) {
                        $mQty=$qtyCol?($item->$qtyCol??0):0; $mWgt=$weightCol?($item->$weightCol??0):0;
                        $ledger=$stkTotals[$item->code]??null;
                        $lQty=$ledger?($ledger->stkqty??0):0; $lWgt=$ledger?($ledger->stkweight??0):0;
                        if (abs($mQty-$lQty)>0.001||abs($mWgt-$lWgt)>0.001) { $add('error',"Error. Items {$item->code} - {$item->name} has different stock in stock list and ledger..."); $itemIssues++; }
                    }
                }
            }
            if ($itemIssues===0) $messages[]=['type'=>'ok','text'=>'OK'];
        } else { $messages[]=['type'=>'info','text'=>'Items table not found, skipping.']; }
        $messages[]=['type'=>'blank','text'=>''];
        $messages[]=['type'=>'section','text'=>'Checking Account master...'];
        if ($this->hasTable('accountm')) {
            $acCols=array_map('strtolower',$this->columnList('accountm'));
            $hasOpbalb=in_array('opbalb',$acCols); $opbalCol=($this->gilevel>1&&$hasOpbalb)?'opbalb':'opbal';
            $hasGrcode=in_array('grcode',$acCols); $hasBshead=in_array('bshead',$acCols); $hasActype1=in_array('actype1',$acCols);
            $selectCols=array_values(array_filter(['accode','name',$hasActype1?'actype1':null,$hasGrcode?'grcode':null,$hasBshead?'bshead':null,'opbal',$hasOpbalb?'opbalb':null]));
            $acRows=DB::table('accountm')->select($selectCols)->get();
            $hasAccountg=$this->hasTable('accountg'); $hasAccountgbs=$this->hasTable('accountgbs'); $hasDaybook=$this->hasTable('daybook');
            $validGroups=$hasAccountg?DB::table('accountg')->pluck('name','grcode')->toArray():[];
            $validBsHeads=$hasAccountgbs?DB::table('accountgbs')->pluck('hname','hcode')->toArray():[];
            $acIssues=0;
            foreach ($acRows as $ac) {
                $opbalVal=($this->gilevel>1&&$hasOpbalb)?($ac->opbalb??0):($ac->opbal??0);
                $dtmp=0.0;
                if ($hasDaybook) $dtmp=(float)DB::table('daybook')->where('accode',$ac->accode)->where('control','<=',$this->gilevel)->sum('amount');
                $grcode=$hasGrcode?trim($ac->grcode??''):'';
                $bshead=$hasBshead?trim($ac->bshead??''):'';
                $actype1=$hasActype1?($ac->actype1??''):'';
                if ($grcode==='') {
                    if (abs($dtmp+$opbalVal)>0) $add('error',"Error. Account {$ac->accode} - {$ac->name} has no group...");
                    else $add('warning',"Warning. Account {$ac->accode} - {$ac->name} has no group...");
                    $acIssues++;
                } elseif ($hasAccountg&&!isset($validGroups[$grcode])) {
                    $add('warning',"Warning. Account {$ac->accode} - {$ac->name} has invalid group - {$grcode}"); $acIssues++;
                }
                if ($actype1==='A'||$actype1==='L') {
                    if ($bshead==='') {
                        if (abs($dtmp+$opbalVal)>0) $add('error',"Error. Account {$ac->accode} - {$ac->name} has no BS Heading...");
                        else $add('warning',"Warning. Account {$ac->accode} - {$ac->name} has no BS Heading...");
                        $acIssues++;
                    } elseif ($hasAccountgbs&&!isset($validBsHeads[$bshead])) {
                        $add('warning',"Warning. Account {$ac->accode} - {$ac->name} has invalid BS Head - {$bshead}"); $acIssues++;
                    }
                }
                if ($dtmp==0&&$opbalVal==0) { $add('message',"Message. Account {$ac->accode} - {$ac->name} has no opening balance and transaction..."); $acIssues++; }
            }
            if ($acIssues===0) $messages[]=['type'=>'ok','text'=>'OK'];
            $messages[]=['type'=>'blank','text'=>''];
            $messages[]=['type'=>'section','text'=>'Checking Account master opening balances...'];
            $opbalSum=(float)DB::table('accountm')->sum($opbalCol);
            if (abs($opbalSum)>0) { $side=$opbalSum<0?'debit':'credit'; $add('error','Error. Opening balances not correct. Difference is '.number_format(abs($opbalSum),2)." more in {$side} side"); }
            else { $messages[]=['type'=>'ok','text'=>'OK']; }
        } else {
            $messages[]=['type'=>'info','text'=>'Account master (accountm) not found, skipping.'];
            $messages[]=['type'=>'blank','text'=>'']; $messages[]=['type'=>'section','text'=>'Checking Account master opening balances...'];
            $messages[]=['type'=>'info','text'=>'Account master (accountm) not found, skipping.'];
        }
        $messages[]=['type'=>'blank','text'=>''];
        $messages[]=['type'=>'section','text'=>'Checking Daybook...'];
        if ($this->hasTable('daybook')) {
            $dbIssues=0; $hasDaybookPart=$this->hasTable('daybookpart');
            $badVouchers=DB::table('daybook')->where('control','<=',$this->gilevel)->selectRaw('slno,sum(amount) as total,max(tdate) as tdate')->groupBy('slno')->havingRaw('abs(sum(amount))>0')->get();
            foreach ($badVouchers as $v) {
                $vchno=$partDesc='';
                if ($hasDaybookPart) { $part=DB::table('daybookpart')->where('slno',$v->slno)->first(); $vchno=$part->vchno??''; $partDesc=$part->particular??''; }
                $dateStr=$v->tdate?date('d/m/Y',strtotime($v->tdate)):'';
                $add('error',"Error. Daybook entry {$dateStr} - {$vchno} - {$partDesc} has error. Debit and Credit not equal..."); $dbIssues++;
            }
            if ($this->hasTable('accountm')) {
                $orphaned=DB::table('daybook')->where('control','<=',$this->gilevel)->select('accode')->distinct()->whereNotIn('accode',DB::table('accountm')->select('accode'))->pluck('accode');
                foreach ($orphaned as $ac) { $add('error',"Error. Daybook entry {$ac} has no master details..."); $dbIssues++; }
            }
            if ($dbIssues===0) $messages[]=['type'=>'ok','text'=>'OK'];
        } else { $messages[]=['type'=>'info','text'=>'Daybook table not found, skipping.']; }
        $messages[]=['type'=>'blank','text'=>''];
        $messages[]=['type'=>'section','text'=>'Checking Sales...'];
        if ($this->hasTable('salesm')) {
            $salesCols=array_map('strtolower',$this->columnList('salesm'));
            if (in_array('billamt',$salesCols)) {
                $badSale=DB::table('salesm')->where('billamt','<=',0)->orderBy('slno')->first();
                if ($badSale) { $dateStr=isset($badSale->tdate)?date('d/m/Y',strtotime($badSale->tdate)):''; $add('warning',"Warning. Some Sales without billamt. First: {$badSale->billno} Dated {$dateStr}"); }
                else { $messages[]=['type'=>'ok','text'=>'OK']; }
            } else { $messages[]=['type'=>'ok','text'=>'OK']; }
        } else { $messages[]=['type'=>'info','text'=>'Sales table (salesm) not found, skipping.']; }
        $messages[]=['type'=>'blank','text'=>''];
        $messages[]=['type'=>'section','text'=>'Checking Purchases...'];
        if ($this->hasTable('purchasem')) {
            $purCols=array_map('strtolower',$this->columnList('purchasem'));
            if (in_array('billamt',$purCols)) {
                $badPur=DB::table('purchasem')->where('billamt','<=',0)->orderBy('slno')->first();
                if ($badPur) { $dateStr=isset($badPur->tdate)?date('d/m/Y',strtotime($badPur->tdate)):''; $add('warning',"Warning. Some Purchases without billamt. First: {$badPur->docno} Dated {$dateStr}"); }
                else { $messages[]=['type'=>'ok','text'=>'OK']; }
            } else { $messages[]=['type'=>'ok','text'=>'OK']; }
        } else { $messages[]=['type'=>'info','text'=>'Purchases table (purchasem) not found, skipping.']; }
        $messages[]=['type'=>'blank','text'=>''];
        $messages[]=['type'=>'divider','text'=>'________________________'];
        $messages[]=['type'=>'info','text'=>"Total Warnings  : {$warnings}"];
        $messages[]=['type'=>'info','text'=>"Total Errors    : {$errors}"];
        $messages[]=['type'=>'info','text'=>"Total Messages  : {$msgCount}"];
        $messages[]=['type'=>'divider','text'=>'________________________'];
        return response()->json(['ok'=>true,'messages'=>$messages,'warnings'=>$warnings,'errors'=>$errors,'msg_count'=>$msgCount]);
    }
}
