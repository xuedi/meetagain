<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Cms\MenuService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class MenuRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private MenuService $menuService,
        private Security $security,
    ) {}

    public function getMenu(string $type): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request instanceof Request ? $request->getLocale() : 'en';

        return $this->menuService->getMenuForContext(type: $type, user: $this->security->getUser(), locale: $locale);
    }
}
