<?php declare(strict_types=1);

namespace App\Form;

use App\Repository\UserRepository;
use App\Service\Config\ConfigService;
use Override;
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
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $mailer = $this->configService->getMailerAddress();
        $builder
            ->add('url', TextType::class, [
                'label' => 'admin_system_config.field_url',
                'attr' => ['placeholder' => $this->configService->getUrl()],
                'data' => $this->configService->getUrl(),
            ])
            ->add('host', TextType::class, [
                'label' => 'admin_system_config.field_host',
                'attr' => ['placeholder' => $this->configService->getHost()],
                'data' => $this->configService->getHost(),
            ])
            ->add('senderName', TextType::class, [
                'label' => 'admin_system_config.field_sender_name',
                'attr' => ['placeholder' => $mailer->getName()],
                'data' => $mailer->getName(),
            ])
            ->add('senderEmail', EmailType::class, [
                'label' => 'admin_system_config.field_sender_email',
                'attr' => ['placeholder' => $mailer->getAddress()],
                'data' => $mailer->getAddress(),
            ])
            ->add('systemUser', ChoiceType::class, [
                'attr' => ['class' => 'is-fullwidth'],
                'label' => 'admin_system_config.field_system_user',
                'data' => $this->configService->getSystemUserId(),
                'choices' => $this->userRepo->getAllUserChoice(),
            ])
            ->add('dateFormat', ChoiceType::class, [
                'attr' => ['class' => 'is-fullwidth'],
                'label' => 'admin_system_config.field_date_format',
                'data' => $this->configService->getDateFormat(),
                'choices' => [
                    '2025-12-30 14:30 (ISO)' => 'Y-m-d H:i',
                    '30.12.2025 14:30 (EU)' => 'd.m.Y H:i',
                    '30/12/2025 14:30 (UK)' => 'd/m/Y H:i',
                    '12/30/2025 02:30 PM (US)' => 'm/d/Y h:i A',
                ],
            ])
            ->add('footerCol1Title', TextType::class, [
                'label' => 'admin_system_config.field_footer_col1',
                'required' => false,
                'data' => $this->configService->getFooterColumnTitle('col1'),
            ])
            ->add('footerCol2Title', TextType::class, [
                'label' => 'admin_system_config.field_footer_col2',
                'required' => false,
                'data' => $this->configService->getFooterColumnTitle('col2'),
            ])
            ->add('footerCol3Title', TextType::class, [
                'label' => 'admin_system_config.field_footer_col3',
                'required' => false,
                'data' => $this->configService->getFooterColumnTitle('col3'),
            ])
            ->add('footerCol4Title', TextType::class, [
                'label' => 'admin_system_config.field_footer_col4',
                'required' => false,
                'data' => $this->configService->getFooterColumnTitle('col4'),
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
