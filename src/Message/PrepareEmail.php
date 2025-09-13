<?php declare(strict_types=1);

namespace App\Message;

use App\Entity\EmailType\EmailInterface;

readonly class PrepareEmail
{
    public static function byType(EmailInterface $type): self
    {
        return new self($type);
    }

    private function __construct(private EmailInterface $type)
    {
    }

    public function getType(): EmailInterface
    {
        return $this->type;
    }
}
