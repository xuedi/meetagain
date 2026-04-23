<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class Registered extends MessageAbstract
{
    public const string TYPE = 'core.registered';

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
        return $this->translator->trans('profile_social.activity_registered');
    }

    protected function renderHtml(): string
    {
        return $this->translator->trans('profile_social.activity_registered');
    }
}
