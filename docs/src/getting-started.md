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
just devModeImport weiqi-club
# Open https://meetagain.local
```

`just devModeImport weiqi-club` starts all Docker containers, resets the database and imports the
Weiqi Club demo archive (members, events, CMS pages, films, photos). Log in as `Crystal.Liu@example.org`
with the password `1234`. See [Demo Data](core-development/demo-data.md) for the other archives.

---

## Dev services

Once started, the following services are available:

| Service     | Container                     | Address                 | Purpose                    |
|-------------|-------------------------------|-------------------------|----------------------------|
| Application | `ma-php` (FrankenPHP + Caddy) | https://meetagain.local | The app                    |
| Database    | `ma-db` (MariaDB 12)          | localhost:3306          | Relational DB              |
| Email       | `ma-mailpit` (Mailpit)        | http://localhost:8025   | Catches all outgoing email |
| Cache       | `ma-valkey` (Valkey)          | internal                | Redis-compatible cache     |

Mail is queued, not sent: `just appCron` dispatches the queue, and Mailpit then captures every outgoing email - no real mail is sent in dev mode.

---

## Reset variants

```bash
just devModeImport               # List the demo archives
just devModeImport <archive>     # Fresh instance built from one demo archive
just devModeInstaller            # Empty instance, set up through the web installer
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
