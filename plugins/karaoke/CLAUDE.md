# Karaoke Plugin Guidelines

**Plugin Key:** `karaoke`

---

## Overview

The Karaoke plugin adds functionality for managing karaoke sessions and song requests.

---

## Quick Start

```bash
# Enable plugin
just plugin-enable karaoke

# Load with fixtures
just devModeFixtures
```

---

## Key Features

- Karaoke session management
- Song request tracking
- Event integration for karaoke nights

---

## Architecture

- **Namespace:** `Plugin\Karaoke\*`
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
