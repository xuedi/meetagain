# Formatters and generators that write back into the tree.

# Format code with Mago
[group('fixing')]
fixMago:
    {{PHP}} vendor/bin/mago --config=tests/config/mago.toml lint --fix --format-after-fix

# Generate coverage badge (CI)
[group('fixing')]
fixCoverageBadge:
	{{PHP}} vendor/bin/phpunit -c tests/config/phpunit.xml --no-progress
	{{PHP}} php bin/console app:badge:generate
	git add tests/badge/coverage.svg

# Build static developer docs to docs/site/
[group('fixing')]
fixDocumentation:
    docker run --rm --user "$(id -u):$(id -g)" -v "$PWD":/docs zensical/zensical build --config-file docs/mkdocs.yml
