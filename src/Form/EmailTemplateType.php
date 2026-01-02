<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\EmailTemplate;
use App\Repository\EmailTemplateTranslationRepository;
use App\Service\TranslationService;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmailTemplateType extends AbstractType
{
    public function __construct(
        private readonly TranslationService $translationService,
        private readonly EmailTemplateTranslationRepository $translationRepo,
    ) {
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var EmailTemplate|null $template */
        $template = $options['data'] ?? null;
        $templateId = $template?->getId();

        if ($templateId !== null) {
            foreach ($this->translationService->getLanguageCodes() as $languageCode) {
                $translation = $this->translationRepo->findOneBy([
                    'emailTemplate' => $templateId,
                    'language' => $languageCode,
                ]);
                $builder->add("subject-$languageCode", TextType::class, [
                    'label' => "Subject ($languageCode)",
                    'data' => $translation?->getSubject() ?? '',
                    'mapped' => false,
                ]);
                $builder->add("body-$languageCode", TextareaType::class, [
                    'label' => "Body ($languageCode)",
                    'data' => $translation?->getBody() ?? '',
                    'mapped' => false,
                    'attr' => ['rows' => 15],
                ]);
            }
        }
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EmailTemplate::class,
        ]);
    }
}
