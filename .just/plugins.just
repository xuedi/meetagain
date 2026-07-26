# Enable, disable and inspect plugins.

# List available plugins with their manifest information
[group('plugins')]
plugin-list:
    {{PHP}} php bin/console app:plugin:list

# Enable a specific plugin without affecting others
[group('plugins')]
plugin-enable name:
    {{PHP}} php bin/console app:plugin enable {{name}}

# Disable a specific plugin without affecting others
[group('plugins')]
plugin-disable name:
    {{PHP}} php bin/console app:plugin disable {{name}}
