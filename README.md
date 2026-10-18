# MeetAgain

[![Version](https://img.shields.io/badge/Version-v1.0.0-31c754.svg)](https://github.com/xuedi/meetagain/releases)
[![EUPL Licence](https://img.shields.io/badge/Licence-EUPL_v1.2-31c754.svg)](https://eupl.eu/1.2/en)
[![Code Coverage](https://raw.githubusercontent.com/xuedi/meetagain/main/tests/badge/coverage.svg)](https://github.com/xuedi/meetagain/blob/main/src/Command/BadgeGenerateCommand.php)

A self-hosted, open-source alternative to other cooperate meetup websites for organizing groups and scheduling events. Single group installation like WordPress

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

- **Backend:** Symfony 8.0 / PHP 8.4+
- **Database:** MariaDB with Doctrine ORM
- **Cache:** Valkey (Redis-compatible)
- **Web Server:** Caddy/FrankenPHP (HTTP/2, HTTP/3)
- **Frontend:** Twig templates with Bulma CSS

## Hosted Version

Not ready to self-host? **[meetagain.org](https://meetagain.org)** offers a managed
version with no server setup required.

Your data is always yours — you can export it at any time and migrate to a
self-hosted instance whenever you want.

## Documentation

generate the documentation by running `just fixDocumentation` or see the generated documentation at https://documentation.meetagain.org/

## Local Development

Requires [Docker](https://docs.docker.com/get-docker/) with Docker Compose and [Just](https://github.com/casey/just)
task runner.

### Quick Start

```bash
just devModeImport weiqi-club
```

Login at https://meetagain.local as `Crystal.Liu@example.org` with password `1234`. `just devModeImport`
without an argument lists the other demo archives.

Run `just` to see all available commands.

## Production Installation

MeetAgain runs as containers. The public image `ghcr.io/xuedi/meetagain` holds the core and every bundled
plugin, and runs as web server, message worker or scheduler, or as all three in one container. It needs
MariaDB and Valkey (or Redis) beside it. [Hosting](docs/src/hosting.md) has a Docker Compose example, the
first-time setup and the update steps.

## PhpStorm Setup

For the Docker remote PHP interpreter, add `COMPOSE_ENV_FILES=../.env` as environment parameter.

## Support

[![PayPal](https://img.shields.io/badge/PayPal-00457C?style=for-the-badge&logo=paypal&logoColor=white)](https://www.paypal.com/donate/?hosted_button_id=76XY2B8VZPTXL)
