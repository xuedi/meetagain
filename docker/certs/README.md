# TLS Certificates for Local Development

The dev stack serves `meetagain.local` and every `*.meetagain.local` subdomain over HTTPS. The certificate is issued
locally by [mkcert](https://github.com/FiloSottile/mkcert), which also registers its own root CA in the browser and
system trust stores - so the browser shows no warning interstitial.

**Nothing in this directory is committed.** The certificate and key are per-machine and gitignored. A fresh clone has
no certificate until you run the setup below.

## Setup

Install mkcert (Arch: `pacman -S mkcert`, Debian/Ubuntu: `apt install mkcert`, macOS: `brew install mkcert`), then:

```bash
just devCerts
```

That runs `mkcert -install` (creates the root CA and adds it to the trust stores), issues the wildcard certificate into
this directory, and restarts the containers so Caddy picks it up. It prompts for your password once, because writing to
the system trust store needs root.

Restart the browser afterwards if pages still show a warning.

## What gets issued

| Name                | Covers                                                            |
|---------------------|-------------------------------------------------------------------|
| `meetagain.local`   | the platform host                                                 |
| `*.meetagain.local` | every group subdomain - one level only, not `a.b.meetagain.local` |

`docker/php/Caddyfile` reads `meetagain.local.crt` and `meetagain.local.key` from here. `http://localhost` stays on
plain HTTP and needs no certificate.

The root CA lives in `$(mkcert -CAROOT)` and is shared by every project on the machine, so a second clone needs no
second CA. The leaf certificate expires after roughly 27 months; re-run `just devCerts` to reissue it.

## Firefox

`mkcert -install` handles Firefox automatically when the profile exists at install time. A profile created later needs
a re-run of `just devCerts`.

## Verify

```bash
# certificate chain and names
openssl x509 -in docker/certs/meetagain.local.crt -noout -issuer -dates -ext subjectAltName

# end-to-end, using the system trust store
curl -sS -o /dev/null -w '%{http_code} verify=%{ssl_verify_result}\n' https://meetagain.local/
```

`verify=0` means the chain validated.

## Removing the CA

```bash
mkcert -uninstall   # drops it from the trust stores, keeps the files in $(mkcert -CAROOT)
```
