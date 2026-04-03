<?php declare(strict_types=1);

namespace Plugin\Bookclub\Activity\Messages;

use App\Activity\MessageAbstract;

class PollCreated extends MessageAbstract
{
    public const string TYPE = 'bookclub.poll_created';

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
        return 'Created a book poll';
    }

    protected function renderHtml(): string
    {
        return 'Created a book poll';
    }
}
