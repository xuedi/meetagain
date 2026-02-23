---
name: test-functional
description: Run functional tests efficiently with Haiku model. Use when the user asks to "run functional tests", "test a specific functional test", or mentions functional testing.
disable-model-invocation: true
---

# Run Functional Tests

Execute PHPUnit functional tests with optional filtering, using the Haiku model for efficiency.

## Arguments

- **filter** (optional): Test class or path filter (e.g., "LoginTest")

## Workflow

Run the following commands using a Haiku agent:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  description: "Run functional tests",
  prompt: |
    Execute PHPUnit functional tests and return results:

    1. Run: just testFunctional $ARGUMENTS
    2. Run: just testPrintResults

    Return a concise summary including:
    - Total tests run
    - Pass/fail counts
    - Runtime
    - If failures exist: list them with file:line
    - Overall status (PASSED/FAILED)

    Format as:
    ```
    Functional Tests: X tests, Y assertions
    Status: PASSED/FAILED
    Runtime: N.NNNs

    [If failures:]
    Failures:
    - TestClass::testMethod (path/to/file.php:123)
      Error details
    ```
)
```

## Examples

- `/test-functional` - Run all functional tests
- `/test-functional LoginTest` - Run specific test class
