
# Start a Mastodon and a Mobilizon test server next to the dev stack (up), rejoin the app containers after a restart (join), stop them (down) or drop their data (reset)
[group('development')]
devFediverse action='up':
    plugins/fediverse/docker/devFediverse.sh {{action}}
