# Permissions

MeetAgain uses Symfony's built-in security system for all access control —
`#[IsGranted]`, `security.role_hierarchy`, and `VoterInterface`. There is no custom
permission layer.

---

## Role hierarchy

Roles form an inheritance chain defined in `config/packages/security.yaml`:

```
ROLE_USER → ROLE_ORGANIZER → ROLE_ADMIN
```

| Role             | Who holds it                |
|------------------|-----------------------------|
| `ROLE_USER`      | Any logged-in user          |
| `ROLE_ORGANIZER` | Organizers and above        |
| `ROLE_ADMIN`     | Platform administrators     |

Group-scoped roles (`ROLE_GROUP_MEMBER`, `ROLE_GROUP_ORGANIZER`, `ROLE_GROUP_ADMIN`,
`ROLE_GROUP_OWNER`) are added by the multisite plugin via `GroupRoleVoter` and are not
part of the core hierarchy.

---

## Step 1 — Protect controllers

Use `#[IsGranted('ROLE_X')]` on controller classes or methods:

```php
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/filmclub')]
final class FilmController extends AbstractController
{
    #[Route('/vote/manage', name: 'app_filmclub_vote_manage')]
    #[IsGranted('ROLE_ORGANIZER')]
    public function manage(): Response { ... }
}
```

The class-level attribute sets the access floor; method-level attributes add stricter
per-action checks on top.

---

## Step 2 — Runtime checks in PHP

Inject `Symfony\Bundle\SecurityBundle\Security` and call `isGranted()`:

```php
use Symfony\Bundle\SecurityBundle\Security;

readonly class VoteService
{
    public function __construct(private Security $security) {}

    public function getViewData(): array
    {
        return [
            'canManage' => $this->security->isGranted('ROLE_ORGANIZER'),
        ];
    }
}
```

---

## Step 3 — Template checks

Use Symfony's built-in `is_granted()` Twig function:

```twig
{% if is_granted('ROLE_ORGANIZER') %}
    <a href="{{ path('app_filmclub_vote_manage') }}">Manage vote</a>
{% endif %}
```

---

## Optional: custom voter for event actions

To gate event-scoped actions based on plugin-specific logic, implement a Symfony `Voter`
with an `Event` subject and one of the built-in action attribute strings:

| Attribute       | Triggered by                |
|-----------------|-----------------------------|
| `event.rsvp`    | Toggle RSVP on an event     |
| `event.comment` | Post a comment on an event  |
| `event.upload`  | Upload images to an event   |

```php
use App\Entity\Event;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class MyEventVoter extends Voter
{
    private const SUPPORTED = ['event.rsvp', 'event.comment', 'event.upload'];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::SUPPORTED, true) && $subject instanceof Event;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        /** @var Event $subject */
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // return true to grant, false to deny
        return $this->canAccess($subject, $user);
    }
}
```

Controllers check the voter with `$this->isGranted('event.rsvp', $event)`. No custom
tagging is required — Symfony auto-registers all classes implementing `VoterInterface`.

---

## Optional: custom voter for other logic

For any other runtime context check (e.g. subscription gating, feature flags), implement
a standard Symfony `Voter`:

```php
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class SubscriptionVoter extends Voter
{
    public function __construct(private readonly SubscriptionService $subscriptionService) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'FEATURE_PREMIUM';
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $user = $token->getUser();
        return $user instanceof User && $this->subscriptionService->isActive($user);
    }
}
```

Check it in a controller with `$this->isGranted('FEATURE_PREMIUM')` or in Twig with
`is_granted('FEATURE_PREMIUM')`.

---

## Checklist

When adding permissions to a new plugin:

- [ ] Add `#[IsGranted('ROLE_X')]` to every controller class (floor) and restricted methods
- [ ] Use `$security->isGranted('ROLE_X')` for runtime branching in services
- [ ] Use `is_granted('ROLE_X')` in Twig for conditional UI
- [ ] Optionally implement a Symfony `Voter` for plugin-specific runtime context checks
