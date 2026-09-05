# TLS Certificates for Local Development

The dev stack serves `meetagain.local` and every `*.meetagain.local` subdomain over HTTPS. The certificate is issued
locally by [mkcert](https://github.com/FiloSottile/mkcert) and signed by a root CA that `just devCerts` registers in the
system, Chrome and Firefox trust stores - so no browser shows a warning interstitial.

**Nothing in this directory is committed.** The certificate and key are per-machine and gitignored. A fresh clone has
no certificate until you run the setup below.

## Setup

Install mkcert (Arch: `pacman -S mkcert`, Debian/Ubuntu: `apt install mkcert`, macOS: `brew install mkcert`), then:

```bash
just devCerts
```

That runs `mkcert -install` (creates the root CA and adds it to the trust stores), issues the wildcard certificate into
this directory, trusts the CA in every Firefox profile, and restarts the containers so Caddy picks it up. It prompts for
your password once, because writing to the system trust store needs root.

Restart the browser afterwards - Chrome and Firefox both read their trust store at startup, so a running instance keeps
rejecting the certificate. Chrome caches the verdict per host, so a host you visited before the CA existed keeps failing
until the process fully exits.

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

Firefox ignores both the system trust store and Chrome's `~/.pki/nssdb`; each profile carries its own `cert9.db`. Worse,
`mkcert -install` only scans `~/.mozilla/firefox/`, so it silently skips profiles under the XDG path
`~/.config/mozilla/firefox/` - which is where Firefox Developer Edition and newer Firefox builds put them. It reports
success either way.

`just devCerts` covers both locations. To re-run only that part after adding a profile:

```bash
just devCertsFirefox
```

It needs `certutil` from the `nss` package, and Firefox must be restarted afterwards.

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
mkcert -uninstall   # drops it from the system and Chrome stores, keeps the files in $(mkcert -CAROOT)
```

`mkcert -uninstall` misses the Firefox profiles for the same reason `-install` does. Remove it from each by hand:

```bash
certutil -d sql:<profile-dir> -D -n "mkcert development CA"
```
