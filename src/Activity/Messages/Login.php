<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class Login extends MessageAbstract
{
    public const string TYPE = 'core.login';

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
        return $this->translator->trans('profile_social.activity_login');
    }

    protected function renderHtml(): string
    {
        return $this->translator->trans('profile_social.activity_login');
    }
}
