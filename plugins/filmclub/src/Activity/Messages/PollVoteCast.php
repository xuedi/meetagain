<?php declare(strict_types=1);

namespace Plugin\Filmclub\Activity\Messages;

use App\Activity\MessageAbstract;

class PollVoteCast extends MessageAbstract
{
    public const string TYPE = 'filmclub.poll_vote_cast';

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
        return 'Voted in poll';
    }

    protected function renderHtml(): string
    {
        return 'Voted in poll';
    }
}
