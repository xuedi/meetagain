---
name: test-all
description: Run complete test suite with coverage. Use when the user asks to "run all tests", "run full test suite", or "run tests with coverage".
disable-model-invocation: true
---

# Run Complete Test Suite

Execute the complete test suite (unit + functional + coverage + quality checks) using the Haiku model for efficiency.

## Workflow

Run the following commands using a Haiku agent:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  description: "Run full test suite",
  prompt: |
    Execute complete test suite:

    1. Run: just test
    2. Run: just testPrintResults

    Return comprehensive summary:
    - Unit tests: count, status
    - Functional tests: count, status
    - Code coverage percentage
    - Mago checks: pass/fail
    - Overall runtime
    - Final status (PASSED/FAILED)

    Format as:
    ```
    Complete Test Suite Results
    ===========================
    Unit Tests: X tests, Y assertions - STATUS
    Functional Tests: X tests, Y assertions - STATUS
    Coverage: XX.X%
    Mago Checks: PASSED/FAILED
    Runtime: N.NNNs

    Overall: PASSED/FAILED

    [If failures exist, list them]
    ```
)
```

## Examples

- `/test-all` - Run complete test suite with coverage
