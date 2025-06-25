<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Service\Activity\MessageAbstract;
use App\Entity\ActivityType;

class Registered extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::Registered;
    }

    public function validate(): void
    {
        //
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
