<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config(['app.name' => site_setting('app_name', config('app.name'))]);

        // Some dynamic Blade/JS row templates use ${row}; keep PHP from
        // interpreting it as an undefined Blade variable before JS runs.
        View::share('row', '${row}');

        // Superadmin/Admin bypasses all permission checks
        Gate::before(function ($user, $ability) {
            if ($user->hasRole(['super admin', 'superadmin', 'admin'])) {
                return true;
            }
        });

        // Morph map for JournalItem::party() polymorphic relation.
        // Only Customer and Supplier have dedicated models — agents/vendors
        // resolve through JournalItem::partyUser() to avoid conflicting with
        // Spatie Permission's reverse morph lookup on User::class.
        Relation::morphMap([
            'customer'      => Customer::class,
            'supplier'      => Supplier::class,
            'journal_entry' => \App\Models\JournalEntry::class,
        ]);
    }
}
