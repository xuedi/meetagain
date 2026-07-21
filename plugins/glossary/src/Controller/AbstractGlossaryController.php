<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Entity\User;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as AbstractSymfonyController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractGlossaryController extends AbstractSymfonyController
{
    protected ConfigService $configService;

    public function __construct(
        protected GlossaryService $service,
    ) {}

    #[Required]
    public function setConfigService(ConfigService $configService): void
    {
        $this->configService = $configService;
    }

    protected function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    protected function getAuthedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException('Should never happen, see: config/packages/security.yaml');
        }

        return $user;
    }

    protected function renderPage(string $template, array $parameter = []): Response
    {
        return $this->render($template, [
            'config' => $this->configService->getConfig(),
            ...$parameter,
        ]);
    }
}
