<?php declare(strict_types=1);

namespace Plugin\Filmclub\Activity\Messages;

use App\Activity\MessageAbstract;

class PollCreated extends MessageAbstract
{
    public const string TYPE = 'filmclub.poll_created';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('poll_id');
        $this->ensureIsNumeric('poll_id');
        $this->ensureHasKey('event_id');

        return $this;
    }

    protected function renderText(): string
    {
        return 'Poll created';
    }

    protected function renderHtml(): string
    {
        return 'Poll created';
    }
}
