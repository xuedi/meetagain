#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "$0")/../../sentry.local"

ISSUE_ID="${1:-}"

if [ -z "$ISSUE_ID" ]; then
  curl -s -H "Authorization: Bearer $SENTRY_TOKEN" \
    "$SENTRY_URL/api/0/projects/$SENTRY_ORG/$SENTRY_PROJECT/issues/?query=is:unresolved&sort=date&limit=10" \
  | python3 -c "
import sys, json
issues = json.load(sys.stdin)
if not issues:
    print('No unresolved issues.')
for i in issues:
    print(f\"#{i['shortId']}  {i['title']}  [{i.get('culprit','—')}]  — {i['count']} events, last seen {i['lastSeen'][:10]}\")
"
else
  curl -s -H "Authorization: Bearer $SENTRY_TOKEN" \
    "$SENTRY_URL/api/0/issues/$ISSUE_ID/" \
  | python3 -c "
import sys, json
i = json.load(sys.stdin)
print(f\"Issue:   {i['title']}\")
print(f\"ID:      {i['id']}  Short: {i['shortId']}\")
print(f\"Status:  {i['status']}  Level: {i['level']}\")
print(f\"Culprit: {i.get('culprit','—')}\")
print(f\"Events:  {i['count']}  Users: {i['userCount']}\")
print(f\"First:   {i['firstSeen'][:19]}\")
print(f\"Last:    {i['lastSeen'][:19]}\")
"

  curl -s -H "Authorization: Bearer $SENTRY_TOKEN" \
    "$SENTRY_URL/api/0/issues/$ISSUE_ID/events/latest/" \
  | python3 -c "
import sys, json
e = json.load(sys.stdin)
print(f\"\nEnvironment: {e.get('environment','—')}\")
print(f\"Platform:    {e.get('platform','—')}\")
for entry in e.get('entries', []):
    if entry.get('type') == 'exception':
        for exc in entry['data'].get('values', []):
            print(f\"\nException: {exc.get('type')}: {exc.get('value')}\")
            frames = exc.get('stacktrace', {}).get('frames', [])[-10:]
            print('Stack trace (last 10 frames):')
            for f in frames:
                loc = f.get('absPath') or f.get('filename','?')
                print(f\"  {loc}:{f.get('lineNo','?')} in {f.get('function','?')}\")
"
fi
