<?php declare(strict_types=1);

namespace Module\Suggestion\Tests\Stub;

use Override;

final class InactivePluginTarget extends Target
{
    public const string TYPE = 'stub_inactive';

    #[Override]
    public function getPluginKey(): string
    {
        return 'no_such_plugin';
    }

    #[Override]
    public function getTargetType(): string
    {
        return self::TYPE;
    }
}
