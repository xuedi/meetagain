<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Service;

use App\Entity\Event;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Dinnerclub\Entity\Dinner;
use Plugin\Dinnerclub\Entity\DinnerCourse;
use Plugin\Dinnerclub\Entity\DinnerCourseItem;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Repository\DinnerCourseItemRepository;
use Plugin\Dinnerclub\Repository\DinnerCourseRepository;
use Plugin\Dinnerclub\Repository\DinnerRepository;
use RuntimeException;

readonly class DinnerService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DinnerRepository $dinnerRepo,
        private DinnerCourseRepository $courseRepo,
        private DinnerCourseItemRepository $itemRepo,
    ) {}

    public function createDinner(Event $event, int $userId): Dinner
    {
        if ($this->dinnerRepo->findByEventId($event->getId()) !== null) {
            throw new RuntimeException('A dinner already exists for this event.');
        }

        $dinner = new Dinner();
        $dinner->setEvent($event);
        $dinner->setCreatedBy($userId);
        $dinner->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($dinner);
        $this->em->flush();

        return $dinner;
    }

    public function getDinnerByEventId(int $eventId): ?Dinner
    {
        return $this->dinnerRepo->findByEventId($eventId);
    }

    public function removeDinner(Dinner $dinner): void
    {
        $this->em->remove($dinner);
        $this->em->flush();
    }

    public function addCourse(Dinner $dinner, string $name): DinnerCourse
    {
        $course = new DinnerCourse();
        $course->setDinner($dinner);
        $course->setName($name);
        $course->setSortOrder($this->courseRepo->getNextSortOrder($dinner));

        $this->em->persist($course);
        $this->em->flush();

        return $course;
    }

    public function updateCourse(DinnerCourse $course, string $name): void
    {
        $course->setName($name);
        $this->em->flush();
    }

    public function removeCourse(DinnerCourse $course): void
    {
        $this->em->remove($course);
        $this->em->flush();
    }

    public function addDishToCourse(DinnerCourse $course, Dish $dish): DinnerCourseItem
    {
        $item = new DinnerCourseItem();
        $item->setCourse($course);
        $item->setDish($dish);
        $item->setIsPrimary(false);
        $item->setSortOrder($this->itemRepo->getNextSortOrder($course));

        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    public function removeDishFromCourse(DinnerCourseItem $item): void
    {
        $this->em->remove($item);
        $this->em->flush();
    }

    public function updatePricePerPerson(Dinner $dinner, ?float $price): void
    {
        $dinner->setPricePerPerson($price);
        $this->em->flush();
    }

    public function updateReservationName(Dinner $dinner, ?string $name): void
    {
        $dinner->setReservationName($name !== '' ? $name : null);
        $this->em->flush();
    }

}
