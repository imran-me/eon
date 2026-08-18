# Wood Art Interiors — module

Everything Wood Art lives here, full stack. The host application knows about
this module through exactly two lines:

- `composer.json` → `"Modules\\": "Modules/"` (PSR-4)
- `bootstrap/providers.php` → `Modules\WoodArt\Providers\WoodArtServiceProvider::class`

Remove those two lines and the suite detaches without a trace in any other
company's code. The working rules (isolation, no-reload navigation, reference
build) are in the repo root `CLAUDE.md` — read it before touching anything.

## Layout — one folder per menu module, like the reference build

```
Modules/WoodArt/
├── Providers/
│   └── WoodArtServiceProvider.php   discovers module folders; views + routes +
│                                    migrations + asset publishing
├── routes/web.php                   the SPINE: declares the shared role group
│                                    once, requires every Modules/*/routes.php
│                                    inside it — adding a module never edits it
├── Modules/                         ← per menu module, full stack, mirroring
│   │                                  the reference companies/woodart/modules/
│   ├── Projects/
│   │   ├── ProjectsController.php   backend (PHP)
│   │   ├── routes.php               this module's routes only
│   │   ├── resources/views/         frontend (Blade)
│   │   ├── Models/                  data layer (pending)
│   │   └── Database/{Migrations,Seeders}/
│   ├── Scope/                       Spaces & Phases — same shape
│   ├── Design/                      Design & 3D — same shape
│   └── Estimates/                   Estimates & BOQ — same shape
└── resources/                       genuinely SHARED pieces only
    ├── views/layouts/suite.blade.php   the suite shell every screen extends
    └── assets/                         SOURCE of browser assets (see below)
        ├── atmosphere/                 ambient scene (woodart-scene.css/js)
        └── nav/                        no-reload navigation (woodart-nav.css/js)
```

Adding the next module = drop in `Modules/<Name>/` (controller + routes.php +
view) + one line in the sidebar's `$waLiveModules` map + one entry in
`nav/woodart-nav.js` `DEFAULT_SUB`. Views all render under the one `woodart::`
namespace, so basenames must stay unique per module.

## Assets: source vs served copy

The web server serves `public/woodart/`, which is **published output** of
`resources/assets/`. Edit the module copy, then sync:

```
php artisan vendor:publish --tag=woodart-assets --force
```

Editing only one of the two lets them drift silently.

## Conventions

- Route names: `role.woodart.*` (the sidebar links against these).
- View namespace: `woodart::`.
- CSS prefixes: `wa-`, `wap-`, `wa-nav-`, `wa-sub-` — nothing global, ever.
- Controllers list `$role` as their first parameter: the host group's `{role}`
  segment is passed positionally, so omitting it feeds the role into the next
  parameter.
- Page content goes inside `[data-wa-view]`; scripts and anything that must
  survive in-suite navigation stay outside it (see CLAUDE.md).

## Reference build

Screens are transcribed (tokens, copy, proportions) from the owner's SPA at
`H:\Imran\Modular ERP\modularerp-main (2)\modularerp-main` — never embedded.
Per-module Laravel blueprints live in the reference at
`companies/woodart/modules/<module>/backend/LARAVEL-BLUEPRINT.md`; the Projects
one specifies the `wa_projects` schema, controllers and business rules for the
data layer still to come.
