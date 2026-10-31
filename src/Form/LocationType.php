<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Location;
use App\Item\Location\CityDefaultProviderInterface;
use Module\Geo\Contract\DirectoryInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class LocationType extends AbstractType
{
    /**
     * @param iterable<CityDefaultProviderInterface> $cityDefaults
     */
    public function __construct(
        private readonly DirectoryInterface $directory,
        #[AutowireIterator(CityDefaultProviderInterface::class)]
        private readonly iterable $cityDefaults,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', options: [
                'label' => 'admin_location.form_label_name',
                'constraints' => $this->requiredText(255),
            ])
            ->add('description', TextareaType::class, [
                'label' => 'admin_location.form_label_description',
                'attr' => ['rows' => 3],
                'constraints' => [new NotBlank(message: 'admin_location.validator_blank')],
            ])
            ->add('street', options: [
                'label' => 'admin_location.form_label_street',
                'constraints' => $this->requiredText(255),
            ])
            ->add('city', options: [
                'label' => 'admin_location.form_label_city',
                'constraints' => $this->requiredText(32),
            ])
            ->add('postcode', options: [
                'label' => 'admin_location.form_label_postcode',
                'constraints' => $this->requiredText(8),
            ])
            ->add('longitude', options: [
                'label' => 'admin_location.form_label_longitude',
                'constraints' => [new Length(max: 20, maxMessage: 'admin_location.validator_too_long')],
            ])
            ->add('latitude', options: [
                'label' => 'admin_location.form_label_latitude',
                'constraints' => [new Length(max: 20, maxMessage: 'admin_location.validator_too_long')],
            ]);

        if ($this->directory->hasCities()) {
            $location = $builder->getData();
            $isNew = !$location instanceof Location || $location->getId() === null && $location->getCityId() === null;
            $builder->add('cityId', CityType::class, [
                'required' => false,
                'search_label' => 'admin_location.form_label_city_place',
                ...($isNew ? ['data' => $this->defaultCityId()] : []),
            ]);
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Location::class,
        ]);
    }

    private function defaultCityId(): ?int
    {
        foreach ($this->cityDefaults as $provider) {
            $cityId = $provider->getDefaultCityId();
            if ($cityId !== null) {
                return $cityId;
            }
        }

        return null;
    }

    /**
     * @param positive-int $max
     *
     * @return array<int, NotBlank|Length>
     */
    private function requiredText(int $max): array
    {
        return [
            new NotBlank(message: 'admin_location.validator_blank'),
            new Length(max: $max, maxMessage: 'admin_location.validator_too_long'),
        ];
    }
}
