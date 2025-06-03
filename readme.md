
[![Gitea Release](https://img.shields.io/badge/Version-v0.5.0-31c754.svg)](https://github.com/xuedi/meetAgain/releases)
[![EUPL Licence](https://img.shields.io/badge/Licence-EUPL_v1.2-31c754.svg)](https://eupl.eu/1.2/en)
[![PHP unit tests](https://github.com/xuedi/meetAgain/actions/workflows/phpunit.yml/badge.svg)](https://github.com/xuedi/meetAgain/actions/workflows/phpunit.yml)
[![EUPL Licence](https://img.shields.io/badge/Roadmap-0.6-31c754.svg)](https://github.com/xuedi/meetAgain/milestones?sort=title&direction=asc)
[![Code Coverage](https://raw.githubusercontent.com/xuedi/meetAgain/main/tests/badge/coverage.svg)](https://github.com/xuedi/meetAgain/blob/master/tests/badgeGenerator.php)

## Introduction
meetup.com got crazily expensive, I created my own page as a single meetup
instance with a basic modular CMS to customize any number of pages. Menus and
such are static and have to be changed in code as for now.


### Software design
A classic PHP symfony application, as upstream as possible no fancy libraries. Local 
development in docker via JustFile. Has just basic twig templating with upstream bulma
and almost no JS & CSS. 


### PHP modules
I used some nice PHP >= 8.4 features out of convenience. Module needed are:
apcu, pdo_mysql, imagick, intl, iconv, ctype. Optional: xdebug, opcache, gd


### Installation
For local installation, when you have the tool `just` and `docker` installed, the only
thing you need to do is `just install` and then login as admin@example.org @ 1234


### Phpstorm
For aesthetic reasons I try to keep the root folder as clean as possible, so docker and other
configs like tests and code-check tools are in their respective folders. To have phpstorm run
smoothly with the docker container, the config has to be bent a bit.
```
COMPOSE_ENV_FILES=../.env # env parameter for phpstorm docker remote php interpreter
```

