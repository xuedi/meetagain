<?php declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\UserRole;
use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
class DashboardVoter extends Voter
{
    public const ACCESS = 'DASHBOARD_ACCESS';

    /** @var object|null Group context service for multisite support */
    private ?object $groupContextService;

    public function __construct(?object $groupContextService = null)
    {
        $this->groupContextService = $groupContextService;
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ACCESS;
    }

    #[Override]
    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Admin users always get access
        if ($user->hasUserRole(UserRole::Admin)) {
            return true;
        }

        // Group owners get access if multisite plugin is available
        if (
            $this->groupContextService !== null
            && method_exists($this->groupContextService, 'getManagedGroupsForUser')
        ) {
            $managedGroups = $this->groupContextService->getManagedGroupsForUser($user);
            if (!empty($managedGroups)) {
                return true;
            }
        }

        return false;
    }
}
