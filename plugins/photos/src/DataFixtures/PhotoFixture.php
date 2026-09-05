<?php declare(strict_types=1);

namespace Plugin\Photos\DataFixtures;

use App\DataFixtures\AbstractFixture;
use App\DataFixtures\EventFixture;
use App\DataFixtures\UserFixture;
use App\Entity\Event;
use App\Entity\EventItemAssociation;
use App\Entity\EventTranslation;
use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Entity\PhotoTranslation;
use Plugin\Photos\Service\ExifService;
use Plugin\Photos\Service\PhotoService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoFixture extends AbstractFixture implements FixtureGroupInterface
{
    private const string FIXTURE_DIR = __DIR__ . '/../../fixtures';

    public function __construct(
        private readonly ImageService $imageService,
        private readonly ImageLocationService $imageLocationService,
        private readonly ExifService $exifService,
    ) {}

    #[Override]
    public function load(ObjectManager $manager): void
    {
        echo 'Creating photos ... ';

        $created = [];
        $positions = [];
        foreach ($this->getData() as [$file, $owner, $eventTitle, $translations]) {
            $creator = $this->user($manager, $owner);
            if (!$creator instanceof User) {
                continue;
            }

            $photo = $this->createPhoto($manager, $file, $translations, $creator);
            if ($photo === null) {
                continue;
            }
            $created[] = $photo;

            $event = $eventTitle === null ? null : $this->event($manager, $eventTitle);
            if ($event instanceof Event) {
                $positions[$eventTitle] ??= 0;
                $association = new EventItemAssociation();
                $association->setEvent($event);
                $association->setItemType(PhotoService::ITEM_TYPE);
                $association->setItemId((int) $photo->getId());
                $association->setCreatedBy((int) $creator->getId());
                $association->setCreatedAt(new DateTimeImmutable());
                $association->setPosition($positions[$eventTitle]++);
                $manager->persist($association);
            }
        }
        $manager->flush();

        if ($created === []) {
            echo 'skipped' . PHP_EOL;

            return;
        }

        foreach ($created as $photo) {
            $image = $photo->getImage();
            if ($image instanceof Image) {
                $this->imageLocationService->addLocation((int) $image->getId(), ImageType::PluginPhotosPhoto, (int) $photo->getId());
            }
        }

        echo 'OK' . PHP_EOL;
    }

    public static function getGroups(): array
    {
        return ['plugin'];
    }

    /**
     * @param array<string, array<string, string>> $translations
     */
    private function createPhoto(ObjectManager $manager, string $file, array $translations, User $creator): ?Photo
    {
        $path = self::FIXTURE_DIR . '/' . $file;
        if (!is_file($path)) {
            return null;
        }

        $image = $this->imageService->upload(new UploadedFile($path, $file), $creator, ImageType::PluginPhotosPhoto);
        if (!$image instanceof Image) {
            return null;
        }
        $this->imageService->createThumbnails($image, ImageType::PluginPhotosPhoto);

        $meta = $this->exifService->extract($path);

        $photo = new Photo();
        $photo->setImage($image);
        $photo->setCreatedBy((int) $creator->getId());
        $photo->setCreatedAt(new DateTimeImmutable());
        $photo->setMeta($meta);
        $photo->setTakenAt($this->exifService->takenAtOf($meta));

        foreach ($translations as $language => $fields) {
            $translation = new PhotoTranslation();
            $translation->setPhoto($photo);
            $translation->setLanguage($language);
            $translation->setTitle($fields['title']);
            $translation->setDescription($fields['description'] ?? null);
            $photo->addTranslation($translation);
        }

        $manager->persist($photo);
        $manager->flush();

        return $photo;
    }

    private function user(ObjectManager $manager, string $name): ?User
    {
        return $manager->getRepository(User::class)->findOneBy([
            'email' => str_replace(' ', '.', $name) . '@example.org',
        ]);
    }

    private function event(ObjectManager $manager, string $title): ?Event
    {
        $translation = $manager->getRepository(EventTranslation::class)->findOneBy(['title' => $title]);

        return $translation?->getEvent();
    }

    /**
     * @return list<array{0: string, 1: string, 2: string|null, 3: array<string, array<string, string>>}>
     */
    private function getData(): array
    {
        return [
            ['harbour-morning.jpg', UserFixture::ADEM_LANE, EventFixture::WEEKLY_GO_STUDY, [
                'en' => ['title' => 'Harbour, just after sunrise', 'description' => 'The first ferry of the day leaving the old pier.'],
                'de' => ['title' => 'Hafen kurz nach Sonnenaufgang', 'description' => 'Die erste Fähre des Tages legt am alten Pier ab.'],
                'fr' => ['title' => 'Le port, juste après le lever du soleil', 'description' => 'Le premier ferry de la journée quitte la vieille jetée.'],
                'es' => ['title' => 'El puerto, justo después del amanecer', 'description' => 'El primer ferri del día sale del viejo muelle.'],
            ]],
            ['winter-forest.jpg', UserFixture::ADEM_LANE, EventFixture::WEEKLY_GO_STUDY, [
                'en' => ['title' => 'Winter forest', 'description' => 'Cold enough that the tripod head stopped moving.'],
                'fr' => ['title' => 'Forêt d\'hiver', 'description' => 'Il faisait si froid que la rotule du trépied s\'est bloquée.'],
                'es' => ['title' => 'Bosque invernal', 'description' => 'Hacía tanto frío que la rótula del trípode dejó de moverse.'],
            ]],
            ['night-market.jpg', UserFixture::KARI_RASMUSSEN, EventFixture::BERLIN_TOURNAMENT, [
                'en' => ['title' => 'Night market', 'description' => 'Two hundred people and one working light bulb.'],
                'de' => ['title' => 'Nachtmarkt', 'description' => 'Zweihundert Menschen und eine einzige funktionierende Glühbirne.'],
                'fr' => ['title' => 'Marché de nuit', 'description' => 'Deux cents personnes et une seule ampoule en état de marche.'],
                'es' => ['title' => 'Mercado nocturno', 'description' => 'Doscientas personas y una sola bombilla que funcionaba.'],
            ]],
            ['rooftop-portrait.jpg', UserFixture::KARI_RASMUSSEN, EventFixture::BERLIN_TOURNAMENT, [
                'en' => ['title' => 'Rooftop portrait', 'description' => 'Waited out the whole golden hour for this one.'],
                'de' => ['title' => 'Porträt auf dem Dach', 'description' => 'Dafür haben wir die ganze goldene Stunde abgewartet.'],
                'fr' => ['title' => 'Portrait sur les toits', 'description' => 'Nous avons attendu toute l\'heure dorée pour celle-ci.'],
                'es' => ['title' => 'Retrato en la azotea', 'description' => 'Esperamos toda la hora dorada para conseguirla.'],
            ]],
            ['kitchen-table.jpg', UserFixture::KARI_RASMUSSEN, null, [
                'en' => ['title' => 'Kitchen table', 'description' => 'Still life, assembled from whatever was in the fruit bowl.'],
                'de' => ['title' => 'Küchentisch', 'description' => 'Stillleben aus allem, was die Obstschale hergab.'],
                'fr' => ['title' => 'Table de cuisine', 'description' => 'Nature morte composée avec ce qui traînait dans la corbeille à fruits.'],
                'es' => ['title' => 'Mesa de cocina', 'description' => 'Bodegón compuesto con lo que había en el frutero.'],
            ]],
        ];
    }
}
