# Fixture System Repair Plan

**Date:** 2026-01-30
**Model:** opus
**Status:** Ready for Approval

---

## Quick Reference

**Total Phases:** 8 phases
**Fixtures to Repair:** 27 total (14 base + 13 plugin)
**Estimated Commits:** ~30 (one per fixture + group changes + smoke tests)

**Phase Breakdown:**
1. ✏️ Justfile & group standardization (1 commit)
2. 🧪 Smoke tests for all fixtures (1-2 commits)
3. 🔧 Install group fixtures: 4 fixtures × 1 commit each = 4 commits
4. 🔧 Base group fixtures: 9 fixtures × 1 commit each = 9 commits
5. ✅ Migration validation (0-1 commits)
6. 🔧 MultiSite plugin fixtures: 9 fixtures × 1 commit each = 9 commits
7. 🔧 Other plugin fixtures: 4 fixtures × 1 commit each = 4 commits
8. ✅ Final validation & cleanup (1 commit)

**Critical Decisions Made:**
- Load `--group=install` then `--group=base` in devModeFixtures
- Standardize all plugins to `group='plugin'`
- Repair order: base first (install + base), then plugins
- One fixture at a time with user verification

---

## Objective

Systematically repair the broken fixture system to ensure:
- `just devModeFixtures` (base fixtures) runs successfully
- `just devModeFixtures multisite` (plugin fixtures) runs successfully
- Migration scripts between fixture loads don't break data integrity
- All fixtures have correct syntax, dependencies, and reference handling

---

## Migration Script Analysis

### What Happens Between Base and Plugin Fixtures

The `multisite:migrate-to-multi-tenant` command (called from `Plugin\MultiSite\Kernel::preFixtures()`) creates:

**Groups created by migration**:
- "Main Site" (platform, domain = NULL)
- "Weiqi Club" (migrated data, domain = weiqi.meetagain.local)

**Database operations**:
1. Creates 2 groups (Main Site + Weiqi Club)
2. Adds ALL base users as members to BOTH groups (~150 users × 2)
3. Maps ALL base Events to "Weiqi Club"
4. Maps ALL base Locations to "Weiqi Club"
5. Maps ALL base CMS pages to "Weiqi Club"

**Groups created by GroupFixture**:
- "Berlin Tech Meetup"
- "Private Book Club"
- "Photography Enthusiasts"
- "Berlin Birdwatchers"

**IMPORTANT**: Migration groups ≠ Fixture groups. This is intentional:
- Migration groups = "existing single-tenant data migrated to multisite"
- Fixture groups = "new demo data for multisite features"

**GroupFixture handles this gracefully**: It checks if a group exists before creating, so no conflicts.

### Data Integrity Concern

The migration does NOT delete or modify base fixture data, it only:
- Creates new Group entities
- Creates mapping table entries (group_event_mapping, group_location_mapping, etc.)

**Verdict**: Migration should NOT break plugin fixtures.

---

## Current State Analysis

### Abstract Fixture Classes (✓ Sound)

**Inheritance Hierarchy:**
```
Doctrine\Common\DataFixtures\AbstractFixture
    ↓ extends
Doctrine\Bundle\FixturesBundle\Fixture
    ↓ extends
App\DataFixtures\AbstractFixture
    ↓ extends (for plugins)
Plugin\MultiSite\DataFixtures\AbstractPluginFixture
```

**Magic Method Implementation:**
- `AbstractFixture.__call()`: Handles `getRef*()` and `addRef*()` for `App\Entity\*` classes
- `AbstractPluginFixture.__call()`: **Correctly overrides** to check `Plugin\MultiSite\Entity\*` first, then falls back to `App\Entity\*`
- **Verdict:** The override is necessary and correct

**Reference Key Format:**
```php
"{EntityName}Fixture::{md5(name)}"
// Example: "UserFixture::098f6bcd4621d373cade4e832627b4f6"
```

### Fixture Inventory

#### Base Fixtures (group: `base` or `install`)

| Fixture | Group | Dependencies | References Provided | References Consumed |
|---------|-------|--------------|---------------------|---------------------|
| SystemUserFixture | `install` | - | User: `import`, `cron` | - |
| LanguageFixture | `install` | SystemUserFixture | - | User: `import` |
| ConfigFixture | `install` | SystemUserFixture | - | User: `import` |
| EmailTemplateFixture | `install` | LanguageFixture | - | - |
| UserFixture | `base` | - | User: 150+ users | User (self-ref for followers) |
| LocationFixture | `base` | UserFixture | Location: 6 locations | User: `import` |
| HostFixture | `base` | UserFixture | Host: 5 hosts | User: various |
| CmsFixture | `base` | UserFixture | Cms: 6 pages | User: `import` |
| CmsBlockFixture | `base` | CmsFixture, UserFixture | - | Cms: various, User: `import` |
| MenuFixture | `base` | CmsFixture | - | Cms: various |
| EventFixture | `base` | UserFixture, LocationFixture, HostFixture | Event: 5 events | User, Location, Host |
| ActivityFixture | `base` | UserFixture | - | User: various |
| MessageFixture | `base` | UserFixture | - | User: sender/receiver |

#### Plugin Fixtures (group: `plugin` or custom)

**MultiSite Plugin:**
| Fixture | Dependencies | References Provided | References Consumed |
|---------|--------------|---------------------|---------------------|
| GroupFixture | - | Group: 4 groups | - |
| GroupMemberFixture | GroupFixture | GroupMember: composite keys | Group, User |
| GroupInvitationFixture | GroupFixture | GroupInvitation: by token | Group, User |
| GroupCmsFixture | GroupFixture | Cms: 9 pages | User: `import`, Group |
| GroupCmsBlockFixture | GroupCmsFixture | - | User: `import`, Cms |
| GroupMenuFixture | GroupFixture, GroupCmsFixture | - | Group, Cms |
| GroupEventFixture | GroupFixture | - | User, Group, Location, Host |
| GroupCmsSettingsFixture | GroupFixture, GroupCmsFixture | - | Group, Cms |
| MessageFixture (plugin) | UserFixture | - | User: sender/receiver |

**Other Plugins:**
- DishFixture (group: `Dishes`, extends AbstractFixture)
- BookclubFixture (group: `Bookclub`, extends AbstractFixture)
- FilmFixture (group: `plugin`, extends AbstractFixture)
- GlossaryFixture (group: `Glossary`, extends Doctrine Fixture directly)

### Issues Identified

1. **Unknown syntax errors** in various fixture files ⚠️
2. **Install vs Base group mismatch** ⚠️ CRITICAL
   - `devModeFixtures` only loads `--group=base`
   - SystemUserFixture is in `--group=install` but many base fixtures reference it
   - Need to decide: Move SystemUserFixture to 'base' or load 'install' group first?
3. **Missing dependency declarations** ⚠️
   - BookclubFixture uses SystemUserFixture but declares `[]` dependencies
   - DishFixture uses SystemUserFixture but declares `[]` dependencies
   - FilmFixture uses UserFixture but declares `[]` dependencies
4. **Custom plugin groups NOT loaded** ⚠️ CRITICAL
   - DishFixture (group: 'Dishes'), BookclubFixture (group: 'Bookclub'), GlossaryFixture (group: 'Glossary')
   - `--group=plugin` won't load these fixtures
   - Need to decide: Change to 'plugin' or load each custom group separately?
5. **GlossaryFixture extends wrong base** ⚠️
   - Extends `Doctrine\Bundle\FixturesBundle\Fixture` directly
   - Can't use `getRef*()` / `addRef*()` magic methods
   - Should extend `App\DataFixtures\AbstractFixture`
6. **Reference providers/consumers verified** ✓ OK
   - All references have valid providers (assuming SystemUserFixture issue is resolved)
   - No circular dependencies found
7. **Migration script integrity** ✓ OK
   - Migration only creates new entities, doesn't modify/delete base data
   - Should NOT break plugin fixtures

---

## Approach (USER APPROVED)

### Decisions Made

1. **Install group handling**: Modify `devModeFixtures` to load `--group=install` first, then `--group=base`
2. **Plugin group standardization**: Change all plugin fixtures to use `group='plugin'` (DishFixture, BookclubFixture, GlossaryFixture)
3. **Repair order**: All base fixtures first (install + base groups), then all plugin fixtures

---

### Phase 1: Justfile & Group Standardization
**Goal:** Update fixture loading process and standardize groups.

**Steps:**
1. Modify `justfile` to load install group before base:
   ```bash
   doctrine:fixtures:load --group=install
   doctrine:fixtures:load --append --group=base
   ```
2. Change plugin fixture groups:
   - DishFixture: `['Dishes']` → `['plugin']`
   - BookclubFixture: `['Bookclub']` → `['plugin']`
   - GlossaryFixture: `['Glossary']` → `['plugin']`
3. Fix GlossaryFixture to extend AbstractFixture instead of base Doctrine Fixture
4. Run `just fixMago` to format
5. Run `just devModeFixtures` to verify (expect failures, that's OK)

**Deliverable:** Consistent fixture groups and loading process.

---

### Phase 2: Syntax Repair & Smoke Tests
**Goal:** Ensure all fixture classes are syntactically valid PHP without executing fixture logic.

**Steps:**
1. Create simple smoke test for each fixture class (instantiation without errors)
2. Fix syntax errors revealed by smoke tests:
   - Missing/incorrect use statements
   - Duplicate extends/implements
   - Typos in class names
3. Run `just fixMago` after each fix
4. Run smoke tests with `just testUnit tests/Unit/DataFixtures/`
5. Don't touch fixture content/logic yet

**Deliverable:** All fixture classes can be instantiated without PHP errors.

---

### Phase 3: Base Fixtures Isolation & Repair (Install Group)
**Goal:** Fix install group fixtures one at a time.

**Fixtures in order:**
1. SystemUserFixture
2. LanguageFixture
3. ConfigFixture
4. EmailTemplateFixture

**Process per fixture:**
1. Change all install fixtures to `tempOffline` group
2. Verify `just devModeFixtures` succeeds with zero install fixtures
3. For each fixture:
   a. Change its group back to `install`
   b. Fix all errors (references, logic, data, dependencies)
   c. Run `just fixMago`
   d. Run `just devModeFixtures` (Haiku agent)
   e. **STOP:** Ask user to verify and commit
   f. Only proceed after user approval

**Deliverable:** All install fixtures working.

---

### Phase 4: Base Fixtures Isolation & Repair (Base Group)
**Goal:** Fix base group fixtures one at a time.

**Fixtures in dependency order:**
1. UserFixture
2. LocationFixture, HostFixture, CmsFixture (can be parallel, all depend on User)
3. ActivityFixture, MessageFixture (can be parallel)
4. EventFixture
5. CmsBlockFixture
6. MenuFixture

**Process per fixture:**
1. Change all base fixtures to `tempOffline` group
2. Verify `just devModeFixtures` succeeds with install fixtures only
3. For each fixture (same process as Phase 3):
   a. Change its group back to `base`
   b. Fix all errors
   c. Run `just fixMago`
   d. Run `just devModeFixtures`
   e. **STOP:** Ask user to verify and commit

**Deliverable:** All base fixtures working.

---

### Phase 5: Migration Script Validation
**Goal:** Ensure migration between base and plugin fixtures doesn't corrupt data.

**Steps:**
1. Verify migration script doesn't break with repaired base fixtures
2. Test full sequence: install + base fixtures → migration → (no plugin fixtures yet)
3. Inspect database to verify migration created groups and mappings correctly
4. Fix any issues in migration command if needed

**Deliverable:** Migration runs successfully after base fixtures.

---

### Phase 6: Plugin Fixtures Repair (MultiSite)
**Goal:** Fix multisite plugin fixtures one at a time.

**Fixtures in dependency order:**
1. GroupFixture
2. GroupMemberFixture, GroupInvitationFixture, GroupEventFixture (level 1)
3. GroupCmsFixture, MessageFixture (level 1)
4. GroupCmsBlockFixture, GroupMenuFixture, GroupCmsSettingsFixture (level 2)

**Process per fixture:**
1. Change all plugin fixtures to `tempOffline` group
2. Verify `just devModeFixtures multisite` succeeds with base + migration only
3. For each fixture:
   a. Change its group back to `plugin`
   b. Fix all errors
   c. Run `just fixMago`
   d. Run `just devModeFixtures multisite`
   e. **STOP:** Ask user to verify and commit

**Deliverable:** All multisite fixtures working.

---

### Phase 7: Plugin Fixtures Repair (Other Plugins)
**Goal:** Fix remaining plugin fixtures.

**Fixtures:**
1. FilmFixture (already group 'plugin', just fix content)
2. DishFixture (now group 'plugin' from Phase 1)
3. BookclubFixture (now group 'plugin' from Phase 1)
4. GlossaryFixture (now group 'plugin' and extends AbstractFixture from Phase 1)

**Process:** Same one-at-a-time approach as Phase 6.

**Deliverable:** All plugin fixtures working.

---

### Phase 8: Final Validation
**Goal:** Confirm entire system works end-to-end.

**Steps:**
1. Run `just devModeFixtures` (install + base fixtures)
2. Verify database has expected data
3. Run `just devModeFixtures multisite` (install + base + plugin fixtures)
4. Verify database has all expected data
5. Run smoke tests to confirm no regressions
6. Clean up: Remove `tempOffline` group references from code

**Deliverable:** Fully functional fixture system.

---

## Execution Workflow (Per Fixture)

For each fixture being repaired:

1. **Read the fixture file** (manually, no scripts)
2. **Identify issues:**
   - Syntax errors
   - Missing/incorrect use statements
   - Duplicate extends/implements
   - Wrong reference method calls
   - Missing dependencies
3. **Fix issues** (edit the file)
4. **Format:** Run `just fixMago` (Haiku agent)
5. **Test:** Run `just devModeFixtures [multisite]` (Haiku agent)
6. **Review test results** from `just testResults` if applicable
7. **STOP and ask user** to verify and commit
8. **Wait for approval** before next fixture

---

## Dependencies & Critical Files

### Critical Base Files
- `/home/xuedi/Projects/meetAgain/src/DataFixtures/AbstractFixture.php` (base class)
- `/home/xuedi/Projects/meetAgain/plugins/multisite/src/DataFixtures/AbstractPluginFixture.php` (plugin base)

### Base Fixture Dependency Order (VERIFIED)

**Install Group** (currently NOT loaded by `devModeFixtures`):
```
1. SystemUserFixture (provides: import, cron users)
2. ConfigFixture (depends: SystemUserFixture)
3. LanguageFixture (depends: UserFixture - but uses SystemUserFixture::IMPORT)
4. EmailTemplateFixture (depends: LanguageFixture)
```

**Base Group** (loaded by `devModeFixtures`):
```
1. UserFixture (provides: 150+ users for all other fixtures)
   NOTE: Implicitly needs SystemUserFixture for IMPORT/CRON references
2. HostFixture (depends: UserFixture)
3. LocationFixture (depends: UserFixture, uses SystemUserFixture::IMPORT)
4. CmsFixture (depends: UserFixture, uses SystemUserFixture::IMPORT)
5. ActivityFixture (depends: UserFixture)
6. MessageFixture (depends: UserFixture)
7. EventFixture (depends: UserFixture, LocationFixture, HostFixture)
8. CmsBlockFixture (depends: CmsFixture, UserFixture)
9. MenuFixture (depends: CmsFixture)
```

**CRITICAL ISSUE**: `devModeFixtures` only loads `--group=base` but many fixtures reference `SystemUserFixture::IMPORT` which is in `--group=install`!

### Plugin Fixture Dependency Order (VERIFIED)

**MultiSite Plugin (group: 'plugin')**:
```
Level 0:
1. GroupFixture (provides: 4 groups - BUT migration creates different groups!)

Level 1:
2. GroupMemberFixture (depends: GroupFixture + UserFixture from base)
3. GroupInvitationFixture (depends: GroupFixture + UserFixture from base)
4. GroupEventFixture (depends: GroupFixture + User, Host, Location from base)
5. GroupCmsFixture (depends: GroupFixture + User from base, provides Cms refs)
6. MessageFixture (depends: UserFixture from base)

Level 2:
7. GroupCmsBlockFixture (depends: GroupCmsFixture + User from base)
8. GroupMenuFixture (depends: GroupFixture + GroupCmsFixture)
9. GroupCmsSettingsFixture (depends: GroupFixture + GroupCmsFixture + BASE CmsFixture!)
```

**Other Plugins**:
```
- GlossaryFixture (group: 'Glossary', no dependencies, extends Doctrine Fixture directly)
- BookclubFixture (group: 'Bookclub', uses SystemUserFixture::IMPORT but declares [] deps)
- DishFixture (group: 'Dishes', uses SystemUserFixture::IMPORT but declares [] deps)
- FilmFixture (group: 'plugin', injects UserFixture, uses getRefUser())
```

**CRITICAL ISSUE**: DishFixture, BookclubFixture, GlossaryFixture have CUSTOM groups, NOT 'plugin'! They won't run with `--group=plugin`!

---

## Testing Strategy

### Smoke Tests (Phase 1)
```php
// Example: tests/Unit/DataFixtures/UserFixtureTest.php
class UserFixtureTest extends TestCase
{
    public function testCanInstantiate(): void
    {
        $fixture = new UserFixture();
        $this->assertInstanceOf(UserFixture::class, $fixture);
    }
}
```

### Fixture Execution Tests (Phases 3 & 5)
- Run `just devModeFixtures` after each base fixture activation
- Run `just devModeFixtures multisite` after each plugin fixture activation
- Manual verification by user before proceeding

### Final Integration Tests (Phase 6)
- Complete fixture load with all groups
- Verify expected entities exist in database
- Check reference integrity

---

## Risks & Considerations

| Risk | Mitigation |
|------|-----------|
| Circular dependencies between fixtures | Dependency analysis in Phase 2 will identify these |
| References not found at runtime | One-at-a-time activation ensures provider exists before consumer |
| Migration script corrupts data | Phase 4 validates this explicitly |
| Previous attempts created inconsistencies | Fresh approach with systematic verification |
| Plugin fixtures conflict with base | Separate group management and never run in parallel |
| Time-consuming manual verification | Necessary to avoid looping/wild edits from previous attempts |

---

## Important Constraints

1. **Never run scripts for file operations** - manually read/edit each fixture
2. **Use Haiku agent for all `just` commands** - token efficiency
3. **Run `just fixMago` after every file change** - ensure code style
4. **One fixture at a time** - no batch processing
5. **User verification required** - stop after each fixture fix
6. **Never run both commands in parallel** - `devModeFixtures` and `devModeFixtures multisite` conflict
7. **Use Opus for all execution tasks** - handle complexity properly
8. **Read files manually, not with scripts** - understand each fixture's code directly
9. **Commit after each working fixture** - incremental progress, easy rollback
10. **Ask when unclear** - don't make assumptions about fixture content or intent

---

## Success Criteria

- [ ] All fixture files have valid syntax
- [ ] All smoke tests pass
- [ ] `just devModeFixtures` runs successfully with all base fixtures
- [ ] Migration script preserves data integrity
- [ ] `just devModeFixtures multisite` runs successfully with all fixtures
- [ ] No circular dependencies
- [ ] All reference providers exist before consumers
- [ ] Code formatted with Mago
- [ ] User has verified and committed each step

---

## Next Steps After Approval

1. Begin Phase 1: Modify justfile and standardize fixture groups
   - Update devModeFixtures to load install + base groups
   - Change DishFixture, BookclubFixture, GlossaryFixture to group 'plugin'
   - Fix GlossaryFixture to extend AbstractFixture
   - Run `just fixMago`
   - Test with `just devModeFixtures` (expect failures)
   - Ask user to verify and commit

2. Begin Phase 2: Create smoke tests for all fixtures
   - Simple instantiation tests to catch syntax errors
   - Run `just testUnit tests/Unit/DataFixtures/`
   - Fix any syntax errors found
   - Ask user to verify and commit

3. Begin Phase 3: Install group fixtures one-by-one
   - SystemUserFixture → LanguageFixture → ConfigFixture → EmailTemplateFixture
   - Stop after each for user verification

4. Continue through remaining phases with same pattern

---

## Notes

- Previous attempts failed due to parallel editing and insufficient verification
- This plan emphasizes incremental progress with user checkpoints
- The abstract fixture classes are sound and don't need modification
- Focus is on fixture content, dependencies, and execution order
