# Deployment

How to build and run the app in production. Sail is a development tool and is not meant for production, so this guide targets a plain Linux server.
For local setup see the [README](../README.md); for design decisions see [architecture.md](architecture.md).

## What runs in production

| Process | Command | Purpose |
|---|---|---|
| Web | nginx + PHP-FPM | serves the SPA and the API from `public/` |
| Queue worker | `php artisan queue:work` | sends broadcast events to Reverb |
| Reverb | `php artisan reverb:start` | WebSocket server for real-time notifications |
| Database | MySQL 8 | data, sessions, cache and the job queue |

There are no scheduled tasks, so no cron entry is needed.

## Server requirements

- PHP 8.3 or newer, with the extensions Laravel requires plus `pdo_mysql` and `curl`
- Composer 2
- Node.js 22 LTS or newer, only to build the frontend; it can run in CI instead of on the server
- MySQL 8
- nginx with HTTPS, and Supervisor to keep the queue worker and Reverb running

## Environment

Start from `.env.example` and change at least these values:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://talks.example.com
LOG_LEVEL=warning

DB_HOST=127.0.0.1
DB_DATABASE=talk_proposals
DB_USERNAME=talk_proposals
DB_PASSWORD=<strong password>

SESSION_SECURE_COOKIE=true

REVERB_APP_ID=<random number>
REVERB_APP_KEY=<random string>
REVERB_APP_SECRET=<random string>
REVERB_SERVER_HOST=127.0.0.1
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=talks.example.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

- **`APP_URL` must be the real address.** Sanctum treats requests from this host as the SPA, so a wrong value breaks login.
- **New Reverb credentials are mandatory.** The values in `.env.example` are public. Generate new ones, for example with `openssl rand -hex 16`.
- **Two kinds of Reverb settings:**
  - `REVERB_HOST`, `REVERB_PORT` and `REVERB_SCHEME` are how Laravel reaches Reverb on the same machine;
  - `VITE_REVERB_*` are how browsers reach it through nginx over `wss://`.
- **`REVERB_SERVER_HOST=127.0.0.1`** keeps port 8080 closed to the outside. Only nginx talks to it.
- **`VITE_*` values are compiled into the JavaScript bundle** by `npm run build`. Build with the production `.env`, and rebuild after changing them.
- **Search.** For Algolia set `SCOUT_DRIVER=algolia`, `ALGOLIA_APP_ID` and `ALGOLIA_SECRET`. Without them `SCOUT_DRIVER=database` works with no extra service.
- **Do not commit `.env`.** It holds the app key, database password and API secrets.

## First deployment

```bash
git clone https://github.com/beloofficial/talk-proposals.git /var/www/talk-proposals
cd /var/www/talk-proposals
cp .env.example .env            # then edit it as above
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force
chown -R www-data:www-data storage bootstrap/cache
```

Then run the [deploy steps](#deploying-an-update) below.
If Algolia is enabled, index existing proposals once: `php artisan scout:import "App\Models\Proposal"`.

**Seeders are for development.** They use Faker, which is a dev dependency and is not installed with `--no-dev`. A production database starts empty, and users sign up through the app.
For a demo server with sample data, install with dev dependencies (`composer install` without `--no-dev`) and run `php artisan migrate --seed --force` once.

## Deploying an update

```bash
cd /var/www/talk-proposals
php artisan down
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan reverb:restart
php artisan up
```

- **`php artisan optimize`** caches config, routes, events and views. After it, `.env` is not read at runtime: code reads `config()`, and an architecture test makes sure no code calls `env()` directly.
- **`queue:restart` and `reverb:restart`** make the long-running processes exit gracefully. Supervisor starts them again with the new code.
- **`--force`** confirms migrations in production. Migrations that already ran are skipped, so the step is safe on every deploy.
- **`storage:link` is not needed.** Uploaded PDFs are deliberately not public.
- **Zero downtime.** `down` and `up` show a 503 page during the deploy. To avoid it, use release directories (Envoyer, Deployer or a CI pipeline) with `.env` and `storage/` shared between releases.

## nginx

The standard Laravel site, plus a WebSocket proxy for Reverb on the same domain:

```nginx
server {
    listen 443 ssl;
    http2 on;
    server_name talks.example.com;
    root /var/www/talk-proposals/public;
    index index.php;

    # ssl_certificate ... ; ssl_certificate_key ... ;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    client_max_body_size 8M;

    location /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_read_timeout 120s;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

- **`client_max_body_size`** must be above the 4 MB PDF limit, or nginx rejects uploads before Laravel can return a validation error. PHP's `upload_max_filesize` and `post_max_size` must be at least as large.
- **`/app/`** is the path the browser opens the WebSocket on. Laravel publishes events to Reverb directly on `127.0.0.1:8080`, so that traffic does not go through nginx.
- **Load balancer.** If TLS terminates on a load balancer in front of nginx, configure trusted proxies (`$middleware->trustProxies(...)` in `bootstrap/app.php`) so Laravel sees HTTPS and the client IP. The IP matters for login throttling.

## Supervisor

`/etc/supervisor/conf.d/talk-proposals.conf`:

```ini
[program:talk-proposals-queue]
command=php /var/www/talk-proposals/artisan queue:work --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopwaitsecs=60
stdout_logfile=/var/www/talk-proposals/storage/logs/queue.log
redirect_stderr=true

[program:talk-proposals-reverb]
command=php /var/www/talk-proposals/artisan reverb:start
user=www-data
autostart=true
autorestart=true
minfds=10000
stdout_logfile=/var/www/talk-proposals/storage/logs/reverb.log
redirect_stderr=true
```

Load it with `supervisorctl reread && supervisorctl update`.

- **`queue:work`, not `queue:listen`.** Development uses `queue:listen`, which reloads the code on every job. In production `queue:work` keeps the app in memory and is restarted on deploy.
- **`minfds`** raises the open-file limit, because every WebSocket connection uses a file descriptor.

## Security checklist

- `APP_DEBUG=false`, so stack traces and environment values never reach the browser.
- HTTPS everywhere, and `SESSION_SECURE_COOKIE=true` so the session cookie is never sent over plain HTTP.
- Fresh `APP_KEY` and Reverb credentials. The dev values from `.env.example` must not reach production.
- Only ports 80 and 443 are public. MySQL (3306) and Reverb (8080) listen on localhost.
- `storage/app/private` is outside `public/`; PDFs are served only through the API with a permission check.
- API docs at `/docs/api` are closed automatically outside `APP_ENV=local`.
- Optional hardening: Reverb accepts connections from any origin (`allowed_origins` in `config/reverb.php`). Private channels already need a signed subscription, but the list can be narrowed to the app's domain.
- Back up the database and `storage/app/private`.

## Health check

`GET /up` returns 200 when the app boots. Use it for uptime monitoring or the load balancer health check.

## Scaling beyond one server

The setup above is for a single server. For several web servers:

- Move PDFs to shared storage such as S3. The code uses the `local` disk by name in `ProposalService` and `ProposalController`, so this means switching that disk name to a new S3 disk.
- Move sessions, cache and the queue to Redis.
- Enable Reverb's Redis scaling (`REVERB_SCALING_ENABLED=true`) to run more than one Reverb process.
