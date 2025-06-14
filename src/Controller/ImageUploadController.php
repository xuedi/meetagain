<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\CmsBlock;
use App\Entity\Image;
use App\Entity\ImageType;
use App\Entity\User;
use App\Form\CmsBlockImageType;
use App\Form\ImageUploadType;
use App\Service\ImageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ImageUploadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageService $imageService
    ) {

    }

    #[Route('/image/{entity}/{id}', name: 'app_image', requirements: ['entity' => 'user|cmsBlock', 'id' => '\d+'])]
    public function index(string $entity, int $id): Response
    {
        $data = $this->prepare($entity, $id);
        $image = $data['image'];
        $gallery = $data['gallery'];

        return $this->render('image/index.html.twig', [
            'imageUploadGallery' => $gallery,
            'image' => $image,
            'entity' => $entity,
            'id' => $id,
        ]);
    }

    #[Route('/image/modal/{entity}/{id}', name: 'app_image_modal', requirements: ['entity' => 'user|cmsBlock', 'id' => '\d+'])]
    public function modal(string $entity, int $id, bool $rotate = false): Response
    {
        $data = $this->prepare($entity, $id);
        $image = $data['image'];
        $gallery = $data['gallery'];

        return $this->render('image/modal.html.twig', [
            'imageUploadGallery' => $gallery,
            'modal' => true,
            'rotate' => ($image === null) ? false : $rotate,
            'image' => $image,
            'entity' => $entity,
            'id' => $id,
        ]);
    }

    #[Route('/image/rotate/{entity}/{id}', name: 'app_image_rotate', requirements: ['entity' => 'user|cmsBlock', 'id' => '\d+'], methods: ['GET'])]
    public function rotate(string $entity, int $id): Response
    {
        $image = $this->prepare($entity, $id)['image'];
        if ($image !== null) {
            $this->imageService->rotateThumbNail($image);
        }

        return $this->redirectToRoute('app_image', [
            'entity' => $entity,
            'id' => $id,
        ]);
    }

    #[Route('/image/{entity}/{id}/upload', name: 'app_image_upload', requirements: ['entity' => 'user|cmsBlock', 'id' => '\d+'], methods: ['POST'])]
    public function upload(Request $request, string $entity, int $id): Response
    {
        $entityString = $entity;
        $data = $this->prepare($entityString, $id);
        $entity = $data['entity'];
        $imageType = $data['imageType'];

        $form = $this->createForm(ImageUploadType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $imageData = $form->get('newImage')->getData();
            if ($imageData instanceof UploadedFile) {
                $image = $this->imageService->upload($imageData, $this->getUser(), $imageType);
                $image->setUploader($this->getUser());
                $image->setUpdatedAt(new DateTimeImmutable());
                $this->em->persist($image);
                $this->imageService->createThumbnails($image, $imageType);
                $this->em->flush();

                // associate image with entity
                $entity->setImage($image);
                $this->em->persist($entity);
                $this->em->flush();
            }
        }

        return $this->redirectToRoute('app_image', [
            'entity' => $entityString,
            'id' => $id,
        ]);
    }

    #[Route('/image/{entity}/{id}/gallery/select/{newImage}', name: 'app_image_gallery_select', requirements: ['entity' => 'user|cmsBlock', 'id' => '\d+'])]
    public function select(string $entity, int $id, int $newImage): Response
    {
        $entityString = $entity;

        $entity = $this->prepare($entityString, $id)['entity'];
        $entity->setImage($this->em->getRepository(Image::class)->findOneBy(['id' => $newImage]));

        $this->em->persist($entity);
        $this->em->flush();

        // TODO: replace for dynamic
        return $this->redirectToRoute('app_image', [
            'entity' => $entityString,
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
                $gallery = $this->em->getRepository(Image::class)->findBy([
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
                'link' => $this->generateUrl('app_image_gallery_select', [
                    'entity' => $entityString,
                    'id' => $id,
                    'newImage' => $item->getId(),
                ])
            ];
        }

        return [
            'imageType' => $imageType,
            'entity' => $entity,
            'image' => $image,
            'gallery' => $extendedGallery,
        ];
    }
}
