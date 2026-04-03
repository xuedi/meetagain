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
        return 'User registered';
    }

    protected function renderHtml(): string
    {
        return 'User registered';
    }
}
