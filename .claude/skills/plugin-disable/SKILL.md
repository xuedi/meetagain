---
name: plugin-disable
description: Disable a plugin. Use when the user asks to "disable a plugin", "deactivate a plugin", or mentions disabling plugins.
disable-model-invocation: true
---

# Disable Plugin

Disable a plugin using the Haiku model for efficiency.

## Arguments

- **plugin** (required): Plugin name to disable (check `plugins/` directory for available plugins)

## Workflow

Run the following commands using a Haiku agent:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  description: "Disable plugin",
  prompt: |
    Disable plugin:

    1. Run: just plugin-disable $ARGUMENTS

    Return a summary including:
    - Plugin disabled status
    - Any warnings or errors
    - Data persistence note

    Format as:
    ```
    Plugin Disable: $ARGUMENTS
    ============================
    Status: DISABLED/FAILED

    [If errors: list them]

    Note: Plugin data remains in database.
    To remove data, manually drop plugin tables or reset database.
    ```
)
```

## Examples

- `/plugin-disable <plugin-name>` - Disable a plugin
- Check `plugins/` directory for available plugins
