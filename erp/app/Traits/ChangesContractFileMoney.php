<?php

namespace App\Traits;

use App\Models\{ContractFile, ContractFileChange, ContractFileServiceCost, PaymentSchedule, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Schema};
use Illuminate\Validation\ValidationException;

trait ChangesContractFileMoney
{
    public function moneyAction(Request $request, string $role, ContractFile $contractFile)
    {
        abort_unless(Schema::hasTable('contract_file_changes'),503,'File change history database update is required.');
        $data=$request->validate([
            'request_token'=>'required|uuid', 'type'=>'required|in:refund,reprocess,others,cancel',
            'bearer'=>'required|in:client,company', 'occurred_on'=>'required|date', 'note'=>'nullable|string|max:1000',
            'service_cost_id'=>'nullable|integer', 'client_refund'=>'nullable|numeric|min:0|max:999999999',
            'vendor_refund'=>'nullable|numeric|min:0|max:999999999', 'title'=>'nullable|string|max:160',
            'vendor_id'=>'nullable|integer|exists:users,id', 'amount'=>'nullable|numeric|min:0|max:999999999',
            'paid_amount'=>'nullable|numeric|min:0|max:999999999', 'bank_id'=>'nullable|integer|exists:banks,id',
            'payment_date'=>'nullable|date', 'due_date'=>'nullable|date',
        ]);
        $hash=hash('sha256',json_encode($data));
        $change=DB::transaction(function()use($contractFile,$data,$hash){
            $file=ContractFile::whereKey($contractFile->id)->lockForUpdate()->firstOrFail();
            $existing=ContractFileChange::where('request_token',$data['request_token'])->first();
            if($existing){
                abort_unless($existing->contract_file_id===$file->id && $existing->created_by===Auth::id() && hash_equals($existing->payload_hash,$hash),409,'Confirmation token already used for another action.');
                return $existing;
            }
            if(!$file->client_id)throw ValidationException::withMessages(['client_id'=>'Assign an Agent / Client before posting a money action.']);
            if(!$file->file_changes->count() && in_array($file->exception_flag,['returned','rejected','cancelled'],true))throw ValidationException::withMessages(['type'=>'This file already has a legacy exception reversal. Clear that exception before posting a new refund or re-processing action.']);
            $service=null; $refund=0; $clientRefund=0; $penalty=0; $extra=0; $delta=0;
            if($data['type']==='cancel'){
                if($file->file_changes->where('type','cancel')->isNotEmpty())throw ValidationException::withMessages(['type'=>'This file has already been cancelled. Create a new file for a new package.']);
                if(!empty($data['service_cost_id']))throw ValidationException::withMessages(['service_cost_id'=>'Cancellation refunds processing only. Service refunds are separate.']);
                $prior=$file->file_changes->where('type','refund')->whereNull('service_cost_id')->first();
                if(!array_key_exists('vendor_refund',$data))throw ValidationException::withMessages(['vendor_refund'=>'Enter vendor refund, including zero.']);
                $refund=round((float)$data['vendor_refund'],2);
                if($prior && $refund>0)throw ValidationException::withMessages(['vendor_refund'=>'Processing refund already posted. Enter zero; existing refund and penalty are preserved.']);
                if($refund>$file->net_processing_cost)throw ValidationException::withMessages(['vendor_refund'=>'Refund exceeds remaining processing cost.']);
                $clientRefund=max(0,round((float)$file->client_price-$file->file_changes->sum('client_refund'),2));
                $penalty=$prior?0:round($file->net_processing_cost-$refund,2);
                $delta=round(($data['bearer']==='client'?$penalty:0)-$clientRefund,2);
                $vendorId=$file->vendor_id;
            }elseif($data['type']==='refund'){
                if(!array_key_exists('vendor_refund',$data))throw ValidationException::withMessages(['refund'=>'Enter the vendor-approved refund, including zero when applicable.']);
                if((float)($data['client_refund']??0)>0)throw ValidationException::withMessages(['client_refund'=>'Vendor refund does not refund the package charge. Client refund is not supported by this action.']);
                $service=!empty($data['service_cost_id']) ? $file->serviceCosts()->whereKey($data['service_cost_id'])->whereNull('voided_at')->lockForUpdate()->firstOrFail() : null;
                $already=$file->file_changes->whereIn('type',['refund','cancel'])->filter(fn($c)=>(int)$c->service_cost_id===(int)($service?->id))->isNotEmpty();
                if($already)throw ValidationException::withMessages(['refund'=>'This cost has already been refunded. Re-process creates a new independently refundable cost.']);
                $currentCost=$service?->net_amount ?? $file->net_processing_cost;
                $refund=round((float)$data['vendor_refund'],2); $clientRefund=0;
                if($refund>$currentCost || $clientRefund>$file->net_client_charge)throw ValidationException::withMessages(['refund'=>'Vendor refund cannot exceed the selected cost; client refund cannot exceed the remaining charge.']);
                $penalty=round($currentCost-$refund,2);
                $delta=round(($data['bearer']==='client' ? $penalty : 0)-$clientRefund,2);
                $vendorId=$service?->vendor_id ?? $file->vendor_id;
            }else{
                $vendorId=$data['vendor_id']??null;
                $extra=round((float)($data['amount']??0),2);
                if(!$vendorId || $extra<=0 || empty(trim($data['title']??'')))throw ValidationException::withMessages(['service'=>'Enter service title, provider and a positive cost.']);
                if(($data['paid_amount']??0)>$extra || (($data['paid_amount']??0)>0 && (empty($data['bank_id']) || empty($data['payment_date']))))throw ValidationException::withMessages(['paid_amount'=>'Paid amount must not exceed cost and requires bank and payment date.']);
                $delta=$data['bearer']==='client' ? $extra : 0;
            }
            if(!$vendorId)throw ValidationException::withMessages(['vendor_id'=>'Assign a vendor before recording a refund.']);
            // Serialize both party running balances even if they currently have no rows.
            User::whereIn('id',[$file->client_id,$vendorId])->orderBy('id')->lockForUpdate()->get();
            $targetTitle=$service?->title ?? 'Processing';
            $snapshot=['package'=>$file->client_price,'charge'=>$file->net_client_charge,'cost'=>$file->total_file_cost,'client_paid'=>$file->client_paid_amount,'status'=>$file->status,'exception'=>$file->exception_flag,'target_cost'=>$service?->net_amount ?? $file->net_processing_cost,'target_title'=>$targetTitle];
            $description=$data['type']==='cancel'
                ? 'File processing cancelled — Package reversal ৳'.number_format($clientRefund,2).' · Vendor refund ৳'.number_format($refund,2).' · New penalty ৳'.number_format($penalty,2).' ('.$data['bearer'].' bears; previous adjustments preserved)'
                : ($data['type']==='refund'
                ? $targetTitle.' refund — Vendor refund ৳'.number_format($refund,2).' · Penalty ৳'.number_format($penalty,2).($data['bearer']==='client'?' charged to Agent / Client':' borne by Company')
                : ucfirst($data['type']).': '.trim($data['title']).' ('.$data['bearer'].' bears)');
            if(in_array($data['type'],['refund','cancel'],true)){
                if($refund>0)$this->postAdjustment($vendorId,ContractFile::VENDOR_ADJUSTMENT_TYPE,$file,0,$refund,$description,$data['occurred_on']);
            }else{
                $service=$this->createFileService($file,$data+['incurred_on'=>$data['occurred_on']],$data['type'],trim($data['title']));
            }
            if($delta!=0)$this->postAdjustment($file->client_id,ContractFile::CLIENT_ADJUSTMENT_TYPE,$file,max(0,$delta),max(0,-$delta),$description,$data['occurred_on']);
            $change=ContractFileChange::create([
                'contract_file_id'=>$file->id,'request_token'=>$data['request_token'],'payload_hash'=>$hash,'type'=>$data['type'],
                'service_cost_id'=>$service?->id,'client_id'=>$file->client_id,'vendor_id'=>$vendorId,'bearer'=>$data['bearer'],
                'client_refund'=>$clientRefund,'vendor_refund'=>$refund,'penalty'=>$penalty,'extra_cost'=>$extra,'client_delta'=>$delta,
                'occurred_on'=>$data['occurred_on'],'snapshot'=>$snapshot,'note'=>$data['note']??null,'created_by'=>Auth::id(),
            ]);
            $file->unsetRelation('moneyChanges');$file->unsetRelation('serviceCosts');
            $file->forceFill(['client_due_amount'=>max(0,$file->net_client_charge-$file->client_paid_amount),'due_amount'=>max(0,$file->net_processing_cost-$file->cost_paid_amount)])->saveQuietly();
            if(in_array($data['type'],['refund','cancel'],true))$file->update(['exception_flag'=>'cancelled']);
            if($data['type']==='reprocess')$file->update(['status'=>'submitted_to_vendor','exception_flag'=>'resubmitted']);
            // Original processing schedule must not demand an already credited refund.
            PaymentSchedule::where('schedulable_type',ContractFile::class)->where('schedulable_id',$file->id)->where('type','pay')->where('status','pending')->update(['amount'=>$file->due_amount,'paid_amount'=>0,'status'=>$file->due_amount>0?'pending':'cancelled']);
            $this->recordEvent($file,'money',$data['type'],mb_substr($description,0,120),$data['note']??null);
            return $change;
        },3);
        return response()->json(['success'=>true,'change_id'=>$change->id,'message'=>'File money action posted. Refund credits are on the party ledgers; cash settlements are separate.']);
    }
}
