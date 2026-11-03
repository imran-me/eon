<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('passport_holder_id')->constrained('passport_holders')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('portal_id')->nullable()->constrained('portals')->nullOnDelete();
            $table->foreignId('notification_recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_purchase_id')->nullable()->constrained('ticket_purchases')->nullOnDelete();
            $table->string('booking_reference')->unique();
            $table->string('pnr')->nullable()->index();
            $table->enum('trip_type', ['one-way', 'two-way'])->default('one-way');
            $table->enum('ticket_class', ['economy', 'business', 'first'])->default('economy');
            $table->dateTime('booking_at');
            $table->dateTime('expires_at')->index();
            $table->dateTime('departure_at')->nullable();
            $table->dateTime('return_at')->nullable();
            $table->decimal('quoted_amount', 12, 2)->default(0);
            $table->string('currency', 5)->default('BDT');
            $table->enum('status', ['booked', 'confirmed', 'expired', 'cancelled'])->default('booked')->index();
            $table->text('notes')->nullable();
            $table->string('attachment')->nullable();
            $table->boolean('notify_in_app')->default(true);
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_sms')->default(true);
            $table->dateTime('reminded_24h_at')->nullable();
            $table->dateTime('reminded_3h_at')->nullable();
            $table->dateTime('reminded_1h_at')->nullable();
            $table->dateTime('expired_notified_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ticket_booking_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->enum('channel', ['in_app', 'email', 'sms']);
            $table->enum('status', ['pending', 'sent', 'failed', 'skipped'])->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['ticket_booking_id', 'event', 'channel'], 'ticket_booking_event_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_booking_notifications');
        Schema::dropIfExists('ticket_bookings');
    }
};
