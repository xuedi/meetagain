<?php declare(strict_types=1);

namespace Module\Task\Tests\Stub;

use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use RuntimeException;

class Task implements TaskInterface
{
    public const string IDENTIFIER = 'stub-task';

    public int $runs = 0;
    public ?string $failWith = null;

    public function __construct(
        private readonly string $identifier = self::IDENTIFIER,
        private readonly Cadence $cadence = new Cadence('*/5 * * * *'),
    ) {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getCadence(): Cadence
    {
        return $this->cadence;
    }

    public function run(): Result
    {
        $this->runs++;
        if ($this->failWith !== null) {
            throw new RuntimeException($this->failWith);
        }

        return new Result(Status::ok, sprintf('run %d', $this->runs));
    }
}
