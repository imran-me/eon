<?php

use App\Models\ContractFile;
use App\Models\ContractFileSale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_file_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_file_sale_id')->constrained('contract_file_sales')->cascadeOnDelete();
            $table->foreignId('contract_file_id')->constrained('contract_files')->cascadeOnDelete();
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('vendor_cost', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['contract_file_sale_id', 'contract_file_id'], 'cf_sale_item_unique');
        });

        ContractFileSale::query()->whereNotNull('file_ids')->chunkById(100, function ($sales) {
            foreach ($sales as $sale) {
                $fileIds = collect($sale->file_ids ?? [])->filter()->unique()->values();

                if ($fileIds->isEmpty()) {
                    continue;
                }

                $files = ContractFile::whereIn('id', $fileIds)->get()->keyBy('id');
                $defaultVendorCost = $fileIds->count() > 0 ? ((float) $sale->vendor_cost / $fileIds->count()) : 0;

                foreach ($fileIds as $fileId) {
                    $file = $files->get($fileId);

                    if (!$file) {
                        continue;
                    }

                    $sale->items()->create([
                        'contract_file_id' => $file->id,
                        'sale_price' => $file->visa_rate ?: 0,
                        'vendor_cost' => $defaultVendorCost,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_file_sale_items');
    }
};
