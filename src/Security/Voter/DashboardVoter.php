<?php declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
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

    public function __construct(
        private readonly ?object $groupContextService = null,
    ) {}

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

        // ROLE_ADMIN always granted
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Group owners/organizers granted if Multisite enabled
        if ($this->groupContextService === null) {
            return false; // Plugin not installed
        }

        if (!method_exists($this->groupContextService, 'getManagedGroupsForUser')) {
            return false;
        }

        $managedGroups = $this->groupContextService->getManagedGroupsForUser($user);
        return count($managedGroups) > 0;
    }
}
