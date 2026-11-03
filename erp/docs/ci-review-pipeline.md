# PR review pipeline

Nothing here deploys. `deploy.yml` (push to `staging` -> FTP) is untouched.

## What runs on every PR into `main` (`.github/workflows/ci.yml`)

| Check | What it proves | Required? |
|---|---|---|
| Lint | every PHP file parses; composer.json/lock in sync | yes |
| Static analysis (Larastan) | no new undefined variables/methods/classes (level 1, baseline) | yes |
| Tests (PHPUnit Unit) | journal-balance + panel-authorization tests | yes |
| Code style on changed files (Pint) | files the PR touches are Pint-clean | **no** (advisory) |

CodeRabbit (`.coderabbit.yaml`) then reviews the diff for authorization, company
scoping, journal balance/duplication, transactions, races, SQL, N+1, migrations.

## Incremental adoption (legacy code)

* **Larastan** `phpstan-baseline.neon` records the ~1,000 findings that exist today
  (846 of them are relation-existence notes). New code must not add any. As you fix
  old ones, regenerate: `vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon`
  then raise `level` in `phpstan.neon` one step at a time.
* **Pint** the repo is not Pint-clean (nearly every file differs). Never run it over
  the whole tree; CI checks only changed files and is advisory.
* One finding could not be baselined and is **excluded, not hidden**:
  `app/Jobs/SendCampaignSMS.php` uses trait `Dispatchable` without importing it
  (the class cannot load). Fix it, then delete the exclude line in `phpstan.neon`.

## Blockers / what is NOT tested

1. **CI cannot build the database.** Legacy migrations fail on an empty DB
   (`2026_02_08_123645_create_workspace_users_table`: "Duplicate key name '1'"; 357
   migrations then stay pending), and the app will not boot on an empty DB because
   `routes/web.php:191` runs `Role::pluck()` at route-load time (the same boot-time
   read makes `artisan migrate` itself fail). So there are **no DB-backed tests**:
   no cross-company access tests, no duplicate-payment tests, no rollback tests. To
   unlock them: commit a MySQL schema dump (`database/schema/mysql-schema.sql`,
   structure only) taken from a current DB, and make the boot-time `roles` read safe.
2. The stock `tests/Feature/ExampleTest.php` fails everywhere (needs a schema);
   CI runs the Unit suite only.
3. App boot/config validation is limited to Larastan booting the app against a
   stub `roles` table.
4. Missing business rules we did not guess at: what counts as a "duplicate payment"
   per module, and which roles may post which financial operation.

## Setup you must do by hand

1. Install the CodeRabbit GitHub App: https://github.com/apps/coderabbitai ->
   Configure -> select `Epal-It-Solutions/epal_erp_soft`. (Free tier covers PR review
   of public repos; private repos need a paid seat.)
2. GitHub -> Settings -> Branches -> add rule for `main`:
   * Require a pull request before merging (1 approval; dismiss stale approvals)
   * Require status checks: `Lint (PHP syntax + composer.json)`,
     `Static analysis (Larastan, baseline)`, `Tests (PHPUnit Unit suite)`
     (names appear only after the workflow has run once on a PR)
   * Require conversation resolution; block force pushes; block deletions
   * Optional: CODEOWNERS for `app/Services`, `database/migrations`, `routes`
   Branch protection needs GitHub Pro/Team for private repos.

## Daily commands

```
git switch rafi            # (imran: git switch imran)
git fetch origin && git merge origin/main      # bring main in first, fix conflicts
git push origin rafi
gh pr create --base main --head rafi --fill
gh pr checks --watch       # wait for CI
# review CodeRabbit comments, resolve, get the other developer's approval, then:
gh pr merge --merge        # merge commit, branch is kept
git switch rafi && git pull origin main
```
