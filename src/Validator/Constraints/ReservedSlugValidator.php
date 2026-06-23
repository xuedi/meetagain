<?php declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Cms\ReservedSlug\ReservedSlugRegistry;
use App\Entity\Cms;
use Override;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ReservedSlugValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ReservedSlugRegistry $registry,
    ) {}

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ReservedSlug) {
            throw new UnexpectedValueException($constraint, ReservedSlug::class);
        }

        if (!$value instanceof Cms) {
            throw new UnexpectedValueException($value, Cms::class);
        }

        $slug = $value->getSlug();
        if ($slug === null || $slug === '') {
            return;
        }

        if (!$this->registry->isReserved($slug, $value->getId())) {
            return;
        }

        $this->context->buildViolation($constraint->message)->atPath('slug')->addViolation();
    }
}
