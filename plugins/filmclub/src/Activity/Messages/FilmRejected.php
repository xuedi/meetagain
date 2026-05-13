<?php declare(strict_types=1);

namespace Plugin\Filmclub\Activity\Messages;

use App\Activity\MessageAbstract;

class FilmRejected extends MessageAbstract
{
    public const string TYPE = 'filmclub.film_rejected';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('film_title');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Rejected film: %s', $this->meta['film_title']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Rejected film: <strong>%s</strong>', $this->escapeHtml($this->meta['film_title']));
    }
}
