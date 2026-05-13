# Restaurant OS — setup (Laravel 11)

## Requirements

- **PHP 8.2+** and common extensions (`openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` recommended for Stripe)
- **Composer 2**
- **Node.js 18+** and **npm**

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
| `BROADCAST_CONNECTION` | Use **`log`** (default in this repo) so broadcast calls are logged only; WebSockets are not used. |
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

## 6. Dashboard updates (HTTP polling)

The floor plan and alerts poll the server on a short interval (JSON) and refresh the canvas without WebSockets. Guest order views refresh the order panel on a timer. Use **Voice settings** on the dashboard for speech alerts; tap the page once after load so the browser allows audio.

Typical local development:

```bash
php artisan serve --host 0.0.0.0
```

```bash
npm run dev
```

> **Queues:** If you add queued jobs, run `php artisan queue:work`.

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

- **Dashboard feels delayed:** Updates rely on HTTP polling; wait a few seconds or refresh the page.
- **No voice:** Click or tap the dashboard once (browser autoplay policy), ensure **Speech on** is checked and **Voice** is not muted, then use **Test voice**. Safari/iOS may require an extra tap on first load.
- **403 on admin routes:** Log in as **admin** (`admin@restaurant.test`).
- **SQLite locked:** Avoid editing the DB file while requests run; use MySQL for heavier concurrency.
