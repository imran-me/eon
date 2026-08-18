<?php

namespace Modules\WoodArt\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Wood Art Interiors — the module's single wiring point.
 *
 * Everything Wood Art lives under Modules/WoodArt/, and inside it every menu
 * module owns its own folder — Modules/<Name>/ holding its controller,
 * routes.php, resources/views (frontend), and Models + Database/Migrations as
 * the data layer lands — mirroring the reference build's
 * companies/woodart/modules/<key>/ layout. What is genuinely shared (the suite
 * shell layout, the atmosphere and nav assets) stays in resources/.
 *
 * This provider DISCOVERS module folders rather than listing them: adding a
 * module means dropping its folder in and (for the sidebar) one line in the
 * sidebar's $waLiveModules map. The host application touches the whole suite in
 * exactly two places: the PSR-4 entry in composer.json and this provider's
 * line in bootstrap/providers.php. Removing those two detaches everything.
 */
class WoodArtServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $base = dirname(__DIR__);

        // The spine declares the shared role group once, then requires each
        // Modules/<Name>/routes.php inside it.
        $this->loadRoutesFrom($base . '/routes/web.php');

        // One `woodart::` namespace over the shared views plus every module's
        // own resources/views. View basenames are per-module (projects.blade,
        // scope.blade, …) so they cannot collide.
        $this->loadViewsFrom(array_merge(
            [$base . '/resources/views'],
            glob($base . '/Modules/*/resources/views', GLOB_ONLYDIR) ?: []
        ), 'woodart');

        // wa_* tables only — no shared table is ever migrated from here.
        foreach (glob($base . '/Modules/*/Database/Migrations', GLOB_ONLYDIR) ?: [] as $path) {
            $this->loadMigrationsFrom($path);
        }

        // Browser-served assets. Source of truth is the module; the copy the
        // web server actually serves is public/woodart/. After editing any
        // asset, sync with:
        //   php artisan vendor:publish --tag=woodart-assets --force
        $this->publishes([
            $base . '/resources/assets' => public_path('woodart'),
        ], 'woodart-assets');
    }
}
