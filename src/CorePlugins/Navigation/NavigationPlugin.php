<?php declare(strict_types=1);

namespace App\CorePlugins\Navigation;

use App\Entity\Link;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\ValueObject\LinkCollection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

readonly class NavigationPlugin implements Plugin
{
    public function __construct(
        private RouterInterface $router,
        private RequestStack $requestStack,
        private Security $security,
    ) {}

    public function getPluginKey(): string
    {
        return 'core_navigation';
    }

    public function getLinkCollection(): LinkCollection
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en';

        $links = [
            new Link(slug: $this->router->generate('app_event', ['_locale' => $locale]), name: 'events', priority: 100),
            new Link(
                slug: $this->router->generate('app_member', ['_locale' => $locale, 'page' => 1]),
                name: 'members',
                priority: 200,
            ),
        ];

        if ($this->security->isGranted('ROLE_ORGANIZER')) {
            $links[] = new Link(slug: $this->router->generate('app_admin'), name: 'admin', priority: 300);
        }

        return LinkCollection::empty()->withNavLinks($links);
    }

    public function getEventTile(int $eventId): ?string
    {
        return null;
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
    }

    public function preFixtures(OutputInterface $output): void
    {
    }

    public function postFixtures(OutputInterface $output): void
    {
    }

    public function getFooterAbout(): ?string
    {
        return null;
    }

    public function runCronTasks(OutputInterface $output): void
    {
    }

    public function getEventListItemTags(int $eventId): array
    {
        return [];
    }

    public function warmCache(WarmCacheType $type, array $ids): void
    {
    }

    public function getMemberPageTop(): ?string
    {
        return null;
    }

    public function getStylesheets(): array
    {
        return [];
    }

    public function getJavascripts(): array
    {
        return [];
    }
}
