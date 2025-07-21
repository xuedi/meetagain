<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Service\Activity\MessageAbstract;
use App\Entity\ActivityType;

class UpdatedProfilePicture extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::UpdatedProfilePicture;
    }

    public function validate(): bool
    {
        return true;
    }

    protected function renderText(): string
    {
        return 'User changed their profile picture';
    }

    protected function renderHtml(): string
    {
        return 'User changed their profile picture';
    }
}
