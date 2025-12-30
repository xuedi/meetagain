<?php declare(strict_types=1);

namespace App\Form;

use App\Repository\UserRepository;
use App\Service\ConfigService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingsType extends AbstractType
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly UserRepository $userRepo,
    ) {
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $mailer = $this->configService->getMailerAddress();
        $builder
            ->add('url', TextType::class, [
                'label' => 'SiteUrl',
                'attr' => ['placeholder' => $this->configService->getUrl()],
                'data' => $this->configService->getUrl(),
            ])
            ->add('host', TextType::class, [
                'label' => 'SiteHost',
                'attr' => ['placeholder' => $this->configService->getHost()],
                'data' => $this->configService->getHost(),
            ])
            ->add('senderName', TextType::class, [
                'label' => 'Sender Name',
                'attr' => ['placeholder' => $mailer->getName()],
                'data' => $mailer->getName(),
            ])
            ->add('senderEmail', EmailType::class, [
                'label' => 'Sender Email',
                'attr' => ['placeholder' => $mailer->getAddress()],
                'data' => $mailer->getAddress(),
            ])
            ->add('systemUser', ChoiceType::class, [
                'attr' => ['class' => 'is-fullwidth'],
                'label' => 'System User',
                'data' => $this->configService->getSystemUserId(),
                'choices' => $this->userRepo->getAllUserChoice(),
            ])
            ->add('dateFormat', ChoiceType::class, [
                'attr' => ['class' => 'is-fullwidth'],
                'label' => 'Date Format',
                'data' => $this->configService->getDateFormat(),
                'choices' => [
                    '2025-12-30 14:30 (ISO)' => 'Y-m-d H:i',
                    '30.12.2025 14:30 (EU)' => 'd.m.Y H:i',
                    '30/12/2025 14:30 (UK)' => 'd/m/Y H:i',
                    '12/30/2025 02:30 PM (US)' => 'm/d/Y h:i A',
                ],
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
