<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageAbstract;

class UpdatedProfilePicture extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::UpdatedProfilePicture;
    }

    public function validate(): bool
    {
        $this->ensureHasKey('old');
        $this->ensureIsNumeric('old');
        $this->ensureHasKey('new');
        $this->ensureIsNumeric('new');

        return true;
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
            $this->imageService->imageTemplateById($this->meta['old']),
            $this->imageService->imageTemplateById($this->meta['new']),
        );
    }
}
