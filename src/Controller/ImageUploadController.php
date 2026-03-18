<?php declare(strict_types=1);

namespace App\Controller;

use App\Authorization\Action\ActionAuthorizationMessageService;
use App\Authorization\Action\ActionAuthorizationService;
use App\Entity\ActivityType;
use App\Entity\CmsBlock;
use App\Entity\Event;
use App\Entity\Image;
use App\Entity\ImageType;
use App\Entity\User;
use App\Enum\EntityAction;
use App\Filter\Image\ImageGalleryFilterService;
use App\Form\EventUploadType;
use App\Form\ImageUploadType;
use App\Repository\CmsBlockRepository;
use App\Service\Activity\ActivityService;
use App\EntityActionDispatcher;
use App\Service\Media\ImageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ImageUploadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageService $imageService,
        private readonly CmsBlockRepository $cmsBlockRepo,
        private readonly ActivityService $activityService,
        private readonly ActionAuthorizationService $actionAuthService,
        private readonly ActionAuthorizationMessageService $authMessageService,
        private readonly ImageGalleryFilterService $imageGalleryFilterService,
        private readonly EntityActionDispatcher $entityActionDispatcher,
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
        } else {
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
        $entity = $this->prepare($entityName, $id)['entity'];
        $previousImage = $entity?->getImage()?->getId() ?? 0;
        $entity->setImage($this->em->getRepository(Image::class)->findOneBy(['id' => $newImage]));

        $this->em->persist($entity);
        $this->em->flush();

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

                $this->logActivity($entityName, $previousImage, $entity->getImage()->getId());
                $this->addFlash('success', 'Changed Image');

                return $this->returnBackToImage($entityName, $id);
            }
        }

        $this->addFlash('error', 'There was an error uploading the file.');

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
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getAuthedUser();
        $event = $this->em->getRepository(Event::class)->findOneBy(['id' => $id]);

        if ($event === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->actionAuthService->isActionAllowed('event.upload', $event->getId(), $user)) {
            $unauthorizedMsg = $this->authMessageService->getUnauthorizedMessage(
                'event.upload',
                $event->getId(),
                $user,
            );
            $this->addFlash($unauthorizedMsg->type->value, $unauthorizedMsg->message);

            return $this->redirectToRoute('app_event_details', ['id' => $event->getId()]);
        }

        $form = $this->createForm(EventUploadType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $files = $form->get('files')->getData();
            $count = $this->imageService->uploadForEvent($event, $files, $user);

            $this->activityService->log(ActivityType::EventImageUploaded, $user, [
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
                $this->denyAccessUnlessGranted('ROLE_USER');
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
                $this->denyAccessUnlessGranted('ROLE_FOUNDER');
                $imageType = ImageType::CmsBlock;
                $entity = $this->em->getRepository(CmsBlock::class)->findOneBy(['id' => $id]);
                $image = $entity->getImage();
                $rawGallery = $this->em->getRepository(Image::class)->findBy(['type' => ImageType::CmsBlock]);
                $rawGallery = $this->imageGalleryFilterService->applyFilter($rawGallery, $imageType);
                $extendedGallery = $this->buildSelectableGallery($rawGallery, $image, $entityString, $id);
                break;
            case 'event':
                $this->denyAccessUnlessGranted('ROLE_USER');
                $imageType = ImageType::EventUpload;
                $entity = $this->em->getRepository(Event::class)->findOneBy(['id' => $id]);
                $image = null;
                $extendedGallery = array_map(fn(Image $item) => [
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
            $this->activityService->log(ActivityType::UpdatedProfilePicture, $this->getAuthedUser(), [
                'old' => $previous,
                'new' => $new,
            ]);
        }
    }
}
