<?php declare(strict_types=1);

namespace App\Entity\Activity\Messages;

use App\Entity\Activity\MessageAbstract;
use App\Entity\ActivityType;

class RegistrationEmailConfirmed extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::RegistrationEmailConfirmed;
    }

    public function validate(): void
    {
        //
    }

    protected function renderText(): string
    {
        return 'User confirmed Email';
    }

    protected function renderHtml(): string
    {
        return 'User confirmed Email';
    }
}
