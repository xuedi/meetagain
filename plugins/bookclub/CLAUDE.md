# Book Club Plugin Guidelines

**Plugin Key:** `bookclub`

---

## Overview

The Book Club plugin adds functionality for managing book discussions and reading groups.

---

## Quick Start

```bash
# Enable plugin
just plugin-enable bookclub

# Load with fixtures
just devModeFixtures
```

---

## Key Features

- Book discussion management
- Reading group organization
- Event integration for book club meetings

---

## Architecture

- **Namespace:** `Plugin\BookClub\*`
- **Entities:** Custom plugin entities in `src/Entity/`
- **Controllers:** `src/Controller/`
- **Fixtures:** `src/DataFixtures/`

---

## Development Notes

- Follows standard plugin architecture
- No modifications to core entities
- Uses plugin fixture inheritance
- See main project guidelines: `../../.claude/CLAUDE.md`

---

## Documentation

For core plugin patterns and architecture, see:
- **Core Guidelines:** `../../.claude/CLAUDE.md`
- **Core Architecture:** `../../.claude/architecture.md`
