---
name: test-unit
description: Run unit tests efficiently with Haiku model. Use when the user asks to "run unit tests", "test a specific class", or mentions unit testing.
disable-model-invocation: true
---

# Run Unit Tests

Execute PHPUnit unit tests with optional filtering, using the Haiku model for efficiency.

## Arguments

- **filter** (optional): Test class or path filter (e.g., "EventServiceTest", "Service/")

## Workflow

Run the following commands using a Haiku agent:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  description: "Run unit tests",
  prompt: |
    Execute PHPUnit unit tests and return results:

    1. Run: just testUnit $ARGUMENTS
    2. Run: just testPrintResults

    Return a concise summary including:
    - Total tests run
    - Pass/fail counts
    - Runtime
    - If failures exist: list them with file:line
    - Overall status (PASSED/FAILED)

    Format as:
    ```
    Unit Tests: X tests, Y assertions
    Status: PASSED/FAILED
    Runtime: N.NNNs

    [If failures:]
    Failures:
    - TestClass::testMethod (path/to/file.php:123)
      Expected X, got Y
    ```
)
```

## Examples

- `/test-unit` - Run all unit tests
- `/test-unit EventServiceTest` - Run specific test class
- `/test-unit Service/` - Run tests in Service directory
