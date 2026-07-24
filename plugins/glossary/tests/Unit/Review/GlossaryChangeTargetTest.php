<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Review;

use App\Entity\User;
use App\Item\Taxonomy\ItemTaxonomyService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Review\GlossaryChangeTarget;
use Plugin\Glossary\Service\ConfigService;
use Plugin\Glossary\Service\GlossaryService;
use Plugin\Glossary\ValueObject\Config;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class GlossaryChangeTargetTest extends TestCase
{
    #[DataProvider('validationCases')]
    public function testValidate(?Glossary $entry, string $field, ?string $value, ?string $expected): void
    {
        // Arrange
        $target = $this->makeTarget(entry: $entry);

        // Act
        $error = $target->validate(1, $field, $value);

        // Assert
        self::assertSame($expected, $error);
    }

    public static function validationCases(): iterable
    {
        $entry = new Glossary();

        yield 'missing entry fails every field' => [null, GlossaryChangeTarget::FIELD_PHRASE, 'x', 'glossary.validation_entry_missing'];
        yield 'blank phrase is rejected' => [$entry, GlossaryChangeTarget::FIELD_PHRASE, '  ', 'glossary.validation_phrase_blank'];
        yield 'non-blank phrase is fine' => [$entry, GlossaryChangeTarget::FIELD_PHRASE, '你好', null];
        yield 'unknown category id is rejected' => [$entry, GlossaryChangeTarget::FIELD_CATEGORY, '99', 'glossary.validation_category_unknown'];
        yield 'known category id is fine' => [$entry, GlossaryChangeTarget::FIELD_CATEGORY, '3', null];
        yield 'clearing the category is fine' => [$entry, GlossaryChangeTarget::FIELD_CATEGORY, null, null];
        yield 'explanation has no constraints' => [$entry, GlossaryChangeTarget::FIELD_EXPLANATION, '', null];
    }

    public function testFormatValueResolvesCategoryLabels(): void
    {
        // Arrange
        $taxonomyService = $this->createStub(ItemTaxonomyService::class);
        $taxonomyService->method('categoryLabelForId')->willReturn('Slang');
        $target = $this->makeTarget(taxonomyService: $taxonomyService);

        // Act & Assert
        self::assertSame('Slang', $target->formatValue(GlossaryChangeTarget::FIELD_CATEGORY, '3'));
        self::assertSame('', $target->formatValue(GlossaryChangeTarget::FIELD_CATEGORY, null));
        self::assertSame('plain', $target->formatValue(GlossaryChangeTarget::FIELD_PHRASE, 'plain'));
    }

    public function testFieldLabelsPreferConfiguredLabels(): void
    {
        // Arrange
        $target = $this->makeTarget();

        // Act & Assert: primary label is configured, definition falls back to the translated default
        self::assertSame('Term', $target->getFieldLabel(GlossaryChangeTarget::FIELD_PHRASE));
        self::assertSame('glossary.label_explanation', $target->getFieldLabel(GlossaryChangeTarget::FIELD_EXPLANATION));
    }

    public function testCanProposeRequiresAnApprovedEntry(): void
    {
        // Arrange
        $user = new User();

        // Act & Assert
        self::assertTrue($this->makeTarget(entry: (new Glossary())->setApproved(true))->canPropose($user, 1));
        self::assertFalse($this->makeTarget(entry: (new Glossary())->setApproved(false))->canPropose($user, 1));
        self::assertFalse($this->makeTarget(entry: null)->canPropose($user, 1));
        self::assertFalse($this->makeTarget(entry: (new Glossary())->setApproved(true), granted: false)->canPropose($user, 1));
    }

    public function testCanReviewRequiresRoleAndExistingEntry(): void
    {
        // Arrange
        $user = new User();

        // Act & Assert
        self::assertTrue($this->makeTarget(entry: new Glossary())->canReview($user, 1));
        self::assertFalse($this->makeTarget(entry: null)->canReview($user, 1));
        self::assertFalse($this->makeTarget(entry: new Glossary(), granted: false)->canReview($user, 1));
    }

    public function testTargetLabelIsThePhraseOrNull(): void
    {
        // Act & Assert
        self::assertSame('你好', $this->makeTarget(entry: (new Glossary())->setPhrase('你好'))->getTargetLabel(1));
        self::assertNull($this->makeTarget(entry: null)->getTargetLabel(1));
    }

    public function testApplyDelegatesToTheService(): void
    {
        // Arrange
        $service = $this->createMock(GlossaryService::class);
        $service->expects(self::once())
            ->method('applyChange')
            ->with(1, GlossaryChangeTarget::FIELD_PHRASE, 'new');
        $target = $this->makeTarget(service: $service);

        // Act
        $target->apply(1, GlossaryChangeTarget::FIELD_PHRASE, 'new');
    }

    private function makeTarget(
        ?Glossary $entry = null,
        bool $granted = true,
        ?ItemTaxonomyService $taxonomyService = null,
        ?GlossaryService $service = null,
    ): GlossaryChangeTarget {
        if ($service === null) {
            $service = $this->createStub(GlossaryService::class);
            $service->method('get')->willReturn($entry);
        }

        $configService = $this->createStub(ConfigService::class);
        $configService->method('getConfig')->willReturn(Config::fromArray([
            'primaryLabel' => 'Term',
            'taxonomy' => [
                'categoriesEnabled' => true,
                'tagsEnabled' => false,
                'categories' => [['id' => 3, 'labels' => ['en' => 'Slang']]],
                'tags' => [],
            ],
        ]));

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new GlossaryChangeTarget(
            $service,
            $configService,
            $taxonomyService ?? $this->createStub(ItemTaxonomyService::class),
            $security,
            $this->createStub(RouterInterface::class),
            new RequestStack(),
            $translator,
        );
    }
}
