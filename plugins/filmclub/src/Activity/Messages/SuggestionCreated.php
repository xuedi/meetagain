<?php declare(strict_types=1);

namespace Plugin\Filmclub\Activity\Messages;

use App\Activity\MessageAbstract;

class SuggestionCreated extends MessageAbstract
{
    public const string TYPE = 'filmclub.suggestion_created';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('film_id');
        $this->ensureIsNumeric('film_id');
        $this->ensureHasKey('film_title');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Suggested film: %s', $this->meta['film_title']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Suggested film: <strong>%s</strong>', $this->escapeHtml($this->meta['film_title']));
    }
}
