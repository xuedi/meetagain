<?php declare(strict_types=1);

namespace App\Moderation;

use App\Entity\User;

final readonly class SubjectSnapshot
{
    public function __construct(
        public string $label,
        public ?string $excerpt,
        public ?User $author,
    ) {}
}
