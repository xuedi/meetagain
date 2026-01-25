#!/bin/bash
# Generate self-signed wildcard certificate for *.meetagain.local

set -e

CERT_DIR="$(dirname "$0")"
DOMAIN="meetagain.local"
DAYS=3650  # 10 years

echo "🔐 Generating self-signed wildcard certificate for *.${DOMAIN}..."

# Create OpenSSL config for SAN (Subject Alternative Names)
cat > "${CERT_DIR}/openssl.cnf" << EOF
[req]
default_bits = 2048
prompt = no
default_md = sha256
distinguished_name = dn
req_extensions = req_ext

[dn]
C=DE
ST=Berlin
L=Berlin
O=MeetAgain Local Development
CN=${DOMAIN}

[req_ext]
subjectAltName = @alt_names

[alt_names]
DNS.1 = ${DOMAIN}
DNS.2 = *.${DOMAIN}
EOF

# Generate private key
openssl genrsa -out "${CERT_DIR}/meetagain.local.key" 2048

# Generate certificate signing request
openssl req -new \
    -key "${CERT_DIR}/meetagain.local.key" \
    -out "${CERT_DIR}/meetagain.local.csr" \
    -config "${CERT_DIR}/openssl.cnf"

# Generate self-signed certificate
openssl x509 -req \
    -days ${DAYS} \
    -in "${CERT_DIR}/meetagain.local.csr" \
    -signkey "${CERT_DIR}/meetagain.local.key" \
    -out "${CERT_DIR}/meetagain.local.crt" \
    -extensions req_ext \
    -extfile "${CERT_DIR}/openssl.cnf"

# Create PEM file (certificate + key combined)
cat "${CERT_DIR}/meetagain.local.crt" "${CERT_DIR}/meetagain.local.key" > "${CERT_DIR}/meetagain.local.pem"

# Set permissions
chmod 644 "${CERT_DIR}/meetagain.local.crt"
chmod 600 "${CERT_DIR}/meetagain.local.key"
chmod 600 "${CERT_DIR}/meetagain.local.pem"

echo "✅ Certificate generated successfully!"
echo ""
echo "📁 Files created:"
echo "   ${CERT_DIR}/meetagain.local.crt  (Certificate)"
echo "   ${CERT_DIR}/meetagain.local.key  (Private Key)"
echo "   ${CERT_DIR}/meetagain.local.pem  (Combined)"
echo ""
echo "🔧 To trust this certificate in Firefox:"
echo "   Settings → Privacy & Security → Certificates → View Certificates"
echo "   → Authorities → Import → Select meetagain.local.crt"
echo "   → Check 'Trust this CA to identify websites'"
echo ""
echo "🔧 Or add to system (Linux):"
echo "   sudo cp ${CERT_DIR}/meetagain.local.crt /usr/local/share/ca-certificates/"
echo "   sudo update-ca-certificates"
echo ""
echo "🔧 macOS:"
echo "   sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ${CERT_DIR}/meetagain.local.crt"
echo ""

# Show certificate details
echo "📋 Certificate details:"
openssl x509 -in "${CERT_DIR}/meetagain.local.crt" -noout -text | grep -A2 "Subject Alternative Name"

# Cleanup
rm "${CERT_DIR}/meetagain.local.csr"
rm "${CERT_DIR}/openssl.cnf"

echo "✅ Done!"
