<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class RegistrationEmailResent extends MessageAbstract
{
    public const string TYPE = 'core.registration_email_resent';

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
        return $this->translator->trans('profile_social.activity_registration_email_resent');
    }

    protected function renderHtml(): string
    {
        return $this->translator->trans('profile_social.activity_registration_email_resent');
    }
}
