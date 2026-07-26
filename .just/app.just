# Symfony console wrappers, migrations, cache and asset compilation.

# Run Symfony console command
[group('app')]
app +parameter='':
    {{PHP}} php bin/console {{parameter}}

# Run cron tasks
[group('app')]
appCron:
    {{PHP}} php bin/console app:cron

# Clear cache and autoload
[group('app')]
appClearCache:
    {{PHP}} composer dump-autoload
    {{JUST}} app cache:pool:clear --all -q
    {{PHP}} php bin/console cache:clear -q

# Run migrations
[group('app')]
appMigrate:
    {{PHP}} php bin/console doctrine:migrations:migrate -n -q
    {{PHP}} php bin/console app:email-templates:seed
    {{PHP}} php bin/console app:cache:app-state:clear -q
    {{PHP}} php bin/console app:cache:config:clear -q

# Check for upgradable dependencies
[group('app')]
appUpgrade:
    clear
    {{JUST}} do 'composer show --outdated'

# Compile SCSS and version all assets into public/assets/
[group('app')]
appAssets:
    rm -rf public/assets/ public/media/
    {{PHP}} php bin/console sass:build
    {{PHP}} php bin/console asset-map:compile
    {{PHP}} php bin/console app:media:compile
    {{PHP}} purgecss --config assets/purgecss.config.js
    {{PHP}} php bin/console cache:clear -q
    {{PHP}} php bin/console cache:pool:clear cache.cms_page_cache -q

# Watch SCSS for changes during development
[group('app')]
appAssetsWatch:
    {{PHP}} php bin/console sass:build --watch

# Download/update Bulma SCSS source (run when updating Bulma version)
[group('app')]
appUpdateBulma version='latest':
    bin/update-bulma.sh "{{PHP}}" "{{version}}"
