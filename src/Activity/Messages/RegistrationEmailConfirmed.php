<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class RegistrationEmailConfirmed extends MessageAbstract
{
    public const string TYPE = 'core.registration_email_confirmed';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function validate(): MessageAbstract
    {
        return $this;
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
