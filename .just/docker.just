# Docker container lifecycle and direct database access.

# Start containers and prepare logs
[group('docker')]
dockerStart:
	{{DOCKER}} up -d
	{{JUST}} storageInit
	{{PHP}} mkdir -p var/log
	{{PHP}} touch var/log/dev.log
	{{PHP}} truncate -s 0 var/log/dev.log

# Prepare the dev Garage (layout, key, buckets) and copy the local images, thumbnails and invoices into it
[group('docker')]
storageInit:
	DOCKER="{{DOCKER}}" docker/garage/init.sh
	{{PHP}} php bin/console app:storage:copy -q

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

# Stop the message worker and the scheduler, so nothing consumes while the database is rebuilt
[group('docker')]
workerStop:
    {{DOCKER}} stop worker scheduler

# Empty the task stream and start the message worker and the scheduler
[group('docker')]
workerStart:
    {{DOCKER}} exec -T valkey valkey-cli DEL tasks
    {{DOCKER}} up -d worker scheduler

# Make every running worker exit after its current message, so the restarted ones run the new code
[group('docker')]
workerRestart:
    {{PHP}} php bin/console messenger:stop-workers
