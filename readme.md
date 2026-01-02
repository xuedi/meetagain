# MeetAgain

[![Gitea Release](https://img.shields.io/badge/Version-v0.5.0-31c754.svg)](https://github.com/xuedi/meetAgain/releases)
[![EUPL Licence](https://img.shields.io/badge/Licence-EUPL_v1.2-31c754.svg)](https://eupl.eu/1.2/en)
[![EUPL Licence](https://img.shields.io/badge/Roadmap-1.0-31c754.svg)](https://github.com/xuedi/meetAgain/milestones?sort=title&direction=asc)
[![PHP unit tests](https://github.com/xuedi/meetAgain/actions/workflows/phpunit.yml/badge.svg)](https://github.com/xuedi/meetAgain/actions/workflows/phpunit.yml)
[![Code Coverage](https://raw.githubusercontent.com/xuedi/meetAgain/main/tests/badge/coverage.svg)](https://github.com/xuedi/meetAgain/blob/master/tests/badgeGenerator.php)

A self-hosted, open-source alternative to meetup.com for organizing groups and scheduling events. Built as a single-instance platform with recurring events, a block-based CMS for custom pages, multi-language support, a plugin system, user management with email verification, RSVP tracking, aggregated notifications, and private messaging.

**Tech Stack:** Symfony 8.0 / PHP 8.4, MariaDB with Doctrine ORM, Valkey (Redis-compatible) cache, Caddy/FrankenPHP, Twig templates with Bulma CSS. A classic upstream Symfony application with minimal dependencies and basic templating.

## Development

**Requirements:** [Docker](https://docs.docker.com/get-docker/) with Docker Compose and [Just](https://github.com/casey/just) task runner.

**Quick Start:** Run `just install`, then login at http://localhost as `admin@example.org` with password `1234`. MailHog is available at http://localhost:8025 for email testing, MariaDB at port 3306, and Valkey at port 6379.

**Demo with Fixtures:** To run a fully functional local demo with sample data, use `just devModeFixtures`. This resets the environment, installs dependencies, and populates the database with fixture data for testing all features.

**Commands:** Run `just` to see all available commands in the [justfile](justfile).

## Production

**Requirements:** PHP >= 8.4 (with apcu, pdo_mysql, imagick, intl, iconv, ctype, redis modules), MariaDB/MySQL, and a web server (Caddy, Nginx, or Apache).

**Installation:** Clone the repository, configure your web server to serve `public/`, then navigate to your domain. You'll be redirected to `/install/` where a wizard guides you through database configuration, mail provider setup (SMTP, SendGrid, Mailgun, Amazon SES, or MailHog), and site configuration including admin account creation. The installer handles `.env` generation, Composer install, database migrations, and user creation automatically. After completion, `installed.lock` prevents re-running the installer.

## PhpStorm Setup

For the Docker remote PHP interpreter, add `COMPOSE_ENV_FILES=../.env` as environment parameter.

## Insights

![Alt](https://repobeats.axiom.co/api/embed/e864b7990dd563003e91dc7f2d92bf09103aa917.svg "Repobeats analytics image")

## Support

[![PayPal](https://img.shields.io/badge/PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://www.paypal.com/donate/?hosted_button_id=76XY2B8VZPTXL)