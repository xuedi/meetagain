<?php declare(strict_types=1);

namespace Plugin\Dishes\Tests\Unit\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Portability\DataCategory;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Repository\UserRepository;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Plugin\Dishes\Entity\Dish;
use Plugin\Dishes\Entity\DishImage;
use Plugin\Dishes\Entity\DishLike;
use Plugin\Dishes\Portability\MemberSection;
use Plugin\Dishes\Repository\DishLikeRepository;
use Plugin\Dishes\Repository\DishRepository;
use Tests\Unit\Portability\Section\SectionTestCase;

final class MemberSectionTest extends SectionTestCase
{
    public function testALikeOfAConsentingMemberTravelsAndOneWithoutConsentStaysBehind(): void
    {
        // Arrange
        $dish = $this->withId(new Dish(), 3);
        $likes = $this->createStub(DishLikeRepository::class);
        $likes->method('findBy')->willReturn([$this->like($dish, 5), $this->like($dish, 6)]);
        $users = $this->createStub(UserRepository::class);
        $users->method('findBy')->willReturn([$this->user(5, 'five@example.org'), $this->user(6, 'six@example.org')]);
        $scope = new Scope(
            users: [5 => 'user', 6 => 'user'],
            itemIds: ['dish' => [3]],
            grants: [5 => [DataCategory::Interactions], 6 => [DataCategory::Uploads]],
        );

        // Act
        $block = $this->section(dishes: $this->dishes([$dish]), likes: $likes, users: $users)->export($scope, $this->images());

        // Assert
        static::assertSame(['likes' => [['dish_ref' => 3, 'email' => 'five@example.org']], 'gallery' => []], $block);
    }

    public function testAGalleryImageOfANonConsentingUploaderStaysBehind(): void
    {
        // Arrange
        $consenting = $this->user(5, 'five@example.org');
        $refusing = $this->user(6, 'six@example.org');
        $dish = $this->withId(new Dish(), 3);
        $dish->getGalleryImages()->add($this->galleryImage(
            11,
            $dish,
            new Image()
                ->setHash('kept')
                ->setUploader($consenting),
            2,
        ));
        $dish->getGalleryImages()->add($this->galleryImage(
            12,
            $dish,
            new Image()
                ->setHash('refused')
                ->setUploader($refusing),
            1,
        ));
        $scope = new Scope(
            users: [5 => 'user', 6 => 'user'],
            itemIds: ['dish' => [3]],
            grants: [5 => [DataCategory::Uploads], 6 => [DataCategory::Interactions]],
        );

        // Act
        $block = $this->section(dishes: $this->dishes([$dish]))->export($scope, $this->images());

        // Assert
        static::assertSame(
            [[
                'dish_ref' => 3,
                'image_file' => 'images/kept.jpg',
                'uploader_email' => 'five@example.org',
                'sort_order' => 2,
                'created_at' => '2026-01-07T19:00:00+00:00',
            ]],
            $block['gallery'],
        );
        static::assertSame([], $block['likes']);
    }

    public function testAWholeInstanceScopeCarriesAGalleryImageNoMemberUploaded(): void
    {
        // Arrange
        $dish = $this->withId(new Dish(), 3);
        $dish->getGalleryImages()->add($this->galleryImage(11, $dish, new Image()->setHash('orphan'), 1));

        // Act
        $block = $this->section(dishes: $this->dishes([$dish]))->export(new Scope(itemIds: ['dish' => [3]], everyUpload: true), $this->images());

        // Assert
        static::assertSame(
            [[
                'dish_ref' => 3,
                'image_file' => 'images/orphan.jpg',
                'uploader_email' => null,
                'sort_order' => 1,
                'created_at' => '2026-01-07T19:00:00+00:00',
            ]],
            $block['gallery'],
        );
    }

    public function testNothingToCarryGivesAnEmptyBlock(): void
    {
        // Act
        $block = $this->section()->export(new Scope(), $this->images());

        // Assert
        static::assertSame([], $block);
    }

    public function testAnImportedLikeKeepsTheCounterInStep(): void
    {
        // Arrange
        $target = $this->withId(new Dish(), 30);
        $target->setLikes(2);
        $context = $this->importContext();
        $context->mapRef(User::class, 'five@example.org', $this->user(50, 'five@example.org'));

        // Act
        $this->section(dishes: $this->dishes([], $target))->import(['likes' => [['dish_ref' => 3, 'email' => 'five@example.org']]], $context);

        // Assert
        $like = $this->onlyPersisted(DishLike::class);
        static::assertSame($target, $like->getDish());
        static::assertSame(50, $like->getUserId());
        static::assertSame(3, $target->getLikes());
        static::assertSame(1, $context->toSummary()->get(MemberSection::KIND_LIKES, Outcome::Created));
    }

    public function testALikeTheTargetAlreadyHoldsIsMatchedAndCountedOnce(): void
    {
        // Arrange
        $target = $this->withId(new Dish(), 30);
        $target->setLikes(1);
        $likes = $this->createStub(DishLikeRepository::class);
        $likes->method('findByDishAndUser')->willReturn(new DishLike());
        $context = $this->importContext();
        $context->mapRef(User::class, 'five@example.org', $this->user(50, 'five@example.org'));

        // Act
        $this->section(dishes: $this->dishes([], $target), likes: $likes)->import([
            'likes' => [['dish_ref' => 3, 'email' => 'five@example.org'], ['dish_ref' => 3, 'email' => 'five@example.org']],
        ], $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame(1, $target->getLikes());
        static::assertSame(2, $context->toSummary()->get(MemberSection::KIND_LIKES, Outcome::Matched));
    }

    public function testALikeOfAMemberTheArchiveDoesNotCarryIsDropped(): void
    {
        // Arrange
        $context = $this->importContext();

        // Act
        $this->section(dishes: $this->dishes([], $this->withId(new Dish(), 30)))->import([
            'likes' => [['dish_ref' => 3, 'email' => 'nobody@example.org']],
        ], $context);

        // Assert
        static::assertSame(1, $context->toSummary()->get(MemberSection::KIND_LIKES, Outcome::Dropped));
    }

    public function testRowsOfADishTypeThisImportDidNotBringAreSkipped(): void
    {
        // Arrange
        $context = $this->context();

        // Act
        $this->section()->import([
            'likes' => [['dish_ref' => 3, 'email' => 'five@example.org']],
            'gallery' => [['dish_ref' => 3, 'image_file' => 'images/g.jpg']],
        ], $context);

        // Assert
        static::assertSame(1, $context->toSummary()->get(MemberSection::KIND_LIKES, Outcome::Skipped));
        static::assertSame(1, $context->toSummary()->get(MemberSection::KIND_GALLERY, Outcome::Skipped));
    }

    public function testAGalleryImageIsImportedWithItsOrderAndItsLocation(): void
    {
        // Arrange
        $target = $this->withId(new Dish(), 30);
        $image = $this->withId(new Image(), 70);
        $locations = $this->createMock(ImageLocationService::class);
        $locations->expects($this->once())->method('addLocation')->with(70, ImageType::PluginDishesPreview, 30);
        $context = $this->importContext($image);

        // Act
        $this->section(dishes: $this->dishes([], $target), locations: $locations)->import([
            'gallery' => [['dish_ref' => 3, 'image_file' => 'images/g.jpg', 'sort_order' => 4, 'created_at' => '2026-01-07T19:00:00+00:00']],
        ], $context);

        // Assert
        $galleryImage = $this->onlyPersisted(DishImage::class);
        static::assertSame($image, $galleryImage->getImage());
        static::assertSame(4, $galleryImage->getSortOrder());
        static::assertSame('2026-01-07 19:00', $galleryImage->getCreatedAt()?->format('Y-m-d H:i'));
        static::assertTrue($target->getGalleryImages()->contains($galleryImage));
    }

    public function testAGalleryImageWhoseFileIsMissingIsDropped(): void
    {
        // Arrange
        $context = $this->importContext();

        // Act
        $this->section(dishes: $this->dishes([], $this->withId(new Dish(), 30)))->import([
            'gallery' => [['dish_ref' => 3, 'image_file' => 'images/gone.jpg']],
        ], $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame(1, $context->toSummary()->get(MemberSection::KIND_GALLERY, Outcome::Dropped));
    }

    private function section(
        ?DishRepository $dishes = null,
        ?DishLikeRepository $likes = null,
        ?UserRepository $users = null,
        ?ImageLocationService $locations = null,
    ): MemberSection {
        return new MemberSection(
            $this->entityManager(),
            $dishes ?? $this->createStub(DishRepository::class),
            $likes ?? $this->createStub(DishLikeRepository::class),
            $users ?? $this->createStub(UserRepository::class),
            $locations ?? $this->createStub(ImageLocationService::class),
        );
    }

    /**
     * @param list<Dish> $found
     */
    private function dishes(array $found, ?Dish $target = null): DishRepository
    {
        $dishes = $this->createStub(DishRepository::class);
        $dishes->method('findBy')->willReturn($found);
        $dishes->method('find')->willReturn($target);

        return $dishes;
    }

    private function importContext(?Image $importedImage = null): ImportContext
    {
        $context = $this->context($importedImage);
        $context->mapItems('dish', [3 => 30]);

        return $context;
    }

    private function like(Dish $dish, int $userId): DishLike
    {
        return new DishLike()
            ->setDish($dish)
            ->setUserId($userId);
    }

    private function galleryImage(int $id, Dish $dish, Image $image, int $sortOrder): DishImage
    {
        $galleryImage = $this->withId(new DishImage(), $id);
        $galleryImage->setDish($dish);
        $galleryImage->setImage($image);
        $galleryImage->setSortOrder($sortOrder);
        $galleryImage->setCreatedAt(new DateTimeImmutable('2026-01-07 19:00:00+00:00'));

        return $galleryImage;
    }

    private function user(int $id, string $email): User
    {
        $user = $this->withId(new User(), $id);
        $user->setEmail($email);

        return $user;
    }
}
