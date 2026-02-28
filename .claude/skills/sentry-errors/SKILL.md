---
name: sentry-errors
description: Fetch latest errors or issue details from Sentry. Use when the user asks to "check sentry", "show latest errors", "what errors are in sentry", or "show sentry issue <id>".
disable-model-invocation: true
---

# Sentry Errors

Fetch the latest unresolved issues from Sentry, or show full details for a specific issue ID.

## Arguments

- **issue_id** (optional): A Sentry issue ID to show full details and stack trace. If omitted, lists the 10 most recent unresolved issues.

## Workflow

Use the Bash tool to run a single-line command. Do NOT use an agent.

**List mode** (no arguments):
```
bash .claude/skills/sentry-errors/run.sh
```

**Detail mode** (with issue ID):
```
bash .claude/skills/sentry-errors/run.sh $ARGUMENTS
```

## Examples

- `/sentry-errors` — List 10 most recent unresolved issues
- `/sentry-errors 12345` — Show full details and stack trace for issue 12345
