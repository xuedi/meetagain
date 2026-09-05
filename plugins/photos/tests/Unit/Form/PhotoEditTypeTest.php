<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Form;

use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use App\Filter\Event\EventFilterResult;
use App\Filter\Event\EventFilterService;
use App\Item\TranslationFormHelper;
use App\Repository\EventRepository;
use App\Service\Config\LanguageService;
use App\Service\Item\AssociationService;
use PHPUnit\Framework\TestCase;
use Plugin\Photos\Entity\Photo;
use Plugin\Photos\Entity\PhotoTranslation;
use Plugin\Photos\Form\PhotoEditType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\RequestStack;

class PhotoEditTypeTest extends TestCase
{
    public function testTheTextsAreSeededFromTheStoredTranslations(): void
    {
        // Arrange
        $photo = new Photo();
        $photo->addTranslation(new PhotoTranslation()->setLanguage('en')->setTitle('Harbour')->setDescription('At dawn.'));

        // Act
        $form = $this->formFor($photo);

        // Assert
        static::assertSame('Harbour', $form->get('title-en')->getData());
        static::assertSame('At dawn.', $form->get('description-en')->getData());
        static::assertSame('', $form->get('title-de')->getData());
    }

    public function testThePictureCannotBeReplacedFromTheEditForm(): void
    {
        // Act
        $form = $this->formFor(new Photo());

        // Assert
        static::assertFalse($form->has('photoFile'));
    }

    public function testTheEventSelectOffersTheAccessibleOccurrencesAndAnEmptyChoice(): void
    {
        // Act
        $form = $this->formFor(new Photo());

        // Assert
        $field = $form->get(PhotoEditType::EVENT_FIELD);
        static::assertSame(['2026-08-14 - Photo Walk' => 21], $field->getConfig()->getOption('choices'));
        static::assertSame('photos_photo.choice_no_event', $field->getConfig()->getOption('placeholder'));
        static::assertNull($field->getData());
    }

    private function formFor(Photo $photo): FormInterface
    {
        $tagService = $this->createStub(TagService::class);
        $tagService->method('getAssignableChoices')->willReturn([]);
        $tagService->method('getDepths')->willReturn([]);
        $tagService->method('getParents')->willReturn([]);

        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getAdminFilteredEnabledCodes')->willReturn(['en', 'de']);

        $eventRepository = $this->createStub(EventRepository::class);
        $eventRepository->method('getOccurrenceChoices')->willReturn(['2026-08-14 - Photo Walk' => 21]);

        $eventFilterService = $this->createStub(EventFilterService::class);
        $eventFilterService->method('getEventIdFilter')->willReturn(EventFilterResult::noFilter());

        $associations = $this->createStub(AssociationService::class);
        $associations->method('eventIdsForItem')->willReturn([]);

        $type = new PhotoEditType(
            new TranslationFormHelper($languageService),
            new AssignmentFormHelper($tagService, new RequestStack()),
            $eventRepository,
            $eventFilterService,
            $associations,
            new RequestStack(),
        );

        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([$type], []))
            ->getFormFactory()
            ->create(PhotoEditType::class, null, ['photo' => $photo]);
    }
}
