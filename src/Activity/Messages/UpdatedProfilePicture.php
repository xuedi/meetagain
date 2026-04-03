<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class UpdatedProfilePicture extends MessageAbstract
{
    public const string TYPE = 'core.updated_profile_picture';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('old');
        $this->ensureIsNumeric('old');
        $this->ensureHasKey('new');
        $this->ensureIsNumeric('new');

        return $this;
    }

    protected function renderText(): string
    {
        return 'User changed their profile picture';
    }

    protected function renderHtml(): string
    {
        $box = '<div class="is-pulled-top-right">%s<i class="fa-solid fa-arrow-right"></i>%s</div>';
        $msgTemplate = 'User changed their profile picture' . $box;

        return sprintf(
            $msgTemplate,
            $this->imageRenderer->renderThumbnail($this->meta['old']),
            $this->imageRenderer->renderThumbnail($this->meta['new']),
        );
    }
}
