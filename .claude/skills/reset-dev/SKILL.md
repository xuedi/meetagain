---
name: reset-dev
description: Reset development environment with fixtures. Use when the user asks to "reset dev environment", "reload fixtures", or "reset database with fixtures".
disable-model-invocation: true
---

# Reset Development Environment

Reset development environment with fixtures using the Haiku model for efficiency.

## Arguments

- **plugin** (optional): Plugin name to enable (check `plugins/` directory for available plugins)

## Workflow

Run the following commands using a Haiku agent:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  description: "Reset dev environment",
  prompt: |
    Reset development environment with fixtures:

    1. Run: just devModeFixtures $ARGUMENTS

    Return a summary including:
    - Database reset status
    - Fixtures loaded
    - Plugins enabled (if applicable)
    - Any warnings or errors

    Format as:
    ```
    Dev Environment Reset
    =====================
    Database: RESET
    Fixtures: LOADED
    [If plugin specified: Plugin Enabled: <name>]

    Status: SUCCESS/FAILED
    [If errors: list them]
    ```
)
```

## Examples

- `/reset-dev` - Reset dev environment with all enabled plugins
- `/reset-dev <plugin-name>` - Reset with specific plugin enabled
