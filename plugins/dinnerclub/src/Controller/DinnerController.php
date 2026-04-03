<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Controller;

use App\Controller\AbstractController;
use App\Repository\EventRepository;
use Plugin\Dinnerclub\Repository\DinnerCourseItemRepository;
use Plugin\Dinnerclub\Repository\DinnerCourseRepository;
use Plugin\Dinnerclub\Repository\DinnerRepository;
use Plugin\Dinnerclub\Service\DinnerService;
use Plugin\Dinnerclub\Service\DishService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dinnerclub/dinner')]
#[IsGranted('ROLE_ORGANIZER')]
final class DinnerController extends AbstractController
{
    public function __construct(
        private readonly DinnerService $dinnerService,
        private readonly DishService $dishService,
        private readonly EventRepository $eventRepository,
        private readonly DinnerRepository $dinnerRepository,
        private readonly DinnerCourseRepository $courseRepository,
        private readonly DinnerCourseItemRepository $itemRepository,
    ) {}

    #[Route('/manage/{eventId}', name: 'app_plugin_dinnerclub_dinner_manage', methods: ['GET'])]
    public function manage(int $eventId): Response
    {
        $event = $this->eventRepository->find($eventId);
        if ($event === null) {
            throw $this->createNotFoundException('Event not found');
        }

        return $this->render('@Dinnerclub/dinner/manage.html.twig', [
            'event' => $event,
            'dinner' => $this->dinnerService->getDinnerByEventId($eventId),
            'dishes' => $this->dishService->getApprovedDishes(),
        ]);
    }

    #[Route('/create/{eventId}', name: 'app_plugin_dinnerclub_dinner_create', methods: ['POST'])]
    public function create(int $eventId): Response
    {
        $event = $this->eventRepository->find($eventId);
        if ($event === null) {
            throw $this->createNotFoundException('Event not found');
        }

        $this->dinnerService->createDinner($event, $this->getAuthedUser()->getId());

        return $this->redirectToRoute('app_plugin_dinnerclub_dinner_manage', ['eventId' => $eventId]);
    }

    #[Route('/delete/{dinnerId}', name: 'app_plugin_dinnerclub_dinner_delete', methods: ['POST'])]
    public function delete(int $dinnerId): Response
    {
        $dinner = $this->dinnerRepository->find($dinnerId);
        if ($dinner === null) {
            throw $this->createNotFoundException('Dinner not found');
        }

        $eventId = $dinner->getEvent()->getId();
        $this->dinnerService->removeDinner($dinner);

        return $this->redirectToRoute('app_event_details', ['id' => $eventId]);
    }

    #[Route('/course/add/{dinnerId}', name: 'app_plugin_dinnerclub_dinner_course_add', methods: ['POST'])]
    public function addCourse(int $dinnerId, Request $request): Response
    {
        $dinner = $this->dinnerRepository->find($dinnerId);
        if ($dinner === null) {
            throw $this->createNotFoundException('Dinner not found');
        }

        $name = trim((string) $request->request->get('name', ''));
        if ($name !== '') {
            $this->dinnerService->addCourse($dinner, $name);
        }

        return $this->redirectToRoute('app_plugin_dinnerclub_dinner_manage', ['eventId' => $dinner->getEvent()->getId()]);
    }

    #[Route('/course/edit/{courseId}', name: 'app_plugin_dinnerclub_dinner_course_edit', methods: ['POST'])]
    public function editCourse(int $courseId, Request $request): Response
    {
        $course = $this->courseRepository->find($courseId);
        if ($course === null) {
            throw $this->createNotFoundException('Course not found');
        }

        $name = trim((string) $request->request->get('name', ''));
        if ($name !== '') {
            $this->dinnerService->updateCourse($course, $name);
        }

        return $this->redirectToRoute('app_plugin_dinnerclub_dinner_manage', ['eventId' => $course->getDinner()->getEvent()->getId()]);
    }

    #[Route('/course/delete/{courseId}', name: 'app_plugin_dinnerclub_dinner_course_delete', methods: ['POST'])]
    public function deleteCourse(int $courseId): Response
    {
        $course = $this->courseRepository->find($courseId);
        if ($course === null) {
            throw $this->createNotFoundException('Course not found');
        }

        $eventId = $course->getDinner()->getEvent()->getId();
        $this->dinnerService->removeCourse($course);

        return $this->redirectToRoute('app_plugin_dinnerclub_dinner_manage', ['eventId' => $eventId]);
    }

    #[Route('/item/add/{courseId}', name: 'app_plugin_dinnerclub_dinner_item_add', methods: ['POST'])]
    public function addItem(int $courseId, Request $request): Response
    {
        $course = $this->courseRepository->find($courseId);
        if ($course === null) {
            throw $this->createNotFoundException('Course not found');
        }

        $dishId = (int) $request->request->get('dish_id');
        $dish = $this->dishService->getDish($dishId);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        $isPrimary = $request->request->get('is_primary') === '1';
        $this->dinnerService->addDishToCourse($course, $dish, $isPrimary);

        return $this->redirectToRoute('app_plugin_dinnerclub_dinner_manage', ['eventId' => $course->getDinner()->getEvent()->getId()]);
    }

    #[Route('/item/delete/{itemId}', name: 'app_plugin_dinnerclub_dinner_item_delete', methods: ['POST'])]
    public function deleteItem(int $itemId): Response
    {
        $item = $this->itemRepository->find($itemId);
        if ($item === null) {
            throw $this->createNotFoundException('Item not found');
        }

        $eventId = $item->getCourse()->getDinner()->getEvent()->getId();
        $this->dinnerService->removeDishFromCourse($item);

        return $this->redirectToRoute('app_plugin_dinnerclub_dinner_manage', ['eventId' => $eventId]);
    }

    #[Route('/reservation-name/{dinnerId}', name: 'app_plugin_dinnerclub_dinner_reservation_name', methods: ['POST'])]
    public function updateReservationName(int $dinnerId, Request $request): Response
    {
        $dinner = $this->dinnerRepository->find($dinnerId);
        if ($dinner === null) {
            throw $this->createNotFoundException('Dinner not found');
        }

        $name = trim((string) $request->request->get('reservation_name', ''));
        $this->dinnerService->updateReservationName($dinner, $name);

        return $this->redirectToRoute('app_plugin_dinnerclub_dinner_manage', ['eventId' => $dinner->getEvent()->getId()]);
    }

    #[Route('/price/{dinnerId}', name: 'app_plugin_dinnerclub_dinner_price', methods: ['POST'])]
    public function updatePrice(int $dinnerId, Request $request): Response
    {
        $dinner = $this->dinnerRepository->find($dinnerId);
        if ($dinner === null) {
            throw $this->createNotFoundException('Dinner not found');
        }

        $raw = trim((string) $request->request->get('price_per_person', ''));
        $price = $raw !== '' ? (float) $raw : null;

        $this->dinnerService->updatePricePerPerson($dinner, $price);

        return $this->redirectToRoute('app_plugin_dinnerclub_dinner_manage', ['eventId' => $dinner->getEvent()->getId()]);
    }

    #[Route('/item/toggle-primary/{itemId}', name: 'app_plugin_dinnerclub_dinner_item_toggle', methods: ['POST'])]
    public function togglePrimary(int $itemId): Response
    {
        $item = $this->itemRepository->find($itemId);
        if ($item === null) {
            throw $this->createNotFoundException('Item not found');
        }

        $eventId = $item->getCourse()->getDinner()->getEvent()->getId();
        $this->dinnerService->togglePrimary($item);

        return $this->redirectToRoute('app_plugin_dinnerclub_dinner_manage', ['eventId' => $eventId]);
    }
}
