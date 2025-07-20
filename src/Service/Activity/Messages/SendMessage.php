<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Service\Activity\MessageAbstract;
use App\Entity\ActivityType;

class SendMessage extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::SendMessage;
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
