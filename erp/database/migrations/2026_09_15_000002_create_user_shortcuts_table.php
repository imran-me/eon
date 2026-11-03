<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's own pinned pages, two kinds:
 *
 *   tab  — a pill in the strip that sits between the LIVE BULLETIN and the
 *          page content (layout/shortcuts.blade.php)
 *   menu — a link in the sidebar's "Shortcuts" section, under Dashboard and
 *          above Companies (layout/sidebar.blade.php)
 *
 * Both are created by right-clicking a sidebar link. Rows are strictly
 * per-user: a shortcut is a personal bookmark, never a company or role
 * setting, so there is no company_id here and nothing reads across users.
 *
 * `url` is the PATH (+ query) of the page, not an absolute URL, so a row
 * survives the app moving hosts. It is pinned to the user's role prefix at
 * the time it was saved (/super-admin/...); a user whose role changes will
 * see the link 403 and can simply remove it.
 *
 * unique(user_id, kind, url): the same page may be both a tab and a menu
 * item, but never twice as either — the controller returns the existing row
 * rather than erroring when a user pins the same page again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_shortcuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);          // 'tab' | 'menu'
            $table->string('label', 60);
            $table->string('url', 500);
            $table->string('icon', 80)->nullable(); // FontAwesome / Bootstrap Icons class list
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'kind', 'url'], 'user_shortcuts_one_per_user_kind_url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_shortcuts');
    }
};
