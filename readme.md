# MeetAgain

[![Gitea Release](https://img.shields.io/badge/Version-v0.5.0-31c754.svg)](https://github.com/xuedi/meetAgain/releases)
[![EUPL Licence](https://img.shields.io/badge/Licence-EUPL_v1.2-31c754.svg)](https://eupl.eu/1.2/en)
[![EUPL Licence](https://img.shields.io/badge/Roadmap-1.0-31c754.svg)](https://github.com/xuedi/meetAgain/milestones?sort=title&direction=asc)
[![PHP unit tests](https://github.com/xuedi/meetAgain/actions/workflows/phpunit.yml/badge.svg)](https://github.com/xuedi/meetAgain/actions/workflows/phpunit.yml)
[![Code Coverage](https://raw.githubusercontent.com/xuedi/meetAgain/main/tests/badge/coverage.svg)](https://github.com/xuedi/meetAgain/blob/master/tests/badgeGenerator.php)

A self-hosted, open-source alternative to meetup.com for organizing groups and scheduling events. Built as a single-instance platform with recurring events, a block-based CMS for custom pages, multi-language support, a plugin system, user management with email verification, RSVP tracking, aggregated notifications, and private messaging.

A self-hosted, open-source alternative to meetup.com for organizing groups and scheduling events. Built as a single-instance meetup platform with a modular CMS to customize pages for different groups.

### Features

- Recurring events with flexible scheduling (daily, weekly, monthly)
- Block-based CMS for custom pages
- Multi-language support with community translations
- Plugin system for extensibility
- User management with email verification
- RSVP tracking and event notifications
- Aggregated notifications for followers
- Private messaging and activity feeds

### Tech Stack

- **Backend:** Symfony 8.0 / PHP 8.4
- **Database:** MariaDB with Doctrine ORM
- **Cache:** Valkey (Redis-compatible)
- **Web Server:** Caddy/FrankenPHP (HTTP/2, HTTP/3)
- **Frontend:** Twig templates with Bulma CSS

### Software Design

A classic PHP Symfony application, as upstream as possible with no fancy libraries. Local development runs in Docker via justfile. Uses basic Twig templating with upstream Bulma and minimal JS & CSS.

## Local Development

Requires [Docker](https://docs.docker.com/get-docker/) with Docker Compose and [Just](https://github.com/casey/just) task runner.

### Development Modes

| Command | Description |
|---------|-------------|
| `just devModeFixtures` | Full reset with demo data - resets everything, installs dependencies, loads fixtures |
| `just devModeInstaller` | Test the web installer - resets to fresh state, access at `/install/` |
| `just devResetToFreshCloneState` | Nuclear option - removes vendor/, var/, and all configs |

### Quick Start

```bash
just devModeFixtures
```

Login at http://localhost as `admin@example.org` with password `1234`

### Docker Services

| Service | URL |
|---------|-----|
| Web | http://localhost |
| MailHog | http://localhost:8025 |
| Valkey | localhost:6379 |
| MariaDB | localhost:3306 |

### Commands

Run `just` to see all available commands.

## Production Installation

**Requirements:** PHP >= 8.4 (apcu, pdo_mysql, imagick, intl, iconv, ctype, redis), MariaDB/MySQL, web server (Caddy, Nginx, Apache)

1. Clone repository and configure web server to serve `public/`
2. Navigate to your domain - redirects to `/install/`
3. Follow the wizard:
   - **Step 1:** Verify PHP requirements, configure database
   - **Step 2:** Choose mail provider (SMTP, SendGrid, Mailgun, Amazon SES, or Null)
   - **Step 3:** Set site URL/name, create admin account
4. Installer auto-runs composer, migrations, and user creation

Login with your admin credentials. The `installed.lock` file prevents re-running the installer.

## PhpStorm Setup

For the Docker remote PHP interpreter, add `COMPOSE_ENV_FILES=../.env` as environment parameter.

## Insights

![Alt](https://repobeats.axiom.co/api/embed/e864b7990dd563003e91dc7f2d92bf09103aa917.svg "Repobeats analytics image")

## Support

[![PayPal](https://img.shields.io/badge/PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://www.paypal.com/donate/?hosted_button_id=76XY2B8VZPTXL)