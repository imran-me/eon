<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('party_invoices', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies');
            $table->date('invoice_date')->nullable()->after('invoice_number');
            $table->decimal('subtotal', 15, 2)->default(0)->after('amount');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('discount_amount');
            $table->decimal('paid_amount', 15, 2)->default(0)->after('tax_amount');
            $table->decimal('due_amount', 15, 2)->default(0)->after('paid_amount');
            $table->string('payment_status', 20)->default('due')->after('due_amount');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
        });

        Schema::create('party_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_invoice_id')->constrained('party_invoices')->cascadeOnDelete();
            $table->string('service_type');
            $table->text('description');
            $table->string('passenger_name')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->string('revenue_account_code', 30);
            $table->timestamps();
        });

        Schema::create('party_invoice_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->string('original_filename');
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('draft')->index();
            $table->json('documents');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->json('created_invoice_ids')->nullable();
            $table->timestamps();
        });

        DB::table('party_invoices')->orderBy('id')->each(function ($invoice) {
            $ledger = DB::table('transactions')->where('type', 'party_invoice')->where('invoice_id', $invoice->id);
            $companyId = (clone $ledger)->whereNotNull('company_id')->value('company_id')
                ?: DB::table('users')->where('id', $invoice->created_by)->value('company_id');
            $paid = (float) (clone $ledger)->sum('credit');
            $due = max(0, (float) $invoice->amount - $paid);
            DB::table('party_invoices')->where('id', $invoice->id)->update([
                'company_id' => $companyId,
                'invoice_date' => substr((string) $invoice->created_at, 0, 10),
                'subtotal' => $invoice->amount,
                'paid_amount' => $paid,
                'due_amount' => $due,
                'payment_status' => $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'due'),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_invoice_import_batches');
        Schema::dropIfExists('party_invoice_items');
        Schema::table('party_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('journal_entry_id');
            $table->dropColumn(['invoice_date', 'subtotal', 'discount_amount', 'tax_amount', 'paid_amount', 'due_amount', 'payment_status']);
        });
    }
};
