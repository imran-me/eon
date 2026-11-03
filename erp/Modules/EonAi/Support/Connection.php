<?php

namespace Modules\EonAi\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The single settings row, read defensively: a missing table (migration not
 * run yet) or an undecryptable key (app key changed) reads as "not set",
 * never as an exception.
 */
class Connection
{
    public const TABLE = 'eon_ai_settings';

    public static function installed(): bool
    {
        static $installed = null;

        if ($installed === null) {
            try {
                $installed = Schema::hasTable(self::TABLE);
            } catch (\Throwable $e) {
                $installed = false;
            }
        }

        return $installed;
    }

    public static function row(): ?object
    {
        if (! self::installed()) {
            return null;
        }

        try {
            return DB::table(self::TABLE)->orderBy('id')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Write fields onto the one row, creating it on first save. */
    public static function save(array $fields): void
    {
        $fields['updated_at'] = now();
        $fields['updated_by'] = auth()->id();
        $row = self::row();

        if ($row) {
            DB::table(self::TABLE)->where('id', $row->id)->update($fields);
        } else {
            DB::table(self::TABLE)->insert($fields + ['created_at' => now()]);
        }
    }

    public static function secret(): ?string
    {
        $row = self::row();

        if (! $row || ! $row->secret) {
            return null;
        }

        try {
            return Crypt::decryptString($row->secret);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function storeSecret(string $plain): void
    {
        self::save(['secret' => Crypt::encryptString($plain), 'key_created_at' => now()]);
    }

    /** "••••••••3f9a" — enough to recognise the key, not enough to use it. */
    public static function maskedSecret(): ?string
    {
        $secret = self::secret();

        return $secret ? str_repeat('•', 8) . substr($secret, -4) : null;
    }
}
