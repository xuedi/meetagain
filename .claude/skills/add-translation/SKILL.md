---
name: add-translation
description: Add translation SQL statements to translationUpdates.sql. Use when creating frontend pages that need translations.
disable-model-invocation: true
---

# Add Translation SQL Statements

Generate SQL INSERT statements for translations and append them to `translationUpdates.sql` in the project root.

## Arguments

- **key** (required): Translation key/placeholder (e.g., "event_list_title")
- **de** (required): German translation text
- **en** (required): English translation text
- **cn** (required): Chinese translation text

Format: `key="translation.key" de="German text" en="English text" cn="中文文本"`

## Workflow

Run the following using a Haiku agent:

```
Task(
  subagent_type: "Bash",
  model: "haiku",
  description: "Add translation SQL",
  prompt: |
    Add translation SQL statements to translationUpdates.sql:

    1. Parse arguments to extract key, de, en, cn values
    2. Generate SQL INSERT statements for all 3 languages:
       - Use user_id = 1
       - Use NOW() for created_at
       - Format: INSERT INTO translation (created_at, language, placeholder, translation, user_id) VALUES (NOW(), 'LANG', 'KEY', 'TEXT', 1) ON DUPLICATE KEY UPDATE translation = 'TEXT', created_at = NOW();
       - The ON DUPLICATE KEY UPDATE ensures safe re-execution (updates existing, inserts new)

    3. Append to translationUpdates.sql in project root:
       - Add a comment line: -- Translation key: KEY
       - Add all 3 INSERT statements (DE, EN, CN)
       - Add blank line for separation

    4. Return summary:
       ```
       Translation SQL Added
       =====================
       Key: <translation_key>

       Generated statements:
       - German (de): <de_text>
       - English (en): <en_text>
       - Chinese (cn): <cn_text>

       Appended to: translationUpdates.sql

       Status: SUCCESS
       ```

    Arguments: $ARGUMENTS
)
```

## Examples

- `/add-translation key="event_list_title" de="Veranstaltungsliste" en="Event List" cn="活动列表"`
- `/add-translation key="save_button" de="Speichern" en="Save" cn="保存"`
- `/add-translation key="error_invalid_email" de="Ungültige E-Mail-Adresse" en="Invalid email address" cn="无效的电子邮件地址"`

## Notes

- Translation files (`translations/*.php`) are gitignored and managed externally in production
- This skill appends SQL to `translationUpdates.sql` for manual integration by the developer
- The SQL uses user_id = 1 (system default) and NOW() for created_at timestamp
- Keys should follow existing naming patterns (snake_case)
