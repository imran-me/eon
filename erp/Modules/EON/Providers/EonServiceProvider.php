<?php

namespace Modules\EON\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * EON — the ERP's evaluation intelligence. One wiring point, nothing else.
 *
 * EON reads the task module and writes only its own eon_* tables. It is a pure
 * addition: no existing controller, model, view, route or asset changes because
 * EON exists. The host application touches EON in exactly two places —
 *
 *   1. bootstrap/providers.php — this provider's line.
 *   2. layout/sidebar.blade.php — one Route::has()-guarded link.
 *
 * Delete those two lines and the whole feature detaches with no trace.
 *
 * SAFETY, in the shape CLAUDE.md demands:
 *   • Every route sits under /{role}/eon/*, so if EON is wrong, the person who
 *     sees it is standing on an EON page. Nothing else can reach this code.
 *   • Views are namespaced `eon::` and loaded only from here.
 *   • Assets publish to public/eon/ and are <link>ed only by EON views. No rule
 *     in them is unprefixed, so nothing can leak into the shared bundle.
 *   • Migrations create eon_ tables only. No shared table is ever touched, and
 *     every read of an eon_ table is behind an existence check, because deploys
 *     land before migrations run.
 */
class EonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $base = dirname(__DIR__);

        $this->loadRoutesFrom($base . '/routes/web.php');
        $this->loadViewsFrom($base . '/resources/views', 'eon');
        $this->loadMigrationsFrom($base . '/Database/Migrations');

        /* NO ASSET PUBLISHING, deliberately.
         *
         * EON has no file under public/. Its styles are inlined by
         * eon::partials.styles and its one small script sits in the view that
         * needs it, so the whole feature ships by uploading files — there is no
         * `vendor:publish` step that somebody can forget and no window in which
         * the pages are live but unstyled. Wood Art needs that step because its
         * assets are large and shared across its own pages; EON does not, and
         * every server step avoided is one that cannot go wrong. If EON ever
         * grows real assets, add the publishes() call AND state the step. */
    }
}
