---
name: db-query
description: Execute SQL query on database. Use when the user asks to "run a database query", "check the database", or "execute SQL".
disable-model-invocation: true
---

# Execute Database Query

Execute SQL query on the database using the Haiku model for efficiency.

## Arguments

- **query** (required): SQL query to execute (e.g., "SELECT COUNT(*) FROM users")

## Workflow

Run the following commands using a Haiku agent:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  description: "Execute database query",
  prompt: |
    Execute SQL query on database:

    1. Run: just dockerDatabase "$ARGUMENTS"

    Return query results in readable format:
    ```
    Database Query
    ==============
    Query: $ARGUMENTS

    Results:
    [Query output]

    Status: SUCCESS/FAILED
    ```
)
```

## Examples

- `/db-query "SELECT COUNT(*) FROM users"` - Count users
- `/db-query "SELECT id, title, start FROM events ORDER BY start DESC LIMIT 5"` - List recent events
