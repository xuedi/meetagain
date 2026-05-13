<?php declare(strict_types=1);

namespace Plugin\Filmclub\Activity\Messages;

use App\Activity\MessageAbstract;

class NoteRevealed extends MessageAbstract
{
    public const string TYPE = 'filmclub.note_revealed';

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
        return sprintf('Revealed review for: %s', $this->meta['film_title']);
    }

    protected function renderHtml(): string
    {
        return sprintf('Revealed review for: <strong>%s</strong>', $this->escapeHtml($this->meta['film_title']));
    }
}
