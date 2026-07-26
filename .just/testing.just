# Test suites, fixtures database, coverage, load and attack scenarios.

# Run all tests and checks (same chain as the pre-commit hook)
[group('testing')]
test:
    @{{PHP}} composer validate --strict --quiet
    @bin/commit-hooks.sh
    @echo "All tests and checks passed successfully"

# Setup test database
[group('testing')]
testSetup:
    {{PHP}} php bin/console doctrine:database:drop --env=test --force --if-exists
    {{PHP}} php bin/console doctrine:database:create --env=test
    {{PHP}} php bin/console doctrine:schema:create --env=test -q
    {{PHP}} php bin/console doctrine:fixtures:load --env=test -q --group=install
    {{PHP}} php bin/console doctrine:fixtures:load --env=test -q --append --group=base
    {{PHP}} php bin/console app:plugin:pre-fixtures --env=test
    {{PHP}} php bin/console app:fixtures:load --env=test -q --append --group=plugin
    {{PHP}} php bin/console app:plugin:post-fixtures --env=test
    ./bin/test-db-clone.sh
    touch tests/config/.test-db.lock

# Run unit tests
[group('testing')]
testUnit +parameter='':
    @{{PHP}} vendor/bin/phpunit -c tests/config/phpunit.xml --testsuite=default --no-coverage --log-junit tests/reports/junit.xml {{parameter}}
    @echo
    @echo

# Run functional tests
[group('testing')]
testFunctional +parameter='':
    @{{PHP}} php bin/console cache:warmup --env=test --quiet
    @{{PHP}} sh -c 'vendor/bin/paratest -c tests/config/phpunit.xml --testsuite=functional --processes=$(nproc) --no-coverage --log-junit tests/reports/junit.xml {{parameter}}'
    @echo
    @echo

# Run smoke tests - hits every discovered GET route, asserts no 5xx (always run last)
[group('testing')]
testSmoke +parameter='':
    @{{PHP}} php bin/console cache:warmup --env=test --quiet
    @{{PHP}} sh -c 'vendor/bin/paratest -c tests/config/phpunit.xml --testsuite=smoke --processes=$(nproc) --functional --no-coverage --log-junit tests/reports/junit.xml {{parameter}}'
    @echo
    @echo

# Profile DB query counts per GET route - identifies caching candidates
[group('testing')]
appProfileQueries +parameter='':
    @{{PHP}} php bin/console cache:warmup --env=test --quiet
    @{{PHP}} php bin/console app:profile:queries --env=test {{parameter}}
    @echo
    @echo

# Print AI-readable test results (for AI agent)
[group('testing')]
testPrintResults +parameter='':
    {{PHP}} php bin/console app:test:results {{parameter}}

# Show coverage report
[group('testing')]
testCoverage +parameter='':
    {{PHP_COVERAGE}} vendor/bin/phpunit -c tests/config/phpunit.xml
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
