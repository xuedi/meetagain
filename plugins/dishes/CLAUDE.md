# Dishes Plugin Guidelines

**Plugin Key:** `dishes`

---

## Overview

The Dishes plugin adds functionality for managing dish assignments and contributions to events.

---

## Quick Start

```bash
# Enable plugin
just plugin-enable dishes

# Load with fixtures
just devModeFixtures
```

---

## Key Features

- Dish assignment management
- Contribution tracking
- Event-specific dish lists

---

## Architecture

- **Namespace:** `Plugin\Dishes\*`
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
