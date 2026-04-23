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
        return $this->translator->trans('profile_social.activity_registration_email_confirmed');
    }

    protected function renderHtml(): string
    {
        return $this->translator->trans('profile_social.activity_registration_email_confirmed');
    }
}
