<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\MenuService;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MenuExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly MenuService $menuService,
        private readonly Security $security,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_menu', $this->getMenu(...)),
        ];
    }

    public function getMenu(string $type): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request instanceof Request ? $request->getLocale() : 'en';

        return $this->menuService->getMenuForContext(
            type: $type,
            user: $this->security->getUser(),
            locale: $locale
        );
    }
}
