<?php declare(strict_types=1);

namespace App\Service\Item;

use App\Enum\ItemViewType;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads and writes the current list view mode in the session, keyed per item type so each
 * list remembers its own mode independently. Session-only UI preference (see
 * architecture/security/get-routes.md exception 2).
 */
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
