# Docker container lifecycle and direct database access.

# Start containers and prepare logs
[group('docker')]
dockerStart:
	{{DOCKER}} up -d
	{{PHP}} mkdir -p var/log
	{{PHP}} touch var/log/dev.log
	{{PHP}} truncate -s 0 var/log/dev.log

# Stop containers
[group('docker')]
dockerStop:
	{{DOCKER}} --profile metrics down

# Start the optional metrics collector and Grafana; set METRICS_DSN in .env.local to feed them
[group('docker')]
dockerMetricsStart:
	{{DOCKER}} --profile metrics up -d victoriametrics grafana
	@echo "Grafana: http://localhost:3001 - metrics flow once .env.local has METRICS_DSN=udp://victoriametrics:8089?app=meetagain"

# Stop the optional metrics collector and Grafana, keeping their data
[group('docker')]
dockerMetricsStop:
	{{DOCKER}} --profile metrics stop victoriametrics grafana

# Restart containers
[group('docker')]
dockerRestart: dockerStop dockerStart

# Rebuild PHP image (no cache)
[group('docker')]
dockerRebuild:
    {{DOCKER}} build --no-cache php

# Enter PHP container shell
[group('docker')]
dockerEnter:
    {{DOCKER}} exec php bash

# Run SQL command with the parameter: query
[group('docker')]
dockerDatabase query:
    {{DB}} mariadb -u$MARIADB_USER -p$MARIADB_PASSWORD $MARIADB_DATABASE -e "{{query}}"
