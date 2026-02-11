---
name: plugin-enable
description: Enable a plugin and run migrations. Use when the user asks to "enable a plugin", "activate a plugin", or mentions enabling plugins.
disable-model-invocation: true
---

# Enable Plugin

Enable a plugin and run migrations using the Haiku model for efficiency.

## Arguments

- **plugin** (required): Plugin name to enable (check `plugins/` directory for available plugins)

## Workflow

Run the following commands using a Haiku agent:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  description: "Enable plugin",
  prompt: |
    Enable plugin and run migrations:

    1. Run: just plugin-enable $ARGUMENTS
    2. Check migration status

    Return a summary including:
    - Plugin enabled status
    - Migrations run (if any)
    - Any warnings or errors
    - Next steps (if applicable)

    Format as:
    ```
    Plugin Enable: $ARGUMENTS
    ===========================
    Status: ENABLED/FAILED

    Migrations:
    [List migrations run, or "No migrations needed"]

    [If errors: list them]

    Next Steps:
    - Run: just devModeFixtures to load plugin fixtures (if needed)
    - Check plugin documentation in plugins/$ARGUMENTS/CLAUDE.md
    ```
)
```

## Examples

- `/plugin-enable <plugin-name>` - Enable a plugin
- Check `plugins/` directory for available plugins
