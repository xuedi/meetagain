# AI Contribution Guidelines

This document sets expectations and guardrails for AI-assisted contributions to the MeetAgain repository. It is meant for both human contributors using AI tools and autonomous AI agents.

Last updated: 2025-09-20 22:40 (local)

## Goals and Scope
- Maintain code quality, security, and consistency across the project.
- Minimize changes necessary to satisfy an issue (prefer surgical edits over refactors unless requested).
- Preserve project conventions: Symfony (PHP), Twig, Doctrine, PSR standards, and existing tooling.

## General Principles
- Prefer clarity over cleverness. Choose explicit, readable solutions.
- Keep PRs small and focused on a single issue.
- Follow existing patterns in the codebase. When in doubt, search for similar implementations.
- Document decisions in code comments or in the PR description when behavior changes.

## Coding Standards
- PHP: PSR-12, strict types where feasible.
- Naming: Follow Symfony/Doctrine conventions (entities, services, controllers).
- Twig: Keep templates simple; push logic to controllers/services when appropriate.
- Types: Add PHPDoc for complex structures and public APIs; prefer typed properties and return types on modern PHP.
- Linting/formatting: Use project tooling (composer scripts, Justfile, or php-cs-fixer if configured).

## Symfony Conventions
- Controllers: Thin; delegate to services. Return proper HTTP status codes.
- Services: Register via Symfony autowiring and attributes as per existing patterns.
- Configuration: Use config/packages and environment variables (.env, .env.local), never hard-code secrets.
- Console Commands: Keep idempotent where reasonable; document schedule for cron usage.

## Doctrine and Database
- Entities: Keep fields private with getters/setters; use typed properties.
- Migrations: Any schema change requires a Doctrine migration with clear up/down.
- Data integrity: Validate at both DB and application levels where appropriate.

## Security and Privacy
- Secrets: Never commit secrets. Use env vars and Symfony vault if configured.
- Input validation: Validate and sanitize input; use Symfony Validator and CSRF protection.
- Authentication/Authorization: Use security voters/attributes consistently. Least privilege.
- Logging: Do not log PII or secrets. Anonymize or hash where needed.

## Error Handling and Observability
- Exceptions: Throw domain-specific exceptions where helpful; convert to user-friendly messages or proper HTTP codes.
- Logging: Use PSR-3 logger; ensure log levels are appropriate.
- Monitoring: Keep logs actionable; avoid noisy logs.

## Testing
- Add or update tests for changed behavior.
- Prefer unit tests for services and functional tests for controllers/routes.
- Ensure tests are deterministic and avoid relying on real external services.

## Dependencies
- Be conservative adding new packages; justify necessity and security posture.
- Pin versions according to composer constraints; run "composer update --lock" only when required.
- Remove unused dependencies if encountered.

## Performance
- Consider complexity of queries and N+1 issues (use joins, eager loading when needed).
- Cache where appropriate (Symfony cache pools) but avoid premature optimization.

## Internationalization (i18n) and Accessibility (a11y)
- Use translation files for user-facing strings when the app is localized.
- In Twig, ensure proper semantics and ARIA attributes where relevant.

## Emails and Notifications
- Use the existing EmailService and NotificationService patterns; avoid duplicating logic.
- Separate rendering (Twig templates) from sending; inject dependencies.
- For background/cron tasks, ensure idempotency and clear logging.

## Frontend Templates
- Keep CSS/JS minimal in Twig; prefer including assets via the established pipeline.
- Avoid inline scripts containing sensitive data.

## Git and PR Process
- Branch naming: feature/..., fix/..., chore/... with short summary.
- Commits: Small, descriptive; reference issue IDs where applicable.
- PR description: Problem, approach, risks, screenshots (for UI), and test notes.
- Follow conventional commit style if already used; otherwise, be consistent.

## Issue Workflow for AI Agents
1) Read the issue and any attached files thoroughly.
2) Create a minimal plan; prefer minimal code changes to satisfy the issue.
3) Reproduce the issue locally if applicable.
4) Implement changes; add tests when behavior changes.
5) Verify via local execution or tests.
6) Document changes and submit.

## Project-Specific Notes
- Environment variables: Use .env.dist as a template; do not modify .env in commits.
- CronCommand and background tasks: Ensure safe re-entrancy and proper logging.
- Admin area templates under templates/admin: Keep UI consistent with base templates.

## How to Run Locally (quick reference)
- Copy .env.dist to .env and fill required values.
- Install dependencies: composer install
- Run DB migrations: php bin/console doctrine:migrations:migrate
- Start server: symfony serve (or PHP built-in server) as per project docs.
- Run tests: just test (see section 20 for more options).

## Security Review Checklist (quick)
- [ ] No secrets committed
- [ ] Inputs validated and escaped
- [ ] Proper auth checks on new endpoints/actions
- [ ] Safe logging (no PII)
- [ ] Dependencies vetted

## Decision Log Template (optional)
If you make a non-trivial decision, include in PR:
- Context: What problem are we solving?
- Options considered: Pros/cons
- Decision: What and why
- Consequences: Risks and follow-ups

## Running Tests (detailed)
- Default (Docker, recommended):
  - just test
  - This runs vendor/bin/phpunit -c tests/phpunit.xml inside the PHP container with XDEBUG_MODE=coverage (as configured in the Justfile).
- Local (no Docker):
  - vendor/bin/phpunit -c tests/phpunit.xml --no-coverage
  - Tip: If you see a warning like "No code coverage driver available", add --no-coverage to suppress it. To enable coverage locally, install and enable Xdebug and run without --no-coverage.
  - Try to avoid running tests locally if possible.
- Useful commands:
  - List all tests: vendor/bin/phpunit -c tests/phpunit.xml --list-tests
  - Run a single test method: vendor/bin/phpunit -c tests/phpunit.xml --filter 'ClassName::testMethodName'
  - Pretty output: add --testdox to any of the above.
