# API Reference

Base URLs:

- Local Laravel API: `http://localhost:8000/api`
- Vercel-safe API alias: `https://your-domain.vercel.app/xapi`

All authenticated endpoints require:

```http
Authorization: Bearer <token>
Accept: application/json
```

## Auth

### Register

```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"Reviewer","email":"reviewer@example.com","password":"password123"}'
```

### Login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"reviewer@example.com","password":"password123"}'
```

## Forms

### Create Form

Use `samples/forms_import.json` as a complete request body example.

```bash
curl -X POST http://localhost:8000/api/forms \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  --data @samples/forms_import.json
```

If using the sample file directly, post one object from the array.

### List Forms

```bash
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/forms
```

### Update Form

```bash
curl -X PUT http://localhost:8000/api/forms/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title":"Updated title","slug":"updated-title","fields":[{"key":"email","label":"Email","type":"email","is_required":true}]}'
```

### Delete Form

```bash
curl -X DELETE http://localhost:8000/api/forms/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

## Public Forms

### Load Published Form

```bash
curl http://localhost:8000/api/public/forms/ai-workshop-registration
```

### Submit Published Form

```bash
curl -X POST http://localhost:8000/api/public/forms/ai-workshop-registration/submissions \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"answers":{"full_name":"Ada Lovelace","email":"ada@example.com","attendance_mode":"Online"}}'
```

## Submissions

### List With Pagination And Search

```bash
curl -H "Authorization: Bearer $TOKEN" \
  "http://localhost:8000/api/forms/1/submissions?page=1&per_page=5&search=ada"
```

### Export CSV

```bash
curl -L -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/forms/1/submissions/export \
  -o submissions.csv
```

## AI

### Create Schema

```bash
curl -X POST http://localhost:8000/api/ai/forms \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"prompt":"Create a job application form for Laravel developers."}'
```

### Edit Existing Schema

```bash
curl -X POST http://localhost:8000/api/ai/forms \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"prompt":"Add an emergency contact section and make phone required.","schema":{"title":"Employee Form","fields":[{"key":"full_name","label":"Full name","type":"text"},{"key":"phone","label":"Phone","type":"phone"}]}}'
```
