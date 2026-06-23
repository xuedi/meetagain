<?php declare(strict_types=1);

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_CLASS)]
final class ReservedSlug extends Constraint
{
    public string $message = 'admin_cms.validator_slug_reserved';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
