# Getting Started

Self-hosting MeetAgain for a single group.

---

## Prerequisites

- **Docker** and **Docker Compose** (v2)
- **[just](https://github.com/casey/just)** command runner (`cargo install just` or via your package manager)
- A domain or local hostname (for local dev: `meetagain.local`)

---

## Quick Start

### 1. Clone the repository

```bash
git clone https://github.com/xuedi/meetAgain.git
cd meetAgain
```

### 2. Start the installer

```bash
just devModeInstaller
```

This starts the Docker containers with a blank database and no `.env` file,
putting the app into installer mode.

### 3. Open the installer

Navigate to `https://meetagain.local/install/` and follow the setup wizard.
The installer creates your admin account, sets up the database, and writes
the `.env` configuration file.

### 4. Done

After the installer completes you have a running single-group instance.
Log in with the credentials you chose during setup.

---

## Development Environment

For a development environment with sample data:

```bash
# Full environment with fixture data (events, users, CMS pages)
just devModeFixtures

# Minimal environment (install fixtures only — useful for testing imports)
just devModeMinimal

# Enable a specific plugin
just devModeFixtures multisite
```

Default admin credentials in dev mode: `admin@example.org` / `1234`

---

## Available Commands

```bash
just                    # List all commands
just start              # Start containers
just stop               # Stop containers
just app <cmd>          # Run a Symfony console command
just appMigrate         # Run database migrations
just appClearCache      # Clear caches
```

---

## Enabling Plugins

```bash
# List available plugins
just plugin-list

# Enable a plugin
just plugin-enable dishes

# Disable a plugin
just plugin-disable dishes
```

After enabling a plugin, run `just appMigrate` to apply its database migrations.

---

## Local Hostname Setup

Add the following to `/etc/hosts`:

```
127.0.0.1   meetagain.local
```

The development server runs on HTTPS with a self-signed certificate.
You may need to accept the certificate warning in your browser on first visit.
