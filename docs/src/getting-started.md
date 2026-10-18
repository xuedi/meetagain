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
| Worker      | `ma-worker`                   | internal                | Runs handed-off work       |
| Scheduler   | `ma-scheduler`                | internal                | Runs the periodic tasks    |
| Database    | `ma-db` (MariaDB 13)          | localhost:3306          | Relational DB              |
| Email       | `ma-mailpit` (Mailpit)        | http://localhost:8025   | Catches all outgoing email |
| Cache       | `ma-valkey` (Valkey)          | internal                | Cache, sessions, queue     |
| Storage     | `ma-garage` (Garage)          | internal                | S3 store for uploads       |

Mail is queued and the worker sends it within a second; Mailpit captures every outgoing email - no real mail is sent
in dev mode. The worker and the scheduler restart every minute, so they pick up code changes on their own; `just
workerRestart` makes them restart now. The periodic tasks are listed at Admin, Logs, Tasks, or with `just app
app:task:list`.

### Debugging a handler or a task

The worker containers run without Xdebug. To step through a task or a message handler, run it inside `ma-php` with
the step debugger switched on, so your IDE gets the session:

```bash
docker exec -it -e XDEBUG_MODE=debug -e XDEBUG_SESSION=1 ma-php php bin/console app:task:run <identifier>
docker exec -it -e XDEBUG_MODE=debug -e XDEBUG_SESSION=1 ma-php php bin/console messenger:consume async --limit=1
```

The first runs one task now; the second handles the next waiting message. Stop the worker first (`just workerStop`,
and `just workerStart` afterwards) when the message must not be taken by it.

The database and Mailpit are published to `127.0.0.1` only, so they are reachable from your own machine and from
nowhere else. Mailpit has no authentication and holds every password-reset link the dev site produces, and the
database password is the one committed in `.env.dist`; on a shared network, publishing either on every interface
hands both to anyone who can reach your host. Keep the `127.0.0.1:` prefix on those port mappings.

---

## Reset variants

```bash
just devModeImport               # List the demo archives
just devModeImport <archive>     # Fresh instance built from one demo archive
just devModeBootstrap            # Empty instance with one admin, as a fresh install starts
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
