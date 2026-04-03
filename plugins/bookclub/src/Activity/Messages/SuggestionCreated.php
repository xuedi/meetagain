<?php declare(strict_types=1);

namespace Plugin\Bookclub\Activity\Messages;

use App\Activity\MessageAbstract;

class SuggestionCreated extends MessageAbstract
{
    public const string TYPE = 'bookclub.suggestion_created';

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
        return sprintf('Suggested book: %s', $this->meta['book_title']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Suggested book: <strong>%s</strong>', $this->escapeHtml($this->meta['book_title']));
    }
}
