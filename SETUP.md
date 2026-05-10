# Restaurant OS — setup (Laravel 11)

## Requirements

- **PHP 8.2+** and common extensions (`openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` recommended for Stripe)
- **Composer 2**
- **Node.js 18+** and **npm**
- **Laravel Reverb** (included) for zero-cost, self-hosted WebSockets — no Pusher or other paid services required

## 1. Install dependencies

```bash
cd /path/to/RestaurantApp
composer install
npm install
```

## 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` at minimum:

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Must match the URL you use in the browser (affects Sanctum / sessions). |
| `BROADCAST_CONNECTION` | Set to **`reverb`** for the live dashboard (use `null` or `log` to disable WebSockets). |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | Generate once with `php artisan reverb:install` or any random strings (must stay consistent). |
| `REVERB_HOST` | Hostname **the browser uses** to reach Reverb (e.g. `127.0.0.1` or your LAN IP like `192.168.x.x`). |
| `REVERB_PORT` | Client port (often `8080` for HTTP/ws, `443` behind HTTPS terminating proxy). |
| `REVERB_SCHEME` | `http` locally, `https` when TLS terminates on Nginx. |
| `REVERB_SERVER_HOST` | `0.0.0.0` on VPS so Reverb listens on all interfaces. |
| `REVERB_SERVER_PORT` | Internal port Reverb listens on (default `8080`; match Nginx `proxy_pass`). |
| `DB_*` | Default is SQLite; ensure `database/database.sqlite` exists: `touch database/database.sqlite` |
| `STRIPE_KEY` / `STRIPE_SECRET` | Optional; enable Stripe Checkout for the `online` payment method. |
| `RESTAURANT_TAX_RATE` | Decimal, e.g. `0.08` for 8% tax on invoices. |

## 3. Database

```bash
touch database/database.sqlite   # if using sqlite
php artisan migrate
php artisan db:seed
```

Demo users (password **`password`**):

- **Admin:** `admin@restaurant.test` — full CRUD (categories, menu, tables)
- **Staff:** `staff@restaurant.test` — orders, billing, dashboard only

## 4. Storage link (menu images)

```bash
php artisan storage:link
```

## 5. Frontend assets

Development (hot reload):

```bash
npm run dev
```

Production build:

```bash
npm run build
```

## 6. Real-time (Laravel Reverb — self-hosted)

Events broadcast on the **`dashboard`** channel include `OrderPlaced`, `OrderPreparing`, `OrderCompleted`, `CheckoutCompleted`, `OrderUpdated`, and `CheckoutRequested`. The dashboard announces updates with the browser **Speech Synthesis API** (free, offline-capable). Tap the page once after load so the browser allows speech; use **Voice settings** for volume, speed, mute, and a **Test voice** button.

Generate app credentials once (writes keys to `.env` if missing):

```bash
php artisan reverb:install
```

Development — run **three** processes (example):

```bash
php artisan serve --host 0.0.0.0
```

```bash
php artisan reverb:start
```

```bash
npm run dev
```

Set `BROADCAST_CONNECTION=reverb`, then align networking:

- Use the **same host** in `REVERB_HOST` that the browser uses (for LAN testing, use your machine IP, not only `localhost`).
- Match `REVERB_PORT` / `REVERB_SCHEME` to how Echo connects (`http` + port `8080` is typical locally).

Production (Ubuntu): run Reverb under **Supervisor** and proxy WebSockets through **Nginx**. Example configs live in `deployment/supervisor-reverb.conf` and `deployment/nginx-reverb-websocket.conf`. After editing Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart restaurant-reverb:*
```

Restart Reverb after deploy:

```bash
sudo supervisorctl restart restaurant-reverb:*
# or: php artisan reverb:start (foreground only)
```

Visit `/login` → **Dashboard**. With Reverb running, the badge shows **Live**; otherwise **Poll** (12s HTTP refresh still applies).

> **Queues:** Broadcast events use `ShouldBroadcastNow`, so no queue worker is required for realtime. If you add queued jobs, run `php artisan queue:work`.

Optional **legacy Pusher**: set `BROADCAST_CONNECTION=pusher` and fill `PUSHER_*` — dashboard listens on the same `dashboard` channel names.

## 7. REST API (Sanctum)

Base path: `/api` (Laravel’s default `api` prefix).

- `GET /api/menu` — public menu with categories and items
- `POST /api/auth/token` — body: `{ "email", "password" }` returns a **Bearer** token
- With `Authorization: Bearer {token}`:
  - `GET/POST /api/orders`, `GET /api/orders/{id}`, `PATCH /api/orders/{id}/status`, `POST /api/orders/{id}/payments`

Example:

```bash
curl -s -X POST http://127.0.0.1:8000/api/auth/token \
  -H "Content-Type: application/json" \
  -d '{"email":"staff@restaurant.test","password":"password"}'
```

## 8. Laravel Breeze

`laravel/breeze` is included as a **dev** dependency for compatibility. This project already ships **Blade login/register** in the Breeze style. You do **not** need to run `breeze:install` unless you want to regenerate scaffolding (it would conflict with existing routes).

## 9. Troubleshooting

- **Broadcasting silent:** Confirm `BROADCAST_CONNECTION=reverb`, `php artisan reverb:start` is running, `REVERB_HOST` matches how you open the site (LAN IP vs localhost), firewall allows the Reverb port, and browser DevTools → Network → WS for errors.
- **No voice:** Click or tap the dashboard once (browser autoplay policy), ensure **Speech on** is checked and **Voice** is not muted, then use **Test voice**. Safari/iOS may require an extra tap on first load.
- **403 on admin routes:** Log in as **admin** (`admin@restaurant.test`).
- **SQLite locked:** Avoid editing the DB file while requests run; use MySQL for heavier concurrency.
