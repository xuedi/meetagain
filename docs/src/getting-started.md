# Getting Started

Setting up a local development environment for contributing to MeetAgain.

For production deployment, see [Hosting](hosting.md).

---

## Prerequisites

- **Docker** and **Docker Compose** (v2)
- **[just](https://github.com/casey/just)** command runner (`cargo install just` or via your package manager)
- A local hostname entry for `meetagain.local` (see [Local Hostname Setup](#local-hostname-setup))

---

## Local Hostname Setup

Add the following to `/etc/hosts`:

```
127.0.0.1   meetagain.local
```

The development server runs on HTTPS with a self-signed certificate.
Accept the certificate warning in your browser on first visit.

---

## Start in 3 commands

```bash
git clone https://github.com/xuedi/meetAgain.git && cd meetAgain
just devModeFixtures
# Open https://meetagain.local
```

`just devModeFixtures` resets the database, loads sample fixtures (events, users, CMS pages),
and starts all Docker containers. Default admin credentials: `admin@example.org` / `1234`

---

## Dev services

Once started, the following services are available:

| Service     | Container                     | Address                 | Purpose                    |
|-------------|-------------------------------|-------------------------|----------------------------|
| Application | `ma-php` (FrankenPHP + Caddy) | https://meetagain.local | The app                    |
| Database    | `ma-db` (MariaDB 12)          | localhost:3306          | Relational DB              |
| Email       | `ma-mailhog` (MailHog)        | http://localhost:8025   | Catches all outgoing email |
| Cache       | `ma-valkey` (Valkey)          | internal                | Redis-compatible cache     |

MailHog captures every outgoing email — no real mail is sent in dev mode.

---

## Reset variants

```bash
just devModeFixtures             # Full environment with fixture data
just devModeMinimal              # Install fixtures only (useful for testing imports)
just devModeFixtures multisite   # Full environment with the multisite plugin enabled
```

---

## Essential commands

```bash
just                    # List all commands
just start              # Start containers
just stop               # Stop containers
just app <cmd>          # Run a Symfony console command
just appMigrate         # Run database migrations
just appClearCache      # Clear caches
just testUnit           # Run unit tests
just test               # Run all tests and checks
just fixMago            # Auto-format code (run before committing)
```
