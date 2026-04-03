<?php declare(strict_types=1);

namespace Plugin\Bookclub\Activity\Messages;

use App\Activity\MessageAbstract;

class BookApproved extends MessageAbstract
{
    public const string TYPE = 'bookclub.book_approved';

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
        return sprintf('Approved book: %s', $this->meta['book_title']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Approved book: <strong>%s</strong>', $this->escapeHtml($this->meta['book_title']));
    }
}
