<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Announcement;
use App\Entity\Cms;
use App\Repository\CmsRepository;
use Override;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AnnouncementType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cmsPage', EntityType::class, [
                'class' => Cms::class,
                'label' => 'announcement_cms_page',
                'choice_label' => fn (Cms $cms) => $cms->getSlug() . ($cms->isPublished() ? '' : ' (unpublished)'),
                'query_builder' => fn (CmsRepository $repo) => $repo->createQueryBuilder('c')->orderBy('c.slug', 'ASC'),
                'placeholder' => 'announcement_select_cms_page',
                'attr' => ['class' => 'input'],
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Announcement::class,
        ]);
    }
}
