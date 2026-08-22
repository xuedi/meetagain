<?php declare(strict_types=1);

namespace Tests\Functional\Migrations;

use App\Entity\Cms;
use App\Entity\CmsMenuLocation;
use App\Entity\User;
use App\Enum\MenuLocation;
use AppMigrations\Version20260822120000;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Version\Version;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EmailFooterBackfillTest extends KernelTestCase
{
    public function testAPageInTheFourthFooterColumnComesOutFlagged(): void
    {
        // Arrange
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();

        $inColumn = $this->page($em, 'backfill-column', MenuLocation::BottomCol4);
        $outsideColumn = $this->page($em, 'backfill-elsewhere', MenuLocation::BottomCol1);
        $em->flush();

        // Act
        $connection->executeStatement($this->backfillStatement());

        // Assert
        static::assertSame(1, $this->storedFlag($connection, $inColumn));
        static::assertSame(0, $this->storedFlag($connection, $outsideColumn));
    }

    private function backfillStatement(): string
    {
        $factory = self::getContainer()->get('doctrine.migrations.dependency_factory');
        static::assertInstanceOf(DependencyFactory::class, $factory);

        $migration = $factory
            ->getMigrationRepository()
            ->getMigration(new Version(Version20260822120000::class))
            ->getMigration();
        $migration->up(new Schema());

        foreach ($migration->getSql() as $query) {
            if (str_starts_with($query->getStatement(), 'UPDATE ')) {
                return $query->getStatement();
            }
        }

        static::fail('The migration carries no backfill statement');
    }

    private function storedFlag(Connection $connection, Cms $cms): int
    {
        return (int) $connection->fetchOne('SELECT email_footer FROM cms WHERE id = ?', [$cms->getId()]);
    }

    private function page(EntityManagerInterface $em, string $slug, MenuLocation $location): Cms
    {
        $cms = new Cms();
        $cms->setSlug($slug);
        $cms->setCreatedAt(new DateTimeImmutable());
        $cms->setCreatedBy($em->getRepository(User::class)->findOneBy([]));
        $cms->setPublished(true);
        $cms->setLocked(false);
        $cms->setEmailFooter(false);

        $menuLocation = new CmsMenuLocation();
        $menuLocation->setCms($cms);
        $menuLocation->setLocation($location);
        $cms->addMenuLocation($menuLocation);

        $em->persist($cms);

        return $cms;
    }
}
