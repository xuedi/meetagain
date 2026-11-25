<?php declare(strict_types=1);

namespace Plugin\Fediverse\Form;

use Override;
use Plugin\Fediverse\Enum\Content;
use Plugin\Fediverse\Enum\Location;
use Plugin\Fediverse\Repository\AccountRepository;
use Plugin\Fediverse\Service\ConfigService;
use Plugin\Fediverse\ValueObject\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ConfigType extends AbstractType
{
    private const string HOST_PATTERN = '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,62}$/';

    public function __construct(
        private readonly ConfigService $configService,
        private readonly AccountRepository $accountRepository,
    ) {}

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $scopeId = $options['scope_id'];
        $ceiling = $scopeId === null ? null : $this->configService->getCeiling();
        $account = $this->accountRepository->findLiveForScope($scopeId);
        $lockedUsername = $account?->isLocked() === true ? $account->getUsername() : null;

        $builder->add('content', EnumType::class, [
            'class' => Content::class,
            'label' => 'fediverse_config.content',
            'help' => 'fediverse_config.content_help',
            'choices' => $ceiling === null ? Content::cases() : Content::upTo($ceiling->getContent()),
            'choice_label' => static fn(Content $content): string => $content->label(),
        ])->add('location', EnumType::class, [
            'class' => Location::class,
            'label' => 'fediverse_config.location',
            'help' => 'fediverse_config.location_help',
            'choices' => $ceiling === null ? Location::cases() : Location::upTo($ceiling->getLocation()),
            'choice_label' => static fn(Location $location): string => $location->label(),
        ]);

        if ($ceiling === null || $ceiling->isImage()) {
            $builder->add('image', CheckboxType::class, [
                'label' => 'fediverse_config.image',
                'required' => false,
            ]);
        }

        $builder->add('username', TextType::class, [
            'label' => 'fediverse_config.username',
            'help' => $lockedUsername === null ? 'fediverse_config.username_help' : 'fediverse_config.username_locked_help',
            'required' => false,
            'disabled' => $lockedUsername !== null,
            'attr' => ['maxlength' => Config::USERNAME_MAX],
            'constraints' => [
                new Length(max: Config::USERNAME_MAX, maxMessage: 'fediverse_config.validator_username_length'),
                new Regex(pattern: Config::USERNAME_PATTERN, message: 'fediverse_config.validator_username_format'),
                new Callback(callback: function (?string $username, ExecutionContextInterface $context) use ($scopeId): void {
                    $isTaken = $username !== null && $this->accountRepository->isUsernameTakenOutsideScope($username, $scopeId);
                    if ($isTaken) {
                        $context->addViolation('fediverse_config.validator_username_taken');
                    }
                }),
            ],
        ])->add('accountSummary', TextareaType::class, [
            'label' => 'fediverse_config.account_summary',
            'help' => 'fediverse_config.account_summary_help',
            'required' => false,
            'attr' => ['maxlength' => Config::ACCOUNT_SUMMARY_MAX, 'rows' => 3],
            'constraints' => [
                new Length(max: Config::ACCOUNT_SUMMARY_MAX, maxMessage: 'fediverse_config.validator_account_summary_length'),
            ],
        ]);

        if ($ceiling === null) {
            $this->addGlobalFields($builder);
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($ceiling, $lockedUsername): void {
            $data = $event->getData();
            if (!$data instanceof Config) {
                return;
            }

            if ($ceiling !== null) {
                $this->clampToCeiling($data, $ceiling);
            }
            if ($lockedUsername !== null) {
                $data->setUsername($lockedUsername);
            }
        });
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Config::class,
            'scope_id' => null,
        ]);
        $resolver->setAllowedTypes('scope_id', ['null', 'string']);
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'fediverse_config';
    }

    private function clampToCeiling(Config $data, Config $ceiling): void
    {
        $clamped = $data->clampedTo($ceiling);
        $data->setContent($clamped->getContent())->setLocation($clamped->getLocation())->setImage($clamped->isImage());
        if ($data->getUsername() !== null && $data->getUsername() === $ceiling->getUsername()) {
            $data->setUsername(null)->setAccountSummary(null);
        }
    }

    private function addGlobalFields(FormBuilderInterface $builder): void
    {
        $builder->add('announce', CheckboxType::class, [
            'label' => 'fediverse_config.announce',
            'help' => 'fediverse_config.announce_help',
            'required' => false,
        ])->add('blockedServers', TextareaType::class, [
            'label' => 'fediverse_config.blocked_servers',
            'help' => 'fediverse_config.blocked_servers_help',
            'required' => false,
            'attr' => ['rows' => 4],
            'constraints' => [
                new All(constraints: [
                    new Regex(pattern: self::HOST_PATTERN, message: 'fediverse_config.validator_blocked_server'),
                ]),
            ],
        ]);

        $builder->get('blockedServers')->addModelTransformer(
            new CallbackTransformer(
                static fn(?array $servers): string => implode("\n", $servers ?? []),
                static fn(?string $text): array => array_values(array_filter(
                    array_map(static fn(string $line): string => mb_strtolower(trim($line)), preg_split('/\R/', $text ?? '') ?: []),
                    static fn(string $line): bool => $line !== '',
                )),
            ),
        );
    }
}
