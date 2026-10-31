<?php declare(strict_types=1);

namespace Module\Task\Tests\Stub;

use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionParameter;
use Module\Task\Contract\ActionSection;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;

class Action implements ActionInterface
{
    public const string IDENTIFIER = 'stub-action';
    public const string QUICK = 'stub-quick';

    public int $runs = 0;

    /** @var array<string, string|int|bool|null> */
    public array $lastValues = [];

    public function __construct(
        private readonly string $identifier = self::IDENTIFIER,
        private readonly bool $withParameters = true,
    ) {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getSection(): ActionSection
    {
        return ActionSection::Diagnostics;
    }

    public function getLabel(): string
    {
        return 'Stub action ' . $this->identifier;
    }

    public function getDescription(): string
    {
        return 'A stub action for tests.';
    }

    public function getParameters(): array
    {
        if (!$this->withParameters) {
            return [];
        }

        return [
            ActionParameter::choice('mode', 'Mode', ['Fast' => 'fast', 'Thorough' => 'thorough'], 'fast'),
            ActionParameter::text('path', 'Path', '/srv/default', remembered: true),
            ActionParameter::number('limit', 'Limit', 5),
            ActionParameter::flag('dry', 'Dry run'),
        ];
    }

    public function getFacts(): array
    {
        return ['Rows' => '3'];
    }

    public function run(array $values): Result
    {
        $this->runs++;
        $this->lastValues = $values;

        return new Result(Status::ok, sprintf('run %d', $this->runs));
    }
}
