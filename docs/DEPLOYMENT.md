# Deployment Guide

Target: cPanel shared hosting with WordPress and PHP. Production must not need a Node.js server, so the frontend is always built before upload.

## 1. Build and package

```bash
cd src/fashionos
npm ci
npm run build
composer install --no-dev --no-interaction   # only if Composer dependencies are added later
```

`npm run build` writes `public/build/manifest.json` and hashed JS/CSS. The PHP asset loader reads that manifest, so never deploy a stale `manifest.json` with fresh assets.

Package the plugin without development files, using `.distignore` as the exclusion list. With `rsync`:

```bash
rsync -av --delete \
  --exclude-from=src/fashionos/.distignore \
  --exclude='node_modules' \
  src/fashionos/ build/fashionos/
```

Upload `build/fashionos/` to `wp-content/plugins/fashionos` over SFTP.

## 2. Deploy

1. Back up the database and the current plugin directory.
2. Upload the new plugin files, keeping `wp-content/uploads/fashionos/` (private files, added in Phase 2) intact.
3. Visit **Plugins** and confirm FashionOS is active. Reactivate it if the plugin was replaced, so activation hooks run.
4. Confirm `{prefix}fos_migrations` contains a row per shipped migration and that `fashionos_db_version` matches the release.

Schema changes ship only as migrations; do not alter tables manually in phpMyAdmin as a normal process.

## 3. Verify after deploy

- Open the application page containing `[fashionos_app]` while signed in; the shell must render, not the "not built" notice.
- `GET /wp-json/fashionos/v1/system/health` returns the expected plugin version, applied migrations and build state.
- Anonymous and signed-out visits are refused or prompted to sign in; no private data is served.
- Browser console and network have no failed asset requests; asset responses use correct content types.
- For any release that adds tenant data endpoints, re-run the cross-tenant access checks with two tenants before announcing the release.

## 4. Cron and background work

There is no queue in Phase 0. Phase 7 adds a database-backed queue processed by cPanel Cron; that job must carry and validate tenant context. Until then no cron entry is needed.

## 5. Rollback

1. Restore the previous plugin directory from backup.
2. Restore the database only when a release included a migration that must be undone; migrations are forward-only, so prefer a corrective release over a manual rollback.
3. Re-check the health endpoint and the shell after rollback.

## 6. Backups

Back up the database, private files and configuration together, keep an independent copy off the hosting account, and test restoration periodically. A backup that has never been restored is not a verified recovery plan.

## 7. Hosting checks before the first production deploy

Confirm PHP 8.1+ (8.3 preferred), MySQL/MariaDB version, InnoDB and `dbDelta` behaviour, memory and upload limits, writable private storage, HTTPS, cron access, SFTP, backup policy and database/disk quotas. Record the results so future releases do not assume untested limits.
