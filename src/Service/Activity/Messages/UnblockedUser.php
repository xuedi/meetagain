<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageAbstract;

class UnblockedUser extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::UnblockedUser;
    }

    public function validate(): MessageAbstract
    {
        $this->ensureHasKey('user_id');
        $this->ensureIsNumeric('user_id');

        return $this;
    }

    protected function renderText(): string
    {
        $userId = $this->meta['user_id'];
        $userName = $this->userNames[$userId] ?? '[deleted]';

        return sprintf('Unblocked user: %s', $userName);
    }

    protected function renderHtml(): string
    {
        return $this->renderText();
    }
}
