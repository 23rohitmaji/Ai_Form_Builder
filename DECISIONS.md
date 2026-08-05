# Technical Decisions

## Architecture

- Laravel is the source of truth for authentication, form schemas, dynamic validation, submissions, analytics, queues, and AI integration.
- React + Vite renders the authenticated builder console and public respondent form pages.
- REST endpoints are exposed under `/api/*` for local/API clients and mirrored under `/xapi/*` for Vercel routing compatibility.

## Authentication

- The app uses bearer tokens stored hashed in `api_tokens`.
- Plain tokens are returned only once at register/login and are sent through the `Authorization: Bearer` header.
- Tokens are not stored in the database in plain text.
- The React app stores the active token under `session_token` and non-sensitive display data under `user_details`.

## Database

- The schema separates `forms`, `form_fields`, and `form_submissions`.
- `form_fields` stores ordered schema configuration, while `form_submissions.answers` stores immutable respondent payloads.
- JSON columns are used for flexible field options, validation rules, settings, answers, and metadata.
- Form slugs are unique so public URLs are stable.
- Field keys are unique per form so submission answers can be exported into stable CSV columns.

## Dynamic Validation

- Public submissions are validated at runtime from the saved field schema.
- Supported validation includes required, email, URL, numeric min/max, text length, regex, file extensions, and file size metadata.
- Unknown answer keys are rejected to avoid accepting data outside the published schema.

## AI Integration

- `AiFormDesigner` supports Groq and OpenAI providers through environment configuration.
- If no LLM key is configured, deterministic fallback schemas keep local setup and automated tests reliable.
- AI editing accepts an existing schema plus an instruction, allowing flows such as adding emergency contact fields, making phone required, or translating labels.

## Queue And Cache

- Submissions are accepted quickly and processed through `ProcessFormSubmission`.
- Public form reads and analytics are cached and invalidated when forms or submissions change.
- Redis/Horizon are supported for production-like queue monitoring, while local defaults use database/array drivers for easy review.

## Deployment

- Vercel routes all traffic through `api/index.php`.
- `/xapi/*` routes are kept stateless by excluding web session/cookie/CSRF middleware.
- Production should use hosted MySQL, synchronous queues or Redis, and `LOG_CHANNEL=stderr`.

## Tradeoffs

- File uploads store file metadata in the JSON answer payload for the assessment demo. A production system should move binary files to S3-compatible object storage and store signed references.
- Token storage in localStorage is simple for the assessment. A production SaaS should consider HttpOnly cookies with a carefully tested stateless API deployment path.
- CSV sample import files are included as reference payloads; the implemented product requirement focuses on CSV export.
