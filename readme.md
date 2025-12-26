# MeetAgain

[![Gitea Release](https://img.shields.io/badge/Version-v0.5.0-31c754.svg)](https://github.com/xuedi/meetAgain/releases)
[![EUPL Licence](https://img.shields.io/badge/Licence-EUPL_v1.2-31c754.svg)](https://eupl.eu/1.2/en)
[![EUPL Licence](https://img.shields.io/badge/Roadmap-0.6-31c754.svg)](https://github.com/xuedi/meetAgain/milestones?sort=title&direction=asc)
[![PHP unit tests](https://github.com/xuedi/meetAgain/actions/workflows/phpunit.yml/badge.svg)](https://github.com/xuedi/meetAgain/actions/workflows/phpunit.yml)
[![Code Coverage](https://raw.githubusercontent.com/xuedi/meetAgain/main/tests/badge/coverage.svg)](https://github.com/xuedi/meetAgain/blob/master/tests/badgeGenerator.php)

## Introduction

A self-hosted, open-source alternative to meetup.com for organizing groups and scheduling events. Built as a single-instance meetup platform with a modular CMS to customize pages for different groups.

### Features

- Recurring events with flexible scheduling (daily, weekly, monthly)
- Block-based CMS for custom pages
- Multi-language support with community translations
- Plugin system for extensibility
- User management with email verification
- RSVP tracking and event filtering
- Private messaging and activity feeds

### Tech Stack

- **Backend:** Symfony 8.0 / PHP 8.4
- **Database:** MariaDB with Doctrine ORM
- **Cache:** Valkey (Redis-compatible)
- **Web Server:** Caddy/FrankenPHP (HTTP/2, HTTP/3)
- **Frontend:** Twig templates with Bulma CSS

### Software Design

A classic PHP Symfony application, as upstream as possible with no fancy libraries. Local development runs in Docker via justfile. Uses basic Twig templating with upstream Bulma and minimal JS & CSS.

## Requirements

### For Development

- [Docker](https://docs.docker.com/get-docker/) & Docker Compose
- [Just](https://github.com/casey/just) (task runner)

### For Production

- PHP >= 8.4 with modules: apcu, pdo_mysql, imagick, intl, iconv, ctype (Optional: xdebug, opcache, gd)
- MariaDB or MySQL database
- Web server (Caddy, Nginx, Apache)
- Composer (installed automatically by web installer)

## Development Installation

For local development with Docker:

```bash
just install
```

Then login as `admin@example.org` with password `1234`

### Docker Services

| Service | URL | Description |
|---------|-----|-------------|
| Web | http://localhost | Main application |
| MailHog | http://localhost:8025 | Email testing UI |
| MariaDB | localhost:3306 | Database |

## Production Installation

For production deployments or manual installation without Docker:

1. Clone the repository to your server
2. Configure your web server to serve the `public/` directory
3. Navigate to your domain in a browser - you'll be automatically redirected to `/install/`
4. Follow the web installer wizard:
   - **Step 1: System Requirements & Database**
     - Verify PHP requirements
     - Configure database connection (host, port, database name, credentials)
   - **Step 2: Mail Configuration**
     - Choose your mail provider:
       - **SMTP** - Custom SMTP server
       - **SendGrid** - SendGrid API
       - **Mailgun** - Mailgun API
       - **Amazon SES** - AWS Simple Email Service
       - **MailHog** - Development mail testing
       - **Null** - Disable email sending
   - **Step 3: Site Configuration**
     - Set site URL and name
     - Create your admin account
5. The installer will automatically:
   - Generate `.env` configuration file
   - Run `composer install`
   - Execute database migrations
   - Create system and admin users

Once installation is complete, login with the admin credentials you created. The installer becomes inaccessible after successful installation via the `installed.lock` file.

## Development Commands
See available commands in [justfile](justfile). By typing in the terminal: `just`

## Project Structure

```
meetAgain/
├── src/               # Application code
│   ├── Controller/    # HTTP controllers
│   ├── Entity/        # Doctrine entities
│   ├── Repository/    # Database repositories
│   └── Service/       # Business logic
├── templates/         # Twig templates
├── config/            # Symfony configuration
├── plugins/           # Plugin system (glossary, karaoke, dishes)
├── docker/            # Docker setup
├── tests/             # PHPUnit tests
├── migrations/        # Database migrations
└── translations/      # i18n files
```

## PhpStorm Setup

For the Docker remote PHP interpreter, add this environment parameter:
```
COMPOSE_ENV_FILES=../.env
```

## Insights

![Alt](https://repobeats.axiom.co/api/embed/e864b7990dd563003e91dc7f2d92bf09103aa917.svg "Repobeats analytics image")

## Support

[![PayPal](https://img.shields.io/badge/PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://www.paypal.com/donate/?hosted_button_id=76XY2B8VZPTXL)