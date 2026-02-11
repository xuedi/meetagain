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

**Plugin Documentation:**

- **IMPORTANT:** When working with plugins, ALWAYS check plugin-specific guidelines
- Each plugin has its own `CLAUDE.md` file (e.g., `plugins/dishes/CLAUDE.md`)
- Core code must not contain plugin-specific documentation or dependencies
- Read both core AND plugin guidelines before making changes

---

## Environment

- **Docker only** via `just` command runner - never run commands on host
- Run `just` to see available commands (don't parse the justfile itself)
- **Code Quality Workflow**: Always run `just fixMago` before `just test`
- Avoid `just test` to save tokens, ask user to run it after coding is complete
- If you must run a test, use `just testUnit <specific test>` only on the test to be run

---

## Git Workflow

**CRITICAL: NEVER create git commits yourself**

- **NEVER** run `git commit`, `git add`, or any other git commands that create commits
- **NEVER** say "I will commit" or "Let me commit" - only the user commits
- **ALWAYS** ask the user to commit changes themselves
- You may suggest good points to commit, but ALWAYS wait for user to do it
- You may run `git status` or `git diff` to check the current state if needed
- Exception: You MAY use git commands ONLY when the user explicitly asks you to create a commit (e.g., "commit these
  changes" or "/commit")

**Correct Pattern:**

1. Complete the implementation
2. Run `just fixMago` and verify tests pass
3. Tell the user: "This is a good point to commit. Please review the changes and commit when ready."
4. **WAIT** for user to confirm they've committed
5. Ask: "Ready to continue?" or "Let me know when you're ready for the next step."

**Example Phrasing:**

- ✅ "The implementation is complete. This would be a good point to commit. Please review and commit the changes, then
  let me know when you're ready to continue."
- ✅ "I've completed step 1. You may want to commit these changes before we proceed to step 2. Let me know when ready."
- ❌ "Let me commit these changes for you."
- ❌ "I'll create a commit now."
- ❌ Automatically proceeding to next step without asking user to commit

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

| Command                 | Purpose                  |
|-------------------------|--------------------------|
| `just checkMago`        | Run Mago linter          |
| `just checkMagoAnalyze` | Mago static analysis     |
| `just checkMagoGuard`   | Mago architectural rules |
| `just checkMagoAll`     | Run all 3 Mago checks    |

### Fixes

| Command        | Purpose                                             |
|----------------|-----------------------------------------------------|
| `just fixMago` | Format code with Mago (run this before `just test`) |

**Note:** Always run `just fixMago` after making code changes and before running `just test` to ensure code style
consistency.

### Database

| Command                       | Purpose                   |
|-------------------------------|---------------------------|
| `just dockerDatabase "query"` | Run SQL query on database |

---

## Available Skills

**Use skills instead of manually invoking Task with Haiku model for common workflows.**

Skills automatically use the Haiku model for efficiency and follow project conventions.

### Testing Skills

| Skill                       | Purpose                                    |
|-----------------------------|--------------------------------------------|
| `/test-unit [filter]`       | Run unit tests (optionally filtered)       |
| `/test-functional [filter]` | Run functional tests (optionally filtered) |
| `/test-all`                 | Run complete test suite with coverage      |

**Examples:**

```bash
/test-unit                    # Run all unit tests
/test-unit EventServiceTest   # Run specific test class
/test-functional LoginTest    # Run functional login tests
/test-all                     # Full test suite + coverage
```

### Code Quality Skills

| Skill            | Purpose                                              |
|------------------|------------------------------------------------------|
| `/check-quality` | Format code and run quality checks                   |
| `/quality-fix`   | Complete pre-commit workflow (format + test + check) |

**Examples:**

```bash
/check-quality   # Auto-format + check for issues
/quality-fix     # Full pre-commit workflow
```

### Database & Environment Skills

| Skill                 | Purpose                             |
|-----------------------|-------------------------------------|
| `/reset-dev [plugin]` | Reset dev environment with fixtures |
| `/cache-clear [env]`  | Clear Symfony cache (dev/test/prod) |
| `/db-query "query"`   | Execute SQL query on database       |

**Examples:**

```bash
/reset-dev              # Reset with all enabled plugins
/reset-dev multisite    # Reset with multisite plugin
/cache-clear            # Clear dev cache
/cache-clear test       # Clear test cache
/db-query "SELECT COUNT(*) FROM users"
```

### Plugin Management Skills

| Skill                    | Purpose                    |
|--------------------------|----------------------------|
| `/plugin-enable <name>`  | Enable plugin + migrations |
| `/plugin-disable <name>` | Disable plugin             |

**Examples:**

```bash
/plugin-enable dishes     # Enable dishes plugin
/plugin-disable multisite # Disable multisite plugin
```

---

## Token Efficiency

- Default to `model: "sonnet"` for subagents
- **Use skills for `just` commands** (skills automatically use Haiku model)
- For custom just commands not covered by skills, use `Task(model: "haiku")`
- Prefer asking user to run tests locally over AI execution
- For codebase exploration, use `subagent_type: "Explore"` over multiple greps
- Only read files that are directly relevant to the task
- Use offset/limit when reading large files

---

## Haiku Agent for `just` Commands

**IMPORTANT: Use skills (e.g., `/test-unit`) instead of manual Haiku agent invocation.**

Skills are pre-configured shortcuts that automatically use Haiku model and follow best practices.

### When Skills Don't Cover Your Use Case

For custom `just` commands not covered by skills, use the Haiku model via Task tool:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  prompt: "Run: just <custom-command> && just testResults"
)
```

**Pattern for running tests manually:**

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
| Repository        | `src/Repository/EventRepository.php`         | Intent-revealing names, query builder      |
| Entity + Enums    | `src/Entity/Event.php`                       | Proper attributes, enum usage              |
| Plugin            | `src/Plugin.php`                             | Interface contract definition              |
| Unit test (AAA)   | `tests/Unit/Service/ActivityServiceTest.php` | Excellent AAA comments, mock/stub use      |
| Fixtures (custom) | `src/DataFixtures/EventFixture.php`          | Custom AbstractFixture with type-safe refs |
| AbstractFixture   | `src/DataFixtures/AbstractFixture.php`       | Magic methods for fixture references       |
| Admin Controller  | `src/Controller/Admin/SystemController.php`  | #[IsGranted] + AdminNavigationConfig       |
| Command           | `src/Command/EventExtentCommand.php`         | CLI with progress, error handling          |
| Form Type         | `src/Form/EventType.php`                     | Form building, validation                  |

---

**For detailed information, see:**

- [Architecture](architecture.md) - Layer dependencies, design patterns, Symfony 8 features
- [Conventions](conventions.md) - PHP style, Doctrine, Frontend, Security, Performance
- [Testing](testing.md) - PHPUnit patterns, fixtures, coverage
