<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Make users + roles the only party master and transactions the company-scoped
 * party ledger. Project tables retain only project facts and point to users.id.
 */
return new class extends Migration
{
    private function key(string $name): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(str_replace('&', ' and ', $name)));
    }

    public function up(): void
    {
        if (! Schema::hasTable('company_party')) {
            Schema::create('company_party', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('party_type', 16); // vendor | agent | client
                $table->timestamps();
                $table->unique(['company_id', 'user_id', 'party_type']);
                $table->index(['company_id', 'party_type']);
            });
        }

        if (! Schema::hasColumn('transactions', 'company_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->restrictOnDelete();
                $table->index(['company_id', 'user_id', 'payment_date'], 'transactions_company_party_date_idx');
            });
        }

        // The old ledger was a Travels-only screen. Freeze that historical truth
        // before multi-company posting starts; future writes always carry company_id.
        DB::table('transactions')->whereNull('company_id')->update(['company_id' => 2]);
        Schema::table('transactions', fn (Blueprint $table) => $table->unsignedBigInteger('company_id')->nullable(false)->change());

        $roles = DB::table('roles')->whereIn('name', ['vendor', 'agent', 'customer'])->pluck('id', 'name');
        $partyMap = [];

        if (Schema::hasTable('project_parties')) {
            $roleRows = Schema::hasTable('project_party_roles')
                ? DB::table('project_party_roles')->get()->groupBy('party_id') : collect();

            foreach (DB::table('project_parties')->whereNull('deleted_at')->orderBy('id')->get() as $party) {
                $key = $this->key($party->name);
                $user = DB::table('users')->whereNull('deleted_at')->get(['id', 'name', 'phone', 'email'])
                    ->first(fn ($u) => $this->key($u->name) === $key);

                if (! $user) {
                    $base = Str::slug($party->name) ?: 'party';
                    $username = $base;
                    for ($n = 2; DB::table('users')->where('username', $username)->exists(); $n++) {
                        $username = $base . '-' . $n;
                    }
                    $email = $party->email && ! DB::table('users')->where('email', $party->email)->exists() ? $party->email : null;
                    $id = DB::table('users')->insertGetId([
                        'name' => $party->name, 'username' => $username, 'company_id' => null,
                        'email' => $email, 'phone' => $party->phone, 'address' => $party->address,
                        'service_categories' => json_encode(['others']), 'status' => 'active',
                        'password' => Hash::make(Str::random(40)), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $user = (object) ['id' => $id];
                }

                $partyMap[(int) $party->id] = (int) $user->id;
                foreach ($roleRows->get($party->id, collect()) as $usage) {
                    $roleName = $usage->type === 'client' ? 'customer' : 'vendor';
                    if (isset($roles[$roleName])) {
                        DB::table('model_has_roles')->updateOrInsert([
                            'role_id' => $roles[$roleName], 'model_type' => 'App\\Models\\User', 'model_id' => $user->id,
                        ]);
                    }
                    DB::table('company_party')->updateOrInsert([
                        'company_id' => $usage->company_id, 'user_id' => $user->id,
                        'party_type' => $usage->type === 'client' ? 'client' : 'vendor',
                    ], ['created_at' => now(), 'updated_at' => now()]);
                }
            }

            // Two phases avoid id collisions (an old project-party id may equal a
            // different target user id).
            $offset = 1000000000;
            foreach (array_keys($partyMap) as $old) {
                DB::table('project_statement')->where('party_id', $old)->update(['party_id' => $offset + $old]);
                DB::table('project_terms')->where('client_party_id', $old)->update(['client_party_id' => $offset + $old]);
                DB::table('project_expense_party')->where('party_id', $old)->update(['party_id' => $offset + $old]);
            }
            foreach ($partyMap as $old => $userId) {
                DB::table('project_statement')->where('party_id', $offset + $old)->update(['party_id' => $userId]);
                DB::table('project_terms')->where('client_party_id', $offset + $old)->update(['client_party_id' => $userId]);
                DB::table('project_expense_party')->where('party_id', $offset + $old)->update(['party_id' => $userId]);
            }
        }

        // Existing role rosters belong to their user's company until explicitly shared.
        foreach (['vendor' => 'vendor', 'agent' => 'agent', 'customer' => 'client'] as $role => $type) {
            if (! isset($roles[$role])) continue;
            $users = DB::table('users as u')->join('model_has_roles as mr', function ($j) use ($roles, $role) {
                $j->on('mr.model_id', '=', 'u.id')->where('mr.model_type', 'App\\Models\\User')->where('mr.role_id', $roles[$role]);
            })->whereNotNull('u.company_id')->whereNull('u.deleted_at')->get(['u.id', 'u.company_id']);
            foreach ($users as $user) {
                DB::table('company_party')->updateOrInsert(
                    ['company_id' => $user->company_id, 'user_id' => $user->id, 'party_type' => $type],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }

            // Transaction ownership is stronger evidence than users.company_id
            // (shared vendors deliberately have no home company).
            $used = DB::table('transactions as t')->join('model_has_roles as mr', function ($j) use ($roles, $role) {
                $j->on('mr.model_id', '=', 't.user_id')->where('mr.model_type', 'App\\Models\\User')->where('mr.role_id', $roles[$role]);
            })->distinct()->get(['t.company_id', 't.user_id']);
            foreach ($used as $user) {
                DB::table('company_party')->updateOrInsert(
                    ['company_id' => $user->company_id, 'user_id' => $user->user_id, 'party_type' => $type],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        Schema::dropIfExists('project_party_roles');
        Schema::dropIfExists('project_parties');

        Schema::table('project_statement', fn (Blueprint $table) =>
            $table->foreign('party_id', 'project_statement_party_user_fk')->references('id')->on('users')->nullOnDelete());
        Schema::table('project_terms', fn (Blueprint $table) =>
            $table->foreign('client_party_id', 'project_terms_client_user_fk')->references('id')->on('users')->nullOnDelete());
        Schema::table('project_expense_party', fn (Blueprint $table) =>
            $table->foreign('party_id', 'project_expense_party_user_fk')->references('id')->on('users')->restrictOnDelete());
    }

    public function down(): void
    {
        throw new RuntimeException('This consolidation is intentionally irreversible: canonical users and financial history must not be split again.');
    }
};
