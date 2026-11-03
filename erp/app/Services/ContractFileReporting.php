<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ContractFileReporting
{
    public static function workspace($files): array
    {
        $ids = $files->modelKeys();
        if(\Illuminate\Support\Facades\Schema::hasTable('contract_file_changes'))$files->loadMissing('moneyChanges');
        $services = \Illuminate\Support\Facades\Schema::hasTable('contract_file_service_costs')
            ? \App\Models\ContractFileServiceCost::with('vendor')->whereIn('contract_file_id',$ids)->get() : collect();
        if(\Illuminate\Support\Facades\Schema::hasTable('contract_file_changes'))$services->load('refundChanges');
        $serviceIds = $services->pluck('id');
        $types = ['contract_file', 'contract_file_client', 'contract_file_client_adjustment', 'contract_file_vendor_adjustment'];
        $journals = \App\Models\JournalEntry::with('items.account')->where(function($q) use($types,$ids,$serviceIds){
            $q->where(fn($s)=>$s->whereIn('source',$types)->whereIn('source_id',$ids))
                ->orWhere(fn($s)=>$s->where('source',\App\Models\ContractFileServiceCost::SOURCE)->whereIn('source_id',$serviceIds));
        })->orderByDesc('date')->orderByDesc('id')->get();
        $transactions = \App\Models\Transaction::with('user')->where(function($q) use($types,$ids,$serviceIds){
            $q->where(fn($s)=>$s->whereIn('type',$types)->whereIn('invoice_id',$ids))
                ->orWhere(fn($s)=>$s->where('type',\App\Models\ContractFileServiceCost::SOURCE)->whereIn('invoice_id',$serviceIds));
        })->orderByDesc('payment_date')->orderByDesc('id')->get();
        $rows = $files->map(function ($file) use ($journals,$services) {
            $ownServices=$services->where('contract_file_id',$file->id);
            $items = $journals->filter(fn($j)=>$j->source===\App\Models\ContractFileServiceCost::SOURCE ? $ownServices->contains('id',$j->source_id) : (int)$j->source_id===(int)$file->id)->flatMap->items;
            $revenue = $items->filter(fn ($i) => $i->account?->code == config('accounts.file_sales_revenue'))->sum(fn ($i) => (float) $i->credit - (float) $i->debit);
            $cost = $items->filter(fn ($i) => $i->account?->code == config('accounts.contract_file_cost'))->sum(fn ($i) => (float) $i->debit - (float) $i->credit);
            return ['file'=>$file, 'revenue'=>$revenue, 'cost'=>$cost, 'profit'=>$revenue-$cost,
                'received'=>(float) $file->client_paid_amount, 'client_due'=>$file->client_due, 'vendor_due'=>(float) $file->due_amount+$ownServices->sum('due_amount')];
        });
        $payables=$files->map(fn($f)=>['file'=>$f,'party'=>$f->vendor,'cost'=>$f->net_processing_cost,'paid'=>(float)$f->cost_paid_amount,'due'=>(float)$f->due_amount,'credit'=>max(0,$f->cost_paid_amount-$f->net_processing_cost),'label'=>'Processing']);
        foreach($services->whereNull('voided_at') as $s)$payables->push(['file'=>$files->firstWhere('id',$s->contract_file_id),'party'=>$s->vendor,'cost'=>$s->net_amount,'paid'=>$s->paid_amount,'due'=>$s->due_amount,'credit'=>$s->refund_credit,'label'=>$s->title]);
        $monthly = $rows->groupBy(fn ($r) => $r['file']->submit_date?->format('Y-m') ?? 'Undated')->sortKeysDesc();
        return compact('rows', 'monthly', 'journals', 'transactions','payables');
    }

    public static function revenue(?int $companyId = null)
    {
        return self::entries($companyId)
            ->whereIn('j.source', ['contract_file_client', 'contract_file_client_adjustment'])
            ->where('a.code', config('accounts.file_sales_revenue'));
    }

    public static function cost(?int $companyId = null)
    {
        return self::entries($companyId)
            ->whereIn('j.source', ['contract_file', 'contract_file_vendor_adjustment','contract_file_service_cost'])
            ->where('a.code', config('accounts.contract_file_cost'));
    }

    private static function entries(?int $companyId)
    {
        return DB::table('journal_entries as j')
            ->join('journal_items as i', 'i.journal_entry_id', '=', 'j.id')
            ->join('accounts as a', 'a.id', '=', 'i.account_id')
            ->whereNull('j.deleted_at')
            ->when($companyId !== null, fn ($q) => $q->where('j.company_id', $companyId));
    }
}
