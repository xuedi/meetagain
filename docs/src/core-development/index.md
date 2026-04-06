# Core Development

Welcome to the MeetAgain core development guide. This section covers everything you need
to contribute to the core application — from understanding the layer architecture to writing
tests and following code conventions.

---

## What's in the core

MeetAgain is organized into distinct subsystems. Each is self-contained and communicates
through services and repositories:

| Subsystem         | Directory                                                    | Description                                     |
|-------------------|--------------------------------------------------------------|-------------------------------------------------|
| **Events**        | `src/Entity/Event*`, `src/Service/EventService.php`          | Event creation, RSVP, recurring rules, comments |
| **CMS**           | `src/Entity/Cms*`, `src/Service/CmsService.php`              | Content pages, blocks, menu locations           |
| **Members**       | `src/Entity/User.php`, `src/Service/MemberService.php`       | User profiles, membership status                |
| **Users & Auth**  | `src/Security/`, `src/Controller/SecurityController.php`     | Login, registration, roles                      |
| **Email**         | `src/Entity/EmailTemplate*`, `src/Service/EmailService.php`  | Template-based transactional email              |
| **Config**        | `src/Entity/Config.php`, `src/Service/ConfigService.php`     | Key/value application settings                  |
| **Plugin System** | `src/Plugin.php`, `src/Filter/`                              | Plugin interface, filter contracts              |
| **Activity Log**  | `src/Entity/Activity.php`, `src/Service/ActivityService.php` | Audit trail of user actions                     |
| **Announcements** | `src/Entity/Message.php`                                     | Site-wide announcements                         |
| **Translations**  | `translations/`, `src/Service/TranslationService.php`        | EN/DE/CN YAML translation files                 |
| **Hosts**         | `src/Entity/Host.php`, `src/Service/HostService.php`         | Event host/organizer groups                     |

---

## Architecture at a glance

The application follows a strict four-layer architecture:

```
Controller  →  Service  →  Repository  →  Entity
(HTTP/CLI)     (logic)     (DB access)    (data)
```

| Layer          | Responsibility                                                           |
|----------------|--------------------------------------------------------------------------|
| **Controller** | Thin HTTP/CLI entry points; delegates to services; no business logic     |
| **Service**    | Business logic; `readonly` classes; uses repositories and other services |
| **Repository** | Data access; QueryBuilder methods with intent-revealing names            |
| **Entity**     | Plain Doctrine-mapped objects; no logic; enums for domain values         |

Dependencies flow **downward only**. A repository never calls a service; a service never
calls a controller.

See [Architecture](architecture.md) for the full dependency rules and plugin system.

---

## Contributor journey

1. **Set up the dev environment** → [Getting Started](../getting-started.md)
2. **Understand the layer rules** → [Architecture](architecture.md)
3. **Read the patterns for the area you're changing** → [Patterns](patterns.md)
4. **Check frontend conventions if touching templates** → [Frontend](frontend.md)
5. **Write tests** → [Testing](testing.md)
6. **Run `just fixMago` then `just test`** — must be green before opening a PR
7. **Open a PR** → [Contributing](../contributing.md)

---

## Quick links

| Page                                  | What it covers                                             |
|---------------------------------------|------------------------------------------------------------|
| [Architecture](architecture.md)       | Layer rules, plugin system, Symfony events, directory tour |
| [Patterns](patterns.md)               | Service, Repository, Entity, Form, Command code examples   |
| [Frontend](frontend.md)               | Twig templates, Bulma CSS, admin UI, translations          |
| [Data Fixtures](fixtures.md)          | AbstractFixture, cross-references, fixture groups          |
| [Testing](testing.md)                 | AAA pattern, test doubles, functional tests, running tests |
| [Best Practices](best-practices.md)   | Readonly services, N+1, enums, HTML sanitization           |
| [Troubleshooting](troubleshooting.md) | Common problems and how to fix them                        |
