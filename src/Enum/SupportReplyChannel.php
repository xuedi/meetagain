<?php declare(strict_types=1);

namespace App\Enum;

enum SupportReplyChannel: string
{
    case Email = 'email';
    case Message = 'message';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'admin_support.channel_email',
            self::Message => 'admin_support.channel_message',
        };
    }
}
