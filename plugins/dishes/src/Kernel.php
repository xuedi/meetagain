<?php declare(strict_types=1);

namespace Plugin\Dishes;

use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Item\ItemAssociationService;
use App\ValueObject\LinkCollection;
use Plugin\Dishes\Service\DishService;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly DishService $dishService,
        private readonly ItemAssociationService $itemAssociations,
        private readonly EventRepository $eventRepository,
        private readonly UserRepository $userRepository,
    ) {}

    public function getPluginKey(): string
    {
        return 'dishes';
    }

    public function getLinkCollection(): LinkCollection
    {
        return LinkCollection::empty()->withNavLinks([
            new Link(slug: $this->urlGenerator->generate('app_dishes_dishlist'), name: 'dishes.menu_main'),
        ]);
    }

    public function getEventTile(int $eventId, EventTileLocation $location): ?string
    {
        return null;
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
        if ($this->dishService->getList() !== []) {
            $output->writeln('<comment>Dishes: already seeded, skipping.</comment>');

            return;
        }

        $admin = $this->userRepository->findOneBy(['email' => 'admin@example.org']);
        if ($admin === null) {
            $output->writeln('<comment>Dishes: no admin user found, skipping.</comment>');

            return;
        }
        $adminId = (int) $admin->getId();

        // [name, language, description, recipe, phonetic, origin]
        $catalog = [
            ['Bruschetta', 'en', 'Grilled bread rubbed with garlic and topped with tomato.', 'Toast bread, rub with garlic, top with diced tomato and basil.', 'bruˈskɛtta', 'Italy'],
            ['Gyoza', 'en', 'Pan-fried dumplings filled with pork and cabbage.', 'Fill wrappers, pleat, pan-fry then steam.', 'ˈɡjoʊzə', 'Japan'],
            ['Pho Bo', 'en', 'Vietnamese beef noodle soup with herbs.', 'Simmer broth with spices, pour over noodles and beef.', 'fə˧˩ ɓɔ˧', 'Vietnam'],
            ['Coq au Vin', 'en', 'Chicken braised in red wine with mushrooms.', 'Brown chicken, braise in wine with lardons and mushrooms.', 'kɔk o vɛ̃', 'France'],
            ['Mapo Tofu', 'en', 'Silken tofu in a spicy fermented-bean sauce.', 'Fry doubanjiang, add stock and tofu, finish with sichuan pepper.', 'ˈmapo ˈtoʊfu', 'China'],
            ['Ribollita', 'en', 'Tuscan bread and vegetable soup.', 'Simmer beans and kale, thicken with stale bread.', 'ribolˈliːta', 'Italy'],
            ['Tiramisu', 'en', 'Coffee-soaked ladyfingers layered with mascarpone.', 'Layer soaked biscuits with whipped mascarpone, dust with cocoa.', 'tiramiˈsu', 'Italy'],
            ['Mango Sticky Rice', 'en', 'Sweet coconut rice served with ripe mango.', 'Steam glutinous rice, fold in coconut milk, serve with mango.', 'ˈmæŋɡoʊ', 'Thailand'],
            ['Panna Cotta', 'en', 'Set cream dessert with a berry coulis.', 'Warm cream with gelatine, set, top with coulis.', 'ˈpanna ˈkɔtta', 'Italy'],
        ];

        $created = [];
        foreach ($catalog as [$name, $language, $description, $recipe, $phonetic, $origin]) {
            $created[] = $this->dishService->create($name, $language, $description, $recipe, $phonetic, $origin, $adminId);
        }

        // Seed a dinner-style grouped menu on a past event: dishes clustered by course label,
        // ordered by a running position - this is what the dish cell renderer groups by sectionLabel.
        $pastEvents = $this->eventRepository->getPastEvents(1);
        $attached = 0;
        if ($pastEvents !== [] && count($created) >= 5) {
            $eventId = (int) $pastEvents[0]->getId();
            $menu = [
                ['Starter', [$created[0], $created[1]]],
                ['Main', [$created[3], $created[4]]],
                ['Dessert', [$created[6]]],
            ];
            foreach ($menu as [$section, $dishes]) {
                foreach ($dishes as $dish) {
                    $this->itemAssociations->attach($eventId, DishService::ITEM_TYPE, (int) $dish->getId(), $adminId, $attached, $section);
                    $attached++;
                }
            }
        }

        $output->writeln(sprintf('<info>Dishes: seeded %d dishes, attached %d as a grouped menu.</info>', count($created), $attached));
    }

    public function preFixtures(OutputInterface $output): void {}

    public function postFixtures(OutputInterface $output): void {}

    public function getFooterAbout(): ?string
    {
        return null;
    }

    public function getEventListItemTags(int $eventId): array
    {
        return [];
    }

    public function warmCache(WarmCacheType $type, array $ids): void {}

    public function getStylesheets(): array
    {
        return [];
    }

    public function getJavascripts(): array
    {
        return [];
    }
}
