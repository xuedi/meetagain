<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageAbstract;

class PasswordResetRequest extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::PasswordResetRequest;
    }

    public function validate(): MessageAbstract
    {
        return $this;
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
