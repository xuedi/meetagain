<?php declare(strict_types=1);

namespace App\Service\Activity\Messages;

use App\Enum\ActivityType;
use App\Service\Activity\MessageAbstract;

class RegistrationEmailResent extends MessageAbstract
{
    public function getType(): ActivityType
    {
        return ActivityType::RegistrationEmailResent;
    }

    public function validate(): MessageAbstract
    {
        return $this;
    }

    protected function renderText(): string
    {
        return 'Registration email resent by admin';
    }

    protected function renderHtml(): string
    {
        return 'Registration email resent by admin';
    }
}
