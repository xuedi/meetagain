# Hosting

Running MeetAgain on your own server.

For the local development environment, see [Getting Started](getting-started.md).

---

## One image, several roles

MeetAgain runs as containers. The public image `ghcr.io/xuedi/meetagain` holds the core and every bundled
plugin. Every tag is a core commit (`ghcr.io/xuedi/meetagain:<commit>`), and `latest` follows the newest one.

The first argument of the container picks what it does:

| Role             | What it runs                                                                         |
|------------------|--------------------------------------------------------------------------------------|
| `web` (default)  | The web server, plain HTTP on port 8080                                              |
| `worker`         | The message worker: mail, push and everything a request hands off. Run one or more  |
| `scheduler`      | The periodic tasks. Run one; a second waits as a standby                            |
| `all`            | `web`, `worker` and `scheduler` in one container                                     |
| `migrate`        | Database migrations, the rows every install needs, one-off data fixes. Run it first |
| `pending-sql`    | Prints the SQL the next `migrate` would run, without running it                      |
| `after-rollback` | Clears the caches after going back to an older image                                 |

Any other command runs as given, for example `php bin/console app:plugin:list`. Every role writes its warnings
and errors to stderr, so `docker compose logs` shows them.

Beside the image you need MariaDB and Valkey (or Redis), and a reverse proxy that terminates TLS and forwards
to port 8080 with an `X-Forwarded-Proto` header.

---

## Docker Compose

A `compose.yaml` for a small install, everything in one `app` container:

```yaml
services:
  app:
    image: ghcr.io/xuedi/meetagain:latest
    command: all
    restart: unless-stopped
    env_file: meetagain.env
    ports:
      - "127.0.0.1:8080:8080"
    volumes:
      - data:/var/www/html/data
      - thumbnails:/var/www/html/public/images/thumbnails
    depends_on:
      - mariadb
      - valkey

  mariadb:
    image: mariadb:13
    restart: unless-stopped
    environment:
      MARIADB_DATABASE: meetagain
      MARIADB_USER: meetagain
      MARIADB_PASSWORD: change-me
      MARIADB_RANDOM_ROOT_PASSWORD: "1"
    volumes:
      - db:/var/lib/mysql

  valkey:
    image: valkey/valkey:9
    restart: unless-stopped
    volumes:
      - valkey:/data

volumes:
  data:
  thumbnails:
  db:
  valkey:
```

And `meetagain.env` beside it:

```bash
APP_SECRET=<random 32-character string>
APP_SECRET_BOX_KEY=<base64-encoded 32-byte key>
DATABASE_URL=mysql://meetagain:change-me@mariadb:3306/meetagain?serverVersion=13.0.2-MariaDB&charset=utf8mb4
REDIS_URL=redis://valkey:6379
MAILER_DSN=smtp://user:password@smtp.example.com:587
```

Generate both secrets with `openssl rand -hex 16` and `openssl rand -base64 32`. Never keep the values from
the repository's `.env.dist`, which is public. Keep `APP_SECRET_BOX_KEY` stable afterwards: it encrypts stored
secrets such as plugin API keys, and they cannot be read with another key.

Optionally, set `METRICS_DSN` to send runtime metrics to a VictoriaMetrics collector. See
[Metrics](core-development/metrics.md).

---

## First-time setup

```bash
docker compose up -d mariadb valkey
docker compose run --rm app migrate
docker compose run --rm app php bin/console app:bootstrap --email=you@example.org --site-url=https://events.example.org
docker compose up -d
```

`app:bootstrap` seeds the empty database, creates your admin account and a starter home page, and prints a
link. Open it within 24 hours to set the admin's password, then replace the starter page under Admin, CMS. The
command refuses to run on a database that already has an admin.

Every plugin starts switched off. Switch the ones you want on at `/admin/plugin`.

---

## Updating

```bash
docker compose pull
docker compose run --rm app migrate
docker compose up -d
```

Migrations always keep the previous version running, so the old containers can serve while `migrate` runs.
To go back, pin the previous tag in `compose.yaml`, run `docker compose up -d`, then
`docker compose run --rm app after-rollback`.

---

## Splitting the roles

On a busier install, run the roles as separate services from the same image. They share the environment and
the volumes:

```yaml
x-app: &app
  image: ghcr.io/xuedi/meetagain:latest
  restart: unless-stopped
  env_file: meetagain.env
  volumes:
    - data:/var/www/html/data
    - thumbnails:/var/www/html/public/images/thumbnails
  depends_on:
    - mariadb
    - valkey

services:
  web:
    <<: *app
    command: web
    ports:
      - "127.0.0.1:8080:8080"
  worker:
    <<: *app
    command: worker
    deploy:
      replicas: 2
  scheduler:
    <<: *app
    command: scheduler
```

`mariadb`, `valkey` and the `volumes` stay as above. Workers stop after an hour, or when a plugin is switched
on or off, and the `restart` policy starts them again with a fresh state.

---

## Object storage

By default, uploads live on the `data` and `thumbnails` volumes. To keep them in an S3-compatible store
instead, set `STORAGE_DSN`:

```bash
STORAGE_DSN=s3://<key>:<secret>@<host>:<port>/<prefix>?region=<region>&tls=true&web=<host>:<port>
```

The path is a bucket prefix: MeetAgain uses the buckets `<prefix>-images`, `<prefix>-thumbnails` and
`<prefix>-private`. Thumbnails are served straight from the store, so `<prefix>-thumbnails` needs public
read access, and the optional `web` parameter names the address of the store's website endpoint the
container fetches them from. The volumes are then no longer needed.

To move existing files into the store, start the app with `STORAGE_DSN` set and run
`docker compose run --rm app php bin/console app:storage:copy`. Add `--reverse` to copy back to the local disk.

[Garage](https://garagehq.deuxfleurs.fr) is a small self-hosted store that fits beside MeetAgain. As an
optional Compose profile:

```yaml
services:
  garage:
    image: dxflrs/garage:v2.1.0
    profiles: [garage]
    restart: unless-stopped
    volumes:
      - ./garage.toml:/etc/garage.toml:ro
      - garage:/var/lib/garage
```

`docker compose --profile garage up -d` starts it. Then, with `garage` standing for
`docker compose exec garage /garage`, create the layout, a key and the buckets:

```bash
garage layout assign -z dc1 -c 10G "$(garage node id -q | cut -d@ -f1)"
garage layout apply --version 1
garage key create meetagain
for bucket in images thumbnails private; do
    garage bucket create "meetagain-$bucket"
    garage bucket allow --read --write --owner "meetagain-$bucket" --key meetagain
done
garage bucket website --allow meetagain-thumbnails
```

With Garage's S3 API on port 3900 and its website endpoint on 3902, the value is
`s3://<key id>:<secret>@garage:3900/meetagain?region=garage&tls=false&web=garage:3902`. Garage's own
documentation covers `garage.toml`.

---

## Static media

All assets (CSS, fonts, images) are compiled into the image under `public/media/` and served by the `web`
role with a one-day cache header. A CDN or proxy cache in front may cache `/media/*` freely: every file name
carries a content hash.
