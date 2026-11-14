# iconors.com

Laravel 12 (PHP 8.2+) site for iconors.com, hosted on Hostinger shared hosting.

## Layout (mirrors the server's `domains/iconors.com/` folder)
- `laravel/` — the app: `app/`, `routes/web.php`, `resources/views` (Blade), `resources/scss`, `config/`, `database/migrations`.
- `public_html/` — web root. `index.php` loads `../laravel/`. `build/` is compiled Vite output; `ico-ext/` holds hand-written JS/CSS (studio, tools).
- Packages: Cashier (Stripe), srmklive/paypal, spatie/laravel-permission.

## Not in git (exist only on the server / local disk)
- `laravel/.env` — secrets. Never commit, print or copy its values.
- `laravel/vendor/` — installed by composer on deploy.
- `laravel/storage/`, `public_html/storage/` — user uploads, logs, cache.
- `laravel/_backup-*` — old manual backups.

## Front-end build
There is no `package.json`/`vite.config.js` in this repo, so `public_html/build/` cannot be rebuilt here.
Make style/JS changes in `public_html/ico-ext/` or inline in Blade unless the Vite sources are added.

## Deploy
Push to `main` → GitHub Actions (`.github/workflows/deploy.yml`) rsyncs `laravel/` and `public_html/` to Hostinger
(never deletes server files), then runs `composer install --no-dev`, `php artisan migrate --force` and rebuilds caches.
- Work on a branch, open a PR, merge to `main` to go live.
- Database changes must be migrations in `laravel/database/migrations` (the DB only exists on Hostinger).
- Code must not call `env()` outside `config/` — config is cached on deploy.
- Deleting a file in git does not delete it on the server; remove it there by hand if needed.
