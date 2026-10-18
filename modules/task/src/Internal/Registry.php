<?php declare(strict_types=1);

namespace Module\Task\Internal;

use LogicException;
use Module\Task\Contract\TaskInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class Registry
{
    /**
     * @param iterable<TaskInterface> $tasks
     */
    public function __construct(
        #[AutowireIterator(TaskInterface::class)]
        private iterable $tasks,
    ) {}

    /**
     * @return array<string, TaskInterface>
     */
    public function all(): array
    {
        $byIdentifier = [];
        foreach ($this->tasks as $task) {
            $identifier = $task->getIdentifier();
            if (isset($byIdentifier[$identifier])) {
                throw new LogicException(sprintf('Two tasks share the identifier "%s".', $identifier));
            }
            $byIdentifier[$identifier] = $task;
        }
        ksort($byIdentifier);

        return $byIdentifier;
    }

    public function find(string $identifier): ?TaskInterface
    {
        return $this->all()[$identifier] ?? null;
    }
}
