<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'view operation tracker',
        'view all operation tracker',
        'edit operation tracker',
    ];

    public function up(): void
    {
        Schema::create('operation_trackers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('trackable_type', 40);
            $table->unsignedBigInteger('trackable_id');
            $table->string('normalized_status', 30)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 20)->default('normal');
            $table->dateTime('due_at')->nullable();
            $table->text('next_action')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->dateTime('last_activity_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['trackable_type', 'trackable_id'], 'operation_trackers_source_unique');
            $table->index(['company_id', 'normalized_status']);
            $table->index(['assigned_to', 'due_at']);
        });

        Schema::create('operation_tracker_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_tracker_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 120)->nullable();
            $table->string('to_status', 120)->nullable();
            $table->text('note');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['operation_tracker_id', 'created_at'], 'operation_tracker_history_date_idx');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(self::PERMISSIONS)->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::whereIn('name', ['super admin', 'super-admin', 'admin', 'Operation'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_tracker_histories');
        Schema::dropIfExists('operation_trackers');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)->get()->each(function (Permission $permission) {
            $permission->roles()->detach();
            $permission->delete();
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
