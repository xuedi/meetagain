<?php declare(strict_types=1);

namespace Plugin\Voting\Activity\Messages;

use App\Activity\MessageAbstract;

class PollClosed extends MessageAbstract
{
    public const string TYPE = 'voting.poll_closed';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('poll_id');
        $this->ensureIsNumeric('poll_id');

        return $this;
    }

    protected function renderText(): string
    {
        return 'Poll closed';
    }

    protected function renderHtml(): string
    {
        return 'Poll closed';
    }
}
