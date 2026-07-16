<?php declare(strict_types=1);

namespace Plugin\Wishlist\Activity\Messages;

use App\Activity\MessageAbstract;

class WishlistAdded extends MessageAbstract
{
    public const string TYPE = 'wishlist.item_added';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('item_type');
        $this->ensureHasKey('item_id');
        $this->ensureIsNumeric('item_id');

        return $this;
    }

    protected function renderText(): string
    {
        return 'Added to wishlist';
    }

    protected function renderHtml(): string
    {
        return 'Added to wishlist';
    }
}
