
[![Gitea Release](https://img.shields.io/badge/Version-v0.5.0-31c754.svg)](https://github.com/xuedi/meetAgain/releases)
[![EUPL Licence](https://img.shields.io/badge/Licence-EUPL_v1.2-31c754.svg)](https://eupl.eu/1.2/en)
[![PHP unit tests](https://github.com/xuedi/meetAgain/actions/workflows/phpunit.yml/badge.svg)](https://github.com/xuedi/meetAgain/actions/workflows/phpunit.yml)
[![Code Coverage](https://raw.githubusercontent.com/xuedi/meetAgain/main/tests/badge/coverage.svg)](https://github.com/xuedi/meetAgain/blob/master/tests/badgeGenerator.php)

## Introduction
meetup.com got crazily expensive, I created my own page as a single meetup
instance, with basic modular CMS to customize any number of pages. Menus and
such are static and have to be changed in code. | Minimalist PHP8.4+ Symfony


### Software design
A classic symfony application as upstream as possible no fancy libraries
or dependencies. Local development in docker via JustFile.  


### PHP modules
I used some nice PHP >= 8.4 features out of convenience. Module needed are:
gd, apcu, pdo_mysql, imagick, intl, iconv, ctype. Optional: xdebug opcache


### Usage
```
just             # get a list with all possible cli interactions
just install     # deletes all data and reset to fresh fixture version
just dockerStart # starts docker development stack
just dockerStop  # stops the stack
``` 


### TODO:
 - an option when a recurring event was changed, to apply changes on all inherit event that follow
 - write cron jobs: 
   - removing regCodes and deactivating user that have not confirmed their emails in xDays
   - sending notifications about events and messages, and the config on how frequent if at all
 - add system config setting with slider on off to make for example for: auto register confirmation
 - remove hardcoded user and make system user configurable: src/Controller/SecurityController.php:94
 - add user settings to control what email notification will be sent and how often
 - add functionality to admin to send messages to all users
 - overwrite cookie settings with user DB settings
 - implement a promoted event flag and a widget for the CMS
 - rework a system of enums in drop down menus like EventTypes (separate filter from data)
 - add a block-list to messages for certain users
 - send messages when to promote and notify of a new event
 - add changed details for cms and event and other actions for system view only
 - admin Log display: checkbox level and type, 3 stage subselect: year, month, day
 - add community translation suggestions and manger approval
 - cross-table reference for images and user gallery
 - play with qodana inspections

### Helpful copy & pasta stuff
```
// misguided web attempts
SELECT url, COUNT(*) AS number FROM `not_found_log` GROUP BY url ORDER BY number DESC;
COMPOSE_ENV_FILES=../.env # env parameter for phpstorm docker remote php interpreter
```