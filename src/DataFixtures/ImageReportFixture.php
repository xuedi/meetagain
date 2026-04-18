<?php declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ImageReport;
use App\Enum\ImageReportReason;
use App\Repository\ImageRepository;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ImageReportFixture extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ImageRepository $imageRepository,
    ) {}

    public static function getGroups(): array
    {
        return ['base'];
    }

    public function load(ObjectManager $manager): void
    {
        echo 'Creating image reports ... ';

        $reporter = $this->userRepository->findOneBy(['name' => 'Abraham Baker']);
        if ($reporter === null) {
            echo 'SKIP (reporter user not found)' . PHP_EOL;

            return;
        }

        $images = $this->imageRepository->findBy([], ['id' => 'ASC'], 1);
        if ($images === []) {
            echo 'SKIP (no images found)' . PHP_EOL;

            return;
        }

        $report = new ImageReport();
        $report->setImage($images[0]);
        $report->setReporter($reporter);
        $report->setReason(ImageReportReason::Inappropriate);
        $report->setRemarks('This image looks suspicious.');

        $manager->persist($report);
        $manager->flush();

        echo 'OK' . PHP_EOL;
    }
}
