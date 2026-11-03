# Repository checks before handoff or commit

Read `context.md` for project conventions. Record owner feedback in the matching
RAFI / IMRAN block, as described in `CLAUDE.md`.

## PHP formatting (Laravel Pint)

Before handing off PHP changes or committing them, run Laravel Pint on the PHP
files changed by the task, then run `--test` on the same files. PHP syntax checks
and Blade compilation do not check formatting and cannot replace Pint.

CI checks every added/modified `*.php` file in `origin/main...HEAD`, excluding
`database/migrations/`. This includes Blade files. Match that scope before a PR;
also include uncommitted PHP files belonging to the task. For another PR target,
use that target branch instead of `origin/main`.

PowerShell example for the committed PR diff:

```powershell
$pintFiles = @(git diff --name-only --diff-filter=AM origin/main...HEAD -- '*.php' |
    Where-Object { $_ -notlike 'database/migrations/*' -and (Test-Path -LiteralPath $_) })
if ($pintFiles.Count -gt 0) {
    & php vendor/bin/pint @pintFiles
    if ($LASTEXITCODE -ne 0) { throw 'Pint formatting failed' }
    & php vendor/bin/pint --test @pintFiles
    if ($LASTEXITCODE -ne 0) { throw 'Pint verification failed' }
}
```

Never run Pint across the entire repository: legacy files are not all formatted.
Review formatter diffs and preserve unrelated user changes. Do not disable or
weaken CI to fix a formatting failure. Report checks actually run; if Pint is
unavailable, explicitly say formatting has not been verified.

## Frontend design workflow (new screens and Wood Art only)

Tooling: skills `impeccable`, `design-taste-frontend`, `web-design-guidelines`
(restore with `npx skills experimental_install`), the Playwright MCP, and the
tokens in `DESIGN.md`. Full rules: "Frontend design workflow" in `CLAUDE.md`.

1. Read `DESIGN.md` before building or changing UI. It applies ONLY to new
   screens and `Modules/WoodArt/`. Never restyle another company's existing
   view; audit those report-only.
2. No purple-to-blue gradient headlines, no emoji tiles above headings, no
   global selectors; Wood Art CSS stays `wa-` / `wap-` prefixed.
3. Hit targets >= 24px (44px touch), never disable zoom, loading state on
   every async button.
4. After UI work, open the LOCAL URL with Playwright, screenshot at 1365x718,
   fix overflow/overlap, then finish.
