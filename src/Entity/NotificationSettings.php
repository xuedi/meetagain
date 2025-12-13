<?php declare(strict_types=1);

namespace App\Entity;

use Exception;
use JsonSerializable;

class NotificationSettings implements JsonSerializable
{
    public bool $announcements;

    public bool $followingUpdates;

    public bool $receivedMessage;

    public static function fromJson(null|array $notificationSettings): self
    {
        if ($notificationSettings === null) {
            return new self([]);
        }
        return new self($notificationSettings);
    }

    public function __construct(array $data)
    {
        $this->announcements = $data['announcements'] ?? true;
        $this->followingUpdates = $data['followingUpdates'] ?? true;
        $this->receivedMessage = $data['receivedMessage'] ?? true;
    }

    public function jsonSerialize(): array
    {
        return [
            'announcements' => $this->announcements,
            'followingUpdates' => $this->followingUpdates,
            'receivedMessage' => $this->receivedMessage,
        ];
    }

    public function getList(): array
    {
        return [
            [
                'key' => 'announcements',
                'value' => $this->announcements,
                'label' => '--> General meetup announcements',
            ],
            [
                'key' => 'followingUpdates',
                'value' => $this->followingUpdates,
                'label' => '--> Updates from people i follow',
            ],
            [
                'key' => 'receivedMessage',
                'value' => $this->receivedMessage,
                'label' => '--> When i received a message',
            ],
        ];
    }

    public function toggle(string $type): self
    {
        switch ($type) {
            case 'announcements':
                $this->announcements = !$this->announcements;
                break;
            case 'followingUpdates':
                $this->followingUpdates = !$this->followingUpdates;
                break;
            case 'receivedMessage':
                $this->receivedMessage = !$this->receivedMessage;
                break;
            default:
                throw new Exception(sprintf("Invalid type: '%s'", $type));
        }
        return $this;
    }

    public function isActive(string $type): bool
    {
        return match ($type) {
            'announcements' => $this->announcements,
            'followingUpdates' => $this->followingUpdates,
            'receivedMessage' => $this->receivedMessage,
        };
    }
}
