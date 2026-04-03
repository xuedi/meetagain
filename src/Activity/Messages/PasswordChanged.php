<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class PasswordChanged extends MessageAbstract
{
    public const string TYPE = 'core.password_changed';

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
        return 'Changed password';
    }

    protected function renderHtml(): string
    {
        return 'Changed password';
    }
}
