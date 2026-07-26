<?php declare(strict_types=1);

namespace App\Security\Permission;

use App\Entity\User;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class Gateway implements VoterInterface
{
    /**
     * @param iterable<CheckerInterface> $checkers
     */
    public function __construct(
        private readonly Security $security,
        #[AutowireIterator(CheckerInterface::class)]
        private readonly iterable $checkers,
    ) {}

    #[Override]
    public function vote(#[\SensitiveParameter] TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
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

    private function buildContext(#[\SensitiveParameter] TokenInterface $token, mixed $subject): Context
    {
        $user = $token->getUser();

        return new Context(actor: $user instanceof User ? $user : null, subject: $subject, isAdmin: $this->security->isGranted('ROLE_ADMIN'));
    }
}
