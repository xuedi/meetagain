# Film Club Plugin Guidelines

**Plugin Key:** `filmclub`

---

## Overview

The Film Club plugin adds functionality for managing movie screenings and film discussions.

---

## Quick Start

```bash
# Enable plugin
just plugin-enable filmclub

# Load with fixtures
just devModeFixtures
```

---

## Key Features

- Film screening management
- Movie discussion organization
- Event integration for film club meetings

---

## Architecture

- **Namespace:** `Plugin\FilmClub\*`
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
