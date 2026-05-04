<?php declare(strict_types=1);

namespace App\Security\Permission;

use App\Entity\User;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Single Symfony voter that owns every domain.action permission attribute and
 * dispatches to tagged PermissionCheckerInterface implementations. The first
 * supporting checker that returns a non-null vote wins; if no checker supports
 * or every supporting checker abstains, the gateway abstains - which under
 * affirmative + allow_if_all_abstain: true means access is allowed.
 */
final class PermissionGateway implements VoterInterface
{
    /**
     * @param iterable<PermissionCheckerInterface> $checkers
     */
    public function __construct(
        private readonly Security $security,
        #[AutowireIterator(PermissionCheckerInterface::class)]
        private readonly iterable $checkers,
    ) {}

    #[Override]
    public function vote(
        #[\SensitiveParameter] TokenInterface $token,
        mixed $subject,
        array $attributes,
        ?Vote $vote = null,
    ): int {
        $context = null;
        $result = self::ACCESS_ABSTAIN;

        foreach ($attributes as $attribute) {
            if (!is_string($attribute)) {
                continue;
            }

            foreach ($this->checkers as $checker) {
                if (!$checker->supports($attribute, $subject)) {
                    continue;
                }
                $context ??= $this->buildContext($token, $subject);
                $decision = $checker->vote($attribute, $context);
                if ($decision === true) {
                    return self::ACCESS_GRANTED;
                }
                if ($decision === false) {
                    $result = self::ACCESS_DENIED;
                }
            }
        }

        return $result;
    }

    public function supportsAttribute(string $attribute): bool
    {
        foreach ($this->checkers as $checker) {
            if ($checker->supports($attribute, null)) {
                return true;
            }
        }

        return false;
    }

    public function supportsType(string $subjectType): bool
    {
        return true;
    }

    private function buildContext(TokenInterface $token, mixed $subject): PermissionContext
    {
        $user = $token->getUser();

        return new PermissionContext(
            actor: $user instanceof User ? $user : null,
            subject: $subject,
            isAdmin: $this->security->isGranted('ROLE_ADMIN'),
        );
    }
}
