#!/usr/bin/env bash
set -euo pipefail

# Usage: bin/update-bulma.sh <docker-php-exec-cmd> [version]
# Example: bin/update-bulma.sh "docker-compose exec php" latest
PHP="$1"
VERSION="${2:-latest}"

if [ "$VERSION" = "latest" ]; then
    TAG=$($PHP curl -s https://api.github.com/repos/jgthms/bulma/releases/latest | grep -o '"tag_name": "[^"]*' | grep -o '[^"]*$')
else
    TAG="$VERSION"
fi
echo "Vendoring Bulma $TAG..."
$PHP bash -c "curl -fsSL https://github.com/jgthms/bulma/archive/refs/tags/$TAG.tar.gz | tar xz -C /tmp && rm -rf assets/styles/vendor/bulma && mv /tmp/bulma-$TAG assets/styles/vendor/bulma"
echo "Bulma $TAG vendored to assets/styles/vendor/bulma/"
