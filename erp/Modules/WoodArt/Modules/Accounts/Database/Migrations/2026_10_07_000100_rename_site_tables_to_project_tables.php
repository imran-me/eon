<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * wa_site_terms / wa_site_statement  →  project_terms / project_statement.
 *
 * These tables started as Wood Art's. They now hold a project's contract value,
 * client installments and supplier dues for ANY company that runs projects
 * (Wood Art Interiors, Epal Constructions), so the `wa_` prefix would mislead.
 * A project already says which company it is (expense_departments.company_id),
 * so the rows need no company column of their own.
 *
 * Written to be safe from every starting point: a server that never ran the
 * earlier two migrations (nothing to rename; they created the old names a moment
 * ago), one that ran them (renamed, data kept), and one already renamed (no-op).
 * Nothing is dropped and no row is touched — a rename keeps the data and indexes.
 *
 * ─── ISOLATION (CLAUDE.md) ───────────────────────────────────────────────
 * Only these two tables. Run with --path, never a bare `migrate`.
 */
return new class extends Migration
{
    private const RENAMES = [
        'wa_site_terms'     => 'project_terms',
        'wa_site_statement' => 'project_statement',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $old => $new) {
            if (Schema::hasTable($new) && ! Schema::hasTable($old)) {
                Schema::rename($new, $old);
            }
        }
    }
};
