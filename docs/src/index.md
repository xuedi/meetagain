# MeetAgain — Documentation

MeetAgain is an open-source event management platform built with **Symfony 8 / PHP 8.4**.
It is designed around a plugin system that lets communities tailor their space to their needs —
book clubs, film nights, board game groups, karaoke nights — without bloating the base install.

This is the central documentation for everyone working with MeetAgain: self-hosters, plugin
developers, and core contributors.

---

## What's in here

| Section                                           | For                                                  |
|---------------------------------------------------|------------------------------------------------------|
| [Getting Started](getting-started.md)             | Developers who want to run the local dev environment |
| [Hosting](hosting.md)                             | Self-hosters deploying to production                 |
| [Core Development](core-development/index.md)     | Contributors to the open-source core                 |
| [Plugin Development](plugin-development/index.md) | Developers building plugins                          |
| [Contributing](contributing.md)                   | PR workflow, code standards                          |

---

## Architecture at a glance

```
┌─────────────────────────────────┐
│         Core Application        │  EUPL licence, public repo
│  (Events, CMS, Members, Users)  │
└──────────────┬──────────────────┘
               │ plugin interfaces
               ▼
┌─────────────────────────────────┐
│            Plugins              │  Loaded from plugins/
│  dishes · filmclub · bookclub   │  Each is a self-contained
│  karaoke · glossary · …         │  Symfony bundle
└─────────────────────────────────┘
```

Plugins integrate through well-defined interfaces — filter hooks, navigation hooks,
content hooks — without modifying core code. Core never depends on plugin code.

---

## Technology Stack

- **Backend:** Symfony 8.0, PHP 8.4, Doctrine ORM
- **Frontend:** Bulma CSS, Font Awesome, Twig
- **Database:** MariaDB
- **Testing:** PHPUnit 12
- **Dev environment:** Docker + [just](https://github.com/casey/just) command runner

---

## Licence

The core and all bundled plugins are published under the
[European Union Public Licence (EUPL)](https://joinup.ec.europa.eu/collection/eupl) —
the only OSI-approved licence drafted by the European Commission.
