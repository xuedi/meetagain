<?php declare(strict_types=1);

namespace App\Entity\Activity\Messages;

use App\Entity\Activity\MessageAbstract;
use App\Entity\ActivityType;

class SendMessage extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::SendMessage;
    }

    public function validate(): void
    {
        $this->ensureHasKey('user_id');
        $this->ensureIsNumeric('user_id');
    }

    protected function renderText(): string
    {
        $userId = $this->meta['user_id'];
        $msgTemplate = 'Send a message to: %s';
        return sprintf(
            $msgTemplate,
            $this->userNames[$userId],
        );
    }

    protected function renderHtml(): string
    {
        $userId = $this->meta['user_id'];
        $msgTemplate = 'Send a message to: %s'; // TODO: link
        return sprintf(
            $msgTemplate,
            $this->userNames[$userId],
        );
    }
}
