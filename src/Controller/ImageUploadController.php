<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\ActivityType;
use App\Entity\CmsBlock;
use App\Entity\Image;
use App\Entity\ImageType;
use App\Entity\User;
use App\Form\ImageUploadType;
use App\Repository\CmsBlockRepository;
use App\Service\ActivityService;
use App\Service\ImageService;
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
    ) {
    }

    #[Route('/replace_image/{entity}/{id}', name: 'app_image_replace', requirements: [
        'entity' => 'user|cmsBlock',
        'id' => '\d+',
    ])]
    public function imageReplace(string $entity, int $id): Response
    {
        $response = $this->getResponse();
        $data = $this->prepare($entity, $id);
        $image = $data['image'];
        $gallery = $data['gallery'];

        return $this->render(
            'image/index.html.twig',
            [
                'imageUploadGallery' => $gallery,
                'image' => $image,
                'entity' => $entity,
                'id' => $id,
            ],
            $response,
        );
    }

    #[Route('/replace_image/modal/{entity}/{id}', name: 'app_image_replace_modal', requirements: [
        'entity' => 'user|cmsBlock',
        'id' => '\d+',
    ])]
    public function imageReplaceModal(string $entity, int $id, bool $rotate = false): Response
    {
        $response = $this->getResponse();
        $data = $this->prepare($entity, $id);
        $image = $data['image'];
        $gallery = $data['gallery'];

        return $this->render(
            'image/image_with_modal.html.twig',
            [
                'imageUploadGallery' => $gallery,
                'modal' => true,
                'rotate' => $image === null ? false : $rotate,
                'image' => $image,
                'entity' => $entity,
                'id' => $id,
            ],
            $response,
        );
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
                $image->setUploader($this->getUser());
                $image->setUpdatedAt(new DateTimeImmutable());
                $this->em->persist($image);
                $this->imageService->createThumbnails($image, $imageType);
                $this->em->flush();

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

    #[Route('/add_image/{entity}/{id}', name: 'app_image_add', requirements: [
        'entity' => 'user|cmsBlock',
        'id' => '\d+',
    ])]
    public function imageAdd(string $entity, int $id): Response
    {
        // Upload multiple images and return

        return $this->render('image/index.html.twig', [
            'entity' => $entity,
            'id' => $id,
        ]);
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

        // TODO: add ajax returns

        return $this->redirectToRoute('app_image_replace', [
            'entity' => $entity,
            'id' => $id,
        ]);
    }

    private function prepare(string $entityString, int $id): array
    {
        switch ($entityString) {
            case 'user':
                $this->denyAccessUnlessGranted('ROLE_USER');
                $imageType = ImageType::ProfilePicture;
                $entity = $this->em->getRepository(User::class)->findOneBy(['id' => $id]);
                if ($entity !== $this->getAuthedUser()) {
                    throw new Exception('You cant change other user profile picture');
                }
                $gallery = $this->em
                    ->getRepository(Image::class)
                    ->findBy([
                        'type' => ImageType::ProfilePicture,
                        'uploader' => $entity,
                    ]);
                break;
            case 'cmsBlock':
                $this->denyAccessUnlessGranted('ROLE_ADMIN');
                $imageType = ImageType::CmsBlock;
                $entity = $this->em->getRepository(CmsBlock::class)->findOneBy(['id' => $id]);
                $gallery = $this->em->getRepository(Image::class)->findBy(['type' => ImageType::CmsBlock]);
                break;
            default:
                throw new Exception('Invalid entity');
        }

        $extendedGallery = [];
        $image = $entity->getImage();
        foreach ($gallery as $item) {
            if ($image === null || $item->getId() === $image->getId()) {
                continue;
            }
            $extendedGallery[] = [
                'image' => $item,
                'link' => $this->generateUrl('app_replace_image_select', [
                    'entity' => $entityString,
                    'id' => $id,
                    'newImage' => $item->getId(),
                ]),
            ];
        }

        return [
            'imageType' => $imageType,
            'entity' => $entity,
            'image' => $image,
            'gallery' => $extendedGallery,
        ];
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
