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
use Symfony\Contracts\Translation\TranslatorInterface;

class AnnouncementType extends AbstractType
{
    public function __construct(
        public readonly TranslatorInterface $translator,
        public readonly CmsRepository $cmsRepository,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cmsPage', EntityType::class, [
                'class' => Cms::class,
                'label' => false,
                'choice_label' => fn (Cms $cms) => $cms->getSlug() . ($cms->isPublished() ? '' : ' (unpublished)'),
                'query_builder' => fn () => $this->cmsRepository->createQueryBuilder('c')->orderBy('c.slug', 'ASC'),
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
