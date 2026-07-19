<?php declare(strict_types=1);

namespace Tests\Functional\Repository;

use App\Entity\Image;
use App\Entity\ImageLocation;
use App\Entity\User;
use App\Enum\ImageType;
use App\Repository\ImageRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ImageRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ImageRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(ImageRepository::class);
    }

    public function testFindHighUsageMissingAltReturnsHighUsageCandidatesRegardlessOfAltState(): void
    {
        // Arrange
        $uploader = $this->em->getRepository(User::class)->findOneBy([]);
        static::assertInstanceOf(User::class, $uploader);

        // Used more than once with a base alt set - still a candidate, since completeness is decided in PHP.
        $highUsage = $this->persistImage($uploader, 'a base alt');
        $this->persistLocation($highUsage, 42001);
        $this->persistLocation($highUsage, 42002);
        $this->persistLocation($highUsage, 42003);

        // Single-usage image stays below the "used more than once" gate.
        $singleUsage = $this->persistImage($uploader, null);
        $this->persistLocation($singleUsage, 42004);

        $this->em->flush();

        // Act
        $candidates = $this->repo->findHighUsageMissingAlt();

        // Assert
        $counts = [];
        foreach ($candidates as $candidate) {
            $counts[$candidate['image']->getId()] = $candidate['count'];
        }

        static::assertArrayHasKey($highUsage->getId(), $counts, 'High-usage image must be a candidate even with a base alt.');
        static::assertSame(3, $counts[$highUsage->getId()]);
        static::assertArrayNotHasKey($singleUsage->getId(), $counts, 'Single-usage image must be excluded.');
    }

    private function persistImage(User $uploader, ?string $alt): Image
    {
        $image = new Image();
        $image->setMimeType('image/webp');
        $image->setExtension('webp');
        $image->setSize(1234);
        $image->setHash(bin2hex(random_bytes(16)));
        $image->setAlt($alt);
        $image->setUploader($uploader);
        $image->setType(ImageType::EventUpload);
        $image->setCreatedAt(new DateTimeImmutable());
        $this->em->persist($image);

        return $image;
    }

    private function persistLocation(Image $image, int $locationId): void
    {
        $location = new ImageLocation();
        $location->setImage($image);
        $location->setLocationType(ImageType::EventUpload);
        $location->setLocationId($locationId);
        $this->em->persist($location);
    }
}
