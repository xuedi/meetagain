<?php

declare(strict_types=1);

namespace App\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class DeveloperAppApplicationType extends AbstractType
{
    public const string FIELD_REDIRECT_URIS = 'redirectUrisRaw';
    public const string FIELD_GRANTS = 'requestedGrants';

    /** @var list<string> */
    private const array GRANT_CHOICES = [
        'authorization_code',
        'refresh_token',
        'client_credentials',
    ];

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $structuralReadonly = (bool) ($options['structural_readonly'] ?? false);

        $builder->add('appName', TextType::class, [
            'constraints' => [
                new NotBlank(message: 'developer_apps.validator_name_blank'),
                new Length(max: 80),
            ],
        ])->add('description', TextareaType::class, [
            'required' => false,
            'constraints' => [new Length(max: 500)],
        ])->add('homepageUrl', UrlType::class, [
            'required' => false,
        ])->add('logo', FileType::class, [
            'mapped' => false,
            'required' => false,
            'constraints' => [
                new File(
                    maxSize: '2M',
                    mimeTypes: ['image/png', 'image/jpeg', 'image/webp', 'image/gif'],
                    mimeTypesMessage: 'developer_apps.validator_logo_mime',
                ),
            ],
        ]);

        if (!$structuralReadonly) {
            $builder->add(self::FIELD_REDIRECT_URIS, TextareaType::class, [
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'developer_apps.validator_redirect_blank'),
                ],
            ])->add(self::FIELD_GRANTS, ChoiceType::class, [
                'mapped' => false,
                'multiple' => true,
                'expanded' => true,
                'choices' => array_combine(self::GRANT_CHOICES, self::GRANT_CHOICES),
                'constraints' => [
                    new NotBlank(message: 'developer_apps.validator_grants_blank'),
                ],
            ]);

            $builder->addEventListener(FormEvents::SUBMIT, $this->onSubmit(...));
        }
    }

    public function onSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        $rawField = $form->get(self::FIELD_REDIRECT_URIS);
        $raw = (string) ($rawField->getData() ?? '');
        $uris = self::parseRedirectUris($raw);

        if ($uris === []) {
            $rawField->addError(new FormError('developer_apps.validator_redirect_blank'));
            return;
        }

        if (count($uris) > 5) {
            $rawField->addError(new FormError('developer_apps.validator_redirect_too_many'));
            return;
        }

        foreach ($uris as $uri) {
            if (self::isValidRedirectUri($uri)) {
                continue;
            }

            $rawField->addError(new FormError('developer_apps.validator_redirect_invalid'));
            return;
        }

        $grantsField = $form->get(self::FIELD_GRANTS);
        /** @var list<string> $grants */
        $grants = (array) ($grantsField->getData() ?? []);

        if ($grants === []) {
            $grantsField->addError(new FormError('developer_apps.validator_grants_blank'));
            return;
        }

        if (in_array('refresh_token', $grants, true) && !in_array('authorization_code', $grants, true)) {
            $grantsField->addError(new FormError('developer_apps.validator_grants_refresh_requires_code'));
            return;
        }
    }

    /**
     * @return list<string>
     */
    public static function parseRedirectUris(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $uris = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }
            $uris[] = $trim;
        }

        return array_values(array_unique($uris));
    }

    public static function isValidRedirectUri(string $uri): bool
    {
        $parts = parse_url($uri);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);

        if ($scheme === 'https') {
            return true;
        }

        if ($scheme === 'http') {
            return $host === 'localhost' || $host === '127.0.0.1';
        }

        return false;
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'structural_readonly' => false,
        ]);
        $resolver->setAllowedTypes('structural_readonly', 'bool');
    }
}
