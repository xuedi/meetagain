---
name: api-query
description: Query the remote production API with a fresh Bearer token. Use when asked to "call the production api", "check the live site api", "query /api/cms/ on production", or "hit the production api endpoint". NOT for local dev — use the database directly for local data.
disable-model-invocation: true
---

# API Query

Authenticate and query the **remote production** API. Fetches a fresh Bearer token on every call, then executes the request and pretty-prints the JSON response.

Use this when you need to inspect or manage data on the live site. For local development data, query the database directly with `/db-query` instead.

## Arguments

- **METHOD** (optional): HTTP method — `GET`, `POST`, `PUT`, `DELETE`. Defaults to `GET`.
- **PATH** (optional): API path, e.g. `/api/cms/`. Defaults to `/api/status`.
- **BODY** (optional): JSON body string for POST/PUT requests.

Credentials are read from `.claude/api.local` (not committed — fill in the production URL, email, and password).

## Workflow

Use the Bash tool to run the script directly. Do NOT use an agent.

```
bash .claude/skills/api-query/run.sh $ARGUMENTS
```

## Examples

- `/api-query` — Health check (`GET /api/status`)
- `/api-query GET /api/cms/` — List all CMS pages
- `/api-query GET /api/cms/1` — Get CMS page with ID 1
- `/api-query POST /api/cms/ '{"slug":"test","titles":{"en":"Test"},"linkNames":{"en":"Test"}}'` — Create page
- `/api-query DELETE /api/cms/1` — Delete CMS page 1
