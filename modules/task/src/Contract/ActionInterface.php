<?php declare(strict_types=1);

namespace Module\Task\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface ActionInterface
{
    public function getIdentifier(): string;

    public function getSection(): ActionSection;

    public function getLabel(): string;

    public function getDescription(): string;

    /**
     * @return list<ActionParameter>
     */
    public function getParameters(): array;

    /**
     * @return array<string, string> value keyed by the translation key of its label
     */
    public function getFacts(): array;

    /**
     * @param array<string, string|int|bool|null> $values keyed by parameter key
     */
    public function run(array $values): Result;
}
