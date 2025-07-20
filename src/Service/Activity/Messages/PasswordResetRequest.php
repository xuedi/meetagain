<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Service\Activity\MessageAbstract;
use App\Entity\ActivityType;

class PasswordResetRequest extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::PasswordResetRequest;
    }

    public function validate(): bool
    {
        return true;
    }

    protected function renderText(): string
    {
        return 'Requested password reset';
    }

    protected function renderHtml(): string
    {
        return 'Requested password reset';
    }
}
