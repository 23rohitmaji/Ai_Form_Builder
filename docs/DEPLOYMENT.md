# Deployment Notes

## Local Review

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Login seed account:

```text
email: admin@example.com
password: password123
```

## Docker

```bash
docker-compose up --build
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate --seed
```

## Vercel

The project includes `vercel.json` for the PHP runtime and Vite build output.

Required Vercel environment variables:

```env
APP_NAME="AI Form Builder"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://your-vercel-domain.vercel.app

DB_CONNECTION=mysql
DB_HOST=your-aiven-host
DB_PORT=17272
DB_DATABASE=defaultdb
DB_USERNAME=avnadmin
DB_PASSWORD=your-aiven-password

LLM_PROVIDER=groq
GROQ_API_KEY=your-groq-key
GROQ_MODEL=llama-3.3-70b-versatile

LOG_CHANNEL=stderr
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
VIEW_COMPILED_PATH=/tmp/views
APP_CONFIG_CACHE=/tmp/config.php
APP_EVENTS_CACHE=/tmp/events.php
APP_PACKAGES_CACHE=/tmp/packages.php
APP_ROUTES_CACHE=/tmp/routes.php
APP_SERVICES_CACHE=/tmp/services.php
```

Do not set `MYSQL_ATTR_SSL_CA` unless you upload a real CA certificate path. A blank value should be omitted.

## Migrations

Run migrations against the production database before sharing the app:

```bash
php artisan migrate --force
php artisan db:seed --force
```

For Vercel, run the commands from a local machine configured with the same Aiven database variables, or use a temporary deployment shell if available.

## Public URLs

- Builder console: `/`
- Public respondent form: `/f/{slug}`
- Vercel API alias: `/xapi/*`
- Local/API route group: `/api/*`

## Operational Checks

After deployment:

```bash
curl -i -X POST "https://your-domain.vercel.app/xapi/register" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"Smoke Test","email":"smoke@example.com","password":"password123"}'
```

Expected response: HTTP `201` with a JSON `token` and `user`.
