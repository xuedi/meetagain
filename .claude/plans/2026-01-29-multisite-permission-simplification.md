# Multisite Permission System Simplification

Date: 2026-01-29
Model: opus

## Objective

Simplify the multisite plugin's permission system from a fine-grained, database-driven approach to a simple role-based system that aligns with the base application's `UserRole` enum pattern.

**Dependencies**: Should be implemented AFTER the fixture consolidation plan is complete.

## Analysis

### Current Permission System (Fine-Grained)

**Entities**:
- `Permission` - 20 granular permissions (GROUP_VIEW, GROUP_EDIT, EVENT_CREATE, etc.)
- `GroupRolePermission` - Junction table mapping `GroupRole` to `Permission`

**Service**:
- `PermissionService::hasPermission(User $user, Group $group, string $permission)` - Checks if user has specific permission

**Fixtures**:
- `PermissionFixture` - Creates 20 permissions and maps them to roles

**Voters**:
- `GroupVoter` - Uses `PermissionService` to check permissions
- `GroupEventVoter` - Uses `PermissionService` for event permissions
- Other voters using the permission system

**Permission List** (from `PermissionFixture`):
```php
GROUP_VIEW, GROUP_EDIT, GROUP_DELETE, GROUP_SETTINGS,
EVENT_CREATE, EVENT_EDIT, EVENT_DELETE, EVENT_VIEW_PARTICIPANTS,
MEMBER_INVITE, MEMBER_REMOVE, MEMBER_EDIT_ROLE,
CMS_CREATE, CMS_EDIT, CMS_DELETE,
MENU_CREATE, MENU_EDIT, MENU_DELETE,
INVITATION_CREATE, INVITATION_DELETE
MESSAGE_CREATE, MESSAGE_DELETE
```

### New Permission System (Role-Based)

**Base App Pattern**:
- `UserRole` enum: `MetaAdmin`, `Admin`, `Organizer`, `User`
- `RequiresRole` attribute on controllers
- Direct role checks in code

**Multisite Plugin Pattern** (should mirror base):
- `GroupRole` enum: `Owner`, `Organizer`, `Member`
- Direct role checks in voters
- No database entities for permissions
- Role hierarchy determines access

### Permission Mapping

Map fine-grained permissions to `GroupRole` hierarchy:

| Permission | Owner | Organizer | Member |
|-----------|-------|-----------|--------|
| GROUP_VIEW | ✓ | ✓ | ✓ |
| GROUP_EDIT | ✓ | ✓ | ✗ |
| GROUP_DELETE | ✓ | ✗ | ✗ |
| GROUP_SETTINGS | ✓ | ✗ | ✗ |
| EVENT_CREATE | ✓ | ✓ | ✗ |
| EVENT_EDIT | ✓ | ✓ | ✗ |
| EVENT_DELETE | ✓ | ✓ | ✗ |
| EVENT_VIEW_PARTICIPANTS | ✓ | ✓ | ✗ |
| MEMBER_INVITE | ✓ | ✓ | ✗ |
| MEMBER_REMOVE | ✓ | ✗ | ✗ |
| MEMBER_EDIT_ROLE | ✓ | ✗ | ✗ |
| CMS_CREATE | ✓ | ✓ | ✗ |
| CMS_EDIT | ✓ | ✓ | ✗ |
| CMS_DELETE | ✓ | ✗ | ✗ |
| MENU_CREATE | ✓ | ✓ | ✗ |
| MENU_EDIT | ✓ | ✓ | ✗ |
| MENU_DELETE | ✓ | ✗ | ✗ |
| INVITATION_CREATE | ✓ | ✓ | ✗ |
| INVITATION_DELETE | ✓ | ✓ | ✗ |
| MESSAGE_CREATE | ✓ | ✓ | ✓ |
| MESSAGE_DELETE | ✓ | ✓ | ✗ |

**Simplified Role Rules**:
- **Member**: Can view group, create messages
- **Organizer**: Can manage events, CMS, invitations, messages (but not group settings or members)
- **Owner**: Can do everything including group settings, delete group, manage members

## Approach

### Strategy

1. **Update `PermissionService`** to use role-based checks instead of database lookups
2. **Remove database entities** (`Permission`, `GroupRolePermission`)
3. **Update all voters** to use simplified role checks
4. **Remove `PermissionFixture`**
5. **Create database migration** to drop permission tables
6. **Update tests** to reflect new permission system

### Benefits

1. **Simpler codebase** - No database lookups for permissions
2. **Faster** - In-memory role checks vs database queries
3. **Consistent** - Matches base app's `UserRole` pattern
4. **Maintainable** - Fewer moving parts, easier to reason about
5. **Type-safe** - Compile-time checks with enums

## Implementation Steps

### Step 1: Update PermissionService to Use Role-Based Checks

**File**: `plugins/multisite/src/Service/PermissionService.php`

**Before**:
```php
public function hasPermission(User $user, Group $group, string $permission): bool
{
    // Database lookup for permission
    $membership = $this->getMembership($user, $group);
    if (!$membership) {
        return false;
    }

    $role = $membership->getRole();
    $rolePermissions = $this->permissionRepository->findByRole($role);

    return in_array($permission, $rolePermissions);
}
```

**After**:
```php
public function hasPermission(User $user, Group $group, string $action): bool
{
    $membership = $this->getMembership($user, $group);
    if (!$membership) {
        return false;
    }

    $role = $membership->getRole();

    return match($action) {
        // Members can view and message
        'group.view', 'message.create' => in_array($role, [
            GroupRole::Member,
            GroupRole::Organizer,
            GroupRole::Owner
        ]),

        // Organizers can manage content
        'group.edit', 'event.create', 'event.edit', 'event.delete',
        'event.view_participants', 'member.invite',
        'cms.create', 'cms.edit', 'menu.create', 'menu.edit',
        'invitation.create', 'invitation.delete', 'message.delete' => in_array($role, [
            GroupRole::Organizer,
            GroupRole::Owner
        ]),

        // Only owners can manage group and members
        'group.delete', 'group.settings', 'member.remove',
        'member.edit_role', 'cms.delete', 'menu.delete' => $role === GroupRole::Owner,

        default => false,
    };
}
```

**Reasoning**: Use PHP 8's match expression for clean, type-safe permission checks based on role hierarchy.

**Tests**: Update `PermissionServiceTest` to verify role-based checks.

---

### Step 2: Update GroupVoter to Use Simplified Checks

**File**: `plugins/multisite/src/Security/Voter/GroupVoter.php`

**Before**:
```php
protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
{
    $user = $token->getUser();
    if (!$user instanceof User) {
        return false;
    }

    return match($attribute) {
        'GROUP_VIEW' => $this->permissionService->hasPermission($user, $subject, 'GROUP_VIEW'),
        'GROUP_EDIT' => $this->permissionService->hasPermission($user, $subject, 'GROUP_EDIT'),
        'GROUP_DELETE' => $this->permissionService->hasPermission($user, $subject, 'GROUP_DELETE'),
        // ... 17 more permissions
        default => false,
    };
}
```

**After**:
```php
protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
{
    $user = $token->getUser();
    if (!$user instanceof User) {
        return false;
    }

    return match($attribute) {
        'GROUP_VIEW' => $this->permissionService->hasPermission($user, $subject, 'group.view'),
        'GROUP_EDIT' => $this->permissionService->hasPermission($user, $subject, 'group.edit'),
        'GROUP_DELETE' => $this->permissionService->hasPermission($user, $subject, 'group.delete'),
        'GROUP_SETTINGS' => $this->permissionService->hasPermission($user, $subject, 'group.settings'),
        default => false,
    };
}
```

**Reasoning**: Voter becomes thin delegation layer. Could be further simplified by using attribute names directly.

**Alternative** (more direct):
```php
protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
{
    $user = $token->getUser();
    if (!$user instanceof User) {
        return false;
    }

    $membership = $this->groupMemberRepository->findOneBy([
        'user' => $user,
        'group' => $subject,
    ]);

    if (!$membership) {
        return false;
    }

    $role = $membership->getRole();

    return match($attribute) {
        'GROUP_VIEW' => true, // All members can view
        'GROUP_EDIT' => in_array($role, [GroupRole::Organizer, GroupRole::Owner]),
        'GROUP_DELETE', 'GROUP_SETTINGS' => $role === GroupRole::Owner,
        default => false,
    };
}
```

**Tests**: Update `GroupVoterTest` to verify role-based authorization.

---

### Step 3: Update Other Voters

**Files**:
- `plugins/multisite/src/Security/Voter/GroupEventVoter.php`
- `plugins/multisite/src/Security/Voter/GroupCmsVoter.php`
- `plugins/multisite/src/Security/Voter/GroupMenuVoter.php`
- Any other voters using `PermissionService`

**Pattern**:
```php
protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
{
    $user = $token->getUser();
    if (!$user instanceof User) {
        return false;
    }

    // Get group from subject (event, cms page, menu, etc.)
    $group = $subject->getGroup();

    $membership = $this->groupMemberRepository->findOneBy([
        'user' => $user,
        'group' => $group,
    ]);

    if (!$membership) {
        return false;
    }

    $role = $membership->getRole();

    return match($attribute) {
        'EVENT_CREATE', 'EVENT_EDIT', 'EVENT_DELETE' => in_array($role, [
            GroupRole::Organizer,
            GroupRole::Owner
        ]),
        'EVENT_VIEW_PARTICIPANTS' => in_array($role, [
            GroupRole::Organizer,
            GroupRole::Owner
        ]),
        default => false,
    };
}
```

**Tests**: Update voter tests for each modified voter.

---

### Step 4: Remove Permission Entities

**Files to Delete**:
- `plugins/multisite/src/Entity/Permission.php`
- `plugins/multisite/src/Entity/GroupRolePermission.php`

**Files to Update** (remove references):
- `plugins/multisite/src/Repository/PermissionRepository.php` - DELETE
- `plugins/multisite/src/Repository/GroupRolePermissionRepository.php` - DELETE

**Reasoning**: No longer need database storage for permissions.

**Tests**: Remove entity tests if they exist.

---

### Step 5: Remove PermissionFixture

**File to Delete**:
- `plugins/multisite/src/DataFixtures/PermissionFixture.php`

**Files to Update**:
- `plugins/multisite/src/DataFixtures/GroupFixture.php` - Remove `PermissionFixture` dependency

**Before** (`GroupFixture`):
```php
public function getDependencies(): array
{
    return [
        UserFixture::class,
        PermissionFixture::class, // REMOVE THIS
    ];
}
```

**After** (`GroupFixture`):
```php
public function getDependencies(): array
{
    return [
        UserFixture::class,
    ];
}
```

**Tests**: Verify fixtures load without `PermissionFixture`.

---

### Step 6: Create Database Migration

**Command**:
```bash
just app make:migration RemovePermissionTables
```

**Migration File** (`plugins/multisite/migrations/VersionXXXX_remove_permission_tables.php`):
```php
public function up(Schema $schema): void
{
    // Drop junction table first (foreign keys)
    $this->addSql('DROP TABLE IF EXISTS group_role_permission');

    // Drop permission table
    $this->addSql('DROP TABLE IF EXISTS permission');
}

public function down(Schema $schema): void
{
    // Recreate permission table
    $this->addSql('CREATE TABLE permission (
        id INT AUTO_INCREMENT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY(id),
        UNIQUE INDEX UNIQ_permission_name (name)
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

    // Recreate junction table
    $this->addSql('CREATE TABLE group_role_permission (
        id INT AUTO_INCREMENT NOT NULL,
        group_role VARCHAR(50) NOT NULL,
        permission_id INT NOT NULL,
        PRIMARY KEY(id),
        INDEX IDX_group_role_permission_permission (permission_id),
        CONSTRAINT FK_group_role_permission_permission FOREIGN KEY (permission_id)
            REFERENCES permission (id) ON DELETE CASCADE
    ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
}
```

**Tests**:
- Run migration: `just app doctrine:migrations:migrate`
- Verify tables dropped: `just dockerDatabase "SHOW TABLES;"`
- Rollback test: `just app doctrine:migrations:migrate prev`
- Re-migrate: `just app doctrine:migrations:migrate`

---

### Step 7: Simplify PermissionService (Optional)

**Decision**: Keep `PermissionService` for centralized permission logic OR remove it and inline checks in voters?

**Option A: Keep PermissionService** (Recommended)
- Centralized permission logic
- Easier to test
- Single source of truth

**Option B: Remove PermissionService**
- Fewer layers
- Direct role checks in voters
- Slightly faster (no service call)

**Recommendation**: Keep `PermissionService` with simplified role-based checks. This maintains consistency with the base app's service-oriented architecture.

---

### Step 8: Update Tests

**Test Files to Update**:
- `tests/Unit/Service/PermissionServiceTest.php` - Update for role-based checks
- `tests/Unit/Security/Voter/GroupVoterTest.php` - Remove permission entity setup
- `tests/Unit/Security/Voter/GroupEventVoterTest.php` - Simplify permission checks
- Any functional tests that create permissions

**Pattern for Updated Tests**:
```php
public function testOrganizerCanEditGroup(): void
{
    // Arrange
    $user = $this->createUser();
    $group = $this->createGroup();
    $membership = new GroupMember();
    $membership->setUser($user);
    $membership->setGroup($group);
    $membership->setRole(GroupRole::Organizer);
    $this->entityManager->persist($membership);
    $this->entityManager->flush();

    // Act
    $hasPermission = $this->permissionService->hasPermission($user, $group, 'group.edit');

    // Assert
    $this->assertTrue($hasPermission);
}

public function testMemberCannotEditGroup(): void
{
    // Arrange
    $user = $this->createUser();
    $group = $this->createGroup();
    $membership = new GroupMember();
    $membership->setUser($user);
    $membership->setGroup($group);
    $membership->setRole(GroupRole::Member);
    $this->entityManager->persist($membership);
    $this->entityManager->flush();

    // Act
    $hasPermission = $this->permissionService->hasPermission($user, $group, 'group.edit');

    // Assert
    $this->assertFalse($hasPermission);
}
```

**Tests**: Run full test suite after updates: `just test`

---

## Testing Strategy

### Phase 1: Unit Tests

```bash
# Test PermissionService
just testUnit Tests/Unit/Service/PermissionServiceTest

# Test Voters
just testUnit Tests/Unit/Security/Voter/GroupVoterTest
just testUnit Tests/Unit/Security/Voter/GroupEventVoterTest

# Expected: All tests pass with new role-based logic
```

### Phase 2: Integration Tests

```bash
# Load fixtures and verify no permission tables
just devModeFixtures multisite
just dockerDatabase "SHOW TABLES;"

# Expected: No permission or group_role_permission tables
```

### Phase 3: Functional Tests

```bash
# Test group access control
just testFunctional Tests/Functional/Controller/GroupControllerTest

# Expected: Authorization works correctly with role-based system
```

### Phase 4: Manual Verification

1. Login as Owner → Verify full access to group settings
2. Login as Organizer → Verify can edit events/CMS but not group settings
3. Login as Member → Verify can view group and create messages only
4. Check that non-members cannot access group at all

---

## Risks & Considerations

### Risk 1: Permission Logic Changes

**Likelihood**: Medium - Some edge cases may be missed in mapping

**Mitigation**:
- Comprehensive test coverage
- Manual testing of all group actions
- Document role permission matrix clearly

### Risk 2: Performance Impact

**Likelihood**: Low - Role checks are faster than database queries

**Mitigation**: Benchmark before/after if concerned

### Risk 3: Breaking Changes for Existing Installations

**Likelihood**: Medium - Production installations will need migration

**Mitigation**:
- Clear migration guide
- Migration script handles schema changes
- Test migration path thoroughly

### Risk 4: Loss of Flexibility

**Concern**: Fine-grained permissions were more flexible

**Response**:
- Current permission mapping is 1:1 with roles, no actual flexibility used
- Role-based system is simpler and sufficient for use case
- Can always add permission entities back if needed (via migration rollback)

---

## Rollback Strategy

If issues arise:

1. **Rollback migration**: `just app doctrine:migrations:migrate prev`
2. **Restore entities**: `git checkout plugins/multisite/src/Entity/Permission*.php`
3. **Restore fixtures**: `git checkout plugins/multisite/src/DataFixtures/PermissionFixture.php`
4. **Restore service**: `git checkout plugins/multisite/src/Service/PermissionService.php`
5. **Reload fixtures**: `just devModeFixtures multisite`

---

## Success Criteria

- [ ] `Permission` and `GroupRolePermission` entities removed
- [ ] `PermissionFixture` removed
- [ ] `PermissionService` uses role-based checks (no database lookups)
- [ ] All voters updated to use simplified permission checks
- [ ] Database migration removes permission tables
- [ ] All tests pass
- [ ] Manual testing confirms correct authorization behavior
- [ ] Fixtures load without permission entities
- [ ] Performance improved (optional: benchmark)

---

## Follow-Up Work

After implementation:

1. **Update documentation**
   - Document role permission matrix
   - Update multisite plugin README with new permission system
   - Add migration guide for existing installations

2. **Consider consolidating voters**
   - If voters follow same pattern, could create abstract base voter
   - Would reduce code duplication

3. **Performance benchmarking** (optional)
   - Measure authorization check performance before/after
   - Document improvements

---

## Implementation Sequence

**Recommended Order**:

1. **Step 1**: Update `PermissionService` (core logic change)
2. **Step 2-3**: Update all voters (consumers of service)
3. **Step 8**: Update tests (verify new behavior)
4. **Step 5**: Remove `PermissionFixture` (no longer needed)
5. **Step 4**: Remove entity files (cleanup)
6. **Step 6**: Create and run migration (schema change)
7. **Step 7**: Decide on `PermissionService` simplification (optional refactor)

**Execute incrementally**: Commit after each step, run tests, verify before proceeding.
