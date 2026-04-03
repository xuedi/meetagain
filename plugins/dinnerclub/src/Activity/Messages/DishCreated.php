<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Activity\Messages;

use App\Activity\MessageAbstract;

class DishCreated extends MessageAbstract
{
    public const string TYPE = 'dinnerclub.dish_created';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('dish_id');
        $this->ensureIsNumeric('dish_id');
        $this->ensureHasKey('dish_name');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Added dish: %s', $this->meta['dish_name']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Added dish: <strong>%s</strong>', $this->escapeHtml($this->meta['dish_name']));
    }
}
