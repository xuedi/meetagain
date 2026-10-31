<?php declare(strict_types=1);

namespace Tests\Unit\Form;

use App\Entity\Location;
use App\Form\CityType;
use App\Form\LocationType;
use App\Item\Location\CityDefaultProviderInterface;
use Module\Geo\Contract\CityView;
use Module\Geo\Contract\DirectoryInterface;
use Override;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validation;

#[AllowMockObjectsWithoutExpectations]
final class LocationTypeTest extends TypeTestCase
{
    private const int GROUP_CITY = 7;
    private const int OWN_CITY = 9;

    private bool $hasCities = true;

    public function testAnEmptyDirectoryLeavesTheCityFieldOut(): void
    {
        // Arrange
        $this->hasCities = false;

        // Act
        $form = $this->factory->create(LocationType::class, new Location());

        // Assert
        self::assertFalse($form->has('cityId'));
    }

    public function testANewLocationStartsWithTheProvidedCity(): void
    {
        // Act
        $form = $this->factory->create(LocationType::class, new Location());

        // Assert
        self::assertSame(self::GROUP_CITY, $form->get('cityId')->getData());
    }

    public function testALocationThatNamesACityKeepsIt(): void
    {
        // Act
        $form = $this->factory->create(LocationType::class, new Location()->setCityId(self::OWN_CITY));

        // Assert
        self::assertSame(self::OWN_CITY, $form->get('cityId')->getData());
    }

    #[Override]
    protected function getExtensions(): array
    {
        $directory = self::createStub(DirectoryInterface::class);
        $directory->method('hasCities')->willReturnCallback(fn(): bool => $this->hasCities);
        $directory->method('listCountries')->willReturn([]);
        $directory
            ->method('findCity')
            ->willReturnCallback(static fn(int $id): CityView => new CityView($id, $id, 'city', 'DE', 'City', 'Germany', 0.0, 0.0, 'Europe/Berlin', 1));
        $provider = self::createStub(CityDefaultProviderInterface::class);
        $provider->method('getDefaultCityId')->willReturn(self::GROUP_CITY);

        return [
            new PreloadedExtension([
                new LocationType($directory, [$provider]),
                new CityType($directory, new RequestStack(), self::createStub(UrlGeneratorInterface::class)),
            ], []),
            new ValidatorExtension(Validation::createValidator()),
        ];
    }
}
