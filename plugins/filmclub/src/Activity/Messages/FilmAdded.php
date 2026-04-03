<?php declare(strict_types=1);

namespace Plugin\Filmclub\Activity\Messages;

use App\Activity\MessageAbstract;

class FilmAdded extends MessageAbstract
{
    public const string TYPE = 'filmclub.film_added';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('film_id');
        $this->ensureIsNumeric('film_id');
        $this->ensureHasKey('film_title');

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Added film: %s', $this->meta['film_title']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Added film: <strong>%s</strong>', $this->escapeHtml($this->meta['film_title']));
    }
}
