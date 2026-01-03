<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageAbstract;

class PasswordChanged extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::PasswordChanged;
    }

    public function validate(): MessageAbstract
    {
        return $this;
    }

    protected function renderText(): string
    {
        return 'Changed password';
    }

    protected function renderHtml(): string
    {
        return 'Changed password';
    }
}
