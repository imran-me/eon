<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Passport is not mandatory (owner, 2026-10-08): a passenger can be saved — and
// created automatically from an imported ticket PDF — with only a name. The column
// stays UNIQUE: MySQL allows any number of NULLs, so two passengers without a
// passport never collide, while a typed passport number is still one person.
// Blank strings become NULL first, or the second passenger saved without a
// passport would clash with the first on ''.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('passport_holders', 'passport_no')) {
            return;
        }
        DB::statement('ALTER TABLE passport_holders MODIFY passport_no VARCHAR(255) NULL');
        DB::table('passport_holders')->where('passport_no', '')->update(['passport_no' => null]);
    }

    public function down(): void
    {
        // Not reversible without inventing passport numbers for the rows that have none.
    }
};
