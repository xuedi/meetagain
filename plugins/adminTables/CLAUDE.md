# Admin Tables Plugin Guidelines

**Plugin Key:** `adminTables`

---

## Overview

The Admin Tables plugin provides CRUD management interfaces for core entities:
- **Events** - Translations, recurring rules, images, cancellation
- **Locations** - Address/geocoding management
- **Hosts** - Event host assignment
- **Images** - Read-only gallery

**Note:** User/Member management has been migrated to core (`src/Controller/Admin/MemberController.php`).

---

## Quick Start

```bash
# Enable plugin (required for admin CRUD functionality)
just plugin-enable adminTables
```

---

## Architecture

- **Namespace:** `Plugin\AdminTables\*`
- **Controllers:** `src/Controller/` - Admin CRUD controllers
- **Forms:** `src/Form/` - Symfony form types for entities
- **Templates:** `templates/tables/` - Twig templates (extend core `admin/base.html.twig`)
- **Navigation:** Uses `AdminNavigationInterface` with `AdminNavigationConfig`

---

## Dependencies

This plugin depends on core services (NOT modified by plugin):
- Services: `EventService`, `ImageService`, `TranslationService`, `EmailService`, `LanguageService`
- Repositories: All `App\Repository\*` classes
- Entities: All `App\Entity\*` classes (Event, Location, Host, User, Image, enums)

---

## Route Names (Backwards Compatible)

All routes maintain original names:
- Events: `app_admin_event`, `app_admin_event_edit`, `app_admin_event_add`, `app_admin_event_cancel`, etc.
- Locations: `app_admin_location`, `app_admin_location_edit`, `app_admin_location_add`
- Hosts: `app_admin_host`, `app_admin_host_edit`, `app_admin_host_add`
- Images: `app_admin_image`

---

## Development Notes

- Admin routes use locale prefix (standard plugin pattern)
- Templates extend core `admin/base.html.twig`
- Forms reference core entities (entities stay in core)
- All service logic remains in core services

---

## Documentation

For core plugin patterns and architecture, see:
- **Core Guidelines:** `../../.claude/CLAUDE.md`
- **Core Architecture:** `../../.claude/architecture.md`
