<?php declare(strict_types=1);

namespace Plugin\Karaoke\Activity\Messages;

use App\Activity\MessageAbstract;

class SongAdded extends MessageAbstract
{
    public const string TYPE = 'karaoke.song_added';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('song_id');
        $this->ensureIsNumeric('song_id');
        $this->ensureHasKey('song_title');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('karaoke.activity_song_added', ['%title%' => $this->meta['song_title']]);
    }

    protected function renderHtml(): string
    {
        return $this->translator->trans('karaoke.activity_song_added', [
            '%title%' => '<strong>' . $this->escapeHtml((string) $this->meta['song_title']) . '</strong>',
        ]);
    }
}
