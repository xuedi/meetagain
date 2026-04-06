# Permissions

MeetAgain uses a custom, plugin-extensible permission system. Plugins can register
additional roles and optionally veto any permission decision with a custom message and
redirect.

---

## Concepts

### Roles and hierarchy

Roles form an inheritance chain. A user holding a higher role automatically satisfies
requirements for all lower roles.

```
Guest → User → Organizer → Admin
```

| Role                  | Identifier       | Who holds it             |
|-----------------------|------------------|--------------------------|
| `CoreRole::Guest`     | `core.guest`     | Unauthenticated visitors |
| `CoreRole::User`      | `core.user`      | Any logged-in user       |
| `CoreRole::Organizer` | `core.organizer` | Organizers and above     |
| `CoreRole::Admin`     | `core.admin`     | Platform administrators  |

Plugin role enums (e.g. `MultisiteRole`) implement the same `RoleInterface` and integrate
via `RoleProviderInterface` + `EffectiveRoleProviderInterface`.

### Authorization model

`#[RequiresPermission(CoreRole::X)]` on a controller class or method is the only mechanism
for access control. There are no separate "action" enums — the role itself is the gate.
Method-level attributes add a stricter role check on top of the class-level floor.

---

## Step 1 — Protect controllers

Use `#[RequiresPermission]` on controller classes and methods. Every controller **must**
declare a class-level attribute as the access floor; method-level attributes add stricter
per-role checks on top of it.

```php
<?php declare(strict_types=1);

namespace Plugin\Filmclub\Controller;

use App\Enum\CoreRole;
use App\Security\Attribute\RequiresPermission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[RequiresPermission(CoreRole::Guest)]              // floor: public
#[Route('/filmclub')]
final class FilmController extends AbstractController
{
    #[Route('/suggest', name: 'app_filmclub_suggest', methods: ['POST'])]
    #[RequiresPermission(CoreRole::User)]            // must be logged in
    public function suggest(): Response
    {
        // ...
    }

    #[Route('/vote/manage', name: 'app_filmclub_vote_manage')]
    #[RequiresPermission(CoreRole::Organizer)]       // requires Organizer or above
    public function manage(): Response
    {
        // ...
    }
}
```

When a check fails, the `PermissionListener` throws a 403 (or redirects, if a voter
provided a redirect route).

---

## Step 2 — Check permissions at runtime

For conditional branching inside a controller or service, inject `PermissionEvaluator`
and call `isAllowed()`:

```php
use App\Enum\CoreRole;
use App\Permission\PermissionEvaluator;

readonly class VoteService
{
    public function __construct(
        private PermissionEvaluator $permissionEvaluator,
    ) {}

    public function getViewData(): array
    {
        return [
            'canManage' => $this->permissionEvaluator->isAllowed(CoreRole::Organizer)->allowed,
        ];
    }
}
```

`isAllowed()` returns a `PermissionDecision` with:

- `->allowed` — `true` / `false`
- `->denyMessageKey` — translation key provided by a voter (if any)
- `->denyRedirectRoute` — redirect route provided by a voter (if any)

---

## Step 3 — Check permissions in templates

Use the `is_role_satisfied()` Twig function:

```twig
{# Show the manage button only to organizers #}
{% if is_role_satisfied('core.organizer') %}
    <a href="{{ path('app_filmclub_vote_manage') }}">Manage vote</a>
{% endif %}
```

The argument is a role identifier string (`core.guest`, `core.user`, `core.organizer`,
`core.admin`, or any plugin-registered role identifier).

---

## Optional: add plugin roles

If your plugin has its own role tier (e.g. group-scoped roles), implement:

- `RoleProviderInterface` — register the role definitions and their parent chain
- `EffectiveRoleProviderInterface` — map the current user to role identifiers at runtime
- The role enum itself implements `RoleInterface` (`roleId(): string`)

See `plugins/multisite/src/Permission/` for a complete example.

---

## Optional: custom voter

Implement `PermissionVoterInterface` when you need to override or veto a permission
decision based on runtime context — for example, locking access to content when a user
isn't a group member.

**`plugins/filmclub/src/Permission/ClosedVoteVoter.php`:**

```php
<?php declare(strict_types=1);

namespace Plugin\Filmclub\Permission;

use App\Permission\PermissionVote;
use App\Permission\PermissionVoterInterface;
use App\Permission\RoleInterface;
use App\Enum\CoreRole;
use Plugin\Filmclub\Repository\VoteRepository;

readonly class ClosedVoteVoter implements PermissionVoterInterface
{
    public function __construct(
        private VoteRepository $voteRepository,
    ) {}

    public function getPriority(): int
    {
        return 0;
    }

    public function vote(RoleInterface $role, array $effectiveRoles): PermissionVote
    {
        // Only intercept User-level requests — abstain for guests and admins
        if ($role->roleId() !== CoreRole::User->roleId()) {
            return PermissionVote::abstain();
        }

        $activeVote = $this->voteRepository->findActive();

        if ($activeVote === null) {
            return PermissionVote::deny('filmclub.no_active_vote');
        }

        return PermissionVote::abstain(); // Let the default rule decide
    }
}
```

**Vote outcomes:**

| Return                                              | Effect                                               |
|-----------------------------------------------------|------------------------------------------------------|
| `PermissionVote::allow()`                           | Explicitly grant access (overrides a default deny)   |
| `PermissionVote::deny($messageKey, $redirectRoute)` | Block access; flash message + optional redirect      |
| `PermissionVote::abstain()`                         | No opinion; other voters and the default rule decide |

**Priority:** Higher `getPriority()` values run first. If multiple voters return `Deny`,
the first one's message and redirect are used.

---

## Checklist

When adding permissions to a new plugin:

- [ ] Add `#[RequiresPermission]` to every controller class (floor) and restricted methods
- [ ] Use `PermissionEvaluator::isAllowed(CoreRole::X)` for runtime branching
- [ ] Use `is_role_satisfied('core.x')` in Twig for conditional UI
- [ ] Optionally implement `RoleProviderInterface` + `EffectiveRoleProviderInterface` for plugin-specific roles
- [ ] Optionally implement `PermissionVoterInterface` for runtime context checks
