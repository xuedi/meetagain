<?php declare(strict_types=1);

namespace App\Entity\Activity;

use App\Entity\ActivityType;

interface MessageInterface
{
    public function getType(): ActivityType;

    public function render(bool $asHtml = false): string;

    public function validate(): void;
}
