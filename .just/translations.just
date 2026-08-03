# Translation extraction and catalogue completeness.

# Extract translation keys from templates into YAML files (run after adding new trans keys)
[group('translations')]
translationExtract:
    @{{PHP}} php bin/console translation:extract --force de --format yaml
    @{{PHP}} php bin/console translation:extract --force en --format yaml
    @{{PHP}} php bin/console translation:extract --force zh --format yaml

# Check that no template references a translation key missing from en/de/zh/fr/es catalogues
[group('translations')]
checkTranslations:
    @echo "Checking translations for missing keys in en, de, zh, fr, es..."
    @for locale in en de zh fr es; do \
        echo ""; \
        echo "=== $locale ==="; \
        {{PHP}} php bin/console debug:translation --only-missing $locale 2>&1 | tail -n +1; \
    done
