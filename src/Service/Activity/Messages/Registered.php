<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Entity\ActivityType;
use App\Service\Activity\MessageAbstract;

class Registered extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::Registered;
    }

    public function validate(): bool
    {
        return true;
    }

    protected function renderText(): string
    {
        return 'User registered';
    }

    protected function renderHtml(): string
    {
        return 'User registered';
    }
}
