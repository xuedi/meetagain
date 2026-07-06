<?php declare(strict_types=1);

namespace App\Service\Media\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;

final class ProfilePictureImageTypeDefinition extends AbstractImageTypeDefinition
{
    public function __construct(
        ImageLocationRepository $repo,
        Connection $connection,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct($repo, $connection);
    }

    public function getType(): ImageType
    {
        return ImageType::ProfilePicture;
    }

    protected function sizes(): array
    {
        return [[400, 400], [350, 350], [80, 80]];
    }

    public function getEditLink(int $locationId): ?array
    {
        return ['route' => 'app_admin_member_edit', 'params' => ['id' => $locationId]];
    }

    public function discoverImageIds(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT image_id, id AS location_id FROM `user` WHERE image_id IS NOT NULL');

        return array_map(static fn(array $r) => [
            'imageId' => (int) $r['image_id'],
            'locationId' => (int) $r['location_id'],
        ], $rows);
    }

    public function locate(Image $image): ?array
    {
        $user = $this->userRepository->findOneBy(['image' => $image]);
        if ($user === null) {
            return null;
        }

        return [
            'label' => sprintf('Profile picture: %s', $user->getName()),
            'route' => 'app_admin_member_edit',
            'params' => ['id' => $user->getId()],
        ];
    }
}
