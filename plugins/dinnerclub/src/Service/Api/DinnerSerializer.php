<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Service\Api;

use Plugin\Dinnerclub\Entity\Dinner;
use Plugin\Dinnerclub\Entity\DinnerCourse;
use Plugin\Dinnerclub\Entity\DinnerCourseItem;

readonly class DinnerSerializer
{
    public function __construct(
        private DishSerializer $dishSerializer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSummary(Dinner $dinner): array
    {
        $event = $dinner->getEvent();

        return [
            'id' => $dinner->getId(),
            'eventId' => $event?->getId(),
            'eventTitle' => $event?->getTitle('en'),
            'startsAt' => $event?->getStart()?->format('c'),
            'pricePerPerson' => $dinner->getPricePerPerson(),
            'reservationName' => $dinner->getReservationName(),
            'courseCount' => $dinner->getCourses()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetail(Dinner $dinner, string $locale, string $baseUrl): array
    {
        $courses = [];
        foreach ($dinner->getCourses() as $course) {
            $courses[] = $this->serializeCourse($course, $locale, $baseUrl);
        }

        return [
            ...$this->toSummary($dinner),
            'courses' => $courses,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCourse(DinnerCourse $course, string $locale, string $baseUrl): array
    {
        $dishes = [];
        foreach ($course->getItems() as $item) {
            $dishes[] = $this->serializeCourseItem($item, $locale, $baseUrl);
        }

        return [
            'id' => $course->getId(),
            'name' => $course->getName(),
            'sortOrder' => $course->getSortOrder(),
            'items' => $dishes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCourseItem(DinnerCourseItem $item, string $locale, string $baseUrl): array
    {
        $dish = $item->getDish();

        return [
            'sortOrder' => $item->getSortOrder(),
            'isPrimary' => $item->isPrimary(),
            'dish' => $dish !== null ? $this->dishSerializer->toSummary($dish, $locale, $baseUrl) : null,
        ];
    }
}
