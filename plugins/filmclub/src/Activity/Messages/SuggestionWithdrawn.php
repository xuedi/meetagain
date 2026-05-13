<?php declare(strict_types=1);

namespace Plugin\Filmclub\Activity\Messages;

use App\Activity\MessageAbstract;

class SuggestionWithdrawn extends MessageAbstract
{
    public const string TYPE = 'filmclub.suggestion_withdrawn';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('film_title');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Withdrew suggestion: %s', $this->meta['film_title']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Withdrew suggestion: <strong>%s</strong>', $this->escapeHtml($this->meta['film_title']));
    }
}
