<?php declare(strict_types=1);

namespace Plugin\Filmclub\Activity\Messages;

use App\Activity\MessageAbstract;

class FilmSelectedForEvent extends MessageAbstract
{
    public const string TYPE = 'filmclub.film_selected_for_event';

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

        return $this;
    }

    protected function renderText(): string
    {
        return sprintf('Selected film for event: %s', $this->meta['film_title']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Selected film for event: <strong>%s</strong>', $this->escapeHtml($this->meta['film_title']));
    }
}
