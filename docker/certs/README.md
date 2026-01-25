# SSL Certificates for Local Development

This directory contains self-signed SSL certificates for `*.meetagain.local` domains.

## Files

- `meetagain.local.crt` - Certificate (public)
- `meetagain.local.key` - Private key
- `meetagain.local.pem` - Combined certificate + key
- `generate-cert.sh` - Script to regenerate certificates

## Certificate Coverage

The wildcard certificate covers:
- `meetagain.local`
- `*.meetagain.local` (all subdomains)

## Trust the Certificate in Firefox

Firefox uses its own certificate store and doesn't trust system certificates by default.

### Method 1: Import into Firefox (Recommended)

1. Open Firefox
2. Go to `Settings` → `Privacy & Security`
3. Scroll down to `Certificates` → Click `View Certificates`
4. Click the `Authorities` tab
5. Click `Import...`
6. Navigate to `/home/xuedi/Projects/meetAgain/docker/certs/`
7. Select `meetagain.local.crt`
8. Check: ✅ `Trust this CA to identify websites`
9. Click `OK`

Now all `*.meetagain.local` domains will work without warnings!

### Method 2: System-wide Trust (Linux)

```bash
# Copy certificate to system store
sudo cp /home/xuedi/Projects/meetAgain/docker/certs/meetagain.local.crt /usr/local/share/ca-certificates/

# Update certificate store
sudo update-ca-certificates
```

Note: This works for most browsers except Firefox (which needs Method 1).

### Method 3: System-wide Trust (macOS)

```bash
sudo security add-trusted-cert -d -r trustRoot \
    -k /Library/Keychains/System.keychain \
    /home/xuedi/Projects/meetAgain/docker/certs/meetagain.local.crt
```

## Regenerating Certificates

If certificates expire or you need new ones:

```bash
bash docker/certs/generate-cert.sh
just stop
just start
```

The certificates are valid for 10 years by default.

## Verify Certificate

```bash
# Check certificate details
openssl x509 -in docker/certs/meetagain.local.crt -noout -text

# Test HTTPS connection
curl -v https://meetagain.local 2>&1 | grep "subject"
```

## URLs Using HTTPS

- `https://meetagain.local` - Main portal
- `https://tech.meetagain.local/manage` - Tech group
- `https://books.meetagain.local/manage` - Books group
- `https://photo.meetagain.local/manage` - Photo group
- `https://birds.meetagain.local/manage` - Birds group
- `https://weiqi.meetagain.local/manage` - Weiqi group
- `http://localhost` - Still uses HTTP (no certificate needed)
