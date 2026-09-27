<?php declare(strict_types=1);

namespace Module\Trust\Tests\Stub;

use Module\Trust\Contract\ActionDescriptor;
use Module\Trust\Contract\ActionSourceInterface;
use Module\Trust\Contract\TrustAction;
use Override;

final class ActionSource implements ActionSourceInterface
{
    public const string HANDOVER = 'stub_handover';
    public const int POINTS = 5;

    public int $replays = 0;

    public string $revision = 'stub-1';

    /** @var list<ActionDescriptor> */
    public array $descriptors;

    /** @var list<TrustAction> */
    public array $actions = [];

    public function __construct()
    {
        $this->descriptors = [new ActionDescriptor(self::HANDOVER, 'trust_stub.action_handover', self::POINTS)];
    }

    #[Override]
    public function describeActions(string $context): iterable
    {
        return $context === ContextDescriber::CONTEXT ? $this->descriptors : [];
    }

    #[Override]
    public function replay(string $context): iterable
    {
        if ($context !== ContextDescriber::CONTEXT) {
            return [];
        }
        ++$this->replays;

        return $this->actions;
    }

    #[Override]
    public function getRevision(string $context): ?string
    {
        return $context === ContextDescriber::CONTEXT ? $this->revision : null;
    }
}
