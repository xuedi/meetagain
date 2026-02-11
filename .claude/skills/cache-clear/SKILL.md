---
name: cache-clear
description: Clear Symfony cache. Use when the user asks to "clear cache", "clear Symfony cache", or mentions cache clearing.
disable-model-invocation: true
---

# Clear Symfony Cache

Clear Symfony cache for specified environment using the Haiku model for efficiency.

## Arguments

- **env** (optional): Environment to clear cache for (dev, test, prod). Defaults to "dev".

## Workflow

Run the following commands using a Haiku agent:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  description: "Clear Symfony cache",
  prompt: |
    Clear Symfony cache:

    1. Run: just app cache:clear --env=$ARGUMENTS

    Return a concise summary including:
    - Environment cleared
    - Cache clearing status
    - Any warnings or errors

    Format as:
    ```
    Cache Clear
    ===========
    Environment: <env>
    Status: SUCCESS/FAILED

    [If errors: list them]
    ```
)
```

## Examples

- `/cache-clear` - Clear dev cache
- `/cache-clear test` - Clear test cache
- `/cache-clear prod` - Clear prod cache
