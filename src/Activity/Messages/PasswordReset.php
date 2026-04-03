<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class PasswordReset extends MessageAbstract
{
    public const string TYPE = 'core.password_reset';

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
        return 'Retested password';
    }

    protected function renderHtml(): string
    {
        return 'Retested password';
    }
}
