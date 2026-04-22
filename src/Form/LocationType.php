<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Location;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LocationType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', options: ['label' => 'admin_location.form_label_name'])
            ->add('description', TextareaType::class, [
                'label' => 'admin_location.form_label_description',
                'attr' => ['rows' => 3],
            ])
            ->add('street', options: ['label' => 'admin_location.form_label_street'])
            ->add('city', options: ['label' => 'admin_location.form_label_city'])
            ->add('postcode', options: ['label' => 'admin_location.form_label_postcode'])
            ->add('longitude', options: ['label' => 'admin_location.form_label_longitude'])
            ->add('latitude', options: ['label' => 'admin_location.form_label_latitude']);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Location::class,
        ]);
    }
}
