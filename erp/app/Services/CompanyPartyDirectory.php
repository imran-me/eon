<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CompanyPartyDirectory
{
    public static function resolve(string $name, string $type, int $companyId, array $contact = [], ?int $projectId = null): User
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $role = $type === 'client' ? 'customer' : $type;
        $key = self::key($name);
        $party = User::role($role)->get()->first(fn (User $u) => self::key($u->name) === $key);

        if (! $party) {
            $email = $contact['email'] ?? null;
            if ($email && User::where('email', $email)->exists()) $email = null;
            $party = User::create([
                'name' => $name, 'company_id' => null, 'email' => $email,
                'phone' => $contact['phone'] ?? null, 'address' => $contact['address'] ?? null,
                'service_categories' => $type === 'vendor' ? ['others'] : null,
                'status' => 'active', 'password' => Str::random(40),
            ]);
            $party->assignRole($role);
        }

        DB::table('company_party')->updateOrInsert(
            ['company_id' => $companyId, 'user_id' => $party->id, 'party_type' => $type],
            ['created_at' => now(), 'updated_at' => now()]
        );
        if ($projectId !== null) {
            DB::table('project_party')->updateOrInsert(
                ['expense_department_id' => $projectId, 'user_id' => $party->id, 'party_type' => $type],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
        return $party;
    }

    public static function members(int $companyId, string $type)
    {
        return User::query()->whereIn('id', DB::table('company_party')->where('company_id', $companyId)
            ->where('party_type', $type)->select('user_id'))->orderBy('name');
    }

    public static function projectMembers(int $projectId, string $type)
    {
        return User::query()->whereIn('id', DB::table('project_party')
            ->where('expense_department_id', $projectId)->where('party_type', $type)->select('user_id'))->orderBy('name');
    }

    public static function belongs(int $userId, int $companyId, string $type): bool
    {
        return DB::table('company_party')->where('user_id', $userId)->where('company_id', $companyId)
            ->where('party_type', $type)->exists();
    }

    private static function key(string $name): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(str_replace('&', ' and ', $name)));
    }
}
