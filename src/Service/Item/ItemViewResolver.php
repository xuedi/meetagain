<?php declare(strict_types=1);

namespace App\Service\Item;

use App\Enum\ItemViewType;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class ItemViewResolver
{
    private const string SESSION_PREFIX = 'item.viewMode.';

    public function __construct(
        private RequestStack $requestStack,
    ) {}

    public function get(string $itemType, ItemViewType $default = ItemViewType::List): ItemViewType
    {
        $stored = $this->requestStack->getSession()->get(self::SESSION_PREFIX . $itemType);

        return is_string($stored) ? ItemViewType::tryFrom($stored) ?? $default : $default;
    }

    public function set(string $itemType, ItemViewType $type): void
    {
        $this->requestStack->getSession()->set(self::SESSION_PREFIX . $itemType, $type->value);
    }
}
