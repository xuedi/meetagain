---
name: api-query
description: Query the remote production API with a fresh Bearer token. Use when asked to "call the production api", "check the live site api", "query /api/cms/ on production", or "hit the production api endpoint". NOT for local dev — use the database directly for local data.
disable-model-invocation: true
---

# API Query

Authenticate and query the **remote production** API. Fetches a fresh Bearer token on every call, then executes the request and pretty-prints the JSON response.

Use this when you need to inspect or manage data on the live site. For local development data, query the database directly with `/db-query` instead.

## Arguments

- **ENV** (optional): `local` to target local dev (`api.local`). Omit for production (`api.prod`).
- **METHOD** (optional): HTTP method — `GET`, `POST`, `PUT`, `DELETE`. Defaults to `GET`.
- **PATH** (optional): API path, e.g. `/api/cms/`. Defaults to `/api/status`.
- **BODY** (optional): JSON body string for POST/PUT requests.

Credentials:
- **Production:** `.claude/api.prod` — production URL, email, password (not committed)
- **Local dev:** `.claude/api.local` — `http://meetagain.local`, `admin@example.org`, `1234`

## Workflow

Use the Bash tool to run the script directly. Do NOT use an agent.

```
bash .claude/skills/api-query/run.sh [$ENV] $ARGUMENTS
```

## Examples

- `/api-query` — Production health check (`GET /api/status`)
- `/api-query local` — Local dev health check
- `/api-query GET /api/cms/` — List all CMS pages (production)
- `/api-query local GET /api/cms/` — List all CMS pages (local dev)
- `/api-query GET /api/cms/1` — Get CMS page with ID 1
- `/api-query POST /api/cms/ '{"slug":"test","titles":{"en":"Test"},"linkNames":{"en":"Test"}}'` — Create page
- `/api-query DELETE /api/cms/1` — Delete CMS page 1
