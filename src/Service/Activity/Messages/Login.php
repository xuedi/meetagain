<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Service\Activity\MessageAbstract;
use App\Entity\ActivityType;

class Login extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::Login;
    }

    public function validate(): bool
    {
        return true;
    }

    protected function renderText(): string
    {
        return 'User logged in';
    }

    protected function renderHtml(): string
    {
        return 'User logged in';
    }
}
