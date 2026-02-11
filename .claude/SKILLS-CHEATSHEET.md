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

Skills are defined in YAML files:

```
.claude/skills/
  testing/          # test-unit, test-functional, test-all
  quality/          # check-quality, quality-fix
  database/         # reset-dev, cache-clear, db-query
  plugins/          # plugin-enable, plugin-disable
```

---

## Creating Custom Skills

1. Create YAML file in `.claude/skills/<category>/<name>.yaml`
2. Use `model: haiku` for token efficiency
3. Define arguments with required/optional flags
4. Provide clear examples

**Template:**
```yaml
name: my-skill
description: What this skill does
model: haiku
category: custom

arguments:
  - name: arg_name
    description: What this argument does
    required: false
    default: ""

steps:
  - task:
      subagent_type: Bash
      model: haiku
      description: Short description
      prompt: |
        Instructions for the Haiku agent
        1. Run: just <command>
        2. Return formatted results

examples:
  - description: Example usage
    command: /my-skill
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
