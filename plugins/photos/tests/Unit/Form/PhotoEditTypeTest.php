<?php declare(strict_types=1);

namespace Plugin\Photos\Tests\Unit\Form;

use App\Item\Tag\AssignmentFormHelper;
use App\Item\Tag\TagService;
use App\Item\TranslationFormHelper;
use App\Service\Config\LanguageService;
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

    private function formFor(Photo $photo): FormInterface
    {
        $tagService = $this->createStub(TagService::class);
        $tagService->method('getChoices')->willReturn([]);
        $tagService->method('getDepths')->willReturn([]);
        $tagService->method('getParents')->willReturn([]);

        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getAdminFilteredEnabledCodes')->willReturn(['en', 'de']);

        $type = new PhotoEditType(new TranslationFormHelper($languageService), new AssignmentFormHelper($tagService, new RequestStack()));

        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([$type], []))
            ->getFormFactory()
            ->create(PhotoEditType::class, null, ['photo' => $photo]);
    }
}
