<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One record per vendor / client, used by many companies and projects.
 *
 * The first shape of `project_parties` gave every company its own copy of a party:
 * "Asia Bricks" supplying both Constructions and Interiors would have been two
 * records, two phone numbers, two names to keep in step. Owner (2026-10-07): a party is
 * entered ONCE; what differs is where it is USED — which company, which project, as
 * what (vendor or client). So:
 *
 *   project_parties        the party itself: name, phone, email, address, note.
 *                          One row per name (name_key = the name with case, spaces and
 *                          punctuation removed, UNIQUE) — no company, no type.
 *   project_party_roles    where he is used: party × company × type × project
 *                          (project_key 0 = used by the company in general, otherwise an
 *                          expense_departments id). Each company's screens list only the
 *                          parties with a role in THAT company, and filter by project / type.
 *
 * Money is never on the party: a due, a payment or an installment sits on a statement row
 * that belongs to a project (so to a company). A shared party's balance is therefore always
 * per company / project — Constructions' dues to Asia Bricks are never added to Interiors'.
 *
 * ─── SAFE FROM ANY STARTING POINT ────────────────────────────────────────────
 * Converts the earlier per-company shape in place: copies each (company, type) into a role,
 * merges same-named parties into one (re-pointing statement rows, clients and cost links),
 * then drops the old columns. Already converted, or never created: a no-op / a plain create.
 * Only `project_` tables are touched. Run with --path, never a bare `migrate`.
 */
return new class extends Migration
{
    private function key(string $name): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(str_replace('&', ' and ', $name)));
    }

    public function up(): void
    {
        if (! Schema::hasTable('project_parties')) {
            return;
        }

        if (! Schema::hasTable('project_party_roles')) {
            Schema::create('project_party_roles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('party_id')->index();
                $table->unsignedBigInteger('company_id')->index();
                $table->string('type', 10);                          // vendor | client
                $table->unsignedBigInteger('project_key')->default(0); // 0 = the company in general, else expense_departments.id
                $table->timestamps();

                $table->unique(['party_id', 'company_id', 'type', 'project_key'], 'ppr_unique');
            });
        }

        if (! Schema::hasColumn('project_parties', 'name_key')) {
            Schema::table('project_parties', fn (Blueprint $t) => $t->string('name_key', 160)->nullable()->after('name'));
        }

        // ── convert the per-company shape ──
        if (Schema::hasColumn('project_parties', 'company_id')) {
            $rows = DB::table('project_parties')->orderBy('id')->get();
            $canon = [];                                             // name_key => the id that survives

            foreach ($rows as $r) {
                $k = $this->key($r->name);
                $keep = $canon[$k] ?? null;

                if ($keep === null) {
                    $canon[$k] = $keep = (int) $r->id;
                    DB::table('project_parties')->where('id', $r->id)->update(['name_key' => $k]);
                } else {
                    // the same name under another company: one record, re-point everything at it
                    DB::table('project_statement')->where('party_id', $r->id)->update(['party_id' => $keep]);
                    DB::table('project_terms')->where('client_party_id', $r->id)->update(['client_party_id' => $keep]);
                    DB::table('project_expense_party')->where('party_id', $r->id)->update(['party_id' => $keep]);
                }

                DB::table('project_party_roles')->updateOrInsert(
                    ['party_id' => $keep, 'company_id' => $r->company_id, 'type' => $r->type, 'project_key' => 0],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }

            // duplicates have been merged into the survivors: remove the husks
            $survivors = array_values($canon);
            if ($survivors) {
                DB::table('project_parties')->whereNotIn('id', $survivors)->delete();
            }

            // a client set on a project is also a role of THAT project
            foreach (DB::table('project_terms')->whereNotNull('client_party_id')->get() as $t) {
                $co = DB::table('expense_departments')->where('id', $t->expense_department_id)->value('company_id');
                if ($co) {
                    DB::table('project_party_roles')->updateOrInsert(
                        ['party_id' => $t->client_party_id, 'company_id' => $co, 'type' => 'client', 'project_key' => $t->expense_department_id],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }
            }

            Schema::table('project_parties', function (Blueprint $t) {
                $t->dropUnique(['company_id', 'type', 'name']);
                $t->dropIndex(['company_id']);
            });
            Schema::table('project_parties', function (Blueprint $t) {
                $t->dropColumn(['company_id', 'type']);
            });
        }

        // names that predate name_key (none on a converted table, but safe)
        foreach (DB::table('project_parties')->whereNull('name_key')->get(['id', 'name']) as $r) {
            DB::table('project_parties')->where('id', $r->id)->update(['name_key' => $this->key($r->name)]);
        }

        $hasUnique = collect(DB::select("SHOW INDEX FROM project_parties WHERE Key_name = 'project_parties_name_key_unique'"))->isNotEmpty();
        if (! $hasUnique) {
            Schema::table('project_parties', fn (Blueprint $t) => $t->unique('name_key'));
        }
    }

    public function down(): void
    {
        // The per-company shape cannot be rebuilt without losing which company owned which
        // record; this migration is deliberately one-way. Roll back the earlier migration to drop the tables.
    }
};
