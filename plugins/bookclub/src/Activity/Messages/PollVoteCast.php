<?php declare(strict_types=1);

namespace Plugin\Bookclub\Activity\Messages;

use App\Activity\MessageAbstract;

class PollVoteCast extends MessageAbstract
{
    public const string TYPE = 'bookclub.poll_vote_cast';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('poll_id');
        $this->ensureIsNumeric('poll_id');
        $this->ensureHasKey('book_id');
        $this->ensureIsNumeric('book_id');
        $this->ensureHasKey('book_title');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Voted for book: %s', $this->meta['book_title']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Voted for book: <strong>%s</strong>', $this->escapeHtml($this->meta['book_title']));
    }
}
