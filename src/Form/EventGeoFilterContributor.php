<?php declare(strict_types=1);

namespace App\Form;

use App\Repository\LocationRepository;
use App\Service\Config\ConfigService;
use Module\Geo\Contract\CityView;
use Module\Geo\Contract\DirectoryInterface;
use Override;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class EventGeoFilterContributor implements EventFilterFormContributorInterface
{
    public function __construct(
        private ConfigService $configService,
        private DirectoryInterface $directory,
        private LocationRepository $locations,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {}

    public function isMapShown(): bool
    {
        return $this->configService->isShowEventGeo();
    }

    public function isActive(): bool
    {
        return $this->configService->isShowEventGeo() && $this->directory->hasCities();
    }

    #[Override]
    public function addFields(FormBuilderInterface $builder): void
    {
        if (!$this->isActive()) {
            return;
        }

        $locale = $this->locale();
        $codes = $this->countryCodesByCity();
        $countries = [$this->translator->trans('events.filter_country_all') => ''];
        foreach ($this->directory->listCountries($locale) as $country) {
            if (in_array($country->iso2, $codes, true)) {
                $countries[$country->name] = $country->iso2;
            }
        }
        $builder->add('country', ChoiceType::class, ['data' => '', 'label' => false, 'required' => false, 'choices' => $countries]);

        $selected = $this->selectedCountry();
        if ($selected === null || !in_array($selected, $countries, true)) {
            return;
        }

        $cities = [$this->translator->trans('events.filter_city_all') => ''];
        $views = $this->directory->findCities(array_keys(array_filter($codes, static fn(string $code): bool => $code === $selected)), $locale);
        usort($views, static fn(CityView $a, CityView $b): int => strcmp($a->name, $b->name));
        foreach ($views as $city) {
            $cities[$city->name] = $city->slug;
        }
        $builder->add('city', ChoiceType::class, ['data' => '', 'label' => false, 'required' => false, 'choices' => $cities]);
    }

    /**
     * @return list<int>|null the cities an event's location must lie in, null while no country is chosen
     */
    public function cityIds(?string $country, ?string $city): ?array
    {
        $country = strtoupper(trim((string) $country));
        if (!$this->isActive() || $country === '') {
            return null;
        }

        $city = trim((string) $city);
        if ($city !== '') {
            $view = $this->directory->findCityBySlug($country, $city, $this->locale());

            return $view === null ? [] : [$view->id];
        }

        return array_keys(array_filter($this->countryCodesByCity(), static fn(string $code): bool => $code === $country));
    }

    /**
     * @return array<int, string> ISO2 keyed by the id of every city a location names
     */
    private function countryCodesByCity(): array
    {
        return $this->directory->findCountryCodes($this->locations->findCityIds());
    }

    private function selectedCountry(): ?string
    {
        $country = $this->requestStack->getCurrentRequest()?->query->getString('country') ?? '';

        return $country === '' ? null : strtoupper($country);
    }

    private function locale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en';
    }
}
