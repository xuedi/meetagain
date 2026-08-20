<?php declare(strict_types=1);

namespace App\Enum;

enum SupportChannel: string
{
    case Thread = 'thread';
    case Message = 'message';

    public function label(): string
    {
        return match ($this) {
            self::Thread => 'admin_support.channel_thread',
            self::Message => 'admin_support.channel_message',
        };
    }
}
