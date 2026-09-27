<?php declare(strict_types=1);

namespace Module\Email\Tests\Stub;

use DateTimeImmutable;
use Module\Email\Contract\PushDispatcherInterface;
use Override;
use RuntimeException;

final class PushDispatcher implements PushDispatcherInterface
{
    /** @var list<array{identifier: string, recipient: string}> */
    public array $pings = [];

    public bool $throws = false;

    #[Override]
    public function dispatch(string $identifier, string $recipient, ?DateTimeImmutable $deadline): void
    {
        if ($this->throws) {
            throw new RuntimeException('The push service is down.');
        }
        $this->pings[] = ['identifier' => $identifier, 'recipient' => $recipient];
    }
}
