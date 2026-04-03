<?php declare(strict_types=1);

namespace Plugin\Bookclub\Activity\Messages;

use App\Activity\MessageAbstract;

class SuggestionWithdrawn extends MessageAbstract
{
    public const string TYPE = 'bookclub.suggestion_withdrawn';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('book_id');
        $this->ensureIsNumeric('book_id');
        $this->ensureHasKey('book_title');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Withdrew book suggestion: %s', $this->meta['book_title']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Withdrew book suggestion: <strong>%s</strong>', $this->escapeHtml($this->meta['book_title']));
    }
}
