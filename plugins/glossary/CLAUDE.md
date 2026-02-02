# Glossary Plugin Guidelines

**Plugin Key:** `glossary`

---

## Overview

The Glossary plugin adds functionality for managing term definitions and vocabulary.

---

## Quick Start

```bash
# Enable plugin
just plugin-enable glossary

# Load with fixtures
just devModeFixtures
```

---

## Key Features

- Term and definition management
- Glossary organization
- Reference system for terminology

---

## Architecture

- **Namespace:** `Plugin\Glossary\*`
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
