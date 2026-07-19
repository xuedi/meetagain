<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Entity\User;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as AbstractSymfonyController;
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

    protected function getAuthedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException('Should never happen, see: config/packages/security.yaml');
        }

        return $user;
    }

    protected function renderList(string $template, array $parameter = [])
    {
        return $this->render($template, [
            'list' => $this->service->getList(),
            'config' => $this->configService->getConfig(),
            ...$parameter,
        ]);
    }
}
