# Claude Code Skills - Quick Reference

**All skills automatically use the Haiku model for token efficiency.**

---

## Testing

```bash
/test-unit                      # All unit tests
/test-unit EventServiceTest     # Specific test class
/test-unit Service/             # Tests in directory

/test-functional                # All functional tests
/test-functional LoginTest      # Specific functional test

/test-all                       # Complete suite (unit + functional + coverage)
```

---

## Code Quality

```bash
/check-quality    # Auto-format code + run quality checks
/quality-fix      # Complete pre-commit workflow
                  # (format → unit tests → quality checks)
```

---

## Database & Environment

```bash
/reset-dev                      # Reset dev with fixtures
/reset-dev <plugin-name>        # Reset with specific plugin enabled

/cache-clear                    # Clear dev cache
/cache-clear test               # Clear test cache
/cache-clear prod               # Clear prod cache

/db-query "SELECT * FROM users LIMIT 5"
/db-query "SELECT COUNT(*) FROM events"
```

---

## Sentry

```bash
/sentry-errors                  # List 10 most recent unresolved issues
/sentry-errors 12345            # Full details + stack trace for issue ID
```

---

## Plugin Management

```bash
/plugin-enable <plugin-name>    # Enable plugin + run migrations
/plugin-disable <plugin-name>   # Disable plugin (data remains in DB)
```

**Note:** Check `plugins/` directory for available plugins. Each plugin has its own `CLAUDE.md` with specific documentation.

---

## Skill Benefits

✅ **Token Efficient** - Automatic Haiku model selection
✅ **Consistent** - Standardized workflows
✅ **Fast** - One command vs multiple steps
✅ **Discoverable** - Self-documenting via examples

---

## File Locations

Skills are defined in SKILL.md files:

```
.claude/skills/
├── test-unit/SKILL.md
├── test-functional/SKILL.md
├── test-all/SKILL.md
├── check-quality/SKILL.md
├── quality-fix/SKILL.md
├── reset-dev/SKILL.md
├── cache-clear/SKILL.md
├── db-query/SKILL.md
├── plugin-enable/SKILL.md
└── plugin-disable/SKILL.md
```

---

## Creating Custom Skills

1. Create directory in `.claude/skills/<skill-name>/`
2. Create `SKILL.md` file with frontmatter and instructions
3. Use `disable-model-invocation: true` for user-only invocation
4. Use `$ARGUMENTS` placeholder for arguments

**Template:**
```markdown
---
name: my-skill
description: When to use this skill (triggers automatic invocation)
disable-model-invocation: true
---

# My Skill Title

Description of what this skill does.

## Arguments

- **arg1** (optional): What this argument does

## Workflow

Run the following commands using a Haiku agent:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  description: "Short description",
  prompt: |
    Instructions for the Haiku agent
    1. Run: just <command> $ARGUMENTS
    2. Return formatted results
)
```

## Examples

- `/my-skill` - Example usage
- `/my-skill arg` - Example with argument
```

---

## Important Notes

### Git Workflow

**Skills DO NOT create git commits.** Only the user commits changes.

After running skills that make changes (like `/quality-fix`), the output may say "READY FOR COMMIT" - this is just a status indicator, not a suggestion that Claude will commit.

**Correct workflow:**
1. Run skill (e.g., `/quality-fix`)
2. Review the changes
3. User commits manually: `git add . && git commit -m "message"`
4. Continue work

---

## Need Help?

- **Full Documentation**: `.claude/CLAUDE.md`
- **Architecture**: `.claude/architecture.md`
- **Testing Patterns**: `.claude/testing.md`
- **Skills Implementation Plan**: `.claude/plans/SKILLS-IMPLEMENTATION-PLAN.md`
