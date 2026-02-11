---
name: check-quality
description: Format code and run quality checks. Use when the user asks to "check code quality", "run quality checks", or "format and check code".
disable-model-invocation: true
---

# Check Code Quality

Format code with Mago and run quality checks using the Haiku model for efficiency.

## Workflow

Run the following commands using a Haiku agent:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  description: "Run code quality workflow",
  prompt: |
    Execute code quality checks:

    1. Run: just fixMago
    2. Run: just checkMago

    Return a concise summary including:
    - Files formatted by fixMago
    - Remaining issues from checkMago (if any)
    - Overall status (CLEAN/ISSUES FOUND)

    Format as:
    ```
    Code Quality Check
    ==================
    Formatting: X files modified

    Mago Checks: PASSED/FAILED
    [If issues found, list categories and counts]

    Overall: CLEAN/ISSUES FOUND
    ```
)
```

## Examples

- `/check-quality` - Format and check code quality
