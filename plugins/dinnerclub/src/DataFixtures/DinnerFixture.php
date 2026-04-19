<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\DataFixtures;

use App\DataFixtures\AbstractFixture;
use App\Entity\Event;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Plugin\Dinnerclub\Entity\Dinner;
use Plugin\Dinnerclub\Entity\DinnerCourse;
use Plugin\Dinnerclub\Entity\DinnerCourseItem;
use Plugin\Dinnerclub\Repository\DishRepository;

class DinnerFixture extends AbstractFixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly DishRepository $dishRepository,
    ) {}

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $dishes = $this->dishRepository->findApproved();
        if (count($dishes) < 2) {
            echo 'Creating dinners ... SKIP (not enough approved dishes)' . PHP_EOL;
            return;
        }

        $events = $manager->getRepository(Event::class)->findBy([], ['start' => 'DESC'], 2);
        if ($events === []) {
            echo 'Creating dinners ... SKIP (no events found)' . PHP_EOL;
            return;
        }

        $organizer = $manager->getRepository(User::class)->findOneBy(['email' => 'organizer@example.com']);
        if (!$organizer instanceof User) {
            $organizer = $manager->getRepository(User::class)->findOneBy([]);
        }
        if ($organizer === null) {
            echo 'Creating dinners ... SKIP (no users found)' . PHP_EOL;
            return;
        }

        echo 'Creating dinners ... ';

        $courseNames = ['Starter', 'Main', 'Dessert'];

        foreach ($events as $eventIndex => $event) {
            $dinner = new Dinner();
            $dinner->setEvent($event);
            $dinner->setCreatedBy($organizer->getId());
            $dinner->setCreatedAt(new DateTimeImmutable());
            $manager->persist($dinner);

            foreach ($courseNames as $sortOrder => $courseName) {
                $course = new DinnerCourse();
                $course->setDinner($dinner);
                $course->setName($courseName);
                $course->setSortOrder($sortOrder);
                $manager->persist($course);

                // Primary dish
                $primaryDish = $dishes[((int) $eventIndex * 3 + $sortOrder) % count($dishes)];
                $primary = new DinnerCourseItem();
                $primary->setCourse($course);
                $primary->setDish($primaryDish);
                $primary->setIsPrimary(true);
                $primary->setSortOrder(0);
                $manager->persist($primary);

                // Alternative dish (if there are enough dishes and it's not Dessert)
                if ($courseName !== 'Dessert' && count($dishes) > 1) {
                    $altDish = $dishes[((int) $eventIndex * 3 + $sortOrder + 1) % count($dishes)];
                    if ($altDish->getId() !== $primaryDish->getId()) {
                        $alt = new DinnerCourseItem();
                        $alt->setCourse($course);
                        $alt->setDish($altDish);
                        $alt->setIsPrimary(false);
                        $alt->setSortOrder(1);
                        $manager->persist($alt);
                    }
                }
            }
        }

        $manager->flush();
        echo 'OK' . PHP_EOL;
    }

    public static function getGroups(): array
    {
        return ['plugin'];
    }
}
