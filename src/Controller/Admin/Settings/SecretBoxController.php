<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Service\Security\SecretBox;
use App\Service\Security\SecretBoxConsumerInterface;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system')]
final class SecretBoxController extends AbstractSettingsController
{
    /** @param iterable<SecretBoxConsumerInterface> $consumers */
    public function __construct(
        TranslatorInterface $translator,
        #[AutowireIterator(SecretBoxConsumerInterface::class)]
        private readonly iterable $consumers,
        #[SensitiveParameter, Autowire(env: 'default::APP_SECRET_BOX_KEY')]
        private readonly ?string $secretBoxKey = null,
    ) {
        parent::__construct($translator, 'secretbox');
    }

    #[Route('/secretbox', name: 'app_admin_system_secretbox', methods: ['GET'])]
    public function index(): Response
    {
        $rawKey = $this->secretBoxKey;
        $keyPresent = $rawKey !== null && $rawKey !== '';
        $keyValid = false;
        $keyPublished = false;

        if ($keyPresent) {
            $decoded = base64_decode($rawKey, strict: true);
            $keyValid = $decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES;

            foreach (SecretBox::PUBLISHED_KEYS as $publishedKey) {
                $keyPublished = $keyPublished || hash_equals($publishedKey, $rawKey);
            }
        }

        $consumers = [];
        foreach ($this->consumers as $consumer) {
            $consumers[] = [
                'label' => $this->translator->trans($consumer->getKey()),
                'count' => $consumer->count(),
            ];
        }

        return $this->render('admin/system/config/secretbox.html.twig', [
            'active' => 'system',
            'keyPresent' => $keyPresent,
            'keyValid' => $keyValid,
            'keyPublished' => $keyPublished,
            'sodiumLoaded' => extension_loaded('sodium'),
            'consumers' => $consumers,
            'adminTabs' => $this->getTabs(),
        ]);
    }
}
