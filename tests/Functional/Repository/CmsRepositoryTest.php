<?php declare(strict_types=1);

namespace Tests\Functional\Repository;

use App\Entity\Cms;
use App\Repository\CmsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CmsRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CmsRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(CmsRepository::class);
    }

    public function testFindLockedSlugsReturnsLockedPageSlugs(): void
    {
        // Act
        $slugs = $this->repo->findLockedSlugs();

        // Assert - the canonical platform-wide legal pages are locked
        static::assertContains('imprint', $slugs);
        static::assertContains('privacy', $slugs);
    }

    public function testFindSlugByIdReturnsPersistedSlug(): void
    {
        // Arrange
        $page = $this->em->getRepository(Cms::class)->findOneBy(['slug' => 'imprint']);
        static::assertInstanceOf(Cms::class, $page);

        // Act
        $slug = $this->repo->findSlugById($page->getId());

        // Assert
        static::assertSame('imprint', $slug);
    }

    public function testFindSlugByIdReturnsNullForUnknownId(): void
    {
        // Act & Assert
        static::assertNull($this->repo->findSlugById(999999));
    }
}
