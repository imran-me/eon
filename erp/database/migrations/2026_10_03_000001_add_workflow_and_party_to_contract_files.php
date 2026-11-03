<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_files', function (Blueprint $table) {
            $table->string('status', 40)->default('file_received')->change();
            $table->string('exception_flag', 30)->nullable()->after('status');
            $table->foreignId('client_id')->nullable()->after('passport_holder_id')->constrained('users')->nullOnDelete();
            $table->string('client_type', 20)->nullable()->after('client_id');
            $table->decimal('client_price', 12, 2)->default(0)->after('client_type');
            $table->decimal('client_paid_amount', 12, 2)->default(0)->after('client_price');
            $table->decimal('client_due_amount', 12, 2)->default(0)->after('client_paid_amount');
        });

        Schema::create('contract_file_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_file_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('code', 40)->nullable();
            $table->string('label', 120);
            $table->text('note')->nullable();
            $table->dateTime('occurred_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['contract_file_id', 'occurred_at']);
        });

        $map = [
            'doc_collection' => 'documents_pending',
            'submitted_to_vendor' => 'submitted_to_vendor',
            'under_process' => 'under_process',
            'approved' => 'approved',
            'delivered' => 'delivered',
            'rejected' => 'under_process',
        ];
        foreach ($map as $old => $new) {
            DB::table('contract_files')->where('status', $old)->update([
                'status' => $new,
                'exception_flag' => $old === 'rejected' ? 'rejected' : null,
            ]);
        }

        DB::table('contract_files')->orderBy('id')->chunkById(500, function ($files) {
            $now = now();
            DB::table('contract_file_events')->insert($files->map(fn ($file) => [
                'contract_file_id' => $file->id,
                'type' => 'stage',
                'code' => $file->status,
                'label' => \App\Models\ContractFile::stageLabel($file->status),
                'note' => 'Opening stage when file journey history was enabled',
                'occurred_at' => $now,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_file_events');
        Schema::table('contract_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['client_type', 'client_price', 'client_paid_amount', 'client_due_amount', 'exception_flag']);
            $table->enum('status', ['doc_collection', 'submitted_to_vendor', 'under_process', 'approved', 'delivered', 'rejected'])
                ->default('doc_collection')->change();
        });
    }
};
