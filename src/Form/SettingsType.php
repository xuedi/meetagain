<?php declare(strict_types=1);

namespace App\Form;

use App\Service\ConfigService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingsType extends AbstractType
{
    public function __construct(private readonly ConfigService $configService)
    {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $mailer = $this->configService->getMailerAddress();
        $builder->add('url', TextType::class, [
            'attr' => ['placeholder' => $this->configService->getUrl()],
            'data' => $this->configService->getUrl(),
            'label' => 'SiteUrl',
        ])->add('senderName', TextType::class, [
            'attr' => ['placeholder' => $mailer->getName()],
            'data' => $mailer->getName(),
            'label' => 'Sender Name',
        ])->add('senderEmail', EmailType::class, [
            'attr' => ['placeholder' => $mailer->getAddress()],
            'data' => $mailer->getAddress(),
            'label' => 'Sender Email',
        ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
