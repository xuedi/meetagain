# Claude Code Guidelines

**Quick Links:** [Architecture](architecture.md) | [Conventions](conventions.md) | [Testing](testing.md)

---

## Quick Start (30 seconds)

This is a **Symfony 8.0 / PHP 8.4** event management application with plugin system.

**Essential Commands:**

```bash
just                    # Show all commands
just devModeFixtures    # Reset dev environment
just testUnit           # Run unit tests
just test               # Run all tests + checks
```

**Key Patterns:**

- Thin Controllers → Services (readonly) → Repositories
- All commands via `just` (Docker isolation)
- Use Haiku agent for running tests
- See [architecture.md](architecture.md) for layer dependencies

---

## Environment

- **Docker only** via `just` command runner - never run commands on host
- Run `just` to see available commands (don't parse the justfile itself)
- **Code Quality Workflow**: Always run `just fixMago` before `just test`
- Avoid `just test` to save tokens, ask user to run it after coding is complete
- If you must run a test, use `just testUnit <specific test>` only on the test to be run

---

## Git Workflow

**IMPORTANT: NEVER create git commits yourself**

- **NEVER** run `git commit`, `git add`, or any other git commands that create commits
- **ALWAYS** ask the user to commit changes themselves
- After completing work, remind the user to review and commit the changes
- You may run `git status` or `git diff` to check the current state if needed
- Exception: You MAY use git commands when the user explicitly asks you to create a commit (e.g., "commit these changes" or "/commit")

**Pattern:**
1. Complete the implementation
2. Run `just fixMago` and verify tests pass
3. Tell the user: "The implementation is complete. Please review the changes and commit when ready."
4. Do NOT create commits automatically

---

## Commands Reference

### Development

| Command                | Purpose                     |
|------------------------|-----------------------------|
| `just start`           | Start Docker containers     |
| `just stop`            | Stop Docker containers      |
| `just devModeFixtures` | Reset dev with fixtures     |
| `just app <cmd>`       | Run Symfony console command |

### Testing

| Command               | Purpose                           |
|-----------------------|-----------------------------------|
| `just test`           | Run all tests and checks          |
| `just testUnit`       | Run unit tests (generates JUnit)  |
| `just testFunctional` | Run functional tests              |
| `just testResults`    | AI-readable test results          |
| `just testCoverage`   | Generate and show coverage report |
| `just testSymfony`    | Analyze route performance         |

### Code Quality Checks

| Command                | Purpose                         |
|------------------------|---------------------------------|
| `just checkMago`       | Run Mago linter                 |
| `just checkMagoAnalyze`| Mago static analysis            |
| `just checkMagoGuard`  | Mago architectural rules        |
| `just checkMagoAll`    | Run all 3 Mago checks           |

### Fixes

| Command      | Purpose                                                    |
|--------------|------------------------------------------------------------|
| `just fixMago` | Format code with Mago (run this before `just test`)     |

**Note:** Always run `just fixMago` after making code changes and before running `just test` to ensure code style consistency.

### Database

| Command                       | Purpose                   |
|-------------------------------|---------------------------|
| `just dockerDatabase "query"` | Run SQL query on database |

---

## Token Efficiency

- Default to `model: "sonnet"` for subagents
- **Always use `model: "haiku"` for running `just` commands** (see Haiku Agent section below)
- Prefer asking user to run tests locally over AI execution
- For codebase exploration, use `subagent_type: "Explore"` over multiple greps
- Only read files that are directly relevant to the task
- Use offset/limit when reading large files

---

## Haiku Agent for `just` Commands

All `just` command execution MUST use the Haiku model via Task tool:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  prompt: "Run: just testUnit Tests\\Unit\\Service\\ExampleTest && just testResults"
)
```

**Pattern for running tests:**

1. Run test command: `just testUnit [TestClass]` (generates JUnit XML)
2. Get results: `just testResults` (AI-readable format)
3. Return structured summary to parent agent

**AI-readable output format:**

```
STATUS: PASSED|FAILED
SUMMARY: X tests, Y assertions, Z failures
---
FAILURES:
  1. ClassName::methodName
     File: path/to/file.php:123
     Message: Failed asserting...
     Expected: 'foo' | Actual: 'bar'
```

**Available test result commands:**

- `just testResults` - Show all test results
- `just testResults --failures-only` - Show only failures

---

## Planning Workflow (Opus Model)

When the user asks to "make a plan" or requests architectural planning:

1. **Use Opus model** for the planning task
2. **Write the plan** to `.claude/plans/YYYY-MM-DD-feature-name.md`
    - Break down "Implementation Steps" into logical, testable chunks
    - Each step should be a coherent unit that delivers value and can be verified
3. **Stop and ask for approval** before executing any code changes
4. After approval, proceed with implementation using Sonnet
    - Execute one step from the plan
    - **Stop and ask to continue** before next step
    - Unless user explicitly says "do it all in one go"

**Plan file structure:**

```markdown
# Feature: [Name]

Date: YYYY-MM-DD
Model: opus

## Objective

[What needs to be done]

## Analysis

[Current state, dependencies, constraints]

## Approach

[High-level strategy]

## Implementation Steps

1. Step one with affected files
2. Step two...

## Testing Strategy

[How to verify the changes]

## Risks & Considerations

[Potential issues, trade-offs]
```

---

## Code Review Checklist

Before marking work complete, verify:

- [ ] Code formatted: Run `just fixMago` to auto-format code
- [ ] Tests pass: `just test` (includes unit tests, functional tests, and all Mago checks)
- [ ] Architecture: Follows layer dependencies (see [architecture.md](architecture.md))
- [ ] No inline scripts in templates
- [ ] Readonly services where applicable
- [ ] AAA pattern in tests with comments

**IMPORTANT: After major refactoring or removing code quality tools, always run `just fixMago` before `just test`**

---

## Quick Pattern References

| Pattern           | Example File                                 | Why                                        |
|-------------------|----------------------------------------------|--------------------------------------------|
| Service           | `src/Service/CleanupService.php`             | Focused SRP, minimal dependencies          |
| Controller        | `src/Controller/ManageController.php`        | Thin, pure delegation (30 lines)           |
| Repository        | `src/Repository/EventRepository.php`         | Intent-revealing names, query builder      |
| Entity + Enums    | `src/Entity/Event.php`                       | Proper attributes, enum usage              |
| Plugin            | `src/Plugin.php`                             | Interface contract definition              |
| Unit test (AAA)   | `tests/Unit/Service/ActivityServiceTest.php` | Excellent AAA comments, mock/stub use      |
| Fixtures (custom) | `src/DataFixtures/EventFixture.php`          | Custom AbstractFixture with type-safe refs |
| AbstractFixture   | `src/DataFixtures/AbstractFixture.php`       | Magic methods for fixture references       |
| Voter             | `src/Security/Voter/EventVoter.php`          | Authorization logic                        |
| Command           | `src/Command/EventExtentCommand.php`         | CLI with progress, error handling          |
| Form Type         | `src/Form/EventType.php`                     | Form building, validation                  |

---

**For detailed information, see:**

- [Architecture](architecture.md) - Layer dependencies, design patterns, Symfony 8 features
- [Conventions](conventions.md) - PHP style, Doctrine, Frontend, Security, Performance
- [Testing](testing.md) - PHPUnit patterns, fixtures, coverage
