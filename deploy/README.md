# EON — push to GitHub, live on `eon.gulfrabit.com`

Four files, one job: bring the live site to the newest commit on `main`.

| File | What it is |
| --- | --- |
| `deploy.sh` | the deploy itself — asks GitHub for the branch head, updates the checkout, publishes it (layout B), runs `post-deploy.php`. Run by cron, by the webhook, or by hand. |
| `post-deploy.php` | after every checkout: runtime folders, `composer install` when `vendor/` is missing, EON's schema when its database is reachable, cache clear, and a one-line health report into `state.json`. Idempotent. |
| `webhook.php` | optional instant deploy: GitHub POSTs here on every push (HMAC-SHA256 verified) and it runs `deploy.sh`. |
| `deploy.env.example` | copy to `deploy.env` on the server (git-ignored): branch, publish dir, php binary, webhook secret. |

`deploy.log` (the last 500 lines), `state.json`, `deploy.env` and the lock files are
created on the server and never committed. `.htaccess` denies the whole folder to
the web except `webhook.php`.

## Which layout?

**A — the checkout is the document root.** Hostinger gave the subdomain its own
folder and nothing else writes there:

```bash
cd ~/domains/eon.gulfrabit.com
rm -rf public_html && git clone https://github.com/imran-me/eon.git public_html
public_html/deploy/deploy.sh                       # first deploy + setup report
```

**B — the checkout lives outside the web root and is published into it.** Use this
when the subdomain resolves *inside* another site's `public_html`
(`~/domains/gulfrabit.com/public_html/eon`), because that site's own deployment can
re-sync the folder underneath you:

```bash
git clone https://github.com/imran-me/eon.git ~/eon-src
EON_PUBLISH_DIR=~/domains/gulfrabit.com/public_html/eon ~/eon-src/deploy/deploy.sh
```

`server/config.local.php`, `server/storage/`, `server/vendor/` and this folder's
runtime files are never overwritten by a deploy, in either layout.

## Cron

```
*/5 * * * * /bin/bash ~/domains/eon.gulfrabit.com/public_html/deploy/deploy.sh --quiet          # layout A
*/5 * * * * EON_PUBLISH_DIR=~/domains/gulfrabit.com/public_html/eon /bin/bash ~/eon-src/deploy/deploy.sh --quiet   # layout B
```

It exits after one `git ls-remote` when GitHub has not moved, so every minute is
fine too. `--quiet` keeps cron mail empty; everything lands in `deploy.log`.

## Checking it

```bash
tail -n 30 deploy/deploy.log
cat deploy/state.json                              # commit, php, token, erp_db, eon_db, llm, source
curl -s https://eon.gulfrabit.com/server/api/health.php   # "commit" is what the site is running
deploy/deploy.sh --force                           # redeploy the same commit
```

Full guide, including the read-only MySQL user, the token and the webhook:
`docs/deploy.md` → **Option C**.
