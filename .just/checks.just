# Linters, static analysis and accessibility checks.

# Run all tests and checks
[group('checks')]
check: checkMago checkMagoAnalyze checkMagoGuard checkPluginConfig checkPluginIsolation
    {{PHP}} composer validate --strict
    {{PHP}} php bin/check-get-routes.php
    {{PHP}} php bin/check-new-parentheses.php
    echo "All tests and checks passed successfully"

# Check Mago (linter) - core, then each plugin against its own config
[group('checks')]
checkMago:
    {{PHP}} sh bin/mago.sh lint

# Analyze code with Mago - core, then each plugin against its own config
[group('checks')]
checkMagoAnalyze:
    {{PHP}} sh bin/mago.sh analyze

# Check architectural rules with Mago - core, then each plugin against its own config
[group('checks')]
checkMagoGuard:
    {{PHP}} sh bin/mago.sh guard

# Run all Mago checks (lint + analyze + guard)
[group('checks')]
checkMagoAll: checkMago checkMagoAnalyze checkMagoGuard
    echo "All Mago checks complete"

# Check accessibility (Pa11y)
[group('checks')]
checkA11y url='http://meetagain.local/':
    {{DOCKER}} build pa11y -q
    {{DOCKER}} run --rm pa11y {{url}} --reporter cli --standard WCAG2AA

# Compile the production container once per installed plugin, each time with one plugin removed
[group('checks')]
checkPluginIsolation:
    {{PHP}} php bin/check-plugin-isolation.php

# Check config/plugins.php against the plugins on disk (run before deploying)
[group('checks')]
checkPluginConfig:
    {{PHP}} php bin/check-plugin-config.php
