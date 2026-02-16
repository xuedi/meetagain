# Translation Workflow - Improved & Automated

## Overview

This document describes the improved translation synchronization workflow between local development and production environments. The workflow has been optimized to reduce manual work and automate common translation tasks.

---

## 🎯 Quick Start

### Option 1: Fully Automated (Recommended)

```bash
# Run the interactive sync script
just translationSync

# With auto-translation for common terms
just translationSync --auto-translate

# Preview without making changes
just translationSync --dry-run --auto-translate
```

### Option 2: Manual Step-by-Step

```bash
# 1. Check missing translations
just translationMissing

# 2. Generate SQL for missing translations
just translationFill --auto-translate

# 3. Review the generated SQL
tail -50 translationUpdates.sql

# 4. Upload SQL to production
# (manual step - execute SQL in production database)

# 5. Sync dev environment with production
just devModeFixtures multisite

# 6. Verify all translations filled
just translationMissing
```

---

## 📋 Available Commands

### Just Commands

| Command | Description |
|---------|-------------|
| `just translationMissing` | List all missing translations in JSON format |
| `just translationFill [args]` | Fill missing translations (generates SQL) |
| `just translationSync [args]` | Interactive workflow script |

### Symfony Console Commands

| Command | Description |
|---------|-------------|
| `app:translation:missing` | List missing translations (JSON output) |
| `app:translation:fill-missing` | Generate SQL for missing translations |

### Shell Script

| Command | Description |
|---------|-------------|
| `./bin/sync-translations.sh` | Interactive translation sync workflow |

---

## 🔧 Command Options

### `just translationFill` / `app:translation:fill-missing`

- `--dry-run` - Preview what would be added without writing to file
- `--auto-translate` - Automatically generate translations for common terms

**Examples:**
```bash
just translationFill --dry-run
just translationFill --auto-translate
just translationFill --dry-run --auto-translate
```

### `just translationSync` / `./bin/sync-translations.sh`

- `--dry-run` - Preview mode
- `--auto-translate` - Enable automatic translation
- `--skip-reset` - Skip the devModeFixtures reset step
- `--help` - Show help message

---

## 🔄 Complete Workflow

### The Full Cycle

```
┌─────────────────────────────────────────────────────────┐
│  1. Develop locally with missing translations          │
│     → Templates reference translation keys              │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│  2. Generate SQL for missing translations               │
│     → just translationFill --auto-translate             │
│     → Creates SQL in translationUpdates.sql             │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│  3. Upload SQL to production                            │
│     → Execute SQL statements in production database     │
│     → Translations now available in production          │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│  4. Sync dev environment with production                │
│     → just devModeFixtures multisite                    │
│     → Downloads latest translations from production     │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│  5. Verify completion                                   │
│     → just translationMissing                           │
│     → Should return empty array []                      │
└─────────────────────────────────────────────────────────┘
```

---

## 🤖 Auto-Translation Feature

The `--auto-translate` flag enables smart translation generation for common terms.

### How It Works

1. **Partial translations**: If a key has translations in some languages but not others, the system attempts to fill missing ones using a built-in dictionary of common terms.

2. **Common term mappings**: The system includes translations for frequently used terms:
   - Config/Configuration → Konfiguration/Einstellungen (DE) → 配置 (CN)
   - Images → Bilder (DE) → 图片 (CN)
   - Theme → Design (DE) → 主题 (CN)
   - And many more...

3. **Fallback behavior**: If a translation cannot be auto-generated, the key is skipped and reported for manual translation.

### When to Use Auto-Translate

✅ **Good for:**
- Common UI terms (buttons, labels, tabs)
- Standard admin interface elements
- System messages with existing partial translations

❌ **Not suitable for:**
- Domain-specific terminology
- Marketing copy or user-facing content
- Complex phrases requiring context

---

## 📝 File Locations

- **SQL Output**: `translationUpdates.sql` (project root)
- **Translation Files**: `translations/*.php` (gitignored, managed in production)
- **Sync Script**: `bin/sync-translations.sh`
- **Command**: `src/Command/TranslationFillMissingCommand.php`
- **Skill**: `.claude/skills/fill-translations/SKILL.md`

---

## 🎨 Skills Reference

### `/add-translation`

Add a single translation manually:

```
/add-translation key="my_key" de="German" en="English" cn="中文"
```

### `/fill-translations`

Fill all missing translations automatically:

```
/fill-translations
/fill-translations --dry-run
/fill-translations --auto-translate
```

---

## 💡 Tips & Best Practices

1. **Always review generated SQL** before uploading to production
2. **Use --dry-run first** to preview what will be generated
3. **Commit translationUpdates.sql** so you have a history of changes
4. **Clear the SQL file** after uploading to production to avoid duplicates
5. **Use --auto-translate** for admin interfaces, but review user-facing content manually

---

## 🔍 Troubleshooting

### "Some keys require manual translation"

**Cause**: The auto-translate feature couldn't generate translations for some keys.

**Solution**:
- Check the skipped keys in the output
- Add them manually using `/add-translation`
- Or add them to the auto-translation dictionary in `TranslationFillMissingCommand.php`

### "Translation already exists" errors in production

**Cause**: This should no longer happen! All SQL statements use `ON DUPLICATE KEY UPDATE`.

**Note**: The workflow now uses `INSERT ... ON DUPLICATE KEY UPDATE` which:
- Inserts new translations
- Updates existing translations
- Never causes duplicate key errors
- Safe to run multiple times

### Translations not showing up after sync

**Cause**: Cache not cleared or fixtures not reloaded.

**Solution**:
```bash
just app cache:clear
just devModeFixtures multisite
```

---

## 📊 Example Session

```bash
# Start with checking what's missing
$ just translationMissing
[
  {
    "placeholder": "new_feature_title",
    "en": "New Feature",
    "de": "",
    "cn": ""
  },
  ...
]

# Preview auto-translation
$ just translationFill --dry-run --auto-translate
Found 5 translation keys with missing values
Would add 5 translation keys
Total SQL statements: 15

# Generate SQL
$ just translationFill --auto-translate
✓ Added 5 translation keys to translationUpdates.sql

# Review and upload to production
$ tail -20 translationUpdates.sql
-- Translation key: new_feature_title
INSERT INTO translation (created_at, language, placeholder, translation, user_id)
VALUES (NOW(), 'de', 'new_feature_title', 'Neue Funktion', 1);
...

# [Upload to production]

# Sync dev environment
$ just devModeFixtures multisite

# Verify
$ just translationMissing
[]

✓ All translations complete!
```

---

## 🚀 Future Improvements

Potential enhancements for the translation workflow:

- [ ] AI-powered translation using Claude API for better quality
- [ ] Translation memory/TM integration
- [ ] Automatic detection of similar keys to suggest translations
- [ ] Web UI for translation management
- [ ] Integration with translation services (DeepL, Google Translate)
- [ ] Validation of translation completeness in CI/CD
- [ ] Export/import translations in standard formats (XLIFF, PO)

---

## 📚 Related Documentation

- [CLAUDE.md](../CLAUDE.md#translation-skills) - Translation skills overview
- [MEMORY.md](~/.claude/projects/-home-xuedi-Projects-meetAgain/memory/MEMORY.md) - Translation workflow patterns
- [Plugin Development Guide](../docs/plugin-development.md) - Plugin-specific translations

---

**Last Updated**: 2026-02-16
