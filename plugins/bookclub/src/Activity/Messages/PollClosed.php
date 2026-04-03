<?php declare(strict_types=1);

namespace Plugin\Bookclub\Activity\Messages;

use App\Activity\MessageAbstract;

class PollClosed extends MessageAbstract
{
    public const string TYPE = 'bookclub.poll_closed';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('poll_id');
        $this->ensureIsNumeric('poll_id');
        $this->ensureHasKey('event_id');
        $this->ensureIsNumeric('event_id');

        return $this;
    }

    protected function renderText(): string
    {
        return 'Closed a book poll';
    }

    protected function renderHtml(): string
    {
        return 'Closed a book poll';
    }
}
