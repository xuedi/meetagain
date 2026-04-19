<?php declare(strict_types=1);

namespace Plugin\Glossary\Controller;

use App\Entity\User;
use Plugin\Glossary\Service\GlossaryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as AbstractSymfonyController;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

abstract class AbstractGlossaryController extends AbstractSymfonyController
{
    public function __construct(
        protected GlossaryService $service,
    ) {}

    protected function getAuthedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AuthenticationCredentialsNotFoundException(
                'Should never happen, see: config/packages/security.yaml',
            );
        }

        return $user;
    }

    protected function renderList(string $template, array $parameter = [])
    {
        return $this->render($template, [
            'list' => $this->service->getList(),
            ...$parameter,
        ]);
    }
}
