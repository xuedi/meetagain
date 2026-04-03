<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Activity\Messages;

use App\Activity\MessageAbstract;

class SuggestionCreated extends MessageAbstract
{
    public const string TYPE = 'dinnerclub.suggestion_created';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('dish_id');
        $this->ensureIsNumeric('dish_id');
        $this->ensureHasKey('dish_name');
        $this->ensureHasKey('field');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Suggested %s translation for dish: %s', $this->meta['field'], $this->meta['dish_name']);
    }

    protected function renderHtml(): string
    {
        return sprintf(
            'Suggested <em>%s</em> translation for dish: <strong>%s</strong>',
            $this->escapeHtml($this->meta['field']),
            $this->escapeHtml($this->meta['dish_name']),
        );
    }
}
