<?php declare(strict_types=1);

namespace Plugin\Ranking\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class GroupContextResolver
{
    public const int DEFAULT_GROUP_ID = 1;

    /** @param iterable<GroupContextProviderInterface> $providers */
    public function __construct(
        #[AutowireIterator(GroupContextProviderInterface::class)]
        private iterable $providers,
    ) {}

    public function getCurrentGroupId(): int
    {
        $sorted = [];
        foreach ($this->providers as $provider) {
            $sorted[] = $provider;
        }
        usort($sorted, static fn(GroupContextProviderInterface $a, GroupContextProviderInterface $b) => $b->getPriority() <=> $a->getPriority());

        foreach ($sorted as $provider) {
            $id = $provider->getCurrentGroupId();
            if ($id !== null) {
                return $id;
            }
        }

        return self::DEFAULT_GROUP_ID;
    }

    public function getCurrentGroupName(): string
    {
        $sorted = [];
        foreach ($this->providers as $provider) {
            $sorted[] = $provider;
        }
        usort($sorted, static fn(GroupContextProviderInterface $a, GroupContextProviderInterface $b) => $b->getPriority() <=> $a->getPriority());

        foreach ($sorted as $provider) {
            $name = $provider->getCurrentGroupName();
            if ($name !== null) {
                return $name;
            }
        }

        return 'default';
    }
}
