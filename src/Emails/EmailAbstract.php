<?php declare(strict_types=1);

namespace App\Emails;

use DateTimeImmutable;
use InvalidArgumentException;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\EmailInterface;
use Module\Email\Contract\GuardOutcome;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\MailerInterface;

abstract readonly class EmailAbstract implements EmailInterface
{
    public function __construct(
        protected BlocklistInterface $blocklist,
        protected MockSampleFactory $samples,
        protected MailerInterface $mailer,
    ) {}

    public function send(array $context, bool $flush = true): int
    {
        $outcome = $this->mailer->send($this, $context, $flush);
        $this->assertNotError($outcome->guard);

        return $outcome->queued;
    }

    public function pushOnEnqueue(): bool
    {
        return true;
    }

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return null;
    }

    public function getAttachments(array $context): array
    {
        return [];
    }

    public function getOrigin(array $context): ?object
    {
        return null;
    }

    public function getGuardRules(): array
    {
        return [];
    }

    public function guardCheck(array $context): bool
    {
        foreach ($this->getGuardRules() as $rule) {
            $result = $rule->evaluate($context);
            $this->assertNotError($result);
            if ($result->outcome === GuardOutcome::Skip) {
                return false;
            }
        }

        return true;
    }

    private function assertNotError(GuardResult $result): void
    {
        if ($result->outcome !== GuardOutcome::Error) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            "Guard rule '%s' for email '%s' returned Error: %s",
            $result->ruleName,
            $this->getIdentifier(),
            $result->explanation,
        ));
    }
}
