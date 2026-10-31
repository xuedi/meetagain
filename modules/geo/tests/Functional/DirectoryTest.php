<?php declare(strict_types=1);

namespace Module\Geo\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Module\Geo\Contract\CityView;
use Module\Geo\Contract\CountryView;
use Module\Geo\Contract\DirectoryInterface;
use Module\Geo\Internal\Entity\City;
use Module\Geo\Internal\Entity\Country;
use Module\Geo\Internal\Slugs;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DirectoryTest extends KernelTestCase
{
    private DirectoryInterface $directory;

    private Country $germany;

    private Country $usa;

    private City $berlin;

    private City $berlinNewHampshire;

    private City $munich;

    private City $springfieldIllinois;

    private City $springfieldMissouri;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->directory = $container->get(DirectoryInterface::class);
        $places = new Places($container->get(EntityManagerInterface::class), $container->get(Slugs::class));

        $this->germany = $places->country('DE', 'DEU', 2921044, 'Germany', 82927922, ['en' => 'Germany', 'de' => 'Deutschland', 'zh' => '德国']);
        $this->usa = $places->country('US', 'USA', 6252001, 'United States', 327167434, ['de' => 'Vereinigte Staaten']);
        $this->berlin = $places->city($this->germany, 2950159, 'Berlin', 3426354, ['en' => 'Berlin', 'es' => 'Berlín', 'zh' => '柏林']);
        $this->munich = $places->city($this->germany, 2867714, 'Munich', 1260391, ['en' => 'Munich', 'de' => 'München', 'fr' => 'Munich']);
        $this->berlinNewHampshire = $places->city($this->usa, 5083330, 'Berlin', 10051);
        $this->springfieldIllinois = $places->city($this->usa, 4250542, 'Springfield', 116565);
        $this->springfieldMissouri = $places->city($this->usa, 4409896, 'Springfield', 166810);
    }

    public function testTheDirectoryKnowsItHoldsCities(): void
    {
        // Act
        $hasCities = $this->directory->hasCities();

        // Assert
        self::assertTrue($hasCities);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideNameFallbacks(): iterable
    {
        yield 'the requested locale wins' => ['zh', '柏林', '德国'];
        yield 'english stands in for a missing locale' => ['fr', 'Berlin', 'Germany'];
        yield 'the requested locale wins over english' => ['es', 'Berlín', 'Germany'];
    }

    #[DataProvider('provideNameFallbacks')]
    public function testANameFallsBackToEnglish(string $locale, string $cityName, string $countryName): void
    {
        // Act
        $view = $this->directory->findCity((int) $this->berlin->getId(), $locale);

        // Assert
        self::assertSame([$cityName, $countryName], [$view?->name, $view?->countryName]);
    }

    public function testCitiesAreCountedPerCountry(): void
    {
        // Act
        $counts = $this->directory->countCitiesPerCountry();

        // Assert
        self::assertSame(['DE' => 2, 'US' => 3], $counts);
    }

    public function testANameWithoutAnyTranslationFallsBackToTheAsciiName(): void
    {
        // Act
        $city = $this->directory->findCity((int) $this->berlinNewHampshire->getId(), 'zh');
        $country = $this->directory->findCity((int) $this->springfieldIllinois->getId(), 'fr');

        // Assert
        self::assertSame(['Berlin', 'United States'], [$city?->name, $country?->countryName]);
    }

    public function testAViewCarriesTheCityFacts(): void
    {
        // Act
        $view = $this->directory->findCity((int) $this->munich->getId(), 'de');

        // Assert
        self::assertEquals(
            new CityView(
                id: (int) $this->munich->getId(),
                geonameId: 2867714,
                slug: 'munich',
                countryIso2: 'DE',
                name: 'München',
                countryName: 'Deutschland',
                latitude: 52.52437,
                longitude: 13.41053,
                timezone: 'Europe/Berlin',
                population: 1260391,
            ),
            $view,
        );
    }

    public function testFindingManyCitiesLeavesUnknownIdsOut(): void
    {
        // Act
        $views = $this->directory->findCities([(int) $this->berlin->getId(), 999999999, (int) $this->munich->getId()], 'en');

        // Assert
        self::assertSame([(int) $this->berlin->getId(), (int) $this->munich->getId()], array_keys($views));
    }

    public function testACityIsFoundBySlugWithinItsCountry(): void
    {
        // Act
        $found = $this->directory->findCityBySlug('de', 'berlin', 'en');
        $elsewhere = $this->directory->findCityBySlug('US', 'berlin', 'en');

        // Assert
        self::assertSame([$this->berlin->getId(), $this->berlinNewHampshire->getId()], [$found?->id, $elsewhere?->id]);
    }

    public function testACityIsFoundByGeonameId(): void
    {
        // Act
        $found = $this->directory->findCityByGeonameId(2867714, 'en');

        // Assert
        self::assertSame($this->munich->getId(), $found?->id);
    }

    public function testASecondSameNamedCityGetsANumberedSlug(): void
    {
        // Act
        $first = $this->directory->findCity((int) $this->springfieldIllinois->getId(), 'en');
        $second = $this->directory->findCity((int) $this->springfieldMissouri->getId(), 'en');

        // Assert
        self::assertSame(['springfield', 'springfield-2'], [$first?->slug, $second?->slug]);
    }

    public function testTheNameSlugKeepsThePlainNameOfANumberedCity(): void
    {
        // Act
        $place = $this->directory->findPlaceNamed('springfield', 'en');

        // Assert
        self::assertSame($this->springfieldMissouri->getId(), $place instanceof CityView ? $place->id : null);
    }

    public function testSearchPutsTheMorePopulousCityFirst(): void
    {
        // Act
        $found = $this->directory->searchCities('ber', null, 'en');

        // Assert
        self::assertSame([$this->berlin->getId(), $this->berlinNewHampshire->getId()], array_map(static fn(CityView $city): int => $city->id, $found));
    }

    public function testSearchIsScopedToACountry(): void
    {
        // Act
        $found = $this->directory->searchCities('Ber', 'us', 'en');

        // Assert
        self::assertSame([$this->berlinNewHampshire->getId()], array_map(static fn(CityView $city): int => $city->id, $found));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTranslatedQueries(): iterable
    {
        yield 'a non-latin name' => ['柏'];
        yield 'an accented name' => ['Berlí'];
        yield 'an accented name typed without its accent' => ['berli'];
    }

    #[DataProvider('provideTranslatedQueries')]
    public function testSearchMatchesTranslatedNames(string $query): void
    {
        // Act
        $found = $this->directory->searchCities($query, 'DE', 'en');

        // Assert
        self::assertSame([$this->berlin->getId()], array_map(static fn(CityView $city): int => $city->id, $found));
    }

    public function testSearchTreatsWildcardsAsText(): void
    {
        // Act
        $found = $this->directory->searchCities('%', null, 'en');

        // Assert
        self::assertSame([], $found);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideExactNames(): iterable
    {
        yield 'every city of that name, most populous first' => ['Berlin', ['DE', 'US']];
        yield 'letter case aside' => ['berlin', ['DE', 'US']];
        yield 'a translated name' => ['München', ['DE']];
        yield 'a name nobody carries' => ['Atlantis', []];
        yield 'only the start of a name' => ['Ber', []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideExactNames')]
    public function testCitiesAreFoundByTheirExactName(string $name, array $expected): void
    {
        // Act
        $found = $this->directory->findCitiesNamed($name, 'en');

        // Assert
        self::assertSame($expected, array_map(static fn(CityView $city): string => $city->countryIso2, $found));
    }

    public function testCountriesAreOrderedByTheirLocalName(): void
    {
        // Act
        $countries = $this->directory->listCountries('de');

        // Assert
        self::assertSame(['Deutschland', 'Vereinigte Staaten'], array_map(static fn(CountryView $country): string => $country->name, $countries));
    }

    public function testTheCountryOfEachCityIsAnsweredInOneCall(): void
    {
        // Act
        $codes = $this->directory->findCountryCodes([(int) $this->munich->getId(), (int) $this->springfieldIllinois->getId()]);

        // Assert
        self::assertSame([(int) $this->munich->getId() => 'DE', (int) $this->springfieldIllinois->getId() => 'US'], $codes);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function providePlaceLabels(): iterable
    {
        yield 'a country name' => ['germany', 'Germany'];
        yield 'a translated country name' => ['deutschland', 'Germany'];
        yield 'a city name, most populous first' => ['berlin', 'Berlin'];
        yield 'a translated city name' => ['munchen', 'Munich'];
        yield 'an umlaut spelled out' => ['muenchen', 'Munich'];
        yield 'a spelled-out umlaut that names no place' => ['bluesky', null];
        yield 'padded and uppercase' => [' Munich ', 'Munich'];
        yield 'no place' => ['boardgames', null];
    }

    #[DataProvider('providePlaceLabels')]
    public function testALabelNamesAPlace(string $label, ?string $expected): void
    {
        // Act
        $place = $this->directory->findPlaceNamed($label, 'en');

        // Assert
        self::assertSame($expected, $place?->name);
    }

    public function testACityMatchedByLabelIsTheMorePopulousOne(): void
    {
        // Act
        $place = $this->directory->findPlaceNamed('berlin', 'en');

        // Assert
        self::assertSame($this->berlin->getId(), $place instanceof CityView ? $place->id : null);
    }
}
