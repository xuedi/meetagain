

[![Code Coverage](https://raw.githubusercontent.com/xuedi/meetAgain/refs/heads/master/tests/badge/coverage.svg)](https://github.com/xuedi/meetAgain/blob/master/tests/badgeGenerator.php)


## Introduction
meetup.com got crazily expensive, I created my own page as a single meetup
instance, with basic modular CMS to customize any number of pages. Menus and
such are static and have to be changed in code. | Minimalist PHP8.4+ Symfony


### Software design
A classic symfony application as upstream as possible no fancy libraries
or dependencies.


### PHP modules
 - PHP >= 8.4
 - ext-ctype
 - ext-iconv
 - ext-imagick


### Usage
```
just          # get a list with all possible cli interactions
just install  # deletes all data and reset to fresh fixture version
just up       # starts docker development stack
just down     # stops the stack
``` 


### TODO:
 - add messages and way to block users
 - send messages when to promote and notify of a new event
 - activity levels for user view and admin view
 - add changed details for cms and event and other actions for system view only
 - add comments
 - admin Log display: checkbox level and type, 3 stage subselect: year, month, day
 - add community translation suggestions and manger approval
 - cross-table reference for images and user gallery
 - play with qodana inspections

### Helpful copy & pasta stuff
```
// misguided web attempts
SELECT url, COUNT(*) AS number FROM `not_found_log` GROUP BY url ORDER BY number DESC;
```