<?php declare(strict_types=1);

namespace Module\Task\Internal\Action;

use LogicException;
use Module\Task\Contract\ActionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class Registry
{
    /**
     * @param iterable<ActionInterface> $actions
     */
    public function __construct(
        #[AutowireIterator(ActionInterface::class)]
        private iterable $actions,
    ) {}

    /**
     * @return array<string, ActionInterface>
     */
    public function all(): array
    {
        $byIdentifier = [];
        foreach ($this->actions as $action) {
            $identifier = $action->getIdentifier();
            if (isset($byIdentifier[$identifier])) {
                throw new LogicException(sprintf('Two actions share the identifier "%s".', $identifier));
            }
            $byIdentifier[$identifier] = $action;
        }
        ksort($byIdentifier);

        return $byIdentifier;
    }

    public function find(string $identifier): ?ActionInterface
    {
        return $this->all()[$identifier] ?? null;
    }
}
