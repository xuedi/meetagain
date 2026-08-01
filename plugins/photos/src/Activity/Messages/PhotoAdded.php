<?php declare(strict_types=1);

namespace Plugin\Photos\Activity\Messages;

use App\Activity\MessageAbstract;

class PhotoAdded extends MessageAbstract
{
    public const string TYPE = 'photos.photo_added';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('photo_id');
        $this->ensureIsNumeric('photo_id');
        $this->ensureHasKey('photo_title');

        return $this;
    }

    protected function renderText(): string
    {
        return $this->translator->trans('photos_photo.activity_photo_added', ['%title%' => $this->title()]);
    }

    protected function renderHtml(): string
    {
        $link = sprintf(
            '<a href="%s">%s</a>',
            $this->router->generate('app_plugin_photos_photo_show', ['id' => $this->meta['photo_id']]),
            $this->escapeHtml($this->title()),
        );

        return $this->translator->trans('photos_photo.activity_photo_added', ['%title%' => $link]);
    }

    private function title(): string
    {
        $title = trim((string) $this->meta['photo_title']);

        return $title === '' ? $this->translator->trans('photos.untitled') : $title;
    }
}
