<?php declare(strict_types=1);

namespace App\Controller;

use App\Activity\ActivityService;
use App\Activity\Messages\EventImageUploaded;
use App\Activity\Messages\UpdatedProfilePicture;
use App\Entity\CmsBlock;
use App\Entity\Event;
use App\Entity\Image;
use App\Entity\User;
use App\EntityActionDispatcher;
use App\Enum\EntityAction;
use App\Enum\ImageType;
use App\Filter\Image\ImageGalleryFilterService;
use App\Form\EventUploadType;
use App\Form\ImageUploadType;
use App\Repository\CmsBlockRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ImageUploadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageService $imageService,
        private readonly CmsBlockRepository $cmsBlockRepo,
        private readonly ActivityService $activityService,
        private readonly ImageGalleryFilterService $imageGalleryFilterService,
        private readonly EntityActionDispatcher $entityActionDispatcher,
        private readonly ImageLocationService $imageLocationService,
        private readonly Security $security,
    ) {}

    #[Route('/image/{entity}/{id}/modal', name: 'app_image_modal', requirements: [
        'entity' => 'user|cmsBlock|event',
        'id' => '\d+',
    ])]
    public function imageModal(string $entity, int $id): Response
    {
        $data = $this->prepare($entity, $id);
        $templateVars = [
            'imageUploadGallery' => $data['gallery'],
            'image' => $data['image'],
            'entity' => $entity,
            'id' => $id,
        ];

        if ($entity === 'event') {
            $uploadUrl = $this->generateUrl('app_event_image_upload', ['id' => $id]);
            $form = $this->createForm(EventUploadType::class, null, ['action' => $uploadUrl]);
            $templateVars['uploadForm'] = $form->createView();
        }
        if ($entity !== 'event') {
            $templateVars['uploadUrl'] = $this->generateUrl('app_replace_image_upload', [
                'entity' => $entity,
                'id' => $id,
            ]);
        }

        return new Response($this->renderView('image/modal_content.html.twig', $templateVars));
    }

    #[Route('/image/{entity}/{id}/select/{newImage}', name: 'app_replace_image_select', requirements: [
        'entity' => 'user|cmsBlock',
        'id' => '\d+',
    ])]
    public function select(string $entity, int $id, int $newImage): Response
    {
        $entityName = $entity;
        $data = $this->prepare($entityName, $id);
        $entity = $data['entity'];
        $imageType = $data['imageType'];
        $previousImage = $entity?->getImage()?->getId() ?? 0;
        $entity->setImage($this->em->getRepository(Image::class)->findOneBy(['id' => $newImage]));

        $this->em->persist($entity);
        $this->em->flush();

        if ($previousImage > 0) {
            $this->imageLocationService->removeLocation($previousImage, $imageType, $id);
        }
        $this->imageLocationService->addLocation($newImage, $imageType, $id);

        $this->logActivity($entityName, $previousImage, $newImage);

        return $this->returnBackToImage($entityName, $id);
    }

    #[Route(
        '/image/{entity}/{id}/upload/replacement',
        name: 'app_replace_image_upload',
        requirements: ['entity' => 'user|cmsBlock', 'id' => '\d+'],
        methods: ['POST'],
    )]
    public function upload(Request $request, string $entity, int $id): Response
    {
        $entityName = $entity;
        $data = $this->prepare($entityName, $id);
        $entity = $data['entity'];
        $imageType = $data['imageType'];
        $previousImage = $entity?->getImage()?->getId() ?? 0;

        $form = $this->createForm(ImageUploadType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() || $form->isValid()) {
            $imageData = $form->get('newImage')->getData();
            if ($imageData instanceof UploadedFile) {
                $image = $this->imageService->upload($imageData, $this->getAuthedUser(), $imageType);
                $image->setUploader($this->getAuthedUser());
                $image->setUpdatedAt(new DateTimeImmutable());
                $this->em->persist($image);
                $this->imageService->createThumbnails($image, $imageType);
                $this->em->flush();
                $this->entityActionDispatcher->dispatch(EntityAction::CreateImage, $image->getId());

                // associate image with the entity
                $entity->setImage($image);
                $this->em->persist($entity);
                $this->em->flush();

                if ($previousImage > 0) {
                    $this->imageLocationService->removeLocation($previousImage, $imageType, $id);
                }
                $this->imageLocationService->addLocation($entity->getImage()->getId(), $imageType, $id);

                $this->logActivity($entityName, $previousImage, $entity->getImage()->getId());
                $this->addFlash('success', 'profile_image.flash_image_changed');

                return $this->returnBackToImage($entityName, $id);
            }
        }

        $this->addFlash('error', 'profile_image.flash_upload_error');

        return $this->returnBackToImage($entityName, $id);
    }

    #[Route(
        '/image/event/{id}/upload',
        name: 'app_event_image_upload',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function uploadEventImages(Request $request, int $id): Response
    {
        $user = $this->getAuthedUser();
        $event = $this->em->getRepository(Event::class)->findOneBy(['id' => $id]);

        if ($event === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isGranted('event.upload', $event)) {
            $this->addFlash('warning', 'events.flash_group_only');

            return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
        }

        $form = $this->createForm(EventUploadType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $files = $form->get('files')->getData();
            $count = $this->imageService->uploadForEvent($event, $files, $user);

            $this->activityService->log(EventImageUploaded::TYPE, $user, [
                'event_id' => $event->getId(),
                'images' => $count,
            ]);
        }

        return $this->redirectToRoute('app_event_details', ['id' => $id]);
    }

    #[Route(
        '/image/rotate/{entity}/{id}',
        name: 'app_image_rotate',
        requirements: ['entity' => 'user|cmsBlock', 'id' => '\d+'],
        methods: ['GET'],
    )]
    public function rotate(string $entity, int $id): Response
    {
        $image = $this->prepare($entity, $id)['image'];
        if ($image !== null) {
            $this->imageService->rotateThumbNail($image);
        }

        return $this->returnBackToImage($entity, $id);
    }

    private function prepare(string $entityString, int $id): array
    {
        switch ($entityString) {
            case 'user':
                $imageType = ImageType::ProfilePicture;
                $entity = $this->em->getRepository(User::class)->findOneBy(['id' => $id]);
                if ($entity === null || $entity->getId() !== $this->getAuthedUser()->getId()) {
                    throw new Exception('You cant change other user profile picture');
                }
                $image = $entity->getImage();
                $rawGallery = $this->em
                    ->getRepository(Image::class)
                    ->findBy([
                        'type' => ImageType::ProfilePicture,
                        'uploader' => $entity,
                    ]);
                $extendedGallery = $this->buildSelectableGallery($rawGallery, $image, $entityString, $id);
                break;
            case 'cmsBlock':
                if (!$this->security->isGranted('ROLE_ADMIN')) {
                    throw new AccessDeniedException();
                }
                $imageType = ImageType::CmsBlock;
                $entity = $this->em->getRepository(CmsBlock::class)->findOneBy(['id' => $id]);
                $image = $entity->getImage();
                $rawGallery = $this->em->getRepository(Image::class)->findBy(['type' => ImageType::CmsBlock]);
                $rawGallery = $this->imageGalleryFilterService->applyFilter($rawGallery, $imageType);
                $extendedGallery = $this->buildSelectableGallery($rawGallery, $image, $entityString, $id);
                break;
            case 'event':
                $imageType = ImageType::EventUpload;
                $entity = $this->em->getRepository(Event::class)->findOneBy(['id' => $id]);
                $image = null;
                $extendedGallery = array_map(static fn(Image $item) => [
                    'image' => $item,
                    'link' => null,
                ], $entity->getImages()->toArray());
                break;
            default:
                throw new Exception('Invalid entity');
        }

        return [
            'imageType' => $imageType,
            'entity' => $entity,
            'image' => $image,
            'gallery' => $extendedGallery,
        ];
    }

    private function buildSelectableGallery(
        array $rawGallery,
        ?Image $currentImage,
        string $entityString,
        int $id,
    ): array {
        if ($currentImage === null) {
            return [];
        }

        $gallery = [];
        foreach ($rawGallery as $item) {
            if ($item->getId() === $currentImage->getId()) {
                continue;
            }
            $gallery[] = [
                'image' => $item,
                'link' => $this->generateUrl('app_replace_image_select', [
                    'entity' => $entityString,
                    'id' => $id,
                    'newImage' => $item->getId(),
                ]),
            ];
        }

        return $gallery;
    }

    private function returnBackToImage(string $entity, int $id): Response
    {
        return match ($entity) {
            'user' => $this->redirectToRoute('app_profile'),
            'cmsBlock' => $this->redirectToRoute('app_admin_cms_edit', [
                'id' => $this->cmsBlockRepo
                    ->findOneBy(['id' => $id])
                    ->getPage()
                    ->getId(),
            ]),
            default => throw new RuntimeException('Invalid entity'),
        };
    }

    private function logActivity(string $entity, int $previous, int $new): void
    {
        if ($entity === 'user') {
            $this->activityService->log(UpdatedProfilePicture::TYPE, $this->getAuthedUser(), [
                'old' => $previous,
                'new' => $new,
            ]);
        }
    }
}
