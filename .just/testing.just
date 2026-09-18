# Test suites, coverage, load and attack scenarios.

# Run all tests and checks (same chain as the pre-commit hook)
[group('testing')]
test:
    @{{PHP}} composer validate --strict --quiet
    @bin/commit-hooks.sh
    @echo "All tests and checks passed successfully"

# Run unit tests
[group('testing')]
testUnit +parameter='':
    @{{PHP}} vendor/bin/phpunit -c tests/config/phpunit.xml --testsuite=default --no-coverage --log-junit tests/reports/junit.xml {{parameter}}
    @echo
    @echo

# Print AI-readable test results (for AI agent)
[group('testing')]
testPrintResults +parameter='':
    {{PHP}} php bin/console app:test:results {{parameter}}

# Show coverage report
[group('testing')]
testCoverage +parameter='':
    {{PHP_COVERAGE}} sh -c 'vendor/bin/paratest -c tests/config/phpunit.xml --processes=$(nproc)'
    {{PHP}} php bin/console app:badge:generate
    {{PHP}} php bin/console app:test:coverage-report {{parameter}}

# Analyze Page speed in various browsers
[group('testing')]
testPerformance:
    {{PHP}} mkdir -p tests/reports/performance
    {{DOCKER}} up -d php-bench
    {{DOCKER}} run --rm sitespeed
    {{DOCKER}} stop php-bench
    xdg-open "$(find tests/reports/performance/sitespeed-result -name 'index.html' -type f -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2)"

# Run a load-test scenario (small | medium | cliff). Targets the running dev stack.
[group('testing')]
testLoad scenario='small':
    mkdir -p tests/reports/load
    {{DOCKER}} run --rm -e K6_WEB_DASHBOARD_EXPORT=/reports/load/{{scenario}}.html loadtest run /scripts/load/{{scenario}}.js
    @echo "Report: tests/reports/load/{{scenario}}.html"
    -xdg-open tests/reports/load/{{scenario}}.html

# Run small + medium back to back. cliff is opt-in via testLoad cliff.
[group('testing')]
testLoadAll:
    {{JUST}} testLoad small
    {{JUST}} testLoad medium

# Run an attack-test scenario (notFoundProbe | rateLimitLogin | accessDeniedScript | fuseCookieRotation).
[group('testing')]
testAttack scenario='notFoundProbe':
    mkdir -p tests/reports/attack
    {{PHP}} php bin/console app:security:reset-state
    {{DOCKER}} run --rm -e K6_WEB_DASHBOARD_EXPORT=/reports/attack/{{scenario}}.html loadtest run /scripts/attack/{{scenario}}.js
    {{PHP}} php bin/console app:security:assert-state tests/reports/attack/{{scenario}}.expectations.json --scenario={{scenario}} --output-dir=tests/reports/attack
    @echo "Report: tests/reports/attack/{{scenario}}.html"
    -xdg-open tests/reports/attack/{{scenario}}.html

# Run all four attack scenarios sequentially and produce a combined HTML index.
[group('testing')]
testAttackAll:
    {{JUST}} testAttack notFoundProbe
    {{JUST}} testAttack rateLimitLogin
    {{JUST}} testAttack accessDeniedScript
    {{JUST}} testAttack fuseCookieRotation
    {{PHP}} php bin/console app:security:report-combined --input-dir=tests/reports/attack --output=tests/reports/attack/index.html
    -xdg-open tests/reports/attack/index.html
