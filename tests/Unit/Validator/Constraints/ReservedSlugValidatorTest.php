<?php declare(strict_types=1);

namespace Tests\Unit\Validator\Constraints;

use App\Cms\ReservedSlug\ReservedSlugRegistry;
use App\Entity\Cms;
use App\Validator\Constraints\ReservedSlug;
use App\Validator\Constraints\ReservedSlugValidator;
use Override;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ReservedSlugValidatorTest extends ConstraintValidatorTestCase
{
    private bool $reserved = false;

    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        $registry = $this->createStub(ReservedSlugRegistry::class);
        $registry->method('isReserved')->willReturnCallback(fn(): bool => $this->reserved);

        return new ReservedSlugValidator($registry);
    }

    public function testReservedSlugRaisesViolationOnSlugPath(): void
    {
        // Arrange
        $this->reserved = true;
        $cms = (new Cms())->setSlug('about');

        // Act
        $this->validator->validate($cms, new ReservedSlug());

        // Assert
        $this->buildViolation('admin_cms.validator_slug_reserved')->atPath('property.path.slug')->assertRaised();
    }

    public function testFreeSlugPasses(): void
    {
        // Arrange
        $this->reserved = false;
        $cms = (new Cms())->setSlug('summer-party');

        // Act
        $this->validator->validate($cms, new ReservedSlug());

        // Assert
        $this->assertNoViolation();
    }

    public function testNullSlugIsSkipped(): void
    {
        // Arrange
        $this->reserved = true;
        $cms = new Cms();

        // Act
        $this->validator->validate($cms, new ReservedSlug());

        // Assert
        $this->assertNoViolation();
    }
}
