<?php declare(strict_types=1);

namespace Module\Circulation\Tests\Stub;

use Module\Circulation\Contract\ParticipationProviderInterface;
use Override;

final class ParticipationProvider implements ParticipationProviderInterface
{
    public const string ITEM_TYPE = 'stub_item';

    public bool $enabled = true;

    #[Override]
    public function isEnabled(string $itemType): ?bool
    {
        return $itemType === self::ITEM_TYPE ? $this->enabled : null;
    }
}
