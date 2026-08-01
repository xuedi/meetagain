<?php declare(strict_types=1);

namespace Tests\Functional\DataHotfix;

use App\DataHotfix\Hotfixes\ItemTaxonomyToTags;
use App\Entity\ItemTag;
use App\Entity\PluginSettings;
use App\Repository\ItemTagRepository;
use App\Service\AppStateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ItemTaxonomyToTagsTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ItemTagRepository $tagRepo;
    private AppStateService $appState;
    private ItemTaxonomyToTags $hotfix;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->tagRepo = $container->get(ItemTagRepository::class);
        $this->appState = $container->get(AppStateService::class);
        $this->hotfix = $container->get(ItemTaxonomyToTags::class);

        $this->appState->remove(ItemTaxonomyToTags::MAP_KEY);
        $this->appState->remove(ItemTaxonomyToTags::SNAPSHOT_KEY);
        $this->storeGlossaryTaxonomy();
    }

    public function testEveryCategoryGroupBecomesARootTagCarryingItsCategories(): void
    {
        // Act
        $this->hotfix->execute();

        // Assert
        $byLabel = $this->tagsByLabel();
        static::assertArrayHasKey('Informal', $byLabel);
        static::assertNull($byLabel['Informal']->getParent());
        static::assertSame($byLabel['Informal'], $byLabel['Swearing']->getParent());
        static::assertNull($byLabel['Greeting']->getParent(), 'An ungrouped category stays a root');
    }

    public function testTheIdMapPointsTheOldDefinitionIdsAtTheNewRows(): void
    {
        // Act
        $this->hotfix->execute();

        // Assert
        $map = json_decode((string) $this->appState->get(ItemTaxonomyToTags::MAP_KEY), true);
        $byLabel = $this->tagsByLabel();
        static::assertSame((int) $byLabel['Greeting']->getId(), $map['glossary||category|0']);
        static::assertSame((int) $byLabel['Swearing']->getId(), $map['glossary||category|1']);
        static::assertSame((int) $byLabel['Informal']->getId(), $map['glossary||group|0']);
    }

    public function testASecondRunCreatesNoFurtherRows(): void
    {
        // Arrange
        $this->hotfix->execute();
        $first = count($this->tagRepo->findForType('glossary'));

        // Act
        $this->hotfix->execute();

        // Assert
        static::assertSame($first, count($this->tagRepo->findForType('glossary')));
    }

    /** @return array<string, ItemTag> */
    private function tagsByLabel(): array
    {
        $byLabel = [];
        foreach ($this->tagRepo->findForType('glossary') as $tag) {
            $byLabel[$tag->getLabels()['en'] ?? ''] = $tag;
        }

        return $byLabel;
    }

    private function storeGlossaryTaxonomy(): void
    {
        $row = $this->em->getRepository(PluginSettings::class)->findOneBy(['pluginKey' => 'glossary']) ?? new PluginSettings();
        $row->setPluginKey('glossary');
        $row->setUpdatedAt(new DateTimeImmutable());
        $row->setData([
            'secondaryEnabled' => true,
            'taxonomy' => [
                'categoriesEnabled' => true,
                'categoryGroups' => [['id' => 0, 'labels' => ['en' => 'Informal']]],
                'categories' => [
                    ['id' => 0, 'labels' => ['en' => 'Greeting']],
                    ['id' => 1, 'labels' => ['en' => 'Swearing'], 'group' => 0],
                ],
                'tags' => [],
            ],
        ]);
        $this->em->persist($row);
        $this->em->flush();
    }
}
