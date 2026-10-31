<?php declare(strict_types=1);

namespace App\Form;

use Module\Geo\Contract\DirectoryInterface;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CityType extends AbstractType
{
    private const string FALLBACK_LOCALE = 'en';

    public function __construct(
        private readonly DirectoryInterface $directory,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $locale = $this->locale();
        $countries = [];
        foreach ($this->directory->listCountries($locale) as $country) {
            $countries[$country->name] = $country->iso2;
        }

        $builder
            ->add('country', ChoiceType::class, [
                'label' => 'city_picker.label_country',
                'choices' => $countries,
                'choice_translation_domain' => false,
                'placeholder' => 'city_picker.country_any',
                'required' => false,
                'attr' => ['data-city-picker-country' => ''],
            ])
            ->add('search', TextType::class, [
                'label' => $options['search_label'],
                'required' => false,
                'attr' => [
                    'autocomplete' => 'off',
                    'placeholder' => 'city_picker.search_placeholder',
                    'data-city-picker-search' => '',
                ],
            ])
            ->add('id', HiddenType::class, [
                'attr' => ['data-city-picker-id' => ''],
            ])
            ->addModelTransformer(
                new CallbackTransformer(
                    fn(?int $cityId): array => $this->toView($cityId, $locale),
                    static fn(?array $value): ?int => is_numeric($value['id'] ?? null) ? (int) $value['id'] : null,
                ),
            );
    }

    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['search_url'] = $this->urlGenerator->generate('app_geo_cities');
        $view->vars['no_results'] = 'city_picker.no_results';
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => false,
            'search_label' => 'city_picker.label_city',
            'error_bubbling' => false,
            'constraints' => fn(Options $options): array => [new Callback(fn(?int $cityId, ExecutionContextInterface $context) => $this->validate(
                $cityId,
                (bool) $options['required'],
                $context,
            ))],
        ]);
        $resolver->setAllowedTypes('search_label', 'string');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'city_picker';
    }

    /**
     * @return array{country: string|null, search: string|null, id: int|null}
     */
    private function toView(?int $cityId, string $locale): array
    {
        $city = $cityId === null ? null : $this->directory->findCity($cityId, $locale);

        return [
            'country' => $city?->countryIso2,
            'search' => $city === null ? null : $city->name . ', ' . $city->countryName,
            'id' => $city?->id,
        ];
    }

    private function validate(?int $cityId, bool $required, ExecutionContextInterface $context): void
    {
        if ($cityId === null) {
            if ($required) {
                $context->buildViolation('city_picker.validator_required')->addViolation();
            }

            return;
        }

        if ($this->directory->findCity($cityId, self::FALLBACK_LOCALE) === null) {
            $context->buildViolation('city_picker.validator_unknown')->addViolation();
        }
    }

    private function locale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? self::FALLBACK_LOCALE;
    }
}
