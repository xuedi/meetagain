<?php declare(strict_types=1);

namespace Plugin\Filmclub\Activity\Messages;

use App\Activity\MessageAbstract;

class VoteCast extends MessageAbstract
{
    public const string TYPE = 'filmclub.vote_cast';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('film_id');
        $this->ensureIsNumeric('film_id');
        $this->ensureHasKey('film_title');
        $this->ensureHasKey('event_id');
        $this->ensureIsNumeric('event_id');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Voted for film: %s', $this->meta['film_title']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Voted for film: <strong>%s</strong>', $this->escapeHtml($this->meta['film_title']));
    }
}
