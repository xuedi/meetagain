<?php declare(strict_types=1);

namespace Module\Task\Tests\Stub;

use Doctrine\ORM\EntityManagerInterface;
use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionParameter;
use Module\Task\Contract\ActionSection;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use RuntimeException;

class Action implements ActionInterface
{
    public const string IDENTIFIER = 'stub-action';
    public const string QUICK = 'stub-quick';
    public const string STEPS = 'stub-steps';
    public const int STEP_COUNT = 3;

    public int $runs = 0;

    /** @var array<string, string|int|bool|null> */
    public array $lastValues = [];

    /** @var list<string|null> */
    public array $cursors = [];

    public ?int $failAtStep = null;

    public function __construct(
        private readonly string $identifier = self::IDENTIFIER,
        private readonly bool $withParameters = true,
        private readonly ?EntityManagerInterface $entityManager = null,
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

    public function run(array $values, ?string $cursor = null): Result
    {
        $this->runs++;
        $this->lastValues = $values;
        $this->cursors[] = $cursor;
        if ($this->identifier !== self::STEPS) {
            return new Result(Status::ok, sprintf('run %d', $this->runs));
        }

        $this->entityManager?->clear();
        $step = (int) ($cursor ?? '0') + 1;
        if ($step === $this->failAtStep) {
            throw new RuntimeException(sprintf('step %d broke', $step));
        }
        if ($step < self::STEP_COUNT) {
            return new Result(Status::ok, sprintf('step %d of %d', $step, self::STEP_COUNT), continueFrom: (string) $step);
        }

        return new Result(Status::ok, sprintf('all %d steps', self::STEP_COUNT));
    }
}
