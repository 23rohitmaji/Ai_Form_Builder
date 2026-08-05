# AI Form Builder API

Laravel backend assessment for a dynamic form builder with REST APIs, schema-driven validation, queue processing, caching, AI-assisted form generation, and submission analytics.

## Required Deliverables

- `README.md` - setup, feature list, API quick start, and reviewer notes.
- `DECISIONS.md` - architecture, database, authentication, AI, queue/cache, deployment, and tradeoff decisions.
- `database/migrations/` - complete schema migrations for users, forms, fields, submissions, API tokens, queues, cache, and jobs.
- `database/seeders/` - idempotent sample data seeders with a demo admin, rich sample form, and submission.
- `samples/forms_import.json` - sample form payload for API import/create testing.
- `samples/submissions_import.csv` - sample submission data matching exported CSV column shape.
- `docs/API.md` - endpoint examples for auth, form CRUD, public forms, submissions, export, and AI.
- `docs/DEPLOYMENT.md` - local, Docker, Vercel, Aiven MySQL, migration, and smoke-test notes.

## Features

- React + Vite frontend for the authenticated builder dashboard and public form pages.
- Token-based API authentication with hashed tokens.
- Authenticated form CRUD with ordered dynamic fields.
- Drag-and-drop/click-to-add form builder with reorder, duplicate, inline edit, and delete.
- Field palette supports text, textarea, number, email, phone, URL, date, dropdown, radio, checkbox, file upload, section heading, rating, and yes/no.
- Per-field configuration for label, key, placeholder, help text, default value, required flag, options, sections, steps, and validation rules.
- Public published-form endpoint for embedding/rendering forms.
- Public respondent UI at `/f/{slug}`.
- Public submission endpoint with runtime validation from the saved field schema.
- Per-form `store_submissions` toggle for privacy-sensitive forms.
- Submission listing with pagination, search, and CSV export.
- Dashboard submission counts and analytics refresh automatically after public submissions.
- Queue-backed submission processing through `ProcessFormSubmission`.
- Cached public form reads and cached owner analytics.
- CSV export for submissions.
- AI form-schema generation through OpenAI when configured, with a deterministic local fallback for offline review.
- AI editing for existing forms, including instructions such as adding emergency contact fields, making phone required, and translating labels.
- Horizon installed for Redis queue monitoring in production-like runs.
- Feature tests covering auth, form creation, public submission, validation, analytics, and AI fallback.

## Local Setup

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

The default `.env.example` uses SQLite, database queues, and database cache so reviewers can run the app without MySQL or Redis. For queue processing in a second terminal:

```bash
php artisan queue:work
```

Seeded reviewer account:

```text
email: admin@example.com
password: password123
```

## Docker Setup

Docker support is included for MySQL and Redis:

```bash
docker-compose up --build
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate --seed
```

The API runs at `http://localhost:8000`. MySQL is exposed on `localhost:3307`; Redis is exposed on `localhost:6379`.

## Frontend Screens

- `/` - React builder dashboard with register/login, form list, form editor, AI schema generator, analytics, submissions, and CSV export.
- `/f/{slug}` - Public React form renderer for respondents.
- `/api/public/forms/{slug}` - Public JSON schema endpoint for external clients.
- `/api/public/forms/{slug}/submissions` - Public JSON submission endpoint.

## AI Configuration

Set these variables to enable real AI generation with Groq:

```env
LLM_PROVIDER=groq
GROQ_API_KEY=your_groq_key
GROQ_MODEL=llama-3.3-70b-versatile
```

You can get a Groq key from `https://console.groq.com/keys`.

OpenAI is still supported if you prefer it later:

```env
LLM_PROVIDER=openai
OPENAI_API_KEY=your_openai_key
OPENAI_MODEL=gpt-5-mini
```

Without a configured provider key, `POST /api/ai/forms` returns a deterministic schema based on prompt keywords so tests and demos still work offline.

## API Quick Start

Register and copy the returned token:

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Rohit","email":"rohit@example.com","password":"password123"}'
```

Create a published form:

```bash
curl -X POST http://localhost:8000/api/forms \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Workshop Registration",
    "slug": "workshop-registration",
    "is_published": true,
    "fields": [
      {"key":"full_name","label":"Full name","type":"text","is_required":true},
      {"key":"email","label":"Email","type":"email","is_required":true},
      {"key":"attendance_mode","label":"Attendance mode","type":"select","is_required":true,"options":["Online","In person"]}
    ]
  }'
```

Submit to the public form:

```bash
curl -X POST http://localhost:8000/api/public/forms/workshop-registration/submissions \
  -H "Content-Type: application/json" \
  -d '{"answers":{"full_name":"Ada Lovelace","email":"ada@example.com","attendance_mode":"Online"}}'
```

Useful endpoints:

```text
POST   /api/register
POST   /api/login
POST   /api/logout
POST   /api/ai/forms
GET    /api/forms
POST   /api/forms
GET    /api/forms/{form}
PUT    /api/forms/{form}
DELETE /api/forms/{form}
GET    /api/public/forms/{slug}
POST   /api/public/forms/{slug}/submissions
GET    /api/forms/{form}/submissions
GET    /api/forms/{form}/submissions/{submission}
GET    /api/forms/{form}/submissions/export
GET    /api/forms/{form}/analytics
```

Additional examples are available in `docs/API.md`.

## Tests And Quality

```bash
php artisan test
./vendor/bin/pint
npm run build
```

## Design Notes

- The `forms`, `form_fields`, and `form_submissions` schema separates mutable form design from immutable submission payloads.
- Field keys are unique per form and indexed with position for fast render ordering.
- Submission validation is generated at runtime by `App\Services\FormSchemaService`, keeping validation rules consistent with form configuration.
- Public form reads and analytics are cached with targeted invalidation on updates and processed submissions.
- Queue processing is isolated in `App\Jobs\ProcessFormSubmission`, so heavier workflows such as notifications, scoring, enrichment, or webhook delivery can be added without slowing the public submission endpoint.
