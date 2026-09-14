# Docker configuration - all commands run inside containers
# Read the comments in this file to understand what each command does.
# Always use `just test` to run tests, not `just do "vendor/bin/phpunit ..."`.
#
# Recipes live in .just/, one file per group. This file keeps only the settings,
# the shared variables every group file uses, and the ungrouped entry points.
set dotenv-load

# Every recipe line runs through bin/just-shell, which hands it to output-filter once that is built
set quiet
set shell := ["bin/just-shell", "sh", "-cu"]

DOCKER := "docker-compose --env-file .env.dist -f docker/docker-compose.yml"
PHP := DOCKER + " exec -T -e XDEBUG_MODE=off php"
PHP_COVERAGE := DOCKER + " exec -T -e XDEBUG_MODE=coverage php"
DB := DOCKER + " exec -T mariadb"
JUST := just_executable() + " --justfile=" + justfile()

# `just debug=1 <recipe>` runs every command unfiltered and shows it, as just's own echo would
debug := env("OUTPUT_FILTER_DEBUG", "")
export OUTPUT_FILTER_DEBUG := debug
export OUTPUT_FILTER_RUN := env("OUTPUT_FILTER_RUN", uuid())

# Show commands
default:
    @echo ""
    @echo "  ███╗   ███╗███████╗███████╗████████╗     █████╗  ██████╗  █████╗ ██╗███╗   ██╗"
    @echo "  ████╗ ████║██╔════╝██╔════╝╚══██╔══╝    ██╔══██╗██╔════╝ ██╔══██╗██║████╗  ██║"
    @echo "  ██╔████╔██║█████╗  █████╗     ██║       ███████║██║  ███╗███████║██║██╔██╗ ██║"
    @echo "  ██║╚██╔╝██║██╔══╝  ██╔══╝     ██║       ██╔══██║██║   ██║██╔══██║██║██║╚██╗██║"
    @echo "  ██║ ╚═╝ ██║███████╗███████╗   ██║       ██║  ██║╚██████╔╝██║  ██║██║██║ ╚████║"
    @echo "  ╚═╝     ╚═╝╚══════╝╚══════╝   ╚═╝       ╚═╝  ╚═╝ ╚═════╝ ╚═╝  ╚═╝╚═╝╚═╝  ╚═══╝"
    @echo ""
    @{{JUST}} --list --unsorted

# Start docker
start: dockerStart

# Stop docker
stop: dockerStop

# Run command in PHP container
do +parameter='':
    {{PHP}} {{parameter}}

import '.just/docker.just'
import '.just/app.just'
import '.just/development.just'
import '.just/plugins.just'
import '.just/testing.just'
import '.just/checks.just'
import '.just/fixing.just'
import '.just/translations.just'
import '.just/tools.just'
import? '.just/local.just'
