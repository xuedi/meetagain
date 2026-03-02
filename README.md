# MeetAgain

[![Version](https://img.shields.io/badge/Version-v1.0.0-31c754.svg)](https://codeberg.org/xuedi/meetAgain/releases)
[![EUPL Licence](https://img.shields.io/badge/Licence-EUPL_v1.2-31c754.svg)](https://eupl.eu/1.2/en)
[![Code Coverage](https://codeberg.org/xuedi/meetAgain/raw/branch/main/tests/badge/coverage.svg)](https://codeberg.org/xuedi/meetAgain/src/branch/main/src/Command/BadgeGenerateCommand.php)

A self-hosted, open-source alternative to Meetup.com for organizing groups and scheduling events. Single group installation like WordPress

## Features

- Recurring events with flexible scheduling (daily, weekly, monthly)
- Block-based CMS for custom pages
- Multi-language support with community translations
- Plugin system for extensibility (Dishes, Film Club, Book Club, Karaoke, Glossary, and more)
- User management with email verification
- RSVP tracking and event notifications
- Aggregated notifications for followers
- Private messaging and activity feeds

## Tech Stack

- **Backend:** Symfony 8.0 / PHP 8.4
- **Database:** MariaDB with Doctrine ORM
- **Cache:** Valkey (Redis-compatible)
- **Web Server:** Caddy/FrankenPHP (HTTP/2, HTTP/3)
- **Frontend:** Twig templates with Bulma CSS

## Hosted Version

Not ready to self-host? **[meetagain.org](https://meetagain.org)** offers a managed
version with no server setup required.

Your data is always yours — you can export it at any time and migrate to a
self-hosted instance whenever you want.

## Local Development

Requires [Docker](https://docs.docker.com/get-docker/) with Docker Compose and [Just](https://github.com/casey/just)
task runner.

### Quick Start

```bash
just devModeFixtures
```

Login at http://localhost as `admin@example.org` with password `1234`

### Development Modes

| Command                          | Description                                     |
|----------------------------------|-------------------------------------------------|
| `just devModeFixtures`           | Full reset with demo data                       |
| `just devModeInstaller`          | Test the web installer at `/install/`           |
| `just devResetToFreshCloneState` | Nuclear option - removes vendor/, var/, configs |

### Docker Services

| Service | URL                   |
|---------|-----------------------|
| Web     | http://localhost      |
| MailHog | http://localhost:8025 |
| Valkey  | localhost:6379        |
| MariaDB | localhost:3306        |

Run `just` to see all available commands.

## Plugin Development

Want to extend meetAgain? See the [Plugin Development Guide](docs/plugin-development.md) for:

- Complete plugin interface reference
- Hook methods and when they're called
- Optional interfaces (filters, authorization, notifications)
- Real-world examples from existing plugins
- Quick start templates

**Enable a plugin:** `just plugin-enable <plugin-name>`

## Production Installation

**Requirements:** PHP >= 8.4 (apcu, pdo_mysql, imagick, intl, iconv, ctype, redis), MariaDB/MySQL, web server (Caddy,
Nginx, Apache)

1. Clone repository from https://codeberg.org/xuedi/meetAgain.git and configure web server to serve `public/`
2. Navigate to your domain - redirects to `/install/`
3. Follow the wizard:
    - **Step 1:** Verify PHP requirements, configure database
    - **Step 2:** Choose mail provider (SMTP, SendGrid, Mailgun, Amazon SES, or Null)
    - **Step 3:** Set site URL/name, create admin account
4. Installer auto-runs composer, migrations, and user creation

The `installed.lock` file prevents re-running the installer.

## PhpStorm Setup

For the Docker remote PHP interpreter, add `COMPOSE_ENV_FILES=../.env` as environment parameter.

## Support

[![PayPal](https://img.shields.io/badge/PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://www.paypal.com/donate/?hosted_button_id=76XY2B8VZPTXL)
