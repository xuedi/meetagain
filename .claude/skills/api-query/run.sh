#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(dirname "$0")"

# Optional first argument: "local" uses api.local (dev), anything else uses api.prod (production)
INSECURE=""
if [ "${1:-}" = "local" ]; then
  source "$SCRIPT_DIR/../../api.local"
  INSECURE="-k"  # local dev uses self-signed TLS cert
  shift
  echo "» Using local dev: $API_URL" >&2
else
  source "$SCRIPT_DIR/../../api.prod"
fi

METHOD="${1:-GET}"
API_PATH="${2:-/api/status}"
BODY="${3:-}"

# Obtain a fresh Bearer token (use python to build JSON safely — password may contain shell-special chars)
AUTH_JSON=$(python3 -c "import json,sys; print(json.dumps({'email':sys.argv[1],'password':sys.argv[2]}))" "$API_EMAIL" "$API_PASSWORD")
TOKEN=$(curl -s $INSECURE -X POST "$API_URL/api/auth/token" \
  -H 'Content-Type: application/json' \
  -d "$AUTH_JSON" \
  | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('token','') if 'token' in d else d.get('error','auth failed'))")

if [ -z "$TOKEN" ] || [[ "$TOKEN" == *"error"* ]] || [[ "$TOKEN" == "auth failed" ]]; then
  echo "ERROR: Could not authenticate — $TOKEN"
  exit 1
fi

# Build curl args
CURL_ARGS=(-s $INSECURE -X "$METHOD" "$API_URL$API_PATH" -H "Authorization: Bearer $TOKEN")

if [ -n "$BODY" ]; then
  CURL_ARGS+=(-H 'Content-Type: application/json' -d "$BODY")
fi

# Execute and pretty-print
curl "${CURL_ARGS[@]}" | python3 -c "
import sys, json
raw = sys.stdin.read()
try:
    print(json.dumps(json.loads(raw), indent=2, ensure_ascii=False))
except Exception:
    print(raw)
"
