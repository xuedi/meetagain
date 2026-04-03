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
        return 'User logged in';
    }

    protected function renderHtml(): string
    {
        return 'User logged in';
    }
}
