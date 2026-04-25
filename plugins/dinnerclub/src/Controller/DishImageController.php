<?php declare(strict_types=1);

namespace Plugin\Dinnerclub\Controller;

use App\Activity\ActivityService;
use App\Controller\AbstractController;
use App\Enum\ImageType;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use Plugin\Dinnerclub\Activity\Messages\ImageSuggestionCreated;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Enum\DishImageSuggestionType;
use Plugin\Dinnerclub\Repository\DishImageRepository;
use Plugin\Dinnerclub\Repository\DishRepository;
use Plugin\Dinnerclub\Service\DishService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/dinnerclub/image')]
#[IsGranted('ROLE_USER')]
final class DishImageController extends AbstractController
{
    public function __construct(
        private readonly DishRepository $dishRepository,
        private readonly DishImageRepository $dishImageRepository,
        private readonly DishService $dishService,
        private readonly ImageService $imageService,
        private readonly ActivityService $activityService,
        private readonly ImageLocationService $imageLocationService,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('/upload/{id}', name: 'plugin_dinnerclub_image_upload', methods: ['POST'])]
    public function upload(Request $request, int $id): Response
    {
        $dish = $this->dishRepository->find($id);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        $file = $request->files->get('image');
        if ($file === null) {
            $this->addFlash('error', $this->translator->trans('dinnerclub.flash_no_image'));

            return $this->redirectToRoute('plugin_dinnerclub_item_show', ['id' => $id]);
        }

        $user = $this->getAuthedUser();
        $image = $this->imageService->upload($file, $user, ImageType::PluginDish);
        if ($image === null) {
            $this->addFlash('error', $this->translator->trans('dinnerclub.flash_image_error'));

            return $this->redirectToRoute('plugin_dinnerclub_item_show', ['id' => $id]);
        }

        $this->imageService->createThumbnails($image, ImageType::PluginDish);

        if ($this->isGranted('ROLE_ORGANIZER')) {
            $this->dishService->addGalleryImage($dish, $image);
            $this->addFlash('success', $this->translator->trans('dinnerclub.flash_image_added'));
            return $this->redirect($this->getReturnUrl($request, $this->generateUrl('plugin_dinnerclub_item_show', ['id' => $id])));
        }

        $suggestion = $this->dishService->addImageSuggestion($dish, $image, DishImageSuggestionType::AddImage, $user->getId());
        $this->activityService->log(ImageSuggestionCreated::TYPE, $user, [
            'dish_id' => $id,
            'dish_name' => $this->getDishName($dish),
            'suggestion_type' => $suggestion->getType()?->value,
        ]);
        $this->addFlash('success', $this->translator->trans('dinnerclub.flash_image_suggestion'));
        return $this->redirect($this->getReturnUrl($request, $this->generateUrl('plugin_dinnerclub_item_show', ['id' => $id])));
    }

    #[Route('/suggest-preview/{dishId}/{dishImageId}', name: 'plugin_dinnerclub_suggest_preview', methods: ['GET'])]
    public function suggestPreview(Request $request, int $dishId, int $dishImageId): Response
    {
        $dish = $this->dishRepository->find($dishId);
        if ($dish === null) {
            throw $this->createNotFoundException('Dish not found');
        }

        $dishImage = $this->dishImageRepository->find($dishImageId);
        if ($dishImage === null || $dishImage->getDish()?->getId() !== $dishId) {
            throw $this->createNotFoundException('Gallery image not found');
        }

        $image = $dishImage->getImage();
        if ($image === null) {
            throw $this->createNotFoundException('Image not found');
        }

        $user = $this->getAuthedUser();

        if ($this->isGranted('ROLE_ORGANIZER')) {
            $oldPreviewId = $dish->getPreviewImage()?->getId();
            $dish->setPreviewImage($image);
            $this->dishService->saveBaseData($dish);

            if ($oldPreviewId !== null) {
                $this->imageLocationService->removeLocation($oldPreviewId, ImageType::PluginDish, $dishId);
            }
            $this->imageLocationService->addLocation($image->getId(), ImageType::PluginDish, $dishId);

            $this->addFlash('success', $this->translator->trans('dinnerclub.flash_preview_updated'));
            return $this->redirect($this->getReturnUrl($request, $this->generateUrl('plugin_dinnerclub_item_show', ['id' => $dishId])));
        }

        $suggestion = $this->dishService->addImageSuggestion($dish, $image, DishImageSuggestionType::SetPreview, $user->getId());
        $this->activityService->log(ImageSuggestionCreated::TYPE, $user, [
            'dish_id' => $dishId,
            'dish_name' => $this->getDishName($dish),
            'suggestion_type' => $suggestion->getType()?->value,
        ]);
        $this->addFlash('success', $this->translator->trans('dinnerclub.flash_preview_suggestion'));
        return $this->redirect($this->getReturnUrl($request, $this->generateUrl('plugin_dinnerclub_item_show', ['id' => $dishId])));
    }

    #[Route('/delete/{dishImageId}', name: 'plugin_dinnerclub_image_delete', methods: ['GET'])]
    #[IsGranted('ROLE_ORGANIZER')]
    public function delete(Request $request, int $dishImageId): Response
    {
        $dishImage = $this->dishImageRepository->find($dishImageId);
        if ($dishImage === null) {
            throw $this->createNotFoundException('Gallery image not found');
        }

        $dishId = $dishImage->getDish()?->getId();

        $this->dishService->removeGalleryImage($dishImageId);
        $this->addFlash('success', $this->translator->trans('dinnerclub.flash_image_removed'));

        return $this->redirect($this->getReturnUrl($request, $this->generateUrl('plugin_dinnerclub_item_show', ['id' => $dishId])));
    }

    private function getReturnUrl(Request $request, string $fallback): string
    {
        $url = $request->request->get('returnUrl') ?? $request->query->get('returnUrl', '');
        if (is_string($url) && str_starts_with($url, '/')) {
            return $url;
        }

        return $fallback;
    }

    private function getDishName(Dish $dish): string
    {
        return $dish->getAnyTranslatedName() ?: '[unknown]';
    }
}
