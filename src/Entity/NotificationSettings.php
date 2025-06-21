<?php declare(strict_types=1);

namespace App\Entity;

use Exception;
use JsonSerializable;

class NotificationSettings implements JsonSerializable
{
    private bool $announcements {
        get => $this->announcements;
        set => $this->announcements = $value;
    }

    private bool $followingUpdates {
        get => $this->followingUpdates;
        set => $this->followingUpdates = $value;
    }

    public static function fromJson(?array $notificationSettings): self
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
    }

    public function jsonSerialize(): array
    {
        return [
            'announcements' => $this->announcements,
            'followingUpdates' => $this->followingUpdates,
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
            default:
                throw new Exception(sprintf("Invalid type: '%s'", $type));
        }
        return $this;
    }

}
