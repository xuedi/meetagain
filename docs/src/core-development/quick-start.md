# Quick Start

Get up to speed as a core contributor in a few minutes.

---

## 1. Start the dev environment

```bash
git clone https://github.com/xuedi/meetAgain.git && cd meetAgain
just devModeFixtures
# Open https://meetagain.local — admin@example.org / 1234
```

See [Getting Started](../getting-started.md) for the full setup (local hostname, Docker prerequisites, etc.).

---

## 2. Understand the layer rule

```
Controller  →  Service  →  Repository  →  Entity
(HTTP/CLI)     (logic)     (DB access)    (data)
```

Dependencies flow **downward only**. Never call a service from a repository; never put
business logic in a controller. See [Architecture](architecture.md) for the full rules.

---

## 3. Find the right file

| If you're changing… | Start in… |
|---|---|
| Business logic | `src/Service/` |
| Database queries | `src/Repository/` |
| Data model / schema | `src/Entity/` |
| Admin or public pages | `src/Controller/Admin/` or `src/Controller/` |
| Twig templates | `templates/` |
| CLI commands | `src/Command/` |
| Translations | `translations/messages.{en,de,cn}.yaml` |

---

## 4. Make your change

Follow the pattern for the layer you're in:

- **Service** — `readonly` class, constructor injection, single responsibility → [Patterns § Service](patterns.md#service)
- **Repository** — `createQueryBuilder()`, intent-revealing method names → [Patterns § Repository](patterns.md#repository)
- **Entity** — Doctrine attributes, backed enums, no logic → [Patterns § Entity](patterns.md#entity)
- **Schema change** — generate a migration after editing an entity → [Patterns § Migrations](patterns.md#migrations)

---

## 5. Run the checks

```bash
just fixMago    # Auto-format code (always run before testing)
just testUnit   # Run unit tests
just test       # Run all tests + static analysis + code style
```

All checks must pass before opening a PR.

---

## 6. Open a PR

See [Contributing](../contributing.md) for the PR workflow and code review checklist.
