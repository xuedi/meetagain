#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "$0")/../../api.local"

METHOD="${1:-GET}"
API_PATH="${2:-/api/status}"
BODY="${3:-}"

# Obtain a fresh Bearer token (use python to build JSON safely — password may contain shell-special chars)
AUTH_JSON=$(python3 -c "import json,sys; print(json.dumps({'email':sys.argv[1],'password':sys.argv[2]}))" "$API_EMAIL" "$API_PASSWORD")
TOKEN=$(curl -s -X POST "$API_URL/api/auth/token" \
  -H 'Content-Type: application/json' \
  -d "$AUTH_JSON" \
  | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('token','') if 'token' in d else d.get('error','auth failed'))")

if [ -z "$TOKEN" ] || [[ "$TOKEN" == *"error"* ]] || [[ "$TOKEN" == "auth failed" ]]; then
  echo "ERROR: Could not authenticate — $TOKEN"
  exit 1
fi

# Build curl args
CURL_ARGS=(-s -X "$METHOD" "$API_URL$API_PATH" -H "Authorization: Bearer $TOKEN")

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
