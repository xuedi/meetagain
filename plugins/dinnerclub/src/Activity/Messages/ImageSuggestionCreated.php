<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Activity\Messages;

use App\Activity\MessageAbstract;

class ImageSuggestionCreated extends MessageAbstract
{
    public const string TYPE = 'dinnerclub.image_suggestion_created';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('dish_id');
        $this->ensureIsNumeric('dish_id');
        $this->ensureHasKey('dish_name');
        $this->ensureHasKey('suggestion_type');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Suggested %s for dish: %s', $this->meta['suggestion_type'], $this->meta['dish_name']);
    }

    protected function renderHtml(): string
    {
        return sprintf(
            'Suggested %s for dish: <strong>%s</strong>',
            $this->escapeHtml($this->meta['suggestion_type']),
            $this->escapeHtml($this->meta['dish_name']),
        );
    }
}
