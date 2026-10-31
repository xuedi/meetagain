<?php declare(strict_types=1);

namespace Module\Task\Internal\Action;

use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionParameter;
use Module\Task\Contract\ActionSettingsInterface;
use Module\Task\Internal\Repository\ActionSettingRepository;
use Override;

readonly class Settings implements ActionSettingsInterface
{
    public function __construct(
        private ActionSettingRepository $repository,
    ) {}

    #[Override]
    public function get(string $identifier, string $key): string|int|bool|null
    {
        return $this->repository->findValues($identifier)[$key] ?? null;
    }

    /**
     * @return array<string, string|int|bool|null> every parameter's starting value: the saved one, else its default
     */
    public function prefill(ActionInterface $action): array
    {
        $saved = $this->repository->findValues($action->getIdentifier());
        $values = [];
        foreach ($action->getParameters() as $parameter) {
            $values[$parameter->key] = $parameter->remembered && array_key_exists($parameter->key, $saved) ? $saved[$parameter->key] : $parameter->default;
        }

        return $values;
    }

    /**
     * @param array<string, string|int|bool|null> $values
     */
    public function remember(ActionInterface $action, array $values): void
    {
        $remembered = array_filter($action->getParameters(), static fn(ActionParameter $parameter): bool => $parameter->remembered);
        $kept = [];
        foreach ($remembered as $parameter) {
            $kept[$parameter->key] = $values[$parameter->key] ?? null;
        }

        $this->repository->store($action->getIdentifier(), $kept);
    }
}
