---
name: fill-translations
description: Automatically fill missing translations by generating SQL statements. Uses auto-translation by default.
disable-model-invocation: true
---

# Fill Missing Translations

Automatically identifies and fills missing translations by generating SQL statements in `translationUpdates.sql`.

## Usage

```
/fill-translations
```

This will automatically use `--auto-translate` to generate translations for common terms.

## Workflow

This skill should be used as part of the translation sync workflow:

1. **Run this skill** - Automatically syncs from production first, then generates SQL for missing translations
2. **User reviews SQL** - Check `translationUpdates.sql` for generated translations
3. **User uploads to production** - Integrate SQL statements into production database
4. **Verify** - Run the skill again to confirm all translations are filled

## How It Works

Run the following using a Haiku agent:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  description: "Fill missing translations",
  prompt: |
    Fill missing translations automatically:

    1. Run: just translationFill
       (This command automatically syncs from production first)

    2. The command will:
       - Sync latest translations from production (devModeFixtures multisite)
       - Check for missing translations
       - Auto-generate SQL for all missing translations
       - Append to translationUpdates.sql
       - Show summary and next steps

    3. Return the command output showing what was added

    4. Remind user to:
       - Review translationUpdates.sql
       - Upload to production
       - Run: just translationFill again to verify
)
```

## Example

```
User: /fill-translations

Claude: [Runs just translationFill command]

Output:
📥 Syncing latest translations from production...
[devModeFixtures output...]

🔍 Checking for missing translations...
[...]

🤖 Generating SQL statements with auto-translation...
✓ Added 5 translation keys to translationUpdates.sql

✅ Done! Review translationUpdates.sql and upload to production.
   Then run: just translationFill again to verify
```

## Notes

- Auto-translation is enabled by default
- Uses smart heuristics to fill common terms automatically
- Partial translations (some languages filled) are handled intelligently
- Keys without any translations may be skipped (reported in output)
- Always review generated SQL before uploading to production
- SQL uses ON DUPLICATE KEY UPDATE (safe to run multiple times)
