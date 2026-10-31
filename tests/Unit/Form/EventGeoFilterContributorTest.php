<?php declare(strict_types=1);

namespace Tests\Unit\Form;

use App\Form\EventGeoFilterContributor;
use App\Repository\LocationRepository;
use App\Service\Config\ConfigService;
use Module\Geo\Contract\CityView;
use Module\Geo\Contract\CountryView;
use Module\Geo\Contract\DirectoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EventGeoFilterContributorTest extends TestCase
{
    private const int BERLIN = 1;
    private const int MUNICH = 2;
    private const int SPRINGFIELD = 3;

    public function testTheSwitchOffAddsNoField(): void
    {
        // Arrange
        $builder = $this->builder();

        // Act
        $this->contributor(enabled: false)->addFields($builder);

        // Assert
        self::assertFalse($builder->has('country'));
    }

    public function testTheCountrySelectListsOnlyCountriesLocationsLieIn(): void
    {
        // Arrange
        $builder = $this->builder();

        // Act
        $this->contributor()->addFields($builder);

        // Assert
        self::assertSame(['events.filter_country_all' => '', 'Germany' => 'DE', 'United States' => 'US'], $builder->get('country')->getOption('choices'));
        self::assertFalse($builder->has('city'));
    }

    public function testAChosenCountryAddsItsCities(): void
    {
        // Arrange
        $builder = $this->builder();

        // Act
        $this->contributor(query: ['country' => 'de'])->addFields($builder);

        // Assert
        self::assertSame(['events.filter_city_all' => '', 'Berlin' => 'berlin', 'Munich' => 'munich'], $builder->get('city')->getOption('choices'));
    }

    public function testACountryFiltersToTheCitiesOfItsLocations(): void
    {
        // Act
        $cityIds = $this->contributor()->cityIds('de', null);

        // Assert
        self::assertSame([self::BERLIN, self::MUNICH], $cityIds);
    }

    public function testACitySlugFiltersToThatCity(): void
    {
        // Act
        $cityIds = $this->contributor()->cityIds('DE', 'berlin');

        // Assert
        self::assertSame([self::BERLIN], $cityIds);
    }

    public function testAnUnknownCityFiltersEverythingOut(): void
    {
        // Act
        $cityIds = $this->contributor()->cityIds('DE', 'atlantis');

        // Assert
        self::assertSame([], $cityIds);
    }

    public function testNoCountryMeansNoFilter(): void
    {
        // Act
        $cityIds = $this->contributor()->cityIds('', 'berlin');

        // Assert
        self::assertNull($cityIds);
    }

    /**
     * @param array<string, string> $query
     */
    private function contributor(bool $enabled = true, array $query = []): EventGeoFilterContributor
    {
        $config = $this->createStub(ConfigService::class);
        $config->method('isShowEventGeo')->willReturn($enabled);
        $locations = $this->createStub(LocationRepository::class);
        $locations->method('findCityIds')->willReturn([self::BERLIN, self::MUNICH, self::SPRINGFIELD]);
        $requests = new RequestStack();
        $requests->push(new Request($query));
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new EventGeoFilterContributor($config, $this->directory(), $locations, $requests, $translator);
    }

    private function directory(): DirectoryInterface
    {
        $cities = [
            self::BERLIN => $this->city(self::BERLIN, 'berlin', 'DE', 'Berlin'),
            self::MUNICH => $this->city(self::MUNICH, 'munich', 'DE', 'Munich'),
            self::SPRINGFIELD => $this->city(self::SPRINGFIELD, 'springfield', 'US', 'Springfield'),
        ];
        $directory = $this->createStub(DirectoryInterface::class);
        $directory->method('hasCities')->willReturn(true);
        $directory->method('findCountryCodes')->willReturn([self::BERLIN => 'DE', self::MUNICH => 'DE', self::SPRINGFIELD => 'US']);
        $directory
            ->method('listCountries')
            ->willReturn([
                new CountryView(10, 2921044, 'CN', 'China', 1, null, null),
                new CountryView(11, 2921044, 'DE', 'Germany', 1, null, null),
                new CountryView(12, 6252001, 'US', 'United States', 1, null, null),
            ]);
        $directory->method('findCities')->willReturnCallback(static fn(array $ids): array => array_values(array_intersect_key($cities, array_flip($ids))));
        $directory
            ->method('findCityBySlug')
            ->willReturnCallback(static fn(string $iso2, string $slug): ?CityView => array_find(
                $cities,
                static fn(CityView $city): bool => $city->countryIso2 === $iso2 && $city->slug === $slug,
            ));

        return $directory;
    }

    private function city(int $id, string $slug, string $iso2, string $name): CityView
    {
        return new CityView($id, $id, $slug, $iso2, $name, $iso2, 0.0, 0.0, 'UTC', 1);
    }

    private function builder(): FormBuilderInterface
    {
        return Forms::createFormFactory()->createNamedBuilder('', FormType::class);
    }
}
