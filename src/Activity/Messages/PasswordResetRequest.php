<?php declare(strict_types=1);

namespace App\Activity\Messages;

use App\Activity\MessageAbstract;

class PasswordResetRequest extends MessageAbstract
{
    public const string TYPE = 'core.password_reset_request';

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
        return 'Requested password reset';
    }

    protected function renderHtml(): string
    {
        return 'Requested password reset';
    }
}
