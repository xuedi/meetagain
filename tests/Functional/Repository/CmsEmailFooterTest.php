<?php declare(strict_types=1);

namespace Tests\Functional\Repository;

use App\Entity\Cms;
use App\Entity\CmsMenuLocation;
use App\Entity\User;
use App\Enum\MenuLocation;
use App\Repository\CmsRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CmsEmailFooterTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CmsRepository $repo;
    private TagAwareCacheInterface $cache;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(CmsRepository::class);
        $this->cache = $container->get('cache.cms_page_cache');
        $this->cache->invalidateTags(['cms_menu']);
    }

    protected function tearDown(): void
    {
        $this->cache->invalidateTags(['cms_menu']);
        parent::tearDown();
    }

    public function testTheFlaggedSetIsIndependentOfTheSitesFourthFooterColumn(): void
    {
        // Arrange
        $flaggedOutsideTheColumn = $this->page('footer-flagged-no-column', emailFooter: true);
        $this->page('column-only', emailFooter: false, location: MenuLocation::BottomCol4);
        $this->em->flush();
        $this->cache->invalidateTags(['cms_menu']);

        // Act
        $slugs = array_map(static fn(Cms $cms): ?string => $cms->getSlug(), $this->repo->findForEmailFooter());

        // Assert
        static::assertContains($flaggedOutsideTheColumn->getSlug(), $slugs);
        static::assertNotContains('column-only', $slugs);
    }

    public function testAnUnpublishedFlaggedPageIsSkipped(): void
    {
        // Arrange
        $this->page('footer-flagged-draft', emailFooter: true, published: false);
        $this->em->flush();
        $this->cache->invalidateTags(['cms_menu']);

        // Act
        $slugs = array_map(static fn(Cms $cms): ?string => $cms->getSlug(), $this->repo->findForEmailFooter());

        // Assert
        static::assertNotContains('footer-flagged-draft', $slugs);
    }

    public function testTheShippedLegalPagesAreFlaggedOutOfTheBox(): void
    {
        // Act
        $slugs = array_map(static fn(Cms $cms): ?string => $cms->getSlug(), $this->repo->findForEmailFooter());

        // Assert
        static::assertContains('imprint', $slugs);
        static::assertContains('privacy', $slugs);
    }

    private function page(string $slug, bool $emailFooter, bool $published = true, ?MenuLocation $location = null): Cms
    {
        $cms = new Cms();
        $cms->setSlug($slug);
        $cms->setCreatedAt(new DateTimeImmutable());
        $cms->setCreatedBy($this->em->getRepository(User::class)->findOneBy([]));
        $cms->setPublished($published);
        $cms->setLocked(false);
        $cms->setEmailFooter($emailFooter);

        if ($location !== null) {
            $menuLocation = new CmsMenuLocation();
            $menuLocation->setCms($cms);
            $menuLocation->setLocation($location);
            $cms->addMenuLocation($menuLocation);
        }

        $this->em->persist($cms);

        return $cms;
    }
}
