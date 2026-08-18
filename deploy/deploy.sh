#!/usr/bin/env bash
# ============================================================
#  EON — deploy: bring the live site to the latest commit on GitHub.
#
#  Two layouts, one script:
#
#  A. the checkout IS the document root (its own website/subdomain)
#         ./deploy/deploy.sh
#
#  B. the checkout lives outside every web root and is published into
#     the document root (use this when the folder sits inside another
#     site's public_html, which that site's own deployment may re-sync)
#         EON_PUBLISH_DIR=~/domains/gulfrabit.com/public_html/eon \
#           ~/eon-src/deploy/deploy.sh
#
#  Options:  --force  redeploy even when nothing changed
#            --quiet  no stdout (for cron)
#            --branch=main   (default: main, or "branch=" in deploy/deploy.env)
#
#  Safe to run every minute: one cheap `git ls-remote` and it exits when
#  GitHub has not moved. Concurrent runs are locked out. Never touches
#  server/config.local.php or server/storage/ — your token, memory and
#  logs survive every deploy.
# ============================================================
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")/.." && pwd)"
ENVFILE="$APP_DIR/deploy/deploy.env"      # key=value, shared with webhook.php, git-ignored

# settings: explicit environment > deploy/deploy.env > default
env_get() { [ -f "$ENVFILE" ] || return 0; sed -n "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*//p" "$ENVFILE" | tail -n 1 | tr -d "\"'" | sed 's/[[:space:]]*$//'; }
BRANCH="${EON_BRANCH:-$(env_get branch)}";      BRANCH="${BRANCH:-main}"
REMOTE="${EON_REMOTE:-origin}"
PUBLISH="${EON_PUBLISH_DIR:-$(env_get publish)}"
PHP_BIN="${EON_PHP:-$(env_get php)}"
NOTIFY="${EON_NOTIFY:-$(env_get notify)}";      NOTIFY="${NOTIFY:-1}"
LOG="$APP_DIR/deploy/deploy.log"
LOCK="$APP_DIR/deploy/.deploy.lock"
FORCE=0
QUIET=0

# the php binary is needed by post-deploy and by the failure notice
if [ -z "$PHP_BIN" ]; then
  for c in php php8.3 php8.2 /usr/bin/php /opt/alt/php83/usr/bin/php /opt/alt/php82/usr/bin/php; do
    if command -v "$c" >/dev/null 2>&1; then PHP_BIN="$c"; break; fi
  done
fi

# things that belong to the live site, not to the repository
KEEP=(server/config.local.php server/storage server/vendor deploy/deploy.log deploy/deploy.env deploy/state.json deploy/.webhook.last .well-known)

for a in "$@"; do
  case "$a" in
    --force)      FORCE=1 ;;
    --quiet|-q)   QUIET=1 ;;
    --branch=*)   BRANCH="${a#*=}" ;;
    --publish=*)  PUBLISH="${a#*=}" ;;
    --help|-h)    sed -n '2,26p' "${BASH_SOURCE[0]:-$0}"; exit 0 ;;
    *)            echo "unknown option: $a" >&2; exit 64 ;;
  esac
done

say() {
  printf '%s  %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >> "$LOG" 2>/dev/null || true
  [ "$QUIET" = 1 ] || printf '%s\n' "$*"
}
die() {
  say "FAILED: $*"
  # tell the boss once, through the same notify config the cron jobs use
  if [ "$NOTIFY" = 1 ] && [ -n "$PHP_BIN" ] && [ -f "$APP_DIR/deploy/notify.php" ]; then
    "$PHP_BIN" "$APP_DIR/deploy/notify.php" "EON deploy failed" "$*" >/dev/null 2>&1 || true
  fi
  exit 1
}

# ---- one deploy at a time (mkdir is atomic on every filesystem) ----
if ! mkdir "$LOCK" 2>/dev/null; then
  if [ -d "$LOCK" ] && [ -z "$(find "$LOCK" -maxdepth 0 -mmin -10 2>/dev/null)" ]; then
    rmdir "$LOCK" 2>/dev/null || true
    mkdir "$LOCK" 2>/dev/null || { say "another deploy is running — skipping"; exit 0; }
    say "cleared a stale deploy lock"
  else
    [ "$QUIET" = 1 ] || echo "another deploy is running — skipping"
    exit 0
  fi
fi
trap 'rmdir "$LOCK" 2>/dev/null || true' EXIT INT TERM

cd "$APP_DIR"
[ -d .git ] || die "$APP_DIR is not a git checkout — see deploy/README.md"
command -v git >/dev/null 2>&1 || die "git is not available on this host (enable SSH access in hPanel)"

# ---- has GitHub moved? one network call, no objects transferred ----
remote_sha="$(git ls-remote "$REMOTE" "refs/heads/$BRANCH" 2>/dev/null | awk 'NR==1{print $1}')" || true
[ -n "$remote_sha" ] || die "cannot reach $REMOTE ($BRANCH) — check the deploy key / network"
local_sha="$(git rev-parse HEAD)"

need_publish=0
if [ -n "$PUBLISH" ] && [ ! -f "$PUBLISH/index.html" ]; then
  need_publish=1                     # the live folder is missing or was wiped — rebuild it
  say "publish target is empty — rebuilding $PUBLISH"
fi

if [ "$remote_sha" = "$local_sha" ] && [ "$FORCE" = 0 ] && [ "$need_publish" = 0 ]; then
  [ "$QUIET" = 1 ] || echo "up to date (${local_sha:0:7})"
  exit 0
fi

if [ "$remote_sha" != "$local_sha" ]; then
  say "deploying ${local_sha:0:7} → ${remote_sha:0:7} ($BRANCH)"
  git fetch --quiet --prune "$REMOTE" "$BRANCH" || die "git fetch failed"
  git reset --hard --quiet "$REMOTE/$BRANCH"    || die "git reset failed"
fi
new_sha="$(git rev-parse HEAD)"
say "checkout ${new_sha:0:7} — $(git log -1 --pretty=%s)"

# ---- publish into the document root (layout B) ----
LIVE_DIR="$APP_DIR"
if [ -n "$PUBLISH" ]; then
  mkdir -p "$PUBLISH" || die "cannot create $PUBLISH"
  LIVE_DIR="$PUBLISH"
  ex=(--exclude=.git --exclude=.github --exclude=deploy/.deploy.lock)
  for k in "${KEEP[@]}"; do ex+=(--exclude="$k"); done
  if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete "${ex[@]}" "$APP_DIR"/ "$PUBLISH"/ || die "rsync to $PUBLISH failed"
  else
    # no rsync on this host: copy the tracked tree, leaving the kept paths alone
    tmp="$(mktemp -d)"
    git archive --format=tar HEAD | tar -x -C "$tmp" || die "git archive failed"
    ( cd "$tmp" && tar -cf - . ) | ( cd "$PUBLISH" && tar -xf - ) || die "copy to $PUBLISH failed"
    rm -rf "$tmp"
  fi
  say "published to $PUBLISH"
fi

# ---- post-deploy: folders, packages, schema, cache, health ----
if [ -n "$PHP_BIN" ] && [ -f "$LIVE_DIR/deploy/post-deploy.php" ]; then
  # hand the commit over: the published folder has no .git, and shell_exec is off on many hosts
  out="$(EON_DEPLOY_SHA="${new_sha:0:7}" "$PHP_BIN" "$LIVE_DIR/deploy/post-deploy.php" 2>&1)" || say "post-deploy reported problems"
  while IFS= read -r line; do [ -n "$line" ] && say "  $line"; done <<< "$out"
elif [ -z "$PHP_BIN" ]; then
  say "  no php binary found — skipped post-deploy (set EON_PHP=/path/to/php)"
fi

say "live: ${new_sha:0:7}"
[ -f "$LOG" ] && { tail -n 500 "$LOG" > "$LOG.tmp" 2>/dev/null && mv "$LOG.tmp" "$LOG"; } || true
exit 0
