# Enable, disable and inspect plugins.

# List available plugins with their manifest information
[group('plugins')]
plugin-list:
    {{PHP}} php bin/console app:plugin:list

# Switch plugins on, by key or "all"
[group('plugins')]
plugin-enable +names:
    {{PHP}} php bin/console app:plugin:toggle on {{names}}

# Switch plugins off, by key or "all"
[group('plugins')]
plugin-disable +names:
    {{PHP}} php bin/console app:plugin:toggle off {{names}}
