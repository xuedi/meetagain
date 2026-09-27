<?php declare(strict_types=1);

namespace Module\Trust\Tests\Stub;

use Module\Trust\Contract\ContextDescriberInterface;
use Module\Trust\Contract\ContextDescriptor;
use Override;

final readonly class ContextDescriber implements ContextDescriberInterface
{
    public const string CONTEXT = 'stub-context';

    #[Override]
    public function describe(string $context): ?ContextDescriptor
    {
        return $context === self::CONTEXT ? $this->descriptor() : null;
    }

    #[Override]
    public function describeAll(): iterable
    {
        yield $this->descriptor();
    }

    private function descriptor(): ContextDescriptor
    {
        return new ContextDescriptor(self::CONTEXT, 'Stub context', '/');
    }
}
