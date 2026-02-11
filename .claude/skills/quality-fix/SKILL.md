---
name: quality-fix
description: Complete pre-commit quality workflow. Use when the user asks to "run pre-commit checks", "verify code is ready to commit", or "run quality fix".
disable-model-invocation: true
---

# Pre-Commit Quality Workflow

Execute complete pre-commit quality workflow (format + test + check) using the Haiku model for efficiency.

## Workflow

Run the following commands using a Haiku agent:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  description: "Run pre-commit quality workflow",
  prompt: |
    Execute complete pre-commit quality workflow:

    1. Run: just fixMago
    2. Run: just testUnit
    3. Run: just checkMago

    Return comprehensive summary including:
    - Files formatted
    - Unit test results (pass/fail, counts)
    - Code quality check results
    - Final go/no-go for commit

    Format as:
    ```
    Pre-Commit Quality Workflow
    ============================
    1. Code Formatting: X files modified

    2. Unit Tests: X tests, Y assertions
       Status: PASSED/FAILED
       Runtime: N.NNNs
       [If failures: list them]

    3. Code Quality: PASSED/FAILED
       [If issues: list categories]

    Overall Status: READY FOR COMMIT / ISSUES FOUND
    ```
)
```

## Examples

- `/quality-fix` - Run complete pre-commit workflow

## Note

"READY FOR COMMIT" is a status indicator only. This skill does NOT create git commits - only the user should commit changes.
