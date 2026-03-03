# Contributing

Contributions to the MeetAgain core and bundled plugins are welcome.

---

## Development Setup

Follow the [Getting Started](getting-started.md) guide to get a running dev environment,
then run the full test suite to confirm everything is green:

```bash
just test
```

This runs unit tests, functional tests, and all Mago static analysis checks.

---

## Code Standards

The project uses **Mago** for formatting, linting, and architecture validation.

```bash
# Format code
just fixMago

# Check for issues (run before submitting a PR)
just checkMagoAll
```

Key rules:

- `declare(strict_types=1)` on every PHP file
- All services must be `readonly` (exception: services that hold a per-request memo field)
- Thin controllers — business logic belongs in services
- No direct repository access from controllers (use services)
- AAA pattern in tests (`// Arrange / // Act / // Assert` comments)

---

## Running Tests

```bash
# All tests
just test

# Unit tests only
just testUnit

# Functional tests only
just testFunctional

# Specific test file
just testUnit plugins/dishes/tests/Unit/SomeServiceTest.php
```

---

## Plugin Contributions

If you are contributing a new bundled plugin, read the
[Plugin Development Guide](plugin-development.md) first.

Plugins must:

- Not modify core entities
- Use junction tables for any relationship to core entities (store IDs as INT, not foreign keys)
- Work correctly when disabled (core must function without them)
- Include fixtures in the `plugin` group
- Include at least basic unit tests

---

## Submitting Changes

1. Fork the repository
2. Create a branch from `main`
3. Make your changes, run `just fixMago` and `just test`
4. Open a pull request against `main` with a clear description of what and why

---

## Reporting Issues

Open an issue on GitHub with:

- What you expected to happen
- What actually happened
- Steps to reproduce
- PHP / Symfony / Docker versions if relevant
