# One-time setup and the dev-environment reset modes.

# Run once per clone
[group('development')]
install: buildTools
    git config core.hooksPath .githooks
    mkdir -p bin/commit-hooks
    cp tests/config/commit-hooks/*.bash bin/commit-hooks/
    chmod +x bin/commit-hooks/*.bash
    @echo "Pre-commit dispatcher activated. Scripts in bin/commit-hooks/ will run on every commit."

# Shared reset sequence used by devModeFixtures and devModeMinimal
[group('development')]
devModeReset plugins='':
    {{JUST}} dockerStop
    {{JUST}} devResetConfigs
    cp .env.dist .env
    cp assets/styles/_config.scss.dist assets/styles/_config.scss
    touch installed.lock
    {{JUST}} dockerStart
    {{JUST}} do "composer install"
    {{JUST}} appAssets
    {{PHP}} php bin/console app:plugin disable all
    {{PHP}} php bin/console app:plugin enable {{plugins}}
    {{PHP}} php bin/console cache:clear -q
    {{JUST}} devResetDatabase
    {{JUST}} appMigrate
    {{PHP}} php bin/console doctrine:fixtures:load -q --group=install
    {{PHP}} php bin/console app:security:reset-state

# Full dev environment with demo data (base + plugin fixtures) — complete sample content for testing
[group('development')]
devModeFixtures plugins='':
    {{JUST}} devModeReset {{plugins}}
    {{PHP}} php bin/console doctrine:fixtures:load -q --append --group=base
    {{PHP}} php bin/console app:plugin:pre-fixtures
    {{PHP}} php bin/console app:fixtures:load -q --append --group=plugin
    {{PHP}} php bin/console app:plugin:post-fixtures
    {{JUST}} appCron
    {{PHP}} php bin/console app:event:add-fixture
    {{JUST}} appClearCache

# Full dev environment with ALL plugins active — ideal for testing plugin interactions
[group('development')]
devModeMax:
    {{JUST}} devModeFixtures all

# Minimal dev environment with install fixtures only (no sample content) — ideal for testing imports
[group('development')]
devModeMinimal plugins='':
    {{JUST}} devModeReset {{plugins}}
    {{PHP}} php bin/console doctrine:fixtures:load -q --append --group=minimal
    {{JUST}} appClearCache

# Switch to installer mode
[group('development')]
devModeInstaller:
    {{JUST}} dockerStop
    {{JUST}} devResetConfigs
    {{JUST}} dockerStart
    {{JUST}} devResetDatabase
    rm -f .env installed.lock
    @echo ""
    @echo "Access: https://meetagain.local/install/"

# Clean generated files
[group('development')]
devResetConfigs:
    rm -rf .env installed.lock config/plugins.php var/

# Reset to fresh clone state
[group('development')]
devResetToFreshCloneState:
    rm -rf .env installed.lock config/plugins.php vendor/ var/ public/bundles/ public/assets/
    cp assets/styles/_config.scss.dist assets/styles/_config.scss

# Reset database
[group('development')]
devResetDatabase:
    {{PHP}} php bin/console doctrine:database:drop --force --if-exists
    {{PHP}} php bin/console doctrine:database:create --if-not-exists

# Empty Mailpit, then queue every email template x language for a visual pass
[group('development')]
devEmailPreview *options='':
    curl -sS -X DELETE http://localhost:8025/api/v1/messages
    {{PHP}} php bin/console app:email:preview {{options}}
    @echo "Queued - dispatch with: just appCron"
    @echo "Sendlog: https://meetagain.local/en/admin/email/sendlog"
    @echo "Inbox: http://localhost:8025"
