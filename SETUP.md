# Restaurant OS — setup (Laravel 11)

## Requirements

- **PHP 8.2+** and common extensions (`openssl`, `pdo`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath` recommended for Stripe)
- **Composer 2**
- **Node.js 18+** and **npm**
- A **Pusher** account (free tier) for real-time orders, or **Laravel Reverb** (not pre-wired; you can switch `BROADCAST_CONNECTION` in `.env`)

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
| `BROADCAST_CONNECTION` | Set to `pusher` for live dashboard (or `log` for local testing without Pusher). |
| `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER` | From your Pusher app. |
| `VITE_PUSHER_*` | Same key/cluster for the frontend (see `.env.example`). |
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

## 6. Run the app

In separate terminals:

```bash
php artisan serve
```

```bash
npm run dev
```

Visit `/login`. Open **Dashboard** on two browsers: placing an order triggers **`NewOrderCreated`** on the `orders` channel — you should hear a short tone and see the row blink when broadcasting is enabled.

> **Queues:** The order event uses `ShouldBroadcastNow`, so no queue worker is required for broadcasts. If you add other queued events, run `php artisan queue:work`.

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

- **Broadcasting silent:** Confirm `BROADCAST_CONNECTION=pusher`, Pusher credentials, `npm run dev` running, and browser console for Echo/Pusher errors.
- **403 on admin routes:** Log in as **admin** (`admin@restaurant.test`).
- **SQLite locked:** Avoid editing the DB file while requests run; use MySQL for heavier concurrency.
