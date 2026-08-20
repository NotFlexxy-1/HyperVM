# HyperVM

Proxmox VE control panel — Laravel 11 + Inertia + React (TypeScript) + Tailwind.
Admin area (nodes, allocations, plans, servers, users, roles, branding) and a client
area (console, resources, network, media, backups, activity, sub-users, 2FA, API keys).
Every hypervisor operation talks to the real Proxmox VE API — nothing is mocked.

## Requirements

- PHP 8.2+ with `bcmath curl gd intl mbstring openssl pdo_mysql tokenizer xml zip`
- Composer 2, Node 20+, MySQL 8 / MariaDB 10.6+, Redis (recommended)
- A Proxmox VE 7/8 host reachable from the panel

## Install

```bash
git clone https://github.com/NotFlexxy-1/hyperVM /var/www/hypervm && cd /var/www/hypervm

composer install --no-dev --optimize-autoloader
npm install && npm run build

cp .env.example .env
php artisan key:generate
# edit .env: APP_URL, DB_*, CACHE_STORE, SESSION_DRIVER, QUEUE_CONNECTION
php artisan migrate --seed
php artisan storage:link

php artisan hypervm:make-admin --email=you@example.com
```

Point your web server document root at `public/`. Then:

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Queue + scheduler (systemd / crontab)

```
* * * * * cd /var/www/hypervm && php artisan schedule:run >> /dev/null 2>&1
```

```
php artisan queue:work --queue=default --tries=3
```

## Proxmox credentials

On the Proxmox host:

```bash
pveum user add hypervm@pve
pveum aclmod / -user hypervm@pve -role Administrator
pveum user token add hypervm@pve panel --privsep 0
```

Add the node in **Admin → Nodes** using the API URL (`https://host:8006/api2/json`),
token ID (`hypervm@pve!panel`) and the token secret. Use **Test connection** to verify.

## Optional environment

| Key | Purpose |
| --- | --- |
| `HYPERVM_VERIFY_TLS` | Set `false` only for self-signed Proxmox certificates |
| `DISCORD_CLIENT_ID` / `DISCORD_CLIENT_SECRET` / `DISCORD_REDIRECT_URI` | Discord OAuth login |
| `HYPERVM_METRICS_CACHE_SECONDS` | Node metric cache window |

Branding (logo, favicon, colours, layout) and registration rules are managed at
runtime in **Admin → Settings** and stored in the database.

## Development

```bash
php artisan serve
npm run dev
composer test   # or: vendor/bin/phpunit
```

## API

Token-authenticated JSON API under `/api/v1` (Sanctum). Create keys in
**Account → API keys**, then send `Authorization: Bearer <token>`.

- `GET /api/v1/servers`, `GET /api/v1/servers/{uuid}`, `POST /api/v1/servers/{uuid}/power`
- `GET /api/v1/nodes`, `GET /api/v1/nodes/{id}/status`

## License

MIT
