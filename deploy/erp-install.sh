#!/usr/bin/env bash
# ============================================================
#  EON — install the ERP by hand, once (only if the cron could not).
#
#     bash ~/domains/gulfrabit.com/public_html/eon/deploy/erp-install.sh
#
#  Same steps post-deploy.php runs, but in your terminal where you
#  can see composer working and it has no cron time limit.
#  Idempotent — safe to run again.
# ============================================================
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")/.." && pwd)"
ERP="$ROOT/erp"
PHP_BIN="${EON_PHP:-$(command -v php || echo /usr/bin/php)}"

[ -f "$ERP/artisan" ] || { echo "no ERP at $ERP — deploy first"; exit 1; }
cd "$ERP" || exit 1

echo "== 1. env + folders (post-deploy writes .env from EON's config) =="
"$PHP_BIN" "$ROOT/deploy/post-deploy.php" | sed 's/^/   /'

echo "== 2. composer =="
if [ ! -f vendor/autoload.php ]; then
  if command -v composer >/dev/null 2>&1; then COMPOSER="composer";
  elif [ -f composer.phar ]; then COMPOSER="$PHP_BIN composer.phar";
  else
    echo "   fetching composer.phar…"
    EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o composer-setup.php
    ACTUAL="$("$PHP_BIN" -r "echo hash_file('sha384', 'composer-setup.php');")"
    if [ "$EXPECTED" != "$ACTUAL" ]; then echo "   composer installer checksum mismatch — aborting"; rm -f composer-setup.php; exit 1; fi
    "$PHP_BIN" composer-setup.php --quiet && rm -f composer-setup.php
    COMPOSER="$PHP_BIN composer.phar"
  fi
  COMPOSER_MEMORY_LIMIT=-1 $COMPOSER install --no-dev --optimize-autoloader --no-interaction || { echo "   composer install failed"; exit 1; }
else
  echo "   vendor/ present — skipping"
fi

echo "== 3. key, storage link, caches =="
grep -q '^APP_KEY=base64:' .env || "$PHP_BIN" artisan key:generate --force
[ -e public/storage ] || "$PHP_BIN" artisan storage:link
"$PHP_BIN" artisan optimize:clear
chmod -R 775 storage bootstrap/cache 2>/dev/null

echo "== 4. does it boot? =="
"$PHP_BIN" artisan --version && "$PHP_BIN" artisan route:list --except-vendor 2>/dev/null | head -5
echo
echo "open https://$(grep -m1 '^APP_URL=' .env | cut -d/ -f3)/  — the ERP should be at the front door now."
