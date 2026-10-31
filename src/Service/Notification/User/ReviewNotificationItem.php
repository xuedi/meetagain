<?php declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\Image;

readonly class ReviewNotificationItem
{
    /**
     * @param list<array{label: string, value: string, before?: string}> $fields
     */
    public function __construct(
        public string $id,
        public string $description,
        public bool $canDeny = true,
        public ?string $icon = null,
        public ?string $longDescription = null,
        public ?string $detailUrl = null,
        public ?string $approveLabelKey = null,
        public array $fields = [],
        public ?Image $image = null,
    ) {}
}
