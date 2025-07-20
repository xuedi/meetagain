<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Service\Activity\MessageAbstract;
use App\Entity\ActivityType;

class FollowedUser extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::FollowedUser;
    }

    public function validate(): bool
    {
        $this->ensureHasKey('user_id');
        $this->ensureIsNumeric('user_id');

        return true;
    }

    protected function renderText(): string
    {
        $userId = $this->meta['user_id'];
        $msgTemplate = 'Started following: %s';
        return sprintf(
            $msgTemplate,
            $this->userNames[$userId],
        );
    }

    protected function renderHtml(): string
    {
        $userId = $this->meta['user_id'];
        $msgTemplate = 'Started following: %s'; // TODO: link
        return sprintf(
            $msgTemplate,
            $this->userNames[$userId],
        );
    }
}
