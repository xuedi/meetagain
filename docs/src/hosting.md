# Hosting

Deploying MeetAgain to a production server.

For the local development environment, see [Getting Started](getting-started.md).

---

## Requirements

- **PHP 8.4** with extensions: `gd`, `pdo_mysql`, `intl`, `redis`, `imagick`, `opcache`, `zip`
- **MariaDB 10.6+**
- **Redis or Valkey** (session and application cache)
- Writable `var/` and `data/` directories (owned by the PHP process user)

---

## Recommended: FrankenPHP

[FrankenPHP](https://frankenphp.dev) is a single binary that embeds PHP with built-in HTTPS,
HTTP/2, and HTTP/3. No separate web server needed.

Minimal `Caddyfile`:

```
your-domain.com {
    root * /var/www/meetagain/public
    encode zstd br gzip
    @phpRoute { not file {path} }
    rewrite @phpRoute index.php
    @frontController path index.php
    php @frontController
    file_server { hide *.php }
}
```

---

## Alternative: Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/meetagain/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ \.php$ {
        return 404;
    }
}
```

---

## Environment

Copy `.env.dist` to `.env` and set the required values:

```bash
APP_ENV=prod
APP_SECRET=<random 32-character string>
DATABASE_URL=mysql://user:password@127.0.0.1:3306/meetagain
MAILER_DSN=smtp://user:password@smtp.example.com:587
REDIS_URL=redis://127.0.0.1:6379
```

---

## First-time setup

After configuring the environment, navigate to `/install/` in your browser to run the
web installer. The wizard creates your admin account and initialises the database.

---

## Deploying updates

```bash
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate -n
php bin/console cache:clear --env=prod
```

---

## Cron job

The `app:cron` command must run every minute for scheduled tasks (email sends, recurring
event generation, etc.).

**crontab (as web server user):**

```
* * * * * php /var/www/meetagain/bin/console app:cron >> /dev/null 2>&1
```

**Systemd timer alternative:**

```ini
# /etc/systemd/system/meetagain-cron.service
[Service]
Type = oneshot
User = www-data
ExecStart = /usr/bin/php /var/www/meetagain/bin/console app:cron
```

```ini
# /etc/systemd/system/meetagain-cron.timer
[Timer]
OnCalendar = *:*:00
[Install]
WantedBy = timers.target
```

---

## File permissions

The PHP process user must have write access to:

- `var/` — Symfony cache and logs
- `data/images/` — user-uploaded images
