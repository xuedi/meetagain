---
name: fill-translations
description: Find and fill missing translation keys across all translation files.
disable-model-invocation: true
---

# Fill Missing Translations

Compares translation keys across all translation files and fills in any missing entries.

## Usage

```
/fill-translations
```

## Translation File Locations

**Core YAML files** (Symfony translation format, always present):
- `translations/messages.en.yaml`
- `translations/messages.de.yaml`
- `translations/messages.cn.yaml`

**Plugin translation files** (PHP arrays, only in plugins that have translations):
- `plugins/*/translations/messages.en.php`
- `plugins/*/translations/messages.de.php`
- `plugins/*/translations/messages.cn.php`

First use Glob to find which plugins actually have translation files (skip `.gitkeep`-only directories).

## Workflow

1. **Run** `just translationExtract` (user runs this first to detect new keys from templates/code)

2. **Discover all translation files:**
   - Read the 3 core YAML files
   - Glob `plugins/*/translations/messages.*.php` to find plugin files with actual content

3. **Compare keys** for each file set:
   - All three languages (en/de/cn) must have identical keys within each set
   - Plugin PHP files must contain all keys from the core YAML files (plugins carry a full copy)
   - Core YAML files must contain any plugin-specific keys that are missing

4. **Add missing keys** using the Edit tool:
   - Core YAML: append to the end of the file
   - Plugin PHP files: append before the closing `);`
   - Take translations from the corresponding language in the reference file
   - For brand-new keys with no translation yet: use a sensible translation based on context

5. **Report** what was added and in which files

## Key Rules

- All three languages (en, de, cn) must have the same set of keys in every file set
- Plugin PHP files are a full copy of all translations — they must stay in sync with core YAML
- YAML format: `key: 'value'` — quote values containing special characters
- PHP format: `'key' => 'value',` — standard PHP array entry before closing `);`
- Keys with spaces or special chars need quoting in YAML: `'Has Tile': 'Has tile'`

## Example Output

```
Found 5 missing keys:

Core YAML (all 3 languages):
  + menu_admin_translation_edit (EN: Edit / DE: Bearbeiten / CN: 编辑)

All files updated.
```
