<?php declare(strict_types=1);

namespace Tests\Unit\Form;

use App\Entity\Cms;
use App\Form\CmsType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CmsTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{0: bool}>
     */
    public static function editorProvider(): iterable
    {
        yield 'a platform admin decides the email footer and the locked flag' => [true];
        yield 'a group editor decides neither' => [false];
    }

    #[DataProvider('editorProvider')]
    public function testTheEmailFooterFlagIsOfferedOnlyToPlatformAdmins(bool $isAdmin): void
    {
        // Act
        $form = $this->factory()->create(CmsType::class, new Cms(), ['is_admin' => $isAdmin]);

        // Assert
        static::assertSame($isAdmin, $form->has('emailFooter'));
        static::assertSame($isAdmin, $form->has('locked'));
    }

    public function testAGroupEditorCannotSetTheFlagThroughThePostBody(): void
    {
        // Arrange
        $cms = new Cms();
        $form = $this->factory()->create(CmsType::class, $cms, ['is_admin' => false]);

        // Act
        $form->submit([
            'slug' => 'club-rules',
            'published' => '1',
            'emailFooter' => '1',
            'pageTitle' => 'Club Rules',
            'linkName' => 'Rules',
            'menuLocations' => [],
        ]);

        // Assert
        static::assertFalse($cms->isEmailFooter());
    }

    private function factory(): FormFactoryInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return Forms::createFormFactoryBuilder()->addType(new CmsType($translator))->getFormFactory();
    }
}
