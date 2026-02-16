#!/bin/bash

# Translation Synchronization Workflow Script
# ============================================
# This script automates the translation sync workflow between local dev and production.
#
# Usage:
#   ./bin/sync-translations.sh [--auto-translate] [--dry-run] [--skip-reset]
#
# Workflow:
#   1. Show current missing translations
#   2. Fill missing translations (generates SQL)
#   3. Display SQL file content for review
#   4. Instructions for uploading to production
#
# Options:
#   --auto-translate   Automatically generate basic translations for common terms
#   --dry-run          Preview what would be added without actually writing
#   --skip-reset       Skip the devModeFixtures reset step
#   --help             Show this help message

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Parse arguments
AUTO_TRANSLATE=""
DRY_RUN=""
SKIP_RESET=false

for arg in "$@"; do
    case $arg in
        --auto-translate)
            AUTO_TRANSLATE="--auto-translate"
            shift
            ;;
        --dry-run)
            DRY_RUN="--dry-run"
            shift
            ;;
        --skip-reset)
            SKIP_RESET=true
            shift
            ;;
        --help)
            head -n 20 "$0" | tail -n +2 | sed 's/^# //'
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $arg${NC}"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Translation Synchronization Workflow${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Step 1: Show current status
echo -e "${YELLOW}Step 1: Checking missing translations...${NC}"
MISSING_COUNT=$(just app app:translation:missing | jq '. | length')
echo -e "Found ${GREEN}${MISSING_COUNT}${NC} keys with missing translations"
echo ""

if [ "$MISSING_COUNT" -eq 0 ]; then
    echo -e "${GREEN}✓ No missing translations!${NC}"
    exit 0
fi

# Step 2: Ask if user wants to proceed
if [ -z "$DRY_RUN" ]; then
    echo -e "${YELLOW}This will generate SQL statements for missing translations.${NC}"
    read -p "Continue? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Aborted."
        exit 1
    fi
fi

# Step 3: Fill missing translations
echo -e "${YELLOW}Step 2: Generating SQL statements...${NC}"
just app "app:translation:fill-missing ${AUTO_TRANSLATE} ${DRY_RUN}"
echo ""

if [ -n "$DRY_RUN" ]; then
    echo -e "${BLUE}Dry-run complete. No files were modified.${NC}"
    echo -e "${YELLOW}Remove --dry-run to actually generate the SQL statements.${NC}"
    exit 0
fi

# Step 4: Show the SQL file
echo -e "${YELLOW}Step 3: Review generated SQL${NC}"
echo -e "${BLUE}Latest additions to translationUpdates.sql:${NC}"
echo ""
tail -n 50 translationUpdates.sql
echo ""

# Step 5: Next steps
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}SQL statements generated successfully!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo ""
echo -e "  1. ${BLUE}Review${NC} the generated SQL in translationUpdates.sql"
echo -e "  2. ${BLUE}Upload${NC} the SQL statements to production database"
echo -e "  3. ${BLUE}Reset${NC} dev environment: ${GREEN}just devModeFixtures multisite${NC}"
echo -e "  4. ${BLUE}Verify${NC} translations: ${GREEN}just app app:translation:missing${NC}"
echo ""
echo -e "${YELLOW}Or run this script again after uploading to production.${NC}"
echo ""

# Optional: Offer to open SQL file in editor
if command -v $EDITOR &> /dev/null; then
    read -p "Open translationUpdates.sql in editor? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        $EDITOR translationUpdates.sql
    fi
fi
