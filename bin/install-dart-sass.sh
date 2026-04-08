#!/usr/bin/env bash
set -euo pipefail

# Usage: bin/install-dart-sass.sh <docker-php-exec-cmd>
# Example: bin/install-dart-sass.sh "docker-compose exec php"
PHP="$1"

if [ ! -f bin/dart-sass/sass ]; then
    echo "Downloading dart-sass 1.86.3..."
    $PHP bash -c "curl -fsSL https://github.com/sass/dart-sass/releases/download/1.86.3/dart-sass-1.86.3-linux-x64.tar.gz | tar xz -C /tmp && rm -rf bin/dart-sass && mv /tmp/dart-sass bin/dart-sass"
fi
