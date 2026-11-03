<?php

namespace Modules\EonAi\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * EON AI — the connection point between this ERP and the external EON
 * assistant (its own server, repository and database; none of its code lives
 * here). One wiring point, nothing else.
 *
 * The host application touches this module in exactly two places —
 *
 *   1. bootstrap/providers.php — this provider's line.
 *   2. layout/sidebar.blade.php — one Route::has()-guarded "EON Connection"
 *      link in Settings, plus `role.eon-ai.*` in that group's routeIs() lists
 *      (a disjunct that is false on every other route).
 *
 * Delete those and the feature detaches with no trace.
 *
 * SAFETY — the ERP is live, so this is built to be harmless while wrong:
 *   • Every route sits under /{role}/eon-ai/*, super admin only, checked in the
 *     controller on every action. Nothing outside those pages calls this code.
 *   • No page of the ERP ever waits on the EON server. The only outbound call
 *     is the "Test connection" button, with a short timeout.
 *   • This step sends NO ERP data anywhere. It stores EON's address, a shared
 *     key (encrypted) and the last test result — in eon_ai_settings, its own
 *     table, read behind an existence check because deploys land before
 *     migrations run.
 *   • Styles are inlined and eai- prefixed. No asset, no publish step.
 */
class EonAiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $base = dirname(__DIR__);

        $this->loadRoutesFrom($base . '/routes/web.php');
        $this->loadViewsFrom($base . '/resources/views', 'eonai');
        $this->loadMigrationsFrom($base . '/Database/Migrations');
    }
}
